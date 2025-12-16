<?php
require_once __DIR__ . '/session_bootstrap.php';
include 'db_config.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Verifica se o ID do vendedor foi enviado
if (!isset($_POST['vendedor_id'])) {
    echo "ID do vendedor não informado.";
    exit;
}

$vendedor_id = $_POST['vendedor_id'];

// Consulta o nome do vendedor
$query_vendedor = "SELECT nome FROM usuarios WHERE id = :vendedor_id AND nivel_acesso = 'Vendedor'";
$stmt_vendedor = $pdo->prepare($query_vendedor);
$stmt_vendedor->bindParam(':vendedor_id', $vendedor_id);
$stmt_vendedor->execute();
$vendedor = $stmt_vendedor->fetch();

if (!$vendedor) {
    echo "Vendedor não encontrado.";
    exit;
}

// Consulta os produtos, sabores, preços e quantidades do estoque do vendedor
$query_estoque = "
    SELECT p.nome AS produto_nome, s.sabor, IFNULL(e.quantidade, 0) AS quantidade, p.preco
    FROM produtos p
    LEFT JOIN sabores s ON s.produto_id = p.id
    LEFT JOIN estoque_vendedores e ON e.produto_id = p.id AND e.sabor_id = s.id AND e.vendedor_id = :vendedor_id
    WHERE e.quantidade > 0
    ORDER BY p.nome, s.sabor
";
$stmt_estoque = $pdo->prepare($query_estoque);
$stmt_estoque->bindParam(':vendedor_id', $vendedor_id);
$stmt_estoque->execute();
$estoque = $stmt_estoque->fetchAll();

if (count($estoque) === 0) {
    echo "Não há produtos em estoque para este vendedor.";
    exit;
}

// Monta o conteúdo do arquivo TXT
$conteudo = "Estoque do Vendedor: " . $vendedor['nome'] . "\n\n";

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

    $produtos_agrupados[$produto_nome]['sabores'][] = "$sabor ({$quantidade}x)";
}

foreach ($produtos_agrupados as $produto_nome => $dados) {
    $conteudo .= "Produto: $produto_nome - Preço: R$ " . $dados['preco'] . "\n";
    foreach ($dados['sabores'] as $sabor) {
        $conteudo .= "  - $sabor\n";
    }
    $conteudo .= "\n";
}

// Define o cabeçalho para download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="estoque_' . $vendedor['nome'] . '.txt"');

// Envia o conteúdo
echo $conteudo;
exit;
