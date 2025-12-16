<?php
include '../auth/db.php';
$pdo = db();

// Gráfico de acessos por dia
$acessos = $pdo->query("SELECT DATE(momento) as dia, COUNT(*) as total FROM logs GROUP BY DATE(momento) ORDER BY dia DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);

// Gráfico de ações mais comuns
$acoes = $pdo->query("SELECT acao, COUNT(*) as total FROM logs GROUP BY acao ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Gráfico de usuários por tipo
$perfis = $pdo->query("SELECT nivel, COUNT(*) as total FROM usuarios GROUP BY nivel")->fetchAll(PDO::FETCH_ASSOC);

// Tempo médio (simulado - precisa session log real)
$tempos = [['nivel'=>'admin','tempo'=>180],['nivel'=>'vendedor','tempo'=>65],['nivel'=>'suporte','tempo'=>95]];

function labels($arr, $key) {
  return json_encode(array_column($arr, $key));
}
function values($arr, $key) {
  return json_encode(array_column($arr, $key));
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<title>📊 Dashboard BI (Real Data)</title>
</head><body>
<h2>📈 Acessos nos últimos dias</h2>
<canvas id="acessos"></canvas>

<h2>🔁 Ações Mais Executadas</h2>
<canvas id="acoes"></canvas>

<h2>🧑‍💼 Distribuição de Perfis</h2>
<canvas id="perfis"></canvas>

<h2>⏳ Tempo Médio de Sessão</h2>
<canvas id="tempos"></canvas>

<script>
new Chart(document.getElementById("acessos"), {
  type: "line",
  data: {
    labels: <?= labels($acessos,'dia') ?>,
    datasets: [{ label: "Acessos", data: <?= values($acessos,'total') ?>, borderColor: "blue", fill: false }]
  }
});
new Chart(document.getElementById("acoes"), {
  type: "bar",
  data: {
    labels: <?= labels($acoes,'acao') ?>,
    datasets: [{ label: "Frequência", data: <?= values($acoes,'total') ?>, backgroundColor: "orange" }]
  }
});
new Chart(document.getElementById("perfis"), {
  type: "pie",
  data: {
    labels: <?= labels($perfis,'nivel') ?>,
    datasets: [{ label: "Usuários", data: <?= values($perfis,'total') ?>, backgroundColor: ["red", "green", "blue"] }]
  }
});
new Chart(document.getElementById("tempos"), {
  type: "bar",
  data: {
    labels: <?= labels($tempos,'nivel') ?>,
    datasets: [{ label: "Minutos", data: <?= values($tempos,'tempo') ?>, backgroundColor: "gray" }]
  }
});
</script>
</body></html>


<h2>💰 Faturamento por Período</h2>
<canvas id="faturamento"></canvas>
<script>
new Chart(document.getElementById("faturamento"), {
  type: "bar",
  data: {
    labels: ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun"],
    datasets: [{
      label: "R$ (mil)",
      data: [12.5, 15.2, 18.9, 21.0, 22.7, 19.8],
      backgroundColor: "rgba(75, 192, 192, 0.7)"
    }]
  },
  options: {
    plugins: {
      title: {
        display: true,
        text: "Faturamento por Mês"
      }
    },
    scales: {
      y: {
        beginAtZero: true
      }
    }
  }
});
</script>

<?php
// Tabelas simuladas: vendas(id, valor, custo, vendedor, data)
$kpi = $pdo->query("SELECT 
    DATE_FORMAT(data, '%Y-%m') as mes,
    SUM(valor) as faturamento,
    SUM(custo) as custo,
    COUNT(*) as vendas
FROM vendas
GROUP BY mes ORDER BY mes DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

// Por vendedor
$por_vendedor = $pdo->query("SELECT vendedor, SUM(valor) as total FROM vendas GROUP BY vendedor")->fetchAll(PDO::FETCH_ASSOC);

function toJSArray($data, $campo) {
    return json_encode(array_column($data, $campo));
}
?>
<h2>📊 KPI de Faturamento Real</h2>
<canvas id="faturamentoReal"></canvas>
<script>
new Chart(document.getElementById("faturamentoReal"), {
  type: "bar",
  data: {
    labels: <?= toJSArray($kpi, 'mes') ?>,
    datasets: [{
      label: "Faturamento (R$)",
      data: <?= toJSArray($kpi, 'faturamento') ?>,
      backgroundColor: "rgba(54, 162, 235, 0.7)"
    }, {
      label: "Custo (R$)",
      data: <?= toJSArray($kpi, 'custo') ?>,
      backgroundColor: "rgba(255, 99, 132, 0.6)"
    }]
  }
});
</script>

<h2>💡 ROI e Custo Médio</h2>
<table border="1" cellpadding="5">
<tr><th>Mês</th><th>Receita</th><th>Custo</th><th>ROI</th><th>Custo Médio</th></tr>
<?php foreach ($kpi as $m): $roi = $m['custo'] > 0 ? round(($m['faturamento'] - $m['custo']) / $m['custo'], 2) : 0;
$media = $m['vendas'] > 0 ? round($m['custo'] / $m['vendas'], 2) : 0; ?>
<tr>
<td><?= $m['mes'] ?></td>
<td>R$<?= number_format($m['faturamento'], 2, ',', '.') ?></td>
<td>R$<?= number_format($m['custo'], 2, ',', '.') ?></td>
<td><?= $roi ?></td>
<td>R$<?= $media ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>👥 Faturamento por Vendedor</h2>
<canvas id="porVendedor"></canvas>
<script>
new Chart(document.getElementById("porVendedor"), {
  type: "bar",
  data: {
    labels: <?= toJSArray($por_vendedor, 'vendedor') ?>,
    datasets: [{
      label: "Total R$",
      data: <?= toJSArray($por_vendedor, 'total') ?>,
      backgroundColor: "rgba(153, 102, 255, 0.7)"
    }]
  }
});
</script>
