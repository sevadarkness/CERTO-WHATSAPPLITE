<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



/**
 * flows_visual_builder.php
 *
 * Builder visual (drag & drop) para fluxos WhatsApp + IA.
 * Permite montar/reordenar steps de um fluxo sem precisar mexer em SQL.
 */

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

$pdo = $pdo ?? null;
if (!$pdo instanceof PDO) {
    throw new RuntimeException('PDO não inicializado em db_config.php');
}

// Resolve flow_id vindo de GET ou POST
$flowId = 0;
if (isset($_GET['flow_id'])) {
    $flowId = (int)$_GET['flow_id'];
} elseif (isset($_POST['flow_id'])) {
    $flowId = (int)$_POST['flow_id'];
}

if ($flowId <= 0) {
    $erro = 'ID de fluxo inválido.';
}

// Carrega dados básicos do fluxo
$flow = null;
if ($erro === null) {
    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\FlowsVisualBuilderRepository($pdo))->prepare_1039();
        $stmt->execute([':id' => $flowId]);
        $flow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$flow) {
            $erro = 'Fluxo não encontrado.';
        }
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar fluxo: ' . $e->getMessage();
    }
}

// Tratamento de POST (salvar definição do fluxo vinda do builder)
if ($erro === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_require();

        $jsonDef = trim((string)($_POST['flow_definition'] ?? ''));
        if ($jsonDef === '') {
            throw new RuntimeException('Definição do fluxo não enviada.');
        }

        $decoded = json_decode($jsonDef, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON de definição inválido.');
        }

        $stepsDef = $decoded['steps'] ?? null;
        if (!is_array($stepsDef)) {
            throw new RuntimeException('Estrutura de steps inválida.');
        }

        // Normaliza templates disponíveis
        $templates = $templates ?? [];
        if (!is_array($templates)) {
            $templates = [];
        }

        // Validação e normalização dos steps (gera também representação compatível para whatsapp_flow_steps)
        $normalizedSteps = [];
        $order = 1;

        // IDs únicas por fluxo
        $usedIds = [];
        foreach ($stepsDef as &$step) {
            if (!is_array($step)) {
                $step = null;
                continue;
            }
            if (empty($step['id'])) {
                $step['id'] = 'step_' . $order;
            }
            if (isset($usedIds[$step['id']])) {
                throw new RuntimeException('ID de step duplicado: ' . $step['id']);
            }
            $usedIds[$step['id']] = true;
            $order++;
        }
        unset($step);

        // Reinicia contador visual
        $order = 1;
        foreach ($stepsDef as $step) {
            if (!is_array($step)) {
                continue;
            }

            $tplSlug = trim((string)($step['template_slug'] ?? ''));
            if ($tplSlug === '' || !array_key_exists($tplSlug, $templates)) {
                // ignora steps com template inválido
                continue;
            }

            $type = trim((string)($step['type'] ?? 'mensagem'));
            $stepType = $type === 'condicional' ? 'condicional' : 'mensagem_whatsapp';

            $delay = isset($step['delay_minutes']) ? (int)$step['delay_minutes'] : 0;
            if ($delay < 0) {
                $delay = 0;
            }

            $normalizedSteps[] = [
                'order'         => $order,
                'step_type'     => $stepType,
                'template_slug' => $tplSlug,
                'delay_minutes' => $delay,
            ];
            $order++;
        }

        // Persiste a definição JSON completa na própria tabela de fluxos (campo definition_json)
        $defJson = json_encode(['steps' => $stepsDef], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($defJson !== false) {
            try {
                $sqlFlow = 'UPDATE whatsapp_flows SET definition_json = :def WHERE id = :id';
                $stmtFlow = $pdo->prepare($sqlFlow);
                $stmtFlow->execute([
                    ':def' => $defJson,
                    ':id'  => $flowId,
                ]);
            } catch (Throwable $e) {
                // Se a coluna não existir, apenas ignora; compatibilidade com esquemas antigos.
            }
        }

        if (!$normalizedSteps) {
            throw new RuntimeException('Nenhum step válido encontrado na definição enviada.');
        }

        // Persiste definição de forma transacional
        $pdo->beginTransaction();

        $del = (new \RedeAlabama\Repositories\Screens\FlowsVisualBuilderRepository($pdo))->prepare_3518();
        $del->execute([':fid' => $flowId]);

        $ins = (new \RedeAlabama\Repositories\Screens\FlowsVisualBuilderRepository($pdo))->prepare_3649();

        foreach ($normalizedSteps as $st) {
            $ins->execute([
                ':fid'   => $flowId,
                ':ord'   => $st['order'],
                ':type'  => $st['step_type'],
                ':tpl'   => $st['template_slug'],
                ':delay' => $st['delay_minutes'],
            ]);
        }

        $pdo->commit();

        // Normaliza identidades de sessão para log (padronização V14):
        // login do painel usa "usuario_id" e "nome_usuario".
        $sessionUserId   = $_SESSION['usuario_id'] ?? null;
        $sessionUserName = $_SESSION['nome_usuario'] ?? null;

        log_app_event('flows_visual_builder', 'save_flow', [
            'flow_id'     => $flowId,
            'steps_count' => count($normalizedSteps),

            // Campos padronizados
            'usuario_id'   => $sessionUserId,
            'usuario_nome' => $sessionUserName,

            // Compat legado (caso algum painel/alerta antigo consuma estes nomes)
            'user_id'      => $sessionUserId,
            'user_login'   => $sessionUserName,
        ]);

        $sucesso = 'Fluxo salvo com sucesso via builder visual (drag & drop).';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = 'Erro ao salvar fluxo pelo builder visual: ' . $e->getMessage();

        log_app_event('flows_visual_builder', 'error', [
            'flow_id' => $flowId,
            'error'   => $e->getMessage(),
        ]);
    }
}

// Recarrega steps atuais do fluxo para desenhar o canvas
$steps = [];
$templates = $templates ?? [];
if ($erro === null) {
    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\FlowsVisualBuilderRepository($pdo))->prepare_5064();
        $stmt->execute([':fid' => $flowId]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $erro = 'Erro ao carregar steps do fluxo: ' . $e->getMessage();
    }
}

// Prepara estrutura simplificada para o JS
$jsSteps = [];
foreach ($steps as $s) {
    $slug = (string)($s['template_slug'] ?? '');
    $tpl  = $templates[$slug] ?? null;
    $jsSteps[] = [
        'id'             => (int)($s['id'] ?? 0),
        'step_type'      => (string)($s['step_type'] ?? 'mensagem_whatsapp'),
        'template_slug'  => $slug,
        'template_title' => $tpl['title'] ?? $slug,
        'template_desc'  => $tpl['description'] ?? '',
        'delay_minutes'  => (int)($s['delay_minutes'] ?? 0),
    ];
}

$jsTemplates = [];
foreach ($templates as $slug => $tpl) {
    if (!is_array($tpl)) {
        continue;
    }
    $jsTemplates[] = [
        'slug'        => (string)$slug,
        'title'       => (string)($tpl['title'] ?? $slug),
        'description' => (string)($tpl['description'] ?? ''),
    ];
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Builder Visual de Fluxos (WhatsApp + IA)</title>
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet"
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
          integrity="sha384-MlwrP8gkNvztNFVQVw1Gc7jqCOUMIqFZRMVAbwY/jGj33jjXNpMOG66Q6VbU8v8T"
          crossorigin="anonymous">
    <style>
        body {
            background-color: #050816;
            color: #e5e7eb;
        }
        .builder-container {
            margin-top: 1.5rem;
            margin-bottom: 2rem;
        }
        .flow-canvas {
            background: radial-gradient(circle at top left, #1f2937, #020617);
            border-radius: .75rem;
            border: 1px solid #111827;
            padding: 1rem;
            min-height: 320px;
            max-height: 520px;
            overflow-y: auto;
        }
        .flow-step-card {
            border-radius: .5rem;
            border: 1px dashed #4b5563;
            padding: .75rem .9rem;
            margin-bottom: .75rem;
            background: rgba(17, 24, 39, 0.92);
            cursor: grab;
            transition: background .12s ease, box-shadow .12s ease, transform .08s ease;
        }
        .flow-step-card:hover {
            background: rgba(31, 41, 55, 0.98);
            box-shadow: 0 0 0 1px #4b5563;
        }
        .flow-step-card.dragging {
            opacity: .75;
            transform: scale(1.01);
            box-shadow: 0 0 0 2px #10b981;
        }
        .flow-step-index {
            font-size: .8rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .flow-step-title {
            font-weight: 600;
            color: #f9fafb;
        }
        .flow-step-meta {
            font-size: .78rem;
            color: #9ca3af;
        }
        .flow-step-actions button {
            padding: .15rem .4rem;
            font-size: .75rem;
        }
        .template-list {
            max-height: 260px;
            overflow-y: auto;
            border-radius: .5rem;
            border: 1px solid #111827;
            background: rgba(15, 23, 42, 0.9);
        }
        .template-item {
            padding: .5rem .65rem;
            border-bottom: 1px solid #111827;
            cursor: pointer;
        }
        .template-item:last-child {
            border-bottom: none;
        }
        .template-item:hover {
            background: rgba(31, 41, 55, 0.95);
        }
        .template-title {
            font-size: .85rem;
            font-weight: 500;
        }
        .template-desc {
            font-size: .75rem;
            color: #9ca3af;
        }
        .json-preview {
            background: #020617;
            border-radius: .5rem;
            border: 1px solid #111827;
            font-size: .75rem;
            max-height: 240px;
            overflow: auto;
            padding: .5rem .75rem;
        }
        code, pre {
            color: #e5e7eb;
        }
        .badge-step-type {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: .15rem .4rem;
        }
        .badge-step-type.whatsapp {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.5);
            color: #6ee7b7;
        }
    </style>
</head>
<body class="bg-dark text-light">
<div class="container builder-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Builder visual de fluxos (WhatsApp + IA)</h1>
            <?php if ($flow): ?>
                <div class="small text-muted">
                    Campanha #<?php echo (int)$flow['id']; ?> ·
                    <?php echo htmlspecialchars($flow['name'] ?? 'sem nome'); ?>
                    <?php if (!empty($flow['status'])): ?>
                        · <span class="badge badge-info"><?php echo htmlspecialchars($flow['status']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($flow['target_segment'])): ?>
                        · <span class="badge badge-secondary">Segmento: <?php echo htmlspecialchars($flow['target_segment']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <a href="flows_manager.php" class="btn btn-sm btn-outline-light">
                &larr; Voltar para Gerenciador de Fluxos
            </a>
        </div>
    </div>

    <?php if ($erro !== null): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>
    <?php if ($sucesso !== null): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($sucesso); ?></div>
    <?php endif; ?>

    <?php if ($flow && $erro === null): ?>
        <div class="card bg-dark border-secondary mb-3">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap justify-content-between align-items-center small">
                    <div>
                        <span class="text-muted text-uppercase" style="letter-spacing: .08em;">Resumo da campanha</span>
                    </div>
                    <div>
                        <span class="mr-3">
                            Steps:
                            <strong id="campaign-steps-count">0</strong>
                        </span>
                        <span class="mr-3">
                            Delay total:
                            <strong id="campaign-delay-total">0 min</strong>
                        </span>
                        <span class="text-muted">
                            Segmento alvo:
                            <strong><?php echo htmlspecialchars($flow['target_segment'] ?? '—'); ?></strong>
                        </span>
                    </div>
                </div>
            </div>
        </div>

    <?php if ($flow && $erro === null): ?>
        <form method="post" id="flow-builder-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="flow_id" value="<?php echo (int)$flowId; ?>">
            <input type="hidden" name="flow_definition" id="flow-definition-input" value="">

            <div class="row">
                <div class="col-md-7 mb-3">
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="flow-step-index text-uppercase">Canvas do fluxo</div>
                            <small class="text-muted">
                                Arraste os cards para definir a ordem dos passos. Use os templates ao lado para adicionar novos steps.
                            </small>
                        </div>
                        <div>
                            <button type="button" id="btn-clear-steps" class="btn btn-sm btn-outline-warning">
                                Limpar canvas
                            </button>
                        </div>
                    </div>
                    <div id="flow-steps-container" class="flow-canvas">
                        <!-- steps rendidos via JS -->
                    </div>
                </div>

                <div class="col-md-5 mb-3">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="flow-step-index">Templates disponíveis</span>
                            <small class="text-muted">Clique para adicionar ao final do fluxo.</small>
                        </div>
                        <div class="template-list" id="template-list">
                            <!-- templates rendidos via JS -->
                        </div>
                        <div class="mt-2 form-inline">
                            <label for="default-delay-input" class="mr-2 small text-muted">
                                Delay padrão (min) ao adicionar:
                            </label>
                            <input type="number" class="form-control form-control-sm"
                                   id="default-delay-input" value="0" min="0" step="1" style="width: 110px;">
                        </div>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="flow-step-index">Preview JSON gerado</span>
                            <small class="text-muted">Somente leitura · útil para debugging.</small>
                        </div>
                        <pre class="json-preview" id="json-preview">{}</pre>
                    </div>
                </div>
            </div>

            <div class="mt-3 d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    Salvando pelo builder visual, os steps atuais do fluxo serão sobrescritos
                    na tabela <code>whatsapp_flow_steps</code>.
                </div>
                <div>
                    <button type="submit" class="btn btn-success">
                        Salvar fluxo (drag &amp; drop)
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script <?php echo alabama_csp_nonce_attr(); ?>>
(function() {
    'use strict';

    var initialConfig = {
        flowId: <?php echo (int)$flowId; ?>,
        steps: <?php echo json_encode($jsSteps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        templates: <?php echo json_encode($jsTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    };

    var state = {
        steps: (initialConfig.steps || []).map(function(s, idx) {
            // Normaliza estrutura interna para novo modelo (com id/type/next/condição)
            var id = s.id || ('step_' + (idx + 1));
            var type = s.type || (s.step_type === 'mensagem_whatsapp' ? 'mensagem' : (s.step_type || 'mensagem'));
            return {
                id: id,
                type: type,
                step_type: s.step_type || 'mensagem_whatsapp',
                template_slug: s.template_slug,
                template_title: s.template_title || s.template_slug,
                template_desc: s.template_desc || '',
                delay_minutes: s.delay_minutes || 0,
                next: s.next || null,
                condition: s.condition || '',
                next_if: s.next_if || null,
                next_else: s.next_else || null
            };
        }),
        templates: initialConfig.templates || [],
        draggingIndex: null
    };

    function escapeHtml(str) {
        if (typeof str !== 'string') {
            return '';
        }
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderTemplates() {
        var list = document.getElementById('template-list');
        if (!list) return;

        list.innerHTML = '';
        if (!state.templates.length) {
            list.innerHTML = '<div class="template-item text-muted small">Nenhum template LLM configurado.</div>';
            return;
        }

        state.templates.forEach(function(tpl, idx) {
            var el = document.createElement('div');
            el.className = 'template-item';
            el.dataset.slug = tpl.slug;
            el.innerHTML =
                '<div class="template-title">' + escapeHtml(tpl.title) + '</div>' +
                '<div class="template-desc">' + escapeHtml(tpl.description || tpl.slug) + '</div>';
            el.addEventListener('click', function() {
                var delayField = document.getElementById('default-delay-input');
                var delayVal = 0;
                if (delayField && delayField.value !== '') {
                    delayVal = parseInt(delayField.value, 10);
                    if (isNaN(delayVal) || delayVal < 0) {
                        delayVal = 0;
                    }
                }
                addStepFromTemplate(tpl, delayVal);
            });
            list.appendChild(el);
        });
    }

    function addStepFromTemplate(tpl, delayMinutes) {
        // Mensagem padrão (tipo mensagem)
        var nextId = null;
        if (state.steps.length) {
            // Por padrão, encadeia após o último step
            nextId = state.steps[state.steps.length - 1].id || null;
        }

        state.steps.push({
            id: 'step_' + (state.steps.length + 1),
            type: 'mensagem',
            step_type: 'mensagem_whatsapp',
            template_slug: tpl.slug,
            template_title: tpl.title,
            template_desc: tpl.description || '',
            delay_minutes: typeof delayMinutes === 'number' ? delayMinutes : 0,
            next: nextId || null,
            condition: '',
            next_if: null,
            next_else: null
        });
        renderSteps();
    }

    function renderSteps() {
        var container = document.getElementById('flow-steps-container');
        if (!container) return;

        container.innerHTML = '';

        if (!state.steps.length) {
            var empty = document.createElement('div');
            empty.className = 'text-center text-muted small';
            empty.textContent = 'Nenhum step no fluxo. Use os templates ao lado para adicionar.';
            container.appendChild(empty);
            syncJsonPreview();
            return;
        }

        state.steps.forEach(function(step, index) {
            var card = document.createElement('div');
            card.className = 'flow-step-card';
            card.setAttribute('draggable', 'true');
            card.dataset.index = String(index);

            var typeLabel = 'WhatsApp';
            var typeBadgeClass = 'badge-step-type whatsapp';

            card.innerHTML =
                '<div class="d-flex justify-content-between align-items-center mb-1">' +
                    '<div>' +
                        '<div class="flow-step-index">Step ' + (index + 1) + '</div>' +
                        '<div class="flow-step-title">' + escapeHtml(step.template_title || step.template_slug) + '</div>' +
                    '</div>' +
                    '<div class="flow-step-actions">' +
                        '<select class="custom-select custom-select-sm step-type-select" data-index="' + index + '">' +
                            '<option value="mensagem"' +
                                (step.type === 'mensagem' ? ' selected' : '') +
                            '>Mensagem</option>' +
                            '<option value="condicional"' +
                                (step.type === 'condicional' ? ' selected' : '') +
                            '>Condicional</option>' +
                        '</select> ' +
                        '<button type="button" class="btn btn-outline-danger btn-sm btn-remove-step" data-index="' + index + '">&times;</button>' +
                    '</div>' +
                '</div>' +
                '<div class="flow-step-meta">' +
                    (step.template_desc ? escapeHtml(step.template_desc) : '') +
                '</div>' +
                '<div class="mt-1 form-inline">' +
                    '<label class="mr-2 small text-muted">Delay (min):</label>' +
                    '<input type="number" class="form-control form-control-sm flow-delay-input" ' +
                           'data-index="' + index + '" value="' + (step.delay_minutes || 0) + '" min="0" step="1" style="width: 110px;">' +
                '</div>' +
                (step.type === 'condicional'
                    ? ('<div class="mt-2 small">' +
                           '<div class="mb-1">' +
                               '<label class="small text-muted mb-1">Condição (expressão segura em JS, usando ctx.*)</label>' +
                               '<input type="text" class="form-control form-control-sm step-condition-input" ' +
                                      'data-index="' + index + '" value="' + escapeHtml(step.condition || '') + '" ' +
                                      'placeholder="ex.: ctx.segment === \'vip\'">' +
                           '</div>' +
                           '<div class="d-flex flex-wrap align-items-center mb-1">' +
                               '<span class="badge badge-success mr-2">IF verdadeiro →</span>' +
                               '<input type="text" class="form-control form-control-sm step-next-if-input mr-2" ' +
                                      'style="width: 120px;" placeholder="step_id" data-index="' + index + '" ' +
                                      'value="' + (step.next_if || '') + '">' +
                               '<span class="text-muted small">ID do próximo step (true)</span>' +
                           '</div>' +
                           '<div class="d-flex flex-wrap align-items-center">' +
                               '<span class="badge badge-danger mr-2">ELSE →</span>' +
                               '<input type="text" class="form-control form-control-sm step-next-else-input mr-2" ' +
                                      'style="width: 120px;" placeholder="step_id" data-index="' + index + '" ' +
                                      'value="' + (step.next_else || '') + '">' +
                               '<span class="text-muted small">ID do próximo step (false)</span>' +
                           '</div>' +
                       '</div>')
                    : ('<div class="mt-2 small d-flex align-items-center">' +
                           '<span class="badge badge-pill badge-primary mr-2">Next</span>' +
                           '<input type="text" class="form-control form-control-sm step-next-input" ' +
                                  'style="width: 140px;" placeholder="step_id" data-index="' + index + '" ' +
                                  'value="' + (step.next || '') + '">' +
                           '<span class="text-muted small ml-2">ID do próximo step</span>' +
                       '</div>')
                );


            card.addEventListener('dragstart', handleDragStart);
            card.addEventListener('dragover', handleDragOver);
            card.addEventListener('drop', handleDrop);
            card.addEventListener('dragend', handleDragEnd);

            container.appendChild(card);
        });

        container.addEventListener('dragover', function(e) {
            e.preventDefault();
        });

        container.addEventListener('drop', function(e) {
            e.preventDefault();
        });

        container.querySelectorAll('.btn-remove-step').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var idx = parseInt(btn.getAttribute('data-index'), 10);
                if (!isNaN(idx)) {
                    state.steps.splice(idx, 1);
                    renderSteps();
                }
            });
        });

        container.querySelectorAll('.flow-delay-input').forEach(function(inp) {
            inp.addEventListener('change', function() {
                var idx = parseInt(inp.getAttribute('data-index'), 10);
                if (isNaN(idx) || !state.steps[idx]) return;
                var v = parseInt(inp.value, 10);
                if (isNaN(v) || v < 0) {
                    v = 0;
                    inp.value = String(v);
                }
                state.steps[idx].delay_minutes = v;
                syncJsonPreview();
            });
        });

        container.querySelectorAll('.step-type-select').forEach(function(sel) {
            sel.addEventListener('change', function() {
                var idx = parseInt(sel.getAttribute('data-index'), 10);
                if (isNaN(idx) || !state.steps[idx]) return;
                var val = sel.value === 'condicional' ? 'condicional' : 'mensagem';
                state.steps[idx].type = val;
                renderSteps(); // re-render para atualizar o card
            });
        });

        container.querySelectorAll('.step-condition-input').forEach(function(inp) {
            inp.addEventListener('change', function() {
                var idx = parseInt(inp.getAttribute('data-index'), 10);
                if (isNaN(idx) || !state.steps[idx]) return;
                state.steps[idx].condition = inp.value || '';
                syncJsonPreview();
            });
        });

        container.querySelectorAll('.step-next-input').forEach(function(inp) {
            inp.addEventListener('change', function() {
                var idx = parseInt(inp.getAttribute('data-index'), 10);
                if (isNaN(idx) || !state.steps[idx]) return;
                state.steps[idx].next = inp.value || null;
                syncJsonPreview();
            });
        });

        container.querySelectorAll('.step-next-if-input').forEach(function(inp) {
            inp.addEventListener('change', function() {
                var idx = parseInt(inp.getAttribute('data-index'), 10);
                if (isNaN(idx) || !state.steps[idx]) return;
                state.steps[idx].next_if = inp.value || null;
                syncJsonPreview();
            });
        });

        container.querySelectorAll('.step-next-else-input').forEach(function(inp) {
            inp.addEventListener('change', function() {
                var idx = parseInt(inp.getAttribute('data-index'), 10);
                if (isNaN(idx) || !state.steps[idx]) return;
                state.steps[idx].next_else = inp.value || null;
                syncJsonPreview();
            });
        });

        syncJsonPreview();
    }

    function handleDragStart(e) {
        var idx = parseInt(this.dataset.index, 10);
        if (isNaN(idx)) return;
        state.draggingIndex = idx;
        this.classList.add('dragging');
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            try {
                e.dataTransfer.setData('text/plain', String(idx));
            } catch (err) {
                // ignore
            }
        }
    }

    function handleDragOver(e) {
        e.preventDefault();
        if (!this.classList.contains('flow-step-card')) return;
        this.classList.add('drag-over');
    }

    function handleDrop(e) {
        e.preventDefault();
        var fromIdx = state.draggingIndex;
        var toIdx = parseInt(this.dataset.index, 10);
        if (isNaN(fromIdx) || isNaN(toIdx) || fromIdx === toIdx) {
            return;
        }
        var moving = state.steps.splice(fromIdx, 1)[0];
        state.steps.splice(toIdx, 0, moving);
        state.draggingIndex = null;
        renderSteps();
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');
        var cards = document.querySelectorAll('.flow-step-card.drag-over');
        cards.forEach(function(c) { c.classList.remove('drag-over'); });
    }

    function syncJsonPreview() {
        // Atualiza resumo da campanha + gera JSON estruturado baseado em IDs e rotas
        var totalSteps = state.steps.length;
        var totalDelay = 0;
        state.steps.forEach(function(step) {
            var d = parseInt(step.delay_minutes || 0, 10);
            if (!isNaN(d) && d > 0) {
                totalDelay += d;
            }
        });
        var stepsEl = document.getElementById('campaign-steps-count');
        if (stepsEl) {
            stepsEl.textContent = String(totalSteps);
        }
        var delayEl = document.getElementById('campaign-delay-total');
        if (delayEl) {
            delayEl.textContent = String(totalDelay) + ' min';
        }

        var payload = {
            steps: state.steps.map(function(step, idx) {
                var base = {
                    id: step.id || ('step_' + (idx + 1)),
                    type: step.type || 'mensagem',
                    template_slug: step.template_slug || null,
                    delay_minutes: step.delay_minutes || 0
                };
                if (base.type === 'mensagem') {
                    base.next = step.next || null;
                } else if (base.type === 'condicional') {
                    base.condition = step.condition || '';
                    base.next_if = step.next_if || null;
                    base.next_else = step.next_else || null;
                }
                return base;
            })
        };

        var hidden = document.getElementById('flow-definition-input');
        if (hidden) {
            hidden.value = JSON.stringify(payload);
        }

        var preview = document.getElementById('json-preview');
        if (preview) {
            try {
                preview.textContent = JSON.stringify(payload, null, 2);
            } catch (e) {
                preview.textContent = '{}';
            }
        }
    }

    function bindFormSubmit() {
        var form = document.getElementById('flow-builder-form');
        if (!form) return;
        form.addEventListener('submit', function() {
            syncJsonPreview();
        });
    }

    function bindClearCanvas() {
        var btn = document.getElementById('btn-clear-steps');
        if (!btn) return;
        btn.addEventListener('click', function() {
            if (!state.steps.length) return;
            if (!window.confirm('Remover todos os steps do canvas? Você precisará adicionar novamente antes de salvar.')) {
                return;
            }
            state.steps = [];
            renderSteps();
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        renderTemplates();
        renderSteps();
        bindFormSubmit();
        bindClearCanvas();
    });
})();
</script>
</body>
</html>
