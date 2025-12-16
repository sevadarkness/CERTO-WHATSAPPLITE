<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   catalogo.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class CatalogoRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1209(): \PDOStatement
    {
        $sql = 'INSERT INTO produtos (nome, descricao, preco, imagem, promocao) VALUES (?, ?, ?, ?, ?)';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1589(): \PDOStatement
    {
        $sql = 'INSERT INTO sabores (produto_id, sabor) VALUES (?, ?)';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5662(): \PDOStatement
    {
        $sql = 'SELECT sabor FROM sabores WHERE produto_id = ?';
        return $this->pdo->prepare($sql);
    }

    public function query_4765(): \PDOStatement
    {
        $sql = 'SELECT * FROM produtos';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: catalogo.php#query#4765');
        }
        return $stmt;
    }

}
