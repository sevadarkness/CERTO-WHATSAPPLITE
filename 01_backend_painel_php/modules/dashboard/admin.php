<?php include '../auth/middleware.php';
include '../auth/db.php';
$pdo = db();

// Dummy metas/financeiro (substituir por tabelas reais se necessário)
$usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$logs = $pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>👑 Painel Admin</title></head>
<body>
<h1>👑 Dashboard Administrativo</h1>
<ul>
  <li>Total de usuários ativos: <?= $usuarios ?></li>
  <li>Logs registrados: <?= $logs ?></li>
  <li><a href="bi_dashboard.php">📊 Ver painel BI completo</a></li>
  <li><a href="logs_admin.php">📜 Ver auditoria/logs</a></li>
  <li><a href="usuarios_admin.php">👥 Gerenciar usuários</a></li>
</ul>
</body></html>
