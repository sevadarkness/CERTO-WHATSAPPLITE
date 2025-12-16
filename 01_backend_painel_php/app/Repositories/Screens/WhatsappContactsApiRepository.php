<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   whatsapp_contacts_api.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class WhatsappContactsApiRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function query_9479(): \PDOStatement
    {
        $sql = 'SELECT DISTINCT telefone_cliente FROM whatsapp_conversas';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: whatsapp_contacts_api.php#query#9479');
        }
        return $stmt;
    }

    public function query_10755(): \PDOStatement
    {
        $sql = 'SELECT DISTINCT telefone_cliente FROM whatsapp_conversas';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: whatsapp_contacts_api.php#query#10755');
        }
        return $stmt;
    }

}
