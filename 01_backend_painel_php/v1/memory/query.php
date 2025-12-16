<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    mem_respond(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$key = mem_require_workspace();
$workspace = mem_workspace_id($key);
$dir = mem_store_dir();
$eventsFile = $dir . '/' . $workspace . '.events.jsonl';
$contextFile = $dir . '/' . $workspace . '.context.json';

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    mem_respond(['ok' => false, 'error' => 'Invalid JSON body'], 400);
}

$chatTitle = trim((string)($body['chatTitle'] ?? ''));
$topK = (int)($body['topK'] ?? 4);
$topK = max(1, min(20, $topK));

$rows = mem_tail_jsonl($eventsFile, 20000);

// Extract latest memory for this chat (last write wins)
$memory = null;
$examples = [];

foreach ($rows as $r) {
    if (!isset($r['event']) || !is_array($r['event'])) continue;
    $ev = $r['event'];
    $type = (string)($ev['type'] ?? '');

    if ($type === 'chat_memory') {
        $ct = (string)($ev['chatTitle'] ?? '');
        if ($chatTitle !== '' && $ct !== $chatTitle) continue;
        $memory = $ev['memory'] ?? null;
        continue;
    }

    if ($type === 'example') {
        $ex = $ev['example'] ?? null;
        if (is_array($ex) && !empty($ex['user']) && !empty($ex['assistant'])) {
            $examples[] = $ex;
        }
        continue;
    }
}

if (count($examples) > $topK) {
    $examples = array_slice($examples, -$topK);
}

$ctx = mem_read_json($contextFile);
$additions = '';
if (is_array($ctx)) {
    $p = trim((string)($ctx['persona'] ?? ''));
    $b = trim((string)($ctx['businessContext'] ?? ''));
    if ($p !== '' || $b !== '') {
        $additions = "Contexto (servidor):\n" .
            ($p !== '' ? ("- Persona:\n" . $p . "\n") : '') .
            ($b !== '' ? ("- Negócio:\n" . $b . "\n") : '');
    }
}

mem_respond([
    'ok' => true,
    'memory' => $memory,
    'examples' => $examples,
    'context' => $additions !== '' ? ['additions' => $additions] : null,
]);
