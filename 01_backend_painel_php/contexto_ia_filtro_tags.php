<?php
declare(strict_types=1);

/**
 * Visualizador simples de contexto IA filtrado por "tag".
 *
 * Uso:
 *   contexto_ia_filtro_tags.php?tag=lead_quente&channel=llm&limit=100
 */

require_once __DIR__ . '/logger.php';

$tag     = isset($_GET['tag']) ? trim((string)$_GET['tag']) : '';
$channel = isset($_GET['channel']) ? trim((string)$_GET['channel']) : 'llm';
$limit   = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 100;

$logDir = defined('ALABAMA_LOG_DIR') ? ALABAMA_LOG_DIR : (__DIR__ . '/logs');
$file   = $logDir . '/' . ($channel !== '' ? $channel : 'app') . '.log';

$rows = [];

if (is_file($file) && is_readable($file)) {
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_slice($lines, -$limit);

    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        if ($tag !== '') {
            $haystack = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            if (stripos($haystack, $tag) === false) {
                continue;
            }
        }
        $rows[] = $decoded;
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contexto IA - Filtro por Tag</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; padding: 16px; background: #0b0b0f; color: #f5f5f5; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        form { margin-bottom: 16px; }
        input, select { padding: 4px 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #333; padding: 6px 8px; font-size: 12px; vertical-align: top; }
        th { background: #111827; }
        tr:nth-child(even) { background: #020617; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 11px; }
    </style>
</head>
<body>
<h1>Contexto IA - Filtro por Tag</h1>

<form method="get">
    <label>Tag contém:
        <input type="text" name="tag" value="<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Canal:
        <input type="text" name="channel" value="<?php echo htmlspecialchars($channel, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>Limite:
        <input type="number" name="limit" value="<?php echo (int)$limit; ?>" min="1" max="1000">
    </label>
    <button type="submit">Filtrar</button>
</form>

<?php if (!$rows): ?>
    <p>Nenhum evento encontrado para os filtros informados.</p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Timestamp</th>
            <th>Canal</th>
            <th>Evento</th>
            <th>Dados</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars((string)($row['ts'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['channel'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['event'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><code><?php echo htmlspecialchars(json_encode($row['data'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
