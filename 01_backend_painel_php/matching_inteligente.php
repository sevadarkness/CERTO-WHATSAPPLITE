<?php
// Matching Inteligente – apenas Administrador/Gerente
require_once __DIR__ . '/rbac.php';
require_role(array('Administrador', 'Gerente'));
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motor de Matching Inteligente - Rede Alabama</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">
    <style>
        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #121218;
            --bg-card: #1a1a24;
            --bg-panel: #1e1e2d;
            --border-primary: #2a2a3a;
            --border-secondary: #3a3a4a;
            --accent-primary: #8b5cf6;
            --accent-secondary: #a78bfa;
            --accent-gradient: linear-gradient(135deg, #8b5cf6, #7c3aed, #6d28d9);
            --accent-soft: rgba(139, 92, 246, 0.15);
            --accent-strong: rgba(139, 92, 246, 0.4);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 18px;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 8px 25px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 15px 40px rgba(0, 0, 0, 0.5);
            --glow: 0 0 20px rgba(139, 92, 246, 0.3);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes glow {
            0%, 100% { box-shadow: var(--shadow-md); }
            50% { box-shadow: var(--glow), var(--shadow-md); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: 
                radial-gradient(circle at 0% 0%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 100% 0%, rgba(139, 92, 246, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(139, 92, 246, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 0% 100%, rgba(139, 92, 246, 0.05) 0%, transparent 50%),
                var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.5;
            animation: fadeIn 0.8s ease-out;
        }

        .page-wrapper {
            padding: 16px;
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeIn 1s ease-out;
        }

        .card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-primary);
            padding: 18px 20px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--accent-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .card:hover::before {
            transform: scaleX(1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 12px;
            animation: slideIn 0.6s ease-out;
        }

        .card-header h1 {
            font-size: 1.45rem;
            margin: 0;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        .badge {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            border: 1px solid var(--accent-strong);
            color: var(--accent-secondary);
            animation: float 3s ease-in-out infinite;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            animation: fadeIn 0.8s ease-out;
        }

        label {
            font-size: 0.82rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        input, select, textarea {
            padding: 9px 11px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-primary);
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        input::placeholder, textarea::placeholder {
            color: var(--text-muted);
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.2);
            transform: translateY(-1px);
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 12px 0;
        }

        .btn {
            padding: 8px 14px;
            border-radius: var(--radius-md);
            border: none;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md), var(--glow);
            animation: pulse 0.5s ease;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-primary);
        }

        .btn-secondary:hover {
            background: var(--bg-panel);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-primary);
        }

        .btn-outline:hover {
            border-color: var(--accent-primary);
            transform: translateY(-2px);
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
        }

        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: default;
            transform: none !important;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.78rem;
        }

        .status-msg {
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: var(--radius-md);
            background: var(--bg-secondary);
            margin: 4px 0 6px;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
            animation: fadeIn 0.5s ease;
        }

        .status-ok {
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            border-left-color: var(--success);
        }

        .status-error {
            color: var(--danger);
            background: rgba(239, 68, 68, 0.1);
            border-left-color: var(--danger);
        }

        .status-info {
            color: var(--info);
            background: rgba(59, 130, 246, 0.1);
            border-left-color: var(--info);
        }

        .layout-main {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 16px;
            margin-top: 10px;
            animation: fadeIn 1.2s ease-out;
        }

        #map {
            width: 100%;
            height: 500px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }

        #map:hover {
            box-shadow: var(--shadow-lg);
        }

        .panel {
            background: var(--bg-panel);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 500px;
            overflow-y: auto;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }

        .panel:hover {
            box-shadow: var(--shadow-lg);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header h3 {
            font-size: 1.02rem;
            margin: 0;
            color: var(--text-primary);
        }

        .chip {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            border: 1px solid var(--accent-strong);
            color: var(--accent-secondary);
            animation: fadeIn 0.6s ease;
        }

        .entregador-card {
            border-radius: var(--radius-md);
            border: 1px solid var(--border-primary);
            padding: 10px 11px;
            background: var(--bg-card);
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 6px;
            animation: fadeIn 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .entregador-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-gradient);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .entregador-card:hover {
            border-color: var(--accent-primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .entregador-card:hover::before {
            transform: scaleY(1);
        }

        .entregador-card.top {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            animation: glow 2s ease-in-out infinite;
        }

        .entregador-card.top::before {
            background: var(--success);
            transform: scaleY(1);
        }

        .entregador-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .entregador-nome {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .entregador-status {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
            font-weight: 600;
        }

        .entregador-status.indisponivel {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.4);
            color: #f87171;
        }

        .entregador-status.on_route {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.4);
            color: #60a5fa;
        }

        .entregador-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 2px;
        }

        .entregador-score {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .entregador-score strong {
            color: var(--warning);
            font-weight: 700;
        }

        .status-pedido {
            font-weight: 600;
            font-size: 0.78rem;
        }

        .status-pendente {
            color: var(--warning);
        }

        .status-confirmado {
            color: var(--success);
        }

        .tag-cliente {
            font-size: 0.83rem;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            border: 1px solid var(--accent-strong);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
            animation: fadeIn 0.6s ease;
        }

        .info-linha {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-primary);
            margin-bottom: 10px;
        }

        .tab {
            padding: 8px 14px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            font-size: 0.87rem;
            color: var(--text-muted);
            position: relative;
        }

        .tab::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--accent-gradient);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .tab.active {
            color: var(--accent-secondary);
        }

        .tab.active::before {
            width: 100%;
        }

        .tab:hover {
            color: var(--text-primary);
        }

        .tab-content {
            display: none;
            margin-top: 8px;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin: 8px 0 4px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            padding: 10px;
            text-align: center;
            border: 1px solid var(--border-primary);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--accent-gradient);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 3px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .toggle-line {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .toggle-line input {
            width: auto;
        }

        #lista-manual-entregadores {
            margin-top: 8px;
        }

        @media (max-width: 1024px) {
            .layout-main {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .toolbar {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* ---- Refinos da dashboard - estilo enterprise ---- */

        @keyframes fadeInUpCard {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .layout-main {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(320px, 1fr);
            gap: 18px;
            margin-top: 14px;
            align-items: stretch;
        }

        .panel {
            background:
                radial-gradient(circle at top, rgba(139, 92, 246, 0.18) 0, transparent 45%),
                var(--bg-panel);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-primary);
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 520px;
            overflow-y: auto;
            box-shadow: var(--shadow-md);
            transition:
                transform 0.25s ease,
                box-shadow 0.25s ease,
                border-color 0.25s ease;
            animation: fadeIn 0.7s ease-out;
        }

        .panel:hover {
            transform: translateY(-2px);
            border-color: var(--accent-primary);
            box-shadow: var(--shadow-lg), var(--glow);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 6px;
            margin-bottom: 6px;
            border-bottom: 1px solid var(--border-primary);
        }

        .panel-header h3 {
            font-size: 1.05rem;
            letter-spacing: 0.02em;
        }

        .panel::-webkit-scrollbar {
            width: 6px;
        }

        .panel::-webkit-scrollbar-track {
            background: transparent;
        }

        .panel::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.5);
            border-radius: 999px;
        }

        #info-cliente {
            padding: 8px 10px;
            border-radius: var(--radius-md);
            background: rgba(15, 23, 42, 0.75);
            border: 1px solid var(--border-primary);
        }

        #lista-entregadores {
            margin-top: 6px;
        }

        #rodape-info {
            margin-top: 4px;
            padding-top: 6px;
            border-top: 1px dashed var(--border-secondary);
            font-size: 0.8rem;
        }

        .entregador-card {
            margin-bottom: 8px;
            padding: 11px 12px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.85));
            border: 1px solid var(--border-primary);
            box-shadow: var(--shadow-sm);
            animation: fadeInUpCard 0.35s ease-out forwards;
            opacity: 0;
        }

        .entregador-card:nth-child(1) {
            animation-delay: 0.03s;
        }
        .entregador-card:nth-child(2) {
            animation-delay: 0.07s;
        }
        .entregador-card:nth-child(3) {
            animation-delay: 0.11s;
        }
        .entregador-card:nth-child(4) {
            animation-delay: 0.15s;
        }
        .entregador-card:nth-child(5) {
            animation-delay: 0.19s;
        }

        .entregador-card.top {
            border-color: var(--success);
            background: linear-gradient(
                135deg,
                rgba(16, 185, 129, 0.16),
                rgba(15, 23, 42, 0.95)
            );
            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.35), var(--shadow-md);
        }

        .entregador-header {
            margin-bottom: 6px;
        }

        .entregador-meta {
            font-size: 0.79rem;
            margin-bottom: 2px;
        }

        .entregador-score {
            margin-top: 2px;
        }

        .stats-grid {
            margin-top: 12px;
            margin-bottom: 6px;
            padding: 6px;
            border-radius: var(--radius-lg);
            background: radial-gradient(circle at top, rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.7));
            border: 1px solid var(--border-primary);
        }

        .stat-card {
            background: rgba(15, 23, 42, 0.9);
            border-radius: var(--radius-md);
            padding: 10px 8px;
            border: 1px solid rgba(31, 41, 55, 0.9);
        }

        .stat-value {
            font-size: 1.15rem;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.76rem;
        }

        /* ---- Expansão para maior área visível em tela ---- */

        body {
            min-height: 100vh;
        }

        .page-wrapper {
            max-width: 100%;
            padding: 24px 28px 28px;
        }

        #map {
            height: 72vh;
        }

        .panel {
            max-height: 72vh;
        }
    </style>
</head>
<body>
<?php include 'menu_navegacao.php'; ?>

<div class="page-wrapper">
    <div class="card">
        <div class="card-header">
            <h1>🚀 Motor de Matching Inteligente</h1>
            <span class="badge">Rede Alabama · Híbrido Final (Moto / Rotas Reais)</span>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="busca">Busca Rápida</div>
            <div class="tab" data-tab="avancado">Modo Avançado</div>
            <div class="tab" data-tab="entregadores">Gerenciar Entregadores</div>
        </div>

        <!-- Tab: Busca rápida -->
        <div class="tab-content active" id="busca">
            <div class="form-grid">
                <div class="form-group">
                    <label for="cliente-nome">Nome do Cliente</label>
                    <input type="text" id="cliente-nome" placeholder="Nome completo do cliente">
                </div>
                <div class="form-group">
                    <label for="cliente-telefone">Telefone do Cliente</label>
                    <input type="text" id="cliente-telefone" placeholder="(DDD) 9XXXX-XXXX">
                </div>
                <div class="form-group">
                    <label for="endereco-cliente">Endereço do Cliente</label>
                    <input type="text" id="endereco-cliente" placeholder="Rua, número, bairro, cidade">
                </div>
                <div class="form-group">
                    <label for="zona-cliente">Zona do Cliente (opcional)</label>
                    <input type="text" id="zona-cliente" placeholder="Ex: Centro, Zona Sul">
                </div>
                <div class="form-group">
                    <label for="estrategia">Estratégia de Matching</label>
                    <select id="estrategia">
                        <option value="balanced">Balanceado (distância + tempo + carga + rating + zona)</option>
                        <option value="fast">Mais rápido (tempo/distância)</option>
                        <option value="low_load">Menos carregado (carga)</option>
                        <option value="zone_priority">Priorizar mesma zona</option>
                    </select>
                </div>
            </div>

            <div class="toolbar">
                <button class="btn btn-primary" id="btn-match">
                    🔍 Encontrar Melhor Entregador
                </button>
                <button class="btn btn-secondary" id="btn-localizar">
                    📡 Usar Minha Localização
                </button>
                <button class="btn btn-secondary" id="btn-limpar">
                    🗑️ Limpar
                </button>
            </div>

            <div id="status-msg" class="status-msg status-info">
                Informe o endereço do cliente e clique em "Encontrar Melhor Entregador".
            </div>
        </div>

        <!-- Tab: Avançado -->
        <div class="tab-content" id="avancado">
            <div class="form-grid">
                <div class="form-group">
                    <label for="produto-codigo">Código do Produto</label>
                    <input type="text" id="produto-codigo" placeholder="Ex: POD_XY_UVA_5">
                </div>
                <div class="form-group">
                    <label for="quantidade">Quantidade</label>
                    <input type="number" id="quantidade" min="1" value="1">
                </div>
                <div class="form-group">
                    <label for="prioridade">Prioridade da Entrega</label>
                    <select id="prioridade">
                        <option value="normal">Normal</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modo-dados">Fonte de Entregadores</label>
                    <select id="modo-dados">
                        <option value="api">API do ADM (api/v2/entregadores.php)</option>
                        <option value="static">Lista estática de fallback</option>
                        <option value="manual">Lista manual (aba "Gerenciar")</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-top:6px;">
                <label>Integração com o ADM</label>
                <div class="toggle-line">
                    <input type="checkbox" id="enviar-adm" checked>
                    <span>Enviar automaticamente o resultado para <code>api/v2/matching.php</code></span>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" id="stat-total">0</div>
                    <div class="stat-label">Entregadores considerados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-disponiveis">0</div>
                    <div class="stat-label">Disponíveis usados</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-tempo">0s</div>
                    <div class="stat-label">Tempo de processamento</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-distancia">0km</div>
                    <div class="stat-label">Distância média dos candidatos</div>
                </div>
            </div>
        </div>

        <!-- Tab: Gerenciar entregadores (manual) -->
        <div class="tab-content" id="entregadores">
            <div class="info-linha" style="margin-bottom:6px;">
                No modo <strong>Manual</strong> (aba "Modo Avançado"), o matching usará exclusivamente a lista abaixo,
                ordenando apenas por <strong>distância real</strong> (via Google Distance Matrix).
            </div>

            <div class="form-grid" style="margin-top:8px;">
                <div class="form-group">
                    <label for="manual-nome">Nome do Entregador</label>
                    <input type="text" id="manual-nome" placeholder="Nome completo do entregador">
                </div>
                <div class="form-group">
                    <label for="manual-telefone">Telefone / WhatsApp</label>
                    <input type="text" id="manual-telefone" placeholder="(DDD) 9XXXX-XXXX">
                </div>
                <div class="form-group">
                    <label for="manual-endereco">Endereço (residência/base)</label>
                    <input type="text" id="manual-endereco" placeholder="Rua, número, bairro, cidade">
                </div>
            </div>

            <div class="toolbar" style="margin-top:6px;">
                <button class="btn btn-primary btn-small" id="btn-salvar-manual">
                    💾 Salvar entregador
                </button>
                <button class="btn btn-outline btn-small" id="btn-reset-form">
                    ↺ Limpar formulário
                </button>
            </div>

            <div class="info-linha" style="margin-top:10px;">
                Entregadores manuais cadastrados:
            </div>
            <div id="lista-manual-entregadores"></div>
        </div>

        <!-- MAPA + PAINEL DE RESULTADOS -->
        <div class="layout-main">
            <div>
                <div id="map"></div>
            </div>
            <div class="panel">
                <div class="panel-header">
                    <h3>🏆 Entregadores Recomendados</h3>
                    <span class="chip" id="chip-contagem">0 candidatos</span>
                </div>

                <div id="info-cliente"></div>
                <div id="lista-entregadores"></div>
                <div id="rodape-info" class="info-linha"></div>
            </div>
        </div>
    </div>
</div>

<script <?php echo alabama_csp_nonce_attr(); ?>>
const MatchingApp = (function () {
    const CONFIG = {
        // Google Maps API key should come from server-side env to avoid leaking secrets.
        googleApiKey: '<?php echo htmlspecialchars(getenv("GOOGLE_MAPS_API_KEY") ?: getenv("ALABAMA_GOOGLE_MAPS_API_KEY") ?: ""); ?>',
        defaultCapacity: 8,
        defaultSlaMinutes: 45,
        endpoints: {
            vendors: 'api/v2/entregadores.php',
            match: 'api/v2/matching.php'
        }
    };

    const state = {
        mapa: null,
        geocoder: null,
        distanceMatrixService: null,
        directionsService: null,
        directionsRenderer: null,
        clienteMarker: null,
        entregadorMarkers: [],
        ultimaPosicaoCliente: null,
        candidatos: [],
        manualVendors: [],
        manualEnderecoLatLng: null,
        manualEnderecoTexto: '',
        manualEditIndex: null,
        lastClient: null,
        lastStrategy: 'balanced',
        lastProductCode: null,
        lastQuantity: 1,
        loadingMatching: false
    };

    function setStatus(msg, tipo) {
        const el = document.getElementById('status-msg');
        el.textContent = msg;
        el.className = 'status-msg';

        if (tipo === 'ok') el.classList.add('status-ok');
        else if (tipo === 'error') el.classList.add('status-error');
        else el.classList.add('status-info');
    }

    function atualizarStats(stats) {
        document.getElementById('stat-total').textContent = String(stats.total);
        document.getElementById('stat-disponiveis').textContent = String(stats.disponiveis);
        document.getElementById('stat-tempo').textContent = stats.tempoSeg + 's';
        document.getElementById('stat-distancia').textContent = stats.distanciaMediaKm.toFixed(1) + 'km';
    }

    function limparMarcadoresEntregadores() {
        state.entregadorMarkers.forEach(m => m.setMap(null));
        state.entregadorMarkers = [];
    }

    function limparRota() {
        if (state.directionsRenderer) {
            state.directionsRenderer.setMap(null);
            state.directionsRenderer.setDirections({ routes: [] });
        }
    }

    function desenharRota(origemLatLng, destinoLatLng) {
        if (!state.directionsService || !state.directionsRenderer || !origemLatLng || !destinoLatLng) return;

        state.directionsRenderer.setMap(state.mapa);
        state.directionsService.route({
            origin: origemLatLng,
            destination: destinoLatLng,
            travelMode: google.maps.TravelMode.DRIVING
        }, (result, status) => {
            if (status === 'OK') {
                state.directionsRenderer.setDirections(result);
            } else {
                console.warn('Erro ao desenhar rota:', status);
            }
        });
    }

    function marcarClienteNoMapa(latLng, enderecoTexto) {
        if (!state.mapa) return;

        if (state.clienteMarker) {
            state.clienteMarker.setMap(null);
        }

        state.clienteMarker = new google.maps.Marker({
            position: latLng,
            map: state.mapa,
            title: 'Cliente',
            icon: {
                url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png'
            }
        });

        state.mapa.panTo(latLng);
        state.mapa.setZoom(13);

        const nomeCliente = document.getElementById('cliente-nome').value.trim() || 'Cliente';
        const telCliente = document.getElementById('cliente-telefone').value.trim();
        const infoDiv = document.getElementById('info-cliente');
        const extra = telCliente ? ' · ' + telCliente : '';

        infoDiv.innerHTML = `
            <div class="tag-cliente">
                👤 <strong>${nomeCliente}</strong>${extra} · ${enderecoTexto}
            </div>
        `;
    }

    function desenharCandidatosNoMapa(origemLatLng, candidatos) {
        if (!state.mapa) return;

        limparMarcadoresEntregadores();

        const bounds = new google.maps.LatLngBounds();
        if (origemLatLng) bounds.extend(origemLatLng);

        const top5 = candidatos.slice(0, 5);

        top5.forEach(c => {
            const pos = new google.maps.LatLng(c.vendor.lat, c.vendor.lng);
            bounds.extend(pos);

            const marker = new google.maps.Marker({
                position: pos,
                map: state.mapa,
                title: c.vendor.name,
                label: {
                    text: String(c.rank),
                    color: '#ffffff'
                },
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 7,
                    fillColor: c.rank === 1 ? '#10b981' : '#8b5cf6',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2
                }
            });

            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="font-size:12px;padding:4px;">
                        <strong>${c.vendor.name}</strong><br>
                        Distância: ${c.distance.distance_text}<br>
                        Tempo: ${c.distance.duration_text}<br>
                        Score: ${c.score.toFixed(3)}
                    </div>
                `
            });

            marker.addListener('click', () => infoWindow.open(state.mapa, marker));
            state.entregadorMarkers.push(marker);
        });

        if (!bounds.isEmpty()) {
            state.mapa.fitBounds(bounds);
        }
    }

    function formatStatus(status) {
        const s = (status || '').toLowerCase();
        if (s === 'on_route') return 'Em rota';
        if (s === 'unavailable') return 'Indisponível';
        return 'Disponível';
    }

    function montarTextoWhatsApp(vendor) {
        const client = state.lastClient || {};
        const produto = state.lastProductCode || '';
        const qtd = state.lastQuantity || 1;
        const prioridadeEl = document.getElementById('prioridade');
        const prioridade = prioridadeEl ? (prioridadeEl.value || 'normal') : 'normal';

        const linhas = [
            '*Nova Entrega - Rede Alabama*',
            '',
            '*Cliente:* ' + (client.name  || ''),
            '*Telefone do Cliente:* ' + (client.phone || ''),
            '*Endereço:* ' + (client.address || ''),
            '',
            '*Produto:* ' + (produto || ''),
            '*Quantidade:* ' + qtd,
            '*Prioridade:* ' + prioridade,
            '',
            '*Entregador Selecionado:* ' + (vendor.name || '')
        ];

        const clientDigits = (client.phone || '').replace(/\D/g, '');
        if (clientDigits.length >= 10) {
            linhas.push('');
            linhas.push(
                '*Abrir conversa com o cliente:* https://wa.me/' + clientDigits
            );
        }

        return encodeURIComponent(linhas.join('\n'));
    }

    function montarLinkWhatsApp(vendor) {
        const texto = montarTextoWhatsApp(vendor);
        const digits = (vendor.phone || '').replace(/\D/g, '');
        if (digits.length >= 10) {
            return `https://wa.me/${digits}?text=${texto}`;
        }
        return `https://wa.me/?text=${texto}`;
    }

    function renderResultados() {
        const lista = document.getElementById('lista-entregadores');
        const chip = document.getElementById('chip-contagem');
        const rodape = document.getElementById('rodape-info');
        const candidatos = state.candidatos || [];

        chip.textContent = `${candidatos.length} candidato${candidatos.length === 1 ? '' : 's'}`;

        if (!candidatos.length) {
            lista.innerHTML = '<div class="info-linha">Nenhum entregador adequado encontrado.</div>';
            rodape.innerHTML = '';
            return;
        }

        lista.innerHTML = '';

        candidatos.forEach(c => {
            const card = document.createElement('div');
            card.className = 'entregador-card' + (c.rank === 1 ? ' top' : '');

            const status = c.vendor.status || 'available';
            const statusLabel = formatStatus(status);
            const statusClass =
                status === 'available' ? '' :
                status === 'on_route' ? 'on_route' :
                'indisponivel';

            const statusPedido = c.statusMatching || 'pending';
            const statusPedidoLabel = statusPedido === 'confirmed' ? 'Confirmado' : 'Pendente';
            const statusPedidoClass = statusPedido === 'confirmed' ? 'status-confirmado' : 'status-pendente';

            const waLink = montarLinkWhatsApp(c.vendor);
            const cargaTexto = `${c.vendor.active_orders || 0}/${c.vendor.capacity || CONFIG.defaultCapacity}`;

            card.innerHTML = `
                <div class="entregador-header">
                    <div class="entregador-nome">#${c.rank} · ${c.vendor.name}</div>
                    <div class="entregador-status ${statusClass}">${statusLabel}</div>
                </div>
                <div class="entregador-meta">
                    ${c.distance.distance_text} · ${c.distance.duration_text}
                </div>
                <div class="entregador-meta">
                    Carga: ${cargaTexto} · Zona: ${c.vendor.zone || 'N/D'}
                </div>
                <div class="entregador-score">
                    Score: <strong>${c.score.toFixed(3)}</strong> ·
                    Rating: ${c.vendor.rating != null ? c.vendor.rating.toFixed(1) : 'N/D'}
                </div>
                <div class="entregador-meta">
                    Status do pedido:
                    <span class="status-pedido ${statusPedidoClass}">${statusPedidoLabel}</span>
                </div>
                <div class="toolbar" style="margin-top:6px;">
                    <button class="btn btn-primary btn-small btn-confirm" data-rank="${c.rank}">✅ Confirmar</button>
                    <a class="btn btn-secondary btn-small" href="${waLink}" target="_blank" rel="noopener noreferrer">
                        💬 WhatsApp
                    </a>
                </div>
            `;

            card.addEventListener('click', () => {
                focarNoEntregador(c);
            });

            const confirmBtn = card.querySelector('.btn-confirm');
            confirmBtn.addEventListener('click', (ev) => {
                ev.stopPropagation();
                confirmarCandidato(c.rank);
            });

            lista.appendChild(card);
        });

        const best = candidatos[0];
        rodape.innerHTML = `
            <strong>Melhor opção:</strong> ${best.vendor.name} ·
            ${best.distance.distance_text} · ${best.distance.duration_text} ·
            Score: ${best.score.toFixed(3)}
        `;
    }

    function focarNoEntregador(candidato) {
        const pos = { lat: candidato.vendor.lat, lng: candidato.vendor.lng };
        if (!state.mapa) return;
        state.mapa.panTo(pos);
        state.mapa.setZoom(15);
        if (state.ultimaPosicaoCliente) {
            const origem = state.ultimaPosicaoCliente;
            const destino = new google.maps.LatLng(candidato.vendor.lat, candidato.vendor.lng);
            desenharRota(origem, destino);
        }
    }

    function normalizarEntregador(raw) {
        if (!raw || typeof raw !== 'object') return null;

        const lat = parseFloat(raw.lat ?? raw.latitude);
        const lng = parseFloat(raw.lng ?? raw.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;

        const capacityRaw =
            raw.capacity != null ? raw.capacity :
            raw.capacidade_entregas != null ? raw.capacidade_entregas :
            null;

        const slaRaw =
            raw.sla_minutes != null ? raw.sla_minutes :
            raw.sla_minutos != null ? raw.sla_minutos :
            null;

        const ratingRaw = raw.rating != null ? raw.rating : null;

        const activeOrdersRaw =
            raw.active_orders != null ? raw.active_orders :
            raw.pedidos_ativos != null ? raw.pedidos_ativos :
            null;

        return {
            id: raw.id ?? raw.vendor_id ?? null,
            name: raw.name ?? raw.nome ?? 'Sem nome',
            phone: raw.phone ?? raw.telefone ?? null,
            address: raw.address ?? raw.endereco ?? null,
            lat: lat,
            lng: lng,
            status: raw.status ?? raw.status_entregador ?? 'available',
            zone: raw.zone ?? raw.zona ?? null,
            rating: ratingRaw != null ? parseFloat(ratingRaw) : null,
            active_orders: activeOrdersRaw != null ? parseInt(activeOrdersRaw, 10) : 0,
            capacity: capacityRaw != null ? parseInt(capacityRaw, 10) : CONFIG.defaultCapacity,
            sla_minutes: slaRaw != null ? parseInt(slaRaw, 10) : CONFIG.defaultSlaMinutes,
            tags: Array.isArray(raw.tags) ? raw.tags : [],
            meta: raw.meta && typeof raw.meta === 'object' ? raw.meta : {}
        };
    }

    async function carregarEntregadoresDaApi(produto, quantidade) {
        const params = new URLSearchParams();
        if (produto) params.append('produto', produto);
        if (Number.isFinite(quantidade) && quantidade > 0) {
            params.append('quantidade', String(quantidade));
        }

        const url = CONFIG.endpoints.vendors + (params.toString() ? '?' + params.toString() : '');

        const resp = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });

        if (!resp.ok) {
            throw new Error('Falha ao consultar api/v2/entregadores.php: ' + resp.status);
        }

        let payload = await resp.json();
        // Suporte ao padrão ApiResponse {ok:true,data:{...}}
        if (payload && payload.ok === true && payload.data !== undefined) {
            payload = payload.data;
        }

        let list;
        if (Array.isArray(payload)) {
            list = payload;
        } else if (payload && Array.isArray(payload.entregadores)) {
            list = payload.entregadores;
        } else if (payload && Array.isArray(payload.data)) {
            list = payload.data;
        } else if (payload && payload.data && Array.isArray(payload.data.entregadores)) {
            list = payload.data.entregadores;
        } else {
            throw new Error('Formato inválido retornado por api/v2/entregadores.php.');
        }

        const vendors = list
            .map(normalizarEntregador)
            .filter(v => v !== null);

        return vendors;
    }

    function carregarEntregadoresEstaticos() {
        const raw = [
            {
                id: 1,
                name: 'João Silva - Zona Sul',
                phone: '(11) 98888-0001',
                lat: -23.6501,
                lng: -46.6345,
                status: 'available',
                zone: 'Zona Sul',
                rating: 4.8,
                active_orders: 2,
                capacity: 8,
                sla_minutes: 35
            },
            {
                id: 2,
                name: 'Maria Santos - Centro',
                phone: '(11) 97777-0002',
                lat: -23.5510,
                lng: -46.6340,
                status: 'available',
                zone: 'Centro',
                rating: 4.9,
                active_orders: 1,
                capacity: 8,
                sla_minutes: 25
            },
            {
                id: 3,
                name: 'Carlos Oliveira - Zona Leste',
                phone: '(11) 96666-0003',
                lat: -23.5450,
                lng: -46.5000,
                status: 'unavailable',
                zone: 'Zona Leste',
                rating: 4.5,
                active_orders: 5,
                capacity: 8,
                sla_minutes: 40
            },
            {
                id: 4,
                name: 'Ana Costa - Zona Oeste',
                phone: '(11) 95555-0004',
                lat: -23.5500,
                lng: -46.7000,
                status: 'available',
                zone: 'Zona Oeste',
                rating: 4.7,
                active_orders: 3,
                capacity: 8,
                sla_minutes: 30
            }
        ];

        return raw.map(normalizarEntregador).filter(v => v !== null);
    }

    function carregarEntregadoresManuais() {
        return state.manualVendors.slice();
    }

    function calcularDistanciasEmLote(origem, vendors) {
        return new Promise((resolve) => {
            if (!state.distanceMatrixService || !vendors.length) {
                resolve(vendors.map(() => null));
                return;
            }

            const destinos = vendors.map(v => ({ lat: v.lat, lng: v.lng }));

            state.distanceMatrixService.getDistanceMatrix({
                origins: [origem],
                destinations: destinos,
                travelMode: google.maps.TravelMode.DRIVING,
                unitSystem: google.maps.UnitSystem.METRIC
            }, (response, status) => {
                if (status !== 'OK' || !response.rows || !response.rows[0]) {
                    console.error('DistanceMatrix error:', status, response);
                    resolve(vendors.map(() => null));
                    return;
                }

                const elements = response.rows[0].elements || [];
                const resultados = elements.map((el) => {
                    if (!el || el.status !== 'OK') return null;
                    return {
                        distance_meters: el.distance?.value ?? 0,
                        distance_text: el.distance?.text ?? '',
                        duration_seconds: el.duration?.value ?? 0,
                        duration_text: el.duration?.text ?? ''
                    };
                });

                resolve(resultados);
            });
        });
    }

    function computeScore(vendor, distance, client, strategy, defaultStrategy, mode) {
        if (!distance) return Number.POSITIVE_INFINITY;

        if (mode === 'manual') {
            return distance.distance_meters;
        }

        const strat = (strategy || defaultStrategy || 'balanced').toLowerCase();

        const dist_km = distance.distance_meters / 1000.0;
        const time_min = distance.duration_seconds / 60.0;

        const norm_dist = dist_km / 10.0;
        const sla = vendor.sla_minutes || CONFIG.defaultSlaMinutes;
        const norm_time = sla > 0 ? (time_min / sla) : 1.0;

        const capacity = vendor.capacity || CONFIG.defaultCapacity;
        const active = Math.max(vendor.active_orders || 0, 0);
        const load_ratio = Math.min(active / (capacity || 1), 1.0);

        const rating = vendor.rating != null ? vendor.rating : 3.0;
        const rating_norm = 1.0 - Math.max(Math.min(rating / 5.0, 1.0), 0.0);

        let zone_penalty = 0.0;
        if (client.zone && vendor.zone &&
            client.zone.toLowerCase() !== vendor.zone.toLowerCase()) {
            zone_penalty = 0.4;
        }

        let w_dist, w_time, w_load, w_rating, w_zone;
        if (strat === 'fast') {
            w_dist = 0.45; w_time = 0.30; w_load = 0.10; w_rating = 0.10; w_zone = 0.05;
        } else if (strat === 'low_load') {
            w_dist = 0.25; w_time = 0.15; w_load = 0.35; w_rating = 0.15; w_zone = 0.10;
        } else if (strat === 'zone_priority') {
            w_dist = 0.20; w_time = 0.20; w_load = 0.15; w_rating = 0.15; w_zone = 0.30;
        } else {
            w_dist = 0.30; w_time = 0.20; w_load = 0.20; w_rating = 0.20; w_zone = 0.10;
        }

        const base_score =
            w_dist * norm_dist +
            w_time * norm_time +
            w_load * load_ratio +
            w_rating * rating_norm +
            w_zone * zone_penalty;

        const sla_penalty = time_min > sla ? 0.5 : 0.0;
        return base_score + sla_penalty;
    }

    function geocodificarEndereco(endereco) {
        return new Promise((resolve) => {
            if (!state.geocoder) {
                resolve(null);
                return;
            }
            state.geocoder.geocode({ address: endereco }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    const loc = results[0].geometry.location;
                    state.ultimaPosicaoCliente = loc;
                    marcarClienteNoMapa(loc, results[0].formatted_address || endereco);
                    resolve(loc);
                } else {
                    resolve(null);
                }
            });
        });
    }

    function usarLocalizacaoAtual() {
        if (!navigator.geolocation) {
            setStatus('Geolocalização não suportada pelo navegador.', 'error');
            return;
        }

        setStatus('Obtendo localização atual...', 'info');

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const loc = new google.maps.LatLng(pos.coords.latitude, pos.coords.longitude);
                state.ultimaPosicaoCliente = loc;
                marcarClienteNoMapa(loc, 'Sua localização atual');
                setStatus('Localização obtida com sucesso. Agora execute o matching.', 'ok');
            },
            (err) => {
                console.error('Erro de geolocalização:', err);
                setStatus('Não foi possível obter a localização do dispositivo.', 'error');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            }
        );
    }

    async function enviarMatchParaAdm(matchResponse) {
        try {
            const resp = await fetch(CONFIG.endpoints.match, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(matchResponse)
            });
            if (!resp.ok) {
                console.warn('Falha ao registrar matching no ADM:', resp.status);
            }
        } catch (e) {
            console.warn('Erro ao registrar matching no ADM:', e);
        }
    }

    function montarMatchResponse(candidatos, clientObj, estrategia, produto, qtd) {
        const best = candidatos[0];
        const summary = {
            total_candidates: candidatos.length,
            best_vendor_id: best.vendor.id ?? null,
            best_vendor_name: best.vendor.name ?? null,
            strategy: estrategia,
            product_code: produto,
            quantity: Number.isFinite(qtd) && qtd > 0 ? qtd : 1
        };

        const candidatesPayload = candidatos.map(c => ({
            rank: c.rank,
            vendor: c.vendor,
            distance: c.distance,
            score: c.score,
            status: c.statusMatching || 'pending'
        }));

        return {
            summary,
            client: clientObj,
            candidates: candidatesPayload
        };
    }

    async function executarMatching() {
        if (state.loadingMatching) return;
        state.loadingMatching = true;
        limparRota();

        const t0 = performance.now();

        try {
            const endereco = document.getElementById('endereco-cliente').value.trim();
            const zonaCliente = document.getElementById('zona-cliente').value.trim() || null;
            const nomeCliente = document.getElementById('cliente-nome').value.trim() || null;
            const telefoneCliente = document.getElementById('cliente-telefone').value.trim() || null;

            const estrategia = document.getElementById('estrategia').value || 'balanced';
            const produto = document.getElementById('produto-codigo').value.trim() || null;
            const qtd = parseInt(document.getElementById('quantidade').value || '1', 10);
            const modoDados = document.getElementById('modo-dados').value || 'api';
            const enviarAdm = document.getElementById('enviar-adm').checked;

            if (!endereco && !state.ultimaPosicaoCliente) {
                setStatus('Informe o endereço do cliente ou use a localização atual.', 'error');
                state.loadingMatching = false;
                return;
            }

            setStatus('Resolvendo localização do cliente...', 'info');

            let clientLatLng = state.ultimaPosicaoCliente;
            let clientAddressText = endereco || 'Localização';

            if (!clientLatLng) {
                const loc = await geocodificarEndereco(endereco);
                if (!loc) {
                    setStatus('Não foi possível geocodificar o endereço informado.', 'error');
                    state.loadingMatching = false;
                    return;
                }
                clientLatLng = loc;
                clientAddressText = document.getElementById('endereco-cliente').value || endereco;
            }

            const clientObj = {
                name: nomeCliente,
                phone: telefoneCliente,
                address: clientAddressText,
                lat: clientLatLng.lat(),
                lng: clientLatLng.lng(),
                zone: zonaCliente
            };

            state.lastClient = clientObj;
            state.lastStrategy = estrategia;
            state.lastProductCode = produto;
            state.lastQuantity = qtd;

            setStatus('Carregando entregadores...', 'info');

            let vendorsSource;
            if (modoDados === 'manual') {
                vendorsSource = carregarEntregadoresManuais();
            } else if (modoDados === 'static') {
                vendorsSource = carregarEntregadoresEstaticos();
            } else {
                vendorsSource = await carregarEntregadoresDaApi(produto, qtd);
            }

            const vendorsFiltrados = vendorsSource.filter(v => v.status !== 'unavailable');
            if (!vendorsFiltrados.length) {
                setStatus('Nenhum entregador disponível para este contexto.', 'error');
                state.loadingMatching = false;
                return;
            }

            setStatus('Calculando distâncias e tempos (rotas via Google Distance Matrix)...', 'info');

            const distancias = await calcularDistanciasEmLote(clientLatLng, vendorsFiltrados);

            const candidatos = [];
            for (let i = 0; i < vendorsFiltrados.length; i++) {
                const v = vendorsFiltrados[i];
                const d = distancias[i];
                if (!d) continue;

                const score = computeScore(v, d, clientObj, estrategia, 'balanced', modoDados);
                candidatos.push({
                    rank: 0,
                    vendor: v,
                    distance: d,
                    score,
                    statusMatching: 'pending'
                });
            }

            if (!candidatos.length) {
                setStatus('Nenhum entregador com rota válida para o cliente.', 'error');
                state.candidatos = [];
                atualizarStats({
                    total: vendorsFiltrados.length,
                    disponiveis: 0,
                    tempoSeg: ((performance.now() - t0) / 1000).toFixed(2),
                    distanciaMediaKm: 0
                });
                state.loadingMatching = false;
                return;
            }

            candidatos.sort((a, b) => a.score - b.score);
            candidatos.forEach((c, idx) => c.rank = idx + 1);
            state.candidatos = candidatos;

            const t1 = performance.now();
            const tempoSeg = ((t1 - t0) / 1000).toFixed(2);

            const total = vendorsFiltrados.length;
            const disponiveis = vendorsFiltrados.filter(v => v.status === 'available').length || total;
            let distanciaMediaKm = 0;
            if (candidatos.length > 0) {
                distanciaMediaKm = candidatos.reduce((acc, c) => acc + (c.distance.distance_meters / 1000.0), 0) / candidatos.length;
            }

            atualizarStats({
                total,
                disponiveis,
                tempoSeg: Number(tempoSeg),
                distanciaMediaKm
            });

            desenharCandidatosNoMapa(clientLatLng, candidatos);
            const matchResponse = montarMatchResponse(candidatos, clientObj, estrategia, produto, qtd);
            renderResultados();

            const best = candidatos[0];
            const destinoBest = new google.maps.LatLng(best.vendor.lat, best.vendor.lng);
            desenharRota(clientLatLng, destinoBest);

            setStatus(`Matching concluído com sucesso (${candidatos.length} candidatos em ${tempoSeg}s).`, 'ok');

            if (enviarAdm) {
                enviarMatchParaAdm(matchResponse);
            }
        } catch (err) {
            console.error('Erro no matching:', err);
            setStatus('Erro inesperado ao executar o matching. Verifique console.', 'error');
        } finally {
            state.loadingMatching = false;
        }
    }

    async function confirmarCandidato(rank) {
        const candidato = state.candidatos.find(c => c.rank === rank);
        if (!candidato) return;

        try {
            const waUrl = montarLinkWhatsApp(candidato.vendor);
            window.open(waUrl, '_blank');
        } catch (e) {
            console.warn('Falha ao abrir WhatsApp automaticamente:', e);
        }

        state.candidatos.forEach(c => {
            if (c === candidato) c.statusMatching = 'confirmed';
            else if (!c.statusMatching) c.statusMatching = 'pending';
        });

        renderResultados();

        if (!state.lastClient) return;

        const matchResponse = montarMatchResponse(
            state.candidatos,
            state.lastClient,
            state.lastStrategy,
            state.lastProductCode,
            state.lastQuantity
        );

        matchResponse.summary.confirmed_vendor_id = candidato.vendor.id ?? null;
        matchResponse.summary.confirmed_vendor_name = candidato.vendor.name ?? null;
        matchResponse.confirmation = {
            vendor: candidato.vendor,
            client: state.lastClient,
            product_code: state.lastProductCode,
            quantity: state.lastQuantity
        };

        enviarMatchParaAdm(matchResponse);
        setStatus(`Entregador ${candidato.vendor.name} confirmado. Detalhes enviados para o ADM e WhatsApp.`, 'ok');

        if (state.ultimaPosicaoCliente) {
            const origem = state.ultimaPosicaoCliente;
            const destino = new google.maps.LatLng(candidato.vendor.lat, candidato.vendor.lng);
            desenharRota(origem, destino);
        }
    }

    function resetFormularioManual() {
        document.getElementById('manual-nome').value = '';
        document.getElementById('manual-telefone').value = '';
        document.getElementById('manual-endereco').value = '';
        state.manualEnderecoLatLng = null;
        state.manualEnderecoTexto = '';
        state.manualEditIndex = null;
    }

    function renderListaManuais() {
        const container = document.getElementById('lista-manual-entregadores');
        if (!container) return;

        if (!state.manualVendors.length) {
            container.innerHTML = '<div class="info-linha">Nenhum entregador manual cadastrado.</div>';
            return;
        }

        container.innerHTML = '';

        state.manualVendors.forEach((v, index) => {
            const card = document.createElement('div');
            card.className = 'entregador-card';

            const statusLabel = formatStatus(v.status);
            const statusClass = v.status === 'available' ? '' : 'indisponivel';
            const cargaTexto = `${v.active_orders || 0}/${v.capacity || CONFIG.defaultCapacity}`;

            card.innerHTML = `
                <div class="entregador-header">
                    <div class="entregador-nome">${v.name}</div>
                    <div class="entregador-status ${statusClass}">
                        ${statusLabel}
                    </div>
                </div>
                <div class="entregador-meta">
                    Tel: ${v.phone || 'N/D'} · Zona: ${v.zone || 'N/D'}
                </div>
                <div class="entregador-meta">
                    Lat/Lng: ${v.lat.toFixed(5)}, ${v.lng.toFixed(5)}
                </div>
                <div class="entregador-score">
                    Rating: ${v.rating != null ? v.rating.toFixed(1) : 'N/D'} ·
                    Carga: ${cargaTexto} ·
                    SLA: ${v.sla_minutes || CONFIG.defaultSlaMinutes} min
                </div>
                <div class="toolbar" style="margin-top:6px;">
                    <button class="btn btn-outline btn-small btn-edit" data-index="${index}">✏️ Editar</button>
                    <button class="btn btn-secondary btn-small btn-delete" data-index="${index}">🗑️ Excluir</button>
                </div>
            `;

            const btnEdit = card.querySelector('.btn-edit');
            const btnDelete = card.querySelector('.btn-delete');

            btnEdit.addEventListener('click', (e) => {
                e.preventDefault();
                editarEntregadorManual(index);
            });

            btnDelete.addEventListener('click', (e) => {
                e.preventDefault();
                excluirEntregadorManual(index);
            });

            container.appendChild(card);
        });
    }

    function editarEntregadorManual(index) {
        const v = state.manualVendors[index];
        if (!v) return;

        document.getElementById('manual-nome').value = v.name || '';
        document.getElementById('manual-telefone').value = v.phone || '';
        document.getElementById('manual-endereco').value = v.address || '';

        state.manualEnderecoLatLng = new google.maps.LatLng(v.lat, v.lng);
        state.manualEnderecoTexto = v.address || '';
        state.manualEditIndex = index;

        setStatus(`Editando entregador manual: ${v.name}`, 'info');
    }

    function excluirEntregadorManual(index) {
        state.manualVendors.splice(index, 1);
        renderListaManuais();
        setStatus('Entregador manual removido.', 'ok');
    }

    async function salvarEntregadorManual() {
        const nome = document.getElementById('manual-nome').value.trim();
        const telefone = document.getElementById('manual-telefone').value.trim();
        const enderecoTexto = document.getElementById('manual-endereco').value.trim();

        if (!nome || !enderecoTexto) {
            setStatus('Nome e endereço do entregador são obrigatórios.', 'error');
            return;
        }

        let latLng = state.manualEnderecoLatLng;
        let enderecoFinal = state.manualEnderecoTexto || enderecoTexto;

        if (!latLng) {
            if (!state.geocoder) {
                setStatus('Geocoder do Maps não disponível para geolocalizar o entregador.', 'error');
                return;
            }
            const loc = await new Promise((resolve) => {
                state.geocoder.geocode({ address: enderecoTexto }, (results, status) => {
                    if (status === 'OK' && results[0]) {
                        resolve({
                            loc: results[0].geometry.location,
                            texto: results[0].formatted_address || enderecoTexto
                        });
                    } else {
                        resolve(null);
                    }
                });
            });

            if (!loc) {
                setStatus('Não foi possível geolocalizar o endereço do entregador.', 'error');
                return;
            }

            latLng = loc.loc;
            enderecoFinal = loc.texto;
        }

        const raw = {
            name: nome,
            phone: telefone,
            address: enderecoFinal,
            lat: latLng.lat(),
            lng: latLng.lng(),
            status: 'available',
            zone: null,
            rating: 4.5,
            active_orders: 0,
            capacity: CONFIG.defaultCapacity,
            sla_minutes: CONFIG.defaultSlaMinutes
        };

        const normalizado = normalizarEntregador(raw);
        if (!normalizado) {
            setStatus('Falha ao normalizar dados do entregador.', 'error');
            return;
        }

        if (state.manualEditIndex != null) {
            state.manualVendors[state.manualEditIndex] = normalizado;
            setStatus('Entregador manual atualizado com sucesso.', 'ok');
        } else {
            state.manualVendors.push(normalizado);
            setStatus('Entregador manual adicionado com sucesso.', 'ok');
        }

        resetFormularioManual();
        renderListaManuais();
    }

    function limparSelecao() {
        document.getElementById('cliente-nome').value = '';
        document.getElementById('cliente-telefone').value = '';
        document.getElementById('endereco-cliente').value = '';
        document.getElementById('zona-cliente').value = '';
        document.getElementById('produto-codigo').value = '';
        document.getElementById('quantidade').value = '1';
        document.getElementById('prioridade').value = 'normal';
        document.getElementById('estrategia').value = 'balanced';

        document.getElementById('info-cliente').innerHTML = '';
        document.getElementById('lista-entregadores').innerHTML = '';
        document.getElementById('rodape-info').innerHTML = '';
        document.getElementById('chip-contagem').textContent = '0 candidatos';

        atualizarStats({
            total: 0,
            disponiveis: 0,
            tempoSeg: 0,
            distanciaMediaKm: 0
        });

        if (state.clienteMarker) {
            state.clienteMarker.setMap(null);
            state.clienteMarker = null;
        }
        limparMarcadoresEntregadores();
        limparRota();
        state.ultimaPosicaoCliente = null;
        state.candidatos = [];
        state.lastClient = null;
        setStatus('Seleção limpa. Informe novos dados para continuar.', 'info');
    }

    function init() {
        const mapDiv = document.getElementById('map');

        state.mapa = new google.maps.Map(mapDiv, {
            center: { lat: -14.2350, lng: -51.9253 },
            zoom: 4,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            styles: [
                { elementType: "geometry", stylers: [{ color: "#0b1120" }] },
                { elementType: "labels.text.stroke", stylers: [{ color: "#0b1120" }] },
                { elementType: "labels.text.fill", stylers: [{ color: "#94a3b8" }] },
                {
                    featureType: "road",
                    elementType: "geometry",
                    stylers: [{ color: "#1e293b" }]
                }
            ]
        });

        state.geocoder = new google.maps.Geocoder();
        state.distanceMatrixService = new google.maps.DistanceMatrixService();
        state.directionsService = new google.maps.DirectionsService();
        state.directionsRenderer = new google.maps.DirectionsRenderer({
            suppressMarkers: true,
            preserveViewport: true
        });

        const inputEndereco = document.getElementById('endereco-cliente');
        const autocomplete = new google.maps.places.Autocomplete(inputEndereco, {
            componentRestrictions: { country: 'br' },
            fields: ['geometry', 'formatted_address']
        });
        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (!place.geometry || !place.geometry.location) return;
            state.ultimaPosicaoCliente = place.geometry.location;
            marcarClienteNoMapa(place.geometry.location, place.formatted_address || inputEndereco.value);
            setStatus('Localização do cliente definida com sucesso.', 'ok');
        });

        const inputManualEndereco = document.getElementById('manual-endereco');
        const autocompleteManual = new google.maps.places.Autocomplete(inputManualEndereco, {
            componentRestrictions: { country: 'br' },
            fields: ['geometry', 'formatted_address']
        });
        autocompleteManual.addListener('place_changed', () => {
            const place = autocompleteManual.getPlace();
            if (!place.geometry || !place.geometry.location) return;
            state.manualEnderecoLatLng = place.geometry.location;
            state.manualEnderecoTexto = place.formatted_address || inputManualEndereco.value;
        });

        document.getElementById('btn-match').addEventListener('click', executarMatching);
        document.getElementById('btn-localizar').addEventListener('click', usarLocalizacaoAtual);
        document.getElementById('btn-limpar').addEventListener('click', limparSelecao);

        document.getElementById('endereco-cliente').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                executarMatching();
            }
        });

        document.getElementById('btn-salvar-manual').addEventListener('click', (e) => {
            e.preventDefault();
            salvarEntregadorManual();
        });
        document.getElementById('btn-reset-form').addEventListener('click', (e) => {
            e.preventDefault();
            resetFormularioManual();
        });

        renderListaManuais();
        setStatus('Mapa carregado. Configure o cliente e execute o matching.', 'info');
    }

    return {
        init
    };
})();

/* Tabs independentes do Google Maps */
function initTabsUI() {
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            const content = document.getElementById(tabId);
            if (content) {
                content.classList.add('active');
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initTabsUI);

function initMatchingApp() {
    MatchingApp.init();
}
</script>

<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode(getenv('GOOGLE_MAPS_API_KEY')?:getenv('ALABAMA_GOOGLE_MAPS_API_KEY')?:''); ?>&libraries=places&callback=initMatchingApp">
</script>
</body>
</html>
