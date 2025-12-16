<?php
declare(strict_types=1);

use RedeAlabama\Support\ApiResponse;




/**
 * Endpoint HTTP simples para testar o LLM do painel Rede Alabama.
 *
 * POST /api/test_prompt.php
 * Body JSON:
 *   {
 *     "prompt": "texto do usuário",
 *     "temperature": 0.2,
 *     "max_tokens": 512,
 *     "model": "gpt-4.1-mini"
 *   }
 *
 * Resposta:
 *   {
 *     "ok": true,
 *     "answer": "texto gerado",
 *     "model": "gpt-4.1-mini"
 *   }
 */


require_once __DIR__ . '/../session_bootstrap.php';
require_once __DIR__ . '/../rbac.php';

/**
 * Segurança / Deploy:
 * - Este endpoint é para testes internos do LLM.
 * - Em produção, NÃO deve ficar aberto ao público.
 *
 * Controle de acesso:
 * 1) Se ALABAMA_TEST_PROMPT_TOKEN estiver configurado:
 *    - aceita Authorization: Bearer <token>
 * 2) Caso contrário (ou token inválido), exige usuário logado com perfil:
 *    - Administrador ou Gerente
 */
$tokenEnv = (string) (getenv('ALABAMA_TEST_PROMPT_TOKEN') ?: '');
$authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$tokenReq = '';
if ($authHeader !== '' && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $tokenReq = trim((string) $m[1]);
}

$authorizedByToken = ($tokenEnv !== '' && $tokenReq !== '' && hash_equals($tokenEnv, $tokenReq));

if (!$authorizedByToken) {
    require_role(['Administrador', 'Gerente']);
}

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../whatsapp_llm_helper.php';


require_once __DIR__ . '/../rate_limiter.php';

// Rate-limit distribuído por token/tenant (LLM)
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$tokenHash = $authHeader !== '' ? substr(sha1($authHeader), 0, 16) : 'no_token';
$tenantId  = $_SESSION['usuario_id'] ?? null;
$tenantKey = $tenantId !== null ? 'tenant:' . $tenantId : 'ip:' . $remoteIp;
$bucketKey = 'llm:' . $tenantKey . ':' . $tokenHash;

// Limite fino: ~60 req/min por tenant/token
$okTenant = apply_rate_limit($bucketKey, 60, 60);

if (!$okTenant) {
    if (!headers_sent()) {
        http_response_code(429);
    }
    ApiResponse::jsonError('too_many_requests_llm', 'Muitas requisições de LLM.', 429);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ApiResponse::jsonError('method_not_allowed', 'Use POST.', 405);
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    ApiResponse::jsonError('invalid_json', 'JSON inválido.', 400);
    exit;
}

$prompt = isset($data['prompt']) ? trim((string)$data['prompt']) : '';
if ($prompt === '') {
    http_response_code(400);
    ApiResponse::jsonError('missing_field_prompt', 'Campo "prompt" é obrigatório.', 400);
    exit;
}

$settings = whatsapp_bot_load_settings() ?? [
    'llm_provider'     => 'openai',
    'llm_model'        => $data['model']        ?? 'gpt-4.1-mini',
    'llm_temperature'  => isset($data['temperature']) ? (float)$data['temperature'] : 0.2,
    'llm_max_tokens'   => isset($data['max_tokens'])  ? (int)$data['max_tokens']  : 512,
];

$historico = [];
try {
    $resultado = whatsapp_bot_chamar_llm($prompt, $historico, $settings);
} catch (Throwable $e) {
    log_app_event('api_test_prompt', 'erro_execucao', ['erro' => $e->getMessage()]);
    http_response_code(500);
    ApiResponse::jsonError('llm_failed', 'Falha ao chamar LLM.', 500);
    exit;
}

ApiResponse::jsonSuccess([
    'answer' => $resultado['resposta'] ?? null,
    'model'  => $settings['llm_model'] ?? null,
]);
