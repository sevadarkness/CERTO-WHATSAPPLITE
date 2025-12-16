<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    mem_respond(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$key = mem_require_workspace();
$workspace = mem_workspace_id($key);
$dir = mem_store_dir();
$file = $dir . '/' . $workspace . '.events.jsonl';

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    mem_respond(['ok' => false, 'error' => 'Invalid JSON body'], 400);
}

$events = $body['events'] ?? null;
if (!is_array($events)) {
    mem_respond(['ok' => false, 'error' => 'Campo "events" deve ser um array'], 400);
}

$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DateTimeInterface::ATOM);
$accepted = 0;

foreach ($events as $ev) {
    if (!is_array($ev)) continue;
    $row = [
        'ts' => $now,
        'event' => $ev,
    ];
    mem_append_jsonl($file, $row);
    $accepted++;
}

mem_respond([
    'ok' => true,
    'accepted' => $accepted,
]);
