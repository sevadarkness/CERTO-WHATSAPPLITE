<?php
declare(strict_types=1);

use RedeAlabama\Support\PrometheusMetrics;


require_once __DIR__ . '/app/Support/PrometheusMetrics.php';


/**
 * Endpoint de métricas Prometheus.
 *
 * Exemplo de scrape:
 *   curl http://seu-dominio/adm.redealabama/metrics.php
 *
 * IMPORTANTE: em produção, proteger com firewall / basic auth / IP whitelisting.
 */

// Collect business metrics from database
try {
    require_once __DIR__ . '/db_config.php';
    
    $metrics = PrometheusMetrics::instance();
    
    // 1. Active users (logged in last 24h)
    try {
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT usuario_id) as count 
            FROM session_logs 
            WHERE created_at >= NOW() - INTERVAL 24 HOUR
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics->setGauge('alabama_active_users', (float)($row['count'] ?? 0));
    } catch (PDOException $e) {
        // Table might not exist, skip
    }
    
    // 2. Sales today
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count, COALESCE(SUM(valor_total), 0) as total
            FROM vendas 
            WHERE DATE(data_venda) = CURDATE()
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics->setGauge('alabama_sales_today_count', (float)($row['count'] ?? 0));
        $metrics->setGauge('alabama_sales_today_total', (float)($row['total'] ?? 0));
    } catch (PDOException $e) {
        // Table might not exist, skip
    }
    
    // 3. Active campaigns
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM remarketing_campanhas 
            WHERE status = 'ativa'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics->setGauge('alabama_campaigns_active', (float)($row['count'] ?? 0));
    } catch (PDOException $e) {
        // Table might not exist, skip
    }
    
    // 4. Messages by status (WhatsApp)
    try {
        $stmt = $pdo->query("
            SELECT direction, COUNT(*) as count 
            FROM whatsapp_mensagens 
            WHERE created_at >= NOW() - INTERVAL 24 HOUR
            GROUP BY direction
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $metrics->setGauge('alabama_whatsapp_messages_24h', (float)$row['count'], ['direction' => $row['direction']]);
        }
    } catch (PDOException $e) {
        // Table might not exist, skip
    }
    
    // 5. Pending jobs
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM jobs_agendados 
            WHERE status = 'pendente'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics->setGauge('alabama_jobs_pending', (float)($row['count'] ?? 0));
    } catch (PDOException $e) {
        // Table might not exist, skip
    }
    
    // 6. Active flows
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM whatsapp_flows 
            WHERE ativo = 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics->setGauge('alabama_flows_active', (float)($row['count'] ?? 0));
    } catch (PDOException $e) {
        // Table might not exist, skip
    }
    
    // 7. Leads last 24h
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM leads 
            WHERE created_at >= NOW() - INTERVAL 24 HOUR
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics->setGauge('alabama_leads_24h', (float)($row['count'] ?? 0));
    } catch (PDOException $e) {
        // Table might not exist, skip
    }
    
    // 8. Open sessions
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM whatsapp_atendimentos 
            WHERE status = 'aberto'
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics->setGauge('alabama_sessions_open', (float)($row['count'] ?? 0));
    } catch (PDOException $e) {
        // Table might not exist, skip
    }
    
} catch (Throwable $e) {
    // If DB is not available, just render stored metrics
}

PrometheusMetrics::instance()->render();

