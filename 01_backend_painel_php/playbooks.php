<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



require_once __DIR__ . '/rbac.php';
require_role(array('Administrador', 'Gerente'));

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

$user = current_user();
$usuarioId = $user['id'] ?? null;
$usuarioNome = $user['nome'] ?? '';

if (!$usuarioId) {
    header('Location: login.php');
    exit;
}

$mensagens = array();

// Tratamento de POST (criação/edição básica de playbook e etapas)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'criar_playbook') {
            $nome = trim((string)($_POST['nome'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $canalBase = $_POST['canal_base'] ?? 'whatsapp';

            if ($nome === '') {
                $mensagens[] = array('tipo' => 'danger', 'texto' => 'Nome do playbook é obrigatório.');
            } else {
                $stmt = (new \RedeAlabama\Repositories\Screens\PlaybooksRepository($pdo))->prepare_951();
                $stmt->execute(array(
                    ':nome'       => $nome,
                    ':descricao'  => $descricao !== '' ? $descricao : null,
                    ':canal_base' => $canalBase,
                    ':criado_por' => $usuarioId,
                ));

                $novoId = (int)$pdo->lastInsertId();
                log_app_event('playbooks', 'playbook_criado', array(
                    'playbook_id' => $novoId,
                    'usuario_id'  => $usuarioId,
                ));
                header('Location: playbooks.php?id=' . $novoId);
                exit;
            }

        } elseif ($acao === 'alterar_status') {
            $playbookId = isset($_POST['playbook_id']) ? (int)$_POST['playbook_id'] : 0;
            $ativo = isset($_POST['ativo']) && $_POST['ativo'] === '1' ? 1 : 0;

            if ($playbookId > 0) {
                $stmt = (new \RedeAlabama\Repositories\Screens\PlaybooksRepository($pdo))->prepare_2050();
                $stmt->execute(array(':ativo' => $ativo, ':id' => $playbookId));

                log_app_event('playbooks', 'playbook_status_alterado', array(
                    'playbook_id' => $playbookId,
                    'ativo'       => $ativo,
                    'usuario_id'  => $usuarioId,
                ));
                header('Location: playbooks.php');
                exit;
            }

        } elseif ($acao === 'criar_etapa') {
            $playbookId = isset($_POST['playbook_id']) ? (int)$_POST['playbook_id'] : 0;
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $offsetDias = isset($_POST['offset_dias']) ? (int)$_POST['offset_dias'] : 0;
            $canal = $_POST['canal'] ?? 'whatsapp';
            $template = trim((string)($_POST['template_mensagem'] ?? ''));

            if ($playbookId <= 0 || $titulo === '') {
                $mensagens[] = array('tipo' => 'danger', 'texto' => 'Playbook e título da etapa são obrigatórios.');
            } else {
                // Determina próxima ordem
                $stmtOrdem = (new \RedeAlabama\Repositories\Screens\PlaybooksRepository($pdo))->prepare_3276();
                $stmtOrdem->execute(array(':pid' => $playbookId));
                $max = (int)$stmtOrdem->fetchColumn();
                $ordem = $max + 1;

                $stmt = (new \RedeAlabama\Repositories\Screens\PlaybooksRepository($pdo))->prepare_3566();
                $stmt->execute(array(
                    ':playbook_id'       => $playbookId,
                    ':ordem'             => $ordem,
                    ':titulo'            => $titulo,
                    ':descricao'         => $descricao !== '' ? $descricao : null,
                    ':offset_dias'       => $offsetDias,
                    ':canal'             => $canal,
                    ':template_mensagem' => $template !== '' ? $template : null,
                ));

                log_app_event('playbooks', 'playbook_etapa_criada', array(
                    'playbook_id' => $playbookId,
                    'ordem'       => $ordem,
                    'usuario_id'  => $usuarioId,
                ));
                header('Location: playbooks.php?id=' . $playbookId);
                exit;
            }
        }
    } catch (Throwable $e) {
        $mensagens[] = array('tipo' => 'danger', 'texto' => 'Erro ao processar requisição: ' . htmlspecialchars($e->getMessage()));
        error_log('Erro em playbooks.php: ' . $e->getMessage());
    }
}

// Carrega playbooks
$stmtPb = (new \RedeAlabama\Repositories\Screens\PlaybooksRepository($pdo))->query_4716();
$playbooks = $stmtPb->fetchAll(PDO::FETCH_ASSOC);

$playbookSelecionado = null;
$etapasSelecionadas = array();

$playbookIdParam = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($playbookIdParam > 0) {
    $stmt = (new \RedeAlabama\Repositories\Screens\PlaybooksRepository($pdo))->prepare_5340();
    $stmt->execute(array(':id' => $playbookIdParam));
    $playbookSelecionado = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($playbookSelecionado) {
        $stmtEtapas = (new \RedeAlabama\Repositories\Screens\PlaybooksRepository($pdo))->prepare_5691();
        $stmtEtapas->execute(array(':id' => $playbookIdParam));
        $etapasSelecionadas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playbooks de Atendimento - Rede Alabama</title>
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
<?php include __DIR__ . '/menu_navegacao.php'; ?>

<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Playbooks de Atendimento</h1>
            <p class="text-muted mb-0">Sequências estruturadas para padronizar abordagem, follow-up e pós-venda.</p>
        </div>
        <div class="text-end">
            <span class="badge bg-secondary">Usuário: <?php echo htmlspecialchars($usuarioNome); ?></span>
        </div>
    </div>

    <?php foreach ($mensagens as $msg): ?>
        <div class="alert alert-<?php echo htmlspecialchars($msg['tipo']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($msg['texto']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endforeach; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h2 class="h5 mb-0">Novo playbook</h2>
                </div>
                <div class="card-body">
                    <form method="post" class="vstack gap-2">
                        <input type="hidden" name="acao" value="criar_playbook">
                        <div class="mb-2">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" required maxlength="255">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Canal base</label>
                            <select name="canal_base" class="form-select">
                                <option value="whatsapp">WhatsApp</option>
                                <option value="telefone">Telefone</option>
                                <option value="email">E-mail</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3" placeholder="Ex.: Fluxo de onboarding, recuperação de carrinho, pós-venda..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Criar playbook</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h2 class="h5 mb-0">Playbooks existentes</h2>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (!$playbooks): ?>
                            <div class="list-group-item text-muted">Nenhum playbook cadastrado.</div>
                        <?php else: ?>
                            <?php foreach ($playbooks as $pb): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="me-2">
                                        <a href="playbooks.php?id=<?php echo (int)$pb['id']; ?>" class="fw-semibold text-decoration-none">
                                            <?php echo htmlspecialchars($pb['nome']); ?>
                                        </a>
                                        <div class="small text-muted">
                                            Canal base: <?php echo htmlspecialchars($pb['canal_base']); ?> ·
                                            Criado por: <?php echo htmlspecialchars($pb['criado_por_nome'] ?? '—'); ?>
                                        </div>
                                    </div>
                                    <form method="post" class="ms-2">
                                        <input type="hidden" name="acao" value="alterar_status">
                                        <input type="hidden" name="playbook_id" value="<?php echo (int)$pb['id']; ?>">
                                        <input type="hidden" name="ativo" value="<?php echo $pb['ativo'] ? '0' : '1'; ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?php echo $pb['ativo'] ? 'btn-outline-success' : 'btn-outline-secondary'; ?>">
                                            <?php echo $pb['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <?php if ($playbookSelecionado): ?>
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h5 mb-0"><?php echo htmlspecialchars($playbookSelecionado['nome']); ?></h2>
                            <p class="small text-muted mb-0">
                                Canal base: <?php echo htmlspecialchars($playbookSelecionado['canal_base']); ?> ·
                                Status: <?php echo $playbookSelecionado['ativo'] ? 'Ativo' : 'Inativo'; ?>
                            </p>
                        </div>
                        <div class="text-end small text-muted">
                            Criado por: <?php echo htmlspecialchars($playbookSelecionado['criado_por_nome'] ?? '—'); ?><br>
                            Em: <?php echo htmlspecialchars($playbookSelecionado['criado_em']); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($playbookSelecionado['descricao']): ?>
                            <p><?php echo nl2br(htmlspecialchars($playbookSelecionado['descricao'])); ?></p>
                        <?php else: ?>
                            <p class="text-muted small">
                                Use este playbook para padronizar as etapas de contato (D0, D2, D7, etc.) e conectar com Remarketing e Matching.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-transparent border-bottom">
                                <h3 class="h6 mb-0">Etapas do playbook</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php if (!$etapasSelecionadas): ?>
                                        <div class="list-group-item text-muted small">
                                            Nenhuma etapa cadastrada. Use o formulário ao lado para criar D0, D2, D7...
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($etapasSelecionadas as $et): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <div class="fw-semibold">
                                                            <?php echo (int)$et['ordem']; ?>. <?php echo htmlspecialchars($et['titulo']); ?>
                                                        </div>
                                                        <div class="small text-muted">
                                                            Offset: <?php echo (int)$et['offset_dias']; ?> dia(s)
                                                            · Canal: <?php echo htmlspecialchars($et['canal']); ?>
                                                        </div>
                                                        <?php if (!empty($et['descricao'])): ?>
                                                            <div class="small mt-1">
                                                                <?php echo nl2br(htmlspecialchars($et['descricao'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($et['template_mensagem'])): ?>
                                                            <details class="small mt-2">
                                                                <summary class="text-primary">Ver template de mensagem</summary>
                                                                <pre class="mt-1 small bg-dark text-light p-2 rounded"><?php echo htmlspecialchars($et['template_mensagem']); ?></pre>
                                                            </details>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-transparent border-bottom">
                                <h3 class="h6 mb-0">Nova etapa</h3>
                            </div>
                            <div class="card-body">
                                <form method="post" class="vstack gap-2">
                                    <input type="hidden" name="acao" value="criar_etapa">
                                    <input type="hidden" name="playbook_id" value="<?php echo (int)$playbookSelecionado['id']; ?>">

                                    <div class="mb-2">
                                        <label class="form-label">Título</label>
                                        <input type="text" name="titulo" class="form-control" required maxlength="255" placeholder="Ex.: D0 - Primeiro contato, D2 - Follow-up, D7 - Oferta final">
                                    </div>

                                    <div class="row">
                                        <div class="col-6 mb-2">
                                            <label class="form-label">Offset (dias)</label>
                                            <input type="number" name="offset_dias" class="form-control" min="0" value="0">
                                        </div>
                                        <div class="col-6 mb-2">
                                            <label class="form-label">Canal</label>
                                            <select name="canal" class="form-select">
                                                <option value="whatsapp">WhatsApp</option>
                                                <option value="telefone">Telefone</option>
                                                <option value="email">E-mail</option>
                                                <option value="outro">Outro</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Descrição interna</label>
                                        <textarea name="descricao" class="form-control" rows="2" placeholder="Orientações para o operador: scripts, objeções, etc."></textarea>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label">Template de mensagem</label>
                                        <textarea name="template_mensagem" class="form-control" rows="4" placeholder="Texto sugerido para WhatsApp / e-mail. Variáveis como {{nome}} podem ser preenchidas manualmente no envio."></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">Adicionar etapa</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    Selecione um playbook na coluna da esquerda para ver e editar as etapas.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
