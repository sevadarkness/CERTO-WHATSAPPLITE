<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';
include 'menu_navegacao.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($usuario['nivel_acesso'] !== 'Gerente' && $usuario['nivel_acesso'] !== 'Administrador') {
    header("Location: acesso_restrito.php");
    exit;
}

// Filtros de Data
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

// Faturamento Bruto
$query_faturamento_bruto = "
    SELECT SUM(v.valor_total) AS faturamento_bruto
    FROM vendas v
    WHERE DATE(v.data_venda) BETWEEN ? AND ?
";
$stmt_faturamento_bruto = $pdo->prepare($query_faturamento_bruto);
$stmt_faturamento_bruto->execute([$data_inicio, $data_fim]);
$faturamento_bruto = $stmt_faturamento_bruto->fetch(PDO::FETCH_ASSOC)['faturamento_bruto'] ?? 0;

// Relatório de Estoque Geral
$query_estoque = "
    SELECT 
        u.nome AS vendedor_nome, 
        SUM(e.quantidade) AS total_estoque,
        SUM(e.quantidade * p.preco) AS valor_total
    FROM estoque_vendedores e
    JOIN usuarios u ON e.vendedor_id = u.id
    JOIN produtos p ON e.produto_id = p.id
    GROUP BY u.id
";
$stmt_estoque = $pdo->prepare($query_estoque);
$stmt_estoque->execute();
$estoque_vendedores = $stmt_estoque->fetchAll(PDO::FETCH_ASSOC);

// Valor total do estoque de todos os vendedores
$query_valor_total_estoque = "
    SELECT 
        SUM(e.quantidade * p.preco) AS valor_total_estoque
    FROM estoque_vendedores e
    JOIN produtos p ON e.produto_id = p.id
";
$stmt_valor_total_estoque = $pdo->prepare($query_valor_total_estoque);
$stmt_valor_total_estoque->execute();
$valor_total_estoque = $stmt_valor_total_estoque->fetch(PDO::FETCH_ASSOC)['valor_total_estoque'] ?? 0;

// Comissões dos Vendedores
$query_comissao = "
    SELECT 
        u.id AS vendedor_id,
        u.nome AS vendedor_nome, 
        COALESCE(SUM(v.valor_total * 0.20), 0) AS comissao
    FROM usuarios u
    LEFT JOIN vendas v ON v.id_vendedor = u.id AND DATE(v.data_venda) BETWEEN ? AND ?
    GROUP BY u.id
";
$stmt_comissao = $pdo->prepare($query_comissao);
$stmt_comissao->execute([$data_inicio, $data_fim]);
$comissoes = $stmt_comissao->fetchAll(PDO::FETCH_ASSOC);

// Última Venda por Vendedor com campo produto_avariado
$query_ultima_venda = "
    SELECT 
        u.nome AS vendedor_nome, 
        p.nome AS produto_nome,
        v.nome_cliente AS cliente_nome,
        v.telefone_cliente AS cliente_telefone,
        v.valor_total AS valor_produto,
        v.data_venda AS ultima_venda,
        v.produto_avariado
    FROM usuarios u
    INNER JOIN vendas v ON v.id_vendedor = u.id
    INNER JOIN produtos p ON v.produto_id = p.id
    WHERE DATE(v.data_venda) BETWEEN ? AND ?
    ORDER BY v.data_venda DESC
    LIMIT 15
";
$stmt_ultima_venda = $pdo->prepare($query_ultima_venda);
$stmt_ultima_venda->execute([$data_inicio, $data_fim]);
$ultimas_vendas = $stmt_ultima_venda->fetchAll(PDO::FETCH_ASSOC);

// Calcular o valor total do prejuízo no período selecionado (tabela prejuizo)
$query_prejuizo_total = "
    SELECT SUM(p.valor) AS prejuizo_total
    FROM prejuizo p
    WHERE DATE(p.data_registro) BETWEEN ? AND ?
";
$stmt_prejuizo_total = $pdo->prepare($query_prejuizo_total);
$stmt_prejuizo_total->execute([$data_inicio, $data_fim]);
$prejuizo_total = $stmt_prejuizo_total->fetch(PDO::FETCH_ASSOC)['prejuizo_total'] ?? 0;

// Comissão do Operador
$comissao_operador = $faturamento_bruto * 0.10;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Vendas - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
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

        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
        }

        .bg-prejuizo {
            background-color: rgba(239, 68, 68, 0.1);
        }

        @media (max-width: 768px) {
            .table-responsive {
                max-height: none;
            }
            
            .data-table {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body class="al-body">
<div class="container-fluid mt-4">
    <!-- Cabeçalho -->
    <div class="dashboard-header text-center text-white">
        <h2 class="mb-0"><i class="fas fa-chart-pie"></i> Relatório Gerencial Completo</h2>
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
        <div class="col-md-3 col-6 mb-4">
            <div class="card-custom bg-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Faturamento</small>
                        <h4 class="mb-0">R$ <?= number_format($faturamento_bruto, 2, ',', '.') ?></h4>
                    </div>
                    <i class="fas fa-wallet fa-2x text-success"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6 mb-4">
            <div class="card-custom bg-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Valor Estoque</small>
                        <h4 class="mb-0">R$ <?= number_format($valor_total_estoque, 2, ',', '.') ?></h4>
                    </div>
                    <i class="fas fa-box-open fa-2x text-primary"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6 mb-4">
            <div class="card-custom bg-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Comissão Vendedores</small>
                        <h4 class="mb-0">R$ <?= number_format(array_sum(array_column($comissoes, 'comissao')), 2, ',', '.') ?></h4>
                    </div>
                    <i class="fas fa-hand-holding-usd fa-2x text-warning"></i>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6 mb-4">
            <div class="card-custom bg-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Comissão Operador</small>
                        <h4 class="mb-0">R$ <?= number_format($comissao_operador, 2, ',', '.') ?></h4>
                    </div>
                    <i class="fas fa-user-tie fa-2x text-danger"></i>
                </div>
            </div>
        </div>

        <!-- Novo Card para Prejuízo Total -->
        <div class="col-md-3 col-6 mb-4">
            <div class="card-custom bg-white p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Prejuízo Total</small>
                        <h4 class="mb-0">R$ <?= number_format($prejuizo_total, 2, ',', '.') ?></h4>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Comissões e Estoque -->
    <div class="row mt-4">
        <!-- Comissões -->
        <div class="col-lg-8 mb-4">
            <div class="data-table">
                <div class="p-3 commission-header text-white">
                    <h5><i class="fas fa-money-check-alt"></i> Comissões por Vendedor (20%) - Período: <?= date('d/m/Y', strtotime($data_inicio)) ?> a <?= date('d/m/Y', strtotime($data_fim)) ?></h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Vendedor</th>
                                <th class="text-right">Comissão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($comissoes as $comissao): ?>
                            <tr>
                                <td><?= htmlspecialchars($comissao['vendedor_nome']) ?></td>
                                <td class="text-right">R$ <?= number_format($comissao['comissao'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Estoque -->
        <div class="col-lg-4 mb-4">
            <div class="data-table">
                <div class="p-3 stock-header text-dark">
                    <h5><i class="fas fa-cubes"></i> Estoque por Vendedor</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Vendedor</th>
                                <th class="text-center">Quantidade</th>
                                <th class="text-right">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estoque_vendedores as $estoque): ?>
                            <tr class="<?= $estoque['total_estoque'] < 10 ? 'table-danger' : '' ?>">
                                <td><?= htmlspecialchars($estoque['vendedor_nome']) ?></td>
                                <td class="text-center">
                                    <?= $estoque['total_estoque'] ?>
                                    <?php if ($estoque['total_estoque'] < 10): ?>
                                        <i class="fas fa-exclamation-triangle text-danger ml-2" title="Estoque baixo!"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">R$ <?= number_format($estoque['valor_total'], 2, ',', '.') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detalhes das Vendas -->
    <div class="data-table mt-4">
        <div class="p-3 sales-header text-white">
            <h5><i class="fas fa-file-invoice-dollar"></i> Detalhes das Vendas</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Vendedor</th>
                        <th>Cliente</th>
                        <th>Telefone</th>
                        <th>Produto</th>
                        <th class="text-right">Valor</th>
                        <th class="text-right">Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimas_vendas as $venda): ?>
                    <tr class="<?= $venda['produto_avariado'] == 1 ? 'bg-prejuizo' : '' ?>">
                        <td><?= htmlspecialchars($venda['vendedor_nome']) ?></td>
                        <td><?= htmlspecialchars($venda['cliente_nome']) ?></td>
                        <td><?= htmlspecialchars($venda['cliente_telefone']) ?></td>
                        <td><?= htmlspecialchars($venda['produto_nome']) ?></td>
                        <td class="text-right">R$ <?= number_format($venda['valor_produto'], 2, ',', '.') ?></td>
                        <td class="text-right"><?= date('d/m/Y H:i', strtotime($venda['ultima_venda'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script <?php echo alabama_csp_nonce_attr(); ?>>
function exportToExcel() {
    // Implementar lógica de exportação para Excel
    alert('Funcionalidade de exportação em desenvolvimento!');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
</body>
</html>