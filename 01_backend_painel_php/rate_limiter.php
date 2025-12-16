<?php
declare(strict_types=1);

use RedeAlabama\Support\RateLimiter;
use RedeAlabama\Support\RequestContext;


require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/app/Support/RateLimiter.php';
require_once __DIR__ . '/app/Support/RequestContext.php';


/**
 * Aplica rate limiting a um bucket (ex.: login_ip:1.2.3.4).
 *
 * Retorna true se permitido, false se bloqueado.
 */
function rate_limit_or_fail(string $bucket, int $maxAttempts, int $decaySeconds): bool
{
    RequestContext::init();
    $allowed = RateLimiter::hit($bucket, $maxAttempts, $decaySeconds);

    if (!$allowed) {
        log_app_event('rate_limit', 'blocked', [
            'bucket'      => $bucket,
            'request_id'  => defined('ALABAMA_REQUEST_ID') ? ALABAMA_REQUEST_ID : null,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        ]);
    }

    return $allowed;
}

/**
 * Backwards compatibility alias.
 *
 * Muitos pontos antigos do código usam apply_rate_limit($bucket, $max, $decay).
 * Esta função apenas delega para rate_limit_or_fail(), preservando a semântica
 * e o logging centralizado.
 */
function apply_rate_limit(string $bucket, int $maxAttempts, int $decaySeconds): bool
{
    return rate_limit_or_fail($bucket, $maxAttempts, $decaySeconds);
}


