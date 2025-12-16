<?php
declare(strict_types=1);

/**
 * Alabama Global Search - Busca Inteligente
 * Modal de busca global com Ctrl+K
 */

// Garante que está em contexto de sessão válida
if (!isset($_SESSION['usuario_id'])) {
    return;
}

$nivel = (string)($_SESSION['nivel_acesso'] ?? 'Vendedor');

// Define todas as páginas pesquisáveis (todas as 45+ páginas do sistema)
$searchablePages = [
    // Dashboard
    ['url' => 'index.php', 'label' => 'Home', 'icon' => 'fa-home', 'category' => 'Dashboard', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'painel_admin.php', 'label' => 'Painel Admin', 'icon' => 'fa-cogs', 'category' => 'Dashboard', 'roles' => ['Administrador']],
    ['url' => 'painel_gerente.php', 'label' => 'Painel Gerente', 'icon' => 'fa-user-tie', 'category' => 'Dashboard', 'roles' => ['Gerente']],
    ['url' => 'painel_vendedor.php', 'label' => 'Minhas Vendas', 'icon' => 'fa-tachometer-alt', 'category' => 'Dashboard', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'dashboard_analytics.php', 'label' => 'Dashboard Analytics', 'icon' => 'fa-chart-line', 'category' => 'Dashboard', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'dashboard_supremacy.php', 'label' => 'Dashboard Supremacy', 'icon' => 'fa-crown', 'category' => 'Dashboard', 'roles' => ['Administrador', 'Gerente']],
    
    // CRM
    ['url' => 'leads.php', 'label' => 'Fila de Leads', 'icon' => 'fa-user-plus', 'category' => 'CRM', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'base_clientes.php', 'label' => 'Base de Clientes', 'icon' => 'fa-address-book', 'category' => 'CRM', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'agenda.php', 'label' => 'Agenda & Compromissos', 'icon' => 'fa-calendar', 'category' => 'CRM', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'sessoes_atendimento.php', 'label' => 'Tempo de Atendimento', 'icon' => 'fa-headset', 'category' => 'CRM', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    
    // Vendas
    ['url' => 'nova_venda.php', 'label' => 'Nova Venda', 'icon' => 'fa-cash-register', 'category' => 'Vendas', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'painel_vendedor_hoje.php', 'label' => 'Minha Operação Hoje', 'icon' => 'fa-calendar-day', 'category' => 'Vendas', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'vendas.php', 'label' => 'Top Vendas', 'icon' => 'fa-trophy', 'category' => 'Vendas', 'roles' => ['Administrador', 'Gerente']],
    
    // Marketing
    ['url' => 'REMARK.php', 'label' => 'Remarketing', 'icon' => 'fa-redo', 'category' => 'Marketing', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'remarketing_inteligente.php', 'label' => 'Remarketing Inteligente', 'icon' => 'fa-brain', 'category' => 'Marketing', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'catalogo.php', 'label' => 'Catálogo de Produtos', 'icon' => 'fa-book', 'category' => 'Marketing', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    
    // WhatsApp
    ['url' => 'whatsapp_bot_console.php', 'label' => 'Conversas WhatsApp', 'icon' => 'fa-comments', 'category' => 'WhatsApp', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'whatsapp_bot_config.php', 'label' => 'Bot WhatsApp IA', 'icon' => 'fa-robot', 'category' => 'WhatsApp', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'flows_manager.php', 'label' => 'Fluxos IA WhatsApp', 'icon' => 'fa-project-diagram', 'category' => 'WhatsApp', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'whatsapp_handover.php', 'label' => 'Handover WhatsApp', 'icon' => 'fa-user-shield', 'category' => 'WhatsApp', 'roles' => ['Administrador', 'Gerente']],
    
    // IA
    ['url' => 'llm_training_hub.php', 'label' => 'LLM Training Hub', 'icon' => 'fa-graduation-cap', 'category' => 'Inteligência Artificial', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'ia_insights_dashboard.php', 'label' => 'Insights IA', 'icon' => 'fa-chart-line', 'category' => 'Inteligência Artificial', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'llm_analytics_dashboard.php', 'label' => 'Analytics IA', 'icon' => 'fa-chart-bar', 'category' => 'Inteligência Artificial', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'admin_assistant.php', 'label' => 'Assistente Interno IA', 'icon' => 'fa-user-astronaut', 'category' => 'Inteligência Artificial', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'vendor_ai_prefs.php', 'label' => 'Minhas Preferências de IA', 'icon' => 'fa-sliders-h', 'category' => 'Inteligência Artificial', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    
    // Estoque
    ['url' => 'estoque_vendedor.php', 'label' => 'Estoque', 'icon' => 'fa-warehouse', 'category' => 'Estoque', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'relatorioestoq.php', 'label' => 'Relatório de Estoque', 'icon' => 'fa-chart-bar', 'category' => 'Estoque', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'diagnostico_estoque.php', 'label' => 'Diagnóstico de Estoque', 'icon' => 'fa-search-plus', 'category' => 'Estoque', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    
    // Relatórios
    ['url' => 'relatorios.php', 'label' => 'Relatórios', 'icon' => 'fa-file-alt', 'category' => 'Relatórios', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'preju.php', 'label' => 'Relatório de Prejuízo', 'icon' => 'fa-exclamation-triangle', 'category' => 'Relatórios', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'audit_dashboard.php', 'label' => 'Dashboard de Auditoria', 'icon' => 'fa-clipboard-list', 'category' => 'Relatórios', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    
    // Automação
    ['url' => 'automation_rules.php', 'label' => 'Regras de Automação', 'icon' => 'fa-cogs', 'category' => 'Automação', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'automation_runner.php', 'label' => 'Runner de Automação', 'icon' => 'fa-play-circle', 'category' => 'Automação', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'jobs_painel.php', 'label' => 'Jobs / Automação', 'icon' => 'fa-robot', 'category' => 'Automação', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'playbooks.php', 'label' => 'Playbooks de Atendimento', 'icon' => 'fa-book-open', 'category' => 'Automação', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    ['url' => 'flow_governance.php', 'label' => 'Governança de Fluxos', 'icon' => 'fa-shield-alt', 'category' => 'Automação', 'roles' => ['Administrador', 'Gerente', 'Vendedor']],
    
    // Configurações
    ['url' => 'env_editor.php', 'label' => 'Editor de .env', 'icon' => 'fa-sliders-h', 'category' => 'Configurações', 'roles' => ['Administrador']],
    ['url' => 'apply_env_dashboard.php', 'label' => 'Status apply-env', 'icon' => 'fa-sync-alt', 'category' => 'Configurações', 'roles' => ['Administrador']],
    
    // Outros
    ['url' => 'frete.php', 'label' => 'Frete', 'icon' => 'fa-truck', 'category' => 'Integração', 'roles' => ['Administrador', 'Gerente']],
    ['url' => 'matching_inteligente.php', 'label' => 'Matching Inteligente', 'icon' => 'fa-route', 'category' => 'Integração', 'roles' => ['Administrador', 'Gerente']],
];

// Filtra páginas com base no nível de acesso do usuário
$userPages = array_filter($searchablePages, function($page) use ($nivel) {
    return in_array($nivel, $page['roles'], true);
});

// Converte para JSON para uso no JavaScript
try {
    $userPagesJson = json_encode(array_values($userPages), JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    // Fallback para array vazio em caso de erro
    $userPagesJson = '[]';
    error_log('Failed to encode search pages: ' . $e->getMessage());
}
?>

<div id="alabama-search-modal" class="alabama-search-modal" style="display: none;">
    <div class="alabama-search-modal-content">
        <div class="alabama-search-header">
            <div class="alabama-search-input-wrapper">
                <i class="fas fa-search"></i>
                <input 
                    type="text" 
                    id="alabama-search-input" 
                    class="alabama-search-input" 
                    placeholder="Buscar páginas... (digite ou use ↑↓ para navegar)"
                    autocomplete="off"
                    spellcheck="false"
                />
                <button type="button" id="alabama-search-close" class="alabama-search-close" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="alabama-search-body">
            <div id="alabama-search-results" class="alabama-search-results">
                <!-- Resultados serão inseridos aqui via JavaScript -->
            </div>
            
            <div id="alabama-search-empty" class="alabama-search-empty" style="display: none;">
                <i class="fas fa-search"></i>
                <p>Nenhuma página encontrada</p>
                <small>Tente buscar por outro termo</small>
            </div>
        </div>
        
        <div class="alabama-search-footer">
            <div class="alabama-search-shortcuts">
                <span><kbd>↑</kbd><kbd>↓</kbd> Navegar</span>
                <span><kbd>Enter</kbd> Abrir</span>
                <span><kbd>ESC</kbd> Fechar</span>
            </div>
        </div>
    </div>
</div>

<script <?php echo function_exists('alabama_csp_nonce_attr') ? alabama_csp_nonce_attr() : ''; ?>>
(function() {
    // Define páginas disponíveis para busca
    window.ALABAMA_SEARCH_PAGES = <?php echo $userPagesJson; ?>;
})();
</script>
