<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories;

use PDO;

/**
 * Repositório de operações da tabela de leads.
 *
 * Extraído a partir da lógica original de api_leads.php para permitir
 * uso em Services/Controllers sem acessar PDO diretamente.
 */
final class LeadRepository extends BaseRepository
{
    public function insertLead(
        ?string $nome,
        string $telefone,
        string $origem,
        ?string $urlOrigem,
        ?string $observacao,
        string $status,
        ?int $vendedorId
    ): int {
        $sql = 'INSERT INTO leads (nome, telefone, origem, url_origem, observacao, status, vendedor_id)
                VALUES (:nome, :telefone, :origem, :url_origem, :observacao, :status, :vendedor_id)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nome'        => $nome,
            ':telefone'    => $telefone,
            ':origem'      => $origem,
            ':url_origem'  => $urlOrigem,
            ':observacao'  => $observacao,
            ':status'      => $status,
            ':vendedor_id' => $vendedorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByTelefone(string $telefone): ?array
    {
        $sql = 'SELECT * FROM leads WHERE telefone = :telefone ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':telefone' => $telefone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function findById(int $id): ?array
    {
        $sql = 'SELECT * FROM leads WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
}

