<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



require_once __DIR__ . '/rbac.php';
require_role(['Vendedor']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$usuarioId = $_SESSION['usuario_id'] ?? null;
if (!$usuarioId) {
    header('Location: login.php');
    exit;
}

// LEADS pendentes do vendedor
$leadsPendentes = [];
try {
    $stmt = (new \RedeAlabama\Repositories\Screens\PainelVendedorHojeRepository($pdo))->prepare_438();
    $stmt->execute([':uid' => $usuarioId]);
    $leadsPendentes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    log_app_event('painel_vendedor', 'erro_leads_pendentes', ['erro' => $e->getMessage()]);
}

// Sessão de atendimento ativa
$sessaoAtiva = null;
try {
    $stmt = (new \RedeAlabama\Repositories\Screens\PainelVendedorHojeRepository($pdo))->prepare_1023();
    $stmt->execute([':uid' => $usuarioId]);
    $sessaoAtiva = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    log_app_event('painel_vendedor', 'erro_sessao', ['erro' => $e->getMessage()]);
}

// Agenda do dia
$agendaHoje = [];
try {
    $stmt = (new \RedeAlabama\Repositories\Screens\PainelVendedorHojeRepository($pdo))->prepare_1472();
    $stmt->execute([':uid' => $usuarioId]);
    $agendaHoje = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    log_app_event('painel_vendedor', 'erro_agenda', ['erro' => $e->getMessage()]);
}

// Conversas em modo humano atribuídas a este vendedor
$conversasHumanas = [];
try {
    $sql = "
        SELECT c.id, c.telefone_cliente, c.status,
               c.ultima_mensagem_em,
               (SELECT m.conteudo
                  FROM whatsapp_mensagens m
                 WHERE m.conversa_id = c.id
                 ORDER BY m.id DESC
                 LIMIT 1) AS ultima_msg
          FROM whatsapp_atendimentos a
          JOIN whatsapp_conversas c ON c.id = a.conversa_id
         WHERE a.usuario_id = :uid
           AND a.status = 'aberto'
         ORDER BY c.updated_at DESC, c.id DESC
         LIMIT 20
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $usuarioId]);
    $conversasHumanas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    log_app_event('painel_vendedor', 'erro_conversas_humanas', ['erro' => $e->getMessage()]);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Operação Hoje - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
</head>
<body class="al-body">
<div class="container mt-4">
    <h1 class="mb-3">Minha operação hoje</h1>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h5 class="card-title">Leads pendentes</h5>
                    <p class="text-muted mb-1">Leads com status <strong>novo</strong> ou <strong>em atendimento</strong>.</p>
                    <h2 class="display-6">
                        <?php echo count($leadsPendentes); ?>
                    </h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h5 class="card-title">Sessão de atendimento</h5>
                    <?php if ($sessaoAtiva): ?>
                        <p>Iniciada em:<br><strong><?php echo htmlspecialchars($sessaoAtiva['inicio'] ?? ''); ?></strong></p>
                        <p class="mb-0"><small>Finalize em <code>sessoes_atendimento.php</code> quando encerrar.</small></p>
                    <?php else: ?>
                        <p class="mb-0 text-muted">Nenhuma sessão ativa no momento.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h5 class="card-title">Compromissos de hoje</h5>
                    <p class="mb-1">
                        <strong><?php echo count($agendaHoje); ?></strong> itens na agenda.
                    </p>
                    <p class="mb-0"><small>Gerencie em <code>agenda.php</code>.</small></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card bg-secondary text-light mb-3">
                <div class="card-header">
                    Leads pendentes (top 20)
                </div>
                <div class="card-body p-0">
                    <?php if (!$leadsPendentes): ?>
                        <p class="p-3 text-muted mb-0">Nenhum lead pendente.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped table-sm mb-0 align-middle">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Telefone</th>
                                    <th>Status</th>
                                    <th>Criado</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($leadsPendentes as $lead): ?>
                                    <tr>
                                        <td><?php echo (int)$lead['id']; ?></td>
                                        <td><?php echo htmlspecialchars($lead['nome_cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($lead['telefone_cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($lead['status'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($lead['criado_em'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card bg-secondary text-light">
                <div class="card-header">
                    Conversas WhatsApp (modo humano)
                </div>
                <div class="card-body p-0">
                    <?php if (!$conversasHumanas): ?>
                        <p class="p-3 text-muted mb-0">Nenhuma conversa em modo humano atribuída a você.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped table-sm mb-0 align-middle">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Telefone</th>
                                    <th>Última mensagem</th>
                                    <th>Atualizado</th>
                                    <th>Ações</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($conversasHumanas as $c): ?>
                                    <tr>
                                        <td><?php echo (int)$c['id']; ?></td>
                                        <td><?php echo htmlspecialchars($c['telefone_cliente'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($c['ultima_msg'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($c['ultima_mensagem_em'] ?? ''); ?></td>
                                        <td>
                                            <button type="button"
                                                    class="btn btn-sm btn-primary js-whatsapp-vendedor-responder"
                                                    data-conversa-id="<?php echo (int)$c['id']; ?>"
                                                    data-telefone="<?php echo htmlspecialchars($c['telefone_cliente'] ?? ''); ?>">
                                                Responder
                                            </button>
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

        <div class="col-md-6">
            <div class="card bg-secondary text-light h-100">
                <div class="card-header">
                    Agenda de hoje
                </div>
                <div class="card-body p-0">
                    <?php if (!$agendaHoje): ?>
                        <p class="p-3 text-muted mb-0">Nenhum compromisso hoje.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped table-sm mb-0 align-middle">
                                <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Título</th>
                                    <th>Canal</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($agendaHoje as $ag): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ag['data_hora_inicio'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($ag['titulo'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($ag['canal'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($ag['status'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de resposta WhatsApp para vendedor -->
<div class="modal fade" id="modalVendedorWhats" tabindex="-1" aria-labelledby="modalVendedorWhatsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVendedorWhatsLabel">Responder cliente pelo painel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">
                    Telefone: <span id="vend-whats-telefone" class="fw-bold"></span>
                </p>
                <textarea id="vend-whats-mensagem" class="form-control form-control-sm bg-dark text-light"
                          rows="4" placeholder="Digite ou cole aqui a mensagem para o cliente..."></textarea>
                <input type="hidden" id="vend-whats-conversa-id">
                <div id="vend-whats-feedback" class="mt-2 small"></div>
                <small class="text-muted d-block mt-1">
                    A mensagem será enviada via API oficial do WhatsApp configurada no painel.
                </small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-info btn-sm" id="vend-whats-btn-ia">
                    Gerar com IA
                </button>
                <button type="button" class="btn btn-success btn-sm" id="vend-whats-btn-enviar">
                    Enviar pelo painel
                </button>
            </div>
        </div>
    </div>
</div>

<script <?php echo alabama_csp_nonce_attr(); ?>>
(function () {
    var modalEl   = document.getElementById('modalVendedorWhats');
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;

    var modal     = new bootstrap.Modal(modalEl);
    var spanTel   = document.getElementById('vend-whats-telefone');
    var inputCid  = document.getElementById('vend-whats-conversa-id');
    var textarea  = document.getElementById('vend-whats-mensagem');
    var feedback  = document.getElementById('vend-whats-feedback');
    var btnEnviar = document.getElementById('vend-whats-btn-enviar');
    var btnIa     = document.getElementById('vend-whats-btn-ia');

    function setFeedback(msg, isError) {
        if (!feedback) return;
        feedback.textContent = msg || '';
        feedback.className = 'mt-2 small ' + (isError ? 'text-danger' : 'text-success');
    }

    // Abrir modal ao clicar no botão "Responder"
    document.querySelectorAll('.js-whatsapp-vendedor-responder').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cid = this.getAttribute('data-conversa-id') || '';
            var tel = this.getAttribute('data-telefone') || '';

            if (inputCid) inputCid.value = cid;
            if (spanTel) spanTel.textContent = tel || '(sem telefone)';

            if (textarea) textarea.value = '';
            setFeedback('', false);

            modal.show();
            if (textarea) textarea.focus();
        });
    });


    // Gerar sugestão de resposta com IA
    if (btnIa) {
        btnIa.addEventListener('click', function () {
            var cid = inputCid ? inputCid.value : '';
            if (!cid) {
                setFeedback('Conversa inválida para IA.', true);
                return;
            }
            var msgCtx = textarea ? textarea.value.trim() : '';
            var payloadAi = new URLSearchParams();
            payloadAi.append('conversa_id', String(cid));
            if (msgCtx) {
                payloadAi.append('mensagem', msgCtx);
            }

            setFeedback('Gerando sugestão com IA...', false);
            btnIa.disabled = true;

            fetch('whatsapp_ai_suggestions.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: payloadAi.toString()
            })
            .then(function (res) { return res.json().catch(function () { return {}; }); })
            .then(function (data) {
                if (!data || data.ok !== true) {
                    var err = (data && data.error) ? data.error : 'Falha ao gerar sugestão com IA.';
                    setFeedback(err, true);
                    return;
                }
                var sugestao = (data.resposta || '').trim();
                if (sugestao && textarea) {
                    textarea.value = sugestao;
                }
                setFeedback('Sugestão de IA aplicada. Revise e, se estiver ok, envie pelo painel.', false);
            })
            .catch(function () {
                setFeedback('Erro inesperado ao chamar IA.', true);
            })
            .finally(function () {
                btnIa.disabled = false;
            });
        });
    }

    // Enviar mensagem pelo painel
    if (btnEnviar) {
        btnEnviar.addEventListener('click', function () {
            var cid = inputCid ? inputCid.value : '';
            var msg = textarea ? textarea.value.trim() : '';
            var csrfToken = window.AL_BAMA_CSRF_TOKEN || '';

            if (!cid) {
                setFeedback('Conversa inválida.', true);
                return;
            }
            if (!msg) {
                setFeedback('Digite a mensagem antes de enviar.', true);
                if (textarea) textarea.focus();
                return;
            }

            btnEnviar.disabled = true;
            setFeedback('Enviando mensagem...', false);

            var payload = new URLSearchParams();
            payload.append('conversa_id', String(cid));
            payload.append('mensagem', msg);
            if (csrfToken) {
                payload.append('_csrf_token', csrfToken);
            }

            fetch('whatsapp_manual_send.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: payload.toString()
            })
            .then(function (res) { return res.json().catch(function () { return {}; }); })
            .then(function (data) {
                if (!data || data.ok !== true) {
                    var err = (data && data.error) ? data.error : 'Falha ao enviar mensagem.';
                    setFeedback(err, true);
                    btnEnviar.disabled = false;
                    return;
                }
                setFeedback('Mensagem enviada com sucesso pelo painel.', false);
                setTimeout(function () {
                    modal.hide();
                    window.location.reload();
                }, 1000);
            })
            .catch(function () {
                setFeedback('Erro inesperado ao enviar mensagem.', true);
                btnEnviar.disabled = false;
            });
        });
    }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
