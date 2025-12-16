<?php
include 'db_conn.php';
$pdo = getPDO();

$data_filtro = $_GET['data'] ?? '';
$vendedor_filtro = $_GET['nome'] ?? '';
$order_by = $_GET['ordem'] ?? 'data';
$direction = strtoupper($_GET['dir'] ?? 'DESC');
$page = max(1, intval($_GET['pagina'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM envios WHERE 1=1";
$params = [];

if (!empty($data_filtro)) {
    $sql .= " AND data = ?";
    $params[] = $data_filtro;
}
if (!empty($vendedor_filtro)) {
    $sql .= " AND nome LIKE ?";
    $params[] = "%" . $vendedor_filtro . "%";
}

$total = $pdo->prepare(str_replace("SELECT *", "SELECT COUNT(*) as total", $sql));
$total->execute($params);
$total_rows = $total->fetchColumn();

$sql .= " ORDER BY $order_by $direction LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>📜 Histórico de Envio Fidelidade</title></head>
<body style="font-family: sans-serif;">
<h2>📜 Histórico de Envio de Cartão Fidelidade</h2>

<form method="get">
    Data: <input type="date" name="data" value="<?= htmlspecialchars($data_filtro) ?>">
    Vendedor: <input type="text" name="nome" value="<?= htmlspecialchars($vendedor_filtro) ?>">
    <button type="submit">🔍 Filtrar</button>
</form>
<br>

<table border="1" cellpadding="5" cellspacing="0">
<tr>
  <th><a href="?<?= http_build_query(array_merge($_GET, ['ordem' => 'data', 'dir' => $direction === 'ASC' ? 'DESC' : 'ASC'])) ?>">Data</a></th>
  <th><a href="?<?= http_build_query(array_merge($_GET, ['ordem' => 'nome', 'dir' => $direction === 'ASC' ? 'DESC' : 'ASC'])) ?>">Nome</a></th>
  <th>Número</th>
  <th><a href="?<?= http_build_query(array_merge($_GET, ['ordem' => 'enviado_em', 'dir' => $direction === 'ASC' ? 'DESC' : 'ASC'])) ?>">Horário</a></th>
</tr>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= $r['data'] ?></td>
<td><?= htmlspecialchars($r['nome']) ?></td>
<td><?= $r['numero'] ?></td>
<td><?= $r['enviado_em'] ?></td>
</tr>
<?php endforeach; ?>
</table>

<br>
<div>
<?php
$total_pages = ceil($total_rows / $limit);
for ($p = 1; $p <= $total_pages; $p++) {
    echo '<a href="?' . http_build_query(array_merge($_GET, ['pagina' => $p])) . '">' . $p . '</a> ';
}
?>
</div>
</body></html>
