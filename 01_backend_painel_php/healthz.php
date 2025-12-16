<?php
declare(strict_types=1);

/**
 * Healthcheck do Rede Alabama (Railway/local).
 *
 * Requisitos:
 * - Não deve derrubar o deploy quando o DB não estiver configurado/disponível.
 * - Deve responder JSON consistente para observabilidade.
 * - Verifica: Database, Disk Space, ENV vars, Writable directories, PHP extensions.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/bootstrap_autoload.php';

// Carrega .env cedo (melhor esforço)
try {
    if (class_exists(\RedeAlabama\Support\Env::class)) {
        \RedeAlabama\Support\Env::load();
    }
} catch (Throwable $e) {
    // ignore
}

$appEnv = (string) (getenv('APP_ENV') ?: getenv('ALABAMA_ENV') ?: 'prod');

$checks = [];

// 1. Database Check
$dbOk = null;
$dbError = null;
$hasDbConfig = (getenv('DB_HOST') !== false) || (getenv('DB_DATABASE') !== false) || (getenv('DB_USERNAME') !== false);

if ($hasDbConfig && class_exists(\RedeAlabama\Support\Config::class)) {
    try {
        $dsn  = \RedeAlabama\Support\Config::dbDsn();
        $user = \RedeAlabama\Support\Config::dbUsername();
        $pass = \RedeAlabama\Support\Config::dbPassword();

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);

        $pdo->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable $e) {
        $dbOk = false;
        $dbError = substr($e->getMessage(), 0, 180);
    }
} else {
    $dbOk = false;
    $dbError = 'db_not_configured';
}

$checks['database'] = [
    'ok' => $dbOk,
    'error' => $dbOk ? null : $dbError,
];

// 2. Disk Space Check
$diskOk = true;
$diskError = null;
$diskFree = 0;
$diskTotal = 0;
try {
    $diskFree = disk_free_space(__DIR__);
    $diskTotal = disk_total_space(__DIR__);
    if ($diskFree !== false && $diskTotal !== false) {
        $diskFreePerc = ($diskTotal > 0) ? (($diskFree / $diskTotal) * 100) : 0;
        if ($diskFreePerc < 10) {
            $diskOk = false;
            $diskError = 'Less than 10% free space';
        }
    } else {
        $diskOk = false;
        $diskError = 'Unable to check disk space';
    }
} catch (Throwable $e) {
    $diskOk = false;
    $diskError = substr($e->getMessage(), 0, 100);
}

$checks['disk_space'] = [
    'ok' => $diskOk,
    'free_mb' => $diskFree !== false ? round($diskFree / (1024 * 1024), 2) : null,
    'total_mb' => $diskTotal !== false ? round($diskTotal / (1024 * 1024), 2) : null,
    'error' => $diskOk ? null : $diskError,
];

// 3. ENV Variables Check (only critical ones that don't have fallbacks)
$envVars = ['DB_HOST', 'DB_DATABASE'];
$envOk = true;
$envMissing = [];
foreach ($envVars as $var) {
    if (getenv($var) === false) {
        $envMissing[] = $var;
        $envOk = false;
    }
}

$checks['env_vars'] = [
    'ok' => $envOk,
    'missing' => $envMissing ?: null,
];

// 4. Writable Directories Check
$writableDirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/exports',
    sys_get_temp_dir(),
];
$writableOk = true;
$writableErrors = [];
foreach ($writableDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_writable($dir)) {
        $writableOk = false;
        $writableErrors[] = $dir;
    }
}

$checks['writable_dirs'] = [
    'ok' => $writableOk,
    'not_writable' => $writableErrors ?: null,
];

// 5. PHP Extensions Check
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl'];
$extOk = true;
$extMissing = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $extOk = false;
        $extMissing[] = $ext;
    }
}

$checks['php_extensions'] = [
    'ok' => $extOk,
    'missing' => $extMissing ?: null,
];

// Overall status
$allOk = $dbOk && $diskOk && $envOk && $writableOk && $extOk;
$status = $allOk ? 'ok' : 'degraded';

// Modo strict: retorna 503 quando algum check crítico falha
$strictHealth = getenv('ALABAMA_STRICT_HEALTHCHECK') === '1';
if ($strictHealth && !$allOk) {
    http_response_code(503);
} else {
    http_response_code(200);
}

$jsonFlags = JSON_UNESCAPED_UNICODE;
if (getenv('APP_DEBUG') === 'true') {
    $jsonFlags |= JSON_PRETTY_PRINT;
}

echo json_encode([
    'ok'        => $allOk,
    'status'    => $status,
    'ts'        => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'app_env'   => $appEnv,
    'php'       => PHP_VERSION,
    'checks'    => $checks,
], $jsonFlags);
