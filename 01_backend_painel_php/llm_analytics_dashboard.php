<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$erro = null;
$rows = [];
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if ($days < 1) {
    $days = 1;
}
if ($days > 90) {
    $days = 90;
}

try {
    // Usa o PDO global definido em db_config.php
    global $pdo;

    $sql = "SELECT 
                DATE(created_at) AS dia,
                provider,
                model,
                COUNT(*) AS total_chamadas,
                SUM(COALESCE(total_tokens, 0)) AS tokens_totais,
                AVG(COALESCE(latency_ms, 0)) AS latency_media_ms
            FROM llm_logs
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(created_at), provider, model
            ORDER BY dia DESC, provider, model";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':days' => $days]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $erro = $e->getMessage();
    log_app_event('ia', 'llm_analytics_error', [
        'error' => $e->getMessage(),
    ]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
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
    <meta charset="utf-8">
    <title>Analytics de IA (LLM Logs) - Rede Alabama</title>
</head>
<body class="al-body">
<div class="container my-4">
    <h1 class="h3 mb-3">Analytics de IA (LLM Logs)</h1>
    <p class="text-muted">
        Visão agregada das chamadas ao LLM registradas na tabela <code>llm_logs</code>.
        Útil para estimativa de custo, monitoramento de latência e sanity check de modelos.
    </p>

    <form method="get" class="form-inline mb-3">
        <label for="days" class="mr-2">Período (dias, 1–90):</label>
        <input type="number" min="1" max="90" id="days" name="days" value="<?php echo htmlspecialchars((string)$days, ENT_QUOTES, 'UTF-8'); ?>" class="form-control form-control-sm mr-2">
        <button type="submit" class="btn btn-sm btn-primary">Atualizar</button>
        <span class="badge badge-secondary ml-2">Fonte: llm_logs (DB)</span>
    </form>

    <?php if ($erro !== null): ?>
        <div class="alert alert-danger">
            <strong>Erro ao carregar dados:</strong><br>
            <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-sm table-striped table-dark">
            <thead>
                <tr>
                    <th>Dia</th>
                    <th>Provider</th>
                    <th>Modelo</th>
                    <th>Chamadas</th>
                    <th>Tokens (total)</th>
                    <th>Latência média (ms)</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6">Nenhum dado encontrado para os últimos <?php echo htmlspecialchars((string)$days, ENT_QUOTES, 'UTF-8'); ?> dias.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string)$r['dia'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['provider'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)$r['model'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)$r['total_chamadas']; ?></td>
                        <td><?php echo (int)$r['tokens_totais']; ?></td>
                        <td><?php echo (int)$r['latency_media_ms']; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="text-muted small mt-3">
        Observação: esta tela não substitui o dashboard de arquivos de log (&ldquo;Insights IA&rdquo;),
        ela complementa com uma visão em cima da tabela <code>llm_logs</code>.
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
