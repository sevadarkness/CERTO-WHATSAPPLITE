<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/whatsapp_contacts_utils.php';

$pdo = get_db_connection();

$erro      = null;
$conversa  = null;
$mensagens = [];

/**
 * Carrega conversa por ID.
 *
 * @return array{conversa: array|null, mensagens: array} 
 */
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
    $conversa = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $mensagens = [];
    if ($conversa) {
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
        $mensagens = $stmtMsg->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return [
        'conversa'  => $conversa,
        'mensagens' => $mensagens,
    ];
}

/**
 * Encontra conversa mais relevante por telefone (auto-bind).
 *
 * Estratégia:
 *  - normaliza o telefone informado;
 *  - faz um LIKE pelos últimos dígitos em whatsapp_conversas.telefone_cliente;
 *  - entre as candidatas, normaliza e procura match exato; se não achar, usa a mais recente.
 *
 * @return array{conversa: array|null, mensagens: array}
 */
function alabama_sidepanel_find_conversa_by_phone(PDO $pdo, string $phoneDigits): array
{
    $phoneDigits = wa_only_digits($phoneDigits);
    if ($phoneDigits === '') {
        return ['conversa' => null, 'mensagens' => []];
    }

    // Usa os últimos 8 dígitos para reduzir o range de busca
    $tail = substr($phoneDigits, -8);
    $pattern = '%' . $tail . '%';

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
    $stmt->execute([':tel' => $pattern]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$rows) {
        return ['conversa' => null, 'mensagens' => []];
    }

    $chosen = null;
    foreach ($rows as $row) {
        $rowDigits = wa_only_digits((string)($row['telefone_cliente'] ?? ''));
        if ($rowDigits === $phoneDigits) {
            $chosen = $row;
            break;
        }
    }

    if (!$chosen) {
        // Fallback para a mais recente
        $chosen = $rows[0];
    }

    $result = alabama_sidepanel_load_conversa_by_id($pdo, (int)$chosen['id']);
    return $result;
}

$conversaId = isset($_GET['conversa_id']) ? (int)$_GET['conversa_id'] : 0;
$phoneParam = trim($_GET['phone'] ?? '');

try {
    if ($conversaId > 0) {
        $data = alabama_sidepanel_load_conversa_by_id($pdo, $conversaId);
        $conversa  = $data['conversa'];
        $mensagens = $data['mensagens'];
    } elseif ($phoneParam !== '') {
        $data = alabama_sidepanel_find_conversa_by_phone($pdo, $phoneParam);
        $conversa  = $data['conversa'];
        $mensagens = $data['mensagens'];
    } else {
        $erro = 'Conversa não informada. Abra o WhatsApp Web a partir do painel Alabama.';
    }
} catch (Throwable $e) {
    $erro = 'Erro ao carregar dados da conversa.';
    log_app_event('whatsapp_sidepanel', 'erro_carregar', [
        'conversa_id' => $conversaId,
        'phone'       => $phoneParam,
        'erro'        => $e->getMessage(),
    ]);
}

$csrfToken = csrf_token();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Rede Alabama – Painel WhatsApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Reaproveita tema e Bootstrap do painel principal -->
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body {
            background: #000;
            color: #f8f9fa;
            font-size: 13px;
            padding: 8px;
        }
        .nav-tabs {
            border-bottom-color: #2b2b2b;
        }
        .nav-tabs .nav-link {
            border-color: transparent;
            color: #adb5bd;
            padding: 0.35rem 0.5rem;
            font-size: 12px;
        }
        .nav-tabs .nav-link.active {
            color: #fff;
            background-color: #111827;
            border-color: #2b2b2b #2b2b2b #111827;
        }
        .msg-bubble {
            border-radius: 8px;
            padding: 6px 8px;
            margin-bottom: 4px;
            word-wrap: break-word;
            white-space: pre-wrap;
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
            max-height: 220px;
            overflow-y: auto;
            margin-bottom: 8px;
        }
        textarea {
            resize: vertical;
            min-height: 70px;
        }
        .small-label {
            font-size: 11px;
            margin-bottom: 2px;
        }
        .card-compact {
            background: #111827;
            border-color: #1f2933;
        }
    </style>
</head>
<body>
<?php if ($erro): ?>
    <div class="alert alert-warning p-2 mb-0">
        <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php else: ?>
    <?php
    $conversaIdSafe = $conversa ? (int)$conversa['id'] : 0;
    $telefoneSafe   = $conversa['telefone_cliente'] ?? $phoneParam;
    $statusSafe     = $conversa['status'] ?? '';
    $updatedSafe    = $conversa['updated_at'] ?? '';
    ?>

    <ul class="nav nav-tabs nav-fill mb-2">
        <li class="nav-item">
            <button class="nav-link active" data-tab="tab-conversa" type="button">
                <i class="fas fa-comments"></i> Conversa
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-tab="tab-ferramentas" type="button">
                <i class="fas fa-tools"></i> Ferramentas WhatsApp
            </button>
        </li>
    </ul>

    <!-- Aba 1: Conversa -->
    <div id="tab-conversa">
        <?php if (!$conversa): ?>
            <div class="alert alert-info p-2 mb-0">
                Nenhuma conversa encontrada para este telefone/ID.
            </div>
        <?php else: ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <strong>Contato:</strong>
                    <span><?php echo htmlspecialchars((string)$telefoneSafe, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span>Status:</span>
                    <span class="badge badge-secondary">
                        <?php echo htmlspecialchars((string)$statusSafe, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="small text-muted mt-1">
                    Atualizado em: <?php echo htmlspecialchars((string)$updatedSafe, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>

            <hr class="my-2">

            <div class="scroll-area">
                <?php if (empty($mensagens)): ?>
                    <p class="text-muted mb-0">Ainda não há histórico nesta conversa.</p>
                <?php else: ?>
                    <?php foreach (array_reverse($mensagens) as $m): ?>
                        <?php
                        $isOut = (($m['direction'] ?? '') === 'out');
                        $cls   = $isOut ? 'msg-bubble msg-out' : 'msg-bubble msg-in';
                        ?>
                        <div class="<?php echo $cls; ?>">
                            <div><?php echo nl2br(htmlspecialchars((string)($m['conteudo'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                            <div class="msg-ts">
                                <?php echo htmlspecialchars((string)($m['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo $isOut ? ' · Você' : ' · Cliente'; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <hr class="my-2">

            <form id="alabamaSidepanelForm">
                <input type="hidden" name="conversa_id" id="conversaId"
                       value="<?php echo $conversaIdSafe; ?>">

                <div class="mb-1">
                    <label class="small-label" for="mensagemCtx">Contexto adicional / mensagem</label>
                    <textarea class="form-control form-control-sm"
                              name="mensagem"
                              id="mensagemCtx"
                              placeholder="Digite detalhes ou deixe a IA sugerir uma resposta completa..."></textarea>
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
                </div>

                <div id="sidepanelFeedback" class="small mt-1"></div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Aba 2: Ferramentas WhatsApp (embeds compactos) -->
    <div id="tab-ferramentas" class="d-none">
        <div class="card card-compact mb-2">
            <div class="card-body p-2">
                <div class="small-label mb-1">
                    <i class="fas fa-bullhorn"></i> Envio em massa de WhatsApp
                </div>
                <iframe src="whatsapp_contacts_bulk_send.php?embed=1"
                        style="width:100%;height:210px;border:0;overflow:auto;background:#000;">
                </iframe>
            </div>
        </div>

        <div class="card card-compact mb-2">
            <div class="card-body p-2">
                <div class="small-label mb-1">
                    <i class="fas fa-tasks"></i> Status das campanhas em massa
                </div>
                <iframe src="whatsapp_contacts_bulk_status.php?embed=1"
                        style="width:100%;height:210px;border:0;overflow:auto;background:#000;">
                </iframe>
            </div>
        </div>

        <div class="card card-compact">
            <div class="card-body p-2">
                <div class="small-label mb-1">
                    <i class="fas fa-file-export"></i> Exportação da base de contatos
                </div>
                <a href="whatsapp_contacts_export.php"
                   target="_blank"
                   class="btn btn-sm btn-outline-light">
                    Exportar contatos (CSV)
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
(function () {
    // Controle de abas
    var tabButtons = document.querySelectorAll('[data-tab]');
    var tabConversa = document.getElementById('tab-conversa');
    var tabFerramentas = document.getElementById('tab-ferramentas');

    if (tabButtons && tabButtons.length) {
        tabButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = this.getAttribute('data-tab');
                if (!target) return;

                if (tabConversa) tabConversa.classList.add('d-none');
                if (tabFerramentas) tabFerramentas.classList.add('d-none');

                var activeTab = document.getElementById(target);
                if (activeTab) activeTab.classList.remove('d-none');

                tabButtons.forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
    }

    var form      = document.getElementById('alabamaSidepanelForm');
    if (!form) return;

    var inputCid  = document.getElementById('conversaId');
    var textarea  = document.getElementById('mensagemCtx');
    var btnIa     = document.getElementById('btnIa');
    var btnEnviar = document.getElementById('btnEnviar');
    var feedback  = document.getElementById('sidepanelFeedback');
    var csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>;

    function setFeedback(msg, isError) {
        if (!feedback) return;
        feedback.textContent = msg || '';
        feedback.className = 'small mt-1 ' + (isError ? 'text-danger' : 'text-info');
    }

    // Sugestão de resposta via IA
    if (btnIa) {
        btnIa.addEventListener('click', function () {
            var cid = inputCid ? inputCid.value : '';
            if (!cid) {
                setFeedback('Conversa inválida para IA.', true);
                return;
            }
            var msgCtx = textarea ? textarea.value.trim() : '';
            var payloadAi = {
                conversa_id: cid,
                _csrf_token: csrfToken
            };
            if (msgCtx) {
                payloadAi.mensagem = msgCtx;
            }

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

    // Envio de mensagem via API oficial
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
                conversa_id: cid,
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
