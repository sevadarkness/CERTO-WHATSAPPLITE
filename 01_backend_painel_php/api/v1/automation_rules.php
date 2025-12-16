<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Repositories\Screens\AutomationRulesRepository;




require_once __DIR__ . '/../../session_bootstrap.php';
require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../rbac.php';
require_once __DIR__ . '/../../logger.php';

require_role(['Administrador', 'Gerente']);

$pdo = $pdo ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(500);
    ApiResponse::jsonError('db_not_initialized', 'Banco não inicializado.', 500);
    exit;
}

require_once __DIR__ . '/../../app/Repositories/Screens/AutomationRulesRepository.php';




$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo   = new AutomationRulesRepository($pdo);

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw ?: '[]', true);
if (!is_array($input)) {
    $input = [];
}

if ($method === 'GET') {
    $stmt = $repo->query_5352();
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ApiResponse::jsonSuccess($rules);
    exit;
}

if ($method === 'POST') {
    $name        = trim((string) ($input['name'] ?? ''));
    $eventKey    = trim((string) ($input['event_key'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $isActive    = !empty($input['is_active']) ? 1 : 0;
    $conditions  = isset($input['conditions_json']) ? (string) $input['conditions_json'] : '';
    $actionType  = trim((string) ($input['action_type'] ?? 'log_only'));
    $actionJson  = isset($input['action_payload_json']) ? (string) $input['action_payload_json'] : '';

    if ($name === '' || $eventKey === '') {
        http_response_code(400);
        ApiResponse::jsonError('missing_fields', 'Campos obrigatórios ausentes.', 400);
        exit;
    }

    $stmt = $repo->prepare_1785();
    $stmt->execute([
        ':name'               => $name,
        ':description'        => $description !== '' ? $description : null,
        ':event_key'          => $eventKey,
        ':is_active'          => $isActive,
        ':conditions_json'    => $conditions !== '' ? $conditions : null,
        ':action_type'        => $actionType !== '' ? $actionType : 'log_only',
        ':action_payload_json'=> $actionJson !== '' ? $actionJson : null,
    ]);

    $id = (int) $pdo->lastInsertId();

    if (function_exists('log_audit_event')) {
        log_audit_event('automation_rule_create', 'automation_rule', $id, [
            'event_key' => $eventKey,
        ]);
    }

    ApiResponse::jsonSuccess(['id' => $id]);
    exit;
}

if ($method === 'PATCH') {
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        ApiResponse::jsonError('invalid_id', 'ID inválido.', 400);
        exit;
    }

    $stmt = $repo->prepare_5021();
    $stmt->execute([':id' => $id]);

    if (function_exists('log_audit_event')) {
        log_audit_event('automation_rule_toggle', 'automation_rule', $id, []);
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
ApiResponse::jsonError('method_not_allowed', 'Método não permitido.', 405);
