<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   flows_visual_builder.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class FlowsVisualBuilderRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1039(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_flows WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3518(): \PDOStatement
    {
        $sql = 'DELETE FROM whatsapp_flow_steps WHERE flow_id = :fid';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3649(): \PDOStatement
    {
        $sql = '
            INSERT INTO whatsapp_flow_steps (flow_id, step_order, step_type, template_slug, delay_minutes, is_active)
            VALUES (:fid, :ord, :type, :tpl, :delay, 1)
        ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5064(): \PDOStatement
    {
        $sql = '
            SELECT *
            FROM whatsapp_flow_steps
            WHERE flow_id = :fid
            ORDER BY step_order ASC, id ASC
        ';
        return $this->pdo->prepare($sql);
    }

}
