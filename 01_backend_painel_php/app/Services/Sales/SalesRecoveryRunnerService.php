<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Sales;

use PDO;
use DateTimeImmutable;
use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\WhatsappMessageRepository;

/**
 * Runner de campanhas de recuperação.
 *
 * V105: implementação funcional básica.
 */
final class SalesRecoveryRunnerService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LlmService $llm,
        private readonly WhatsappMessageRepository $messages
    ) {
    }

    /**
     * Processa um lote de enrollments pendentes para um tenant.
     *
     * @return array{ok:bool,total_processado:int,error?:string}
     */
    public function processarLote(
        int $tenantId,
        int $maxPorExecucao = 100
    ): array {
        if ($tenantId <= 0) {
            return [
                'ok' => false,
                'total_processado' => 0,
                'error' => 'tenantId inválido no runner de campanhas.',
            ];
        }

        if ($maxPorExecucao <= 0) {
            $maxPorExecucao = 100;
        }

        $agora    = new DateTimeImmutable('now');
        $agoraStr = $agora->format('Y-m-d H:i:s');

        // 1) Buscar enrollments pendentes/agendados
        $sql = "
            SELECT *
            FROM sales_recovery_enrollments
            WHERE tenant_id = :tenant_id
              AND status IN ('pendente', 'agendado')
              AND (proxima_execucao_at IS NULL OR proxima_execucao_at <= :now)
            ORDER BY proxima_execucao_at ASC, id ASC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':now', $agoraStr);
        $stmt->bindValue(':limit', $maxPorExecucao, PDO::PARAM_INT);
        $stmt->execute();

        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$enrollments) {
            return [
                'ok' => true,
                'total_processado' => 0,
            ];
        }

        $totalProcessado = 0;

        foreach ($enrollments as $enrollment) {
            $id         = (int)$enrollment['id'];
            $clienteId  = (int)$enrollment['cliente_id'];
            $campaignId = (int)$enrollment['campaign_id'];
            $threadId   = (string)($enrollment['thread_id'] ?? '');

            // 2) Carrega dados mínimos da campanha
            $stmtCamp = $this->pdo->prepare("
                SELECT nome, tipo_segmento, dias_inatividade
                FROM sales_recovery_campaigns
                WHERE id = :id AND tenant_id = :tenant_id
                LIMIT 1
            ");
            $stmtCamp->execute([
                ':id'        => $campaignId,
                ':tenant_id' => $tenantId,
            ]);
            $camp = $stmtCamp->fetch(PDO::FETCH_ASSOC);
            if (!$camp) {
                $this->marcarComoErro($id, 'campanha_inexistente');
                continue;
            }

            // 3) Carrega contexto básico de WhatsApp (nome/telefone + histórico)
            $contatoNome     = 'Cliente';
            $contatoTelefone = '';

            if ($threadId !== '') {
                $stmtInfo = $this->pdo->prepare("
                    SELECT contato_nome, contato_telefone
                    FROM whatsapp_messages
                    WHERE thread_id = :thread_id
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmtInfo->execute([':thread_id' => $threadId]);
                $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                if ($info) {
                    $contatoNome     = (string)($info['contato_nome'] ?? 'Cliente');
                    $contatoTelefone = (string)($info['contato_telefone'] ?? '');
                }
            }

            if ($threadId !== '') {
                $stmtHist = $this->pdo->prepare("
                    SELECT direction, conteudo, created_at
                    FROM whatsapp_messages
                    WHERE thread_id = :thread_id
                    ORDER BY id DESC
                    LIMIT 15
                ");
                $stmtHist->execute([':thread_id' => $threadId]);
            } else {
                $stmtHist = $this->pdo->prepare("
                    SELECT direction, conteudo, created_at
                    FROM whatsapp_messages
                    WHERE contato_telefone = :telefone
                    ORDER BY id DESC
                    LIMIT 15
                ");
                $stmtHist->execute([':telefone' => $contatoTelefone]);
            }

            $msgs = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
            $historicoMensagens = [];
            foreach ($msgs as $m) {
                $historicoMensagens[] = [
                    'direction'  => $m['direction'] ?? null,
                    'conteudo'   => $m['conteudo'] ?? null,
                    'created_at' => $m['created_at'] ?? null,
                ];
            }

            // 4) Monta contexto para a fábrica de prompts
            $context = [
                'tenant' => [
                    'id'       => $tenantId,
                    'nome'     => null,
                    'segmento' => null,
                ],
                'campanha' => [
                    'id'               => $campaignId,
                    'nome'             => (string)$camp['nome'],
                    'tipo_segmento'    => (string)$camp['tipo_segmento'],
                    'dias_inatividade' => (int)($camp['dias_inatividade'] ?? 0),
                ],
                'cliente' => [
                    'id'                       => $clienteId,
                    'nome'                     => $contatoNome,
                    'telefone'                 => $contatoTelefone,
                    'ja_comprou'               => null,
                    'ticket_medio'             => null,
                    'ultima_interacao_resumo'  => null,
                    'produto_interesse_resumo' => null,
                ],
                'historico_mensagens' => $historicoMensagens,
                'historico_pedidos'   => [],
            ];

            $prompts = SalesPromptFactory::buildRecoveryPrompts($context);

            $mensagem = $this->llm->generateChatCompletion(
                $prompts['user_prompt'],
                $prompts['system_prompt']
            );

            if ($mensagem === null || trim($mensagem) === '') {
                $this->marcarComoErro($id, 'llm_sem_resposta');
                continue;
            }

            $mensagem = trim($mensagem);

            // 5) Registra mensagem como saída IA (log). O envio real seguirá o fluxo padrão.
            $threadToUse = $threadId !== '' ? $threadId : ('recovery-' . $campaignId . '-' . $clienteId);
            $this->messages->storeOutgoingIaSuggestion(
                $threadToUse,
                $contatoNome,
                $contatoTelefone,
                $mensagem,
                null
            );

            // 6) Atualiza enrollment
            $stmtUpd = $this->pdo->prepare("
                UPDATE sales_recovery_enrollments
                SET status = 'enviado',
                    ultima_execucao_at = :ultima_execucao_at,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $stmtUpd->execute([
                ':ultima_execucao_at' => $agoraStr,
                ':updated_at'         => $agoraStr,
                ':id'                 => $id,
            ]);

            $totalProcessado++;
        }

        return [
            'ok' => true,
            'total_processado' => $totalProcessado,
        ];
    }

    private function marcarComoErro(int $enrollmentId, string $motivo): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE sales_recovery_enrollments
            SET status = :status,
                updated_at = :updated_at
            WHERE id = :id
        ");
        $stmt->execute([
            ':status'     => $motivo,
            ':updated_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ':id'         => $enrollmentId,
        ]);
    }
}
