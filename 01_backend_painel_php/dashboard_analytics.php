<?php
declare(strict_types=1);

/**
 * Dashboard Analytics - Sistema de métricas e visualizações
 * Exibe métricas de IA, campanhas, leads e WhatsApp
 */

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

$current_page = basename(__FILE__);

// Get period filter
$period = $_GET['period'] ?? 'today';
$allowedPeriods = ['today', '7days', '30days', 'custom'];
if (!in_array($period, $allowedPeriods)) {
    $period = 'today';
}

// Custom date range
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

/**
 * Get LLM metrics based on period
 */
function get_llm_metrics(PDO $pdo, string $period, string $startDate, string $endDate): array
{
    $dateFilter = get_date_filter($period, $startDate, $endDate);
    
    try {
        // Total calls and tokens
        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    SUM(COALESCE(total_tokens, 0)) as total_tokens,
                    AVG(COALESCE(latency_ms, 0)) as avg_latency,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as error_count
                FROM llm_logs
                WHERE $dateFilter";
        
        $stmt = $pdo->query($sql);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calls by day
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as calls
                FROM llm_logs
                WHERE $dateFilter
                GROUP BY DATE(created_at)
                ORDER BY date ASC";
        
        $stmt = $pdo->query($sql);
        $byDay = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'totals' => $totals ?: [
                'total_calls' => 0,
                'total_tokens' => 0,
                'avg_latency' => 0,
                'success_count' => 0,
                'error_count' => 0,
            ],
            'by_day' => $byDay,
        ];
    } catch (Throwable $e) {
        log_app_event('dashboard', 'llm_metrics_error', ['error' => $e->getMessage()]);
        return ['totals' => [], 'by_day' => []];
    }
}

/**
 * Get campaign metrics
 */
function get_campaign_metrics(PDO $pdo, string $period, string $startDate, string $endDate): array
{
    $dateFilter = get_date_filter($period, $startDate, $endDate, 'created_at');
    
    try {
        // Total campaigns
        $sql = "SELECT 
                    COUNT(*) as total_campaigns,
                    SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as active_campaigns,
                    SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as inactive_campaigns
                FROM remarketing_campanhas";
        
        $stmt = $pdo->query($sql);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Messages sent via bulk jobs
        $dateFilterJobs = get_date_filter($period, $startDate, $endDate, 'created_at');
        $sql = "SELECT 
                    COUNT(DISTINCT job_id) as total_jobs,
                    COUNT(*) as total_items,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
                FROM whatsapp_bulk_job_items
                WHERE $dateFilterJobs";
        
        $stmt = $pdo->query($sql);
        $messages = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'totals' => $totals ?: ['total_campaigns' => 0, 'active_campaigns' => 0, 'inactive_campaigns' => 0],
            'messages' => $messages ?: ['total_jobs' => 0, 'total_items' => 0, 'sent_count' => 0, 'failed_count' => 0, 'pending_count' => 0],
        ];
    } catch (Throwable $e) {
        log_app_event('dashboard', 'campaign_metrics_error', ['error' => $e->getMessage()]);
        return ['totals' => [], 'messages' => []];
    }
}

/**
 * Get lead/conversation metrics
 */
function get_lead_metrics(PDO $pdo, string $period, string $startDate, string $endDate): array
{
    $dateFilter = get_date_filter($period, $startDate, $endDate, 'created_at');
    
    try {
        // Conversations
        $sql = "SELECT 
                    COUNT(*) as total_conversations,
                    SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) as active_conversations,
                    SUM(CASE WHEN status = 'finalizada' THEN 1 ELSE 0 END) as closed_conversations
                FROM whatsapp_conversas
                WHERE $dateFilter";
        
        $stmt = $pdo->query($sql);
        $conversations = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Messages in conversations
        $sql = "SELECT 
                    COUNT(*) as total_messages,
                    SUM(CASE WHEN direction = 'in' THEN 1 ELSE 0 END) as incoming_messages,
                    SUM(CASE WHEN direction = 'out' THEN 1 ELSE 0 END) as outgoing_messages
                FROM whatsapp_mensagens wm
                JOIN whatsapp_conversas wc ON wm.conversa_id = wc.id
                WHERE $dateFilter";
        
        $stmt = $pdo->query($sql);
        $messages = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'conversations' => $conversations ?: ['total_conversations' => 0, 'active_conversations' => 0, 'closed_conversations' => 0],
            'messages' => $messages ?: ['total_messages' => 0, 'incoming_messages' => 0, 'outgoing_messages' => 0],
        ];
    } catch (Throwable $e) {
        log_app_event('dashboard', 'lead_metrics_error', ['error' => $e->getMessage()]);
        return ['conversations' => [], 'messages' => []];
    }
}

/**
 * Get WhatsApp metrics
 */
function get_whatsapp_metrics(PDO $pdo, string $period, string $startDate, string $endDate): array
{
    $dateFilter = get_date_filter($period, $startDate, $endDate, 'created_at');
    
    try {
        // Messages
        $sql = "SELECT 
                    COUNT(*) as total_messages,
                    COUNT(DISTINCT telefone_cliente) as unique_contacts
                FROM whatsapp_conversas
                WHERE $dateFilter";
        
        $stmt = $pdo->query($sql);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Bulk job stats
        $sql = "SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
                FROM whatsapp_bulk_job_items
                WHERE $dateFilter";
        
        $stmt = $pdo->query($sql);
        $bulkStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'totals' => $totals ?: ['total_messages' => 0, 'unique_contacts' => 0],
            'bulk_stats' => $bulkStats ?: ['total_items' => 0, 'sent_count' => 0, 'failed_count' => 0],
        ];
    } catch (Throwable $e) {
        log_app_event('dashboard', 'whatsapp_metrics_error', ['error' => $e->getMessage()]);
        return ['totals' => [], 'bulk_stats' => []];
    }
}

/**
 * Helper to build date filter SQL
 */
function get_date_filter(string $period, string $startDate, string $endDate, string $field = 'created_at'): string
{
    switch ($period) {
        case 'today':
            return "$field >= CURDATE()";
        case '7days':
            return "$field >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        case '30days':
            return "$field >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        case 'custom':
            $start = date('Y-m-d', strtotime($startDate));
            $end = date('Y-m-d', strtotime($endDate));
            return "$field >= '$start' AND $field <= '$end 23:59:59'";
        default:
            return "$field >= CURDATE()";
    }
}

// Fetch all metrics
global $pdo;
$llmMetrics = get_llm_metrics($pdo, $period, $startDate, $endDate);
$campaignMetrics = get_campaign_metrics($pdo, $period, $startDate, $endDate);
$leadMetrics = get_lead_metrics($pdo, $period, $startDate, $endDate);
$whatsappMetrics = get_whatsapp_metrics($pdo, $period, $startDate, $endDate);

include __DIR__ . '/menu_navegacao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Analytics - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="al-body">
<div class="container-fluid my-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-3">
                <i class="fas fa-chart-line"></i> Dashboard Analytics
            </h1>
            <p class="text-muted">Métricas de uso da plataforma, campanhas e conversões</p>
        </div>
    </div>

    <!-- Period Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card al-card">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-auto">
                            <label class="form-label">Período</label>
                            <select name="period" class="form-select" id="periodSelect">
                                <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Hoje</option>
                                <option value="7days" <?= $period === '7days' ? 'selected' : '' ?>>Últimos 7 dias</option>
                                <option value="30days" <?= $period === '30days' ? 'selected' : '' ?>>Últimos 30 dias</option>
                                <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Personalizado</option>
                            </select>
                        </div>
                        <div class="col-auto" id="customDates" style="display: <?= $period === 'custom' ? 'block' : 'none' ?>;">
                            <label class="form-label">Data inicial</label>
                            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                        </div>
                        <div class="col-auto" id="customDatesEnd" style="display: <?= $period === 'custom' ? 'block' : 'none' ?>;">
                            <label class="form-label">Data final</label>
                            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Atualizar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- LLM Metrics Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3"><i class="fas fa-robot"></i> Métricas de IA (LLM)</h4>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Total de Chamadas</h6>
                            <h2 class="mb-0"><?= number_format((int)($llmMetrics['totals']['total_calls'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-phone fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Tokens Consumidos</h6>
                            <h2 class="mb-0"><?= number_format((int)($llmMetrics['totals']['total_tokens'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-coins fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Latência Média</h6>
                            <h2 class="mb-0"><?= number_format((float)($llmMetrics['totals']['avg_latency'] ?? 0), 0) ?>ms</h2>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Taxa de Sucesso</h6>
                            <?php 
                            $successRate = 0;
                            $totalCalls = (int)($llmMetrics['totals']['total_calls'] ?? 0);
                            if ($totalCalls > 0) {
                                $successRate = ((int)($llmMetrics['totals']['success_count'] ?? 0) / $totalCalls) * 100;
                            }
                            ?>
                            <h2 class="mb-0"><?= number_format($successRate, 1) ?>%</h2>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LLM Chart -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card al-card">
                <div class="card-body">
                    <h5 class="card-title">Chamadas de IA por Dia</h5>
                    <canvas id="llmChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaign Metrics Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3"><i class="fas fa-bullhorn"></i> Métricas de Campanhas</h4>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Campanhas Ativas</h6>
                            <h2 class="mb-0"><?= number_format((int)($campaignMetrics['totals']['active_campaigns'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-play-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Mensagens Enviadas</h6>
                            <h2 class="mb-0"><?= number_format((int)($campaignMetrics['messages']['sent_count'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-paper-plane fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Taxa de Entrega</h6>
                            <?php 
                            $deliveryRate = 0;
                            $totalItems = (int)($campaignMetrics['messages']['total_items'] ?? 0);
                            if ($totalItems > 0) {
                                $deliveryRate = ((int)($campaignMetrics['messages']['sent_count'] ?? 0) / $totalItems) * 100;
                            }
                            ?>
                            <h2 class="mb-0"><?= number_format($deliveryRate, 1) ?>%</h2>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Mensagens Pendentes</h6>
                            <h2 class="mb-0"><?= number_format((int)($campaignMetrics['messages']['pending_count'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-hourglass-half fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leads/Conversations Metrics Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3"><i class="fas fa-users"></i> Métricas de Conversas/Leads</h4>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Total de Conversas</h6>
                            <h2 class="mb-0"><?= number_format((int)($leadMetrics['conversations']['total_conversations'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-comments fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Conversas Ativas</h6>
                            <h2 class="mb-0"><?= number_format((int)($leadMetrics['conversations']['active_conversations'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-comment-dots fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Mensagens Recebidas</h6>
                            <h2 class="mb-0"><?= number_format((int)($leadMetrics['messages']['incoming_messages'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-info">
                            <i class="fas fa-inbox fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Mensagens Enviadas</h6>
                            <h2 class="mb-0"><?= number_format((int)($leadMetrics['messages']['outgoing_messages'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-warning">
                            <i class="fas fa-share fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp Metrics Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3"><i class="fab fa-whatsapp"></i> Métricas de WhatsApp</h4>
        </div>
        <div class="col-md-4">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Contatos Únicos</h6>
                            <h2 class="mb-0"><?= number_format((int)($whatsappMetrics['totals']['unique_contacts'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-success">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Mensagens Bulk Enviadas</h6>
                            <h2 class="mb-0"><?= number_format((int)($whatsappMetrics['bulk_stats']['sent_count'] ?? 0)) ?></h2>
                        </div>
                        <div class="text-primary">
                            <i class="fas fa-paper-plane fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card al-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-muted">Taxa de Erro</h6>
                            <?php 
                            $errorRate = 0;
                            $totalBulkItems = (int)($whatsappMetrics['bulk_stats']['total_items'] ?? 0);
                            if ($totalBulkItems > 0) {
                                $errorRate = ((int)($whatsappMetrics['bulk_stats']['failed_count'] ?? 0) / $totalBulkItems) * 100;
                            }
                            ?>
                            <h2 class="mb-0"><?= number_format($errorRate, 1) ?>%</h2>
                        </div>
                        <div class="text-danger">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Period selector
document.getElementById('periodSelect').addEventListener('change', function() {
    const customDates = document.getElementById('customDates');
    const customDatesEnd = document.getElementById('customDatesEnd');
    if (this.value === 'custom') {
        customDates.style.display = 'block';
        customDatesEnd.style.display = 'block';
    } else {
        customDates.style.display = 'none';
        customDatesEnd.style.display = 'none';
    }
});

// LLM Chart
const llmData = <?= json_encode($llmMetrics['by_day']) ?>;
const llmLabels = llmData.map(item => item.date);
const llmValues = llmData.map(item => parseInt(item.calls));

const llmCtx = document.getElementById('llmChart').getContext('2d');
new Chart(llmCtx, {
    type: 'line',
    data: {
        labels: llmLabels,
        datasets: [{
            label: 'Chamadas de IA',
            data: llmValues,
            borderColor: 'rgb(255, 165, 0)',
            backgroundColor: 'rgba(255, 165, 0, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                labels: {
                    color: '#999'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: '#999'
                },
                grid: {
                    color: '#333'
                }
            },
            x: {
                ticks: {
                    color: '#999'
                },
                grid: {
                    color: '#333'
                }
            }
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
