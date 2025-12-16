<?php
declare(strict_types=1);

require_once __DIR__ . '/../../session_bootstrap.php';
require_once __DIR__ . '/../../rbac.php';
require_role(['Vendedor']);
require_once __DIR__ . '/../../db_config.php';

$usuarioId = $_SESSION['usuario_id'] ?? null;
if (!$usuarioId) {
    header('Location: ../../login.php');
    exit;
}

// KPI simples baseado na tabela vendas: total do dia, do mês, e ranking básico
$hoje = (new DateTimeImmutable('today'))->format('Y-m-d');
$inicioMes = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');

// Totais do vendedor
$sqlDia = "SELECT COALESCE(SUM(valor),0) AS total_dia, COUNT(*) AS qtd_dia
           FROM vendas
           WHERE vendedor_id = :vid AND DATE(data) = :hoje";
$sqlMes = "SELECT COALESCE(SUM(valor),0) AS total_mes, COUNT(*) AS qtd_mes
           FROM vendas
           WHERE vendedor_id = :vid AND DATE(data) >= :inicioMes";

$stmt = $pdo->prepare($sqlDia);
$stmt->execute([':vid' => $usuarioId, ':hoje' => $hoje]);
$dia = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_dia' => 0, 'qtd_dia' => 0];

$stmt = $pdo->prepare($sqlMes);
$stmt->execute([':vid' => $usuarioId, ':inicioMes' => $inicioMes]);
$mes = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_mes' => 0, 'qtd_mes' => 0];

// Últimas vendas do vendedor
$stmt = $pdo->prepare("SELECT data, valor, custo, cliente_nome 
                       FROM vendas
                       WHERE vendedor_id = :vid
                       ORDER BY data DESC
                       LIMIT 20");
$stmt->execute([':vid' => $usuarioId]);
$ultimas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Painel do Vendedor - KPIs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">📦 Painel do Vendedor</h2>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Hoje</h5>
                    <p class="card-text mb-1"><strong>Vendas:</strong> <?php echo (int)$dia['qtd_dia']; ?></p>
                    <p class="card-text mb-0"><strong>Total:</strong> R$<?php echo number_format((float)$dia['total_dia'], 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Este mês</h5>
                    <p class="card-text mb-1"><strong>Vendas:</strong> <?php echo (int)$mes['qtd_mes']; ?></p>
                    <p class="card-text mb-0"><strong>Total:</strong> R$<?php echo number_format((float)$mes['total_mes'], 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Ticket médio (mês)</h5>
                    <?php
                    $ticketMedio = ($mes['qtd_mes'] ?? 0) > 0 ? (float)$mes['total_mes'] / (int)$mes['qtd_mes'] : 0.0;
                    ?>
                    <p class="card-text display-6">R$<?php echo number_format($ticketMedio, 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <h4 class="mb-3">Últimas vendas</h4>
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Valor</th>
                        <th>Custo</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$ultimas): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Nenhuma venda registrada ainda.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ultimas as $v): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)$v['data'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string)($v['cliente_nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>R$<?php echo number_format((float)$v['valor'], 2, ',', '.'); ?></td>
                                <td>R$<?php echo number_format((float)($v['custo'] ?? 0), 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
