<?php
include 'gerar_fidelidade.php';

$nome = "Beatriz";
$compras = [20, 22, 30, 28, 18];
$historico = [
  ['data' => '12/06', 'descricao' => '1 POD'],
  ['data' => '14/06', 'descricao' => '2 PODs'],
  ['data' => '20/06', 'descricao' => '1 POD']
];
$mensagem = gerarMensagemCliente($nome, $compras, $historico);
$barra = gerarBarraVisual($compras);
?>

<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Fidelidade</title></head>
<body style="font-family:monospace">
<h2>📋 Cartão Fidelidade – <?= $nome ?></h2>
<p><?= $barra ?></p>
<textarea rows="10" cols="100"><?= $mensagem ?></textarea>
</body>
</html>
