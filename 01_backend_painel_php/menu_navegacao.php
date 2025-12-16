<?php
declare(strict_types=1);

// Headers de segurança básicos para toda a área autenticada
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
}

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];

try {
    $stmt_usuario = (new \RedeAlabama\Repositories\Screens\MenuNavegacaoRepository($pdo))->prepare_680();
    $stmt_usuario->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_usuario->execute();
    $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    log_app_event('usuarios', 'erro_carregar_menu', [
        'usuario_id' => $usuario_id,
        'error'      => $e->getMessage(),
    ]);
    $usuario = null;
}

if (!$usuario) {
    // Se não encontrou o usuário, força logout por segurança
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$nivel = (string)($usuario['nivel_acesso'] ?? '');

// Define a página inicial com base no nível de acesso
$pagina_inicial = match ($nivel) {
    'Administrador' => 'painel_admin.php',
    'Gerente'       => 'painel_gerente.php',
    default         => 'painel_vendedor.php',
};

// Pega a página atual
$current_page = basename($_SERVER['PHP_SELF'] ?? '');

// Inclui o novo sistema de navegação (sidebar + busca global)
// NOTA: Mantemos o navbar tradicional por enquanto para compatibilidade
$use_new_navigation = true; // Defina como false para desabilitar temporariamente

if ($use_new_navigation) {
    // Inclui sidebar e busca global
    if (file_exists(__DIR__ . '/includes/sidebar_menu.php')) {
        include __DIR__ . '/includes/sidebar_menu.php';
    }
    
    if (file_exists(__DIR__ . '/includes/global_search.php')) {
        include __DIR__ . '/includes/global_search.php';
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo htmlspecialchars($pagina_inicial, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-gem" style="color: var(--al-primary);"></i>
            <span style="font-weight: 800; background: linear-gradient(135deg, var(--al-primary), var(--al-accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Alabama</span>
            <span style="font-weight: 400; color: var(--al-text-muted); font-size: 0.9rem;">CMS</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Alternar navegação">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">

                <?php if ($nivel === 'Vendedor'): ?>
                    <li class="nav-item <?php echo ($current_page === 'painel_vendedor.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="painel_vendedor.php"><i class="fas fa-tachometer-alt"></i> Minhas Vendas</a>
                    </li>
                    <li class="nav-item <?php echo ($current_page === 'nova_venda.php') ? 'active nova-venda' : ''; ?>">
                        <a class="nav-link" href="nova_venda.php"><i class="fas fa-cash-register"></i> Nova Venda</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownOperacaoVendedor" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-headset"></i> Operação
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownOperacaoVendedor">
                            <li><a class="dropdown-item" href="painel_vendedor_hoje.php">Minha operação hoje</a></li>
                            <li><a class="dropdown-item" href="leads.php">Fila de Leads</a></li>
                            <li><a class="dropdown-item" href="agenda.php">Minha Agenda</a></li>
                            <li><a class="dropdown-item" href="sessoes_atendimento.php">Meu Tempo de Atendimento</a></li>
                            <li><a class="dropdown-item" href="vendor_ai_prefs.php">Minhas preferências de IA</a></li>
                            <li><a class="dropdown-item" href="flow_governance.php">Governança de Fluxos</a></li>
                        </ul>
                    </li>
                    <li class="nav-item <?php echo ($current_page === 'relatorioestoq.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="relatorioestoq.php"><i class="fas fa-chart-bar"></i> Relatório Estoque</a>
                    </li>
                    <li class="nav-item <?php echo ($current_page === 'audit_dashboard.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="audit_dashboard.php"><i class="fas fa-clipboard-list"></i> Auditoria</a>
                    </li>
                    <li class="nav-item <?php echo ($current_page === 'diagnostico_estoque.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="diagnostico_estoque.php"><i class="fas fa-search-plus"></i> Diagnóstico Estoque</a>
                    </li>

                <?php elseif ($nivel === 'Gerente'): ?>
                    <li class="nav-item <?php echo ($current_page === 'painel_gerente.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="painel_gerente.php"><i class="fas fa-home"></i> Início</a>
                    </li>
                    <li class="nav-item <?php echo ($current_page === 'painel_vendedor.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="painel_vendedor.php"><i class="fas fa-tachometer-alt"></i> Minhas Vendas</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownOperacaoGerente" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-headset"></i> Operação
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownOperacaoGerente">
                            <li><a class="dropdown-item" href="leads.php">Fila de Leads</a></li>
                            <li><a class="dropdown-item" href="agenda.php">Agenda &amp; Compromissos</a></li>
                            <li><a class="dropdown-item" href="sessoes_atendimento.php">Tempo de Atendimento</a></li>
                            <li><a class="dropdown-item" href="playbooks.php">Playbooks de Atendimento</a></li>
                            <li><a class="dropdown-item" href="flow_governance.php">Governança de Fluxos</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownEstoque" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-boxes"></i> Estoque
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownEstoque">
                            <li><a class="dropdown-item" href="estoque_vendedor.php">Estoque</a></li>
                            <li><a class="dropdown-item" href="relatorioestoq.php">Relatório Estoque</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownRelatorios" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-alt"></i> Relatórios
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownRelatorios">
                            <li><a class="dropdown-item" href="relatorios.php">Relatórios</a></li>
                            <li><a class="dropdown-item" href="dashboard_analytics.php"><i class="fas fa-chart-line"></i> Dashboard Analytics</a></li>
                            <li><a class="dropdown-item" href="dashboard_supremacy.php"><i class="fas fa-tachometer-alt"></i> Dashboard Supremacy</a></li>
                            <li><a class="dropdown-item" href="relatorioestoq.php">Relatório Estoque</a></li>
                            <li><a class="dropdown-item" href="preju.php">Relatório de Prejuízo</a></li>
                            <li><a class="dropdown-item" href="vendas.php">Top vendas</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownIntegracao" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-plug"></i> Integração
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownIntegracao">
                            <li><a class="dropdown-item" href="estoque_vendedor.php">Editar Estoque</a></li>
                            <li><a class="dropdown-item" href="base_clientes.php"><i class="fas fa-users"></i> Base de Clientes</a></li>
                            <li><a class="dropdown-item" href="catalogo.php"><i class="fas fa-book"></i> Catálogo</a></li>
                            <li><a class="dropdown-item" href="REMARK.php"><i class="fas fa-bullhorn"></i> Remarketing</a></li>
                            <li><a class="dropdown-item" href="remarketing_inteligente.php"><i class="fas fa-bullhorn"></i> Remarketing Inteligente</a></li>
                            <li><a class="dropdown-item" href="frete.php"><i class="fas fa-truck"></i> Frete</a></li>
                            <li><a class="dropdown-item" href="matching_inteligente.php"><i class="fas fa-route"></i> Matching Inteligente</a></li>
                            <li><a class="dropdown-item" href="whatsapp_bot_config.php"><i class="fab fa-whatsapp"></i> Bot WhatsApp IA</a></li>
                            <li><a class="dropdown-item" href="whatsapp_handover.php"><i class="fas fa-user-shield"></i> Handover WhatsApp</a></li>
                            <li><a class="dropdown-item" href="llm_training_hub.php"><i class="fas fa-brain"></i> LLM Training Hub</a></li>
                            <li><a class="dropdown-item" href="jobs_painel.php"><i class="fas fa-robot"></i> Jobs / Automação</a></li>
                            <li><a class="dropdown-item" href="whatsapp_bot_console.php"><i class="fas fa-comments"></i> Conversas WhatsApp</a></li>
                            <li><a class="dropdown-item" href="flows_manager.php"><i class="fas fa-network-wired"></i> Fluxos IA WhatsApp</a></li>
                            <li><a class="dropdown-item" href="automation_rules.php"><i class="fas fa-bolt"></i> Regras de Automação</a></li>
                            <li><a class="dropdown-item" href="automation_runner.php"><i class="fas fa-play-circle"></i> Runner de Automação</a></li>
                            <li><a class="dropdown-item" href="ia_insights_dashboard.php"><i class="fas fa-chart-line"></i> Insights IA</a></li>
                            <li><a class="dropdown-item" href="llm_analytics_dashboard.php"><i class="fas fa-chart-bar"></i> Analytics IA (DB)</a></li>
                        </ul>
                    </li>
                    <li class="nav-item <?php echo ($current_page === 'nova_venda.php') ? 'active nova-venda' : ''; ?>">
                        <a class="nav-link" href="nova_venda.php"><i class="fas fa-cash-register"></i> Nova Venda</a>
                    </li>

                <?php elseif ($nivel === 'Administrador'): ?>
                    <li class="nav-item <?php echo ($current_page === 'painel_admin.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="painel_admin.php"><i class="fas fa-cogs"></i> Administração</a>
                    </li>
                    <li class="nav-item <?php echo ($current_page === 'painel_vendedor.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="painel_vendedor.php"><i class="fas fa-tachometer-alt"></i> Minhas Vendas</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownOperacaoAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-headset"></i> Operação
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownOperacaoAdmin">
                            <li><a class="dropdown-item" href="leads.php">Fila de Leads</a></li>
                            <li><a class="dropdown-item" href="agenda.php">Agenda &amp; Compromissos</a></li>
                            <li><a class="dropdown-item" href="sessoes_atendimento.php">Tempo de Atendimento</a></li>
                            <li><a class="dropdown-item" href="playbooks.php">Playbooks de Atendimento</a></li>
                            <li><a class="dropdown-item" href="flow_governance.php">Governança de Fluxos</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownRelatoriosAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-alt"></i> Relatórios
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownRelatoriosAdmin">
                            <li><a class="dropdown-item" href="relatorios.php">Relatórios</a></li>
                            <li><a class="dropdown-item" href="dashboard_analytics.php"><i class="fas fa-chart-line"></i> Dashboard Analytics</a></li>
                            <li><a class="dropdown-item" href="dashboard_supremacy.php"><i class="fas fa-tachometer-alt"></i> Dashboard Supremacy</a></li>
                            <li><a class="dropdown-item" href="relatorioestoq.php">Relatório Estoque</a></li>
                            <li><a class="dropdown-item" href="preju.php">Relatório de Prejuízo</a></li>
                            <li><a class="dropdown-item" href="vendas.php">Top vendas</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownIntegracaoAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-plug"></i> Integração
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownIntegracaoAdmin">
                            <li><a class="dropdown-item" href="estoque_vendedor.php">Editar Estoque</a></li>
                            <li><a class="dropdown-item" href="base_clientes.php"><i class="fas fa-users"></i> Base de Clientes</a></li>
                            <li><a class="dropdown-item" href="catalogo.php"><i class="fas fa-book"></i> Catálogo</a></li>
                            <li><a class="dropdown-item" href="REMARK.php"><i class="fas fa-bullhorn"></i> Remarketing</a></li>
                            <li><a class="dropdown-item" href="remarketing_inteligente.php"><i class="fas fa-bullhorn"></i> Remarketing Inteligente</a></li>
                            <li><a class="dropdown-item" href="frete.php"><i class="fas fa-truck"></i> Frete</a></li>
                            <li><a class="dropdown-item" href="matching_inteligente.php"><i class="fas fa-route"></i> Matching Inteligente</a></li>
                            <li><a class="dropdown-item" href="whatsapp_bot_config.php"><i class="fab fa-whatsapp"></i> Bot WhatsApp IA</a></li>
                            <li><a class="dropdown-item" href="whatsapp_handover.php"><i class="fas fa-user-shield"></i> Handover WhatsApp</a></li>
                            <li><a class="dropdown-item" href="llm_training_hub.php"><i class="fas fa-brain"></i> LLM Training Hub</a></li>
                            <li><a class="dropdown-item" href="jobs_painel.php"><i class="fas fa-robot"></i> Jobs / Automação</a></li>
                            <li><a class="dropdown-item" href="whatsapp_bot_console.php"><i class="fas fa-comments"></i> Conversas WhatsApp</a></li>
                            <li><a class="dropdown-item" href="flows_manager.php"><i class="fas fa-network-wired"></i> Fluxos IA WhatsApp</a></li>
                            <li><a class="dropdown-item" href="automation_rules.php"><i class="fas fa-bolt"></i> Regras de Automação</a></li>
                            <li><a class="dropdown-item" href="automation_runner.php"><i class="fas fa-play-circle"></i> Runner de Automação</a></li>
                            <li><a class="dropdown-item" href="ia_insights_dashboard.php"><i class="fas fa-chart-line"></i> Insights IA</a></li>
                            <li><a class="dropdown-item" href="llm_analytics_dashboard.php"><i class="fas fa-chart-bar"></i> Analytics IA (DB)</a></li>
                        </ul>
                    </li>
                    <li class="nav-item <?php echo ($current_page === 'nova_venda.php') ? 'active nova-venda' : ''; ?>">
                        <a class="nav-link" href="nova_venda.php"><i class="fas fa-cash-register"></i> Nova Venda</a>
                    </li>

                <?php endif; ?>

                <?php if (in_array($nivel, ['Administrador', 'Gerente'], true)): ?>
                    <li class="nav-item <?php echo ($current_page === 'admin_assistant.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="admin_assistant.php"><i class="fas fa-user-astronaut"></i> Assistente Interno IA</a>
                    </li>
                <?php endif; ?>

                <?php if ($nivel === 'Administrador'): ?>
                    <li class="nav-item <?php echo ($current_page === 'env_editor.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="env_editor.php"><i class="fas fa-sliders-h"></i> Config .env</a>
                    </li>
                    <li class="nav-item <?php echo ($current_page === 'apply_env_dashboard.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="apply_env_dashboard.php"><i class="fas fa-sync-alt"></i> Status apply-env</a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <!-- Notification Widget -->
            <?php include __DIR__ . '/includes/notifications_widget.php'; ?>
            
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="sair.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
                </li>
            </ul>
        </div>
    </div>
</nav>


<style>
    /* Nova Venda - Destaque especial */
    .nova-venda {
        background: linear-gradient(135deg, var(--al-success), #16a34a) !important;
        color: white !important;
        font-weight: 600 !important;
        border-radius: var(--al-radius-md) !important;
        transition: var(--al-transition) !important;
        box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3) !important;
    }
    .nova-venda:hover {
        background: linear-gradient(135deg, var(--al-success-hover), var(--al-success)) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 16px rgba(34, 197, 94, 0.5) !important;
        text-decoration: none !important;
    }
    
    /* Estilos do navbar já estão no alabama-theme.css, mas adicionamos override para garantir */
    .navbar-brand {
        font-weight: 800;
        font-size: 1.25rem;
        color: var(--al-text-primary);
    }
    
    .nav-item.active .nav-link {
        background: rgba(139, 92, 246, 0.15) !important;
        color: var(--al-primary-hover) !important;
        border-radius: var(--al-radius-sm) !important;
    }
    
    .nav-link {
        font-size: 0.9375rem;
        padding: 0.625rem 1rem;
        border-radius: var(--al-radius-sm);
        transition: var(--al-transition);
    }
    
    .nav-link:hover {
        background: rgba(139, 92, 246, 0.08);
    }
    
    /* Responsividade */
    @media (max-width: 768px) {
        .navbar-nav {
            text-align: center;
            gap: 0.5rem;
        }
        .dropdown-menu {
            text-align: center;
        }
        .nav-link {
            margin: 0.25rem 0;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<?php if ($use_new_navigation): ?>
<!-- Alabama Navigation System CSS -->
<link rel="stylesheet" href="assets/css/alabama-navigation.css">
<?php endif; ?>

<?php if (function_exists('csrf_token')): ?>
<script <?php echo alabama_csp_nonce_attr(); ?>>
    window.AL_BAMA_CSRF_TOKEN = "<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>";
</script>
<?php endif; ?>

<?php if ($use_new_navigation): ?>
<!-- Alabama Navigation System JS -->
<script src="assets/js/navigation.js"></script>
<?php endif; ?>

<style>
    #alabama-ai-fab-btn {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 1080;
        border-radius: 999px;
        padding: 0.75rem 1.2rem;
        font-weight: 600;
        font-size: 0.95rem;
        border: none;
        cursor: pointer;
        background: linear-gradient(135deg, var(--alabama-primary), var(--alabama-accent));
        color: #f9fafb;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.7);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    #alabama-ai-fab-btn span.icon {
        display: inline-flex;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.2);
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    #alabama-ai-fab-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 30px rgba(15, 23, 42, 0.9);
    }

    #alabama-ai-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(6px);
        z-index: 1079;
        display: none;
    }

    #alabama-ai-modal {
        position: fixed;
        bottom: 88px;
        right: 24px;
        width: 360px;
        max-width: calc(100% - 32px);
        background: #020617;
        border-radius: 1rem;
        border: 1px solid rgba(79, 70, 229, 0.6);
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.85);
        z-index: 1081;
        display: none;
        overflow: hidden;
    }

    #alabama-ai-modal-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgba(75, 85, 99, 0.7);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: radial-gradient(circle at top left, rgba(129, 140, 248, 0.25), transparent);
    }

    #alabama-ai-modal-header h6 {
        margin: 0;
        font-size: 0.9rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #e5e7eb;
    }

    #alabama-ai-modal-header small {
        font-size: 0.7rem;
        color: #9ca3af;
    }

    #alabama-ai-modal-body {
        padding: 0.75rem 1rem 0.5rem 1rem;
    }

    #alabama-ai-modal textarea {
        width: 100%;
        min-height: 80px;
        max-height: 180px;
        background: #020617;
        border-radius: 0.5rem;
        border: 1px solid rgba(55, 65, 81, 0.9);
        color: #e5e7eb;
        font-size: 0.85rem;
        resize: vertical;
        padding: 0.5rem 0.6rem;
    }

    #alabama-ai-modal textarea:focus {
        outline: none;
        border-color: var(--alabama-accent);
        box-shadow: 0 0 0 1px rgba(56, 189, 248, 0.6);
    }

    #alabama-ai-modal-footer {
        padding: 0.5rem 1rem 0.75rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
    }

    #alabama-ai-modal-footer button {
        border-radius: 999px;
        border: none;
        font-size: 0.8rem;
        padding: 0.4rem 0.9rem;
        cursor: pointer;
    }

    #alabama-ai-modal-footer button.primary {
        background: linear-gradient(135deg, var(--alabama-primary-soft), var(--alabama-accent));
        color: #f9fafb;
        font-weight: 600;
    }

    #alabama-ai-modal-footer button.secondary {
        background: transparent;
        color: #9ca3af;
        border: 1px solid rgba(75, 85, 99, 0.9);
    }

    #alabama-ai-modal-response {
        max-height: 220px;
        overflow-y: auto;
        padding: 0.75rem 1rem 1rem 1rem;
        border-top: 1px solid rgba(31, 41, 55, 0.9);
        font-size: 0.8rem;
        color: #d1d5db;
        background: radial-gradient(circle at bottom right, rgba(56, 189, 248, 0.12), transparent);
    }

    #alabama-ai-modal-response pre {
        white-space: pre-wrap;
        margin: 0;
    }

    #alabama-ai-modal-loading {
        font-size: 0.75rem;
        color: #9ca3af;
    }

    @media (max-width: 768px) {
        #alabama-ai-fab-btn {
            bottom: 16px;
            right: 16px;
            padding: 0.6rem 0.9rem;
            font-size: 0.85rem;
        }

        #alabama-ai-modal {
            right: 16px;
            left: 16px;
            width: auto;
            bottom: 80px;
        }
    }
</style>

<script <?php echo alabama_csp_nonce_attr(); ?>>
(function () {
    if (!window.fetch) {
        return;
    }

    document.addEventListener('DOMContentLoaded', function () {
        try {
            // Evita duplicar caso o menu seja incluído mais de uma vez em alguma página
            if (document.getElementById('alabama-ai-fab-btn')) {
                return;
            }

            var body = document.body;
            if (!body) {
                return;
            }

            var fab = document.createElement('button');
            fab.id = 'alabama-ai-fab-btn';
            fab.type = 'button';
            fab.innerHTML = '<span class="icon"><i class="fas fa-magic"></i></span><span>IA em qualquer tela</span>';
            body.appendChild(fab);

            var backdrop = document.createElement('div');
            backdrop.id = 'alabama-ai-modal-backdrop';
            body.appendChild(backdrop);

            var modal = document.createElement('div');
            modal.id = 'alabama-ai-modal';
            modal.innerHTML = ''
                + '<div id="alabama-ai-modal-header">'
                + '  <div>'
                + '    <h6>Assistente Interno IA</h6>'
                + '    <small>Contextual ao painel · usa suas preferências de IA</small>'
                + '  </div>'
                + '  <button type="button" id="alabama-ai-modal-close" style="background:none;border:none;color:#9ca3af;font-size:0.85rem;">✕</button>'
                + '</div>'
                + '<div id="alabama-ai-modal-body">'
                + '  <textarea id="alabama-ai-question" placeholder="Explique o que você precisa: resumo de números, ideias de mensagem, rascunho de campanha, script de atendimento..." rows="4"></textarea>'
                + '</div>'
                + '<div id="alabama-ai-modal-footer">'
                + '  <span id="alabama-ai-modal-loading"></span>'
                + '  <div>'
                + '    <button type="button" class="secondary" id="alabama-ai-modal-cancel">Cancelar</button>'
                + '    <button type="button" class="primary" id="alabama-ai-modal-send">Perguntar IA</button>'
                + '  </div>'
                + '</div>'
                + '<div id="alabama-ai-modal-response" style="display:none;">'
                + '  <pre id="alabama-ai-modal-response-text"></pre>'
                + '</div>';
            body.appendChild(modal);

            var questionInput = document.getElementById('alabama-ai-question');
            var btnClose = document.getElementById('alabama-ai-modal-close');
            var btnCancel = document.getElementById('alabama-ai-modal-cancel');
            var btnSend = document.getElementById('alabama-ai-modal-send');
            var loadingEl = document.getElementById('alabama-ai-modal-loading');
            var respBox = document.getElementById('alabama-ai-modal-response');
            var respText = document.getElementById('alabama-ai-modal-response-text');

            // Pré-preenche o campo com o texto selecionado na tela, se existir
            try {
                var selText = '';
                if (window.getSelection) {
                    selText = (window.getSelection().toString() || '').trim();
                }
                if (selText && questionInput && !questionInput.value) {
                    questionInput.value = selText + "\n\n---\nExplique o que você precisa com esse conteúdo:";
                }
            } catch (e) {
                // Falha silenciosa: não impede uso normal do assistente
            }

            function openModal() {
                backdrop.style.display = 'block';
                modal.style.display = 'block';
                loadingEl.textContent = '';
                respBox.style.display = 'none';
                respText.textContent = '';
                setTimeout(function () {
                    if (questionInput) {
                        questionInput.focus();
                    }
                }, 50);
            }

            function closeModal() {
                backdrop.style.display = 'none';
                modal.style.display = 'none';
            }

            function setLoading(isLoading, msg) {
                if (isLoading) {
                    loadingEl.textContent = msg || 'Consultando IA...';
                    btnSend.disabled = true;
                    btnSend.textContent = 'Aguarde...';
                } else {
                    loadingEl.textContent = '';
                    btnSend.disabled = false;
                    btnSend.textContent = 'Perguntar IA';
                }
            }

            fab.addEventListener('click', openModal);
            btnClose.addEventListener('click', closeModal);
            btnCancel.addEventListener('click', closeModal);
            backdrop.addEventListener('click', closeModal);

            if (questionInput) {
                questionInput.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) {
                        ev.preventDefault();
                        btnSend.click();
                    }
                });
            }

            btnSend.addEventListener('click', function () {
                var texto = questionInput ? questionInput.value.trim() : '';
                if (!texto) {
                    if (questionInput) {
                        questionInput.focus();
                    }
                    return;
                }

                var csrfToken = window.AL_BAMA_CSRF_TOKEN || '';
                setLoading(true);

                var payload = {
                    pergunta: texto,
                    page: window.location.pathname || null,
                    _csrf_token: csrfToken
                };

                fetch('admin_assistant_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                    .then(function (resp) { return resp.json(); })
                    .then(function (data) {
                        setLoading(false);
                        if (!data || !data.ok) {
                            loadingEl.textContent = (data && data.error) ? data.error : 'Erro ao consultar IA.';
                            return;
                        }
                        if (respBox && respText) {
                            respText.textContent = data.resposta || '';
                            respBox.style.display = 'block';
                        }
                    })
                    .catch(function () {
                        setLoading(false);
                        loadingEl.textContent = 'Falha de comunicação com o servidor.';
                    });
            });
        } catch (e) {
            // Failsafe silencioso – não quebra o painel se algo der errado.
            if (typeof console !== 'undefined' && console && console.warn) {
                console.warn('Alabama IA FAB error:', e);
            }
        }
    });
})();
</script>
