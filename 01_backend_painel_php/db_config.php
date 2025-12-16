<?php
declare(strict_types=1);

use RedeAlabama\Support\Config;
use RedeAlabama\Support\Env;

/**
 * Conexão centralizada com o banco de dados (PDO) – V27 Ultra.
 *
 * - Lê credenciais preferencialmente de variáveis de ambiente (.env)
 * - Mantém compatibilidade lendo config.json como fallback
 * - Força charset utf8mb4
 * - Usa PDO com exceptions e prepared statements reais
 */


require_once __DIR__ . '/bootstrap_autoload.php';

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/error_handler.php';
require_once __DIR__ . '/app/Support/Env.php';
require_once __DIR__ . '/app/Support/Config.php';


Env::load();

$dsn      = Config::dbDsn();
$username = Config::dbUsername();
$password = Config::dbPassword();

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
    ]);
} catch (PDOException $e) {
    // Em ambientes de desenvolvimento mostramos a mensagem completa
    if (Config::debug()) {
        echo "Erro na conexão com o banco de dados: " . htmlspecialchars($e->getMessage());
    } else {
        // Em produção apenas logamos de forma estruturada
        log_app_event('database', 'connection_error', [
            'error'      => $e->getMessage(),
            'code'       => $e->getCode(),
            'request_id' => defined('ALABAMA_REQUEST_ID') ? ALABAMA_REQUEST_ID : null,
        ]);
        echo "Erro interno ao conectar ao banco de dados.";
    }
    exit;
}

/**
 * Helper de compatibilidade (V31): alguns scripts do painel usam get_db_connection().
 * Mantemos a assinatura para evitar fatal error e centralizar o acesso ao PDO.
 */
if (!function_exists('get_db_connection')) {
    function get_db_connection(): PDO
    {
        /** @var PDO $pdo */
        global $pdo;
        return $pdo;
    }
}

// Auto-migration: executa migrations pendentes automaticamente
// Pode ser desabilitado definindo ALABAMA_AUTO_MIGRATE=0
if (getenv('ALABAMA_AUTO_MIGRATE') !== '0') {
    require_once __DIR__ . '/database/auto_migrate.php';
    try {
        $migrationResults = auto_migrate_run($pdo);
        // Log dos resultados apenas em modo debug
        if (Config::debug() && function_exists('log_app_event')) {
            foreach ($migrationResults as $result) {
                log_app_event('auto_migrate', $result['status'], $result);
            }
        }
    } catch (Throwable $e) {
        // Silently fail - não quebra o sistema se migration falhar
        if (function_exists('log_app_event')) {
            log_app_event('auto_migrate', 'error', ['message' => $e->getMessage()]);
        }
    }
}
