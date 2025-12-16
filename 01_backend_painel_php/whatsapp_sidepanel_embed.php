<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/whatsapp_contacts_utils.php';
require_once __DIR__ . '/csrf.php';

// X-Frame-Options é controlado pelo session_bootstrap.php (allowlist para embed)

$csrfToken = csrf_token();

$conversaId = (int)($_GET['conversa_id'] ?? 0);
$phoneParam = trim((string)($_GET['phone'] ?? ''));

$erro       = null;
$conversa   = null;
$mensagens  = [];

function alabama_sidepanel_load_conversa_by_id(PDO $pdo, int $conversaId): array
{
    $sql = "SELECT c.id,
                   c.telefone_cliente,
                   c.status,
                   c.created_at,
                   c.updated_at,
                   c.ultima_mensagem_em
              FROM whatsapp_conversas c
             WHERE c.id = :id
             LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $conversaId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$conv) {
        return [null, []];
    }

    $sqlMsg = "SELECT m.id,
                      m.conteudo,
                      m.direction,
                      m.created_at
                 FROM whatsapp_mensagens m
                WHERE m.conversa_id = :id
                ORDER BY m.id DESC
                LIMIT 20";
    $stmtMsg = $pdo->prepare($sqlMsg);
    $stmtMsg->execute([':id' => $conversaId]);
    $msgs = $stmtMsg->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [$conv, $msgs];
}

function alabama_sidepanel_find_conversa_by_phone(PDO $pdo, string $phone): array
{
    $digits = wa_only_digits($phone);
    if ($digits === '') {
        return [null, []];
    }

    $needle = substr($digits, -8);
    $like = '%' . $needle . '%';

    $sql = "SELECT c.id,
                   c.telefone_cliente,
                   c.status,
                   c.created_at,
                   c.updated_at,
                   c.ultima_mensagem_em
              FROM whatsapp_conversas c
             WHERE c.telefone_cliente LIKE :tel
             ORDER BY c.id DESC
             LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':tel' => $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (empty($rows)) {
        return [null, []];
    }

    $best = null;
    $digitsNormalized = $digits;
    foreach ($rows as $row) {
        $rowDigits = wa_only_digits((string)($row['telefone_cliente'] ?? ''));
        if ($rowDigits !== '' && $rowDigits === $digitsNormalized) {
            $best = $row;
            break;
        }
    }

    if (!$best) {
        $best = $rows[0];
    }

    $convId = (int)$best['id'];

    $sqlMsg = "SELECT m.id,
                      m.conteudo,
                      m.direction,
                      m.created_at
                 FROM whatsapp_mensagens m
                WHERE m.conversa_id = :id
                ORDER BY m.id DESC
                LIMIT 20";
    $stmtMsg = $pdo->prepare($sqlMsg);
    $stmtMsg->execute([':id' => $convId]);
    $msgs = $stmtMsg->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [$best, $msgs];
}

try {
    if ($conversaId > 0) {
        [$conversa, $mensagens] = alabama_sidepanel_load_conversa_by_id($pdo, $conversaId);
        if (!$conversa) {
            $erro = 'Nenhuma conversa encontrada para o ID informado.';
            log_app_event('whatsapp_sidepanel', 'not_found_by_id', [
                'conversa_id' => $conversaId,
            ]);
        } else {
            log_app_event('whatsapp_sidepanel', 'resolved_by_id', [
                'conversa_id' => $conversaId,
                'telefone'    => $conversa['telefone_cliente'] ?? null,
            ]);
        }
    } elseif ($phoneParam !== '') {
        [$conversa, $mensagens] = alabama_sidepanel_find_conversa_by_phone($pdo, $phoneParam);
        if (!$conversa) {
            $erro = 'Nenhuma conversa encontrada para o telefone informado.';
            log_app_event('whatsapp_sidepanel', 'not_found_by_phone', [
                'phone_param' => $phoneParam,
            ]);
        } else {
            log_app_event('whatsapp_sidepanel', 'resolved_by_phone', [
                'phone_param' => $phoneParam,
                'conversa_id' => $conversa['id'] ?? null,
                'telefone'    => $conversa['telefone_cliente'] ?? null,
            ]);
        }
    } else {
        $erro = 'Conversa não informada. Abra o WhatsApp Web a partir do painel Alabama ou forneça um telefone válido.';
    }
} catch (Throwable $e) {
    $erro = 'Erro ao carregar dados da conversa.';
    log_app_event('whatsapp_sidepanel', 'erro_carregar', [
        'conversa_id' => $conversaId,
        'phone'       => $phoneParam,
        'erro'        => $e->getMessage(),
    ]);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Rede Alabama – Painel WhatsApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        html, body {
            height: 100%;
        }
        body {
            background: #000;
            color: #f8f9fa;
            font-size: 13px;
            padding: 8px;
            overflow-y: auto;
        }
        .nav-tabs {
            border-bottom: 1px solid rgba(148, 163, 184, 0.3);
        }
        .nav-tabs .nav-link {
            border-radius: 0;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 6px 8px;
            font-size: 12px;
            color: #9ca3af;
            cursor: pointer;
            background: transparent;
        }
        .nav-tabs .nav-link.active {
            color: #e5e7eb;
            border-color: #22c55e;
        }
        .tab-pane {
            padding-top: 6px;
        }
        .msg-bubble {
            border-radius: 8px;
            padding: 6px 8px;
            margin-bottom: 4px;
        }
        .msg-in {
            background: #1f2933;
        }
        .msg-out {
            background: #0b8457;
        }
        .msg-ts {
            font-size: 10px;
            opacity: .7;
        }
        .scroll-area {
            max-height: 230px;
            overflow-y: auto;
            margin-bottom: 8px;
        }
        textarea {
            resize: vertical;
            min-height: 70px;
        }
        iframe.embed-panel {
            width: 100%;
            border: 0;
            background: #000;
        }
    </style>
</head>
<body>
<ul class="nav nav-tabs nav-fill" id="alabamaSidepanelTabs">
    <li class="nav-item">
        <button class="nav-link active" data-tab="tab-conversa">Conversa</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-tab="tab-ferramentas">Ferramentas WhatsApp</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-tab="tab-marketing">IA Marketing</button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane" id="tab-conversa">
        <?php if ($erro): ?>
            <div class="alert alert-warning p-2 mb-0">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php elseif (!$conversa): ?>
            <div class="alert alert-info p-2 mb-0">
                Nenhuma conversa encontrada.
            </div>
        <?php else: ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <strong>Contato:</strong>
                    <span><?php echo htmlspecialchars($conversa['telefone_cliente'] ?? ''); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>Status:</span>
                    <span class="badge bg-secondary">
                        <?php echo htmlspecialchars($conversa['status'] ?? ''); ?>
                    </span>
                </div>
                <div class="small text-muted">
                    Atualizado em: <?php echo htmlspecialchars($conversa['updated_at'] ?? ''); ?>
                </div>
            </div>

            <hr class="my-2">

            <div class="scroll-area">
                <?php if (empty($mensagens)): ?>
                    <p class="text-muted mb-0">Ainda não há histórico nesta conversa.</p>
                <?php else: ?>
                    <?php foreach (array_reverse($mensagens) as $m): ?>
                        <?php
                        $isOut = ($m['direction'] ?? '') === 'out';
                        $cls   = $isOut ? 'msg-bubble msg-out' : 'msg-bubble msg-in';
                        ?>
                        <div class="<?php echo $cls; ?>">
                            <div><?php echo nl2br(htmlspecialchars($m['conteudo'] ?? '')); ?></div>
                            <div class="msg-ts">
                                <?php echo htmlspecialchars($m['created_at'] ?? ''); ?>
                                <?php echo $isOut ? ' · Você' : ' · Cliente'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <hr class="my-2">

            <form id="alabamaSidepanelForm">
                <input type="hidden" name="conversa_id" id="conversaId"
                       value="<?php echo (int)$conversa['id']; ?>">

                <div class="mb-1">
                    <label class="form-label mb-1">Contexto adicional / mensagem</label>
                    <textarea class="form-control form-control-sm"
                              name="mensagem"
                              id="mensagemCtx"
                              placeholder="Digite detalhes ou uma resposta para enviar ao cliente"></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="button"
                            class="btn btn-sm btn-warning"
                            id="btnIa">
                        Sugestão IA
                    </button>

                    <button type="button"
                            class="btn btn-sm btn-success"
                            id="btnEnviar">
                        Enviar via API
                    </button>

                    <a href="whatsapp_bot_console.php"
                       target="_blank"
                       class="btn btn-sm btn-outline-light">
                        Abrir painel completo
                    </a>
                </div>

                <div id="sidepanelFeedback" class="small mt-1"></div>
            </form>
        <?php endif; ?>
    </div>

    <div class="tab-pane" id="tab-ferramentas" style="display:none;">
        <div class="mb-2 small text-muted">
            Ferramentas compactas do módulo WhatsApp (envio em massa, status de campanhas, etc.).
        </div>

        <div class="mb-2">
            <div class="small mb-1">Envio em massa de mensagens</div>
            <iframe class="embed-panel"
                    src="whatsapp_contacts_bulk_send.php?embed=1"
                    style="height:210px;"></iframe>
        </div>

        <div class="mb-2">
            <div class="small mb-1">Status das campanhas em massa</div>
            <iframe class="embed-panel"
                    src="whatsapp_contacts_bulk_status.php?embed=1"
                    style="height:210px;"></iframe>
        </div>

        <div class="mb-2">
            <div class="small mb-1">Exportação de contatos</div>
            <a href="whatsapp_contacts_export.php"
               target="_blank"
               class="btn btn-sm btn-outline-light">
                Exportar contatos (CSV)
            </a>
        </div>
    </div>

    <div class="tab-pane" id="tab-marketing" style="display:none;">
        <div class="mb-2 small text-muted">
            Console de estratégia de marketing com IA, integrado à infraestrutura do Alabama.
        </div>
        <iframe class="embed-panel"
                src="marketing_strategy_panel.php?embed=1"
                style="height:360px;"></iframe>
    </div>
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('#alabamaSidepanelTabs .nav-link');
    var panes = {
        'tab-conversa': document.getElementById('tab-conversa'),
        'tab-ferramentas': document.getElementById('tab-ferramentas'),
        'tab-marketing': document.getElementById('tab-marketing')
    };

    function activate(tabId) {
        Object.keys(panes).forEach(function (id) {
            if (!panes[id]) return;
            panes[id].style.display = (id === tabId) ? 'block' : 'none';
        });
        tabs.forEach(function (btn) {
            if (btn.getAttribute('data-tab') === tabId) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.getAttribute('data-tab');
            activate(target);
        });
    });

    activate('tab-conversa');
})();

(function () {
    var form      = document.getElementById('alabamaSidepanelForm');
    if (!form) return;

    var inputCid  = document.getElementById('conversaId');
    var textarea  = document.getElementById('mensagemCtx');
    var btnIa     = document.getElementById('btnIa');
    var btnEnviar = document.getElementById('btnEnviar');
    var feedback  = document.getElementById('sidepanelFeedback');
    var csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    function setFeedback(msg, isError) {
        if (!feedback) return;
        feedback.textContent = msg || '';
        feedback.className = 'small mt-1 ' + (isError ? 'text-danger' : 'text-info');
    }

    if (btnIa) {
        btnIa.addEventListener('click', function () {
            var cid = inputCid ? inputCid.value : '';
            if (!cid) {
                setFeedback('Conversa inválida para IA.', true);
                return;
            }
            var msgCtx = textarea ? textarea.value.trim() : '';
            var payloadAi = {
                conversa_id: String(cid),
                mensagem: msgCtx,
                _csrf_token: csrfToken
            };

            setFeedback('Gerando sugestão com IA...', false);
            btnIa.disabled = true;

            fetch('whatsapp_ai_suggestions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payloadAi)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                btnIa.disabled = false;
                if (!data || !data.ok) {
                    setFeedback(data && data.error ? data.error : 'Falha ao gerar sugestão IA.', true);
                    return;
                }
                if (textarea) {
                    textarea.value = data.resposta || '';
                }
                setFeedback('Sugestão gerada. Revise e clique em Enviar.', false);
            })
            .catch(function () {
                btnIa.disabled = false;
                setFeedback('Erro inesperado ao chamar IA.', true);
            });
        });
    }

    if (btnEnviar) {
        btnEnviar.addEventListener('click', function () {
            var cid = inputCid ? inputCid.value : '';
            var msg = textarea ? textarea.value.trim() : '';

            if (!cid) {
                setFeedback('Conversa inválida.', true);
                return;
            }
            if (!msg) {
                setFeedback('Mensagem vazia.', true);
                return;
            }

            var payload = {
                conversa_id: String(cid),
                mensagem: msg,
                _csrf_token: csrfToken
            };

            setFeedback('Enviando mensagem...', false);
            btnEnviar.disabled = true;

            fetch('whatsapp_manual_send.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                btnEnviar.disabled = false;
                if (!data || !data.ok) {
                    setFeedback(data && data.error ? data.error : 'Falha ao enviar mensagem.', true);
                    return;
                }
                setFeedback('Mensagem enviada com sucesso.', false);
            })
            .catch(function () {
                btnEnviar.disabled = false;
                setFeedback('Erro inesperado ao enviar mensagem.', true);
            });
        });
    }
    // ================= Triplo AI - integração com extensão (postMessage) =================

    function handleTriploAiActionFromExtension(payload) {
        if (!payload || typeof payload !== 'object') return;

        var cid = inputCid ? inputCid.value : '';
        if (!cid) {
            // Sem conversa vinculada, não há contexto para IA
            return;
        }

        var baseText = '';
        if (typeof payload.text === 'string' && payload.text.trim()) {
            baseText = payload.text.trim();
        }

        if (!baseText && payload.context && typeof payload.context.selectedMessageText === 'string') {
            var sel = payload.context.selectedMessageText.trim();
            if (sel) {
                baseText = sel;
            }
        }

        if (!baseText && payload.context && typeof payload.context.composerText === 'string') {
            var ctxTxt = payload.context.composerText.trim();
            if (ctxTxt) {
                baseText = ctxTxt;
            }
        }

        var body = {
            conversa_id: cid,
            _csrf_token: csrfToken
        };

        if (baseText) {
            body.mensagem = baseText;
        }

        // Metadados para logs no backend (opcional, compatível com o PHP atual)
        if (payload.action) {
            body.acao_ia = payload.action;
        }
        if (payload.requestId) {
            body.request_id = payload.requestId;
        }

        fetch('whatsapp_ai_suggestions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var ok = !!(data && data.ok && data.resposta);
                if (!ok) {
                    window.parent.postMessage({
                        type: 'triplo_ai_suggestion',
                        ok: false,
                        requestId: payload.requestId || null,
                        error: (data && data.error) ? data.error : 'Falha ao gerar sugestão IA.',
                        text: ''
                    }, '*');
                    return;
                }

                window.parent.postMessage({
                    type: 'triplo_ai_suggestion',
                    ok: true,
                    requestId: payload.requestId || null,
                    action: payload.action || null,
                    phone: payload.phone || null,
                    text: data.resposta,
                    meta: {
                        fonte: 'whatsapp_sidepanel_embed.php',
                        version: payload.version || null
                    }
                }, '*');
            })
            .catch(function () {
                window.parent.postMessage({
                    type: 'triplo_ai_suggestion',
                    ok: false,
                    requestId: payload.requestId || null,
                    error: 'Erro inesperado ao chamar IA (painel).',
                    text: ''
                }, '*');
            });
    }

    if (!window.__RA_TRIPLO_AI_PANEL_LISTENER__) {
        window.__RA_TRIPLO_AI_PANEL_LISTENER__ = true;
        window.addEventListener('message', function (event) {
            var data = event.data;
            if (!data || typeof data !== 'object') return;
            if (data.type !== 'triplo_ai_action') return;

            // Se informado, preferimos mensagens originadas da extensão do WhatsApp
            if (data.source && data.source !== 'rede-alabama-whatsapp-extension') {
                return;
            }

            handleTriploAiActionFromExtension(data.payload || {});
        });
    }

    // ================= Fim Triplo AI - integração com extensão =================

})();
</script>
</body>
</html>