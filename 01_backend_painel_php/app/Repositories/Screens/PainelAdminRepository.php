<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   painel_admin.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class PainelAdminRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1050(): \PDOStatement
    {
        $sql = 'INSERT INTO usuarios (nome, telefone, senha, nivel_acesso) VALUES (?, ?, ?, ?)';
        return $this->pdo->prepare($sql);
    }

    public function query_1552(): \PDOStatement
    {
        $sql = 'SELECT * FROM usuarios';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: painel_admin.php#query#1552');
        }
        return $stmt;
    }

}
