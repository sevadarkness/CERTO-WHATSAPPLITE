<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/whatsapp_contacts_utils.php';

function alabama_build_whatsapp_web_url(string $telefone, int $conversaId): string
{
    $digits = wa_only_digits($telefone);
    if ($digits === '') {
        return 'https://web.whatsapp.com/';
    }

    $base = 'https://web.whatsapp.com/send';
    $query = http_build_query([
        'phone'          => $digits,
        'ra_conversa_id' => $conversaId,
    ]);

    return $base . '?' . $query;
}

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$f_tel = trim($_GET['telefone'] ?? '');

$sql = "SELECT c.id, c.telefone_cliente, c.status, c.created_at, c.updated_at, c.ultima_mensagem_em,
               (SELECT m.conteudo FROM whatsapp_mensagens m WHERE m.conversa_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultima_msg
        FROM whatsapp_conversas c";
$params = [];

if ($f_tel !== '') {
    $sql .= " WHERE c.telefone_cliente LIKE :tel";
    $params[':tel'] = '%' . $f_tel . '%';
}

$sql .= " ORDER BY c.updated_at DESC, c.id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
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
    <title>Bot WhatsApp IA - Conversas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="al-body">
<div class="container mt-4">
    <h1 class="mb-3"><i class="fab fa-whatsapp"></i> Bot WhatsApp IA - Conversas</h1>

    <form class="row g-3 mb-3" method="get">
        <div class="col-md-4">
            <label for="telefone" class="form-label">Telefone</label>
            <input type="text" id="telefone" name="telefone" class="form-control"
                   value="<?php echo htmlspecialchars($f_tel); ?>" placeholder="+55...">
        </div>
        <div class="col-md-4 align-self-end">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="whatsapp_bot_console.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>
    <div class="row mt-3">
        <div class="col-md-8">
            <div class="card bg-dark text-light mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-2">Inteligência de Contatos (Bot WhatsApp)</h5>
                    <p class="card-text mb-3">
                        Utilize esta área para exportar contatos oriundos do Bot WhatsApp,
                        cruzando conversas com a base de Leads. Os dados são calculados
                        diretamente a partir das tabelas <code>whatsapp_conversas</code>,
                        <code>whatsapp_mensagens</code> e <code>leads</code>.
                    </p>
                    <a href="whatsapp_contacts_export.php" class="btn btn-success btn-sm">
                        Exportar contatos (CSV)
                    </a>
                    <a href="whatsapp_contacts_api.php?action=chats&amp;limit=1000" class="btn btn-outline-light btn-sm" target="_blank" rel="noopener">
                        Ver JSON (debug)
                    </a>
                </div>
            </div>
        </div>
    </div>



    <div class="card bg-secondary text-light">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Telefone</th>
                        <th>Status</th>
                        <th>Última mensagem</th>
                        <th>Atualizado em</th>
                        <th>Aberta em</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$conversas): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Nenhuma conversa encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($conversas as $c): ?>
                            <tr>
                                <td><?php echo (int)$c['id']; ?></td>
                                <td><?php echo htmlspecialchars($c['telefone_cliente']); ?></td>
                                <td>
                                    <?php
                                    $status = $c['status'] ?? 'ativa';
                                    $badgeClass = 'bg-success';
                                    if ($status === 'encerrada') {
                                        $badgeClass = 'bg-secondary';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                </td>
                                <td style="max-width: 320px;">
                                    <span class="text-truncate d-inline-block" style="max-width: 320px;">
                                        <?php echo htmlspecialchars($c['ultima_msg'] ?? ''); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($c['updated_at'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($c['created_at'] ?? ''); ?></td>
                                <td class="d-flex gap-2">
                                    <button type="button"
                                            class="btn btn-sm btn-primary btn-manual-send"
                                            data-conversa-id="<?php echo (int)$c['id']; ?>"
                                            data-telefone="<?php echo htmlspecialchars($c['telefone_cliente']); ?>">
                                        Enviar via API
                                    </button>
                                    <a href="<?php echo htmlspecialchars(
                                                alabama_build_whatsapp_web_url(
                                                    (string)$c['telefone_cliente'],
                                                    (int)$c['id']
                                                )
                                            ); ?>"
                                       class="btn btn-sm btn-success"
                                       target="_blank"
                                       rel="noopener noreferrer">
                                        WhatsApp Web + CRM
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal para envio manual via API WhatsApp -->
<div class="modal fade" id="manualSendModal" tabindex="-1" aria-labelledby="manualSendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="manualSendModalLabel">Enviar mensagem via API WhatsApp</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form id="manualSendForm">
                    <input type="hidden" id="manualSendConversaId" name="conversa_id">
                    <div class="mb-3">
                        <label for="manualSendTelefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="manualSendTelefone" name="telefone" placeholder="+55..." readonly>
                    </div>
                    <div class="mb-3">
                        <label for="manualSendMensagem" class="form-label">Mensagem</label>
                        <textarea class="form-control" id="manualSendMensagem" name="mensagem" rows="4"
                                  placeholder="Digite ou cole aqui a mensagem que deseja enviar..."></textarea>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Enviar via API WhatsApp</button>
                    </div>
                </form>
                <div id="manualSendFeedback" class="mt-3 small"></div>
            </div>
        </div>
    </div>
</div>

<script <?php echo alabama_csp_nonce_attr(); ?>>
(function() {
    const modalEl   = document.getElementById('manualSendModal');
    if (!modalEl) return;

    const modal     = new bootstrap.Modal(modalEl);
    const form      = document.getElementById('manualSendForm');
    const inputCid  = document.getElementById('manualSendConversaId');
    const inputTel  = document.getElementById('manualSendTelefone');
    const inputMsg  = document.getElementById('manualSendMensagem');
    const feedback  = document.getElementById('manualSendFeedback');

    // Abre modal ao clicar em "Enviar via API"
    document.querySelectorAll('.btn-manual-send').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const cid = this.getAttribute('data-conversa-id') || '';
            const tel = this.getAttribute('data-telefone') || '';
            inputCid.value = cid;
            inputTel.value = tel;
            inputMsg.value = '';
            feedback.textContent = '';
            feedback.className = 'mt-3 small';
            modal.show();
        });
    });

    // Submit do formulário (envio AJAX)
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const cid = inputCid.value;
        const tel = inputTel.value;
        const msg = inputMsg.value.trim();

        if (!msg) {
            feedback.textContent = 'Digite uma mensagem antes de enviar.';
            feedback.className = 'mt-3 small text-warning';
            return;
        }

        const payload = {
            conversa_id: cid ? parseInt(cid, 10) : null,
            telefone: tel,
            mensagem: msg,
            _csrf_token: window.AL_BAMA_CSRF_TOKEN || ''
        };

        feedback.textContent = 'Enviando...';
        feedback.className = 'mt-3 small text-info';

        fetch('whatsapp_manual_send.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(function(r) { return r.json(); })
            .then(function(resp) {
                if (!resp || !resp.ok) {
                    feedback.textContent = (resp && resp.error) ? resp.error : 'Falha ao enviar mensagem.';
                    feedback.className = 'mt-3 small text-danger';
                    return;
                }
                feedback.textContent = 'Mensagem enviada com sucesso via API WhatsApp.';
                feedback.className = 'mt-3 small text-success';
                setTimeout(function() {
                    modal.hide();
                    window.location.reload();
                }, 1200);
            })
            .catch(function(err) {
                console.error(err);
                feedback.textContent = 'Erro inesperado ao enviar mensagem.';
                feedback.className = 'mt-3 small text-danger';
            });
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
