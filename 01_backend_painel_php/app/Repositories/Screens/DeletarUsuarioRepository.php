<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   deletar_usuario.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class DeletarUsuarioRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_981(): \PDOStatement
    {
        $sql = 'DELETE FROM usuarios WHERE id = ?';
        return $this->pdo->prepare($sql);
    }

}
