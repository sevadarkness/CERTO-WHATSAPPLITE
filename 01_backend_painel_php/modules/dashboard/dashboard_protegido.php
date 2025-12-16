<?php include '../auth/middleware.php'; ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Painel Protegido</title></head>
<body>
<h2>Painel Restrito</h2>
<p>Olá <?= $_SESSION['usuario'] ?>! Nível: <?= $_SESSION['nivel'] ?></p>
</body></html>
