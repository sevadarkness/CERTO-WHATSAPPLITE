<?php
declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/security_helpers.php';

// Garante que apenas Gerente e Administrador acessem
require_role(['Gerente', 'Administrador']);

include 'menu_navegacao.php';

// Usuário atual
$user = current_user();
$nomeUsuario = $user['nome'] ?? 'Gerente';

/* ================= KPIs DE VENDAS ================= */

// Hoje
$stmt_hoje = $pdo->query("
    SELECT COUNT(*) total, COALESCE(SUM(valor_total),0) valor
    FROM vendas
    WHERE DATE(data_venda)=CURDATE() AND produto_avariado=0
");
$vendas_hoje = $stmt_hoje->fetch(PDO::FETCH_ASSOC);

// Semana
$stmt_semana = $pdo->query("
    SELECT COUNT(*) total, COALESCE(SUM(valor_total),0) valor
    FROM vendas
    WHERE YEARWEEK(data_venda,1)=YEARWEEK(CURDATE(),1) AND produto_avariado=0
");
$vendas_semana = $stmt_semana->fetch(PDO::FETCH_ASSOC);

// Mês
$stmt_mes = $pdo->query("
    SELECT COUNT(*) total, COALESCE(SUM(valor_total),0) valor
    FROM vendas
    WHERE YEAR(data_venda)=YEAR(CURDATE())
      AND MONTH(data_venda)=MONTH(CURDATE())
      AND produto_avariado=0
");
$vendas_mes = $stmt_mes->fetch(PDO::FETCH_ASSOC);

$ticket_medio = $vendas_mes['total'] > 0
    ? $vendas_mes['valor'] / $vendas_mes['total']
    : 0;

/* ================= RANKING VENDEDORES ================= */

$stmt_ranking = $pdo->query("
    SELECT u.nome vendedor_nome,
           COUNT(v.id) total_vendas,
           COALESCE(SUM(v.valor_total),0) valor_total
    FROM vendas v
    JOIN usuarios u ON v.id_vendedor=u.id
    WHERE YEAR(v.data_venda)=YEAR(CURDATE())
      AND MONTH(v.data_venda)=MONTH(CURDATE())
      AND v.produto_avariado=0
    GROUP BY u.id,u.nome
    ORDER BY valor_total DESC
    LIMIT 10
");
$ranking_vendedores = $stmt_ranking->fetchAll(PDO::FETCH_ASSOC);

/* ================= TOP PRODUTOS ================= */

$stmt_produtos = $pdo->query("
    SELECT p.nome produto_nome,
           COUNT(v.id) vezes_vendido,
           COALESCE(SUM(v.quantidade),0) quantidade_total
    FROM vendas v
    JOIN produtos p ON v.produto_id=p.id
    WHERE YEAR(v.data_venda)=YEAR(CURDATE())
      AND MONTH(v.data_venda)=MONTH(CURDATE())
      AND v.produto_avariado=0
    GROUP BY p.id,p.nome
    ORDER BY quantidade_total DESC
    LIMIT 5
");
$top_produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

/* ================= LEADS ================= */

$leads_novos        = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status='novo'")->fetchColumn();
$leads_atendimento  = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status='em_atendimento'")->fetchColumn();
$leads_convertidos  = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status='convertido'")->fetchColumn();

/* ================= GRÁFICO 7 DIAS ================= */

$stmt_grafico = $pdo->query("
    SELECT DATE(data_venda) dia, COALESCE(SUM(valor_total),0) total_vendas
    FROM vendas
    WHERE DATE(data_venda)>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)
      AND produto_avariado=0
    GROUP BY DATE(data_venda)
    ORDER BY DATE(data_venda)
");
$vendas_7_dias = $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);

$ultimos_7_dias = [];
for ($i=6;$i>=0;$i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $ultimos_7_dias[$dia]=0;
}
foreach ($vendas_7_dias as $row) {
    $ultimos_7_dias[$row['dia']] = (float)$row['total_vendas'];
}

/* ================= ESTOQUE BAIXO ================= */

$stmt_estoque = $pdo->query("
    SELECT p.nome produto_nome, s.sabor sabor_nome, SUM(ev.quantidade) quantidade_total
    FROM estoque_vendedores ev
    JOIN produtos p ON ev.produto_id=p.id
    LEFT JOIN sabores s ON ev.sabor_id=s.id
    GROUP BY p.id,s.id
    HAVING quantidade_total>0 AND quantidade_total<=10
    ORDER BY quantidade_total ASC
    LIMIT 10
");
$estoque_baixo = $stmt_estoque->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Painel do Gerente - Rede Alabama</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="assets/css/alabama-design-system.css">
<link rel="stylesheet" href="alabama-theme.css">
<link rel="stylesheet" href="assets/css/alabama-page-overrides.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<style>
.dashboard-header{
    background:linear-gradient(135deg,var(--al-primary),var(--al-accent));
    color:#fff;
    padding:2rem 0;
    margin-bottom:2rem;
    border-radius:var(--al-radius-lg);
    border:1px solid var(--al-border);
    box-shadow:var(--al-shadow-glow);
}
.kpi-card{padding:1.5rem;margin-bottom:1.5rem;transition:.2s}
.kpi-card:hover{transform:translateY(-4px)}
.kpi-value{font-size:2rem;font-weight:700;color:var(--al-primary)}
.kpi-label{text-transform:uppercase;font-size:.85rem}
.section-title{font-weight:600;border-bottom:2px solid var(--al-primary);margin-bottom:1rem}
.chart-container{height:300px}
.icon-box{width:48px;height:48px;border-radius:var(--al-radius-md);display:flex;align-items:center;justify-content:center;font-size:1.4rem;margin-right:1rem}
.icon-box.blue{background:rgba(139,92,246,.1);color:var(--al-primary)}
.icon-box.green{background:rgba(74,222,128,.1);color:var(--al-success)}
.icon-box.orange{background:rgba(251,191,36,.1);color:var(--al-warning)}
.icon-box.purple{background:rgba(139,92,246,.15);color:var(--al-primary-hover)}
</style>
</head>

<body class="al-body">

<div class="container-fluid mt-4">
    <div class="dashboard-header text-center">
        <h2><i class="fas fa-chart-line me-2"></i>Painel do Gerente</h2>
        <p class="mb-0">
            Bem-vindo, <?= htmlspecialchars($nomeUsuario,ENT_QUOTES,'UTF-8');?>!
            Visão geral do desempenho de vendas
        </p>
    </div>

    <!-- KPIs -->
    <div class="row">
        <?php
        $kpis = [
            ['Hoje','fa-calendar-day',$vendas_hoje],
            ['Semana','fa-calendar-week',$vendas_semana],
            ['Mês','fa-calendar-alt',$vendas_mes],
        ];
        foreach($kpis as [$label,$icon,$data]): ?>
        <div class="col-md-3">
            <div class="kpi-card al-card">
                <div class="d-flex align-items-center">
                    <div class="icon-box blue"><i class="fas <?= $icon ?>"></i></div>
                    <div>
                        <div class="kpi-label"><?= $label ?></div>
                        <div class="kpi-value"><?= (int)$data['total'] ?></div>
                        <small class="text-muted">
                            R$ <?= number_format($data['valor'],2,',','.') ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="col-md-3">
            <div class="kpi-card al-card">
                <div class="d-flex align-items-center">
                    <div class="icon-box purple"><i class="fas fa-receipt"></i></div>
                    <div>
                        <div class="kpi-label">Ticket Médio</div>
                        <div class="kpi-value">
                            R$ <?= number_format($ticket_medio,2,',','.') ?>
                        </div>
                        <small class="text-muted">Este mês</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico -->
    <div class="al-card p-4">
        <h5 class="section-title"><i class="fas fa-chart-area me-2"></i>Vendas - Últimos 7 dias</h5>
        <div class="chart-container">
            <canvas id="vendasChart"></canvas>
        </div>
    </div>
</div>

<footer class="footer bg-dark text-center text-white py-3 mt-4">
    AlabamaCMS 1.1 © <?= date('Y');?> — Painel Gerencial
</footer>

<script <?= alabama_csp_nonce_attr(); ?>>
const labels = <?= json_encode(array_keys($ultimos_7_dias)); ?>.map(d=>{
    const dt=new Date(d+'T00:00:00');
    return dt.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'});
});
const data = <?= json_encode(array_values($ultimos_7_dias),JSON_NUMERIC_CHECK); ?>;

new Chart(document.getElementById('vendasChart'),{
    type:'line',
    data:{labels,datasets:[{
        label:'Vendas (R$)',
        data,
        fill:true,
        tension:.4,
        backgroundColor:'rgba(139,92,246,.15)',
        borderColor:'rgba(139,92,246,1)',
        borderWidth:2
    }]},
    options:{responsive:true,maintainAspectRatio:false}
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
