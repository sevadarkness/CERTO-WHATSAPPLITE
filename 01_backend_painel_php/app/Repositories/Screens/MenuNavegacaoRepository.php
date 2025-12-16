<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   menu_navegacao.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class MenuNavegacaoRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_680(): \PDOStatement
    {
        $sql = 'SELECT * FROM usuarios WHERE id = :usuario_id';
        return $this->pdo->prepare($sql);
    }

}
