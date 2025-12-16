<?php
function geraListaVendas($dados) {
    $out = '';
    foreach ($dados['vendas'] as $v) {
        $out .= "* 🧑🏻‍💼{$v['nome']}: {$v['valor']} R$\n";
    }
    return $out;
}

function geraListaComissoes($dados) {
    $out = '';
    foreach ($dados['comissoes'] as $c) {
        $out .= "* 🟢 {$c['nome']}: {$c['valor']} R$\n";
    }
    return $out;
}

function geraEstoque($dados) {
    $out = '';
    foreach ($dados['estoque'] as $e) {
        $out .= "* 📊 {$e['nome']}: {$e['itens']} itens\n";
    }
    return $out;
}

function gerarRelatorioDiario($dados) {
    return <<<TXT
❌ RELATÓRIO DIÁRIO ❌
📅 {$dados['data']}

🛒 1. VENDAS
{geraListaVendas($dados)}

💰 2. COMISSÕES
{geraListaComissoes($dados)}

🏆 3. VENDEDOR DA SEMANA
🥇 TOP VENDEDOR: {$dados['top_vendedor']}

📦 4. ITENS EM ESTOQUE
{geraEstoque($dados)}

🎫 5. CARTÕES FIDELIDADE E PAGAMENTOREALIZADOS
💳 Cartões Fidelidade: {$dados['fidelidade']}
💵 Pagamentos: {$dados['pagamentos']}

🚀 Avante, Equipe!
TXT;
}
?>
