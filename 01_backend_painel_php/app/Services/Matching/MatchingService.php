<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Matching;

use PDO;
use RuntimeException;

/**
 * MatchingService
 *
 * Camada de orquestração para o endpoint de matching.
 * No momento, faz bridge com a implementação legacy api_matching_registro.php,
 * encapsulando o uso de superglobais e json_encode/json_decode.
 */
final class MatchingService
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Registra um matching a partir do payload normalizado.
     *
     * @param array $payload Dados vindos da MatchingRequest.
     * @param array $currentUser Usuário autenticado atual.
     *
     * @return array Resultado já em formato estruturado.
     */
    public function registerMatch(array $payload, array $currentUser): array
    {
        // Bridge com implementação legacy.
        $_SERVER['API_V2'] = true;

        if (!isset($_SESSION)) {
            session_start();
        }
        if (isset($currentUser['id'])) {
            $_SESSION['usuario_id'] = $currentUser['id'];
        }

        $_POST = $payload;

        ob_start();
        require __DIR__ . '/../../../api_matching_registro.php';
        $raw = ob_get_clean();

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !array_key_exists('success', $decoded)) {
            throw new RuntimeException('Resposta inválida da api_matching_registro.php');
        }

        return $decoded;
    }
}

