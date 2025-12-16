<?php
declare(strict_types=1);

/**
 * Alabama Layout Header
 * Header comum para todas as páginas do painel
 */

// Garante que está em contexto de sessão válida
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') . ' - ' : ''; ?>Alabama CMS</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Alabama Theme -->
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-navigation.css">
    
    <?php if (isset($extra_css)): ?>
        <?php foreach ((array)$extra_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($css_file, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <?php 
    // Inclui o sidebar e busca global
    if (file_exists(__DIR__ . '/sidebar_menu.php')) {
        include __DIR__ . '/sidebar_menu.php';
    }
    
    if (file_exists(__DIR__ . '/global_search.php')) {
        include __DIR__ . '/global_search.php';
    }
    ?>
    
    <div class="alabama-main-wrapper">
        <?php 
        // Inclui o menu de navegação tradicional se necessário
        // (pode ser removido futuramente quando sidebar estiver completo)
        ?>
        
        <main class="alabama-content">
