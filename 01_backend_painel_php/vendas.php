<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';
include 'menu_navegacao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Consulta os produtos mais vendidos com seus sabores e a quantidade total de vendas por sabor
$query_produtos_vendidos = "
    SELECT
        p.nome AS produto_nome,
        IFNULL(s.sabor, 'Sem sabor definido') AS sabor_nome,
        COUNT(v.id) AS vezes_relacionado,
        SUM(v.quantidade) AS total_quantidade,
        COUNT(v.id) AS total_vendas_produto
    FROM vendas v
    JOIN produtos p ON v.produto_id = p.id
    LEFT JOIN sabores s ON v.sabor_id = s.id
    GROUP BY p.nome, sabor_nome
    ORDER BY total_vendas_produto DESC, vezes_relacionado DESC
";

$stmt_produtos_vendidos = $pdo->query($query_produtos_vendidos);
$produtos_vendidos = $stmt_produtos_vendidos->fetchAll();

// Organiza os dados em um formato de array, agrupando por produto
$produtos_grouped = [];
foreach ($produtos_vendidos as $row) {
    $produtos_grouped[$row['produto_nome']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Vendas - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <style>
        .produto-card {
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .produto-card:hover {
            transform: translateY(-5px);
        }

        .produto-header {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .produto-rank {
            font-size: 20px;
            font-weight: bold;
        }

        .toggle-button {
            font-size: 14px;
            cursor: pointer;
            text-decoration: underline;
        }

        .sabor-list {
            list-style-type: none;
            padding-left: 0;
            display: none;
        }

        .sabor-item {
            border-radius: var(--al-radius-sm);
            margin: 5px 0;
            font-size: 14px;
            padding: 10px;
        }

        .vezes-relacionado {
            font-size: 12px;
        }
</head>
<body class="al-body">
    <script <?php echo alabama_csp_nonce_attr(); ?>>
        // Função para mostrar/esconder sabores
        function toggleSabores(produtoId) {
            var saboresList = document.getElementById('sabores-' + produtoId);
            var button = document.getElementById('toggle-btn-' + produtoId);
            if (saboresList.style.display === 'none') {
                saboresList.style.display = 'block';
                button.textContent = 'Esconder Sabores';
            } else {
                saboresList.style.display = 'none';
                button.textContent = 'Mostrar Sabores';
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h2>Top Produtos e Sabores Mais Vendidos</h2>

        <div class="row">
            <?php
            $rank = 1; // Inicializa o rank
            foreach ($produtos_grouped as $produto_nome => $sabores) :
                $total_vendas_produto = 0; // Soma o número de registros de vendas do produto
                foreach ($sabores as $sabor) {
                    $total_vendas_produto += $sabor['total_vendas_produto'];
                }
            ?>
                <div class="col-md-6">
                    <!-- Card do Produto -->
                    <div class="produto-card">
                        <div class="produto-header">
                            <span class="produto-rank"><?= $rank++ ?> - <?= htmlspecialchars($produto_nome) ?></span>
                            <span class="total-vendas-produto"><?= $total_vendas_produto ?> Total Vendidos</span>
                        </div>

                        <!-- Botão para mostrar/esconder sabores -->
                        <div>
                            <span class="toggle-button" id="toggle-btn-<?= $rank ?>" onclick="toggleSabores(<?= $rank ?>)">Mostrar Sabores</span>
                        </div>

                        <!-- Lista de Sabores -->
                        <ul class="sabor-list" id="sabores-<?= $rank ?>">
                            <?php foreach ($sabores as $sabor) : ?>
                                <li class="sabor-item">
                                    <strong><?= htmlspecialchars($sabor['sabor_nome']) ?></strong>
                                    - <?= $sabor['total_quantidade'] ?> Unidades Vendidas
                                    <div class="vezes-relacionado"><?= $sabor['vezes_relacionado'] ?> Vezes Relacionado</div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
