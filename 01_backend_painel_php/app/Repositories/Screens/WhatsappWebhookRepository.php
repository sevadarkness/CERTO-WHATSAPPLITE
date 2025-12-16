<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   whatsapp_webhook.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class WhatsappWebhookRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function prepare_1387(): \PDOStatement
    {
        $sql = 'UPDATE leads
                SET primeiro_contato_em = :primeiro,
                    ultimo_contato_em   = :ultimo
              WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_4435(): \PDOStatement
    {
        $sql = 'UPDATE whatsapp_conversas SET ultima_mensagem_em = NOW(), updated_at = NOW() WHERE id = :id';
        return $this->pdo->prepare($sql);
    }

    public function prepare_4624(): \PDOStatement
    {
        $sql = 'INSERT INTO whatsapp_conversas (telefone_cliente, status, ultima_mensagem_em) VALUES (:tel, \'ativa\', NOW())';
        return $this->pdo->prepare($sql);
    }

}
