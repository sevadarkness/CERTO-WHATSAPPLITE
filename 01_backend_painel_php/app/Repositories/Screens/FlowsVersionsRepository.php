<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   flows_versions.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class FlowsVersionsRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_2413(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_flows WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

}
