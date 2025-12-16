<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   editar_produto.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class EditarProdutoRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_263(): \PDOStatement
    {
        $sql = 'SELECT * FROM produtos WHERE id = ?';
        return $this->pdo->prepare($sql);
    }

    public function prepare_964(): \PDOStatement
    {
        $sql = 'UPDATE produtos SET nome = ?, descricao = ?, preco = ?, imagem = ?, promocao = ? WHERE id = ?';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1375(): \PDOStatement
    {
        $sql = 'SELECT id, sabor FROM sabores WHERE produto_id = ?';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1969(): \PDOStatement
    {
        $sql = 'DELETE FROM sabores WHERE produto_id = ? AND sabor = ?';
        return $this->pdo->prepare($sql);
    }

    public function prepare_2320(): \PDOStatement
    {
        $sql = 'INSERT INTO sabores (produto_id, sabor) VALUES (?, ?)';
        return $this->pdo->prepare($sql);
    }

        public function prepare_2695(): \PDOStatement
    {
        $sql = 'DELETE FROM estoque_vendedores WHERE sabor_id = ?';
        return $this->pdo->prepare($sql);
    }

public function prepare_3162(): \PDOStatement
    {
        $sql = 'SELECT * FROM sabores WHERE produto_id = ?';
        return $this->pdo->prepare($sql);
    }

}
