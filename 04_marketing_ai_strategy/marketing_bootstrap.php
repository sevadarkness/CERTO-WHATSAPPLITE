<?php
declare(strict_types=1);

/**
 * Bootstrap do módulo 04 — Marketing AI Strategy.
 *
 * Este módulo fica fora do diretório principal do backend (01_backend_painel_php),
 * porém depende das rotinas centrais de:
 *  - sessão (session_bootstrap.php)
 *  - RBAC (rbac.php)
 *  - CSRF (csrf.php)
 *  - logs (logger.php)
 *
 * Deploy padrão (layout deste ZIP):
 *   /01_backend_painel_php
 *   /04_marketing_ai_strategy
 *
 * Caso seu deploy seja diferente, defina a env:
 *   ALABAMA_BACKEND_DIR=/caminho/absoluto/para/01_backend_painel_php
 *
 * Observação importante:
 * - O RBAC do Alabama calcula o "base path" web a partir do SCRIPT_NAME.
 *   Como este módulo pode ser servido via Alias/VirtualHost fora do backend,
 *   inferimos e exportamos ALABAMA_BASE_PATH automaticamente (somente se ainda
 *   não estiver definido).
 */

// Resolve diretório físico do backend.
$backendDir = (string)(getenv('ALABAMA_BACKEND_DIR') ?: '');
if ($backendDir !== '' && is_dir($backendDir)) {
    $backendDir = (string)realpath($backendDir);
} else {
    $candidate = realpath(__DIR__ . '/../01_backend_painel_php');
    if ($candidate !== false && is_dir($candidate)) {
        $backendDir = (string)$candidate;
    } else {
        $candidate2 = realpath(__DIR__ . '/01_backend_painel_php');
        if ($candidate2 !== false && is_dir($candidate2)) {
            $backendDir = (string)$candidate2;
        }
    }
}

if ($backendDir === '' || !is_dir($backendDir)) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "Erro de deploy: não foi possível localizar o backend (01_backend_painel_php).\n";
    echo "Defina a variável de ambiente ALABAMA_BACKEND_DIR apontando para o diretório do backend.\n";
    exit;
}

if (!defined('ALABAMA_BACKEND_DIR')) {
    define('ALABAMA_BACKEND_DIR', $backendDir);
}

// Ajuste automático do base path web para redirects (login/MFA) quando o módulo está fora do backend.
// Não sobrescreve se o operador já definiu ALABAMA_BASE_PATH no ambiente.
$basePathEnv = trim((string)(getenv('ALABAMA_BASE_PATH') ?: ''));
if ($basePathEnv === '' && PHP_SAPI !== 'cli' && is_file($backendDir . '/login.php')) {
    $backendReal = realpath($backendDir) ?: $backendDir;
    $docRoot     = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';

    $computed = '';

    // Caso comum em Docker: DocumentRoot aponta diretamente para o backend.
    // Ex.: docroot = /var/www/html/01_backend_painel_php
    // Nesse caso, o base path web é a raiz ("/").
    if ($docRoot !== '' && $backendReal !== '' && str_starts_with($backendReal, $docRoot)) {
        $rel = trim((string)substr($backendReal, strlen($docRoot)), '/');
        $computed = ($rel === '') ? '/' : $rel;
    } else {
        // Caso comum em hospedagem: docroot é a raiz do projeto e o backend é um subdiretório.
        // Ex.: docroot = /var/www/html ; backend = /var/www/html/01_backend_painel_php
        if ($docRoot !== '' && $backendReal !== '' && str_starts_with($backendReal, $docRoot . DIRECTORY_SEPARATOR)) {
            $rel = trim((string)substr($backendReal, strlen($docRoot)), '/');
            $computed = ($rel === '') ? '/' : $rel;
        } else {
            // Fallback: usa o nome do diretório do backend como base path.
            $base = basename($backendReal);
            if ($base !== '') {
                $computed = $base;
            }
        }
    }

    if ($computed !== '') {
        putenv('ALABAMA_BASE_PATH=' . $computed);
    }
}

require_once $backendDir . '/session_bootstrap.php';
require_once $backendDir . '/rbac.php';
require_once $backendDir . '/csrf.php';
require_once $backendDir . '/logger.php';
