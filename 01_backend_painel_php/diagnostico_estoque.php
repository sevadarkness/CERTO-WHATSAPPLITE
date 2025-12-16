<?php
/**
 * Diagnóstico de estoque entre tabelas legado (`estoques`) e canônica (`estoque_vendedores`).
 * Apenas leitura – não faz ajustes automáticos.
 */

require_once __DIR__ . '/rbac.php';
require_role(array('Administrador', 'Gerente'));

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Estoque - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
</head>
<body class="al-body">
<?php include 'menu_navegacao.php'; ?>

<div class="container my-4">
    <h1 class="mb-3">Diagnóstico de Estoque</h1>
    <p class="text-muted">
        Comparação entre as tabelas <code>estoques</code> (legado) e <code>estoque_vendedores</code> (nova canônica).
        Utilize esta página para revisar divergências antes de qualquer ajuste manual.
    </p>

    <?php
    // Divergências de quantidade quando há registro nas duas tabelas
    $sqlDiff = "
        SELECT 
            COALESCE(e.produto_id, ev.produto_id) AS produto_id,
            COALESCE(e.vendedor_id, ev.vendedor_id) AS vendedor_id,
            COALESCE(e.sabor_id, ev.sabor_id) AS sabor_id,
            e.quantidade  AS qtd_estoques,
            ev.quantidade AS qtd_estoque_vendedores
        FROM estoques e
        INNER JOIN estoque_vendedores ev
            ON ev.produto_id = e.produto_id
           AND ev.vendedor_id = e.vendedor_id
           AND ((ev.sabor_id IS NULL AND e.sabor_id IS NULL) OR ev.sabor_id = e.sabor_id)
        WHERE e.quantidade <> ev.quantidade
        ORDER BY produto_id, vendedor_id, sabor_id
        LIMIT 500
    ";
    $diff = $pdo->query($sqlDiff)->fetchAll(PDO::FETCH_ASSOC);

    // Registros apenas em estoques
    $sqlOnlyE = "
        SELECT e.produto_id, e.vendedor_id, e.sabor_id, e.quantidade
        FROM estoques e
        LEFT JOIN estoque_vendedores ev
          ON ev.produto_id = e.produto_id
         AND ev.vendedor_id = e.vendedor_id
         AND ((ev.sabor_id IS NULL AND e.sabor_id IS NULL) OR ev.sabor_id = e.sabor_id)
        WHERE ev.id IS NULL
        ORDER BY e.produto_id, e.vendedor_id, e.sabor_id
        LIMIT 500
    ";
    $onlyE = $pdo->query($sqlOnlyE)->fetchAll(PDO::FETCH_ASSOC);

    // Registros apenas em estoque_vendedores
    $sqlOnlyEv = "
        SELECT ev.produto_id, ev.vendedor_id, ev.sabor_id, ev.quantidade
        FROM estoque_vendedores ev
        LEFT JOIN estoques e
          ON e.produto_id = ev.produto_id
         AND e.vendedor_id = ev.vendedor_id
         AND ((e.sabor_id IS NULL AND ev.sabor_id IS NULL) OR e.sabor_id = ev.sabor_id)
        WHERE e.id IS NULL
        ORDER BY ev.produto_id, ev.vendedor_id, ev.sabor_id
        LIMIT 500
    ";
    $onlyEv = $pdo->query($sqlOnlyEv)->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="row">
        <div class="col-12 col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">Divergências de quantidade</div>
                <div class="card-body p-0">
                    <?php if (empty($diff)): ?>
                        <p class="p-3 text-muted mb-0">Nenhuma divergência encontrada entre <code>estoques</code> e <code>estoque_vendedores</code>.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Vendedor</th>
                                    <th>Sabor</th>
                                    <th>Qtd (estoques)</th>
                                    <th>Qtd (estoque_vendedores)</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($diff as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['produto_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['vendedor_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['sabor_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['qtd_estoques'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['qtd_estoque_vendedores'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6 mb-4">
            <div class="card mb-3">
                <div class="card-header">Somente em <code>estoques</code></div>
                <div class="card-body p-0">
                    <?php if (empty($onlyE)): ?>
                        <p class="p-3 text-muted mb-0">Nenhum registro exclusivo em <code>estoques</code>.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Vendedor</th>
                                    <th>Sabor</th>
                                    <th>Quantidade</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($onlyE as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['produto_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['vendedor_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['sabor_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['quantidade'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Somente em <code>estoque_vendedores</code></div>
                <div class="card-body p-0">
                    <?php if (empty($onlyEv)): ?>
                        <p class="p-3 text-muted mb-0">Nenhum registro exclusivo em <code>estoque_vendedores</code>.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Vendedor</th>
                                    <th>Sabor</th>
                                    <th>Quantidade</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($onlyEv as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['produto_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['vendedor_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['sabor_id'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['quantidade'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <p class="mt-3 text-muted"><small>Qualquer ajuste deve ser feito com base no estoque físico real e no processo operacional da Rede Alabama.</small></p>

</div>

</body>
</html>
