<?php
declare(strict_types=1);

/**
 * Alabama Navigation System - Demo Page
 * Demonstra todas as funcionalidades do sistema de navegação
 */

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/acesso_restrito.php';

$page_title = 'Demo do Sistema de Navegação';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> - Alabama CMS</title>
    
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
</head>
<body>
    <?php 
    // Inclui o sidebar e busca global
    include __DIR__ . '/includes/sidebar_menu.php';
    include __DIR__ . '/includes/global_search.php';
    ?>
    
    <div class="alabama-main-wrapper">
        <main class="alabama-content">
            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-12">
                        <h1 class="mb-2">
                            <i class="fas fa-compass text-primary"></i>
                            Sistema de Navegação Inteligente
                        </h1>
                        <p class="text-muted">Demonstração completa de todas as funcionalidades</p>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Sidebar -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bars"></i> Sidebar Menu</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">Funcionalidades:</h6>
                                <ul class="mb-3">
                                    <li>✅ 280px de largura (padrão)</li>
                                    <li>✅ 10 categorias organizadas</li>
                                    <li>✅ ~45 páginas no total</li>
                                    <li>✅ Visibilidade baseada em roles</li>
                                    <li>✅ Estado salvo no localStorage</li>
                                    <li>✅ Modo mini (70px, só ícones)</li>
                                    <li>✅ Badges de notificações</li>
                                    <li>✅ Sistema de favoritos</li>
                                    <li>✅ Responsivo (drawer em mobile)</li>
                                </ul>
                                
                                <h6 class="text-primary">Como testar:</h6>
                                <ol>
                                    <li>Clique nas categorias para expandir/recolher</li>
                                    <li>Clique em "Minimizar" no rodapé para modo mini</li>
                                    <li>Passe o mouse sobre itens para ver botão de favorito</li>
                                    <li>Redimensione a janela para ver comportamento mobile</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Global Search -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-search"></i> Busca Global</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">Funcionalidades:</h6>
                                <ul class="mb-3">
                                    <li>✅ Abre com <kbd>Ctrl+K</kbd> ou <kbd>Cmd+K</kbd></li>
                                    <li>✅ Busca em tempo real</li>
                                    <li>✅ Filtra por nome, categoria ou URL</li>
                                    <li>✅ Navegação por teclado (↑↓)</li>
                                    <li>✅ <kbd>Enter</kbd> para abrir página</li>
                                    <li>✅ <kbd>ESC</kbd> para fechar</li>
                                    <li>✅ Click fora fecha o modal</li>
                                </ul>
                                
                                <h6 class="text-primary">Como testar:</h6>
                                <ol>
                                    <li>Pressione <kbd>Ctrl+K</kbd> para abrir</li>
                                    <li>Digite "vendas" para filtrar</li>
                                    <li>Use ↑↓ para navegar</li>
                                    <li>Pressione <kbd>Enter</kbd> ou clique para abrir</li>
                                </ol>
                                
                                <button type="button" id="demo-search-btn" class="btn btn-primary mt-2">
                                    <i class="fas fa-search"></i> Abrir Busca (Ctrl+K)
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Badges -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bell"></i> Badges de Notificação</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">Funcionalidades:</h6>
                                <ul class="mb-3">
                                    <li>✅ Atualização via AJAX a cada 30s</li>
                                    <li>✅ <code>new_leads</code>: Leads criados hoje</li>
                                    <li>✅ <code>unread_messages</code>: Conversas não lidas</li>
                                    <li>✅ <code>active_campaigns</code>: Campanhas ativas</li>
                                    <li>✅ <code>pending_tasks</code>: Tarefas pendentes</li>
                                </ul>
                                
                                <h6 class="text-primary">API Endpoint:</h6>
                                <code>GET api/menu_badges.php</code>
                                
                                <h6 class="text-primary mt-3">Exemplo de resposta:</h6>
                                <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": true,
  "badges": {
    "new_leads": 5,
    "unread_messages": 12,
    "active_campaigns": 3,
    "pending_tasks": 0
  },
  "timestamp": 1702744800
}</code></pre>
                            </div>
                        </div>
                    </div>

                    <!-- Favorites -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-star"></i> Sistema de Favoritos</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">Funcionalidades:</h6>
                                <ul class="mb-3">
                                    <li>✅ Adicionar/remover favoritos</li>
                                    <li>✅ Reordenar favoritos (drag & drop futuro)</li>
                                    <li>✅ Persistência no banco de dados</li>
                                    <li>✅ Ícone muda ao favoritar</li>
                                </ul>
                                
                                <h6 class="text-primary">API Endpoints:</h6>
                                <ul>
                                    <li><code>GET api/favorites.php</code> - Lista favoritos</li>
                                    <li><code>POST api/favorites.php</code> - Adiciona/remove/reordena</li>
                                </ul>
                                
                                <h6 class="text-primary mt-3">Como testar:</h6>
                                <ol>
                                    <li>Passe o mouse sobre qualquer item do menu</li>
                                    <li>Clique no ícone de estrela que aparece</li>
                                    <li>O ícone ficará amarelo quando favoritado</li>
                                    <li>Clique novamente para remover</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Responsive -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-mobile-alt"></i> Design Responsivo</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="text-primary">Breakpoints:</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="p-3 bg-success bg-opacity-10 rounded">
                                            <strong>Desktop (>1024px)</strong>
                                            <p class="mb-0 small">Sidebar fixo à esquerda</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 bg-warning bg-opacity-10 rounded">
                                            <strong>Tablet (768-1024px)</strong>
                                            <p class="mb-0 small">Sidebar drawer com overlay</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 bg-danger bg-opacity-10 rounded">
                                            <strong>Mobile (<768px)</strong>
                                            <p class="mb-0 small">Sidebar fullscreen drawer</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <h6 class="text-primary mt-4">Como testar:</h6>
                                <ol>
                                    <li>Abra o DevTools (F12)</li>
                                    <li>Ative o modo responsivo (Ctrl+Shift+M)</li>
                                    <li>Teste diferentes tamanhos de tela</li>
                                    <li>Em mobile, use o botão hamburger para abrir sidebar</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- Keyboard Shortcuts -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-keyboard"></i> Atalhos de Teclado</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Atalho</th>
                                                <th>Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><kbd>Ctrl+K</kbd> ou <kbd>Cmd+K</kbd></td>
                                                <td>Abrir busca global</td>
                                            </tr>
                                            <tr>
                                                <td><kbd>ESC</kbd></td>
                                                <td>Fechar busca global</td>
                                            </tr>
                                            <tr>
                                                <td><kbd>↑</kbd> <kbd>↓</kbd></td>
                                                <td>Navegar resultados da busca</td>
                                            </tr>
                                            <tr>
                                                <td><kbd>Enter</kbd></td>
                                                <td>Abrir página selecionada</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- CSRF Token -->
    <?php if (function_exists('csrf_token')): ?>
    <script>
        window.AL_BAMA_CSRF_TOKEN = "<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>";
    </script>
    <?php endif; ?>
    
    <!-- Alabama Navigation JS -->
    <script src="assets/js/navigation.js"></script>
    
    <script>
        // Demo: Trigger search with button
        document.getElementById('demo-search-btn')?.addEventListener('click', function() {
            document.getElementById('alabama-search-trigger')?.click();
        });
    </script>
</body>
</html>
