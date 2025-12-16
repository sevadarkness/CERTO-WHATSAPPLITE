<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   automation_core.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class AutomationCoreRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1487(): \PDOStatement
    {
        $sql = 'INSERT INTO automation_events (event_key, payload_json, status, created_at)
                               VALUES (:event_key, :payload_json, :status, NOW())';
        return $this->pdo->prepare($sql);
    }

    public function prepare_2554(): \PDOStatement
    {
        $sql = 'SELECT * FROM automation_rules WHERE is_active = 1 AND event_key = :event_key ORDER BY id ASC';
        return $this->pdo->prepare($sql);
    }

    public function prepare_4402(): \PDOStatement
    {
        $sql = 'UPDATE automation_events SET status = :status, processed_at = NOW(), last_error = NULL WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_4717(): \PDOStatement
    {
        $sql = 'UPDATE automation_events SET status = :status, processed_at = NOW(), last_error = :err WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_6878(): \PDOStatement
    {
        $sql = 'SELECT * FROM automation_events WHERE status = :status ORDER BY id ASC LIMIT :limit';
        return $this->pdo->prepare($sql);
    }

    public function prepare_7535(): \PDOStatement
    {
        $sql = 'UPDATE automation_events SET status = :status, processed_at = NOW() WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function query_595(): \PDOStatement
    {
        $sql = 'SHOW TABLES LIKE \'" . str_replace("\'", "\'\'", $table) . "\'';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: automation_core.php#query#595');
        }
        return $stmt;
    }

    public function query_2694(): \PDOStatement
    {
        $sql = 'SELECT * FROM automation_rules WHERE is_active = 1 ORDER BY id ASC';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: automation_core.php#query#2694');
        }
        return $stmt;
    }

}
