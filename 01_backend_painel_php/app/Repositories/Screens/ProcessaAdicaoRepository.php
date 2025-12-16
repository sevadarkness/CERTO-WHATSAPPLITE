<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   processa_adicao.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class ProcessaAdicaoRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_887(): \PDOStatement
    {
        $sql = 'INSERT INTO produtos (nome, descricao, preco, imagem, promocao) VALUES (?, ?, ?, ?, ?)';
        return $this->pdo->prepare($sql);
    }

}
