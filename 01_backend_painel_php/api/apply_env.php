<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;



require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../logger.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    ApiResponse::jsonError('method_not_allowed', 'Método não permitido. Use POST.', 405);
    return;
}

$tokenEnv = getenv('ALABAMA_APPLY_ENV_TOKEN') ?: '';
if ($tokenEnv === '') {
    ApiResponse::jsonError('apply_env_token_not_configured', 'ALABAMA_APPLY_ENV_TOKEN não configurado no .env.', 500);
    return;
}

// Lê token do header ou body JSON
$headers = function_exists('getallheaders') ? getallheaders() : [];
$tokenReq = '';

if (isset($headers['X-ALABAMA-APPLY-TOKEN'])) {
    $tokenReq = (string)$headers['X-ALABAMA-APPLY-TOKEN'];
} elseif (isset($headers['x-alabama-apply-token'])) {
    $tokenReq = (string)$headers['x-alabama-apply-token'];
} else {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['token'])) {
            $tokenReq = (string)$json['token'];
        }
    }
}

if (!hash_equals($tokenEnv, $tokenReq)) {
    ApiResponse::jsonError('invalid_token', 'Token inválido.', 401);
    return;
}

// IP allowlist opcional
$allowedIpsEnv = getenv('ALABAMA_APPLY_ENV_ALLOWED_IPS') ?: '';
if ($allowedIpsEnv !== '') {
    $allowed = array_filter(array_map('trim', explode(',', $allowedIpsEnv)));
    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remoteIp === '' || !in_array($remoteIp, $allowed, true)) {
        ApiResponse::jsonError('ip_not_allowed', 'IP não autorizado.', 403, [
            'remote_ip' => $remoteIp,
        ]);
        return;
    }
}

// Dispara apply-env.sh (caminho pode variar). Prioriza ALABAMA_APPLY_ENV_SCRIPT.
$candidates = [];
$envScript = trim((string)(getenv('ALABAMA_APPLY_ENV_SCRIPT') ?: ''));
if ($envScript !== '') {
    $candidates[] = $envScript;
}
$candidates[] = __DIR__ . '/../apply-env.sh';
$candidates[] = dirname(__DIR__) . '/../06_deploy_infra/scripts/apply-env.sh';
$candidates[] = '/var/www/html/apply-env.sh';

$script = null;
foreach ($candidates as $cand) {
    if ($cand !== '' && is_file($cand)) {
        $script = $cand;
        break;
    }
}

$script = $script ?: ($envScript !== '' ? $envScript : '/var/www/html/apply-env.sh');
$logDir = __DIR__ . '/../logs';
$logPath = $logDir . '/apply-env.log';

if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

if (!is_file($script)) {
    ApiResponse::jsonError('apply_env_script_not_found', 'apply-env.sh não encontrado no container.', 500, [
        'script' => $script,
        'candidates' => $candidates,
    ]);
    return;
}

@chmod($script, 0755);
require_once __DIR__ . '/../app/Support/Security.php';
$res = Security::safe_exec($script);
// Persist output to log for diagnosis
if (!empty($res['output'])) {
    @file_put_contents($logPath, $res['output'] . "\n", FILE_APPEND | LOCK_EX);
}
$exitCode = $res['exit_code'] ?? null;
log_app_event('apply_env_api', 'trigger', [
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'exit_code' => $exitCode,
    'error'     => $res['error'] ?? null,
]);

ApiResponse::jsonSuccess([
    'message'  => 'apply-env disparado.',
    'log_path' => $logPath,
], [
    'triggered_at' => date('c'),
], 202);
