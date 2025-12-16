<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador']);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/security_helpers.php';

$candidates = [];
$envScript = trim((string)(getenv('ALABAMA_APPLY_ENV_SCRIPT') ?: ''));
if ($envScript !== '') {
    $candidates[] = $envScript;
}
$candidates[] = __DIR__ . '/apply-env.sh';
$candidates[] = dirname(__DIR__) . '/06_deploy_infra/scripts/apply-env.sh';
$candidates[] = '/var/www/html/apply-env.sh';

$scriptPath = null;
foreach ($candidates as $cand) {
    if ($cand !== '' && is_file($cand)) {
        $scriptPath = $cand;
        break;
    }
}

// Mantém uma string sempre definida para renderização
$scriptPath = $scriptPath ?: ($envScript !== '' ? $envScript : '/var/www/html/apply-env.sh');
$logDir  = __DIR__ . '/logs';
$logPath = $logDir . '/apply-env.log';

if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$erro = null;
$sucesso = null;

// Disparo manual do apply-env via botão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'disparar') {
    csrf_require();

    if (!is_file($scriptPath)) {
        $erro = "apply-env.sh não encontrado em {$scriptPath}.";
    } else {
        @chmod($scriptPath, 0755);
        require_once __DIR__ . '/app/Support/Security.php';

        // Executa sem operadores de shell (>, &, etc.) para respeitar a política do safe_exec.
        $res = Security::safe_exec($scriptPath);
        $exitCode = $res['exit_code'] ?? null;

        // Persistimos o output no log para diagnóstico.
        if (!empty($res['output'])) {
            @file_put_contents($logPath, $res['output'] . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        if (!empty($res['error'])) {
            $erro = "Falha ao executar apply-env.sh: " . $res['error'] . ". Verifique o log.";
            log_app_event('apply_env_dashboard', 'manual_trigger_error', [
                'script'    => $scriptPath,
                'exit_code' => $exitCode,
                'error'     => $res['error'],
            ]);
        } elseif ($exitCode !== null && (int)$exitCode !== 0) {
            $erro = "apply-env.sh executou mas retornou código " . (string)$exitCode . ". Verifique o log.";
            log_app_event('apply_env_dashboard', 'manual_trigger_nonzero_exit', [
                'script'    => $scriptPath,
                'exit_code' => $exitCode,
            ]);
        } else {
            $sucesso = "apply-env.sh executado com sucesso.";
            log_app_event('apply_env_dashboard', 'manual_trigger', [
                'script'    => $scriptPath,
                'exit_code' => $exitCode,
            ]);
        }
    }
}

// status do script/log
$scriptExists   = is_file($scriptPath);
$scriptExec     = $scriptExists && is_executable($scriptPath);
$logExists      = is_file($logPath);
$logMtime       = $logExists ? date('Y-m-d H:i:s', filemtime($logPath)) : null;
$logTail        = '';

if ($logExists) {
    $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        $logTail = implode(PHP_EOL, array_slice($lines, -200));
    }
}

include __DIR__ . '/menu_navegacao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <meta charset="UTF-8">
    <title>Status apply-env</title>
    <link rel="stylesheet"
</head>
<body class="al-body">
<div class="container my-4">
    <h1 class="h3 mb-3">Status apply-env</h1>
    <p class="text-muted">
        Monitor de execução do script <code>apply-env.sh</code> usado para recarregar containers após mudança no .env.
    </p>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="card bg-secondary text-light mb-3">
        <div class="card-header">Resumo</div>
        <div class="card-body">
            <p><strong>Script:</strong> <code><?php echo htmlspecialchars($scriptPath, ENT_QUOTES, 'UTF-8'); ?></code></p>
            <p><strong>Existe:</strong> <?php echo $scriptExists ? 'Sim' : 'Não'; ?></p>
            <p><strong>Executável:</strong> <?php echo $scriptExec ? 'Sim' : 'Não'; ?></p>
            <p><strong>Log:</strong> <code><?php echo htmlspecialchars($logPath, ENT_QUOTES, 'UTF-8'); ?></code></p>
            <p><strong>Log existe:</strong> <?php echo $logExists ? 'Sim' : 'Não'; ?></p>
            <p><strong>Última modificação do log:</strong> <?php echo $logMtime ?: '-'; ?></p>

            <form method="post" class="mt-3">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="acao" value="disparar">
                <button type="submit" class="btn btn-primary">
                    Disparar apply-env agora
                </button>
            </form>
        </div>
    </div>

    <div class="card bg-secondary text-light">
        <div class="card-header">Últimas linhas do apply-env.log</div>
        <div class="card-body">
            <?php if (!$logExists): ?>
                <p class="text-muted mb-0">Nenhum log encontrado ainda.</p>
            <?php else: ?>
                <pre style="max-height:400px;overflow:auto;background:#020617;color:#e5e7eb;"><?php
                    echo htmlspecialchars($logTail, ENT_QUOTES, 'UTF-8');
                ?></pre>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
