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

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$erro = null;
$ok   = null;

// Ações: assumir / devolver
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao        = $_POST['acao'] ?? '';
    $conversa_id = isset($_POST['conversa_id']) ? (int)$_POST['conversa_id'] : 0;
    $usuario_id  = $_SESSION['usuario_id'] ?? null;

    if ($conversa_id > 0 && $usuario_id) {
        try {
            if ($acao === 'assumir') {
                // Fecha atendimentos abertos anteriores para essa conversa
                $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappHandoverRepository($pdo))->prepare_745();
                $stmt->execute([':id' => $conversa_id]);

                // Cria novo atendimento humano
                $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappHandoverRepository($pdo))->prepare_1013();
                $stmt->execute([':cid' => $conversa_id, ':uid' => $usuario_id]);

                $ok = 'Atendimento assumido pelo humano.';
            } elseif ($acao === 'devolver_bot') {
                // Fecha atendimentos abertos para a conversa (volta para modo bot)
                $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappHandoverRepository($pdo))->prepare_1444();
                $stmt->execute([':id' => $conversa_id]);
                $ok = 'Atendimento devolvido para o Bot.';
            }
            log_app_event('whatsapp_handover', 'acao', [
                'acao' => $acao,
                'conversa_id' => $conversa_id,
                'usuario_id' => $usuario_id,
            ]);
        } catch (Throwable $e) {
            $erro = 'Erro ao processar ação: ' . htmlspecialchars($e->getMessage());
            log_app_event('whatsapp_handover', 'erro', ['erro' => $e->getMessage()]);
        }
    }
}

// Lista de conversas recentes com status de handover
$sql = "SELECT c.id, c.telefone_cliente, c.status AS status_conversa, 
               c.created_at, c.updated_at, c.ultima_mensagem_em,
               (SELECT m.conteudo FROM whatsapp_mensagens m WHERE m.conversa_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultima_msg,
               (SELECT a.modo FROM whatsapp_atendimentos a WHERE a.conversa_id = c.id AND a.status = 'aberto' ORDER BY a.id DESC LIMIT 1) AS modo_atendimento,
               (SELECT u.nome FROM whatsapp_atendimentos a JOIN usuarios u ON u.id = a.usuario_id 
                  WHERE a.conversa_id = c.id AND a.status = 'aberto' ORDER BY a.id DESC LIMIT 1) AS atendente
        FROM whatsapp_conversas c
        ORDER BY c.updated_at DESC, c.id DESC
        LIMIT 200";

$stmt = $pdo->query($sql);
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
    <title>Handover WhatsApp - Bot x Humano</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="al-body">
<div class="container mt-4">
    <h1 class="mb-3"><i class="fab fa-whatsapp"></i> Handover WhatsApp - Bot x Humano</h1>

    <p class="text-muted">
        Esta tela controla quem está atendendo cada conversa: <strong>Bot</strong> ou <strong>Humano</strong>.
        Quando marcado como <em>humano</em>, o Bot NÃO responde automaticamente novas mensagens daquela conversa.
    </p>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo $erro; ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
        <div class="alert alert-success"><?php echo $ok; ?></div>
    <?php endif; ?>

    <div class="card bg-secondary text-light">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-striped align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Telefone</th>
                        <th>Status conversa</th>
                        <th>Modo</th>
                        <th>Atendente</th>
                        <th>Última mensagem</th>
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
                            <?php
                            $modo = $c['modo_atendimento'] ?? 'bot';
                            $atendente = $c['atendente'] ?? '-';
                            ?>
                            <tr>
                                <td><?php echo (int)$c['id']; ?></td>
                                <td><?php echo htmlspecialchars($c['telefone_cliente']); ?></td>
                                <td><?php echo htmlspecialchars($c['status_conversa'] ?? ''); ?></td>
                                <td>
                                    <?php if ($modo === 'humano'): ?>
                                        <span class="badge bg-info">Humano</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Bot</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($atendente); ?></td>
                                <td style="max-width: 320px;">
                                    <span class="text-truncate d-inline-block" style="max-width: 320px;">
                                        <?php echo htmlspecialchars($c['ultima_msg'] ?? ''); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="conversa_id" value="<?php echo (int)$c['id']; ?>">
                                        <?php if ($modo === 'humano'): ?>
                                            <button type="submit" name="acao" value="devolver_bot" class="btn btn-sm btn-outline-warning">
                                                Devolver para Bot
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="acao" value="assumir" class="btn btn-sm btn-outline-light">
                                                Assumir atendimento
                                            </button>
                                        <?php endif; ?>
                                    </form>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
