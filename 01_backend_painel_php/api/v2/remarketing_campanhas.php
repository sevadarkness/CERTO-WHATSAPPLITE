<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;



require_once __DIR__ . '/../../rbac.php';
require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../logger.php';

require_role(['Administrador','Gerente','Vendedor']);

// Executa implementação legacy em buffer, mas responde sempre via ApiResponse
$_SERVER['API_V2'] = true;

ob_start();
require __DIR__ . '/../../api_remarketing_campanhas.php';
$raw = ob_get_clean();

$data = null;
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

if (is_array($data) && array_key_exists('success', $data)) {
    $success = (bool)$data['success'];
    unset($data['success']);
    if ($success) {
        ApiResponse::jsonSuccess($data);
        return;
    } else {
        $msg = isset($data['error']) && is_string($data['error']) ? $data['error'] : 'Erro na API legacy.';
        ApiResponse::jsonError('legacy_error', $msg, 400, $data);
        return;
    }
}

ApiResponse::jsonError('legacy_invalid_response', 'Resposta legacy inválida ou vazia.', 500);
