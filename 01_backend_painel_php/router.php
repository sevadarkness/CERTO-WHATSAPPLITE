<?php
declare(strict_types=1);

/**
 * Router para o PHP Built-in Server (php -S) com:
 * - Hardening básico (bloqueia dotfiles, .env, dumps/logs, diretórios internos)
 * - Aliases /marketing e /ai para manter paridade com o Apache (Docker)
 *
 * Uso:
 *   cd 01_backend_painel_php
 *   php -S 0.0.0.0:8080 -t . router.php
 */

$uriPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$path = $uriPath !== '' ? $uriPath : '/';
if ($path[0] !== '/') {
    $path = '/' . $path;
}

// Normalização defensiva
$path = str_replace("\0", '', $path);

// / -> /index.php (o painel não é SPA)
if ($path === '/') {
    $path = '/index.php';
}

// Bloqueia traversal óbvio
if (str_contains($path, '..')) {
    http_response_code(400);
    echo 'Bad Request';
    return true;
}

// Bloqueia dotfiles em qualquer segmento (exceto .well-known)
if (preg_match('#(^|/)\.(?!well-known/)#', $path)) {
    http_response_code(404);
    echo 'Not Found';
    return true;
}

// Bloqueia arquivos sensíveis por nome/extensão (php -S não respeita .htaccess)
$blockedNames = [
    '/.env',
    '/.env.example',
    '/config.json',
    '/composer.json',
    '/composer.lock',
    '/phpunit.xml',
];
foreach ($blockedNames as $bn) {
    if ($path === $bn) {
        http_response_code(404);
        echo 'Not Found';
        return true;
    }
}

// Bloqueia extensões típicas de artefatos/segredos
// (inclui variações comuns de backup como .bak2, .bak2025, .swp, .orig)
if (preg_match('#\.(sql|sqlite|db|bak\d*|old|orig|swp|log|gz|zip|tar|tgz|yml|yaml|toml|sh|ps1|md)$#i', $path)) {
    http_response_code(404);
    echo 'Not Found';
    return true;
}

// Bloqueia diretórios internos (normalmente não são rotas públicas)
// Mantém paridade com as regras de .htaccess (config/tests) para evitar vazamento em Railway/php -S.
if (preg_match('#^/(app|config|database|tests|logs|metrics_storage|scripts|vendor)(/|$)#i', $path)) {
    http_response_code(404);
    echo 'Not Found';
    return true;
}

// Evita execução de PHP dentro de uploads (mitiga RCE por upload)
if (preg_match('#^/uploads/.+\.(php|phtml|phar)$#i', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

/**
 * Alias /marketing -> ../04_marketing_ai_strategy
 */
if ($path === '/marketing' || str_starts_with($path, '/marketing/')) {
    $base = realpath(__DIR__ . '/../04_marketing_ai_strategy');
    if ($base === false) {
        http_response_code(404);
        echo 'Not Found';
        return true;
    }

    $rel = '';
    if ($path === '/marketing' || $path === '/marketing/') {
        $rel = 'marketing_strategy_panel.php';
    } else {
        $rel = ltrim(substr($path, strlen('/marketing/')), '/');
    }

    $target = $base . DIRECTORY_SEPARATOR . $rel;
    $real = realpath($target);

    if ($real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
        http_response_code(404);
        echo 'Not Found';
        return true;
    }

    // Ajusta variáveis para que o módulo calcule redirects corretamente
    $_SERVER['SCRIPT_FILENAME'] = $real;
    $_SERVER['SCRIPT_NAME']     = $path;
    $_SERVER['PHP_SELF']        = $path;

    require $real;
    return true;
}

/**
 * Alias /ai -> ../03_ai_llm_platform
 */
if ($path === '/ai' || str_starts_with($path, '/ai/')) {
    $base = realpath(__DIR__ . '/../03_ai_llm_platform');
    if ($base === false) {
        http_response_code(404);
        echo 'Not Found';
        return true;
    }

    $rel = '';
    if ($path === '/ai' || $path === '/ai/') {
        // Dashboard padrão (wrapper) – redireciona para o backend.
        $rel = 'dashboards/ia_insights_dashboard.php';
    } else {
        $rel = ltrim(substr($path, strlen('/ai/')), '/');
    }

    $target = $base . DIRECTORY_SEPARATOR . $rel;
    $real = realpath($target);

    if ($real === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || !is_file($real)) {
        http_response_code(404);
        echo 'Not Found';
        return true;
    }

    $_SERVER['SCRIPT_FILENAME'] = $real;
    $_SERVER['SCRIPT_NAME']     = $path;
    $_SERVER['PHP_SELF']        = $path;

    require $real;
    return true;
}

// Se o arquivo existe dentro do docroot do backend, deixa o server servir
$localFile = realpath(__DIR__ . $path);
if ($localFile !== false && str_starts_with($localFile, __DIR__ . DIRECTORY_SEPARATOR) && is_file($localFile)) {
    return false;
}

http_response_code(404);
echo 'Not Found';
return true;
