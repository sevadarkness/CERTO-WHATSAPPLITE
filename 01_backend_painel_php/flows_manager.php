<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/llm_templates.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$erro    = null;
$sucesso = null;

$action  = isset($_GET['action']) ? (string)$_GET['action'] : 'list';
$flowId  = isset($_GET['flow_id']) ? (int)$_GET['flow_id'] : 0;

// Carrega templates para steps de WhatsApp (contexto whatsapp_ai)
$templates_raw = [
    'whatsapp_resposta_cliente' => 'Resposta para cliente em canal WhatsApp.',
    'whatsapp_cobranca'         => 'Cobrança amigável de inadimplente.',
    'whatsapp_reativacao'       => 'Reativação de cliente inativo.',
    'campanha_broadcast'        => 'Campanhas de broadcast com foco em engajamento.',
];
$templates = alabama_llm_templates_get($pdo, 'whatsapp_ai', $templates_raw);

// Tratamento de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if (isset($_POST['save_flow'])) {
        $name        = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $status      = trim((string)($_POST['status'] ?? 'rascunho'));
        $segment     = trim((string)($_POST['target_segment'] ?? ''));

        if ($name === '') {
            $erro = 'Nome do fluxo é obrigatório.';
        } else {
            try {
                if ($flowId > 0) {
                    $sql = "UPDATE whatsapp_flows
                               SET name = :name,
                                   description = :description,
                                   status = :status,
                                   target_segment = :segment,
                                   updated_at = CURRENT_TIMESTAMP
                             WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':name'    => $name,
                        ':description' => $description,
                        ':status'  => $status,
                        ':segment' => $segment !== '' ? $segment : null,
                        ':id'      => $flowId,
                    ]);
                } else {
                    $sql = "INSERT INTO whatsapp_flows (name, description, status, target_segment)
                                  VALUES (:name, :description, :status, :segment)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':name'    => $name,
                        ':description' => $description,
                        ':status'  => $status,
                        ':segment' => $segment !== '' ? $segment : null,
                    ]);
                    $flowId = (int)$pdo->lastInsertId();
                }

                log_app_event('flows', 'save_flow', [
                    'flow_id' => $flowId,
                    'status'  => $status,
                ]);

                $sucesso = 'Fluxo salvo com sucesso.';
                $action  = 'edit';
            } catch (Throwable $e) {
                $erro = 'Erro ao salvar fluxo: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_step']) && $flowId > 0) {
        $stepOrder = isset($_POST['step_order']) ? (int)$_POST['step_order'] : 0;
        $stepType  = trim((string)($_POST['step_type'] ?? 'mensagem_whatsapp'));
        $tplSlug   = trim((string)($_POST['template_slug'] ?? ''));
        $delayMin  = isset($_POST['delay_minutes']) ? (int)$_POST['delay_minutes'] : 0;

        if ($tplSlug === '' || !array_key_exists($tplSlug, $templates)) {
            $erro = 'Template inválido para o step.';
        } else {
            try {
                $sql = "INSERT INTO whatsapp_flow_steps (flow_id, step_order, step_type, template_slug, delay_minutes, is_active)
                              VALUES (:fid, :ord, :type, :tpl, :delay, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':fid'   => $flowId,
                    ':ord'   => $stepOrder,
                    ':type'  => $stepType,
                    ':tpl'   => $tplSlug,
                    ':delay' => $delayMin,
                ]);

                log_app_event('flows', 'add_step', [
                    'flow_id' => $flowId,
                    'template_slug' => $tplSlug,
                ]);

                $sucesso = 'Step adicionado ao fluxo.';
                $action  = 'edit';
            } catch (Throwable $e) {
                $erro = 'Erro ao adicionar step: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_step']) && $flowId > 0) {
        $stepId = isset($_POST['step_id']) ? (int)$_POST['step_id'] : 0;
        if ($stepId > 0) {
            try {
                $stmt = (new \RedeAlabama\Repositories\Screens\FlowsManagerRepository($pdo))->prepare_4954();
                $stmt->execute([
                    ':id'  => $stepId,
                    ':fid' => $flowId,
                ]);
                log_app_event('flows', 'delete_step', [
                    'flow_id' => $flowId,
                    'step_id' => $stepId,
                ]);
                $sucesso = 'Step removido.';
                $action  = 'edit';
            } catch (Throwable $e) {
                $erro = 'Erro ao remover step: ' . $e->getMessage();
            }
        }
    }
}

// Carregamento de dados para views
$flow = null;
$steps = [];

if ($action === 'edit' && $flowId > 0) {
    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\FlowsManagerRepository($pdo))->prepare_5679();
        $stmt->execute([':id' => $flowId]);
        $flow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($flow) {
            $stmtSteps = (new \RedeAlabama\Repositories\Screens\FlowsManagerRepository($pdo))->prepare_5880();
            $stmtSteps->execute([':id' => $flowId]);
            $steps = $stmtSteps->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $action = 'list';
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar fluxo: ' . $e->getMessage();
        $action = 'list';
    }
}

// Lista de fluxos para a view principal
$flows = [];
if ($action === 'list') {
    try {
        $sql = "SELECT f.*, COALESCE(COUNT(s.id), 0) AS total_steps
                  FROM whatsapp_flows f
             LEFT JOIN whatsapp_flow_steps s ON s.flow_id = f.id
              GROUP BY f.id
              ORDER BY f.created_at DESC, f.id DESC";
        $stmt = $pdo->query($sql);
        $flows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $erro = 'Erro ao listar fluxos: ' . $e->getMessage();
    }
}

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
    <title>Gerenciador de Fluxos WhatsApp + IA</title>
</head>
<body class="al-body">
<div class="container my-4">
    <h1 class="h3 mb-3">Gerenciador de Fluxos (WhatsApp + IA)</h1>
    <p class="text-muted">
        Configure sequências de passos (flows) para campanhas de WhatsApp usando templates LLM.
        Este módulo define apenas a orquestração; a execução efetiva deve ser feita por jobs (jobs_runner.php)
        lendo as tabelas <code>whatsapp_flows</code> e <code>whatsapp_flow_steps</code>.
    </p>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div class="mb-3">
            <a href="?action=edit" class="btn btn-primary">Criar novo fluxo</a>
        </div>

        <div class="card bg-secondary mb-3">
            <div class="card-header">Fluxos configurados</div>
            <div class="card-body p-0">
                <table class="table table-dark table-striped mb-0">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Status</th>
                        <th>Segmento alvo</th>
                        <th>Steps</th>
                        <th>Criado em</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$flows): ?>
                        <tr><td colspan="7" class="text-center text-muted">Nenhum fluxo configurado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($flows as $f): ?>
                            <tr>
                                <td><?php echo (int)$f['id']; ?></td>
                                <td><?php echo htmlspecialchars($f['name']); ?></td>
                                <td><?php echo htmlspecialchars($f['status']); ?></td>
                                <td><?php echo htmlspecialchars($f['target_segment'] ?? ''); ?></td>
                                <td><?php echo (int)$f['total_steps']; ?></td>
                                <td><?php echo htmlspecialchars($f['created_at'] ?? ''); ?></td>
                                <td>
                                    <a href="?action=edit&flow_id=<?php echo (int)$f['id']; ?>" class="btn btn-sm btn-outline-light">Editar</a> <a href="flows_visual_builder.php?flow_id=<?php echo (int)$f['id']; ?>" class="btn btn-sm btn-info ml-1" title="Abrir builder visual (drag & drop)">Builder Visual</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($action === 'edit'): ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card bg-secondary mb-3">
                    <div class="card-header">
                        <?php echo $flowId > 0 ? 'Editar fluxo' : 'Novo fluxo'; ?>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <div class="form-group">
                                <label for="name">Nome do fluxo</label>
                                <input type="text" name="name" id="name" class="form-control"
                                       value="<?php echo htmlspecialchars($flow['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Descrição</label>
                                <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($flow['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <?php
                                    $statuses = ['rascunho' => 'Rascunho', 'ativo' => 'Ativo', 'pausado' => 'Pausado'];
                                    $currentStatus = $flow['status'] ?? 'rascunho';
                                    foreach ($statuses as $value => $label):
                                        ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($currentStatus === $value) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="target_segment">Segmento alvo (ex.: D30+, D0–D3, todos)</label>
                                <input type="text" name="target_segment" id="target_segment" class="form-control"
                                       value="<?php echo htmlspecialchars($flow['target_segment'] ?? ''); ?>">
                            </div>
                            <button type="submit" name="save_flow" class="btn btn-primary">Salvar fluxo</button>
                            <a href="?action=list" class="btn btn-outline-light">Voltar</a>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($flowId > 0 && $flow): ?>
                <div class="col-md-6">
                    <div class="card bg-secondary mb-3">
                        <div class="card-header">Steps do fluxo</div>
                        <div class="card-body">
                            <h5 class="card-title">Steps existentes</h5>
                            <table class="table table-dark table-sm">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tipo</th>
                                    <th>Template</th>
                                    <th>Delay (min)</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!$steps): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Nenhum step configurado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($steps as $s): ?>
                                        <tr>
                                            <td><?php echo (int)$s['step_order']; ?></td>
                                            <td><?php echo htmlspecialchars($s['step_type']); ?></td>
                                            <td><?php echo htmlspecialchars($s['template_slug']); ?></td>
                                            <td><?php echo (int)$s['delay_minutes']; ?></td>
                                            <td>
                                                <form method="post" style="display:inline-block" onsubmit="return confirm('Remover este step?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="step_id" value="<?php echo (int)$s['id']; ?>">
                                                    <button type="submit" name="delete_step" class="btn btn-sm btn-danger">Remover</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>

                            <hr>

                            <h5 class="card-title">Adicionar novo step</h5>
                            <form method="post">
                                <?php echo csrf_field(); ?>
                                <div class="form-group">
                                    <label for="step_order">Ordem</label>
                                    <input type="number" name="step_order" id="step_order" class="form-control" value="<?php echo count($steps) + 1; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="step_type">Tipo de step</label>
                                    <select name="step_type" id="step_type" class="form-control">
                                        <option value="mensagem_whatsapp">Mensagem WhatsApp</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="template_slug">Template LLM</label>
                                    <select name="template_slug" id="template_slug" class="form-control" required>
                                        <?php foreach ($templates as $slug => $label): ?>
                                            <option value="<?php echo htmlspecialchars($slug); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="delay_minutes">Delay após step anterior (minutos)</label>
                                    <input type="number" name="delay_minutes" id="delay_minutes" class="form-control" value="0" min="0">
                                </div>
                                <button type="submit" name="add_step" class="btn btn-primary">Adicionar step</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
