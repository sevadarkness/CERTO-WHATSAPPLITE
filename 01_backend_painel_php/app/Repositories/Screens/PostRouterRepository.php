<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   post_router.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class PostRouterRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1798(): \PDOStatement
    {
        $sql = 'DELETE FROM usuarios WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

}
