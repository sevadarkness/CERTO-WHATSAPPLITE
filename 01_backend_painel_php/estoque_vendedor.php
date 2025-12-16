<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';
include 'menu_navegacao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Pega as informações do usuário logado
$usuario_id = $_SESSION['usuario_id'];
$query_usuario = "SELECT id, nome, nivel_acesso FROM usuarios WHERE id = :usuario_id";
$stmt_usuario = $pdo->prepare($query_usuario);
$stmt_usuario->bindParam(':usuario_id', $usuario_id);
$stmt_usuario->execute();
$usuario = $stmt_usuario->fetch();

// Define se o usuário pode selecionar o vendedor
$permitir_selecao_vendedor = ($usuario['nivel_acesso'] == 'Administrador' || $usuario['nivel_acesso'] == 'Gerente');

// Determina o ID do vendedor (do filtro ou do usuário logado)
$vendedor_id = null;

if ($permitir_selecao_vendedor) {
    // Para Administrador ou Gerente: Pega o ID do vendedor selecionado no filtro
    $vendedor_id = isset($_GET['vendedor_id']) ? $_GET['vendedor_id'] : null;
} else {
    // Para Vendedores: Exibe apenas o estoque do próprio usuário
    $vendedor_id = $usuario_id;
}

// Verifica se um vendedor foi selecionado
$erro_vendedor_nao_selecionado = !$vendedor_id;

// Se um vendedor foi selecionado, consulta os produtos do estoque
if (!$erro_vendedor_nao_selecionado) {
    $query_produtos = "
        SELECT p.id, p.nome, p.preco, IFNULL(SUM(e.quantidade), 0) AS quantidade_estoque 
    FROM produtos p
    LEFT JOIN estoque_vendedores e ON p.id = e.produto_id 
    AND e.vendedor_id = :vendedor_id
    WHERE p.id IN (SELECT produto_id FROM sabores) -- Apenas produtos com sabores válidos
    GROUP BY p.id
    ";
    $stmt_produtos = $pdo->prepare($query_produtos);
    $stmt_produtos->bindParam(':vendedor_id', $vendedor_id);
    $stmt_produtos->execute();
    $produtos = $stmt_produtos->fetchAll();
} else {
    $produtos = [];
}

// Consulta os vendedores para exibir no filtro (somente para administrador/gerente)
if ($permitir_selecao_vendedor) {
    $query_vendedores = "SELECT id, nome FROM usuarios WHERE nivel_acesso = 'Vendedor'";
    $stmt_vendedores = $pdo->query($query_vendedores);
    $vendedores = $stmt_vendedores->fetchAll();
} else {
    $vendedores = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque de Produtos - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <style>
        @media (max-width: 768px) {
            .form-group {
                max-width: 100%;
            }
            .table {
                font-size: 0.9rem;
            }
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h3>Estoque de Produtos</h3>

        <?php if (isset($_GET['estoque_atualizado']) && $_GET['estoque_atualizado'] == 'true'): ?>
            <div class="alert alert-success" role="alert">
                <strong>Estoque atualizado com sucesso!</strong>
            </div>
        <?php endif; ?>

        <?php if ($permitir_selecao_vendedor): ?>
            <form method="GET" class="form-group">
                <label for="vendedor_id">Selecione o Vendedor:</label>
                <select name="vendedor_id" id="vendedor_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Escolha um vendedor</option>
                    <?php foreach ($vendedores as $vendedor): ?>
                        <option value="<?php echo $vendedor['id']; ?>" <?php echo $vendedor_id == $vendedor['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vendedor['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>

        <?php if ($erro_vendedor_nao_selecionado): ?>
            <div class="alert alert-warning" role="alert">
                <strong>Atenção!</strong> Você precisa selecionar um vendedor para visualizar o estoque.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Preço</th>
                            <th>Quantidade em Estoque</th>
                            <th>Sabores</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                                <td>R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
                                <td><?php echo $produto['quantidade_estoque']; ?></td>
                                <td>
                                    <?php
                                    $query_sabores = "
                                        SELECT s.sabor
                                        FROM sabores s
                                        WHERE s.produto_id = :produto_id
                                    ";
                                    $stmt_sabores = $pdo->prepare($query_sabores);
                                    $stmt_sabores->bindParam(':produto_id', $produto['id']);
                                    $stmt_sabores->execute();
                                    $sabores = $stmt_sabores->fetchAll();
                                    foreach ($sabores as $sabor) {
                                        echo htmlspecialchars($sabor['sabor']) . "<br>";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="editar_estoque.php?id=<?php echo $produto['id']; ?>&vendedor_id=<?php echo $vendedor_id; ?>" class="btn btn-editar">Editar Estoque</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
