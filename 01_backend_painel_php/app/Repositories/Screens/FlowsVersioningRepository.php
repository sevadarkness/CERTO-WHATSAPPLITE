<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   flows_versioning.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class FlowsVersioningRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1190(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_flows WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1452(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_flow_steps WHERE flow_id = :flow_id ORDER BY step_order ASC';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1823(): \PDOStatement
    {
        $sql = 'SELECT MAX(version_number) AS max_v FROM whatsapp_flow_versions WHERE flow_id = :flow_id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_2084(): \PDOStatement
    {
        $sql = 'INSERT INTO whatsapp_flow_versions
            (flow_id, version_number, created_at, created_by_user_id, reason, flow_snapshot_json)
            VALUES (:flow_id, :version_number, NOW(), :created_by, :reason, :snapshot_json)';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3327(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_flow_versions WHERE flow_id = :flow_id ORDER BY version_number DESC';
        return $this->pdo->prepare($sql);
    }

    public function prepare_4161(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_flow_versions WHERE id = :id AND flow_id = :flow_id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5615(): \PDOStatement
    {
        $sql = 'DELETE FROM whatsapp_flow_steps WHERE flow_id = :flow_id';
        return $this->pdo->prepare($sql);
    }

    public function query_550(): \PDOStatement
    {
        $sql = 'SHOW TABLES LIKE \'whatsapp_flow_versions\'';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: flows_versioning.php#query#550');
        }
        return $stmt;
    }

}
