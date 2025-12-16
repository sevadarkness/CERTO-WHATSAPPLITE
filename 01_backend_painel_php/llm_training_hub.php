<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$erro = null;
$ok   = null;

$acao = $_POST['acao'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao) {
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    if (!$usuario_id) {
        $erro = 'Usuário não identificado na sessão.';
    } else {
        try {
            if ($acao === 'salvar_sample') {
                $conversa_id       = isset($_POST['conversa_id']) ? (int)$_POST['conversa_id'] : null;
                $mensagem_usuario  = trim($_POST['mensagem_usuario'] ?? '');
                $resposta_bot      = trim($_POST['resposta_bot'] ?? '');
                $resposta_ajustada = trim($_POST['resposta_ajustada'] ?? '');
                $tags              = trim($_POST['tags'] ?? '');
                $aprovado          = isset($_POST['aprovado']) ? 1 : 0;

                if ($mensagem_usuario !== '') {
                    $sql = "INSERT INTO llm_training_samples (fonte, conversa_id, mensagem_usuario, resposta_bot, resposta_ajustada, aprovado, marcado_por_id, tags)
                            VALUES ('whatsapp', :conversa_id, :mensagem_usuario, :resposta_bot, :resposta_ajustada, :aprovado, :marcado_por_id, :tags)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':conversa_id'      => $conversa_id ?: null,
                        ':mensagem_usuario' => $mensagem_usuario,
                        ':resposta_bot'     => $resposta_bot !== '' ? $resposta_bot : null,
                        ':resposta_ajustada'=> $resposta_ajustada !== '' ? $resposta_ajustada : null,
                        ':aprovado'         => $aprovado,
                        ':marcado_por_id'   => $usuario_id,
                        ':tags'             => $tags !== '' ? $tags : null,
                    ]);
                    $ok = 'Exemplo salvo para dataset de treinamento.';
                    log_app_event('llm_training', 'sample_salvo', ['usuario_id' => $usuario_id]);
                } else {
                    $erro = 'Mensagem do usuário não pode ser vazia.';
                }
            }
        } catch (Throwable $e) {
            $erro = 'Erro ao salvar exemplo: ' . htmlspecialchars($e->getMessage());
            log_app_event('llm_training', 'erro_salvar', ['erro' => $e->getMessage()]);
        }
    }
}

// Lista últimos samples aprovados
$sql = "SELECT s.id, s.fonte, s.conversa_id, s.mensagem_usuario, s.resposta_bot, s.resposta_ajustada,
               s.aprovado, s.tags, s.criado_em,
               u.nome AS marcado_por
        FROM llm_training_samples s
        LEFT JOIN usuarios u ON u.id = s.marcado_por_id
        ORDER BY s.id DESC
        LIMIT 100";
$stmt = $pdo->query($sql);
$samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Também podemos listar últimas conversas do WhatsApp como fonte de inspiração
$sqlConv = "SELECT c.id, c.telefone_cliente,
                   (SELECT m.conteudo FROM whatsapp_mensagens m WHERE m.conversa_id = c.id AND m.direction = 'in' ORDER BY m.id DESC LIMIT 1) AS ultima_pergunta,
                   (SELECT m.conteudo FROM whatsapp_mensagens m WHERE m.conversa_id = c.id AND m.direction = 'out' ORDER BY m.id DESC LIMIT 1) AS ultima_resposta
            FROM whatsapp_conversas c
            ORDER BY c.updated_at DESC
            LIMIT 50";
$stmtConv = $pdo->query($sqlConv);
$conversas = $stmtConv->fetchAll(PDO::FETCH_ASSOC);
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
    <title>LLM Training Hub - Dataset supervisionado</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="al-body">
<div class="container mt-4">
    <h1 class="mb-3"><i class="fas fa-brain"></i> LLM Training Hub</h1>

    <p class="text-muted">
        Use esta tela para transformar conversas reais em exemplos supervisionados de alta qualidade,
        prontos para serem exportados como dataset de fine-tuning.
    </p>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo $erro; ?></div>
    <?php endif; ?>
    <?php if ($ok): ?>
        <div class="alert alert-success"><?php echo $ok; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-header">
                    Criar novo exemplo supervisionado
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="acao" value="salvar_sample">
                        <div class="mb-3">
                            <label class="form-label" for="conversa_id">ID da conversa (opcional)</label>
                            <input type="number" class="form-control" id="conversa_id" name="conversa_id">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="mensagem_usuario">Mensagem do cliente (prompt)</label>
                            <textarea class="form-control" id="mensagem_usuario" name="mensagem_usuario" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="resposta_bot">Resposta atual do Bot (se houver)</label>
                            <textarea class="form-control" id="resposta_bot" name="resposta_bot" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="resposta_ajustada">Resposta ideal (corrigida pelo humano)</label>
                            <textarea class="form-control" id="resposta_ajustada" name="resposta_ajustada" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="tags">Tags (ex.: venda, objeção, entrega)</label>
                            <input type="text" class="form-control" id="tags" name="tags">
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" id="aprovado" name="aprovado" checked>
                            <label class="form-check-label" for="aprovado">Aprovado para dataset</label>
                        </div>
                        <button type="submit" class="btn btn-success">Salvar exemplo</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-header">
                    Conversas recentes (fonte de exemplos)
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-dark table-striped table-sm align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Telefone</th>
                                <th>Última pergunta</th>
                                <th>Última resposta</th>
                                <th>Ação</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$conversas): ?>
                                <tr><td colspan="4" class="text-center text-muted">Nenhuma conversa encontrada.</td></tr>
                            <?php else: ?>
                                <?php foreach ($conversas as $c): ?>
                                    <tr>
                                        <td><?php echo (int)$c['id']; ?></td>
                                        <td><?php echo htmlspecialchars($c['telefone_cliente']); ?></td>
                                        <td><?php echo htmlspecialchars($c['ultima_pergunta'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($c['ultima_resposta'] ?? ''); ?></td>
                                        <td>
                                            <?php
                                            $promptBase = $c['ultima_resposta'] ?? '';
                                            ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info"
                                                    onclick="navigator.clipboard.writeText(<?php echo json_encode($promptBase, JSON_UNESCAPED_UNICODE); ?>);">
                                                Usar como prompt
                                            </button>
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
    </div>

    <div class="card bg-secondary text-light mt-3">
        <div class="card-header">
            Últimos exemplos supervisionados
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-striped table-sm align-middle">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fonte</th>
                        <th>Conversa</th>
                        <th>Aprovado</th>
                        <th>Tags</th>
                        <th>Marcado por</th>
                        <th>Criado em</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$samples): ?>
                        <tr><td colspan="7" class="text-center text-muted">Nenhum exemplo salvo.</td></tr>
                    <?php else: ?>
                        <?php foreach ($samples as $s): ?>
                            <tr>
                                <td><?php echo (int)$s['id']; ?></td>
                                <td><?php echo htmlspecialchars($s['fonte']); ?></td>
                                <td><?php echo htmlspecialchars((string)($s['conversa_id'] ?? '')); ?></td>
                                <td>
                                    <?php if (!empty($s['aprovado'])): ?>
                                        <span class="badge bg-success">Sim</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($s['tags'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($s['marcado_por'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($s['criado_em'] ?? ''); ?></td>
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
