<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   editar_usuario.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class EditarUsuarioRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_231(): \PDOStatement
    {
        $sql = 'SELECT * FROM usuarios WHERE id = ?';
        return $this->pdo->prepare($sql);
    }

    public function prepare_593(): \PDOStatement
    {
        $sql = 'UPDATE usuarios SET nome = ?, telefone = ?, nivel_acesso = ? WHERE id = ?';
        return $this->pdo->prepare($sql);
    }

}
