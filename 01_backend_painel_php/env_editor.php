<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador']);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/security_helpers.php';

// Caminho do .env (pode variar no deploy). Por padrão, assume .env no diretório do backend.
// Pode ser sobrescrito por ALABAMA_ENV_PATH.
$envPath = getenv('ALABAMA_ENV_PATH') ?: (__DIR__ . '/.env');
$logDir  = __DIR__ . '/logs';
$logPath = $logDir . '/apply-env.log';

if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$erro = null;
$sucesso = null;
$envContent = '';

if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if ($envContent === false) {
        $erro = "Falha ao ler o arquivo .env.";
    }
} else {
    $erro = "Arquivo .env não encontrado.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $acao = $_POST['acao'] ?? 'salvar';
    $novo = $_POST['env_content'] ?? '';

    if (trim($novo) === '') {
        $erro = "Não é permitido salvar o .env vazio.";
    } elseif (strlen($novo) > 64 * 1024) {
        $erro = "Conteúdo muito grande para .env (limite 64KB).";
    } else {
        if (file_put_contents($envPath, $novo) === false) {
            $erro = "Falha ao gravar o .env.";
        } else {
            $envContent = $novo;
            $sucesso = "Arquivo .env atualizado com sucesso.";
            log_app_event('env_editor', 'env_updated', []);

            if ($acao === 'atualizar') {
                $candidates = [];
                $envScript = trim((string)(getenv('ALABAMA_APPLY_ENV_SCRIPT') ?: ''));
                if ($envScript !== '') {
                    $candidates[] = $envScript;
                }
                $candidates[] = __DIR__ . '/apply-env.sh';
                $candidates[] = dirname(__DIR__) . '/06_deploy_infra/scripts/apply-env.sh';
                $candidates[] = '/var/www/html/apply-env.sh';

                $script = null;
                foreach ($candidates as $cand) {
                    if ($cand !== '' && is_file($cand)) {
                        $script = $cand;
                        break;
                    }
                }

                if ($script) {
                    @chmod($script, 0755);
                    require_once __DIR__ . '/app/Support/Security.php';

                    // Executa sem operadores de shell (>, &, etc.) para respeitar a política do safe_exec.
                    $res = Security::safe_exec($script);
                    $exitCode = $res['exit_code'] ?? null;

                    // Persistimos o output no log para diagnóstico.
                    if (!empty($res['output'])) {
                        @file_put_contents($logPath, $res['output'] . PHP_EOL, FILE_APPEND | LOCK_EX);
                    }

                    if (!empty($res['error'])) {
                        $erro = "Falha ao executar apply-env.sh: " . $res['error'] . " (ver logs/apply-env.log).";
                        log_app_event('env_editor', 'env_apply_error', [
                            'script'    => $script,
                            'exit_code' => $exitCode,
                            'error'     => $res['error'],
                        ]);
                    } elseif ($exitCode !== null && (int)$exitCode !== 0) {
                        $erro = "apply-env.sh executou mas retornou código " . (string)$exitCode . ". Verifique logs/apply-env.log.";
                        log_app_event('env_editor', 'env_apply_nonzero_exit', [
                            'script'    => $script,
                            'exit_code' => $exitCode,
                        ]);
                    } else {
                        $sucesso .= ' Ambiente aplicado (apply-env.sh executado).';
                        log_app_event('env_editor', 'env_apply_triggered', [
                            'script'    => $script,
                            'exit_code' => $exitCode,
                        ]);
                    }
                } else {
                    $erro = "apply-env.sh não encontrado. Configure ALABAMA_APPLY_ENV_SCRIPT ou coloque o script em um dos caminhos conhecidos.";
                    log_app_event('env_editor', 'env_apply_not_found', ['candidates' => $candidates]);
                }
            }
        }
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
    <title>Editor de .env</title>
    <link rel="stylesheet"
</head>
<body class="al-body">
<div class="container my-4">
    <h1 class="h3 mb-3">Editor de .env (Configuração Avançada)</h1>
    <p class="text-warning">
        Qualquer alteração aqui impacta diretamente o comportamento do painel. Use com extremo cuidado.
    </p>

    <?php if ($erro): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($envContent !== ''): ?>
        <form method="post">
            <?php echo csrf_field(); ?>

            <div class="mb-3">
                <label class="form-label">Conteúdo do .env</label>
                <textarea name="env_content"
                          rows="25"
                          class="form-control font-monospace"
                          style="background:#020617;color:#e5e7eb;"><?php
                    echo htmlspecialchars($envContent, ENT_QUOTES, 'UTF-8');
                ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit"
                        name="acao"
                        value="salvar"
                        class="btn btn-outline-light">
                    Salvar .env
                </button>

                <button type="submit"
                        name="acao"
                        value="atualizar"
                        class="btn btn-primary ml-2">
                    ATUALIZAR (salvar e aplicar)
                </button>
            </div>

            <small class="form-text text-muted mt-2">
                O botão <strong>ATUALIZAR</strong> tenta executar <code>/var/www/html/apply-env.sh</code>.
                Saída do script: <code><?php echo htmlspecialchars($logPath, ENT_QUOTES, 'UTF-8'); ?></code>
            </small>
        </form>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
