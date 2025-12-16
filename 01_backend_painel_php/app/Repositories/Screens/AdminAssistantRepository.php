<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   admin_assistant.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class AdminAssistantRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function query_500(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_bot_settings ORDER BY id ASC LIMIT 1';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: admin_assistant.php#query#500');
        }
        return $stmt;
    }

}
