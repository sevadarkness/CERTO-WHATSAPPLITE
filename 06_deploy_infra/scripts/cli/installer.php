#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Instalador CLI da Rede Alabama (V106).
 *
 * Uso recomendado (dentro do container `app`):
 *   docker compose exec app php 06_deploy_infra/scripts/cli/installer.php
 *
 * Funções principais:
 *   - Carrega .env e config central
 *   - Testa conexão com o banco (PDO)
 *   - Garante que migrations foram aplicadas
 *   - Executa seed do usuário administrador padrão (idempotente)
 */

if (PHP_SAPI !== 'cli') {
    echo "Este script deve ser executado via CLI (php 06_deploy_infra/scripts/cli/installer.php)." . PHP_EOL;
    exit(1);
}


// Este arquivo fica em: 06_deploy_infra/scripts/cli/installer.php
// Raiz do projeto é 3 níveis acima.
$projectRoot = dirname(__DIR__, 3);
$backendPath = $projectRoot . '/01_backend_painel_php';

// Paths básicos
$dbConfigPath      = $backendPath . '/db_config.php';
$migratePath       = $backendPath . '/migrate.php';
$seedAdminPath     = $backendPath . '/database/seeds/SeedAdminUser.php';
$envSupportPath    = $backendPath . '/app/Support/Env.php';
$configSupportPath = $backendPath . '/app/Support/Config.php';
$loggerPath        = $backendPath . '/logger.php';

if (!is_file($dbConfigPath) || !is_file($migratePath)) {
    echo "Estrutura esperada não encontrada. Verifique se o diretório do projeto está completo." . PHP_EOL;
    exit(1);
}

require_once $envSupportPath;
require_once $configSupportPath;
require_once $loggerPath;

use RedeAlabama\Support\Env;
use RedeAlabama\Support\Config;

echo "==============================================" . PHP_EOL;
echo "   Rede Alabama – Instalador CLI (V106)       " . PHP_EOL;
echo "==============================================" . PHP_EOL;

// Garante carregamento do .env
// Ordem de busca:
//  1) ALABAMA_ENV_PATH (override)
//  2) 01_backend_painel_php/.env
//  3) 06_deploy_infra/docker/.env (quando usando docker compose)
$envFile = getenv('ALABAMA_ENV_PATH') ?: '';

if (!$envFile) {
    $candidate1 = $backendPath . '/.env';
    $candidate2 = $projectRoot . '/06_deploy_infra/docker/.env';
    if (is_file($candidate1)) {
        $envFile = $candidate1;
    } elseif (is_file($candidate2)) {
        $envFile = $candidate2;
    }
}

if (!$envFile || !is_file($envFile)) {
    echo "[ERRO] Arquivo .env não encontrado." . PHP_EOL;
    echo "Esperado em: {$backendPath}/.env (ou {$projectRoot}/06_deploy_infra/docker/.env)" . PHP_EOL;
    echo "Crie a partir de 06_deploy_infra/docker/.env.example e ajuste as variáveis antes de continuar." . PHP_EOL;
    exit(1);
}

Env::load($envFile);

echo PHP_EOL . "[1/4] Ambiente carregado." . PHP_EOL;
echo "ALABAMA_ENV: " . (getenv('ALABAMA_ENV') ?: 'prod') . PHP_EOL;

// Mostra config de banco que será usada
$dbHost = Config::dbHost();
$dbName = Config::dbName();
$dbUser = Config::dbUsername();

echo PHP_EOL . "[2/4] Testando conexão ao banco de dados..." . PHP_EOL;
echo "Host: {$dbHost}" . PHP_EOL;
echo "Base: {$dbName}" . PHP_EOL;
echo "Usuário: {$dbUser}" . PHP_EOL;

// Reutiliza db_config.php (expõe $pdo ou encerra em caso de erro)
require_once $dbConfigPath;

if (!isset($pdo)) {
    echo "[ERRO] Não foi possível obter instância PDO a partir de db_config.php." . PHP_EOL;
    exit(1);
}

echo "[OK] Conexão com banco de dados estabelecida." . PHP_EOL;

// Executa migrations (idempotente)
echo PHP_EOL . "[3/4] Aplicando migrations (se necessário)..." . PHP_EOL;
$cmd = sprintf('php %s up', escapeshellarg($migratePath));
    require_once __DIR__ . '/../../../01_backend_painel_php/app/Support/Security.php';
    $safe = Security::safe_exec($cmd);
    $migrateExitCode = $safe['exit_code'] ?? 1;
if ($migrateExitCode !== 0) {
    echo "[ERRO] Falha ao aplicar migrations (código {$migrateExitCode})." . PHP_EOL;
    exit(1);
}
echo "[OK] Migrations aplicadas." . PHP_EOL;

// Executa seed do admin padrão (idempotente)
echo PHP_EOL . "[4/4] Verificando usuário administrador padrão..." . PHP_EOL;
if (is_file($seedAdminPath)) {
    require $seedAdminPath;
} else {
    echo "SeedAdminUser.php não encontrado. Pulei criação automática de admin." . PHP_EOL;
}

echo PHP_EOL . "==============================================" . PHP_EOL;
echo " Instalação CLI concluída com sucesso.        " . PHP_EOL;
echo " Acesse o painel em: http://localhost:8000    " . PHP_EOL;
echo " Se o seed padrão rodou, você tem:            " . PHP_EOL;
echo "   Telefone: 00000000000                      " . PHP_EOL;
echo "   Senha:    admin@123                        " . PHP_EOL;
echo " (Altere esses dados após o primeiro acesso.) " . PHP_EOL;
echo "==============================================" . PHP_EOL;

exit(0);
