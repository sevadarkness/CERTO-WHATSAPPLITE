<?php
include 'db_config.php';

// Verifica se o `produto_id` foi enviado via POST
if (isset($_POST['produto_id'])) {
    $produto_id = $_POST['produto_id'];

    // Consulta os sabores baseados no produto selecionado
    $query_sabores = "SELECT id, sabor FROM sabores WHERE produto_id = :produto_id";
    $stmt_sabores = $pdo->prepare($query_sabores);
    $stmt_sabores->bindParam(':produto_id', $produto_id);
    $stmt_sabores->execute();
    $sabores = $stmt_sabores->fetchAll();

    // Retorna as opções de sabores
    if ($sabores) {
        foreach ($sabores as $sabor) {
            echo "<option value='{$sabor['id']}'>{$sabor['sabor']}</option>";
        }
    } else {
        echo "<option value=''>Nenhum sabor disponível</option>";
    }
}
?>
