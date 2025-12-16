<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';
include 'menu_navegacao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Filtros de Data
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01'); // Início do mês atual
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d'); // Data atual

// Consulta os prejuízos no período selecionado
$query_prejuizos = "
    SELECT 
        p.id,
        p.produto_id,
        p.sabor_id,
        p.vendedor_id,
        p.motivo,
        p.valor,
        p.data_registro,
        pr.nome AS produto_nome,
        s.sabor AS sabor_nome,
        u.nome AS vendedor_nome
    FROM prejuizo p
    JOIN produtos pr ON p.produto_id = pr.id
    LEFT JOIN sabores s ON p.sabor_id = s.id
    JOIN usuarios u ON p.vendedor_id = u.id
    WHERE DATE(p.data_registro) BETWEEN ? AND ?
    ORDER BY p.data_registro DESC
";
$stmt_prejuizos = $pdo->prepare($query_prejuizos);
$stmt_prejuizos->execute([$data_inicio, $data_fim]);
$prejuizos = $stmt_prejuizos->fetchAll();

// Valor total do prejuízo no período
$query_total_prejuizo = "
    SELECT SUM(valor) AS total_prejuizo
    FROM prejuizo
    WHERE DATE(data_registro) BETWEEN ? AND ?
";
$stmt_total_prejuizo = $pdo->prepare($query_total_prejuizo);
$stmt_total_prejuizo->execute([$data_inicio, $data_fim]);
$total_prejuizo = $stmt_total_prejuizo->fetch(PDO::FETCH_ASSOC)['total_prejuizo'] ?? 0;

// Valor total do prejuízo por vendedor
$query_prejuizo_por_vendedor = "
    SELECT 
        u.nome AS vendedor_nome,
        SUM(p.valor) AS total_prejuizo
    FROM prejuizo p
    JOIN usuarios u ON p.vendedor_id = u.id
    WHERE DATE(p.data_registro) BETWEEN ? AND ?
    GROUP BY u.id
    ORDER BY total_prejuizo DESC
";
$stmt_prejuizo_por_vendedor = $pdo->prepare($query_prejuizo_por_vendedor);
$stmt_prejuizo_por_vendedor->execute([$data_inicio, $data_fim]);
$prejuizo_por_vendedor = $stmt_prejuizo_por_vendedor->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Prejuízo - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-header {
            padding: 1.5rem;
            border-radius: var(--al-radius-lg);
            margin-bottom: 1.5rem;
        }

        .data-table {
            border-radius: var(--al-radius-lg);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .filter-card {
            padding: 1rem;
            border-radius: var(--al-radius-lg);
        }

        .bg-danger-light {
            background-color: rgba(239, 68, 68, 0.1);
        }
    </style>
</head>
<body class="al-body">
<div class="container-fluid mt-4">
    <!-- Cabeçalho -->
    <div class="dashboard-header text-center">
        <h2 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Relatório de Prejuízo</h2>
    </div>

    <!-- Filtros -->
    <div class="filter-card mb-4">
        <form method="GET">
            <div class="row align-items-end">
                <div class="col-md-3 mb-3">
                    <label>Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label>Data Final</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-3 mb-3">
                    <button type="button" class="btn btn-success btn-block" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Exportar
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Cards Resumo -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card-custom bg-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Prejuízo Total</small>
                        <h4 class="mb-0">R$ <?= number_format($total_prejuizo, 2, ',', '.') ?></h4>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card-custom bg-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Total de Registros</small>
                        <h4 class="mb-0"><?= count($prejuizos) ?></h4>
                    </div>
                    <i class="fas fa-list fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Prejuízo por Vendedor -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card-custom bg-white p-3">
                <h5><i class="fas fa-chart-bar"></i> Prejuízo por Vendedor</h5>
                <canvas id="prejuizoPorVendedorChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tabela de Detalhes do Prejuízo -->
    <div class="data-table mt-4">
        <div class="p-3 bg-danger text-white">
            <h5><i class="fas fa-file-invoice-dollar"></i> Detalhes do Prejuízo</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Vendedor</th>
                        <th>Produto</th>
                        <th>Sabor</th>
                        <th>Motivo</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prejuizos as $prejuizo): ?>
                    <tr class="bg-danger-light">
                        <td><?= date('d/m/Y H:i', strtotime($prejuizo['data_registro'])) ?></td>
                        <td><?= htmlspecialchars($prejuizo['vendedor_nome']) ?></td>
                        <td><?= htmlspecialchars($prejuizo['produto_nome']) ?></td>
                        <td><?= htmlspecialchars($prejuizo['sabor_nome'] ?? 'Sem sabor') ?></td>
                        <td><?= htmlspecialchars($prejuizo['motivo']) ?></td>
                        <td class="text-right">R$ <?= number_format($prejuizo['valor'], 2, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script <?php echo alabama_csp_nonce_attr(); ?>>
// Gráfico de Prejuízo por Vendedor
const ctx = document.getElementById('prejuizoPorVendedorChart').getContext('2d');
const prejuizoPorVendedorChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($prejuizo_por_vendedor, 'vendedor_nome')) ?>,
        datasets: [{
            label: 'Prejuízo por Vendedor',
            data: <?= json_encode(array_column($prejuizo_por_vendedor, 'total_prejuizo')) ?>,
            backgroundColor: 'rgba(231, 76, 60, 0.2)',
            borderColor: 'rgba(231, 76, 60, 1)',
            borderWidth: 1
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

function exportToExcel() {
    alert('Funcionalidade de exportação em desenvolvimento!');
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>