<?php
declare(strict_types=1);

/**
 * Alabama Sidebar Menu - Navegação Inteligente
 * Menu lateral colapsável com categorias e favoritos
 */

// Garante que está em contexto de sessão válida
if (!isset($_SESSION['usuario_id'])) {
    return;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$nivel = (string)($_SESSION['nivel_acesso'] ?? 'Vendedor');

// Define estrutura do menu com 10 categorias
$menuCategories = [
    'dashboard' => [
        'icon' => 'fa-chart-pie',
        'label' => '📊 Dashboard',
        'roles' => ['Administrador', 'Gerente', 'Vendedor'],
        'items' => [
            ['url' => 'index.php', 'label' => 'Home', 'icon' => 'fa-home', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'painel_admin.php', 'label' => 'Painel Admin', 'icon' => 'fa-cogs', 'roles' => ['Administrador']],
            ['url' => 'painel_gerente.php', 'label' => 'Painel Gerente', 'icon' => 'fa-user-tie', 'roles' => ['Gerente']],
            ['url' => 'painel_vendedor.php', 'label' => 'Minhas Vendas', 'icon' => 'fa-tachometer-alt', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'dashboard_analytics.php', 'label' => 'Analytics', 'icon' => 'fa-chart-line', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'dashboard_supremacy.php', 'label' => 'Supremacy', 'icon' => 'fa-crown', 'roles' => ['Administrador', 'Gerente']],
        ]
    ],
    'crm' => [
        'icon' => 'fa-users',
        'label' => '👥 CRM',
        'roles' => ['Administrador', 'Gerente', 'Vendedor'],
        'items' => [
            ['url' => 'leads.php', 'label' => 'Leads', 'icon' => 'fa-user-plus', 'badge_type' => 'new_leads', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'base_clientes.php', 'label' => 'Clientes', 'icon' => 'fa-address-book', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'agenda.php', 'label' => 'Agenda', 'icon' => 'fa-calendar', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'sessoes_atendimento.php', 'label' => 'Atendimento', 'icon' => 'fa-headset', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
        ]
    ],
    'vendas' => [
        'icon' => 'fa-shopping-cart',
        'label' => '💰 Vendas',
        'roles' => ['Administrador', 'Gerente', 'Vendedor'],
        'items' => [
            ['url' => 'nova_venda.php', 'label' => 'Nova Venda', 'icon' => 'fa-cash-register', 'roles' => ['Administrador', 'Gerente', 'Vendedor'], 'highlight' => true],
            ['url' => 'painel_vendedor_hoje.php', 'label' => 'Operação Hoje', 'icon' => 'fa-calendar-day', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'vendas.php', 'label' => 'Top Vendas', 'icon' => 'fa-trophy', 'roles' => ['Administrador', 'Gerente']],
        ]
    ],
    'marketing' => [
        'icon' => 'fa-bullhorn',
        'label' => '📢 Marketing',
        'roles' => ['Administrador', 'Gerente'],
        'items' => [
            ['url' => 'REMARK.php', 'label' => 'Remarketing', 'icon' => 'fa-redo', 'badge_type' => 'active_campaigns', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'remarketing_inteligente.php', 'label' => 'Remarketing IA', 'icon' => 'fa-brain', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'catalogo.php', 'label' => 'Catálogo', 'icon' => 'fa-book', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
        ]
    ],
    'whatsapp' => [
        'icon' => 'fa-whatsapp',
        'label' => '💬 WhatsApp',
        'roles' => ['Administrador', 'Gerente', 'Vendedor'],
        'items' => [
            ['url' => 'whatsapp_bot_console.php', 'label' => 'Conversas', 'icon' => 'fa-comments', 'badge_type' => 'unread_messages', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'whatsapp_bot_config.php', 'label' => 'Bot IA', 'icon' => 'fa-robot', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'flows_manager.php', 'label' => 'Fluxos', 'icon' => 'fa-project-diagram', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'whatsapp_handover.php', 'label' => 'Handover', 'icon' => 'fa-user-shield', 'roles' => ['Administrador', 'Gerente']],
        ]
    ],
    'ia' => [
        'icon' => 'fa-brain',
        'label' => '🧠 Inteligência Artificial',
        'roles' => ['Administrador', 'Gerente'],
        'items' => [
            ['url' => 'llm_training_hub.php', 'label' => 'Training Hub', 'icon' => 'fa-graduation-cap', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'ia_insights_dashboard.php', 'label' => 'Insights IA', 'icon' => 'fa-chart-line', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'llm_analytics_dashboard.php', 'label' => 'Analytics IA', 'icon' => 'fa-chart-bar', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'admin_assistant.php', 'label' => 'Assistente IA', 'icon' => 'fa-user-astronaut', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'vendor_ai_prefs.php', 'label' => 'Preferências IA', 'icon' => 'fa-sliders-h', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
        ]
    ],
    'estoque' => [
        'icon' => 'fa-boxes',
        'label' => '📦 Estoque',
        'roles' => ['Administrador', 'Gerente', 'Vendedor'],
        'items' => [
            ['url' => 'estoque_vendedor.php', 'label' => 'Estoque', 'icon' => 'fa-warehouse', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'relatorioestoq.php', 'label' => 'Relatório', 'icon' => 'fa-chart-bar', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'diagnostico_estoque.php', 'label' => 'Diagnóstico', 'icon' => 'fa-search-plus', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
        ]
    ],
    'relatorios' => [
        'icon' => 'fa-file-alt',
        'label' => '📊 Relatórios',
        'roles' => ['Administrador', 'Gerente'],
        'items' => [
            ['url' => 'relatorios.php', 'label' => 'Relatórios', 'icon' => 'fa-file-alt', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'preju.php', 'label' => 'Prejuízo', 'icon' => 'fa-exclamation-triangle', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'audit_dashboard.php', 'label' => 'Auditoria', 'icon' => 'fa-clipboard-list', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
        ]
    ],
    'automacao' => [
        'icon' => 'fa-bolt',
        'label' => '⚡ Automação',
        'roles' => ['Administrador', 'Gerente'],
        'items' => [
            ['url' => 'automation_rules.php', 'label' => 'Regras', 'icon' => 'fa-cogs', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'automation_runner.php', 'label' => 'Runner', 'icon' => 'fa-play-circle', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'jobs_painel.php', 'label' => 'Jobs', 'icon' => 'fa-robot', 'roles' => ['Administrador', 'Gerente']],
            ['url' => 'playbooks.php', 'label' => 'Playbooks', 'icon' => 'fa-book-open', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
            ['url' => 'flow_governance.php', 'label' => 'Governança', 'icon' => 'fa-shield-alt', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
        ]
    ],
    'config' => [
        'icon' => 'fa-cog',
        'label' => '⚙️ Configurações',
        'roles' => ['Administrador'],
        'items' => [
            ['url' => 'painel_admin.php', 'label' => 'Usuários', 'icon' => 'fa-user-cog', 'roles' => ['Administrador']],
            ['url' => 'env_editor.php', 'label' => 'Config .env', 'icon' => 'fa-sliders-h', 'roles' => ['Administrador']],
            ['url' => 'apply_env_dashboard.php', 'label' => 'Status apply-env', 'icon' => 'fa-sync-alt', 'roles' => ['Administrador']],
        ]
    ],
];

// Use existing $current_page if set by parent, otherwise define it
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
}
?>

<aside id="alabama-sidebar" class="alabama-sidebar">
    <div class="alabama-sidebar-header">
        <button type="button" id="alabama-sidebar-toggle" class="alabama-sidebar-toggle" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <span class="alabama-sidebar-brand">
            <i class="fas fa-gem" style="color: var(--al-primary);"></i>
            <span class="alabama-sidebar-brand-text">Alabama</span>
        </span>
    </div>

    <div class="alabama-sidebar-search">
        <button type="button" id="alabama-search-trigger" class="alabama-search-trigger">
            <i class="fas fa-search"></i>
            <span>Buscar... (Ctrl+K)</span>
        </button>
    </div>

    <nav class="alabama-sidebar-nav">
        <?php foreach ($menuCategories as $categoryId => $category): ?>
            <?php 
            // Verifica se o usuário tem acesso a algum item da categoria
            $hasAccess = false;
            if (in_array($nivel, $category['roles'], true)) {
                foreach ($category['items'] as $item) {
                    if (in_array($nivel, $item['roles'], true)) {
                        $hasAccess = true;
                        break;
                    }
                }
            }
            
            if (!$hasAccess) continue;
            ?>
            
            <div class="alabama-sidebar-category" data-category="<?php echo htmlspecialchars($categoryId, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="button" class="alabama-category-toggle" data-category="<?php echo htmlspecialchars($categoryId, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas <?php echo htmlspecialchars($category['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <span class="alabama-category-label"><?php echo htmlspecialchars($category['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <i class="fas fa-chevron-down alabama-category-arrow"></i>
                </button>
                
                <ul class="alabama-category-items">
                    <?php foreach ($category['items'] as $item): ?>
                        <?php if (!in_array($nivel, $item['roles'], true)) continue; ?>
                        
                        <?php 
                        $isActive = ($current_page === $item['url']);
                        $itemClass = 'alabama-menu-item';
                        if ($isActive) $itemClass .= ' active';
                        if (isset($item['highlight']) && $item['highlight']) $itemClass .= ' highlight';
                        ?>
                        
                        <li>
                            <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>" 
                               class="<?php echo $itemClass; ?>"
                               data-url="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>"
                               data-label="<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>"
                               data-icon="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                <span class="alabama-item-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if (isset($item['badge_type'])): ?>
                                    <span class="alabama-badge" data-badge-type="<?php echo htmlspecialchars($item['badge_type'], ENT_QUOTES, 'UTF-8'); ?>">0</span>
                                <?php endif; ?>
                                <button type="button" class="alabama-favorite-btn" data-favorited="false" aria-label="Add to favorites">
                                    <i class="far fa-star"></i>
                                </button>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="alabama-sidebar-footer">
        <button type="button" id="alabama-sidebar-collapse" class="alabama-sidebar-collapse-btn" aria-label="Collapse Sidebar">
            <i class="fas fa-angle-double-left"></i>
            <span>Minimizar</span>
        </button>
    </div>
</aside>

<div id="alabama-sidebar-overlay" class="alabama-sidebar-overlay"></div>
