<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/logger.php';

$current_page = basename(__FILE__);
include __DIR__ . '/menu_navegacao.php';

$erro = null;

$channelsCount = [];
$templatesCount = [];
$tonesCount = [];
$totalEvents = 0;
$totalSuggestions = 0;

$logDir = defined('ALABAMA_LOG_DIR') ? ALABAMA_LOG_DIR : (__DIR__ . '/logs');

function alabama_tail_lines(string $file, int $maxLines = 2000): array
{
    if (!is_readable($file)) {
        return [];
    }

    $fp = @fopen($file, 'rb');
    if (!$fp) {
        return [];
    }

    $buffer = '';
    $chunkSize = 4096;
    $pos = -1;
    $lines = [];

    fseek($fp, 0, SEEK_END);
    $filesize = ftell($fp);

    while (count($lines) < $maxLines && -$pos < $filesize) {
        $seek = max($pos - $chunkSize, -$filesize);
        fseek($fp, $seek, SEEK_END);
        $chunk = fread($fp, $chunkSize);
        $buffer = $chunk . $buffer;
        $pos = $seek;

        $parts = explode("\n", $buffer);
        if (count($parts) > 1) {
            $linesChunk = array_slice($parts, 1 - (count($parts) - 1));
            $lines = array_merge($linesChunk, $lines);
            $buffer = $parts[0];
        }

        if (count($lines) >= $maxLines) {
            break;
        }

        if ($seek === -$filesize) {
        }
    }

    fclose($fp);

    if ($buffer !== '') {
        array_unshift($lines, $buffer);
    }

    return array_slice(array_filter($lines, 'strlen'), -$maxLines);
}

try {
    $files = glob($logDir . '/*.log') ?: [];

    foreach ($files as $file) {
        foreach (alabama_tail_lines($file, 1500) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $totalEvents++;

            $channel = (string)($decoded['channel'] ?? 'unknown');
            $message = (string)($decoded['message'] ?? '');
            $context = is_array($decoded['context'] ?? null) ? $decoded['context'] : [];

            if (!isset($channelsCount[$channel])) {
                $channelsCount[$channel] = 0;
            }
            $channelsCount[$channel]++;

            if ($channel === 'whatsapp_ai' && $message === 'suggestion') {
                $totalSuggestions++;
                $tone = isset($context['tone']) ? (string)$context['tone'] : '';
                $tpl  = isset($context['template']) ? (string)$context['template'] : '';

                if ($tone !== '') {
                    if (!isset($tonesCount[$tone])) {
                        $tonesCount[$tone] = 0;
                    }
                    $tonesCount[$tone]++;
                }

                if ($tpl !== '') {
                    if (!isset($templatesCount[$tpl])) {
                        $templatesCount[$tpl] = 0;
                    }
                    $templatesCount[$tpl]++;
                }
            }

            if ($channel === 'admin_assistant' && $message === 'llm_resposta') {
                $tone = isset($context['tone']) ? (string)$context['tone'] : '';
                $tpl  = isset($context['template']) ? (string)$context['template'] : '';

                if ($tone !== '') {
                    if (!isset($tonesCount[$tone])) {
                        $tonesCount[$tone] = 0;
                    }
                    $tonesCount[$tone]++;
                }

                if ($tpl !== '') {
                    if (!isset($templatesCount[$tpl])) {
                        $templatesCount[$tpl] = 0;
                    }
                    $templatesCount[$tpl]++;
                }
            }
        }
    }
} catch (Throwable $e) {
    $erro = 'Erro ao ler logs de IA: ' . $e->getMessage();
}

$channelsLabels = array_keys($channelsCount);
$channelsValues = array_values($channelsCount);

$templatesLabels = array_keys($templatesCount);
$templatesValues = array_values($templatesCount);

$tonesLabels = array_keys($tonesCount);
$tonesValues = array_values($tonesCount);

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
    <meta charset="UTF-8">
    <title>Dashboard de Insights IA + WhatsApp</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="al-body">
<div class="container my-4">
    <h1 class="h3 mb-3">Dashboard de Insights IA + WhatsApp</h1>
    <p class="text-muted">
        Visão consolidada do uso da IA (assistente interno + sugestões de WhatsApp) com base nos logs estruturados.
        Os gráficos abaixo utilizam apenas as últimas milhares de linhas de cada log para manter a performance.
    </p>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-secondary mb-3">
                <div class="card-header">Eventos de log (amostra)</div>
                <div class="card-body">
                    <h3 class="h2 mb-0"><?php echo (int)$totalEvents; ?></h3>
                    <p class="mb-0 text-muted">Linhas de log analisadas</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-secondary mb-3">
                <div class="card-header">Sugestões de WhatsApp (IA)</div>
                <div class="card-body">
                    <h3 class="h2 mb-0"><?php echo (int)$totalSuggestions; ?></h3>
                    <p class="mb-0 text-muted">Eventos whatsapp_ai / suggestion</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-secondary mb-3">
        <div class="card-header">Eventos por canal de log</div>
        <div class="card-body">
            <canvas id="channelsChart"></canvas>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="card bg-secondary mb-3">
                <div class="card-header">Templates mais usados</div>
                <div class="card-body">
                    <canvas id="templatesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-secondary mb-3">
                <div class="card-header">Tons mais usados</div>
                <div class="card-body">
                    <canvas id="tonesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>

<script <?php echo alabama_csp_nonce_attr(); ?>>
const channelsLabels = <?php echo json_encode($channelsLabels, JSON_UNESCAPED_UNICODE); ?>;
const channelsValues = <?php echo json_encode($channelsValues, JSON_UNESCAPED_UNICODE); ?>;
const templatesLabels = <?php echo json_encode($templatesLabels, JSON_UNESCAPED_UNICODE); ?>;
const templatesValues = <?php echo json_encode($templatesValues, JSON_UNESCAPED_UNICODE); ?>;
const tonesLabels = <?php echo json_encode($tonesLabels, JSON_UNESCAPED_UNICODE); ?>;
const tonesValues = <?php echo json_encode($tonesValues, JSON_UNESCAPED_UNICODE); ?>;

if (channelsLabels.length) {
    const ctxChannels = document.getElementById('channelsChart').getContext('2d');
    new Chart(ctxChannels, {
        type: 'bar',
        data: {
            labels: channelsLabels,
            datasets: [{
                label: 'Eventos por canal',
                data: channelsValues,
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                }
            }
        }
    });
}

if (templatesLabels.length) {
    const ctxTemplates = document.getElementById('templatesChart').getContext('2d');
    new Chart(ctxTemplates, {
        type: 'bar',
        data: {
            labels: templatesLabels,
            datasets: [{
                label: 'Uso de templates',
                data: templatesValues,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            scales: {
                x: {
                    beginAtZero: true,
                }
            }
        }
    });
}

if (tonesLabels.length) {
    const ctxTones = document.getElementById('tonesChart').getContext('2d');
    new Chart(ctxTones, {
        type: 'bar',
        data: {
            labels: tonesLabels,
            datasets: [{
                label: 'Uso de tons',
                data: tonesValues,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            scales: {
                x: {
                    beginAtZero: true,
                }
            }
        }
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
