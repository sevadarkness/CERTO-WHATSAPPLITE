<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Remarketing;

use PDO;
use DateTimeImmutable;
use RuntimeException;
use RedeAlabama\Repositories\Screens\ApiRemarketingSegmentosRepository;

/**
 * RemarketingSegmentosService
 *
 * Orquestra segmentação de clientes para campanhas de remarketing.
 * Implementa diretamente a lógica que antes estava em api_remarketing_segmentos.php.
 */
final class RemarketingSegmentosService
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Gera segmentos de clientes a partir de filtros enviados.
     *
     * @param array $body
     * @param array $query
     * @param array $currentUser
     * @return array
     */
    public function gerarSegmentos(array $body, array $query, array $currentUser): array
    {
        try {
            $params = [];
            $whereParts = [];
            $whereParts[] = "telefone_cliente IS NOT NULL";
            $whereParts[] = "telefone_cliente <> ''";
            // Não entra avaria como venda para remarketing de consumo normal
            $whereParts[] = "(produto_avariado IS NULL OR produto_avariado = 0)";

            // Filtro por período (opcional) – usando query, como no código original
            if (!empty($query['data_inicio'])) {
                $dataInicio = $query['data_inicio'] . ' 00:00:00';
                $whereParts[] = "data_venda >= :data_inicio";
                $params[':data_inicio'] = $dataInicio;
            }
            if (!empty($query['data_fim'])) {
                $dataFim = $query['data_fim'] . ' 23:59:59';
                $whereParts[] = "data_venda <= :data_fim";
                $params[':data_fim'] = $dataFim;
            }

            // Filtro por vendedor (opcional)
            if (!empty($query['vendedor_id'])) {
                $whereParts[] = "id_vendedor = :vendedor_id";
                $params[':vendedor_id'] = (int) $query['vendedor_id'];
            }

            // Filtro por produto (opcional)
            if (!empty($query['produto_id'])) {
                $whereParts[] = "produto_id = :produto_id";
                $params[':produto_id'] = (int) $query['produto_id'];
            }

            $whereSql = implode(' AND ', $whereParts);

            // Consolida histórico por telefone (cliente)
            $sql = "
                SELECT
                    telefone_cliente AS telefone,
                    MAX(COALESCE(NULLIF(TRIM(nome_cliente), ''), NULLIF(TRIM(cliente_nome), ''))) AS nome,
                    COUNT(*) AS total_vendas,
                    SUM(COALESCE(valor_total, 0)) AS total_valor,
                    MIN(data_venda) AS primeira_venda,
                    MAX(data_venda) AS ultima_venda
                FROM vendas
                WHERE {$whereSql}
                GROUP BY telefone_cliente
                ORDER BY ultima_venda DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $clientes = [];
            $segmentosCount = [
                'D0_D7'   => 0,
                'D8_D15'  => 0,
                'D16_D30' => 0,
                'GT_D30'  => 0,
            ];

            $hoje = new DateTimeImmutable('now');

            // Consulta preparada para pegar a última venda + produto de cada cliente
            $repo = new ApiRemarketingSegmentosRepository($this->pdo);
            $stmtLast = $repo->prepare_2528();

            foreach ($rows as $row) {
                $ultimaVenda = $row['ultima_venda'] !== null ? new DateTimeImmutable($row['ultima_venda']) : null;
                $primeiraVenda = $row['primeira_venda'] !== null ? new DateTimeImmutable($row['primeira_venda']) : null;

                $diasDesdeUltima = null;
                if ($ultimaVenda) {
                    $diasDesdeUltima = (int) $hoje->diff($ultimaVenda)->format('%a');
                }

                // Segmentação simples e objetiva por tempo desde a última compra
                if ($diasDesdeUltima === null) {
                    $segmento = 'GT_D30';
                } elseif ($diasDesdeUltima <= 7) {
                    $segmento = 'D0_D7';
                } elseif ($diasDesdeUltima <= 15) {
                    $segmento = 'D8_D15';
                } elseif ($diasDesdeUltima <= 30) {
                    $segmento = 'D16_D30';
                } else {
                    $segmento = 'GT_D30';
                }

                if (isset($segmentosCount[$segmento])) {
                    $segmentosCount[$segmento]++;
                }

                // Busca a última venda detalhada para este cliente
                $stmtLast->execute([':telefone' => $row['telefone']]);
                $last = $stmtLast->fetch() ?: null;

                $ultimoProduto = null;
                if ($last) {
                    $ultimoProduto = [
                        'id'       => $last['produto_id'] !== null ? (int) $last['produto_id'] : null,
                        'nome'     => $last['produto_nome'],
                        'capacity' => $last['capacity'] !== null ? (int) $last['capacity'] : null,
                        'preco'    => $last['preco'] !== null ? (float) $last['preco'] : null,
                    ];
                }

                $clientes[] = [
                    'telefone'          => $row['telefone'],
                    'nome'              => $row['nome'] ?: null,
                    'total_vendas'      => (int) $row['total_vendas'],
                    'total_valor'       => (float) $row['total_valor'],
                    'primeira_venda'    => $primeiraVenda ? $primeiraVenda->format('Y-m-d H:i:s') : null,
                    'ultima_venda'      => $ultimaVenda ? $ultimaVenda->format('Y-m-d H:i:s') : null,
                    'dias_desde_ultima' => $diasDesdeUltima,
                    'segmento'          => $segmento,
                    'ultimo_produto'    => $ultimoProduto,
                    'id_vendedor_ultima'=> $last && $last['id_vendedor'] !== null ? (int) $last['id_vendedor'] : null,
                    'vendedor_ultima'   => $last['vendedor_nome'] ?? null,
                ];
            }

            if (function_exists('log_app_event')) {
                log_app_event('api', 'remarketing_segmentos', ['total_clientes' => count($clientes)]);
            }

            return [
                'success'        => true,
                'total_clientes' => count($clientes),
                'segmentos'      => $segmentosCount,
                'clientes'       => $clientes,
            ];
        } catch (\Throwable $e) {
            if (function_exists('error_log')) {
                error_log('Erro em RemarketingSegmentosService::gerarSegmentos: ' . $e->getMessage());
            }

            return [
                'success' => false,
                'error'   => 'Erro interno ao processar segmentos de remarketing.',
            ];
        }
    }
}

