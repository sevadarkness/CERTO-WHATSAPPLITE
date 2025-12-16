<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Sales;

use PDO;
use DateTimeImmutable;
use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\LeadRepository;
use RedeAlabama\Repositories\WhatsappMessageRepository;

/**
 * Campanhas de recuperação de vendas (definição + segmentação).
 *
 * V105: implementação básica funcional.
 */
final class SalesRecoveryCampaignService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LlmService $llm,
        private readonly LeadRepository $leads,
        private readonly WhatsappMessageRepository $messages
    ) {
    }

    /**
     * Lista campanhas de recuperação do tenant.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listarCampanhas(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, tenant_id, nome, tipo_segmento, dias_inatividade,
                   ativo, janela_envio_hora_inicial, janela_envio_hora_final,
                   limite_envios_dia_por_tenant, created_at, updated_at
            FROM sales_recovery_campaigns
            WHERE tenant_id = :tenant_id
            ORDER BY id DESC
        ");
        $stmt->execute([':tenant_id' => $tenantId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows ?: [];
    }

    /**
     * Cria ou atualiza campanha de recuperação.
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool,campaign_id?:int,error?:string}
     */
    public function salvarCampanha(int $tenantId, array $payload): array
    {
        $nome         = trim((string)($payload['nome'] ?? ''));
        $tipoSegmento = trim((string)($payload['tipo_segmento'] ?? ''));
        $diasInat     = isset($payload['dias_inatividade']) ? (int)$payload['dias_inatividade'] : null;
        $ativo        = isset($payload['ativo']) ? (int)$payload['ativo'] : 1;

        $horaIni = isset($payload['janela_envio_hora_inicial']) ? (int)$payload['janela_envio_hora_inicial'] : 9;
        $horaFim = isset($payload['janela_envio_hora_final'])   ? (int)$payload['janela_envio_hora_final']   : 21;
        $limite  = isset($payload['limite_envios_dia_por_tenant'])
            ? (int)$payload['limite_envios_dia_por_tenant']
            : 500;

        if ($nome === '' || $tipoSegmento === '') {
            return [
                'ok'    => false,
                'error' => 'Nome e tipo_segmento são obrigatórios para a campanha.',
            ];
        }

        $agora = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $id    = isset($payload['id']) ? (int)$payload['id'] : null;

        if ($id !== null && $id > 0) {
            $sql = "
                UPDATE sales_recovery_campaigns
                SET nome = :nome,
                    tipo_segmento = :tipo_segmento,
                    dias_inatividade = :dias_inatividade,
                    ativo = :ativo,
                    janela_envio_hora_inicial = :hora_ini,
                    janela_envio_hora_final   = :hora_fim,
                    limite_envios_dia_por_tenant = :limite,
                    updated_at = :updated_at
                WHERE id = :id
                  AND tenant_id = :tenant_id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nome'             => $nome,
                ':tipo_segmento'    => $tipoSegmento,
                ':dias_inatividade' => $diasInat,
                ':ativo'            => $ativo,
                ':hora_ini'         => $horaIni,
                ':hora_fim'         => $horaFim,
                ':limite'           => $limite,
                ':updated_at'       => $agora,
                ':id'               => $id,
                ':tenant_id'        => $tenantId,
            ]);

            return [
                'ok'          => true,
                'campaign_id' => $id,
            ];
        }

        $sql = "
            INSERT INTO sales_recovery_campaigns
                (tenant_id, nome, tipo_segmento, dias_inatividade, ativo,
                 janela_envio_hora_inicial, janela_envio_hora_final,
                 limite_envios_dia_por_tenant, created_at, updated_at)
            VALUES
                (:tenant_id, :nome, :tipo_segmento, :dias_inatividade, :ativo,
                 :hora_ini, :hora_fim, :limite, :created_at, :updated_at)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id'        => $tenantId,
            ':nome'             => $nome,
            ':tipo_segmento'    => $tipoSegmento,
            ':dias_inatividade' => $diasInat,
            ':ativo'            => $ativo,
            ':hora_ini'         => $horaIni,
            ':hora_fim'         => $horaFim,
            ':limite'           => $limite,
            ':created_at'       => $agora,
            ':updated_at'       => $agora,
        ]);

        $newId = (int)$this->pdo->lastInsertId();

        return [
            'ok'          => true,
            'campaign_id' => $newId,
        ];
    }

    /**
     * Gera segmento para uma campanha, populando sales_recovery_enrollments.
     *
     * Implementação simples baseada em histórico de WhatsApp:
     *  - tipo_segmento = 'inativo'         => contatos com último contato antigo
     *  - tipo_segmento = 'perguntou_preco' => contatos com mensagens contendo 'preço'/'preco'/'valor'
     *
     * @return int quantidade de clientes enfileirados
     */
    public function gerarSegmentoParaCampanha(int $tenantId, int $campaignId): int
    {
        if ($tenantId <= 0 || $campaignId <= 0) {
            return 0;
        }

        // Carrega campanha
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM sales_recovery_campaigns
            WHERE id = :id AND tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id'        => $campaignId,
            ':tenant_id' => $tenantId,
        ]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            return 0;
        }

        $tipoSegmento = (string)$campaign['tipo_segmento'];
        $diasInat     = (int)($campaign['dias_inatividade'] ?? 0);
        $now          = new DateTimeImmutable('now');

        $cutoff = null;
        if ($diasInat > 0) {
            $cutoff = $now->modify('-' . $diasInat . ' days')->format('Y-m-d H:i:s');
        }

        $telefones = [];

        if ($tipoSegmento === 'perguntou_preco') {
            // Clientes que perguntaram preço/valor
            $sql = "
                SELECT DISTINCT contato_telefone
                FROM whatsapp_messages
                WHERE direction = 'in'
                  AND (
                      conteudo LIKE '%preco%'
                      OR conteudo LIKE '%preço%'
                      OR conteudo LIKE '%valor%'
                  )
            ";
            if ($cutoff !== null) {
                $sql .= " AND created_at <= :cutoff";
            }

            $stmt = $this->pdo->prepare($sql);
            $params = [];
            if ($cutoff !== null) {
                $params[':cutoff'] = $cutoff;
            }
            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $tel = trim((string)$r['contato_telefone']);
                if ($tel !== '') {
                    $telefones[$tel] = true;
                }
            }
        } else {
            // inativo (padrão): último contato antigo
            $sql = "
                SELECT contato_telefone, MAX(created_at) AS last_at
                FROM whatsapp_messages
                GROUP BY contato_telefone
            ";
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($cutoff !== null) {
                foreach ($rows as $r) {
                    $tel    = trim((string)$r['contato_telefone']);
                    $lastAt = (string)($r['last_at'] ?? '');
                    if ($tel === '' || $lastAt === '') {
                        continue;
                    }
                    if ($lastAt <= $cutoff) {
                        $telefones[$tel] = true;
                    }
                }
            }
        }

        if (!$telefones) {
            return 0;
        }

        $inserted = 0;
        $nowStr   = $now->format('Y-m-d H:i:s');

        // Para cada telefone, tenta localizar lead e criar enrollment
        foreach (array_keys($telefones) as $telefone) {
            $lead = $this->leads->findByTelefone($telefone);
            if ($lead === null) {
                continue;
            }

            $clienteId = (int)$lead['id'];

            // Thread arbitrária: pega qualquer thread ligada a esse telefone
            $stmt = $this->pdo->prepare("
                SELECT thread_id
                FROM whatsapp_messages
                WHERE contato_telefone = :tel
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([':tel' => $telefone]);
            $threadRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $threadId  = $threadRow ? (string)$threadRow['thread_id'] : '';

            $stmtIns = $this->pdo->prepare("
                INSERT INTO sales_recovery_enrollments
                    (tenant_id, campaign_id, cliente_id, thread_id, status, proxima_execucao_at, ultima_execucao_at, created_at, updated_at)
                VALUES
                    (:tenant_id, :campaign_id, :cliente_id, :thread_id, 'pendente', :proxima_execucao_at, NULL, :created_at, NULL)
            ");
            $stmtIns->execute([
                ':tenant_id'           => $tenantId,
                ':campaign_id'         => $campaignId,
                ':cliente_id'          => $clienteId,
                ':thread_id'           => $threadId,
                ':proxima_execucao_at' => $nowStr,
                ':created_at'          => $nowStr,
            ]);

            $inserted++;
        }

        return $inserted;
    }
}
