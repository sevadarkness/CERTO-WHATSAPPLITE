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

function parse_datetime_local(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    if (!$dt) {
        return null;
    }
    return $dt->format('Y-m-d H:i:s');
}

// Processa criação/atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'criar_compromisso') {
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descricao = trim((string)($_POST['descricao'] ?? ''));
            $leadId = isset($_POST['lead_id']) ? (int)$_POST['lead_id'] : 0;
            $dataHoraInicioStr = parse_datetime_local((string)($_POST['data_hora_inicio'] ?? ''));
            $dataHoraFimStr = parse_datetime_local((string)($_POST['data_hora_fim'] ?? ''));
            $origem = trim((string)($_POST['origem'] ?? ''));
            $canal = $_POST['canal'] ?? 'online';
            $local = trim((string)($_POST['local'] ?? ''));
            $urlExterna = trim((string)($_POST['url_externa'] ?? ''));

            if ($titulo === '' || !$dataHoraInicioStr) {
                $mensagens[] = array('tipo' => 'danger', 'texto' => 'Título e data/hora inicial são obrigatórios.');
            } else {
                $stmt = (new \RedeAlabama\Repositories\Screens\AgendaRepository($pdo))->prepare_1748();
                $stmt->execute(array(
                    ':usuario_id'  => $usuarioId,
                    ':lead_id'     => $leadId > 0 ? $leadId : null,
                    ':titulo'      => $titulo,
                    ':descricao'   => $descricao !== '' ? $descricao : null,
                    ':inicio'      => $dataHoraInicioStr,
                    ':fim'         => $dataHoraFimStr,
                    ':origem'      => $origem !== '' ? $origem : null,
                    ':canal'       => $canal,
                    ':local'       => $local !== '' ? $local : null,
                    ':url_externa' => $urlExterna !== '' ? $urlExterna : null,
                ));

                $novoId = (int)$pdo->lastInsertId();
                log_app_event('agenda', 'compromisso_criado', array(
                    'compromisso_id' => $novoId,
                    'usuario_id'     => $usuarioId,
                ));
                header('Location: agenda.php');
                exit;
            }
        } elseif ($acao === 'alterar_status') {
            $compromissoId = isset($_POST['compromisso_id']) ? (int)$_POST['compromisso_id'] : 0;
            $novoStatus = $_POST['status'] ?? '';

            $statusPermitidos = array('agendado','confirmado','concluido','cancelado','no_show');
            if ($compromissoId > 0 && in_array($novoStatus, $statusPermitidos, true)) {
                // Restrição: vendedor só pode alterar seus próprios compromissos
                if ($nivelAcesso === 'Vendedor') {
                    $stmt = (new \RedeAlabama\Repositories\Screens\AgendaRepository($pdo))->prepare_3681();
                    $stmt->execute(array(':status' => $novoStatus, ':id' => $compromissoId, ':uid' => $usuarioId));
                } else {
                    $stmt = (new \RedeAlabama\Repositories\Screens\AgendaRepository($pdo))->prepare_3953();
                    $stmt->execute(array(':status' => $novoStatus, ':id' => $compromissoId));
                }

                log_app_event('agenda', 'compromisso_status_alterado', array(
                    'compromisso_id' => $compromissoId,
                    'status'         => $novoStatus,
                    'usuario_id'     => $usuarioId,
                ));
                header('Location: agenda.php');
                exit;
            }
        }
    } catch (Throwable $e) {
        $mensagens[] = array('tipo' => 'danger', 'texto' => 'Erro ao processar agenda: ' . htmlspecialchars($e->getMessage()));
        error_log('Erro em agenda.php: ' . $e->getMessage());
    }
}

// Filtro de período
$hoje = new DateTimeImmutable('today');
$inicioPadrao = $hoje->format('Y-m-d');
$fimPadrao = $hoje->modify('+7 days')->format('Y-m-d');

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

// Limites em formato datetime (início do dia / fim do dia)
$dtInicioSql = $inicioFiltro->format('Y-m-d 00:00:00');
$dtFimSql = $fimFiltro->format('Y-m-d 23:59:59');

// Filtro de vendedor (apenas gerente/admin podem escolher)
$vendedorFiltro = 0;
$vendedores = array();

if ($nivelAcesso === 'Administrador' || $nivelAcesso === 'Gerente') {
    $vendedorFiltro = isset($_GET['vendedor_id']) ? (int)$_GET['vendedor_id'] : 0;
    $stmtVend = (new \RedeAlabama\Repositories\Screens\AgendaRepository($pdo))->query_5447();
    $vendedores = $stmtVend->fetchAll(PDO::FETCH_ASSOC);
}

// Monta consulta
$where = array("a.data_hora_inicio BETWEEN :ini AND :fim");
$params = array(':ini' => $dtInicioSql, ':fim' => $dtFimSql);

if ($nivelAcesso === 'Vendedor') {
    $where[] = "a.usuario_id = :uid";
    $params[':uid'] = $usuarioId;
} elseif ($vendedorFiltro > 0) {
    $where[] = "a.usuario_id = :uid";
    $params[':uid'] = $vendedorFiltro;
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT a.*, u.nome AS usuario_nome, l.nome_cliente, l.telefone_cliente
    FROM agenda_compromissos a
    JOIN usuarios u ON u.id = a.usuario_id
    LEFT JOIN leads l ON l.id = a.lead_id
    WHERE {$whereSql}
    ORDER BY a.data_hora_inicio ASC
";
$stmtAg = $pdo->prepare($sql);
$stmtAg->execute($params);
$compromissos = $stmtAg->fetchAll(PDO::FETCH_ASSOC);

// Métricas simples de produtividade: total por status
$metricasStatus = array();
foreach ($compromissos as $c) {
    $st = $c['status'];
    if (!isset($metricasStatus[$st])) {
        $metricasStatus[$st] = 0;
    }
    $metricasStatus[$st]++;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda &amp; Compromissos - Rede Alabama</title>
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
            <h1 class="h3 mb-1">Agenda &amp; Compromissos</h1>
            <p class="text-muted mb-0">
                Visão unificada de reuniões, retornos e promessas de contato – inspirada em ferramentas como Calendly.
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
                    <h2 class="h6 mb-0">Novo compromisso</h2>
                </div>
                <div class="card-body">
                    <form method="post" class="vstack gap-2">
                        <input type="hidden" name="acao" value="criar_compromisso">

                        <div class="mb-2">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" required maxlength="255" placeholder="Ex.: Retorno WhatsApp, Reunião de proposta, Onboarding">
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Lead (ID opcional)</label>
                            <input type="number" name="lead_id" class="form-control" min="0" placeholder="ID do lead se já estiver na fila">
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Início</label>
                            <input type="datetime-local" name="data_hora_inicio" class="form-control" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Fim (opcional)</label>
                            <input type="datetime-local" name="data_hora_fim" class="form-control">
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Canal</label>
                            <select name="canal" class="form-select">
                                <option value="online">Online</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="telefone">Telefone</option>
                                <option value="presencial">Presencial</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Origem</label>
                            <input type="text" name="origem" class="form-control" placeholder="Ex.: Landing page, Indicação, Instagram">
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Local / link</label>
                            <input type="text" name="local" class="form-control" placeholder="Ex.: Link da reunião, loja física...">
                        </div>

                        <div class="mb-2">
                            <label class="form-label">URL externa (opcional)</label>
                            <input type="url" name="url_externa" class="form-control" placeholder="Ex.: link Calendly ou outro agendador">
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Descrição interna</label>
                            <textarea name="descricao" class="form-control" rows="3" placeholder="Contexto para o compromisso, objeções, histórico..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Agendar</button>
                    </form>
                </div>
            </div>

            <?php if ($metricasStatus): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent border-bottom">
                        <h2 class="h6 mb-0">Resumo por status</h2>
                    </div>
                    <div class="card-body">
                        <?php foreach ($metricasStatus as $st => $qtd): ?>
                            <div class="d-flex justify-content-between small mb-1">
                                <span><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $st))); ?></span>
                                <span class="fw-semibold"><?php echo (int)$qtd; ?></span>
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
                            <input form="filtroAgenda" type="date" name="data_inicio" class="form-control" value="<?php echo htmlspecialchars($inicioFiltro->format('Y-m-d')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fim</label>
                            <input form="filtroAgenda" type="date" name="data_fim" class="form-control" value="<?php echo htmlspecialchars($fimFiltro->format('Y-m-d')); ?>">
                        </div>
                        <?php if ($nivelAcesso === 'Administrador' || $nivelAcesso === 'Gerente'): ?>
                            <div class="col-md-3">
                                <label class="form-label">Vendedor</label>
                                <select form="filtroAgenda" name="vendedor_id" class="form-select">
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
                            <form id="filtroAgenda" method="get" class="d-grid">
                                <button type="submit" class="btn btn-outline-light mt-3">Filtrar</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!$compromissos): ?>
                        <div class="p-3 text-muted">Nenhum compromisso no período selecionado.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped table-hover mb-0 align-middle">
                                <thead>
                                <tr>
                                    <th>Início</th>
                                    <th>Título</th>
                                    <th>Lead</th>
                                    <th>Responsável</th>
                                    <th>Canal</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($compromissos as $c): ?>
                                    <tr>
                                        <td class="small">
                                            <?php echo htmlspecialchars((new DateTimeImmutable($c['data_hora_inicio']))->format('d/m/Y H:i')); ?>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($c['titulo']); ?></div>
                                            <?php if (!empty($c['descricao'])): ?>
                                                <div class="small text-muted"><?php echo nl2br(htmlspecialchars($c['descricao'])); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($c['local'])): ?>
                                                <div class="small text-info mt-1">Local: <?php echo htmlspecialchars($c['local']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($c['url_externa'])): ?>
                                                <div class="small mt-1">
                                                    <a href="<?php echo htmlspecialchars($c['url_externa']); ?>" target="_blank" rel="noopener noreferrer">Abrir link externo</a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <?php
                                            if (!empty($c['lead_id'])) {
                                                echo 'Lead #' . (int)$c['lead_id'] . '<br>';
                                            }
                                            if (!empty($c['nome_cliente'])) {
                                                echo htmlspecialchars($c['nome_cliente']);
                                            } elseif (!empty($c['cliente_nome'])) {
                                                echo htmlspecialchars($c['cliente_nome']);
                                            }
                                            if (!empty($c['telefone_cliente'])) {
                                                echo '<br><span class="text-muted">' . htmlspecialchars($c['telefone_cliente']) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="small">
                                            <?php echo htmlspecialchars($c['usuario_nome']); ?>
                                        </td>
                                        <td class="small">
                                            <?php echo htmlspecialchars(ucfirst($c['canal'])); ?>
                                            <?php if (!empty($c['origem'])): ?>
                                                <br><span class="text-muted small">Origem: <?php echo htmlspecialchars($c['origem']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <span class="badge bg-<?php
                                                switch ($c['status']) {
                                                    case 'agendado': echo 'info'; break;
                                                    case 'confirmado': echo 'primary'; break;
                                                    case 'concluido': echo 'success'; break;
                                                    case 'cancelado': echo 'secondary'; break;
                                                    case 'no_show': echo 'danger'; break;
                                                    default: echo 'secondary'; break;
                                                }
                                            ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $c['status']))); ?>
                                            </span>
                                        </td>
                                        <td class="small">
                                            <form method="post" class="d-flex flex-column gap-1">
                                                <input type="hidden" name="acao" value="alterar_status">
                                                <input type="hidden" name="compromisso_id" value="<?php echo (int)$c['id']; ?>">
                                                <select name="status" class="form-select form-select-sm mb-1">
                                                    <?php
                                                    $statusOpts = array('agendado','confirmado','concluido','cancelado','no_show');
                                                    foreach ($statusOpts as $st):
                                                    ?>
                                                        <option value="<?php echo $st; ?>" <?php echo $c['status'] === $st ? 'selected' : ''; ?>>
                                                            <?php echo ucfirst(str_replace('_', ' ', $st)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-outline-light">Atualizar</button>
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
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
