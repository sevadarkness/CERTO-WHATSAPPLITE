<?php
declare(strict_types=1);

/**
 * jobs/scheduler.php
 *
 * Runner simples de tarefas agendadas.
 *
 * Objetivo:
 *  - Ler um JSON com lista de "tasks".
 *  - Executar os comandos sequencialmente.
 *
 * Importante:
 *  - Por padrão, este scheduler executa as tasks no diretório do backend
 *    (01_backend_painel_php), para que comandos como "php jobs_runner.php"
 *    funcionem sem precisar de paths absolutos.
 *
 * Uso:
 *  php jobs/scheduler.php
 *  php jobs/scheduler.php --config=/caminho/para/scheduler.json
 *  php jobs/scheduler.php --baseDir=/caminho/para/01_backend_painel_php
 *
 * Override via env:
 *  SCHEDULER_CONFIG=/caminho/scheduler.json php jobs/scheduler.php
 */

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

function err(string $msg): void
{
    // Em CLI, STDERR existe e é melhor para logs de erro.
    if (PHP_SAPI === 'cli' && defined('STDERR')) {
        fwrite(STDERR, $msg . PHP_EOL);
        return;
    }
    out($msg);
}

/**
 * Lê argumentos simples no formato:
 *  --key=value
 *  --key value
 */
function cli_arg(string $key): ?string
{
    $argv = $_SERVER['argv'] ?? [];
    $prefix = '--' . $key;
    $n = count($argv);
    for ($i = 1; $i < $n; $i++) {
        $a = (string)$argv[$i];
        if (strpos($a, $prefix . '=') === 0) {
            return (string)substr($a, strlen($prefix) + 1);
        }
        if ($a === $prefix && isset($argv[$i + 1])) {
            return (string)$argv[$i + 1];
        }
    }
    return null;
}

/**
 * Flag booleana simples no formato:
 *   --flag
 *   --flag=1
 *   --flag=true
 *
 * Observação: se usar "--flag=0" ou "--flag=false", retorna false.
 */
function cli_flag(string $key): bool
{
    $argv = $_SERVER['argv'] ?? [];
    $prefix = '--' . $key;
    foreach ($argv as $i => $a) {
        if ($i === 0) {
            continue;
        }
        $a = (string)$a;
        if ($a === $prefix) {
            return true;
        }
        if (strpos($a, $prefix . '=') === 0) {
            $val = strtolower(trim((string)substr($a, strlen($prefix) + 1)));
            if ($val === '' || $val === '1' || $val === 'true' || $val === 'yes' || $val === 'on') {
                return true;
            }
            if ($val === '0' || $val === 'false' || $val === 'no' || $val === 'off') {
                return false;
            }
            // Valor desconhecido -> considera presente.
            return true;
        }
    }
    return false;
}

/**
 * Resolve o diretório raiz do backend (01_backend_painel_php).
 */
function resolve_backend_dir(?string $override = null): ?string
{
    if ($override) {
        $real = realpath($override);
        return ($real && is_dir($real)) ? $real : null;
    }

    // Estrutura esperada do pacote reorganizado:
    //   /RedeAlabama_Platform0/
    //     01_backend_painel_php/
    //     02_whatsapp_automation_engine/jobs/scheduler.php
    $candidate = realpath(__DIR__ . '/../../01_backend_painel_php');
    if ($candidate && is_dir($candidate)) {
        return $candidate;
    }

    // Fallback: tenta achar subindo pastas.
    $dir = realpath(__DIR__) ?: __DIR__;
    for ($i = 0; $i < 6; $i++) {
        $try = $dir . DIRECTORY_SEPARATOR . '01_backend_painel_php';
        if (is_dir($try)) {
            $real = realpath($try);
            return ($real && is_dir($real)) ? $real : $try;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    return null;
}

function resolve_config_path(string $backendDir, ?string $override = null): ?string
{
    // 1) override por CLI
    if ($override) {
        $real = realpath($override);
        return ($real && is_file($real)) ? $real : null;
    }

    // 2) env var
    $env = getenv('SCHEDULER_CONFIG');
    if (is_string($env) && $env !== '') {
        $real = realpath($env);
        if ($real && is_file($real)) {
            return $real;
        }
    }

    // 3) padrão: backend/config/scheduler.json
    $p1 = $backendDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'scheduler.json';
    if (is_file($p1)) {
        $real = realpath($p1);
        return $real ?: $p1;
    }

    // 4) fallback: backend/config/scheduler.example.json
    $p2 = $backendDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'scheduler.example.json';
    if (is_file($p2)) {
        $real = realpath($p2);
        return $real ?: $p2;
    }

    // 5) fallback: 02_whatsapp_automation_engine/jobs/config/scheduler.json
    $p3 = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'scheduler.json';
    if (is_file($p3)) {
        $real = realpath($p3);
        return $real ?: $p3;
    }

    return null;
}

$baseDirOverride = cli_arg('baseDir');
$backendDir = resolve_backend_dir($baseDirOverride);
if (!$backendDir) {
    err("[scheduler] ERRO: não foi possível localizar a pasta 01_backend_painel_php.");
    err("[scheduler] Dica: rode com --baseDir=/caminho/para/01_backend_painel_php");
    exit(1);
}

$configOverride = cli_arg('config');
$configPath = resolve_config_path($backendDir, $configOverride);
if (!$configPath) {
    err("[scheduler] scheduler.json não encontrado.");
    err("[scheduler] Esperado em: {$backendDir}/config/scheduler.json");
    err("[scheduler] Você pode copiar {$backendDir}/config/scheduler.example.json para scheduler.json e ajustar.");
    exit(0);
}

// Executa as tasks no diretório do backend.
if (!@chdir($backendDir)) {
    err("[scheduler] ERRO: não consegui dar chdir() para: {$backendDir}");
    exit(1);
}

$raw = file_get_contents($configPath);
if ($raw === false) {
    err("[scheduler] Falha ao ler: {$configPath}");
    exit(1);
}

$decoded = json_decode($raw, true);
if (!is_array($decoded) || empty($decoded['tasks']) || !is_array($decoded['tasks'])) {
    err("[scheduler] Config inválida ou sem tasks: {$configPath}");
    exit(0);
}

// Se for o example, avisar (mas ainda executar).
if (preg_match('/scheduler\.example\.json$/', $configPath)) {
    out("[scheduler] Aviso: usando scheduler.example.json (exemplo). Crie scheduler.json para produção.");
}

out("[scheduler] Backend: {$backendDir}");
out("[scheduler] Config:  {$configPath}");

// Carrega Security helpers a partir do backendDir resolvido (evita path hardcoded).
$securityFile = $backendDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'Security.php';
if (!is_file($securityFile)) {
    err("[scheduler] ERRO: Security.php não encontrado em: {$securityFile}");
    exit(1);
}
require_once $securityFile;
if (!class_exists('Security')) {
    err("[scheduler] ERRO: Classe Security não carregou corretamente.");
    exit(1);
}

$dryRun = cli_flag('dry-run');

$globalExitCode = 0;
foreach ($decoded['tasks'] as $task) {
    if (!is_array($task)) {
        continue;
    }

    // Permite desligar tasks via config.
    if (isset($task['enabled']) && !$task['enabled']) {
        continue;
    }

    $cmd = isset($task['command']) ? (string)$task['command'] : '';
    $cmd = trim($cmd);
    if ($cmd === '') {
        continue;
    }

    // Evita comandos multi-linha acidentalmente.
    if (strpos($cmd, "\n") !== false || strpos($cmd, "\r") !== false) {
        err('[scheduler] Ignorando comando inválido (contém quebra de linha).');
        continue;
    }

    $name = isset($task['name']) ? (string)$task['name'] : $cmd;

    out("[scheduler] Executando: {$name}");
    out("[scheduler] CMD: {$cmd}");

    // Opcional: permitir que uma task rode em um cwd específico.
    $originalCwd = getcwd();
    if (isset($task['cwd']) && is_string($task['cwd']) && $task['cwd'] !== '') {
        $targetCwd = $task['cwd'];
        if (@chdir($targetCwd)) {
            out("[scheduler] CWD: {$targetCwd}");
        } else {
            err("[scheduler] Aviso: não consegui chdir() para cwd da task: {$targetCwd}. Mantendo backend dir.");
        }
    }

    if ($dryRun) {
        out('[scheduler] (dry-run) não executado.');
        if ($originalCwd) {
            @chdir($originalCwd);
        }
        continue;
    }

    $exitCode = 0;
    $safe = Security::safe_exec($cmd);
    $exitCode = $safe['exit_code'] ?? 1;

    if ($originalCwd) {
        @chdir($originalCwd);
    }

    if ($exitCode !== 0) {
        err("[scheduler] Task falhou (exit={$exitCode}): {$name}");
        $globalExitCode = $exitCode;
    } else {
        out("[scheduler] OK: {$name}");
    }
}

exit($globalExitCode);
