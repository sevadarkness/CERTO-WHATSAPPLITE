<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Repositories\FlowRepository;
use RedeAlabama\Services\Flow\FlowGovernanceService;




require_once __DIR__ . '/../../session_bootstrap.php';
require_once __DIR__ . '/../../db_config.php';
require_once __DIR__ . '/../../rbac.php';
require_once __DIR__ . '/../../logger.php';

require_role(['Administrador']);

$pdo = $pdo ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(500);
    ApiResponse::jsonError('db_not_initialized', 'Banco não inicializado.', 500);
    exit;
}

require_once __DIR__ . '/../../app/Repositories/FlowRepository.php';
require_once __DIR__ . '/../../app/Services/Flow/FlowGovernanceService.php';




$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

$repo = new FlowRepository($pdo);
$gov  = new FlowGovernanceService($pdo, $repo);

if ($method === 'GET') {
    if ($action === 'versions') {
        $versions = $gov->listVersions();
        ApiResponse::jsonSuccess($versions);
        exit;
    }

    // Lista fluxos ativos
    $flows = $repo->allActive();
    $data = [];
    foreach ($flows as $flow) {
        $data[] = [
            'id'    => $flow->id,
            'nome'  => $flow->nome,
            'ativo' => $flow->ativo,
        ];
    }
    ApiResponse::jsonSuccess($data);
    exit;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw ?: '[]', true);
if (!is_array($input)) {
    $input = [];
}

if ($method === 'POST' && $action === 'snapshot') {
    $reason = isset($input['reason']) ? (string) $input['reason'] : 'manual_api_v1';
    $uid    = $_SESSION['usuario_id'] ?? null;
    $versionId = $gov->snapshot($reason, $uid);
    ApiResponse::jsonSuccess(['version_id' => $versionId]);
    exit;
}

if ($method === 'POST' && $action === 'rollback') {
    $versionId = isset($input['version_id']) ? (int) $input['version_id'] : 0;
    if ($versionId <= 0) {
        http_response_code(400);
        ApiResponse::jsonError('invalid_version_id', 'Versão inválida.', 400);
        exit;
    }
    $ok = $gov->rollback($versionId);
    ApiResponse::jsonSuccess(['rollback_ok' => $ok]);
    exit;
}

http_response_code(405);
ApiResponse::jsonError('method_not_allowed', 'Método não permitido.', 405);
