<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   metrics.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class MetricsRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1162(): \PDOStatement
    {
        $sql = '
        SELECT COUNT(*) FROM whatsapp_messages
        WHERE direction = \'out\'
          AND created_at >= :today
    ';
        return $this->pdo->prepare($sql);
    }

    public function query_713(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) FROM whatsapp_flows WHERE ativo = 1';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: metrics.php#query#713');
        }
        return $stmt;
    }

    public function query_910(): \PDOStatement
    {
        $sql = 'SELECT COUNT(*) FROM automation_rules';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: metrics.php#query#910');
        }
        return $stmt;
    }

}
