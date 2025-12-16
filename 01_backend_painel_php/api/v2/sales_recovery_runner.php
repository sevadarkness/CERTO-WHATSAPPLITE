<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\WhatsappMessageRepository;
use RedeAlabama\Services\Sales\SalesRecoveryRunnerService;



require_once __DIR__ . '/../../rbac.php';
require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../logger.php';

$usuario = current_user();
if (!$usuario) {
    ApiResponse::jsonError('unauthenticated', 'Usuário não autenticado.', 401);
    return;
}

require_role(['Administrador', 'Gerente']);

// $pdo é definido em db_config.php
if (!isset($pdo)) {
    ApiResponse::jsonError('db_not_initialized', 'Conexão com banco de dados não inicializada.', 500);
    return;
}

$tenantId = (int)($usuario['tenant_id'] ?? 1);

$llm           = LlmService::fromEnv();
$msgRepository = new WhatsappMessageRepository($pdo);

$service = new SalesRecoveryRunnerService($pdo, $llm, $msgRepository);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    ApiResponse::jsonError('method_not_allowed', 'Use POST para executar o runner de campanhas.', 405);
    return;
}

$max = isset($_POST['max']) ? (int)$_POST['max'] : 100;

$result = $service->processarLote($tenantId, $max);

if (!($result['ok'] ?? false)) {
    ApiResponse::jsonError('runner_failed', (string)($result['error'] ?? 'Falha ao processar campanhas.'), 400, $result);
    return;
}

ApiResponse::jsonSuccess($result);
