<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\LeadRepository;
use RedeAlabama\Repositories\WhatsappMessageRepository;
use RedeAlabama\Services\Sales\SalesRecoveryCampaignService;



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

$llm            = LlmService::fromEnv();
$leadRepository = new LeadRepository($pdo);
$msgRepository  = new WhatsappMessageRepository($pdo);

$service = new SalesRecoveryCampaignService($pdo, $llm, $leadRepository, $msgRepository);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($method === 'GET' && $action === 'list') {
    $rows = $service->listarCampanhas($tenantId);
    ApiResponse::jsonSuccess(['items' => $rows]);
    return;
}

if ($method === 'POST' && $action === 'save') {
    $payload = $_POST;

    $result = $service->salvarCampanha($tenantId, $payload);

    if (!($result['ok'] ?? false)) {
        ApiResponse::jsonError('campaign_save_failed', (string)($result['error'] ?? 'Erro ao salvar campanha.'), 400, $result);
        return;
    }

    ApiResponse::jsonSuccess($result);
    return;
}

if ($method === 'POST' && $action === 'segment') {
    $campaignId = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : 0;

    if ($campaignId <= 0) {
        ApiResponse::jsonError('invalid_params', 'campaign_id é obrigatório para segmentação.', 422);
        return;
    }

    $qtd = $service->gerarSegmentoParaCampanha($tenantId, $campaignId);

    ApiResponse::jsonSuccess([
        'ok'                    => true,
        'clientes_enfileirados' => $qtd,
        'campaign_id'           => $campaignId,
    ]);
    return;
}

ApiResponse::jsonError('not_found', 'Ação não encontrada para /api/v2/sales_recovery_campaigns.php', 404);
