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

// KPIs simples (puxando dados do dia/semana)
$hoje = date('Y-m-d');
$semana_ini = date('Y-m-d', strtotime('monday this week'));

// Vendas hoje
$stmt = (new \RedeAlabama\Repositories\Screens\DashboardSupremacyRepository($pdo))->prepare_423();
$stmt->execute([':d' => $hoje]);
$vendasHoje = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'total' => 0];

// Vendas na semana
$stmt = (new \RedeAlabama\Repositories\Screens\DashboardSupremacyRepository($pdo))->prepare_681();
$stmt->execute([':dini' => $semana_ini]);
$vendasSemana = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'total' => 0];

// Leads em aberto
$stmt = (new \RedeAlabama\Repositories\Screens\DashboardSupremacyRepository($pdo))->query_937();
$leadsAbertos = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0];

// Conversas ativas WhatsApp
$stmt = (new \RedeAlabama\Repositories\Screens\DashboardSupremacyRepository($pdo))->query_1131();
$conversasAtivas = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0];


// SLA médio (em minutos) nos últimos 7 dias
try {
    $stmt = (new \RedeAlabama\Repositories\Screens\DashboardSupremacyRepository($pdo))->query_1349();
    $rowSla = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['sla_seg' => null];
    $slaMinutos = $rowSla['sla_seg'] !== null ? round(((float)$rowSla['sla_seg']) / 60.0, 1) : null;
} catch (Throwable $e) {
    $slaMinutos = null;
}

// Leads em aberto sem primeiro contato
try {
    $stmt = (new \RedeAlabama\Repositories\Screens\DashboardSupremacyRepository($pdo))->query_1965();
    $leadsSemPrimeiroContato = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0];
} catch (Throwable $e) {
    $leadsSemPrimeiroContato = ['qtd' => 0];
}

// Jobs pendentes e com erro
try {
    $stmt = (new \RedeAlabama\Repositories\Screens\DashboardSupremacyRepository($pdo))->query_2418();
    $jobsPendentes = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0];
} catch (Throwable $e) {
    $jobsPendentes = ['qtd' => 0];
}

try {
    $stmt = (new \RedeAlabama\Repositories\Screens\DashboardSupremacyRepository($pdo))->query_2653();
    $jobsErro = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0];
} catch (Throwable $e) {
    $jobsErro = ['qtd' => 0];
}


// Atendimentos humanos abertos
$stmt = (new \RedeAlabama\Repositories\Screens\DashboardSupremacyRepository($pdo))->query_2897();
$atendimentosAbertos = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0];

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
    <title>Dashboard Supremacy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="al-body">
<div class="container mt-4">
    <h1 class="mb-3"><i class="fas fa-tachometer-alt"></i> Dashboard Supremacy</h1>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h6 class="text-muted">Vendas Hoje</h6>
                    <h3><?php echo (int)$vendasHoje['qtd']; ?></h3>
                    <p class="mb-0">R$ <?php echo number_format((float)$vendasHoje['total'], 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h6 class="text-muted">Vendas na Semana</h6>
                    <h3><?php echo (int)$vendasSemana['qtd']; ?></h3>
                    <p class="mb-0">R$ <?php echo number_format((float)$vendasSemana['total'], 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h6 class="text-muted">Leads em aberto</h6>
                    <h3><?php echo (int)$leadsAbertos['qtd']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h6 class="text-muted">Conversas WhatsApp ativas</h6>
                    <h3><?php echo (int)$conversasAtivas['qtd']; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h6 class="text-muted">Atendimentos humanos abertos</h6>
                    <h3><?php echo (int)$atendimentosAbertos['qtd']; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h6 class="text-muted">SLA médio (últimos 7 dias)</h6>
                    <h3>
                        <?php
                        if ($slaMinutos !== null) {
                            echo htmlspecialchars((string)$slaMinutos) . ' min';
                        } else {
                            echo '-';
                        }
                        ?>
                    </h3>
                    <p class="mb-0 small text-muted">Tempo entre criação do lead e primeiro contato.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h6 class="text-muted">Leads em aberto sem primeiro contato</h6>
                    <h3><?php echo (int)$leadsSemPrimeiroContato['qtd']; ?></h3>
                    <p class="mb-0 small text-muted">Status novo/em atendimento sem SLA iniciado.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h6 class="text-muted">Jobs pendentes</h6>
                    <h3><?php echo (int)$jobsPendentes['qtd']; ?></h3>
                    <p class="mb-0 small text-muted">jobs_agendados com status pendente.</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-secondary text-light h-100">
                <div class="card-body">
                    <h6 class="text-muted">Jobs com erro</h6>
                    <h3><?php echo (int)$jobsErro['qtd']; ?></h3>
                    <p class="mb-0 small text-muted">jobs_agendados com status erro.</p>
                </div>
            </div>
        </div>
    </div>


    <p class="text-muted">
        Este dashboard consolida visão de vendas, funil de leads, SLA e saúde de jobs do sistema.
        Gráficos mais avançados podem ser plugados aqui reaproveitando as APIs já existentes (ranking, remarketing, etc.).
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
