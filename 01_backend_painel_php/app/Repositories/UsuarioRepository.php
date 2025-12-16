<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories;

use PDO;

final class UsuarioRepository extends BaseRepository
{
    public function findByTelefone(string $telefone): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE telefone = :telefone LIMIT 1');
        $stmt->execute([':telefone' => $telefone]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
}
