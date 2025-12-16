<?php
/**
 * CSRF protection global para o painel Alabama (V19 Ultra).
 *
 * Uso típico em páginas que processam POST:
 *   require_once __DIR__ . '/csrf.php';
 *   csrf_require(); // antes de manipular $_POST
 *
 * Em formulários HTML:
 *   <?= csrf_field(); ?>
 *
 * Para chamadas AJAX/JSON:
 *   - Envie o token no campo "_csrf_token" do corpo (JSON ou x-www-form-urlencoded),
 *     ou em cabeçalho "X-CSRF-Token".
 */

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';

const ALABAMA_CSRF_SESSION_KEY = '_alabama_csrf_token';
const ALABAMA_CSRF_TTL = 3600; // 1 hora

/**
 * Gera (se necessário) e retorna o token CSRF da sessão.
 * Token expira após ALABAMA_CSRF_TTL segundos.
 */
function csrf_token(): string
{
    $now = time();
    $tokenData = $_SESSION[ALABAMA_CSRF_SESSION_KEY] ?? null;
    
    // Verificar se token existe e não expirou
    if (is_array($tokenData) && isset($tokenData['token'], $tokenData['created_at'])) {
        if (($now - $tokenData['created_at']) < ALABAMA_CSRF_TTL) {
            return $tokenData['token'];
        }
    }
    
    // Gerar novo token
    $newToken = bin2hex(random_bytes(32));
    $_SESSION[ALABAMA_CSRF_SESSION_KEY] = [
        'token' => $newToken,
        'created_at' => $now,
    ];
    
    return $newToken;
}

/**
 * Campo hidden padrão para formulários HTML.
 */
function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida o token CSRF a partir de um array arbitrário (ex.: $_POST ou dados JSON).
 */
function csrf_validate_from_array(array $source): bool
{
    $tokenData = $_SESSION[ALABAMA_CSRF_SESSION_KEY] ?? null;
    
    // Verificar se token existe
    if (!is_array($tokenData) || !isset($tokenData['token'], $tokenData['created_at'])) {
        return false;
    }
    
    // Verificar TTL
    $now = time();
    if (($now - $tokenData['created_at']) >= ALABAMA_CSRF_TTL) {
        // Token expirado - regenerar na próxima chamada de csrf_token()
        unset($_SESSION[ALABAMA_CSRF_SESSION_KEY]);
        return false;
    }
    
    $expected = $tokenData['token'];
    
    $provided = null;
    if (isset($source['_csrf_token']) && is_string($source['_csrf_token'])) {
        $provided = $source['_csrf_token'];
    }

    if ($provided === null) {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (is_string($header) && $header !== '') {
            $provided = $header;
        }
    }

    if (!is_string($provided) || $provided === '') {
        return false;
    }

    return hash_equals($expected, $provided);
}

/**
 * Valida o token CSRF com base em $_POST / body da requisição atual.
 */
function csrf_validate(): bool
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET' || $method === 'HEAD' || $method === 'OPTIONS') {
        // Por padrão, não exigimos token para requisições idempotentes.
        return true;
    }

    // Se Content-Type for JSON, tenta ler o corpo bruto.
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        return csrf_validate_from_array($data);
    }

    // Fallback para campos tradicionais (x-www-form-urlencoded / multipart).
    return csrf_validate_from_array($_POST);
}

/**
 * Encerra a requisição com 403 caso o token seja inválido.
 */
function csrf_require(): void
{
    if (csrf_validate()) {
        return;
    }

    require_once __DIR__ . '/logger.php';

    log_app_event('security', 'csrf_invalid', [
        'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
    ]);

    http_response_code(403);
    echo 'Requisição inválida (CSRF). Recarregue a página e tente novamente.';
    exit;
}
