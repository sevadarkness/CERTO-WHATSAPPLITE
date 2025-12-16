<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\WhatsappMessageRepository;
use RedeAlabama\Services\Sales\SalesObjectionAssistantService;



require_once __DIR__ . '/../../rbac.php';
require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../logger.php';

$usuario = current_user();
if (!$usuario) {
    ApiResponse::jsonError('unauthenticated', 'Usuário não autenticado.', 401);
    return;
}

require_role(['Administrador', 'Gerente', 'Vendedor']);

// $pdo é definido em db_config.php
if (!isset($pdo)) {
    ApiResponse::jsonError('db_not_initialized', 'Conexão com banco de dados não inicializada.', 500);
    return;
}

$tenantId = (int)($usuario['tenant_id'] ?? 1);

$llm           = LlmService::fromEnv();
$msgRepository = new WhatsappMessageRepository($pdo);

$service = new SalesObjectionAssistantService($pdo, $llm, $msgRepository);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($method === 'GET' && $action === 'list') {
    $items = $service->listObjections($tenantId);
    ApiResponse::jsonSuccess(['items' => $items]);
    return;
}

if ($method === 'POST' && $action === 'resolve') {
    $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
    $threadId  = isset($_POST['thread_id']) ? (string)$_POST['thread_id'] : '';
    $codigo    = isset($_POST['codigo_objecao'])
        ? (string)$_POST['codigo_objecao']
        : (string)($_POST['codigo_objection'] ?? '');

    if ($clienteId <= 0 || $threadId === '' || $codigo === '') {
        ApiResponse::jsonError('invalid_params', 'cliente_id, thread_id e codigo_objecao são obrigatórios.', 422);
        return;
    }

    $result = $service->resolveObjection($tenantId, (int)$usuario['id'], $clienteId, $threadId, $codigo);

    if (!($result['ok'] ?? false)) {
        ApiResponse::jsonError('objection_resolve_failed', (string)($result['error'] ?? 'Falha ao tratar objeção.'), 400, $result);
        return;
    }

    ApiResponse::jsonSuccess($result);
    return;
}

if ($method === 'POST' && $action === 'feedback') {
    $logId          = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
    $vendedorEditou = isset($_POST['vendedor_editou']) ? (bool)$_POST['vendedor_editou'] : false;
    $clienteAceitou = null;

    if (array_key_exists('cliente_aceitou', $_POST)) {
        $val = $_POST['cliente_aceitou'];
        if ($val === '' || $val === 'null') {
            $clienteAceitou = null;
        } else {
            $clienteAceitou = (bool)$val;
        }
    }

    if ($logId <= 0) {
        ApiResponse::jsonError('invalid_params', 'log_id é obrigatório para feedback.', 422);
        return;
    }

    $ok = $service->registerFeedback($tenantId, $logId, $vendedorEditou, $clienteAceitou);

    if (!$ok) {
        ApiResponse::jsonError('feedback_failed', 'Não foi possível registrar o feedback.', 400);
        return;
    }

    ApiResponse::jsonSuccess(['ok' => true]);
    return;
}

ApiResponse::jsonError('not_found', 'Ação não encontrada para /api/v2/sales_objections.php', 404);
