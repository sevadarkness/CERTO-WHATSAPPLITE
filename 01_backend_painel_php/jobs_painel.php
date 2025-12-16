<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

// Filtros simples
$status = $_GET['status'] ?? '';
$tipo   = $_GET['tipo'] ?? '';

$where = [];
$params = [];

if ($status !== '') {
    $where[] = 'j.status = :status';
    $params[':status'] = $status;
}
if ($tipo !== '') {
    $where[] = 'j.tipo = :tipo';
    $params[':tipo'] = $tipo;
}

$sql = "SELECT j.*, 
               (SELECT COUNT(*) FROM jobs_logs l WHERE l.job_id = j.id) AS qtd_logs
        FROM jobs_agendados j";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY j.criado_em DESC, j.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Carrega últimos logs
$sqlLogs = "SELECT l.id, l.job_id, l.tipo, l.status, l.mensagem, l.criado_em
            FROM jobs_logs l
            ORDER BY l.id DESC
            LIMIT 200";
$stmtLogs = $pdo->query($sqlLogs);
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Jobs / Automação - Supremacy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="al-body">
<div class="container mt-4">
    <h1 class="mb-3"><i class="fas fa-robot"></i> Jobs / Automação</h1>

    <form class="row g-3 mb-3" method="get">
        <div class="col-md-3">
            <label for="status" class="form-label">Status</label>
            <select name="status" id="status" class="form-select">
                <option value="">(todos)</option>
                <?php
                $optsStatus = ['pendente' => 'Pendente', 'concluido' => 'Concluído', 'falhou' => 'Falhou'];
                foreach ($optsStatus as $k => $label):
                    $sel = ($status === $k) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($k) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
                endforeach;
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="tipo" class="form-label">Tipo de Job</label>
            <input type="text" class="form-control" id="tipo" name="tipo" value="<?php echo htmlspecialchars($tipo); ?>">
        </div>
        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="jobs_painel.php" class="btn btn-secondary">Limpar</a>
        </div>
    </form>

    <div class="row">
        <div class="col-md-7 mb-3">
            <div class="card bg-secondary text-light">
                <div class="card-header">
                    Jobs agendados (últimos 200)
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-dark table-striped table-sm align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Tentativas</th>
                                <th>Agendado para</th>
                                <th>Executado em</th>
                                <th>Logs</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$jobs): ?>
                                <tr><td colspan="7" class="text-center text-muted">Nenhum job encontrado.</td></tr>
                            <?php else: ?>
                                <?php foreach ($jobs as $j): ?>
                                    <tr>
                                        <td><?php echo (int)$j['id']; ?></td>
                                        <td><?php echo htmlspecialchars($j['tipo']); ?></td>
                                        <td><?php echo htmlspecialchars($j['status']); ?></td>
                                        <td><?php echo (int)$j['tentativas'] . ' / ' . (int)$j['max_tentativas']; ?></td>
                                        <td><?php echo htmlspecialchars($j['agendado_para'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($j['executado_em'] ?? ''); ?></td>
                                        <td><?php echo (int)$j['qtd_logs']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-5 mb-3">
            <div class="card bg-secondary text-light">
                <div class="card-header">
                    Logs recentes
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-dark table-striped table-sm align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Job</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Mensagem</th>
                                <th>Data</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$logs): ?>
                                <tr><td colspan="6" class="text-center text-muted">Nenhum log.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td><?php echo (int)$l['id']; ?></td>
                                        <td><?php echo htmlspecialchars((string)($l['job_id'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($l['tipo']); ?></td>
                                        <td><?php echo htmlspecialchars($l['status']); ?></td>
                                        <td style="max-width: 260px;">
                                            <span class="d-inline-block text-truncate" style="max-width: 260px;">
                                                <?php echo htmlspecialchars($l['mensagem'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($l['criado_em'] ?? ''); ?></td>
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
