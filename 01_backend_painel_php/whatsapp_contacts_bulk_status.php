<?php
declare(strict_types=1);

/**
 * Tela de status de campanhas de envio em massa de WhatsApp.
 *
 * Permite:
 *  - Listar campanhas (jobs) com contadores de sucesso/falha.
 *  - Ver status atual (queued, running, finished, failed, paused).
 *  - Reprocessar apenas itens com status failed (requeue).
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';

$embed = isset($_GET['embed']) && $_GET['embed'] === '1';

if (!$embed) {
    include __DIR__ . '/menu_navegacao.php';
}


$pdo = get_db_connection();
$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = $_POST['action'] ?? '';
    $jobId  = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;

    if ($jobId <= 0) {
        $erro = 'Job inválido.';
    } else {
        try {
            if ($action === 'retry_failed') {
                // Volta itens failed para pending
                $stmt = $pdo->prepare("
                    UPDATE whatsapp_bulk_job_items
                    SET status = 'pending', last_error = NULL
                    WHERE bulk_job_id = :job_id AND status = 'failed'
                ");
                $stmt->execute([':job_id' => $jobId]);

                // Reseta contadores de falha no job
                $stmtJob = $pdo->prepare("
                    UPDATE whatsapp_bulk_jobs
                    SET status = 'queued',
                        enviados_falha = 0,
                        finalizado_em = NULL
                    WHERE id = :job_id
                ");
                $stmtJob->execute([':job_id' => $jobId]);

                $sucesso = 'Itens com falha foram re-enfileirados para o job ' . $jobId . '.';
                log_app_event('whatsapp_bulk', 'retry_failed', ['job_id' => $jobId]);

            } elseif ($action === 'pause') {
                $stmtJob = $pdo->prepare("
                    UPDATE whatsapp_bulk_jobs
                    SET status = 'paused'
                    WHERE id = :job_id
                ");
                $stmtJob->execute([':job_id' => $jobId]);

                $sucesso = 'Job pausado com sucesso.';
                log_app_event('whatsapp_bulk', 'pause_job', ['job_id' => $jobId]);

            } elseif ($action === 'resume') {
                $stmtJob = $pdo->prepare("
                    UPDATE whatsapp_bulk_jobs
                    SET status = 'queued'
                    WHERE id = :job_id AND status = 'paused'
                ");
                $stmtJob->execute([':job_id' => $jobId]);

                $sucesso = 'Job reativado com sucesso.';
                log_app_event('whatsapp_bulk', 'resume_job', ['job_id' => $jobId]);
            }
        } catch (Throwable $e) {
            $erro = 'Erro ao processar ação: ' . $e->getMessage();
            log_app_event('whatsapp_bulk', 'status_action_error', [
                'job_id' => $jobId,
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}

// Lista jobs recentes
$stmt = $pdo->query("
    SELECT *
    FROM whatsapp_bulk_jobs
    ORDER BY created_at DESC
    LIMIT 50
");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Campanhas em Massa - WhatsApp</title>
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-dark text-light">
<div class="container my-4">
    <h1 class="h3 mb-3">Campanhas em Massa de WhatsApp</h1>
    <p class="text-muted">
        Visão geral das campanhas de envio em massa. Use esta tela para acompanhar o progresso e reprocessar falhas.
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

    <div class="card bg-secondary text-light">
        <div class="card-body">
            <?php if (!$jobs): ?>
                <p class="text-muted mb-0">Nenhuma campanha criada até o momento.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Status</th>
                                <th>Simulação</th>
                                <th>Total</th>
                                <th>Sucesso</th>
                                <th>Falha</th>
                                <th>Agendado para</th>
                                <th>Iniciado</th>
                                <th>Finalizado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td><?php echo (int)$job['id']; ?></td>
                                <td><?php echo htmlspecialchars($job['nome_campanha'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($job['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo ((int)$job['is_simulation'] === 1) ? 'Sim' : 'Não'; ?></td>
                                <td><?php echo (int)$job['total_destinatarios']; ?></td>
                                <td><?php echo (int)$job['enviados_sucesso']; ?></td>
                                <td><?php echo (int)$job['enviados_falha']; ?></td>
                                <td><?php echo $job['agendado_para'] ? htmlspecialchars($job['agendado_para'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                <td><?php echo $job['iniciado_em'] ? htmlspecialchars($job['iniciado_em'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                <td><?php echo $job['finalizado_em'] ? htmlspecialchars($job['finalizado_em'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                <td>
                                    <form method="post" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                                        <input type="hidden" name="action" value="retry_failed">
                                        <button type="submit" class="btn btn-sm btn-warning mb-1">
                                            Reenviar falhas
                                        </button>
                                    </form>

                                    <?php if ($job['status'] === 'paused'): ?>
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                                            <input type="hidden" name="action" value="resume">
                                            <button type="submit" class="btn btn-sm btn-success mb-1">
                                                Retomar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" class="d-inline">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="job_id" value="<?php echo (int)$job['id']; ?>">
                                            <input type="hidden" name="action" value="pause">
                                            <button type="submit" class="btn btn-sm btn-secondary mb-1">
                                                Pausar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
