<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    mem_respond(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$key = mem_require_workspace();
$workspace = mem_workspace_id($key);
$dir = mem_store_dir();
$file = $dir . '/' . $workspace . '.context.json';

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    mem_respond(['ok' => false, 'error' => 'Invalid JSON body'], 400);
}

$persona = isset($body['persona']) && is_string($body['persona']) ? trim($body['persona']) : '';
$businessContext = isset($body['businessContext']) && is_string($body['businessContext']) ? trim($body['businessContext']) : '';

$existing = mem_read_json($file) ?: [];
$existing['persona'] = $persona;
$existing['businessContext'] = $businessContext;
$existing['updatedAt'] = date('c');

mem_write_json($file, $existing);

mem_respond(['ok' => true, 'stored' => true]);
