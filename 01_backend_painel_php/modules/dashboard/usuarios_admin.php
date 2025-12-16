<?php include '../auth/middleware.php';
if (!temPermissao('usuarios')) die("Acesso negado.");
$pdo = db();
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Gerenciar Usuários</title></head>
<body>
<h2>👥 Lista de Usuários</h2>
<table border="1" cellpadding="5">
<tr><th>Nome</th><th>Email</th><th>Nível</th><th>Criado em</th></tr>
<?php foreach ($usuarios as $u): ?>
<tr>
<td><?= htmlspecialchars($u['nome']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td><?= $u['nivel'] ?></td>
<td><?= $u['criado_em'] ?></td>
</tr>
<?php endforeach; ?>
<tr><td colspan="4"><a href="usuarios_form.php">➕ Criar novo usuário</a></td></tr>
</table>
</body></html>
