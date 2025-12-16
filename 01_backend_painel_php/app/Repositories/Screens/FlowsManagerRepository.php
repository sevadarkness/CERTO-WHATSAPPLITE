<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   flows_manager.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class FlowsManagerRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_4954(): \PDOStatement
    {
        $sql = 'DELETE FROM whatsapp_flow_steps WHERE id = :id AND flow_id = :fid';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5679(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_flows WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5880(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_flow_steps WHERE flow_id = :id ORDER BY step_order ASC, id ASC';
        return $this->pdo->prepare($sql);
    }

}
