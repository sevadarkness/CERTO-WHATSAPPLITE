<?php include '../auth/middleware.php';
if (!temPermissao('usuarios')) die("Acesso negado.");
include '../auth/db.php';
registrarLog("Acessou formulário de edição/criação de usuários");

$pdo = db();
$editando = isset($_GET['id']);
$usuario = ['nome' => '', 'email' => '', 'nivel' => 'vendedor'];

if ($editando) {
  $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
  $stmt->execute([$_GET['id']]);
  $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($editando) {
    $stmt = $pdo->prepare("UPDATE usuarios SET nome=?, email=?, nivel=? WHERE id=?");
    $stmt->execute([$_POST['nome'], $_POST['email'], $_POST['nivel'], $_GET['id']]);
    registrarLog("Atualizou usuário " . $_POST['email']);
  } else {
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, nivel) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['nome'], $_POST['email'], $_POST['nivel']]);
    registrarLog("Criou usuário " . $_POST['email']);
  }
  header("Location: usuarios_admin.php");
  exit;
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title><?= $editando ? 'Editar' : 'Novo' ?> Usuário</title></head>
<body>
<h2><?= $editando ? 'Editar' : 'Novo' ?> Usuário</h2>
<?php if ($editando && $_GET['id'] == $_SESSION['id']) { die("Você não pode editar seu próprio perfil aqui."); } ?>
<form method="post">
  Nome: <input name="nome" value="<?= $usuario['nome'] ?>" required><br>
  Email: <input type="email" pattern="[^@]+@[^@]+\.[a-zA-Z]{2,}" name="email" value="<?= $usuario['email'] ?>" required><br>
  Nível:
  <select name="nivel">
    <option <?= $usuario['nivel']=='admin'?'selected':'' ?>>admin</option>
    <option <?= $usuario['nivel']=='vendedor'?'selected':'' ?>>vendedor</option>
    <option <?= $usuario['nivel']=='suporte'?'selected':'' ?>>suporte</option>
  </select><br><br>
  <button type="submit">Salvar</button>
</form>
</body></html>
