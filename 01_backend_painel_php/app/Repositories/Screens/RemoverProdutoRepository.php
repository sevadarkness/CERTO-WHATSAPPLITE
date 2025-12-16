<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   remover_produto.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class RemoverProdutoRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_60(): \PDOStatement
    {
        $sql = 'DELETE FROM produtos WHERE id = ?';
        return $this->pdo->prepare($sql);
    }

}
