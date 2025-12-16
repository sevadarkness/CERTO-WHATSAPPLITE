<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';
include 'menu_navegacao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Consulta os vendedores
$query_vendedores = "SELECT id, nome FROM usuarios WHERE nivel_acesso = 'Vendedor'";
$stmt_vendedores = $pdo->query($query_vendedores);
$vendedores = $stmt_vendedores->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Estoques - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .prejuizo-box {
            padding: 15px;
            border-radius: var(--al-radius-md);
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="al-body">
    <div class="container mt-4">
        <h3 class="text-center">Relatório de Estoques</h3>
        
        <?php foreach ($vendedores as $vendedor): ?>
            <?php
            $query_estoque = "
                SELECT p.nome AS produto_nome, s.sabor, IFNULL(e.quantidade, 0) AS quantidade, p.preco
                FROM produtos p
                LEFT JOIN sabores s ON s.produto_id = p.id
                LEFT JOIN estoque_vendedores e ON e.produto_id = p.id AND e.sabor_id = s.id AND e.vendedor_id = :vendedor_id
                WHERE e.quantidade > 0
                ORDER BY p.nome, s.sabor
            ";
            $stmt_estoque = $pdo->prepare($query_estoque);
            $stmt_estoque->bindParam(':vendedor_id', $vendedor['id']);
            $stmt_estoque->execute();
            $estoque = $stmt_estoque->fetchAll();
            
            if (count($estoque) > 0): ?>
                <div class="prejuizo-box text-center">
                    <strong>Aviso:</strong> Verifique os produtos com baixa quantidade em estoque!
                </div>
                
                <h4 class="mt-4">Vendedor: <?php echo htmlspecialchars($vendedor['nome']); ?></h4>
                <form method="post" action="exportar_estoque.php" class="mb-3">
                    <input type="hidden" name="vendedor_id" value="<?php echo $vendedor['id']; ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Exportar Estoque (TXT)</button>
                </form>
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="thead-dark">
                            <tr>
                                <th>Produto</th>
                                <th>Sabores e Quantidades</th>
                                <th>Preço</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $produtos_agrupados = [];
                            foreach ($estoque as $item) {
                                $produto_nome = $item['produto_nome'];
                                $sabor = $item['sabor'] ?: 'Sem sabor';
                                $quantidade = $item['quantidade'];
                                $preco = number_format($item['preco'], 2, ',', '.');

                                if (!isset($produtos_agrupados[$produto_nome])) {
                                    $produtos_agrupados[$produto_nome] = [
                                        'sabores' => [],
                                        'preco' => $preco
                                    ];
                                }
                                $produtos_agrupados[$produto_nome]['sabores'][] = "$sabor (<strong>{$quantidade}x</strong>)";
                            }
                            foreach ($produtos_agrupados as $produto_nome => $dados): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($produto_nome); ?></td>
                                    <td><?php echo implode('<br>', $dados['sabores']); ?></td>
                                    <td>R$ <?php echo $dados['preco']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    
    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
