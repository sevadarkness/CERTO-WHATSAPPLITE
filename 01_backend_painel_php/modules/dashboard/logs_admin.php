<?php
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
  include '../auth/db.php';
  $pdo = db();
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment;filename=logs.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Email', 'Ação', 'Data/Hora']);
  $query = $pdo->query("SELECT * FROM logs ORDER BY momento DESC");
  foreach ($query as $row) {
    fputcsv($out, [$row['usuario_email'], $row['acao'], $row['momento']]);
  }
  fclose($out);
  exit;
}
include '../auth/middleware.php';
if (!temPermissao('usuarios')) die("Acesso negado.");
include '../auth/db.php';
$pdo = db();

$filtro = $_GET['email'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM logs WHERE usuario_email LIKE ? ORDER BY momento DESC LIMIT 200");
$stmt->execute(['%' . $filtro . '%']);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Logs de Ação</title></head>
<body>
<h2>📝 Logs de Ações</h2>
<form method="get">
  Filtrar por email: <input name="email" value="<?= htmlspecialchars($filtro) ?>">
  <button type="submit">🔍 Buscar</button>
</form>
<table border="1" cellpadding="5">
<tr><th>Email</th><th>Ação</th><th>Data/Hora</th></tr>
<?php foreach ($logs as $l): ?>
<tr><td><?= $l['usuario_email'] ?></td><td><?= htmlspecialchars($l['acao']) ?></td><td><?= $l['momento'] ?></td></tr>
<?php endforeach; ?>
</table>
</body></html>
