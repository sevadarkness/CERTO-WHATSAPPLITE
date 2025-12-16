<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Remarketing;

use PDO;
use RuntimeException;

/**
 * RemarketingStatsService
 *
 * Camada de orquestração para estatísticas de remarketing.
 */
final class RemarketingStatsService
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Retorna estatísticas agregadas de remarketing.
     *
     * @param array $currentUser
     * @return array
     */
    public function stats(array $currentUser): array
    {
        $_SERVER['API_V2'] = true;

        if (!isset($_SESSION)) {
            session_start();
        }

        // Nenhum filtro específico no momento; aproveita lógica legacy.
        ob_start();
        require __DIR__ . '/../../../api_remarketing_stats.php';
        $raw = ob_get_clean();

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !array_key_exists('success', $decoded)) {
            throw new RuntimeException('Resposta inválida da api_remarketing_stats.php');
        }

        return $decoded;
    }
}

