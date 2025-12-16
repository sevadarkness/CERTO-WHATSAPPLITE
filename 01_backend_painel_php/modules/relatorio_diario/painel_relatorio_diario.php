<?php
include 'gerar_mensagem.php';

$dados = [
    'data' => date('Y-m-d'),
    'vendas' => [['nome' => 'LUIZ HENRIQUE', 'valor' => '88,00'], ['nome' => 'ANA LIVIA', 'valor' => '136,00']],
    'comissoes' => [['nome' => 'LUIZ HENRIQUE', 'valor' => '17,60'], ['nome' => 'ANA LIVIA', 'valor' => '27,20']],
    'top_vendedor' => 'ANA LIVIA',
    'estoque' => [['nome' => 'LUIZ HENRIQUE', 'itens' => 13], ['nome' => 'ANA LIVIA', 'itens' => 5]],
    'fidelidade' => '✅',
    'pagamentos' => '✅'
];

$mensagem = gerarRelatorioDiario($dados);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>📤 Relatório Diário (IA/Auto)</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f9f9f9; }
        textarea { width: 100%; height: 300px; font-size: 1rem; }
        button { margin-top: 10px; padding: 10px 20px; font-size: 1rem; }
    </style>
</head>
<body>
    <h2>📤 Relatório Diário (IA/Auto)</h2>
    <textarea id="mensagem"><?= $mensagem ?></textarea>
    <br>
    <button onclick="navigator.clipboard.writeText(document.getElementById('mensagem').value)">📋 Copiar para WhatsApp</button>
    <button onclick="alert('🔗 Envio via API ativado (mock)!')">🚀 Enviar via API</button>
</body>
</html>
