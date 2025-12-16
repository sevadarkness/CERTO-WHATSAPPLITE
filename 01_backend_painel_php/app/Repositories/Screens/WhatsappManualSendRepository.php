<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   whatsapp_manual_send.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class WhatsappManualSendRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1674(): \PDOStatement
    {
        $sql = 'SELECT telefone_cliente FROM whatsapp_conversas WHERE id = :id LIMIT 1';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3525(): \PDOStatement
    {
        $sql = 'SELECT id FROM whatsapp_conversas WHERE telefone_cliente = :tel ORDER BY id DESC LIMIT 1';
        return $this->pdo->prepare($sql);
    }

    public function prepare_3827(): \PDOStatement
    {
        $sql = 'INSERT INTO whatsapp_conversas (telefone_cliente, status, created_at, updated_at, ultima_mensagem_em)
                                   VALUES (:tel, :status, NOW(), NOW(), NOW())';
        return $this->pdo->prepare($sql);
    }

    public function prepare_5054(): \PDOStatement
    {
        $sql = 'UPDATE whatsapp_conversas
                           SET ultima_mensagem_em = NOW(), updated_at = NOW()
                           WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

}
