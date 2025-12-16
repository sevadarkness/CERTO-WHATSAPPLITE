<?php
/**
 * Testes simples de invariantes de estoque.
 * Verifica duplicidade em estoque_vendedores por (produto, vendedor, sabor).
 */

declare(strict_types=1);

require_once __DIR__ . '/../db_config.php';

header('Content-Type: text/plain; charset=utf-8');

$results = [];

function ok(string $msg): void {
    global $results;
    $results[] = "[OK]  " . $msg;
}

function fail(string $msg): void {
    global $results;
    $results[] = "[ERRO] " . $msg;
}

// Verifica duplicidades
$sql = "
    SELECT produto_id, vendedor_id, sabor_id, COUNT(*) AS c
    FROM estoque_vendedores
    GROUP BY produto_id, vendedor_id, sabor_id
    HAVING c > 1
";

try {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        ok('Nenhuma duplicidade em estoque_vendedores por (produto, vendedor, sabor).');
    } else {
        fail('Foram encontradas duplicidades em estoque_vendedores: ' . json_encode($rows));
    }
} catch (Throwable $e) {
    fail('Erro ao verificar invariantes de estoque: ' . $e->getMessage());
}

echo implode("\n", $results), "\n";
