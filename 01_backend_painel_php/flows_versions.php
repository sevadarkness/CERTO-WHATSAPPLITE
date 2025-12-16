<?php
declare(strict_types=1);

/**
 * flows_versions.php
 *
 * Tela simples para visualizar e operar versões de um fluxo WhatsApp.
 */

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flows_versioning.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$pdo = $pdo ?? null;

$erro    = null;
$sucesso = null;

$flowId = isset($_GET['flow_id']) ? (int) $_GET['flow_id'] : 0;
if ($flowId <= 0) {
    $erro = 'ID de fluxo inválido. Acesse um fluxo a partir do Gerenciador de Fluxos.';
}

if ($erro === null && !flow_versioning_is_enabled($pdo)) {
    $erro = 'Versionamento de fluxos não está habilitado (tabela whatsapp_flow_versions ausente).';
}

if ($erro === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check_post()) {
        $erro = 'Falha de validação CSRF.';
    } else {
        $action    = $_POST['action'] ?? '';
        $versionId = isset($_POST['version_id']) ? (int) $_POST['version_id'] : 0;
        $reason    = trim((string) ($_POST['reason'] ?? ''));

        $userId = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;

        if ($action === 'snapshot') {
            try {
                flow_versioning_snapshot($pdo, $flowId, $userId, $reason !== '' ? $reason : 'snapshot manual');
                $sucesso = 'Snapshot criado com sucesso.';
            } catch (Throwable $e) {
                $erro = 'Erro ao criar snapshot: ' . $e->getMessage();
            }
        } elseif ($action === 'rollback' && $versionId > 0) {
            try {
                $ok = flow_versioning_rollback($pdo, $flowId, $versionId, $userId);
                if ($ok) {
                    $sucesso = 'Rollback realizado com sucesso.';
                } else {
                    $erro = 'Rollback não pôde ser concluído (verifique as versões disponíveis).';
                }
            } catch (Throwable $e) {
                $erro = 'Erro ao executar rollback: ' . $e->getMessage();
            }
        } else {
            $erro = 'Ação inválida.';
        }
    }
}

// Carrega dados básicos do fluxo e suas versões
$flow     = null;
$versions = [];

if ($erro === null) {
    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\FlowsVersionsRepository($pdo))->prepare_2413();
        $stmt->execute([':id' => $flowId]);
        $flow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$flow) {
            $erro = 'Fluxo não encontrado.';
        } else {
            $versions = flow_versioning_list($pdo, $flowId);
        }

        // Cálculo opcional de diff JSON entre a versão selecionada e o estado atual do fluxo
        $diffVersionId = isset($_GET['compare_version_id']) ? (int) $_GET['compare_version_id'] : 0;
        $diff = null;
        if ($diffVersionId > 0) {
            try {
                $diff = flow_versioning_diff_version($pdo, $flowId, $diffVersionId);
            } catch (Throwable $e) {
                $erro = 'Erro ao calcular diferenças da versão selecionada: ' . $e->getMessage();
            }
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar dados do fluxo: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Versionamento de Fluxos - Rede Alabama</title>
    <link rel="stylesheet" href="alabama-theme.css">
</head>
<body class="alabama-body">
<div class="container mt-4">
    <h1 class="mb-3">Versionamento de Fluxos</h1>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (isset($diff) && is_array($diff) && !$erro): ?>
        <div class="card mb-4" id="diff">
            <div class="card-header">
                Diferenças entre a versão #<?php echo isset($diff['version']['version_number']) ? (int) $diff['version']['version_number'] : 0; ?> e o fluxo atual
            </div>
            <div class="card-body">
                <?php if (!empty($diff['flow_diff'])): ?>
                    <h5>Diferenças de campos do fluxo</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Atual</th>
                                <th>Versão</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($diff['flow_diff'] as $d): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) $d['field'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><code><?php echo htmlspecialchars(json_encode($d['current'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    <td><code><?php echo htmlspecialchars(json_encode($d['version'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php $stepsDiff = $diff['steps_diff'] ?? ['added' => [], 'removed' => [], 'changed' => []]; ?>

                <?php if (!empty($stepsDiff['added']) || !empty($stepsDiff['removed']) || !empty($stepsDiff['changed'])): ?>
                    <h5>Diferenças de steps</h5>

                    <?php if (!empty($stepsDiff['added'])): ?>
                        <h6 class="mt-3">Steps que serão ADICIONADOS após rollback</h6>
                        <pre class="bg-light p-2 small"><?php echo htmlspecialchars(json_encode($stepsDiff['added'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
                    <?php endif; ?>

                    <?php if (!empty($stepsDiff['removed'])): ?>
                        <h6 class="mt-3">Steps que serão REMOVIDOS após rollback</h6>
                        <pre class="bg-light p-2 small"><?php echo htmlspecialchars(json_encode($stepsDiff['removed'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
                    <?php endif; ?>

                    <?php if (!empty($stepsDiff['changed'])): ?>
                        <h6 class="mt-3">Steps ALTERADOS</h6>
                        <pre class="bg-light p-2 small"><?php echo htmlspecialchars(json_encode($stepsDiff['changed'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($flow && !$erro): ?>
        <div class="card mb-3">
            <div class="card-header">
                Fluxo #<?php echo (int) $flow['id']; ?> -
                <?php echo htmlspecialchars((string) ($flow['name'] ?? 'sem nome'), ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="card-body">
                <p class="mb-1">
                    <strong>Status:</strong>
                    <?php echo htmlspecialchars((string) ($flow['status'] ?? 'desconhecido'), ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <?php if (!empty($flow['description'])): ?>
                    <p class="mb-0">
                        <strong>Descrição:</strong>
                        <?php echo nl2br(htmlspecialchars((string) $flow['description'], ENT_QUOTES, 'UTF-8')); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                Criar novo snapshot
            </div>
            <div class="card-body">
                <form method="post">
                    <?php echo csrf_hidden_input(); ?>
                    <input type="hidden" name="action" value="snapshot">
                    <input type="hidden" name="flow_id" value="<?php echo (int) $flowId; ?>">

                    <div class="mb-3">
                        <label for="reason" class="form-label">Motivo (opcional)</label>
                        <input type="text" class="form-control" id="reason" name="reason"
                               placeholder="Ex.: antes de alterar steps, ajuste de copy, etc.">
                    </div>

                    <button type="submit" class="btn btn-primary">Criar snapshot</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                Histórico de versões
            </div>
            <div class="card-body p-0">
                <?php if (!$versions): ?>
                    <div class="p-3 text-muted">Nenhuma versão encontrada para este fluxo.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Criada em</th>
                                <th>Criada por (ID)</th>
                                <th>Motivo</th>
                                <th>Comparar</th>
                                <th>Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($versions as $v): ?>
                                <tr>
                                    <td><?php echo (int) $v['version_number']; ?></td>
                                    <td><?php echo htmlspecialchars((string) $v['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $v['created_by_user_id'] !== null ? (int) $v['created_by_user_id'] : '-'; ?></td>
                                    <td><?php echo htmlspecialchars((string) ($v['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <a href="flows_versions.php?flow_id=<?php echo (int) $flowId; ?>&compare_version_id=<?php echo (int) $v['id']; ?>#diff"
                                           class="btn btn-sm btn-outline-secondary">
                                            Ver diff com atual
                                        </a>
                                    </td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Confirmar rollback para esta versão?');" class="d-inline">
                                            <?php echo csrf_hidden_input(); ?>
                                            <input type="hidden" name="action" value="rollback">
                                            <input type="hidden" name="flow_id" value="<?php echo (int) $flowId; ?>">
                                            <input type="hidden" name="version_id" value="<?php echo (int) $v['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Rollback
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
