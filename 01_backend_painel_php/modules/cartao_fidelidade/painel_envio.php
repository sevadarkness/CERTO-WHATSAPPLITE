<?php
include 'gerar_fidelidade.php';
include '../relatorio_diario/enviar_whatsapp_api.php';

$nome = "Beatriz";
$numero = "5599999999999";
$compras = [20, 22, 19, 18, 24]; // valores reais
$historico = [
    ['data' => '12/06', 'descricao' => '1 POD'],
    ['data' => '14/06', 'descricao' => '2 PODs'],
    ['data' => '20/06', 'descricao' => '1 POD']
];

$mensagem = gerarMensagemCliente($nome, $compras, $historico);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>📨 Enviar Cartão Fidelidade</title>
</head>
<body style="font-family: monospace;">
<h2>📨 Enviar Cartão Fidelidade para <?= $nome ?></h2>
<textarea rows="16" cols="100"><?= $mensagem ?></textarea><br><br>
<button onclick="navigator.clipboard.writeText(document.querySelector('textarea').value)">📋 Copiar</button>
<button onclick="alert('📤 Enviado via API simulada')">🚀 Enviar via WhatsApp</button>
</body>
</html>
