<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   migrate.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class MigrateRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1332(): \PDOStatement
    {
        $sql = 'INSERT INTO schema_migrations (migration) VALUES (:migration)';
        return $this->pdo->prepare($sql);
    }

    public function query_731(): \PDOStatement
    {
        $sql = 'SELECT migration FROM schema_migrations';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: migrate.php#query#731');
        }
        return $stmt;
    }

}
