<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   api/v2/remarketing_segmentos.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class ApiRemarketingSegmentosRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_2528(): \PDOStatement
    {
        $sql = '
        SELECT 
            v.id,
            v.produto_id,
            v.id_vendedor,
            v.valor_total,
            v.data_venda,
            p.nome AS produto_nome,
            p.capacity,
            p.preco,
            u.nome AS vendedor_nome
        FROM vendas v
        JOIN produtos p ON p.id = v.produto_id
        LEFT JOIN usuarios u ON u.id = v.id_vendedor
        WHERE v.telefone_cliente = :telefone
          AND (v.produto_avariado IS NULL OR v.produto_avariado = 0)
        ORDER BY v.data_venda DESC
        LIMIT 1
    ';
        return $this->pdo->prepare($sql);
    }

}
