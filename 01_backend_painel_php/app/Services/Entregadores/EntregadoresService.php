<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Entregadores;

use PDO;
use RuntimeException;

/**
 * EntregadoresService
 *
 * Camada de orquestração para consultar entregadores.
 * Faz bridge com api_entregadores.php, encapsulando superglobais.
 */
final class EntregadoresService
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Consulta lista de entregadores com base em filtros (se houver).
     *
     * @param array $filters
     * @param array $currentUser
     * @return array
     */
    public function listEntregadores(array $filters, array $currentUser): array
    {
        $_SERVER['API_V2'] = true;

        if (!isset($_SESSION)) {
            session_start();
        }
        if (isset($currentUser['id'])) {
            $_SESSION['usuario_id'] = $currentUser['id'];
        }

        $_GET = $filters;

        ob_start();
        require __DIR__ . '/../../../api_entregadores.php';
        $raw = ob_get_clean();

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !array_key_exists('success', $decoded)) {
            throw new RuntimeException('Resposta inválida da api_entregadores.php');
        }

        return $decoded;
    }
}

