<?php
declare(strict_types=1);

/**
 * automation_rules.php
 *
 * Tela simples de CRUD para regras de automação.
 */

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/automation_core.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$pdo = $pdo ?? null;

$erro    = null;
$sucesso = null;

if (!automation_table_exists($pdo, 'automation_rules')) {
    $erro = 'Tabela automation_rules não encontrada. Crie as tabelas de automação conforme o README da V23.';
}

$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;

if ($erro === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check_post()) {
        $erro = 'Falha de validação CSRF.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $name        = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $eventKey    = trim((string) ($_POST['event_key'] ?? ''));
            $isActive    = isset($_POST['is_active']) ? 1 : 0;
            $conditions  = trim((string) ($_POST['conditions_json'] ?? ''));
            $actionType  = trim((string) ($_POST['action_type'] ?? 'log_only'));
            $actionJson  = trim((string) ($_POST['action_payload_json'] ?? ''));

            if ($name === '' || $eventKey === '') {
                $erro = 'Nome e event_key são obrigatórios.';
            } else {
                try {
                    if ($action === 'create') {
                        $stmt = (new \RedeAlabama\Repositories\Screens\AutomationRulesRepository($pdo))->prepare_1785();
                        $stmt->execute([
                            ':name'               => $name,
                            ':description'        => $description !== '' ? $description : null,
                            ':event_key'          => $eventKey,
                            ':is_active'          => $isActive,
                            ':conditions_json'    => $conditions !== '' ? $conditions : null,
                            ':action_type'        => $actionType !== '' ? $actionType : 'log_only',
                            ':action_payload_json'=> $actionJson !== '' ? $actionJson : null,
                        ]);
                        $sucesso = 'Regra criada com sucesso.';
                        if (function_exists('log_audit_event')) {
                            log_audit_event('automation_rule_create', 'automation_rule', (int)$pdo->lastInsertId(), [
                                'event_key' => $eventKey,
                            ]);
                        }
                    } else {
                        $ruleId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
                        if ($ruleId <= 0) {
                            $erro = 'ID de regra inválido.';
                        } else {
                            $stmt = (new \RedeAlabama\Repositories\Screens\AutomationRulesRepository($pdo))->prepare_3114();
                            $stmt->execute([
                                ':id'                 => $ruleId,
                                ':name'               => $name,
                                ':description'        => $description !== '' ? $description : null,
                                ':event_key'          => $eventKey,
                                ':is_active'          => $isActive,
                                ':conditions_json'    => $conditions !== '' ? $conditions : null,
                                ':action_type'        => $actionType !== '' ? $actionType : 'log_only',
                                ':action_payload_json'=> $actionJson !== '' ? $actionJson : null,
                            ]);
                            $sucesso = 'Regra atualizada com sucesso.';
                            if (function_exists('log_audit_event')) {
                                log_audit_event('automation_rule_update', 'automation_rule', $ruleId, [
                                    'event_key' => $eventKey,
                                ]);
                            }
                            $editId  = 0;
                        }
                    }
                } catch (Throwable $e) {
                    $erro = 'Erro ao salvar regra: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'toggle') {
            $ruleId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if ($ruleId <= 0) {
                $erro = 'ID de regra inválido.';
            } else {
                try {
                    $stmt = (new \RedeAlabama\Repositories\Screens\AutomationRulesRepository($pdo))->prepare_5021();
                    $stmt->execute([':id' => $ruleId]);
                    $sucesso = 'Status da regra atualizado.';
                    if (function_exists('log_audit_event')) {
                        log_audit_event('automation_rule_toggle', 'automation_rule', $ruleId, []);
                    }
                } catch (Throwable $e) {
                    $erro = 'Erro ao atualizar status da regra: ' . $e->getMessage();
                }
            }
        }
    }
}

// Carrega regra em edição, se houver
$editingRule = null;
if ($erro === null && $editId > 0) {
    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\AutomationRulesRepository($pdo))->prepare_5546();
        $stmt->execute([':id' => $editId]);
        $editingRule = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$editingRule) {
            $erro   = 'Regra não encontrada.';
            $editId = 0;
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar regra: ' . $e->getMessage();
    }
}

// Lista de regras
$rules = [];
if ($erro === null) {
    try {
        $rules = (new \RedeAlabama\Repositories\Screens\AutomationRulesRepository($pdo))->query_5352()->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar lista de regras: ' . $e->getMessage();
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
    <title>Regras de Automação - Rede Alabama</title>
</head>
<body class="al-body">
<div class="container mt-4">
    <h1>Regras de Automação</h1>
    <p class="text-muted">Engine simples de regras com base em eventos de automação.</p>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$erro): ?>
        <div class="card mb-4">
            <div class="card-header">
                <?php if ($editingRule): ?>
                    Editar Regra #<?php echo (int) $editingRule['id']; ?>
                <?php else: ?>
                    Nova Regra
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="post">
                    <?php echo csrf_hidden_input(); ?>
                    <input type="hidden" name="action" value="<?php echo $editingRule ? 'update' : 'create'; ?>">
                    <?php if ($editingRule): ?>
                        <input type="hidden" name="id" value="<?php echo (int) $editingRule['id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text"
                                   class="form-control"
                                   id="name"
                                   name="name"
                                   required
                                   value="<?php echo htmlspecialchars((string) ($editingRule['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="event_key" class="form-label">Event Key</label>
                            <input type="text"
                                   class="form-control"
                                   id="event_key"
                                   name="event_key"
                                   required
                                   placeholder="ex.: whatsapp.flow.completed"
                                   value="<?php echo htmlspecialchars((string) ($editingRule['event_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Descrição (opcional)</label>
                        <input type="text"
                               class="form-control"
                               id="description"
                               name="description"
                               value="<?php echo htmlspecialchars((string) ($editingRule['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox"
                               class="form-check-input"
                               id="is_active"
                               name="is_active"
                               <?php echo (!isset($editingRule['is_active']) || (int) $editingRule['is_active'] === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Regra ativa</label>
                    </div>

                    <div class="mb-3">
                        <label for="conditions_json" class="form-label">Condições (JSON)</label>
                        <textarea class="form-control"
                                  id="conditions_json"
                                  name="conditions_json"
                                  rows="4"
                                  placeholder='Ex.: [{"field":"event.payload.segmento","op":"equals","value":"D0_D7"}]'><?php
                            echo htmlspecialchars((string) ($editingRule['conditions_json'] ?? ''), ENT_QUOTES, 'UTF-8');
                        ?></textarea>
                        <small class="form-text text-muted">
                            Lista de objetos com campo, operador (equals, not_equals, contains, in, greater_than, less_than) e value.
                        </small>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="action_type" class="form-label">Tipo de Ação</label>
                            <select class="form-select" id="action_type" name="action_type">
                                <?php
                                $currentAction = (string) ($editingRule['action_type'] ?? 'log_only');
                                $options = [
                                    'log_only' => 'Apenas registrar em log (debug)',
                                    // Pontos de extensão:
                                    // 'start_flow_for_conversation' => 'Iniciar fluxo para conversa (extensão futura)',
                                ];
                                foreach ($options as $val => $label):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $currentAction === $val ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="action_payload_json" class="form-label">Payload da ação (JSON, opcional)</label>
                            <textarea class="form-control"
                                      id="action_payload_json"
                                      name="action_payload_json"
                                      rows="3"
                                      placeholder='{"exemplo":"valor"}'><?php
                                echo htmlspecialchars((string) ($editingRule['action_payload_json'] ?? ''), ENT_QUOTES, 'UTF-8');
                            ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?php echo $editingRule ? 'Salvar alterações' : 'Criar regra'; ?>
                    </button>
                    <?php if ($editingRule): ?>
                        <a href="automation_rules.php" class="btn btn-secondary ms-2">Cancelar edição</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                Regras cadastradas
            </div>
            <div class="card-body p-0">
                <?php if (!$rules): ?>
                    <div class="p-3 text-muted">Nenhuma regra cadastrada.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Event Key</th>
                                <th>Ativa</th>
                                <th>Criada em</th>
                                <th>Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rules as $r): ?>
                                <tr>
                                    <td><?php echo (int) $r['id']; ?></td>
                                    <td><?php echo htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><code><?php echo htmlspecialchars((string) $r['event_key'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                    <td><?php echo (int) $r['is_active'] === 1 ? 'Sim' : 'Não'; ?></td>
                                    <td><?php echo htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <a href="automation_rules.php?edit_id=<?php echo (int) $r['id']; ?>"
                                           class="btn btn-sm btn-outline-primary">Editar</a>

                                        <form method="post" class="d-inline">
                                            <?php echo csrf_hidden_input(); ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-secondary ms-2">
                                                <?php echo (int) $r['is_active'] === 1 ? 'Desativar' : 'Ativar'; ?>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
