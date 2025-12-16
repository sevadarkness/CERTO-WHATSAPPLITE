<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Sales;

use PDO;
use DateTimeImmutable;
use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\LeadRepository;
use RedeAlabama\Repositories\WhatsappMessageRepository;

/**
 * IA Vendedora PRO: gera combos/ofertas inteligentes para um cliente específico.
 *
 * Versão V105: implementação funcional.
 */
final class SalesSmartOfferService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly LlmService $llm,
        private readonly LeadRepository $leads,
        private readonly WhatsappMessageRepository $messages
    ) {
    }

    /**
     * Gera uma oferta inteligente (combo + mensagem pronta) para um cliente.
     *
     * @return array{
     *   ok: bool,
     *   combo?: array|null,
     *   mensagem_sugerida?: string|null,
     *   proposta_json?: array|null,
     *   log_id?: int|null,
     *   error?: string
     * }
     */
    public function gerarOferta(
        int $tenantId,
        int $vendedorId,
        int $clienteId,
        string $threadId
    ): array {
        $threadId = trim($threadId);

        if ($tenantId <= 0) {
            return [
                'ok'    => false,
                'error' => 'tenantId inválido ao gerar oferta IA.',
            ];
        }

        if ($clienteId <= 0 || $threadId === '') {
            return [
                'ok'    => false,
                'error' => 'Parâmetros insuficientes para gerar oferta IA.',
            ];
        }

        // 1) Carregar lead/cliente
        $lead = $this->leads->findById($clienteId);
        if ($lead === null) {
            return [
                'ok'    => false,
                'error' => 'Cliente não encontrado para gerar oferta IA.',
            ];
        }

        $clienteNome     = (string)($lead['nome'] ?? '');
        $clienteTelefone = (string)($lead['telefone'] ?? '');

        // 2) Carregar templates de oferta
        $stmt = $this->pdo->prepare("
            SELECT id, nome_combo, descricao, produtos_json,
                   desconto_max_percent, lucro_min_percent
            FROM sales_offer_templates
            WHERE tenant_id = :tenant_id
              AND ativo = 1
        ");
        $stmt->execute([':tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return [
                'ok'    => false,
                'error' => 'Nenhum template de oferta configurado para este tenant.',
            ];
        }

        $combos = [];
        foreach ($rows as $row) {
            $produtos = json_decode((string)($row['produtos_json'] ?? '[]'), true);
            if (!is_array($produtos)) {
                $produtos = [];
            }

            $combos[] = [
                'id'                   => (int)$row['id'],
                'nome_combo'           => (string)$row['nome_combo'],
                'descricao'            => $row['descricao'],
                'produtos'             => $produtos,
                'desconto_max_percent' => (float)$row['desconto_max_percent'],
                'lucro_min_percent'    => (float)$row['lucro_min_percent'],
            ];
        }

        // 3) Histórico de interações (últimas mensagens da thread em whatsapp_messages)
        $stmt = $this->pdo->prepare("
            SELECT direction, conteudo, created_at
            FROM whatsapp_messages
            WHERE thread_id = :thread_id
            ORDER BY id DESC
            LIMIT 20
        ");
        $stmt->execute([':thread_id' => $threadId]);
        $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $historicoMensagens = [];
        foreach ($msgs as $m) {
            $historicoMensagens[] = [
                'direction'  => $m['direction'] ?? null,
                'conteudo'   => $m['conteudo'] ?? null,
                'created_at' => $m['created_at'] ?? null,
            ];
        }

        // 4) Montar contexto para a prompt factory
        $context = [
            'tenant' => [
                'id'       => $tenantId,
                'nome'     => null,
                'segmento' => null,
            ],
            'cliente' => [
                'id'                    => $clienteId,
                'nome'                  => $clienteNome ?: 'Cliente',
                'telefone'              => $clienteTelefone,
                'ticket_medio'          => null,
                'total_pedidos'         => null,
                'ultima_compra_at'      => null,
                'categorias_preferidas' => [],
            ],
            'historico_interacoes' => [
                'ultimos_produtos_consultados' => [],
                'ultimas_mensagens'            => $historicoMensagens,
            ],
            'combos_candidatos' => $combos,
            'restricoes_gerais' => [
                'estoque_considerado' => false,
                'comentario'          => 'Respeitar apenas desconto_max_percent e lucro_min_percent informados em cada combo.',
            ],
        ];

        // 5) Chamar LLM
        $prompts = SalesPromptFactory::buildSmartOfferPrompts($context);

        $rawResponse = $this->llm->generateChatCompletion(
            $prompts['user_prompt'],
            $prompts['system_prompt']
        );

        if ($rawResponse === null) {
            return [
                'ok'    => false,
                'error' => 'Falha ao chamar a LLM para oferta inteligente.',
            ];
        }

        $rawResponseTrimmed = trim($rawResponse);
        $decoded = json_decode($rawResponseTrimmed, true);

        if (!is_array($decoded)) {
            return [
                'ok'    => false,
                'error' => 'Resposta da LLM para oferta inteligente não é um JSON válido.',
            ];
        }

        $ofertaViavel = (bool)($decoded['oferta_viavel'] ?? false);
        if (!$ofertaViavel) {
            $motivo = (string)($decoded['motivo_indeferimento'] ?? 'Oferta marcada como não viável pela IA.');

            $logId = $this->insertLog(
                $tenantId,
                $vendedorId,
                $clienteId,
                $threadId,
                $decoded,
                'ia'
            );

            return [
                'ok'            => false,
                'error'         => $motivo,
                'log_id'        => $logId,
                'proposta_json' => $decoded,
            ];
        }

        $combo            = $decoded['combo_escolhido'] ?? null;
        $mensagemSugerida = $decoded['mensagem_sugerida'] ?? null;

        // 6) Registrar log
        $logId = $this->insertLog(
            $tenantId,
            $vendedorId,
            $clienteId,
            $threadId,
            $decoded,
            'ia'
        );

        return [
            'ok'                => true,
            'combo'             => $combo,
            'mensagem_sugerida' => $mensagemSugerida,
            'proposta_json'     => $decoded,
            'log_id'            => $logId,
        ];
    }

    /**
     * Registra que o vendedor aceitou/aplicou a oferta sugerida.
     */
    public function registrarAceiteOferta(
        int $tenantId,
        int $vendedorId,
        int $logId,
        ?float $ticketGerado
    ): bool {
        if ($logId <= 0 || $tenantId <= 0) {
            return false;
        }

        $sql = "
            UPDATE sales_ai_offers_log
            SET aceita_pelo_vendedor = 1,
                ticket_gerado        = :ticket_gerado
            WHERE id = :id
              AND tenant_id = :tenant_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':ticket_gerado' => $ticketGerado,
            ':id'            => $logId,
            ':tenant_id'     => $tenantId,
        ]);

        return $ok;
    }

    /**
     * Insere registro em sales_ai_offers_log.
     *
     * @param array<string,mixed> $proposta
     */
    private function insertLog(
        int $tenantId,
        int $vendedorId,
        int $clienteId,
        string $threadId,
        array $proposta,
        string $origem
    ): int {
        $sql = "
            INSERT INTO sales_ai_offers_log
                (tenant_id, vendedor_id, cliente_id, thread_id, proposta_json, origem, aceita_pelo_vendedor, cliente_aceitou, ticket_gerado, created_at)
            VALUES
                (:tenant_id, :vendedor_id, :cliente_id, :thread_id, :proposta_json, :origem, 0, NULL, NULL, :created_at)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id'     => $tenantId,
            ':vendedor_id'   => $vendedorId,
            ':cliente_id'    => $clienteId,
            ':thread_id'     => $threadId,
            ':proposta_json' => json_encode($proposta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':origem'        => $origem,
            ':created_at'    => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
