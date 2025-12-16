<?php
/**
 * api_matching_registro.php (legacy)
 *
 * Mantém compatibilidade com o bridge usado por MatchingService (API v2).
 * Recebe um payload em $_POST (geralmente vindo de JSON) e registra em
 * matching_registros.
 *
 * Contrato mínimo:
 *   { "success": true, "id": 123 }
 */

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $payload = $_POST ?? [];
    if (!is_array($payload) || $payload === []) {
        echo json_encode([
            'success' => false,
            'error'   => 'Payload vazio ou inválido.',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    $usuarioId = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;

    // Extrai campos úteis (quando presentes)
    $summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : [];
    $client  = isset($payload['client']) && is_array($payload['client']) ? $payload['client'] : [];

    $clientPhone = isset($client['phone']) ? trim((string)$client['phone']) : null;
    $bestVendorId = isset($summary['best_vendor_id']) ? (int)$summary['best_vendor_id'] : null;
    $strategy = isset($summary['strategy']) ? trim((string)$summary['strategy']) : null;

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        $json = '{}';
    }

    // Insere (tabela criada por migration da Etapa 1)
    $sql = "INSERT INTO matching_registros (usuario_id, cliente_telefone, best_vendor_id, strategy, payload_json, created_at)
            VALUES (:usuario_id, :cliente_telefone, :best_vendor_id, :strategy, :payload_json, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':usuario_id'      => $usuarioId,
        ':cliente_telefone'=> $clientPhone,
        ':best_vendor_id'  => $bestVendorId,
        ':strategy'        => $strategy,
        ':payload_json'    => $json,
    ]);

    $id = (int)$pdo->lastInsertId();

    if (function_exists('log_app_event')) {
        log_app_event('matching', 'registered', [
            'id' => $id,
            'usuario_id' => $usuarioId,
            'best_vendor_id' => $bestVendorId,
        ]);
    }

    echo json_encode([
        'success' => true,
        'id'      => $id,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Erro interno ao registrar matching.',
        'details' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
