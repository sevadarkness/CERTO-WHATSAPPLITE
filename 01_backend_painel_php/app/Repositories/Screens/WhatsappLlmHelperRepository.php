<?php
declare(strict_types=1);

namespace RedeAlabama\Repositories\Screens;

use PDO;

/**
 * Repositório gerado automaticamente para a tela/arquivo:
 *   whatsapp_llm_helper.php
 *
 * Cada método prepare_* / query_* corresponde a uma consulta SQL originalmente
 * embutida na view/controlador.
 */
final class WhatsappLlmHelperRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function query_11320(): \PDOStatement
    {
        $sql = 'SELECT * FROM whatsapp_bot_settings WHERE ativo = 1 ORDER BY id ASC LIMIT 1';
        $stmt = $this->pdo->query($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao executar consulta: whatsapp_llm_helper.php#query#11320');
        }
        return $stmt;
    }

}
