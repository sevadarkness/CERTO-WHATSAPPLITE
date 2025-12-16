<?php
function calcularDesconto($compras) {
    $totalPontos = array_sum($compras);
    return round($totalPontos * 0.10, 2);
}

function gerarBarraVisual($compras) {
    $pontos = count($compras);
    $preenchido = str_repeat("🟩", $pontos);
    $vazio = str_repeat("⬜", 10 - $pontos);
    return "[$preenchido$vazio] → $pontos/10 pontos";
}

function gerarMensagemCliente($nome, $compras, $historico) {
    $barra = gerarBarraVisual($compras);
    $msg = "📢 Olá, $nome! Você já tem $barra no seu Cartão Fidelidade da Rede Alabama!\n\n";

    if (count($compras) >= 10) {
        $desconto = calcularDesconto($compras);
        $msg .= "🎉 Cartão completado! Você ganhou R$$desconto de desconto na sua próxima compra!\n\n";
    } else {
        $msg .= "🛍️ Falta pouco para ganhar seu desconto especial!\n\n";
    }

    $msg .= "📎 Histórico:\n";
    foreach ($historico as $h) {
        $msg .= "✅ {$h['data']} – {$h['descricao']}\n";
    }

    $msg .= "\n📲 Continue comprando e resgate seu benefício!";
    return $msg;
}
?>
