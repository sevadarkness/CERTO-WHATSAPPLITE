<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Services\Llm\LlmService;
use RedeAlabama\Repositories\LeadRepository;
use RedeAlabama\Repositories\WhatsappMessageRepository;
use RedeAlabama\Services\Sales\SalesSmartOfferService;



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

$llm            = LlmService::fromEnv();
$leadRepository = new LeadRepository($pdo);
$msgRepository  = new WhatsappMessageRepository($pdo);

$service = new SalesSmartOfferService($pdo, $llm, $leadRepository, $msgRepository);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if ($method === 'POST' && $action === 'generate') {
    $clienteId = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
    $threadId  = isset($_POST['thread_id']) ? (string)$_POST['thread_id'] : '';

    if ($clienteId <= 0 || $threadId === '') {
        ApiResponse::jsonError('invalid_params', 'cliente_id e thread_id são obrigatórios.', 422);
        return;
    }

    $result = $service->gerarOferta($tenantId, (int)$usuario['id'], $clienteId, $threadId);

    if (!($result['ok'] ?? false)) {
        ApiResponse::jsonError('ia_offer_failed', (string)($result['error'] ?? 'Falha ao gerar oferta IA.'), 400, $result);
        return;
    }

    ApiResponse::jsonSuccess($result);
    return;
}

if ($method === 'POST' && $action === 'accept') {
    $logId        = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
    $ticketGerado = isset($_POST['ticket_gerado']) && $_POST['ticket_gerado'] !== ''
        ? (float)$_POST['ticket_gerado']
        : null;

    if ($logId <= 0) {
        ApiResponse::jsonError('invalid_params', 'log_id é obrigatório para registrar aceite.', 422);
        return;
    }

    $ok = $service->registrarAceiteOferta($tenantId, (int)$usuario['id'], $logId, $ticketGerado);

    if (!$ok) {
        ApiResponse::jsonError('offer_accept_failed', 'Não foi possível registrar o aceite da oferta.', 400);
        return;
    }

    ApiResponse::jsonSuccess(['ok' => true]);
    return;
}

ApiResponse::jsonError('not_found', 'Ação não encontrada para /api/v2/sales_ia_offers.php', 404);
