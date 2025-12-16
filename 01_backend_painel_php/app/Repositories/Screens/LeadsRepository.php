<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   leads.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class LeadsRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function query_1749(): \PDOStatement
    {
        $sql = 'SELECT id, nome FROM usuarios WHERE nivel_acesso = \'Vendedor\' ORDER BY nome';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: leads.php#query#1749');
        }
        return $stmt;
    }

}
