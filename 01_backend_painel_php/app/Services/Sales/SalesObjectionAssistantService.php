<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Sales;

use PDO;
use DateTimeImmutable;
use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\WhatsappMessageRepository;

/**
 * Copiloto de Vendedor: IA focada em objeções.
 *
 * V105: implementação básica funcional.
 */
final class SalesObjectionAssistantService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LlmService $llm,
        private readonly WhatsappMessageRepository $messages
    ) {
    }

    /**
     * Lista objeções cadastradas para o tenant.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listObjections(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, tenant_id, codigo, titulo, descricao, ativo, created_at, updated_at
            FROM sales_objection_library
            WHERE tenant_id = :tenant_id
              AND ativo = 1
            ORDER BY titulo ASC
        ");
        $stmt->execute([':tenant_id' => $tenantId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    }

    /**
     * Gera uma resposta IA para uma objeção específica.
     *
     * @return array{ok:bool,resposta_sugerida?:string,log_id?:int,error?:string}
     */
    public function resolveObjection(
        int $tenantId,
        int $vendedorId,
        int $clienteId,
        string $threadId,
        string $codigoObjection
    ): array {
        $threadId        = trim($threadId);
        $codigoObjection = trim($codigoObjection);

        if ($tenantId <= 0 || $vendedorId <= 0 || $clienteId <= 0 || $threadId === '' || $codigoObjection === '') {
            return [
                'ok'    => false,
                'error' => 'Parâmetros inválidos ao resolver objeção.',
            ];
        }

        // 1) Carrega definição da objeção
        $stmt = $this->pdo->prepare("
            SELECT codigo, titulo, descricao
            FROM sales_objection_library
            WHERE tenant_id = :tenant_id
              AND codigo = :codigo
              AND ativo = 1
            LIMIT 1
        ");
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':codigo'    => $codigoObjection,
        ]);
        $obj = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$obj) {
            return [
                'ok'    => false,
                'error' => 'Objeção não encontrada ou inativa.',
            ];
        }

        // 2) Histórico da conversa por thread
        $stmtHist = $this->pdo->prepare("
            SELECT direction, conteudo, created_at
            FROM whatsapp_messages
            WHERE thread_id = :thread_id
            ORDER BY id DESC
            LIMIT 20
        ");
        $stmtHist->execute([':thread_id' => $threadId]);
        $msgs = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

        $mensagensCliente  = [];
        $mensagensVendedor = [];

        foreach ($msgs as $m) {
            $texto = (string)($m['conteudo'] ?? '');
            if ($texto === '') {
                continue;
            }
            if (($m['direction'] ?? '') === 'in') {
                $mensagensCliente[] = $texto;
            } else {
                $mensagensVendedor[] = $texto;
            }
        }

        // 3) Monta contexto para a fábrica de prompts
        $context = [
            'tenant' => [
                'id'       => $tenantId,
                'nome'     => null,
                'segmento' => null,
            ],
            'objection' => [
                'codigo'    => (string)$obj['codigo'],
                'titulo'    => (string)$obj['titulo'],
                'descricao' => (string)($obj['descricao'] ?? ''),
            ],
            'conversa' => [
                'mensagens_cliente'  => array_reverse($mensagensCliente),
                'mensagens_vendedor' => array_reverse($mensagensVendedor),
            ],
            'produto' => [
                'nome'              => null,
                'preco'             => null,
                'beneficios_resumo' => null,
            ],
            'cliente' => [
                'id' => $clienteId,
            ],
            'vendedor' => [
                'id' => $vendedorId,
            ],
        ];

        $prompts = SalesPromptFactory::buildObjectionPrompts($context);

        $resposta = $this->llm->generateChatCompletion(
            $prompts['user_prompt'],
            $prompts['system_prompt']
        );

        if ($resposta === null || trim($resposta) === '') {
            return [
                'ok'    => false,
                'error' => 'Falha ao chamar IA para tratar objeção.',
            ];
        }

        $resposta = trim($resposta);

        // 4) Log
        $stmtLog = $this->pdo->prepare("
            INSERT INTO sales_objection_ai_log
                (tenant_id, vendedor_id, cliente_id, thread_id,
                 objection_codigo, prompt_usado, resposta_ia,
                 vendedor_editou, cliente_aceitou, created_at)
            VALUES
                (:tenant_id, :vendedor_id, :cliente_id, :thread_id,
                 :objection_codigo, :prompt_usado, :resposta_ia,
                 0, NULL, :created_at)
        ");
        $stmtLog->execute([
            ':tenant_id'        => $tenantId,
            ':vendedor_id'      => $vendedorId,
            ':cliente_id'       => $clienteId,
            ':thread_id'        => $threadId,
            ':objection_codigo' => $codigoObjection,
            ':prompt_usado'     => $prompts['user_prompt'],
            ':resposta_ia'      => $resposta,
            ':created_at'       => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        $logId = (int)$this->pdo->lastInsertId();

        return [
            'ok'               => true,
            'resposta_sugerida'=> $resposta,
            'log_id'           => $logId,
        ];
    }

    /**
     * Atualiza flags de feedback do vendedor/cliente no log de objeções.
     */
    public function registerFeedback(
        int $tenantId,
        int $logId,
        bool $vendedorEditou,
        ?bool $clienteAceitou
    ): bool {
        if ($tenantId <= 0 || $logId <= 0) {
            return false;
        }

        $sql = "
            UPDATE sales_objection_ai_log
            SET vendedor_editou = :vendedor_editou,
                cliente_aceitou = :cliente_aceitou
            WHERE id = :id
              AND tenant_id = :tenant_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':vendedor_editou' => $vendedorEditou ? 1 : 0,
            ':cliente_aceitou' => $clienteAceitou,
            ':id'              => $logId,
            ':tenant_id'       => $tenantId,
        ]);

        return true;
    }
}
