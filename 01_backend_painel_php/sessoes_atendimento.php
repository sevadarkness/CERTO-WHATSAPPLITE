<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



require_once __DIR__ . '/rbac.php';
require_role(array('Administrador', 'Gerente', 'Vendedor'));

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

$user = current_user();
$usuarioId = $user['id'] ?? null;
$usuarioNome = $user['nome'] ?? '';
$nivelAcesso = $user['nivel_acesso'] ?? '';

if (!$usuarioId) {
    header('Location: login.php');
    exit;
}

$mensagens = array();

// Busca sessão aberta (se existir) para o usuário logado
$stmtSessaoAberta = (new \RedeAlabama\Repositories\Screens\SessoesAtendimentoRepository($pdo))->prepare_517();
$stmtSessaoAberta->execute(array(':uid' => $usuarioId));
$sessaoAberta = $stmtSessaoAberta->fetch(PDO::FETCH_ASSOC) ?: null;

// Processa ações de iniciar / encerrar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'iniciar') {
            if ($sessaoAberta) {
                $mensagens[] = array('tipo' => 'warning', 'texto' => 'Já existe uma sessão em andamento. Encerre antes de iniciar outra.');
            } else {
                $leadId = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
                $canal = $_POST['canal'] ?? 'whatsapp';
                $tipo = $_POST['tipo'] ?? 'prospeccao';
                $obs = trim((string)($_POST['observacoes'] ?? ''));

                $stmt = (new \RedeAlabama\Repositories\Screens\SessoesAtendimentoRepository($pdo))->prepare_1431();
                $stmt->execute(array(
                    ':usuario_id'   => $usuarioId,
                    ':lead_id'      => $leadId > 0 ? $leadId : null,
                    ':canal'        => $canal,
                    ':tipo'         => $tipo,
                    ':observacoes'  => $obs !== '' ? $obs : null,
                ));

                log_app_event('sessoes', 'sessao_iniciada', array(
                    'usuario_id' => $usuarioId,
                    'lead_id'    => $leadId,
                    'canal'      => $canal,
                    'tipo'       => $tipo,
                ));

                header('Location: sessoes_atendimento.php');
                exit;
            }
        } elseif ($acao === 'encerrar') {
            if (!$sessaoAberta) {
                $mensagens[] = array('tipo' => 'warning', 'texto' => 'Não há sessão em andamento para encerrar.');
            } else {
                $sessaoId = (int)$sessaoAberta['id'];
                $stmt = (new \RedeAlabama\Repositories\Screens\SessoesAtendimentoRepository($pdo))->prepare_2655();
                $stmt->execute(array(':id' => $sessaoId, ':uid' => $usuarioId));

                log_app_event('sessoes', 'sessao_encerrada', array(
                    'usuario_id' => $usuarioId,
                    'sessao_id'  => $sessaoId,
                ));

                header('Location: sessoes_atendimento.php');
                exit;
            }
        }
    } catch (Throwable $e) {
        $mensagens[] = array('tipo' => 'danger', 'texto' => 'Erro ao processar sessão: ' . htmlspecialchars($e->getMessage()));
        error_log('Erro em sessoes_atendimento.php: ' . $e->getMessage());
    }
}

// Filtros para listagem e métricas
$hoje = new DateTimeImmutable('today');
$inicioPadrao = $hoje->modify('-7 days')->format('Y-m-d');
$fimPadrao = $hoje->format('Y-m-d');

$dataInicio = $_GET['data_inicio'] ?? $inicioPadrao;
$dataFim = $_GET['data_fim'] ?? $fimPadrao;

try {
    $inicioFiltro = new DateTimeImmutable($dataInicio);
} catch (Throwable $e) {
    $inicioFiltro = new DateTimeImmutable($inicioPadrao);
}
try {
    $fimFiltro = new DateTimeImmutable($dataFim);
} catch (Throwable $e) {
    $fimFiltro = new DateTimeImmutable($fimPadrao);
}

$dtInicioSql = $inicioFiltro->format('Y-m-d 00:00:00');
$dtFimSql = $fimFiltro->format('Y-m-d 23:59:59');

$vendedorFiltro = 0;
$vendedores = array();

if ($nivelAcesso === 'Administrador' || $nivelAcesso === 'Gerente') {
    $vendedorFiltro = isset($_GET['vendedor_id']) ? (int)$_GET['vendedor_id'] : 0;
    $stmtVend = (new \RedeAlabama\Repositories\Screens\SessoesAtendimentoRepository($pdo))->query_4114();
    $vendedores = $stmtVend->fetchAll(PDO::FETCH_ASSOC);
}

// Monta cláusulas de filtro
$where = array("s.inicio BETWEEN :ini AND :fim");
$params = array(':ini' => $dtInicioSql, ':fim' => $dtFimSql);

if ($nivelAcesso === 'Vendedor') {
    $where[] = "s.usuario_id = :uid";
    $params[':uid'] = $usuarioId;
} elseif ($vendedorFiltro > 0) {
    $where[] = "s.usuario_id = :uid";
    $params[':uid'] = $vendedorFiltro;
}

$whereSql = implode(' AND ', $where);

// Listagem de sessões
$sql = "
    SELECT s.*, u.nome AS usuario_nome, l.nome_cliente, l.telefone_cliente
    FROM sessoes_atendimento s
    JOIN usuarios u ON u.id = s.usuario_id
    LEFT JOIN leads l ON l.id = s.lead_id
    WHERE {$whereSql}
    ORDER BY s.inicio DESC
";
$stmtSess = $pdo->prepare($sql);
$stmtSess->execute($params);
$sessoes = $stmtSess->fetchAll(PDO::FETCH_ASSOC);

// Métricas agregadas por usuário
$sqlMetricas = "
    SELECT s.usuario_id, u.nome AS usuario_nome,
           COUNT(*) AS total_sessoes,
           SUM(COALESCE(s.duracao_segundos, 0)) AS total_segundos
    FROM sessoes_atendimento s
    JOIN usuarios u ON u.id = s.usuario_id
    WHERE {$whereSql}
    GROUP BY s.usuario_id, u.nome
    ORDER BY total_segundos DESC
";
$stmtMet = $pdo->prepare($sqlMetricas);
$stmtMet->execute($params);
$metricas = $stmtMet->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tempo de Atendimento - Rede Alabama</title>
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
            <h1 class="h3 mb-1">Tempo de Atendimento</h1>
            <p class="text-muted mb-0">
                Controle de sessões de atendimento por vendedor – inspirado em ferramentas de time tracking como Toggl.
            </p>
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
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-transparent border-bottom">
                    <h2 class="h6 mb-0">Sessão atual</h2>
                </div>
                <div class="card-body">
                    <?php if ($sessaoAberta): ?>
                        <p class="mb-2">
                            Sessão iniciada em:<br>
                            <strong><?php echo htmlspecialchars((new DateTimeImmutable($sessaoAberta['inicio']))->format('d/m/Y H:i:s')); ?></strong>
                        </p>
                        <?php if (!empty($sessaoAberta['lead_id'])): ?>
                            <p class="small text-muted mb-1">
                                Lead vinculado: #<?php echo (int)$sessaoAberta['lead_id']; ?>
                            </p>
                        <?php endif; ?>
                        <form method="post" class="d-grid mt-3">
                            <input type="hidden" name="acao" value="encerrar">
                            <button type="submit" class="btn btn-danger">Encerrar sessão</button>
                        </form>
                    <?php else: ?>
                        <p class="small text-muted">
                            Não há sessão em andamento.
                            Use o formulário abaixo para iniciar uma nova sessão vinculada (ou não) a um lead.
                        </p>
                        <form method="post" class="vstack gap-2">
                            <input type="hidden" name="acao" value="iniciar">
                            <div class="mb-2">
                                <label class="form-label">Lead (ID opcional)</label>
                                <input type="number" name="lead_id" class="form-control" min="0" placeholder="ID do lead em atendimento">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Canal</label>
                                <select name="canal" class="form-select">
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="telefone">Telefone</option>
                                    <option value="email">E-mail</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="interno">Interno</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="prospeccao">Prospecção</option>
                                    <option value="suporte">Suporte</option>
                                    <option value="pos_venda">Pós-venda</option>
                                    <option value="interno">Interno</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="3" placeholder="Ex.: campanha, contexto, objeções..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Iniciar sessão</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($metricas): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent border-bottom">
                        <h2 class="h6 mb-0">Produtividade no período</h2>
                    </div>
                    <div class="card-body">
                        <?php foreach ($metricas as $m): ?>
                            <?php
                            $horas = $m['total_segundos'] !== null ? floor((int)$m['total_segundos'] / 3600) : 0;
                            $minutos = $m['total_segundos'] !== null ? floor(((int)$m['total_segundos'] % 3600) / 60) : 0;
                            ?>
                            <div class="mb-2 small">
                                <div class="fw-semibold"><?php echo htmlspecialchars($m['usuario_nome']); ?></div>
                                <div class="d-flex justify-content-between">
                                    <span>Sessões: <?php echo (int)$m['total_sessoes']; ?></span>
                                    <span>Tempo: <?php echo sprintf('%02dh %02dmin', $horas, $minutos); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-transparent border-bottom">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Início</label>
                            <input form="filtroSessoes" type="date" name="data_inicio" class="form-control" value="<?php echo htmlspecialchars($inicioFiltro->format('Y-m-d')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fim</label>
                            <input form="filtroSessoes" type="date" name="data_fim" class="form-control" value="<?php echo htmlspecialchars($fimFiltro->format('Y-m-d')); ?>">
                        </div>
                        <?php if ($nivelAcesso === 'Administrador' || $nivelAcesso === 'Gerente'): ?>
                            <div class="col-md-3">
                                <label class="form-label">Vendedor</label>
                                <select form="filtroSessoes" name="vendedor_id" class="form-select">
                                    <option value="0">Todos</option>
                                    <?php foreach ($vendedores as $v): ?>
                                        <option value="<?php echo (int)$v['id']; ?>" <?php echo $vendedorFiltro === (int)$v['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($v['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <form id="filtroSessoes" method="get" class="d-grid">
                                <button type="submit" class="btn btn-outline-light mt-3">Filtrar</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!$sessoes): ?>
                        <div class="p-3 text-muted">Nenhuma sessão registrada no período selecionado.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped table-hover mb-0 align-middle">
                                <thead>
                                <tr>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Vendedor</th>
                                    <th>Lead</th>
                                    <th>Canal</th>
                                    <th>Tipo</th>
                                    <th>Duração</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($sessoes as $s): ?>
                                    <?php
                                    $durSeg = $s['duracao_segundos'] !== null ? (int)$s['duracao_segundos'] : null;
                                    $durFmt = '—';
                                    if ($durSeg !== null && $durSeg >= 0) {
                                        $h = floor($durSeg / 3600);
                                        $m = floor(($durSeg % 3600) / 60);
                                        $durFmt = sprintf('%02dh %02dmin', $h, $m);
                                    }
                                    ?>
                                    <tr>
                                        <td class="small">
                                            <?php echo htmlspecialchars((new DateTimeImmutable($s['inicio']))->format('d/m/Y H:i:s')); ?>
                                        </td>
                                        <td class="small">
                                            <?php
                                            if (!empty($s['fim'])) {
                                                echo htmlspecialchars((new DateTimeImmutable($s['fim']))->format('d/m/Y H:i:s'));
                                            } else {
                                                echo '<span class="text-warning">Em andamento</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="small">
                                            <?php echo htmlspecialchars($s['usuario_nome']); ?>
                                        </td>
                                        <td class="small">
                                            <?php
                                            if (!empty($s['lead_id'])) {
                                                echo '#'.(int)$s['lead_id'];
                                                if (!empty($s['nome_cliente'])) {
                                                    echo ' - ' . htmlspecialchars($s['nome_cliente']);
                                                }
                                                if (!empty($s['telefone_cliente'])) {
                                                    echo '<br><span class="text-muted">' . htmlspecialchars($s['telefone_cliente']) . '</span>';
                                                }
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td class="small">
                                            <?php echo htmlspecialchars(ucfirst($s['canal'])); ?>
                                        </td>
                                        <td class="small">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $s['tipo']))); ?>
                                        </td>
                                        <td class="small">
                                            <?php echo $durFmt; ?>
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
    </div>
</div>

</body>
</html>
