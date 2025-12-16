<?php
declare(strict_types=1);

// Shared helpers for the lightweight memory server used by the Chrome extension.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Workspace-Key');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function mem_respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mem_get_workspace_key(): string
{
    return trim((string)($_SERVER['HTTP_X_WORKSPACE_KEY'] ?? ''));
}

function mem_require_workspace(): string
{
    $key = mem_get_workspace_key();
    if ($key === '') {
        mem_respond(['ok' => false, 'error' => 'Missing X-Workspace-Key header'], 401);
    }

    $required = trim((string)(getenv('MEMORY_SERVER_WORKSPACE_KEY') ?: ''));
    if ($required !== '' && !hash_equals($required, $key)) {
        mem_respond(['ok' => false, 'error' => 'Invalid workspace key'], 401);
    }

    return $key;
}

function mem_store_dir(): string
{
    // Keep outside public routes; metrics_storage is blocked by router/.htaccess.
    $dir = realpath(__DIR__ . '/../../metrics_storage') ?: (__DIR__ . '/../../metrics_storage');
    $dir .= '/memory_server';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

function mem_workspace_id(string $key): string
{
    return substr(hash('sha256', $key), 0, 16);
}

function mem_read_json(string $file): ?array
{
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function mem_write_json(string $file, array $data): void
{
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function mem_append_jsonl(string $file, array $row): void
{
    $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) return;
    @file_put_contents($file, $line . "\n", FILE_APPEND);
}

function mem_tail_jsonl(string $file, int $maxLines = 5000): array
{
    if (!is_file($file)) return [];
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, -$maxLines);
    }
    $rows = [];
    foreach ($lines as $ln) {
        $j = json_decode($ln, true);
        if (is_array($j)) $rows[] = $j;
    }
    return $rows;
}
