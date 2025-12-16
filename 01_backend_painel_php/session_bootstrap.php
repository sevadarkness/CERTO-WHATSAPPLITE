<?php
/**
 * Bootstrap central de sessão para o painel Alabama (V14 Ultra).
 *
 * - Define cookie de sessão com HttpOnly, SameSite=Lax e, se possível, Secure
 * - Unifica o nome da sessão em toda a aplicação
 * - Implementa proteção básica contra fixation (regeneração de ID em pontos críticos)
 *
 * NOTA (Deploy): este arquivo roda no início de praticamente toda requisição.
 * Por isso, aqui é o lugar correto para carregar o .env cedo.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap_autoload.php';

$securityHelpers = __DIR__ . '/security_helpers.php';
if (is_file($securityHelpers)) {
    require_once $securityHelpers;
}

// Carrega .env cedo para que ALABAMA_* / APP_* afetem este bootstrap.
// Falha silenciosa para não derrubar o painel em ambientes minimalistas.
try {
    if (class_exists(\RedeAlabama\Support\Env::class)) {
        \RedeAlabama\Support\Env::load();
    }
} catch (Throwable $e) {
    // ignore
}

// Normaliza ambiente (ALABAMA_ENV preferencial, fallback APP_ENV)
$envRaw = getenv('ALABAMA_ENV');
if (!is_string($envRaw) || trim($envRaw) === '') {
    $envRaw = getenv('APP_ENV');
}
$envRaw = (is_string($envRaw) && trim($envRaw) !== '') ? $envRaw : 'prod';
$envNorm = strtolower(trim($envRaw));
if (in_array($envNorm, ['production', 'prod'], true)) {
    $envNorm = 'prod';
} elseif (in_array($envNorm, ['local', 'development', 'dev'], true)) {
    $envNorm = 'local';
}

if (!defined('ALABAMA_ENV')) {
    define('ALABAMA_ENV', $envNorm);
}

// Debug toggle (APP_DEBUG/ALABAMA_DEBUG)
$debugFlag = getenv('APP_DEBUG');
if (!is_string($debugFlag) || $debugFlag === '') {
    $debugFlag = getenv('ALABAMA_DEBUG');
}
$debugFlag = strtolower(trim((string) $debugFlag));
$isDebug = in_array($debugFlag, ['1', 'true', 'yes', 'on'], true);

if (ALABAMA_ENV === 'prod' && !$isDebug) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Opcional: sessões armazenadas em Redis (quando configurado)
$sessionDriver = getenv('ALABAMA_SESSION_DRIVER') ?: '';
if ($sessionDriver === 'redis' && class_exists('Redis')) {
    $redisHost = getenv('ALABAMA_REDIS_HOST') ?: (getenv('REDIS_HOST') ?: 'redis');
    $redisPort = (int) (getenv('ALABAMA_REDIS_PORT') ?: (getenv('REDIS_PORT') ?: 6379));
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', 'tcp://' . $redisHost . ':' . $redisPort);
}

if (session_status() === PHP_SESSION_NONE) {
    // HTTPS detection (inclui proxy/load balancer)
    $secure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443')
    );

    if (!headers_sent()) {
        // Nome de sessão padronizado
        session_name('ALABAMA_SESSID');

        session_set_cookie_params([
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => 'Lax',
            'path'     => '/',
        ]);
    }

    session_start();
    
    // Aplicar security headers básicos (se não estiverem no modo CLI e headers ainda não enviados)
    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        $securityHeadersFile = __DIR__ . '/security_headers.php';
        if (is_file($securityHeadersFile)) {
            define('ALABAMA_CUSTOM_CSP', true); // Prevenir que security_headers.php defina CSP duplicado
            require_once $securityHeadersFile;
        }
    }

    // Guard simples baseado em User-Agent (mitiga hijack mais grosseiro)
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (!isset($_SESSION['__sec_ua'])) {
        $_SESSION['__sec_ua'] = $ua;
    } elseif ($_SESSION['__sec_ua'] !== $ua) {
        // Apenas loga e regenera o ID; não derruba a sessão para evitar falsos positivos
        require_once __DIR__ . '/logger.php';
        log_app_event('security', 'session_ua_mismatch', [
            'old_ua' => $_SESSION['__sec_ua'],
            'new_ua' => $ua,
        ]);

        session_regenerate_id(true);
        $_SESSION['__sec_ua'] = $ua;
    }

    // Binding adicional da sessão ao IP de origem (mitiga hijack básico)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!isset($_SESSION['__sec_ip'])) {
        $_SESSION['__sec_ip'] = $ip;
        $_SESSION['__sec_first_seen'] = time();
    } elseif ($_SESSION['__sec_ip'] !== $ip) {
        require_once __DIR__ . '/logger.php';
        log_app_event('security', 'session_ip_mismatch', [
            'old_ip' => $_SESSION['__sec_ip'],
            'new_ip' => $ip,
        ]);
        // Mantém a sessão, mas registra evidência de possível fraude
        $_SESSION['__sec_ip'] = $ip;
    }
}


// V30 Ultra: contexto de requisição + security headers + rate limiting global
if (php_sapi_name() !== 'cli') {
    // Inicializa contexto de requisição (request_id)
    $ctxFile = __DIR__ . '/app/Support/RequestContext.php';
    if (is_file($ctxFile)) {
        require_once $ctxFile;
        \RedeAlabama\Support\RequestContext::init();
    }

    // Rate limiting global + por endpoint + por usuário (com Redis quando disponível)
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path !== '/healthz.php' && $path !== '/metrics.php') {
        $rlFile = __DIR__ . '/rate_limiter.php';
        if (is_file($rlFile)) {
            require_once $rlFile;

            $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $user = $_SESSION['usuario_id'] ?? null;

            $globalBucket   = 'global:' . date('YmdHi');
            $endpointBucket = 'endpoint:' . ($path ?: '/') . ':' . ($_SERVER['REQUEST_METHOD'] ?? 'GET');
            $actorKey       = $user !== null ? 'user:' . $user : 'ip:' . $ip;
            $actorBucket    = 'actor:' . $actorKey . ':' . date('YmdHi');

            $ok = true;
            $ok = $ok && apply_rate_limit($globalBucket, 5000, 60);
            $ok = $ok && apply_rate_limit($endpointBucket, 1200, 60);
            $ok = $ok && apply_rate_limit($actorBucket, 120, 60);

            if (!$ok) {
                if (!headers_sent()) {
                    http_response_code(429);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode([
                    'ok'      => false,
                    'error'   => 'too_many_requests',
                    'message' => 'Muitas requisições. Tente novamente em instantes.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    if (!headers_sent()) {
        // Permite embutir páginas específicas (ex.: sidepanel no WhatsApp Web) via CSP frame-ancestors.
        // Para as demais páginas, mantém X-Frame-Options SAMEORIGIN.
        $scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $allowIframe = in_array($scriptName, [
            'whatsapp_sidepanel_embed.php',
            'whatsapp_sidepanel_embed_v5_original.php',
        ], true);

        $frameAncestors = "'self'";
        if ($allowIframe) {
            $extraAncestors = (string)(getenv('ALABAMA_IFRAME_ANCESTORS') ?: 'https://web.whatsapp.com');
            // Sanitiza contra injeção de CSP (remove ; e quebras)
            $extraAncestors = str_replace([';', "\r", "\n"], '', trim($extraAncestors));
            if ($extraAncestors !== '') {
                $frameAncestors .= ' ' . $extraAncestors;
            }
        } else {
            header('X-Frame-Options: SAMEORIGIN');
        }

        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0');

        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443')
        );

        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // CSP com suporte a nonce dinâmico (modo estrito opcional)
        $strictCsp = getenv('ALABAMA_STRICT_CSP') === '1';

        try {
            $nonce = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $nonce = substr(hash('sha256', uniqid('', true) . (string)mt_rand()), 0, 32);
        }

        if (!defined('ALABAMA_CSP_NONCE')) {
            define('ALABAMA_CSP_NONCE', $nonce);
        }

        if ($strictCsp) {
            $csp = "default-src 'self'; "
                 . "script-src 'self' 'nonce-" . ALABAMA_CSP_NONCE . "' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
                 . "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; "
                 . "img-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
                 . "font-src 'self' https://fonts.gstatic.com; "
                 . "connect-src 'self'; "
                 . "frame-ancestors " . $frameAncestors . ";";
        } else {
            $csp = "default-src 'self'; "
                 . "script-src 'self' 'nonce-" . ALABAMA_CSP_NONCE . "' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; "
                 . "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; "
                 . "img-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
                 . "font-src 'self' https://fonts.gstatic.com; "
                 . "connect-src 'self'; "
                 . "frame-ancestors " . $frameAncestors . ";";
        }

        header('Content-Security-Policy: ' . $csp);
    }
}
