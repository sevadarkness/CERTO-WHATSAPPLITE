<?php
include 'db_conn.php';
include 'gerar_fidelidade.php';
include '../relatorio_diario/enviar_whatsapp_api.php';

$pdo = getPDO();
$dataHoje = date('Y-m-d');

// Simula base real de vendas do dia
$vendedores = [
  ['nome' => 'Beatriz', 'numero' => '5599999999999', 'compras' => [22], 'historico' => [['data' => date('d/m'), 'descricao' => '1 POD']]],
  ['nome' => 'Luiz Henrique', 'numero' => '5588888888888', 'compras' => [18], 'historico' => [['data' => date('d/m'), 'descricao' => '1 POD']]]
];

foreach ($vendedores as $v) {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM envios WHERE data = ? AND numero = ?");
  $stmt->execute([$dataHoje, $v['numero']]);
  $alreadySent = $stmt->fetchColumn();

  if (!$alreadySent) {
    echo "Enviando fidelidade para {$v['nome']}\n";
    enviarCartaoFidelidadeCliente($v['numero'], $v['nome'], $v['compras'], $v['historico']);
    $pdo->prepare("INSERT INTO envios (data, numero, nome, enviado_em) VALUES (?, ?, ?, ?)")->execute([
      $dataHoje, $v['numero'], $v['nome'], date('H:i:s')
    ]);
  } else {
    echo "Já enviado para {$v['nome']} hoje. Pulando...\n";
  }
}
?>
