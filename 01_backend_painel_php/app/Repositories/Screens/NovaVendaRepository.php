<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   nova_venda.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class NovaVendaRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_693(): \PDOStatement
    {
        $sql = 'SELECT nivel_acesso FROM usuarios WHERE id = ?';
        return $this->pdo->prepare($sql);
    }

    public function prepare_1014(): \PDOStatement
    {
        $sql = '
        SELECT p.id, p.nome, p.preco, s.id AS sabor_id, s.sabor, e.quantidade 
        FROM produtos p
        JOIN estoque_vendedores e ON p.id = e.produto_id
        JOIN sabores s ON s.id = e.sabor_id
        WHERE e.vendedor_id = ? AND e.quantidade > 0
        ORDER BY p.nome, s.sabor
    ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3694(): \PDOStatement
    {
        $sql = 'SELECT quantidade FROM estoque_vendedores WHERE produto_id = ? AND sabor_id = ? AND vendedor_id = ?';
        return $this->pdo->prepare($sql);
    }

    public function prepare_4120(): \PDOStatement
    {
        $sql = 'INSERT INTO vendas 
            (produto_id, sabor_id, nome_cliente, telefone_cliente, valor_total, id_vendedor, produto_avariado, motivo_avariado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ';
        return $this->pdo->prepare($sql);
    }

    public function prepare_4649(): \PDOStatement
    {
        $sql = 'UPDATE estoque_vendedores SET quantidade = quantidade - 1 WHERE produto_id = ? AND sabor_id = ? AND vendedor_id = ?';
        return $this->pdo->prepare($sql);
    }

    public function prepare_4937(): \PDOStatement
    {
        $sql = 'INSERT INTO prejuizo (produto_id, sabor_id, vendedor_id, motivo, valor) 
                VALUES (?, ?, ?, ?, ?)
            ';
        return $this->pdo->prepare($sql);
    }

}
