<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   automation_rules.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class AutomationRulesRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1785(): \PDOStatement
    {
        $sql = 'INSERT INTO automation_rules
                            (name, description, event_key, is_active, conditions_json, action_type, action_payload_json, created_at, updated_at)
                            VALUES (:name, :description, :event_key, :is_active, :conditions_json, :action_type, :action_payload_json, NOW(), NOW())';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3114(): \PDOStatement
    {
        $sql = 'UPDATE automation_rules
                                SET name = :name,
                                    description = :description,
                                    event_key = :event_key,
                                    is_active = :is_active,
                                    conditions_json = :conditions_json,
                                    action_type = :action_type,
                                    action_payload_json = :action_payload_json,
                                    updated_at = NOW()
                                WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5021(): \PDOStatement
    {
        $sql = 'UPDATE automation_rules SET is_active = 1 - is_active, updated_at = NOW() WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5546(): \PDOStatement
    {
        $sql = 'SELECT * FROM automation_rules WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function query_5352(): \PDOStatement
    {
        $sql = 'SELECT * FROM automation_rules ORDER BY id DESC';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: automation_rules.php#query#5352');
        }
        return $stmt;
    }

}
