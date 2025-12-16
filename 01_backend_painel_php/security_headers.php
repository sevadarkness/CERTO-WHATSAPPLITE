<?php
declare(strict_types=1);

/**
 * Security Headers - Incluir no início de todas as páginas PHP
 * 
 * Uso: require_once __DIR__ . '/security_headers.php';
 */

// Prevenir que a página seja carregada em iframes (clickjacking)
header('X-Frame-Options: SAMEORIGIN');

// Prevenir MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Ativar proteção XSS do navegador
header('X-XSS-Protection: 1; mode=block');

// Referrer policy - não enviar referrer para origens externas
header('Referrer-Policy: strict-origin-when-cross-origin');

// Permissions policy - restringir features do navegador
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// HSTS - forçar HTTPS (apenas se não for localhost)
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) 
    || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost:');

if (!$isLocalhost && (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Content Security Policy básica (pode ser customizada por página)
// Não definir CSP aqui se a página já define seu próprio CSP com nonce
if (!defined('ALABAMA_CUSTOM_CSP')) {
    $cspDirectives = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: https:",
        "connect-src 'self' https://api.openai.com",
        "frame-ancestors 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $cspDirectives));
}
