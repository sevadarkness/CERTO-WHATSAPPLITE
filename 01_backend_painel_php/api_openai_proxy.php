<?php
declare(strict_types=1);

/**
 * Proxy server-side para OpenAI.
 *
 * Objetivos:
 * - Nunca expor OPENAI_API_KEY no frontend/extensão.
 * - Suportar múltiplos endpoints via ?target= (preserva o path real: /v1/responses, /v1/chat/completions, etc.).
 * - CORS preflight correto (OPTIONS) sem retornar 405.
 * - Auth coerente:
 *    - Se OPENAI_PROXY_SECRET estiver configurado: aceita sessão OU X-Alabama-Proxy-Key.
 *    - Se OPENAI_PROXY_SECRET NÃO estiver configurado: exige sessão.
 * - Hardening: limita target (SSRF) e tamanho de body.
 */

require_once __DIR__ . '/bootstrap_autoload.php';

// Carrega .env cedo (melhor esforço)
try {
    if (class_exists(\RedeAlabama\Support\Env::class)) {
        \RedeAlabama\Support\Env::load();
    }
} catch (Throwable $e) {
    // ignore
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));

$allowedOriginPrefix = trim((string)(getenv('OPENAI_PROXY_ALLOWED_ORIGIN') ?: ''));
$corsAllowed = false;
if ($origin !== '' && $allowedOriginPrefix !== '') {
    if ($allowedOriginPrefix === '*' || str_starts_with($origin, $allowedOriginPrefix)) {
        $corsAllowed = true;
    }
}

if ($corsAllowed) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Alabama-Proxy-Key, OpenAI-Organization, OpenAI-Project, OpenAI-Beta');
    header('Access-Control-Max-Age: 86400');
}

// Preflight CORS
if ($method === 'OPTIONS') {
    http_response_code($corsAllowed || $origin === '' ? 204 : 403);
    exit;
}

// Se há Origin e existe allowlist configurada, mas não bate -> bloqueia explicitamente
if ($origin !== '' && $allowedOriginPrefix !== '' && !$corsAllowed) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => 'cors_origin_not_allowed',
        'message' => 'Origin não permitido para este proxy.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Helpers
 */
$respondJson = static function (int $code, array $payload): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
};

$proxySecret = trim((string)(getenv('OPENAI_PROXY_SECRET') ?: ''));
$providedKey = trim((string)($_SERVER['HTTP_X_ALABAMA_PROXY_KEY'] ?? ''));

$hasValidProxyKey = false;
if ($proxySecret !== '' && $providedKey !== '') {
    $hasValidProxyKey = hash_equals($proxySecret, $providedKey);
}

$authMode = 'session';
if ($hasValidProxyKey) {
    $authMode = 'proxy_key';
}

// Se não há proxy secret configurado, exige sessão sempre.
if ($proxySecret === '') {
    $authMode = 'session';
}

require_once __DIR__ . '/logger.php';

// Sessão apenas quando necessário (evita criar sessões novas em chamadas do WhatsApp Web)
if ($authMode === 'session') {
    require_once __DIR__ . '/session_bootstrap.php';

    $loggedIn = isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] !== null;
    if (!$loggedIn) {
        log_app_event('openai_proxy', 'unauthorized', [
            'auth_mode' => $authMode,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'origin' => $origin !== '' ? $origin : null,
        ]);

        $respondJson(401, [
            'ok' => false,
            'error' => 'unauthorized',
            'message' => 'Não autenticado. Faça login no painel ou configure X-Alabama-Proxy-Key.',
        ]);
        exit;
    }
}

// Rate limit por IP (melhor esforço)
try {
    if (class_exists(\RedeAlabama\Support\RateLimiter::class)) {
        $max = (int) (getenv('OPENAI_PROXY_RL_MAX_ATTEMPTS') ?: 60);
        $win = (int) (getenv('OPENAI_PROXY_RL_WINDOW_SECONDS') ?: 60);
        if ($max > 0 && $win > 0) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $bucket = 'openai_proxy:' . $ip;
            $ok = \RedeAlabama\Support\RateLimiter::hit($bucket, $max, $win);
            if (!$ok) {
                log_app_event('openai_proxy', 'rate_limited', [
                    'ip' => $ip,
                    'max' => $max,
                    'window' => $win,
                    'origin' => $origin !== '' ? $origin : null,
                ]);

                $respondJson(429, [
                    'ok' => false,
                    'error' => 'too_many_requests',
                    'message' => 'Muitas requisições. Tente novamente em instantes.',
                ]);
                exit;
            }
        }
    }
} catch (Throwable $e) {
    // ignore
}

// Resolve a API key do servidor
$apiKey = trim((string)(getenv('OPENAI_API_KEY') ?: ''));
if ($apiKey === '') {
    $apiKey = trim((string)(getenv('ALABAMA_OPENAI_API_KEY') ?: ''));
}
if ($apiKey === '') {
    $apiKey = trim((string)(getenv('LLM_OPENAI_API_KEY') ?: ''));
}
if ($apiKey === '') {
    $respondJson(500, [
        'ok' => false,
        'error' => 'server_misconfigured',
        'message' => 'OPENAI_API_KEY não configurada no servidor.',
    ]);
    exit;
}

// target: URL completa ou path relativo.
$targetRaw = (string)($_GET['target'] ?? '');
$defaultTarget = 'https://api.openai.com/v1/chat/completions';
$target = trim($targetRaw);
if ($target === '') {
    $target = $defaultTarget;
} elseif (str_starts_with($target, '/')) {
    $target = 'https://api.openai.com' . $target;
} elseif (!preg_match('#^https?://#i', $target)) {
    $target = 'https://api.openai.com/' . ltrim($target, '/');
}

$parsed = parse_url($target);
$scheme = strtolower((string)($parsed['scheme'] ?? ''));
$host = strtolower((string)($parsed['host'] ?? ''));
if ($scheme !== 'https' || $host !== 'api.openai.com') {
    $respondJson(400, [
        'ok' => false,
        'error' => 'invalid_target',
        'message' => 'Target inválido. Use https://api.openai.com/...',
    ]);
    exit;
}

// Método permitido
if (!in_array($method, ['POST', 'GET'], true)) {
    $respondJson(405, [
        'ok' => false,
        'error' => 'method_not_allowed',
        'message' => 'Somente GET/POST.',
    ]);
    exit;
}

$timeout = (int) (getenv('LLM_TIMEOUT_SECONDS') ?: 15);
if ($timeout <= 0) {
    $timeout = 15;
}

$body = '';
$payload = null;

if ($method === 'POST') {
    $maxBody = (int) (getenv('OPENAI_PROXY_MAX_BODY_BYTES') ?: 1048576); // 1MB
    if ($maxBody <= 0) {
        $maxBody = 1048576;
    }

    $body = (string) file_get_contents('php://input');
    if (strlen($body) > $maxBody) {
        $respondJson(413, [
            'ok' => false,
            'error' => 'payload_too_large',
            'message' => 'Payload muito grande.',
        ]);
        exit;
    }

    if (trim($body) === '') {
        $respondJson(400, [
            'ok' => false,
            'error' => 'empty_body',
            'message' => 'Body vazio.',
        ]);
        exit;
    }

    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? 'application/json');
    $isJson = str_contains(strtolower($contentType), 'application/json') || trim($contentType) === '';
    if (!$isJson) {
        // Mantém compat com JSON-only (evita proxy virar túnel genérico)
        $respondJson(415, [
            'ok' => false,
            'error' => 'unsupported_media_type',
            'message' => 'Content-Type não suportado. Use application/json.',
        ]);
        exit;
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $respondJson(400, [
            'ok' => false,
            'error' => 'invalid_json',
            'message' => 'JSON inválido.',
        ]);
        exit;
    }

    // Validações opcionais (somente se o operador configurar envs)
    $allowedModels = trim((string)(getenv('OPENAI_PROXY_ALLOWED_MODELS') ?: ''));
    if ($allowedModels !== '' && isset($decoded['model']) && is_string($decoded['model'])) {
        $list = array_filter(array_map('trim', explode(',', $allowedModels)));
        if (!empty($list) && !in_array($decoded['model'], $list, true)) {
            $respondJson(400, [
                'ok' => false,
                'error' => 'model_not_allowed',
                'message' => 'Model não permitido neste proxy.',
            ]);
            exit;
        }
    }

    $maxTokens = (int) (getenv('OPENAI_PROXY_MAX_TOKENS') ?: 0);
    if ($maxTokens > 0) {
        foreach (['max_tokens', 'max_output_tokens', 'max_completion_tokens'] as $field) {
            if (isset($decoded[$field]) && is_numeric($decoded[$field])) {
                $val = (int) $decoded[$field];
                if ($val > $maxTokens) {
                    $decoded[$field] = $maxTokens;
                }
            }
        }
    }

    $payload = $decoded;
    $body = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === 'null' || $body === '') {
        $respondJson(500, [
            'ok' => false,
            'error' => 'encode_failed',
            'message' => 'Falha ao serializar JSON.',
        ]);
        exit;
    }
}

// Monta headers para upstream
$headers = [];
$headers['Content-Type'] = 'application/json';
$headers['Authorization'] = 'Bearer ' . $apiKey;

// Forward opcional de alguns headers úteis (sem vazar auth)
$forward = [
    'HTTP_OPENAI_ORGANIZATION' => 'OpenAI-Organization',
    'HTTP_OPENAI_PROJECT' => 'OpenAI-Project',
    'HTTP_OPENAI_BETA' => 'OpenAI-Beta',
];
foreach ($forward as $serverKey => $hdrName) {
    if (!empty($_SERVER[$serverKey])) {
        $headers[$hdrName] = (string) $_SERVER[$serverKey];
    }
}

log_app_event('openai_proxy', 'request', [
    'auth_mode' => $authMode,
    'method' => $method,
    'target_path' => (string)($parsed['path'] ?? ''),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'origin' => $origin !== '' ? $origin : null,
    // Em modo "proxy_key", não iniciamos sessão para evitar cookies inúteis.
    // Portanto, não podemos assumir que $_SESSION existe.
    'usuario_id' => isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : null,
]);

/**
 * Executa request via cURL (preferido) ou file_get_contents (fallback).
 * Retorna: [statusCode, responseHeadersAssoc, body]
 */
$doRequest = static function (string $method, string $url, array $headers, string $body, int $timeout): array {
    $headerLines = [];
    foreach ($headers as $k => $v) {
        $headerLines[] = $k . ': ' . $v;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return [0, [], ''];
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        if ($raw === false || $raw === null) {
            $err = curl_error($ch);
            curl_close($ch);
            return [0, ['x_curl_error' => $err], ''];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerRaw = substr((string)$raw, 0, $headerSize);
        $bodyRaw = substr((string)$raw, $headerSize);

        $respHeaders = [];
        foreach (explode("\r\n", $headerRaw) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $k = strtolower(trim($k));
                $v = trim($v);
                if ($k !== '') {
                    $respHeaders[$k] = $v;
                }
            }
        }
        if ($contentType !== '') {
            $respHeaders['content-type'] = $contentType;
        }

        return [$status, $respHeaders, (string)$bodyRaw];
    }

    // Fallback sem cURL
    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines) . "\r\n",
            'content' => $method === 'POST' ? $body : '',
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);

    $respBody = @file_get_contents($url, false, $ctx);
    $respBody = is_string($respBody) ? $respBody : '';

    $status = 0;
    $respHeaders = [];
    global $http_response_header;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
                continue;
            }
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $k = strtolower(trim($k));
                $v = trim($v);
                if ($k !== '') {
                    $respHeaders[$k] = $v;
                }
            }
        }
    }

    return [$status, $respHeaders, $respBody];
};

[$status, $respHeaders, $respBody] = $doRequest($method, $target, $headers, $body, $timeout);

if ($status <= 0) {
    log_app_event('openai_proxy', 'upstream_unreachable', [
        'auth_mode' => $authMode,
        'target' => $target,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'origin' => $origin !== '' ? $origin : null,
        'transport' => function_exists('curl_init') ? 'curl' : 'stream',
        'curl_error' => $respHeaders['x_curl_error'] ?? null,
    ]);

    $respondJson(502, [
        'ok' => false,
        'error' => 'upstream_unreachable',
        'message' => 'Falha ao contatar o provider.',
    ]);
    exit;
}

// Repassa status e content-type do upstream
http_response_code($status);

$ct = (string)($respHeaders['content-type'] ?? 'application/json; charset=utf-8');
header('Content-Type: ' . $ct);
header('Cache-Control: no-store');

// Repassa headers úteis (sem leak)
$passHeaders = ['openai-request-id', 'x-request-id', 'retry-after'];
foreach ($passHeaders as $h) {
    if (isset($respHeaders[$h])) {
        header($h . ': ' . $respHeaders[$h]);
    }
}

echo $respBody;

// Logging de erro upstream (sem vazar payload)
if ($status < 200 || $status >= 300) {
    log_app_event('openai_proxy', 'upstream_error', [
        'status' => $status,
        'auth_mode' => $authMode,
        'target_path' => (string)($parsed['path'] ?? ''),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'origin' => $origin !== '' ? $origin : null,
    ]);
}
