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

// Pega o ID do produto e do vendedor
$id_produto = isset($_GET['id']) ? $_GET['id'] : null;
$vendedor_id = isset($_GET['vendedor_id']) ? $_GET['vendedor_id'] : null;

// Se o produto ou vendedor não for encontrado, redireciona de volta
if (!$id_produto || !$vendedor_id) {
    header("Location: estoque_vendedor.php");
    exit;
}

// Consulta os sabores disponíveis para o produto, associando com o estoque do vendedor
$query_sabores = "
    SELECT s.id AS sabor_id, s.sabor, e.quantidade 
    FROM sabores s
    LEFT JOIN estoque_vendedores e ON e.produto_id = s.produto_id AND e.sabor_id = s.id AND e.vendedor_id = :vendedor_id
    WHERE s.produto_id = :produto_id
";
$stmt_sabores = $pdo->prepare($query_sabores);
$stmt_sabores->bindParam(':produto_id', $id_produto);
$stmt_sabores->bindParam(':vendedor_id', $vendedor_id);
$stmt_sabores->execute();
$sabores = $stmt_sabores->fetchAll();

// Consulta o nome do vendedor
$query_vendedor = "SELECT nome FROM usuarios WHERE id = :vendedor_id";
$stmt_vendedor = $pdo->prepare($query_vendedor);
$stmt_vendedor->bindParam(':vendedor_id', $vendedor_id);
$stmt_vendedor->execute();
$vendedor = $stmt_vendedor->fetch();

// Consulta o nome do produto
$query_produto = "SELECT nome FROM produtos WHERE id = :produto_id";
$stmt_produto = $pdo->prepare($query_produto);
$stmt_produto->bindParam(':produto_id', $id_produto);
$stmt_produto->execute();
$produto = $stmt_produto->fetch();

// Atualiza o estoque do sabor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['quantidade'] as $sabor_id => $quantidade) {
        if ($quantidade !== "") {
            // Verifica se o estoque já existe ou se deve ser inserido
            $query_check = "SELECT id FROM estoque_vendedores WHERE sabor_id = :sabor_id AND vendedor_id = :vendedor_id AND produto_id = :produto_id";
            $stmt_check = $pdo->prepare($query_check);
            $stmt_check->bindParam(':sabor_id', $sabor_id);
            $stmt_check->bindParam(':vendedor_id', $vendedor_id);
            $stmt_check->bindParam(':produto_id', $id_produto);
            $stmt_check->execute();
            $estoque = $stmt_check->fetch();

            if ($estoque) {
                // Atualiza o estoque existente
                $query_update = "UPDATE estoque_vendedores SET quantidade = :quantidade WHERE sabor_id = :sabor_id AND vendedor_id = :vendedor_id AND produto_id = :produto_id";
                $stmt_update = $pdo->prepare($query_update);
                $stmt_update->bindParam(':quantidade', $quantidade);
                $stmt_update->bindParam(':sabor_id', $sabor_id);
                $stmt_update->bindParam(':vendedor_id', $vendedor_id);
                $stmt_update->bindParam(':produto_id', $id_produto);
                $stmt_update->execute();
            } else {
                // Insere um novo registro no estoque
                $query_insert = "INSERT INTO estoque_vendedores (produto_id, sabor_id, vendedor_id, quantidade) 
                                 VALUES (:produto_id, :sabor_id, :vendedor_id, :quantidade)";
                $stmt_insert = $pdo->prepare($query_insert);
                $stmt_insert->bindParam(':produto_id', $id_produto);
                $stmt_insert->bindParam(':sabor_id', $sabor_id);
                $stmt_insert->bindParam(':vendedor_id', $vendedor_id);
                $stmt_insert->bindParam(':quantidade', $quantidade);
                $stmt_insert->execute();
            }
        }
    }
    header("Location: estoque_vendedor.php?estoque_atualizado=true");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="alabama-theme.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Estoque</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
	    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/inputmask/5.0.6/inputmask.min.js"></script>
		  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-4">
        <h3>Editar Estoque de Produto</h3>

        <p><strong>Produto:</strong> <?php echo htmlspecialchars($produto['nome']); ?></p>
        <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($vendedor['nome']); ?></p>

        <form method="POST">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Sabor</th>
                        <th>Quantidade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sabores as $sabor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sabor['sabor']); ?></td>
                            <td>
                                <input type="number" name="quantidade[<?php echo $sabor['sabor_id']; ?>]" value="<?php echo $sabor['quantidade']; ?>" class="form-control">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" class="btn btn-success">Salvar</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
