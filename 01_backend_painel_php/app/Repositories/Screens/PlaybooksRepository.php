<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   playbooks.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class PlaybooksRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_951(): \PDOStatement
    {
        $sql = '
                    INSERT INTO playbooks (nome, descricao, canal_base, ativo, criado_por_id)
                    VALUES (:nome, :descricao, :canal_base, 1, :criado_por)
                ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_2050(): \PDOStatement
    {
        $sql = 'UPDATE playbooks SET ativo = :ativo WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3276(): \PDOStatement
    {
        $sql = 'SELECT COALESCE(MAX(ordem), 0) AS max_ordem FROM playbook_etapas WHERE playbook_id = :pid';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3566(): \PDOStatement
    {
        $sql = '
                    INSERT INTO playbook_etapas (playbook_id, ordem, titulo, descricao, offset_dias, canal, template_mensagem)
                    VALUES (:playbook_id, :ordem, :titulo, :descricao, :offset_dias, :canal, :template_mensagem)
                ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5340(): \PDOStatement
    {
        $sql = '
        SELECT p.*, u.nome AS criado_por_nome
        FROM playbooks p
        LEFT JOIN usuarios u ON u.id = p.criado_por_id
        WHERE p.id = :id
    ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5691(): \PDOStatement
    {
        $sql = '
            SELECT *
            FROM playbook_etapas
            WHERE playbook_id = :id
            ORDER BY ordem ASC
        ';
        return $this->pdo->prepare($sql);
    }

    public function query_4716(): \PDOStatement
    {
        $sql = '
    SELECT p.*, u.nome AS criado_por_nome
    FROM playbooks p
    LEFT JOIN usuarios u ON u.id = p.criado_por_id
    ORDER BY p.ativo DESC, p.nome ASC
';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: playbooks.php#query#4716');
        }
        return $stmt;
    }

}
