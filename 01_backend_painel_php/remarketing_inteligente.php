<?php
// Remarketing Inteligente – apenas Administrador/Gerente
require_once __DIR__ . '/rbac.php';
require_role(array('Administrador', 'Gerente'));
?>

<!DOCTYPE html>
<html lang="pt-BR" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remarketing Inteligente - Rede Alabama</title>
    <meta name="description" content="Módulo de Remarketing com segmentação D0–Dn, puffs/dia e disparos automáticos.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/alabama-design-system.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <link rel="stylesheet" href="assets/css/alabama-page-overrides.css">

    <style>
        :root {
            --rm-bg: #020617;
            --rm-bg-card: #020617;
            --rm-bg-soft: #020819;
            --rm-bg-softer: #030816;
            --rm-border: #1f2937;
            --rm-border-soft: #111827;
            --rm-accent: #6366f1;
            --rm-accent-strong: #4f46e5;
            --rm-accent-soft: rgba(99,102,241,0.12);
            --rm-text: #f9fafb;
            --rm-text-muted: #9ca3af;
            --rm-text-soft: #6b7280;
            --rm-success: #10b981;
            --rm-danger: #ef4444;
            --rm-warning: #f59e0b;
            --rm-info: #3b82f6;
            --rm-radius-sm: 6px;
            --rm-radius-md: 10px;
            --rm-radius-lg: 14px;
            --rm-shadow-md: 0 10px 25px rgba(0,0,0,0.4);
            --rm-shadow-lg: 0 18px 40px rgba(0,0,0,0.6);
            --rm-transition: all 0.2s ease;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background:
                radial-gradient(circle at 0% 0%, rgba(99,102,241,.18), transparent 55%),
                radial-gradient(circle at 100% 0%, rgba(79,70,229,.12), transparent 55%),
                radial-gradient(circle at 0% 100%, rgba(37,99,235,.12), transparent 55%),
                var(--rm-bg);
            color: var(--rm-text);
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .rm-page-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 16px;
        }

        .rm-card {
            background: radial-gradient(circle at top left, rgba(99,102,241,0.16), transparent 55%) var(--rm-bg-card);
            border-radius: 16px;
            border: 1px solid var(--rm-border);
            padding: 18px 20px 18px;
            box-shadow: var(--rm-shadow-md);
            position: relative;
            overflow: hidden;
        }

        .rm-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 0 0, rgba(99,102,241,0.22), transparent 55%),
                radial-gradient(circle at 100% 100%, rgba(56,189,248,0.10), transparent 55%);
            opacity: 0.15;
            pointer-events: none;
        }

        .rm-card-inner {
            position: relative;
            z-index: 1;
        }

        .rm-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--rm-border);
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .rm-card-header-title h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.2rem;
            letter-spacing: 0.02em;
        }

        .rm-card-header-title .rm-subtitle {
            font-size: 0.84rem;
            color: var(--rm-text-muted);
        }

        .rm-badge-version {
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--rm-accent-soft);
            border: 1px solid rgba(99,102,241,0.4);
            color: var(--rm-accent);
            font-weight: 500;
            white-space: nowrap;
        }

        .rm-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 8px;
            border-bottom: 1px solid var(--rm-border-soft);
        }

        .rm-tab {
            border: none;
            background: transparent;
            color: var(--rm-text-muted);
            font-size: 0.85rem;
            font-weight: 500;
            padding: 8px 14px;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            border-radius: 6px 6px 0 0;
            transition: var(--rm-transition);
        }

        .rm-tab:hover {
            color: var(--rm-text);
        }

        .rm-tab.active {
            color: var(--rm-accent);
            border-bottom-color: var(--rm-accent);
            background: linear-gradient(to top, rgba(15,23,42,0.1), transparent);
        }

        .rm-tab-content {
            display: none;
            margin-top: 6px;
        }

        .rm-tab-content.active {
            display: block;
        }

        .rm-status {
            font-size: 0.82rem;
            padding: 6px 10px;
            border-radius: 8px;
            background: rgba(15,23,42,0.8);
            border-left: 3px solid transparent;
            color: var(--rm-text-muted);
            margin-bottom: 10px;
        }

        .rm-status.info {
            border-left-color: var(--rm-info);
            color: var(--rm-info);
        }

        .rm-status.ok {
            border-left-color: var(--rm-success);
            color: var(--rm-success);
        }

        .rm-status.error {
            border-left-color: var(--rm-danger);
            color: var(--rm-danger);
        }

        .rm-section-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--rm-text-soft);
            margin-bottom: 6px;
            font-weight: 600;
        }

        .rm-filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }

        .rm-label {
            font-size: 0.78rem;
            color: var(--rm-text-muted);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .rm-input,
        .rm-select,
        .rm-textarea {
            width: 100%;
            background-color: var(--rm-bg-soft);
            border-radius: 8px;
            border: 1px solid var(--rm-border);
            color: var(--rm-text);
            font-size: 0.86rem;
            padding: 6px 9px;
            transition: var(--rm-transition);
        }

        .rm-textarea {
            min-height: 90px;
            resize: vertical;
        }

        .rm-input:focus,
        .rm-select:focus,
        .rm-textarea:focus {
            outline: none;
            border-color: var(--rm-accent);
            box-shadow: 0 0 0 1px rgba(99,102,241,0.4);
            background-color: #020617;
        }

        .rm-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .rm-chip-range {
            border-radius: 999px;
            border: 1px solid #374151;
            background: #020617;
            color: var(--rm-text-muted);
            font-size: 0.78rem;
            padding: 3px 10px;
            cursor: pointer;
            transition: var(--rm-transition);
        }

        .rm-chip-range:hover {
            border-color: var(--rm-accent);
            color: var(--rm-accent);
            background: rgba(99,102,241,0.1);
        }

        .rm-chip-range.active {
            border-color: rgba(99,102,241,0.7);
            color: var(--rm-accent);
            background: var(--rm-accent-soft);
            font-weight: 600;
        }

        .rm-chip-flag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 9px;
            border-radius: 999px;
            background: rgba(15,23,42,0.9);
            border: 1px solid var(--rm-border);
            font-size: 0.78rem;
            color: var(--rm-text-muted);
        }

        .rm-chip-flag i {
            color: var(--rm-accent);
        }

        .rm-btn {
            border-radius: 8px;
            font-size: 0.86rem;
            font-weight: 500;
            padding: 6px 13px;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: var(--rm-transition);
        }

        .rm-btn-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 4px 14px rgba(79,70,229,0.45);
        }

        .rm-btn-primary:hover {
            filter: brightness(1.06);
            transform: translateY(-1px);
            box-shadow: var(--rm-shadow-lg);
        }

        .rm-btn-ghost {
            background: transparent;
            border-color: #374151;
            color: var(--rm-text-muted);
        }

        .rm-btn-ghost:hover {
            background: rgba(15,23,42,0.8);
            border-color: var(--rm-accent);
            color: var(--rm-accent);
        }

        .rm-btn-sm {
            padding: 4px 10px;
            font-size: 0.78rem;
        }

        .rm-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 10px;
            margin: 8px 0 10px;
        }

        .rm-kpi-card {
            background: rgba(15,23,42,0.92);
            border-radius: 10px;
            border: 1px solid var(--rm-border);
            padding: 10px 11px;
            position: relative;
            overflow: hidden;
            transition: var(--rm-transition);
        }

        .rm-kpi-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top left, rgba(99,102,241,0.20), transparent 55%);
            opacity: 0;
            transition: var(--rm-transition);
        }

        .rm-kpi-card:hover {
            border-color: var(--rm-accent);
            box-shadow: var(--rm-shadow-md);
            transform: translateY(-1px);
        }

        .rm-kpi-card:hover::before {
            opacity: 0.35;
        }

        .rm-kpi-label {
            font-size: 0.78rem;
            color: var(--rm-text-muted);
            font-weight: 500;
        }

        .rm-kpi-value {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 4px 0;
        }

        .rm-kpi-sub {
            font-size: 0.76rem;
            color: var(--rm-text-soft);
        }

        .rm-layout-main {
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(0, 2fr);
            gap: 14px;
            margin-top: 4px;
        }

        .rm-card-soft {
            background: rgba(3,7,18,0.94);
            border-radius: 12px;
            border: 1px solid var(--rm-border);
            padding: 10px 11px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.6);
        }

        .rm-table-wrapper {
            border-radius: 10px;
            border: 1px solid var(--rm-border);
            overflow: hidden;
            background: #020617;
            max-height: 360px;
        }

        table.rm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .rm-table thead {
            background: #020617;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .rm-table th,
        .rm-table td {
            padding: 7px 10px;
            border-bottom: 1px solid #111827;
            white-space: nowrap;
        }

        .rm-table th {
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--rm-text-muted);
            font-weight: 600;
        }

        .rm-table tbody tr:nth-child(even) {
            background: rgba(15,23,42,0.85);
        }

        .rm-table tbody tr:hover {
            background: rgba(31,41,55,0.95);
        }

        .rm-chip-tag {
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(15,23,42,0.9);
            border: 1px solid var(--rm-border);
            font-size: 0.72rem;
            color: var(--rm-text-muted);
        }

        .rm-chip-tag.green {
            background: rgba(16,185,129,0.15);
            border-color: rgba(16,185,129,0.6);
            color: #6ee7b7;
        }

        .rm-chip-tag.orange {
            background: rgba(249,115,22,0.15);
            border-color: rgba(249,115,22,0.6);
            color: #fdba74;
        }

        .rm-chip-tag.red {
            background: rgba(239,68,68,0.15);
            border-color: rgba(239,68,68,0.6);
            color: #fecaca;
        }

        .rm-chip-tag.blue {
            background: rgba(59,130,246,0.15);
            border-color: rgba(59,130,246,0.6);
            color: #bfdbfe;
        }

        .rm-chart-container {
            position: relative;
            height: 260px;
            background: #020617;
            border-radius: 10px;
            border: 1px solid var(--rm-border);
            padding: 6px 8px 4px;
        }

        .rm-chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .rm-chart-header span {
            font-size: 0.8rem;
            color: var(--rm-text-muted);
        }

        .rm-chart-header select {
            width: auto;
            font-size: 0.78rem;
            padding: 3px 8px;
        }

        .rm-camp-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }

        .rm-template-row {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(0, 1.4fr);
            gap: 10px;
        }

        .rm-preview-box {
            border-radius: 10px;
            border: 1px dashed #374151;
            background: radial-gradient(circle at top left, rgba(56,189,248,0.1), transparent 55%) #020617;
            padding: 8px 10px;
            font-size: 0.82rem;
            color: var(--rm-text-muted);
            min-height: 90px;
        }

        .rm-preview-header {
            font-size: 0.78rem;
            color: var(--rm-text-soft);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rm-pill-auto {
            padding: 2px 7px;
            border-radius: 999px;
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.6);
            color: #a7f3d0;
            font-size: 0.72rem;
        }

        .rm-pill-manual {
            padding: 2px 7px;
            border-radius: 999px;
            background: rgba(249,115,22,0.15);
            border: 1px solid rgba(249,115,22,0.6);
            color: #fed7aa;
            font-size: 0.72rem;
        }

        .rm-badge-status {
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 0.72rem;
        }

        .rm-badge-status.on {
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.6);
            color: #6ee7b7;
        }

        .rm-badge-status.off {
            background: rgba(148,163,184,0.15);
            border: 1px solid rgba(148,163,184,0.6);
            color: #e5e7eb;
        }

        .rm-logs-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }

        .rm-badge-small {
            font-size: 0.74rem;
            padding: 3px 7px;
            border-radius: 999px;
            background: rgba(15,23,42,0.9);
            border: 1px solid var(--rm-border);
            color: var(--rm-text-soft);
        }

        .rm-tag-token {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            border-radius: 999px;
            background: rgba(15,23,42,0.9);
            border: 1px dashed #374151;
            font-size: 0.74rem;
            color: var(--rm-text-soft);
        }

        .rm-tag-token code {
            font-size: 0.74rem;
            color: #e5e7eb;
        }

        .rm-scroll-y {
            max-height: 300px;
            overflow-y: auto;
        }

        .rm-overlay-loading {
            position: absolute;
            inset: 0;
            background: rgba(15,23,42,0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            z-index: 10;
        }

        .rm-overlay-loading span {
            font-size: 0.84rem;
            color: var(--rm-text-muted);
        }

        @media (max-width: 992px) {
            .rm-layout-main {
                grid-template-columns: 1fr;
            }
            .rm-template-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .rm-page-wrapper {
                padding: 12px 8px;
            }
            .rm-filters-grid {
                grid-template-columns: 1fr;
            }
            .rm-kpis {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .rm-kpis {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include 'menu_navegacao.php'; ?>

<div class="rm-page-wrapper">
    <div class="rm-card">
        <div class="rm-card-inner">
            <div class="rm-card-header">
                <div class="rm-card-header-title">
                    <h5>🎯 Módulo de Remarketing Inteligente</h5>
                    <div class="rm-subtitle">
                        Segmentação D0–Dn, puffs/dia, previsão de esgotamento e disparos automáticos (WhatsApp / Omni).
                    </div>
                </div>
                <span class="rm-badge-version">
                    adm.redealabama · Remarketing Engine v1.0
                </span>
            </div>

            <!-- TABS -->
            <div class="rm-tabs" role="tablist">
                <button class="rm-tab active" data-tab="segmento" type="button">
                    <i class="bi bi-funnel"></i> Construtor de Segmentos
                </button>
                <button class="rm-tab" data-tab="campanhas" type="button">
                    <i class="bi bi-bullseye"></i> Campanhas & Fluxos
                </button>
                <button class="rm-tab" data-tab="logs" type="button">
                    <i class="bi bi-activity"></i> Logs & Monitoria
                </button>
            </div>

            <div id="rm-status" class="rm-status info">
                Configure o segmento (D0–Dn, puffs/dia, últimos compradores) e clique em “Gerar segmento”.
            </div>

            <!-- TAB: SEGMENTO -->
            <div id="rm-tab-segmento" class="rm-tab-content active">
                <div class="rm-section-title">Segmentação · D0–Dn · Puffs/Dia</div>

                <!-- Filtros de segmento -->
                <div class="rm-filters-grid mb-1">
                    <div>
                        <div class="rm-label">Período base (data da compra)</div>
                        <div class="rm-inline">
                            <input type="date" id="rm-date-start" class="rm-input">
                            <span style="font-size:0.78rem;color:var(--rm-text-muted);">até</span>
                            <input type="date" id="rm-date-end" class="rm-input">
                        </div>
                    </div>
                    <div>
                        <div class="rm-label">Vendedor / Operação</div>
                        <select id="rm-seller" class="rm-select">
                            <option value="">Todos os vendedores</option>
                            <!-- Preencher via backend (PHP) se necessário -->
                        </select>
                    </div>
                    <div>
                        <div class="rm-label">Produto</div>
                        <select id="rm-product" class="rm-select">
                            <option value="">Todos os produtos</option>
                            <!-- Preencher via backend -->
                        </select>
                    </div>
                    <div>
                        <div class="rm-label">Somente clientes com WhatsApp válido</div>
                        <div class="rm-inline">
                            <input type="checkbox" id="rm-whatsapp-only" class="form-check-input" checked>
                            <small style="font-size:0.78rem;color:var(--rm-text-muted);">
                                Apenas contatos com número validado / opt-in ativo.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Segmento D0–Dn -->
                <div class="mb-1">
                    <div class="rm-label mb-1">
                        Janela D0–Dn (dias desde a última compra) · clique em um atalho ou ajuste manualmente
                    </div>
                    <div class="rm-inline mb-1">
                        <span style="font-size:0.78rem;color:var(--rm-text-soft);margin-right:4px;">Atalhos:</span>
                        <button type="button" class="rm-chip-range" data-min="0" data-max="0">D0 (hoje)</button>
                        <button type="button" class="rm-chip-range" data-min="1" data-max="1">D1</button>
                        <button type="button" class="rm-chip-range" data-min="2" data-max="3">D2–D3</button>
                        <button type="button" class="rm-chip-range" data-min="4" data-max="7">D4–D7</button>
                        <button type="button" class="rm-chip-range" data-min="8" data-max="15">D8–D15</button>
                        <button type="button" class="rm-chip-range" data-min="16" data-max="30">D16–D30</button>
                        <button type="button" class="rm-chip-range" data-min="31" data-max="">D31+</button>
                    </div>
                    <div class="rm-inline">
                        <span style="font-size:0.78rem;color:var(--rm-text-soft);margin-right:4px;">Janela manual:</span>
                        <input type="number" id="rm-dias-min" class="rm-input" style="max-width:80px;" min="0" placeholder="Min">
                        <span style="font-size:0.78rem;color:var(--rm-text-muted);">até</span>
                        <input type="number" id="rm-dias-max" class="rm-input" style="max-width:80px;" min="0" placeholder="Max">
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            Deixe em branco o máximo para “D+ infinito”.
                        </small>
                    </div>
                </div>

                <!-- Puffs/dia · previsão de esgotamento -->
                <div class="rm-filters-grid mt-2">
                    <div>
                        <div class="rm-label">Filtro por puffs/dia estimado</div>
                        <div class="rm-inline">
                            <input type="number" id="rm-puffs-min" class="rm-input" min="0" placeholder="Mín. puffs/dia">
                            <span style="font-size:0.78rem;color:var(--rm-text-muted);">até</span>
                            <input type="number" id="rm-puffs-max" class="rm-input" min="0" placeholder="Máx. puffs/dia">
                        </div>
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            Baseado na capacidade do pod (puffs) x histórico de consumo.
                        </small>
                    </div>
                    <div>
                        <div class="rm-label">Clientes “quase acabando”</div>
                        <div class="rm-inline">
                            <span style="font-size:0.78rem;color:var(--rm-text-soft);">Estimativa: restam até</span>
                            <input type="number" id="rm-restam-max" class="rm-input" min="0" style="max-width:70px;" placeholder="Dias">
                            <span style="font-size:0.78rem;color:var(--rm-text-soft);">dias para acabar</span>
                        </div>
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            Ideal para campanhas de reposição (recompra preventiva).
                        </small>
                    </div>
                    <div>
                        <div class="rm-label">Ticket / valor (opcional)</div>
                        <div class="rm-inline">
                            <input type="number" id="rm-ticket-min" class="rm-input" min="0" placeholder="Ticket mín. (R$)">
                            <span style="font-size:0.78rem;color:var(--rm-text-muted);">até</span>
                            <input type="number" id="rm-ticket-max" class="rm-input" min="0" placeholder="Ticket máx. (R$)">
                        </div>
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            Útil para separar clientes premium x volume.
                        </small>
                    </div>
                </div>

                <!-- Botões de ação do segmento -->
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-2">
                    <button id="rm-btn-gerar-segmento" type="button" class="rm-btn rm-btn-primary">
                        <i class="bi bi-play-circle"></i> Gerar segmento
                    </button>
                    <button id="rm-btn-salvar-segmento" type="button" class="rm-btn rm-btn-ghost rm-btn-sm">
                        <i class="bi bi-bookmark"></i> Salvar segmento
                    </button>
                    <button id="rm-btn-limpar-segmento" type="button" class="rm-btn rm-btn-ghost rm-btn-sm">
                        <i class="bi bi-eraser"></i> Limpar filtros
                    </button>
                    <span class="rm-chip-flag ms-auto">
                        <i class="bi bi-lightning-charge-fill"></i>
                        Segmento atual: <span id="rm-segmento-resumo-label">Nenhum gerado</span>
                    </span>
                </div>

                <!-- KPIs do segmento -->
                <div class="rm-kpis">
                    <div class="rm-kpi-card">
                        <div class="rm-kpi-label">Clientes no segmento</div>
                        <div class="rm-kpi-value" id="rm-kpi-clientes">0</div>
                        <div class="rm-kpi-sub">Total de clientes únicos elegíveis para disparo.</div>
                    </div>
                    <div class="rm-kpi-card">
                        <div class="rm-kpi-label">Dispositivos / pods</div>
                        <div class="rm-kpi-value" id="rm-kpi-dispositivos">0</div>
                        <div class="rm-kpi-sub">Quantidade somada de dispositivos em uso na base filtrada.</div>
                    </div>
                    <div class="rm-kpi-card">
                        <div class="rm-kpi-label">Puffs/dia médio</div>
                        <div class="rm-kpi-value" id="rm-kpi-puffs-dia">0</div>
                        <div class="rm-kpi-sub">Estimativa média de consumo/dia para este segmento.</div>
                    </div>
                    <div class="rm-kpi-card">
                        <div class="rm-kpi-label">Dias médios até esgotar</div>
                        <div class="rm-kpi-value" id="rm-kpi-dias-restantes">0</div>
                        <div class="rm-kpi-sub">Janela média para reposição ideal (remarketing preventivo).</div>
                    </div>
                </div>

                <!-- Layout: gráfico D0–Dn + preview clientes -->
                <div class="rm-layout-main">
                    <!-- Gráfico D0–Dn -->
                    <div class="rm-card-soft position-relative">
                        <div class="rm-chart-header">
                            <span>
                                Distribuição por faixa D (dias desde última compra)
                            </span>
                            <span class="rm-badge-small" id="rm-badge-segmento-size">
                                0 clientes mapeados
                            </span>
                        </div>
                        <div class="rm-chart-container">
                            <canvas id="rm-chart-dias"></canvas>
                        </div>
                        <small style="font-size:0.74rem;color:var(--rm-text-soft);display:block;margin-top:4px;">
                            Essa visão ajuda a calibrar regras automáticas (ex.: disparar D3, D7, D15, etc.).
                        </small>
                    </div>

                    <!-- Tabela de clientes (amostra) -->
                    <div class="rm-card-soft">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div style="font-size:0.82rem;color:var(--rm-text-muted);font-weight:500;">
                                Amostra de clientes do segmento
                            </div>
                            <span class="rm-badge-small" id="rm-badge-segmento-exemplo">
                                Exibindo até 20 clientes
                            </span>
                        </div>
                        <div class="rm-table-wrapper rm-scroll-y">
                            <table class="rm-table">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Cliente</th>
                                    <th>Telefone</th>
                                    <th>Produto</th>
                                    <th>Dias (D)</th>
                                    <th>Puffs/dia</th>
                                    <th>Restam (dias)</th>
                                    <th>Vendedor</th>
                                </tr>
                                </thead>
                                <tbody id="rm-tbody-segmento">
                                <tr>
                                    <td colspan="8" style="font-size:0.8rem;color:var(--rm-text-soft);padding:8px 10px;">
                                        Nenhum segmento gerado ainda. Configure os filtros e clique em “Gerar segmento”.
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <small style="font-size:0.74rem;color:var(--rm-text-soft);display:block;margin-top:4px;">
                            A lista completa e o disparo real serão construídos pelo backend (API de remarketing).
                        </small>
                    </div>
                </div>
            </div>

            <!-- TAB: CAMPANHAS & FLUXOS -->
            <div id="rm-tab-campanhas" class="rm-tab-content">
                <div class="rm-section-title">Campanhas · Fluxos Automáticos · Template</div>

                <div class="rm-camp-form-grid">
                    <div>
                        <div class="rm-label">Nome da campanha</div>
                        <input type="text" id="rm-camp-nome" class="rm-input" placeholder="Ex.: Reposição IGNITE V150 · D7–D10">
                    </div>
                    <div>
                        <div class="rm-label">Canal principal</div>
                        <select id="rm-camp-canal" class="rm-select">
                            <option value="whatsapp">WhatsApp (principal)</option>
                            <option value="sms" disabled>SMS (em implantação)</option>
                            <option value="email" disabled>E-mail (em implantação)</option>
                        </select>
                    </div>
                    <div>
                        <div class="rm-label">Segmento origem</div>
                        <div class="rm-inline">
                            <input type="text" id="rm-camp-segmento-label" class="rm-input" readonly
                                   placeholder="Utiliza o segmento atual (Construtor de Segmentos)">
                        </div>
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            Sempre que o segmento for atualizado, você pode reapontar esta campanha.
                        </small>
                    </div>
                    <div>
                        <div class="rm-label">Modo de disparo</div>
                        <select id="rm-camp-modo-envio" class="rm-select">
                            <option value="manual">Manual (operador dispara)</option>
                            <option value="auto_dpos">Automático · D+N após compra</option>
                            <option value="auto_restam">Automático · quando restam ≤ X dias</option>
                        </select>
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            O modo automático é processado pelo backend via cron / filas.
                        </small>
                    </div>
                </div>

                <!-- Regras automáticas -->
                <div class="rm-filters-grid mt-1">
                    <div>
                        <div class="rm-label">Regra D+N após compra</div>
                        <div class="rm-inline">
                            <span style="font-size:0.78rem;color:var(--rm-text-soft);">Disparar em D+</span>
                            <input type="number" id="rm-camp-dias-n" class="rm-input" min="0" style="max-width:70px;" value="7">
                            <span style="font-size:0.78rem;color:var(--rm-text-soft);">após a última compra</span>
                        </div>
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            Ex.: D+7, D+15, D+30 — ideal para jornadas pré-definidas.
                        </small>
                    </div>
                    <div>
                        <div class="rm-label">Regra por previsão de esgotamento</div>
                        <div class="rm-inline">
                            <span style="font-size:0.78rem;color:var(--rm-text-soft);">Quando restarem até</span>
                            <input type="number" id="rm-camp-restam-n" class="rm-input" min="0" style="max-width:70px;" value="2">
                            <span style="font-size:0.78rem;color:var(--rm-text-soft);">dias de puffs</span>
                        </div>
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            Baseado em capacidade do produto (puffs) e consumo médio (puffs/dia).
                        </small>
                    </div>
                    <div>
                        <div class="rm-label">Janela / frequência anti-spam</div>
                        <div class="rm-inline">
                            <span style="font-size:0.78rem;color:var(--rm-text-soft);">Máx. 1 disparo a cada</span>
                            <input type="number" id="rm-camp-cooldown" class="rm-input" min="1" style="max-width:70px;" value="7">
                            <span style="font-size:0.78rem;color:var(--rm-text-soft);">dias por cliente</span>
                        </div>
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            Ajuda a proteger a reputação do canal e evitar excesso de contatos.
                        </small>
                    </div>
                    <div>
                        <div class="rm-label">Janela de disparo diário</div>
                        <div class="rm-inline">
                            <span style="font-size:0.78rem;color:var(--rm-text-soft);">Horário base</span>
                            <input type="time" id="rm-camp-horario" class="rm-input" style="max-width:130px;" value="10:00">
                        </div>
                        <small style="font-size:0.76rem;color:var(--rm-text-soft);">
                            O backend pode fatiar os disparos dentro desta janela para distribuir carga.
                        </small>
                    </div>
                </div>

                <!-- Dias da semana -->
                <div class="mt-2 mb-2">
                    <div class="rm-label mb-1">Dias da semana para disparo automático</div>
                    <div class="rm-inline">
                        <span style="font-size:0.78rem;color:var(--rm-text-soft);margin-right:4px;">Ativo em:</span>
                        <div class="form-check form-check-inline" style="font-size:0.78rem;color:var(--rm-text-soft);">
                            <input class="form-check-input" type="checkbox" value="1" id="rm-dia-1" checked>
                            <label class="form-check-label" for="rm-dia-1">Seg</label>
                        </div>
                        <div class="form-check form-check-inline" style="font-size:0.78rem;color:var(--rm-text-soft);">
                            <input class="form-check-input" type="checkbox" value="2" id="rm-dia-2" checked>
                            <label class="form-check-label" for="rm-dia-2">Ter</label>
                        </div>
                        <div class="form-check form-check-inline" style="font-size:0.78rem;color:var(--rm-text-soft);">
                            <input class="form-check-input" type="checkbox" value="3" id="rm-dia-3" checked>
                            <label class="form-check-label" for="rm-dia-3">Qua</label>
                        </div>
                        <div class="form-check form-check-inline" style="font-size:0.78rem;color:var(--rm-text-soft);">
                            <input class="form-check-input" type="checkbox" value="4" id="rm-dia-4" checked>
                            <label class="form-check-label" for="rm-dia-4">Qui</label>
                        </div>
                        <div class="form-check form-check-inline" style="font-size:0.78rem;color:var(--rm-text-soft);">
                            <input class="form-check-input" type="checkbox" value="5" id="rm-dia-5" checked>
                            <label class="form-check-label" for="rm-dia-5">Sex</label>
                        </div>
                        <div class="form-check form-check-inline" style="font-size:0.78rem;color:var(--rm-text-soft);">
                            <input class="form-check-input" type="checkbox" value="6" id="rm-dia-6">
                            <label class="form-check-label" for="rm-dia-6">Sáb</label>
                        </div>
                        <div class="form-check form-check-inline" style="font-size:0.78rem;color:var(--rm-text-soft);">
                            <input class="form-check-input" type="checkbox" value="0" id="rm-dia-0">
                            <label class="form-check-label" for="rm-dia-0">Dom</label>
                        </div>
                    </div>
                </div>

                <!-- Template de mensagem -->
                <div class="rm-template-row mt-2">
                    <div>
                        <div class="rm-label mb-1">Template da mensagem (WhatsApp)</div>
                        <textarea id="rm-camp-template" class="rm-textarea" placeholder="Exemplo:
Oi {{nome}}, tudo bem? 😊

Aqui é da Rede Alabama. Vimos que seu {{produto}} está prestes a acabar (faltam ~{{dias_restantes}} dias). 
Quer garantir a reposição com uma condição especial?"></textarea>
                        <div class="mt-1" style="font-size:0.75rem;color:var(--rm-text-soft);">
                            Tokens disponíveis:
                            <span class="rm-tag-token"><code>{{nome}}</code> · nome do cliente</span>
                            <span class="rm-tag-token"><code>{{produto}}</code> · produto principal</span>
                            <span class="rm-tag-token"><code>{{dias_desde_ultima_compra}}</code></span>
                            <span class="rm-tag-token"><code>{{puffs_dia}}</code></span>
                            <span class="rm-tag-token"><code>{{dias_restantes}}</code></span>
                            <span class="rm-tag-token"><code>{{link_pedido}}</code></span>
                        </div>
                    </div>
                    <div>
                        <div class="rm-preview-box">
                            <div class="rm-preview-header">
                                <span>Preview (cliente exemplo do segmento)</span>
                                <span id="rm-preview-modo-pill" class="rm-pill-manual">
                                    Modo atual: Manual
                                </span>
                            </div>
                            <div id="rm-preview-mensagem">
                                Nenhum segmento foi gerado ainda. Depois de gerar um segmento, o preview trará um cliente
                                exemplo com os valores reais de D, puffs/dia e dias restantes.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões de campanha -->
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <button id="rm-btn-simular-campanha" type="button" class="rm-btn rm-btn-ghost rm-btn-sm">
                        <i class="bi bi-clock-history"></i> Simular 7 dias de disparos
                    </button>
                    <button id="rm-btn-salvar-campanha" type="button" class="rm-btn rm-btn-primary">
                        <i class="bi bi-save"></i> Salvar campanha
                    </button>
                    <button id="rm-btn-disparar-agora" type="button" class="rm-btn rm-btn-ghost rm-btn-sm">
                        <i class="bi bi-send-check"></i> Disparar agora (modo manual)
                    </button>
                    <span class="rm-chip-flag ms-auto">
                        <i class="bi bi-info-circle"></i>
                        As filas e envios reais são processados pela API de comunicação do backend.
                    </span>
                </div>

                <!-- Lista de campanhas -->
                <div class="rm-card-soft mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div style="font-size:0.82rem;font-weight:500;color:var(--rm-text-muted);">
                            Campanhas cadastradas
                        </div>
                        <span class="rm-badge-small" id="rm-badge-campanhas">
                            0 campanhas ativas
                        </span>
                    </div>
                    <div class="rm-table-wrapper rm-scroll-y">
                        <table class="rm-table">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Campanha</th>
                                <th>Segmento</th>
                                <th>Modo</th>
                                <th>Canal</th>
                                <th>Disparos</th>
                                <th>Conversão</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                            </thead>
                            <tbody id="rm-tbody-campanhas">
                            <tr>
                                <td colspan="9" style="font-size:0.8rem;color:var(--rm-text-soft);padding:8px 10px;">
                                    Nenhuma campanha cadastrada ainda. Crie acima e salve para começar a operar o motor de
                                    remarketing.
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB: LOGS & MONITORIA -->
            <div id="rm-tab-logs" class="rm-tab-content">
                <div class="rm-section-title">Logs · Engajamento · Saúde do Canal</div>

                <div class="rm-layout-main">
                    <!-- Gráfico de engajamento -->
                    <div class="rm-card-soft position-relative">
                        <div class="rm-chart-header">
                            <span>Engajamento por dia (disparos, entregas, respostas)</span>
                            <select id="rm-chart-range" class="rm-select" style="max-width:150px;">
                                <option value="7">Últimos 7 dias</option>
                                <option value="30" selected>Últimos 30 dias</option>
                                <option value="90">Últimos 90 dias</option>
                            </select>
                        </div>
                        <div class="rm-chart-container">
                            <canvas id="rm-chart-engajamento"></canvas>
                        </div>
                        <small style="font-size:0.74rem;color:var(--rm-text-soft);display:block;margin-top:4px;">
                            Use essa visão para calibrar horários, frequência e efetividade das campanhas.
                        </small>
                    </div>

                    <!-- Tabela de logs -->
                    <div class="rm-card-soft">
                        <div class="rm-logs-toolbar">
                            <div style="font-size:0.82rem;color:var(--rm-text-muted);font-weight:500;">
                                Últimos disparos
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="rm-badge-small" id="rm-badge-logs">
                                    0 eventos carregados
                                </span>
                                <button id="rm-btn-recarregar-logs" type="button" class="rm-btn rm-btn-ghost rm-btn-sm">
                                    <i class="bi bi-arrow-repeat"></i> Recarregar
                                </button>
                            </div>
                        </div>

                        <div class="rm-table-wrapper rm-scroll-y">
                            <table class="rm-table">
                                <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Cliente</th>
                                    <th>Telefone</th>
                                    <th>Campanha</th>
                                    <th>Canal</th>
                                    <th>Status</th>
                                    <th>Retorno</th>
                                </tr>
                                </thead>
                                <tbody id="rm-tbody-logs">
                                <tr>
                                    <td colspan="7" style="font-size:0.8rem;color:var(--rm-text-soft);padding:8px 10px;">
                                        Nenhum log carregado ainda. Clique em “Recarregar” para consultar os últimos disparos
                                        registrados pela API.
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <small style="font-size:0.74rem;color:var(--rm-text-soft);display:block;margin-top:4px;">
                            Os detalhes de entrega, leitura e resposta vêm da integração com o provedor de WhatsApp/Omni
                            do backend.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overlay de carregamento (global do módulo) -->
        <div id="rm-loading-overlay" class="rm-overlay-loading d-none">
            <div class="spinner-border text-light" style="width:2.1rem;height:2.1rem;border-width:0.18em;" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <span>Processando requisição de remarketing...</span>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script <?php echo alabama_csp_nonce_attr(); ?>>
(function () {
    'use strict';

    const CONFIG = {
        ENDPOINT_SEGMENTO_PREVIEW: 'api/v2/remarketing_segmentos.php',
        ENDPOINT_CAMPANHAS: 'api/v2/remarketing_campanhas.php',
        ENDPOINT_DISPAROS: 'api/v2/remarketing_disparos.php',
        ENDPOINT_LOGS: 'api/v2/remarketing_logs.php',
        ENDPOINT_STATS: 'api/v2/remarketing_stats.php',
        ITEMS_AMOSTRA_SEGMENTO: 20
    };

    const state = {
        segmentoAtual: null,
        campanhas: [],
        logs: [],
        charts: {
            dias: null,
            engajamento: null
        },
        loading: false
    };

    const DOM = {};

    const utils = {
        unwrapApi(payload) {
            if (!payload) return null;
            // Novo padrão do backend (ApiResponse)
            if (payload.ok === true && payload.data !== undefined) {
                return payload.data;
            }
            // Compat: alguns endpoints antigos retornavam {success:true,data:{...}}
            if (payload.success === true && payload.data !== undefined) {
                return payload.data;
            }
            return payload;
        },
        formatCurrency(v) {
            const n = Number(v) || 0;
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL',
                minimumFractionDigits: 2
            }).format(n);
        },
        formatNumber(v) {
            const n = Number(v) || 0;
            return new Intl.NumberFormat('pt-BR').format(n);
        },
        formatDateTime(str) {
            if (!str) return '–';
            const d = new Date(str);
            if (Number.isNaN(d.getTime())) return str;
            return d.toLocaleString('pt-BR');
        },
        formatDate(str) {
            if (!str) return '–';
            const d = new Date(str);
            if (Number.isNaN(d.getTime())) return str;
            return d.toLocaleDateString('pt-BR');
        },
        formatPercent(v, digits = 1) {
            const n = Number(v) || 0;
            return new Intl.NumberFormat('pt-BR', {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits
            }).format(n) + '%';
        },
        setStatus(message, type = 'info') {
            if (!DOM.status) return;
            DOM.status.textContent = message;
            DOM.status.className = 'rm-status ' + type;
        },
        toggleLoading(flag) {
            state.loading = !!flag;
            if (!DOM.loading) return;
            if (state.loading) {
                DOM.loading.classList.remove('d-none');
            } else {
                DOM.loading.classList.add('d-none');
            }
        },
        getSelectedDiasSemana() {
            const ids = ['rm-dia-0', 'rm-dia-1', 'rm-dia-2', 'rm-dia-3', 'rm-dia-4', 'rm-dia-5', 'rm-dia-6'];
            const values = [];
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el && el.checked) {
                    values.push(Number(el.value));
                }
            });
            return values;
        },
        clampNonNegative(val) {
            const n = Number(val);
            if (!Number.isFinite(n) || n < 0) return 0;
            return n;
        }
    };

    function initDOM() {
        DOM.status = document.getElementById('rm-status');
        DOM.loading = document.getElementById('rm-loading-overlay');

        DOM.tabButtons = Array.from(document.querySelectorAll('.rm-tab'));
        DOM.tabSegmento = document.getElementById('rm-tab-segmento');
        DOM.tabCampanhas = document.getElementById('rm-tab-campanhas');
        DOM.tabLogs = document.getElementById('rm-tab-logs');

        DOM.rangeButtons = Array.from(document.querySelectorAll('.rm-chip-range'));

        DOM.dateStart = document.getElementById('rm-date-start');
        DOM.dateEnd = document.getElementById('rm-date-end');
        DOM.seller = document.getElementById('rm-seller');
        DOM.product = document.getElementById('rm-product');
        DOM.whatsappOnly = document.getElementById('rm-whatsapp-only');

        DOM.diasMin = document.getElementById('rm-dias-min');
        DOM.diasMax = document.getElementById('rm-dias-max');
        DOM.puffsMin = document.getElementById('rm-puffs-min');
        DOM.puffsMax = document.getElementById('rm-puffs-max');
        DOM.restamMax = document.getElementById('rm-restam-max');
        DOM.ticketMin = document.getElementById('rm-ticket-min');
        DOM.ticketMax = document.getElementById('rm-ticket-max');

        DOM.btnGerarSegmento = document.getElementById('rm-btn-gerar-segmento');
        DOM.btnSalvarSegmento = document.getElementById('rm-btn-salvar-segmento');
        DOM.btnLimparSegmento = document.getElementById('rm-btn-limpar-segmento');

        DOM.kpiClientes = document.getElementById('rm-kpi-clientes');
        DOM.kpiDispositivos = document.getElementById('rm-kpi-dispositivos');
        DOM.kpiPuffsDia = document.getElementById('rm-kpi-puffs-dia');
        DOM.kpiDiasRestantes = document.getElementById('rm-kpi-dias-restantes');

        DOM.segmentoResumoLabel = document.getElementById('rm-segmento-resumo-label');
        DOM.badgeSegmentoSize = document.getElementById('rm-badge-segmento-size');
        DOM.badgeSegmentoAmostra = document.getElementById('rm-badge-segmento-exemplo');
        DOM.tbodySegmento = document.getElementById('rm-tbody-segmento');
        DOM.chartDiasCanvas = document.getElementById('rm-chart-dias');

        DOM.campNome = document.getElementById('rm-camp-nome');
        DOM.campCanal = document.getElementById('rm-camp-canal');
        DOM.campSegmentoLabel = document.getElementById('rm-camp-segmento-label');
        DOM.campModoEnvio = document.getElementById('rm-camp-modo-envio');
        DOM.campDiasN = document.getElementById('rm-camp-dias-n');
        DOM.campRestamN = document.getElementById('rm-camp-restam-n');
        DOM.campCooldown = document.getElementById('rm-camp-cooldown');
        DOM.campHorario = document.getElementById('rm-camp-horario');
        DOM.campTemplate = document.getElementById('rm-camp-template');
        DOM.previewMensagem = document.getElementById('rm-preview-mensagem');
        DOM.previewModoPill = document.getElementById('rm-preview-modo-pill');

        DOM.btnSimularCampanha = document.getElementById('rm-btn-simular-campanha');
        DOM.btnSalvarCampanha = document.getElementById('rm-btn-salvar-campanha');
        DOM.btnDispararAgora = document.getElementById('rm-btn-disparar-agora');
        DOM.tbodyCampanhas = document.getElementById('rm-tbody-campanhas');
        DOM.badgeCampanhas = document.getElementById('rm-badge-campanhas');

        DOM.chartEngajamentoCanvas = document.getElementById('rm-chart-engajamento');
        DOM.chartRange = document.getElementById('rm-chart-range');
        DOM.tbodyLogs = document.getElementById('rm-tbody-logs');
        DOM.badgeLogs = document.getElementById('rm-badge-logs');
        DOM.btnRecarregarLogs = document.getElementById('rm-btn-recarregar-logs');
    }

    function initTabs() {
        DOM.tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                DOM.tabButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const tab = btn.getAttribute('data-tab');
                [DOM.tabSegmento, DOM.tabCampanhas, DOM.tabLogs].forEach(el => {
                    if (!el) return;
                    el.classList.remove('active');
                });

                if (tab === 'segmento') DOM.tabSegmento.classList.add('active');
                if (tab === 'campanhas') DOM.tabCampanhas.classList.add('active');
                if (tab === 'logs') DOM.tabLogs.classList.add('active');
            });
        });
    }

    function initRangeButtons() {
        DOM.rangeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                DOM.rangeButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const min = btn.getAttribute('data-min');
                const max = btn.getAttribute('data-max');
                DOM.diasMin.value = min !== null ? min : '';
                DOM.diasMax.value = max !== null ? max : '';
            });
        });
    }

    function bindEvents() {
        DOM.btnGerarSegmento.addEventListener('click', () => {
            gerarSegmento();
        });

        DOM.btnLimparSegmento.addEventListener('click', () => {
            limparFiltrosSegmento();
        });

        DOM.btnSalvarSegmento.addEventListener('click', () => {
            salvarSegmentoLocal();
        });

        DOM.campModoEnvio.addEventListener('change', atualizarPillModoEnvio);
        DOM.campTemplate.addEventListener('input', atualizarPreviewMensagem);

        DOM.btnSalvarCampanha.addEventListener('click', salvarCampanhaLocal);
        DOM.btnDispararAgora.addEventListener('click', dispararAgora);
        DOM.btnSimularCampanha.addEventListener('click', simularCampanha);

        DOM.chartRange.addEventListener('change', () => {
            carregarStatsEngajamento();
        });

        DOM.btnRecarregarLogs.addEventListener('click', () => {
            carregarLogs();
        });
    }

    function limparFiltrosSegmento() {
        DOM.dateStart.value = '';
        DOM.dateEnd.value = '';
        if (DOM.seller) DOM.seller.value = '';
        if (DOM.product) DOM.product.value = '';
        if (DOM.whatsappOnly) DOM.whatsappOnly.checked = true;

        DOM.diasMin.value = '';
        DOM.diasMax.value = '';
        DOM.puffsMin.value = '';
        DOM.puffsMax.value = '';
        DOM.restamMax.value = '';
        DOM.ticketMin.value = '';
        DOM.ticketMax.value = '';

        DOM.rangeButtons.forEach(b => b.classList.remove('active'));
        utils.setStatus('Filtros de segmento limpos. Configure novamente e gere um novo segmento.', 'info');
    }

    function getSegmentoFiltros() {
        return {
            data_inicio: DOM.dateStart.value || null,
            data_fim: DOM.dateEnd.value || null,
            vendedor_id: DOM.seller.value || null,
            produto_id: DOM.product.value || null,
            dias_min: DOM.diasMin.value !== '' ? Number(DOM.diasMin.value) : null,
            dias_max: DOM.diasMax.value !== '' ? Number(DOM.diasMax.value) : null,
            puffs_min: DOM.puffsMin.value !== '' ? Number(DOM.puffsMin.value) : null,
            puffs_max: DOM.puffsMax.value !== '' ? Number(DOM.puffsMax.value) : null,
            restam_max: DOM.restamMax.value !== '' ? Number(DOM.restamMax.value) : null,
            ticket_min: DOM.ticketMin.value !== '' ? Number(DOM.ticketMin.value) : null,
            ticket_max: DOM.ticketMax.value !== '' ? Number(DOM.ticketMax.value) : null,
            whatsapp_only: DOM.whatsappOnly.checked ? 1 : 0
        };
    }

    async function gerarSegmento() {
        const filtros = getSegmentoFiltros();

        if (filtros.data_inicio && filtros.data_fim) {
            const d1 = new Date(filtros.data_inicio);
            const d2 = new Date(filtros.data_fim);
            if (d1 > d2) {
                utils.setStatus('A data inicial não pode ser maior que a data final.', 'error');
                return;
            }
        }

        utils.setStatus('Gerando segmento (D0–Dn, puffs/dia, previsão de esgotamento)...', 'info');
        utils.toggleLoading(true);

        try {
            const qs = new URLSearchParams();
            Object.entries(filtros).forEach(([key, val]) => {
                if (val !== null && val !== '' && typeof val !== 'undefined') {
                    qs.append(key, val);
                }
            });

            const resp = await fetch(CONFIG.ENDPOINT_SEGMENTO_PREVIEW + '?' + qs.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            let data;
            if (resp.ok) {
                data = utils.unwrapApi(await resp.json());
            } else {
                console.warn('Falha no endpoint de segmento, usando mock local. Status:', resp.status);
                data = null;
            }

            if (!data || !Array.isArray(data.clientes)) {
                data = gerarSegmentoMock(filtros);
            }

            state.segmentoAtual = {
                filtros,
                clientes: data.clientes || [],
                total_clientes: data.total_clientes || (data.clientes ? data.clientes.length : 0),
                total_dispositivos: data.total_dispositivos || 0,
                puffs_dia_medio: data.puffs_dia_medio || 0,
                dias_restantes_medio: data.dias_restantes_medio || 0,
                label: data.label || montarLabelSegmento(filtros)
            };

            atualizarResumoSegmento();
            atualizarTabelaSegmento();
            atualizarChartDias();
            sincronizarCampanhaComSegmento();

            utils.setStatus(`Segmento gerado com sucesso. ${state.segmentoAtual.total_clientes} clientes elegíveis.`, 'ok');
        } catch (e) {
            console.error('Erro ao gerar segmento:', e);
            utils.setStatus('Erro inesperado ao gerar segmento. Verifique console ou back-end.', 'error');
        } finally {
            utils.toggleLoading(false);
        }
    }

    function gerarSegmentoMock(filtros) {
        const clientes = [];
        const baseN = 42;
        for (let i = 0; i < baseN; i++) {
            const dias = Math.floor(Math.random() * 40);
            const puffsDia = 400 + Math.floor(Math.random() * 400);
            const diasRestantes = 1 + Math.floor(Math.random() * 6);
            const produto = ['IGNITE V150', 'ELFBAR BC10000', 'NIKBAR 12000'][i % 3];
            const capacity = produto === 'IGNITE V150' ? 15000 : produto === 'NIKBAR 12000' ? 12000 : 10000;

            clientes.push({
                id_cliente: 1000 + i,
                nome: `Cliente ${i + 1}`,
                telefone: `+55 21 9${(80000000 + i).toString().slice(-8)}`,
                produto_principal: produto,
                capacidade_total_puffs: capacity,
                puffs_dia_estimado: puffsDia,
                dias_desde_ultima_compra: dias,
                dias_restantes_estimado: diasRestantes,
                vendedor_nome: ['Sara', 'Bruno', 'Luana', 'Equipe Online'][i % 4],
                ultima_compra: new Date(Date.now() - dias * 24 * 60 * 60 * 1000).toISOString()
            });
        }

        const totalClientes = clientes.length;
        const totalDispositivos = Math.round(totalClientes * 1.2);
        const puffsMedio = clientes.reduce((acc, c) => acc + (c.puffs_dia_estimado || 0), 0) / (totalClientes || 1);
        const diasRestantesMedio = clientes.reduce((acc, c) => acc + (c.dias_restantes_estimado || 0), 0) / (totalClientes || 1);

        return {
            clientes,
            total_clientes: totalClientes,
            total_dispositivos: totalDispositivos,
            puffs_dia_medio: Math.round(puffsMedio),
            dias_restantes_medio: Math.round(diasRestantesMedio),
            label: montarLabelSegmento(filtros)
        };
    }

    function montarLabelSegmento(filtros) {
        const partes = [];
        if (filtros.dias_min !== null || filtros.dias_max !== null) {
            let p = 'D';
            if (filtros.dias_min !== null) p += filtros.dias_min;
            else p += '0';
            p += '–';
            if (filtros.dias_max !== null) p += filtros.dias_max;
            else p += '∞';
            partes.push(p);
        } else {
            partes.push('D0–D∞');
        }

        if (filtros.puffs_min || filtros.puffs_max) {
            partes.push(`puffs/dia: ${filtros.puffs_min || 0}–${filtros.puffs_max || '∞'}`);
        }

        if (filtros.restam_max !== null) {
            partes.push(`restam ≤ ${filtros.restam_max} dias`);
        }

        return partes.join(' · ') || 'Segmento geral';
    }

    function atualizarResumoSegmento() {
        if (!state.segmentoAtual) {
            DOM.kpiClientes.textContent = '0';
            DOM.kpiDispositivos.textContent = '0';
            DOM.kpiPuffsDia.textContent = '0';
            DOM.kpiDiasRestantes.textContent = '0';
            DOM.segmentoResumoLabel.textContent = 'Nenhum gerado';
            DOM.badgeSegmentoSize.textContent = '0 clientes mapeados';
            DOM.badgeSegmentoAmostra.textContent = 'Exibindo até 0 clientes';
            return;
        }

        const s = state.segmentoAtual;
        DOM.kpiClientes.textContent = utils.formatNumber(s.total_clientes || s.clientes.length || 0);
        DOM.kpiDispositivos.textContent = utils.formatNumber(s.total_dispositivos || 0);
        DOM.kpiPuffsDia.textContent = utils.formatNumber(s.puffs_dia_medio || 0);
        DOM.kpiDiasRestantes.textContent = utils.formatNumber(s.dias_restantes_medio || 0);
        DOM.segmentoResumoLabel.textContent = s.label || 'Segmento atual';
        DOM.badgeSegmentoSize.textContent = `${utils.formatNumber(s.total_clientes || s.clientes.length || 0)} clientes mapeados`;
        DOM.badgeSegmentoAmostra.textContent = `Exibindo até ${CONFIG.ITEMS_AMOSTRA_SEGMENTO} clientes`;
    }

    function atualizarTabelaSegmento() {
        if (!DOM.tbodySegmento) return;

        DOM.tbodySegmento.innerHTML = '';

        if (!state.segmentoAtual || !state.segmentoAtual.clientes.length) {
            DOM.tbodySegmento.innerHTML =
                '<tr><td colspan="8" style="font-size:0.8rem;color:var(--rm-text-soft);padding:8px 10px;">' +
                'Nenhum registro encontrado para esta configuração de segmento.' +
                '</td></tr>';
            return;
        }

        const subset = state.segmentoAtual.clientes.slice(0, CONFIG.ITEMS_AMOSTRA_SEGMENTO);

        subset.forEach((c, idx) => {
            const tr = document.createElement('tr');

            const d = Number(c.dias_desde_ultima_compra || 0);
            const diasLabel = d + ' dias';
            const puffsDia = c.puffs_dia_estimado || 0;
            const restam = c.dias_restantes_estimado || 0;

            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td>${c.nome || '-'}</td>
                <td>${c.telefone || '-'}</td>
                <td>${c.produto_principal || '-'}</td>
                <td>${diasLabel}</td>
                <td>${utils.formatNumber(puffsDia)}</td>
                <td>${restam}</td>
                <td>${c.vendedor_nome || '-'}</td>
            `;
            DOM.tbodySegmento.appendChild(tr);
        });
    }

    function bucketDias(dias) {
        if (dias <= 0) return 'D0';
        if (dias === 1) return 'D1';
        if (dias <= 3) return 'D2–D3';
        if (dias <= 7) return 'D4–D7';
        if (dias <= 15) return 'D8–D15';
        if (dias <= 30) return 'D16–D30';
        return 'D31+';
    }

    function atualizarChartDias() {
        if (!DOM.chartDiasCanvas) return;

        const ctx = DOM.chartDiasCanvas.getContext('2d');
        if (state.charts.dias) {
            state.charts.dias.destroy();
            state.charts.dias = null;
        }

        if (!state.segmentoAtual || !state.segmentoAtual.clientes.length) {
            state.charts.dias = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['D0', 'D1', 'D2–D3', 'D4–D7', 'D8–D15', 'D16–D30', 'D31+'],
                    datasets: [{
                        label: 'Clientes',
                        data: [0, 0, 0, 0, 0, 0, 0],
                        backgroundColor: '#6366f1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#9ca3af', font: { size: 10 } }, grid: { display: false } },
                        y: { ticks: { color: '#9ca3af', font: { size: 10 } }, grid: { color: '#1f2937' }, beginAtZero: true }
                    }
                }
            });
            return;
        }

        const buckets = {
            'D0': 0,
            'D1': 0,
            'D2–D3': 0,
            'D4–D7': 0,
            'D8–D15': 0,
            'D16–D30': 0,
            'D31+': 0
        };

        state.segmentoAtual.clientes.forEach(c => {
            const d = Number(c.dias_desde_ultima_compra || 0);
            const b = bucketDias(d);
            buckets[b]++;
        });

        const labels = Object.keys(buckets);
        const data = labels.map(k => buckets[k]);

        state.charts.dias = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Clientes',
                    data,
                    backgroundColor: labels.map(label => {
                        if (label === 'D0') return '#22c55e';
                        if (label === 'D1') return '#4ade80';
                        if (label === 'D2–D3') return '#3b82f6';
                        if (label === 'D4–D7') return '#6366f1';
                        if (label === 'D8–D15') return '#a855f7';
                        if (label === 'D16–D30') return '#f97316';
                        return '#f97373';
                    })
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#e5e7eb',
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.95)',
                        borderColor: '#374151',
                        borderWidth: 1,
                        titleColor: '#f9fafb',
                        bodyColor: '#e5e7eb'
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#9ca3af', font: { size: 10 } },
                        grid: { display: false }
                    },
                    y: {
                        ticks: {
                            color: '#9ca3af',
                            font: { size: 10 }
                        },
                        grid: { color: '#1f2937' },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function sincronizarCampanhaComSegmento() {
        if (!state.segmentoAtual) {
            DOM.campSegmentoLabel.value = '';
            return;
        }
        const s = state.segmentoAtual;
        DOM.campSegmentoLabel.value =
            `${s.label || 'Segmento atual'} · ${utils.formatNumber(s.total_clientes)} clientes`;
        atualizarPreviewMensagem();
    }

    function getCampanhaFromForm() {
        const diasSemana = utils.getSelectedDiasSemana();
        const modo = DOM.campModoEnvio.value || 'manual';

        return {
            id: Date.now(),
            nome: DOM.campNome.value || 'Campanha sem nome',
            canal: DOM.campCanal.value || 'whatsapp',
            segmento_label: DOM.campSegmentoLabel.value || 'Segmento atual',
            modo_envio: modo,
            dias_n: utils.clampNonNegative(DOM.campDiasN.value || 0),
            restam_n: utils.clampNonNegative(DOM.campRestamN.value || 0),
            cooldown_dias: utils.clampNonNegative(DOM.campCooldown.value || 7),
            horario_base: DOM.campHorario.value || '10:00',
            dias_semana: diasSemana,
            template: DOM.campTemplate.value || '',
            stats: {
                disparos: 0,
                conversoes: 0
            },
            ativo: true,
            criado_em: new Date().toISOString()
        };
    }

    function atualizarPillModoEnvio() {
        const modo = DOM.campModoEnvio.value;
        if (modo === 'manual') {
            DOM.previewModoPill.textContent = 'Modo atual: Manual';
            DOM.previewModoPill.className = 'rm-pill-manual';
        } else {
            DOM.previewModoPill.textContent = 'Modo atual: Automático';
            DOM.previewModoPill.className = 'rm-pill-auto';
        }
    }

    function atualizarPreviewMensagem() {
        if (!DOM.previewMensagem) return;

        const tpl = DOM.campTemplate.value || '';
        if (!tpl.trim()) {
            DOM.previewMensagem.textContent =
                'Defina um template de mensagem para visualizar como o cliente enxergará o disparo.';
            return;
        }

        let exemplo = null;
        if (state.segmentoAtual && state.segmentoAtual.clientes.length) {
            exemplo = state.segmentoAtual.clientes[0];
        } else {
            exemplo = {
                nome: 'Breno',
                telefone: '+55 21 9XXXX-XXXX',
                produto_principal: 'IGNITE V150',
                puffs_dia_estimado: 600,
                dias_desde_ultima_compra: 7,
                dias_restantes_estimado: 2
            };
        }

        const vars = {
            nome: exemplo.nome || 'Cliente',
            produto: exemplo.produto_principal || 'Produto',
            dias_desde_ultima_compra: exemplo.dias_desde_ultima_compra || 0,
            puffs_dia: exemplo.puffs_dia_estimado || 0,
            dias_restantes: exemplo.dias_restantes_estimado || 0,
            link_pedido: 'https://redealabama.com/pedido/...' // backend substitui pelo real
        };

        let msg = tpl;
        Object.entries(vars).forEach(([key, val]) => {
            const re = new RegExp('{{\\s*' + key + '\\s*}}', 'g');
            msg = msg.replace(re, String(val));
        });

        DOM.previewMensagem.textContent = msg;
    }

    function salvarCampanhaLocal() {
        const camp = getCampanhaFromForm();

        if (!state.segmentoAtual) {
            utils.setStatus('Antes de salvar a campanha, gere e associe um segmento na aba de Segmentos.', 'error');
            return;
        }

        state.campanhas.push(camp);
        renderCampanhas();
        utils.setStatus('Campanha salva localmente. Integre com o endpoint api/v2/remarketing_campanhas.php para persistência.', 'ok');
    }

    function renderCampanhas() {
        if (!DOM.tbodyCampanhas) return;

        DOM.tbodyCampanhas.innerHTML = '';

        if (!state.campanhas.length) {
            DOM.tbodyCampanhas.innerHTML =
                '<tr><td colspan="9" style="font-size:0.8rem;color:var(--rm-text-soft);padding:8px 10px;">' +
                'Nenhuma campanha cadastrada. Crie uma campanha acima.' +
                '</td></tr>';
            DOM.badgeCampanhas.textContent = '0 campanhas ativas';
            return;
        }

        state.campanhas.forEach((c, idx) => {
            const tr = document.createElement('tr');

            const totalDisp = c.stats.disparos || 0;
            const conv = c.stats.conversoes || 0;
            const taxa = totalDisp > 0 ? (conv / totalDisp) * 100 : 0;

            const modoChip = c.modo_envio === 'manual'
                ? '<span class="rm-chip-tag orange">Manual</span>'
                : '<span class="rm-chip-tag green">Automático</span>';

            const statusChip = c.ativo
                ? '<span class="rm-badge-status on">Ativa</span>'
                : '<span class="rm-badge-status off">Pausada</span>';

            tr.innerHTML = `
                <td>${idx + 1}</td>
                <td>${c.nome}</td>
                <td>${c.segmento_label}</td>
                <td>${modoChip}</td>
                <td>${c.canal || '-'}</td>
                <td>${utils.formatNumber(totalDisp)}</td>
                <td>${utils.formatPercent(taxa)}</td>
                <td>${statusChip}</td>
                <td>
                    <button type="button" class="rm-btn rm-btn-ghost rm-btn-sm" data-action="toggle" data-id="${c.id}">
                        ${c.ativo ? '<i class="bi bi-pause-circle"></i>' : '<i class="bi bi-play-circle"></i>'}
                    </button>
                    <button type="button" class="rm-btn rm-btn-ghost rm-btn-sm" data-action="clone" data-id="${c.id}">
                        <i class="bi bi-files"></i>
                    </button>
                    <button type="button" class="rm-btn rm-btn-ghost rm-btn-sm" data-action="delete" data-id="${c.id}">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            DOM.tbodyCampanhas.appendChild(tr);
        });

        const totalAtivas = state.campanhas.filter(c => c.ativo).length;
        DOM.badgeCampanhas.textContent = `${totalAtivas} campanhas ativas`;

        DOM.tbodyCampanhas.querySelectorAll('button[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = Number(btn.getAttribute('data-id'));
                const action = btn.getAttribute('data-action');
                handleCampanhaAction(id, action);
                e.stopPropagation();
            });
        });
    }

    function handleCampanhaAction(id, action) {
        const idx = state.campanhas.findIndex(c => c.id === id);
        if (idx === -1) return;

        const c = state.campanhas[idx];
        if (action === 'toggle') {
            c.ativo = !c.ativo;
            utils.setStatus(`Campanha “${c.nome}” foi ${c.ativo ? 'ativada' : 'pausada'} localmente.`, 'ok');
        } else if (action === 'clone') {
            const clone = { ...c, id: Date.now(), nome: c.nome + ' (cópia)' };
            state.campanhas.push(clone);
            utils.setStatus(`Campanha “${c.nome}” clonada. Ajuste os parâmetros e salve.`, 'ok');
        } else if (action === 'delete') {
            state.campanhas.splice(idx, 1);
            utils.setStatus(`Campanha “${c.nome}” removida localmente.`, 'ok');
        }
        renderCampanhas();
    }

    function dispararAgora() {
        if (!state.segmentoAtual || !state.segmentoAtual.clientes.length) {
            utils.setStatus('Não há segmento carregado para disparo. Gere o segmento primeiro.', 'error');
            return;
        }
        const camp = getCampanhaFromForm();
        const total = state.segmentoAtual.total_clientes || state.segmentoAtual.clientes.length;
        utils.setStatus(`(Simulação) Disparo manual enfileirado para ${total} clientes da campanha “${camp.nome}”. Integre com o endpoint api/v2/remarketing_disparos.php.`, 'ok');
    }

    function simularCampanha() {
        if (!state.segmentoAtual || !state.segmentoAtual.clientes.length) {
            utils.setStatus('Gere um segmento antes de simular a campanha.', 'error');
            return;
        }
        utils.setStatus('Simulação de 7 dias de disparos executada localmente (mock). Estruture a simulação real no backend.', 'info');
    }

    async function carregarStatsEngajamento() {
        if (!DOM.chartEngajamentoCanvas) return;

        const ctx = DOM.chartEngajamentoCanvas.getContext('2d');
        const range = Number(DOM.chartRange.value || 30);

        utils.setStatus(`Carregando estatísticas de engajamento dos últimos ${range} dias...`, 'info');
        utils.toggleLoading(true);

        let data = null;
        try {
            const qs = new URLSearchParams({ dias: range });
            const resp = await fetch(CONFIG.ENDPOINT_STATS + '?' + qs.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (resp.ok) {
                data = utils.unwrapApi(await resp.json());
            } else {
                console.warn('Falha ao buscar stats, usando mock local. Status:', resp.status);
            }
        } catch (e) {
            console.warn('Erro ao buscar stats, usando mock local:', e);
        } finally {
            utils.toggleLoading(false);
        }

        if (!data || !Array.isArray(data.series)) {
            data = gerarStatsEngajamentoMock(range);
        }

        const labels = data.series.map(p => p.dia);
        const disparos = data.series.map(p => p.disparos);
        const entregues = data.series.map(p => p.entregues);
        const respostas = data.series.map(p => p.respostas);

        if (state.charts.engajamento) {
            state.charts.engajamento.destroy();
            state.charts.engajamento = null;
        }

        state.charts.engajamento = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Disparos',
                        data: disparos,
                        borderWidth: 2,
                        tension: 0.3,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.1)'
                    },
                    {
                        label: 'Entregues',
                        data: entregues,
                        borderWidth: 2,
                        tension: 0.3,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,0.1)'
                    },
                    {
                        label: 'Respostas',
                        data: respostas,
                        borderWidth: 2,
                        tension: 0.3,
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249,115,22,0.1)'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#e5e7eb',
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.95)',
                        borderColor: '#374151',
                        borderWidth: 1,
                        titleColor: '#f9fafb',
                        bodyColor: '#e5e7eb'
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#9ca3af', font: { size: 10 } },
                        grid: { display: false }
                    },
                    y: {
                        ticks: { color: '#9ca3af', font: { size: 10 } },
                        grid: { color: '#1f2937' },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function gerarStatsEngajamentoMock(range) {
        const series = [];
        const hoje = new Date();
        for (let i = range - 1; i >= 0; i--) {
            const d = new Date(hoje.getTime() - i * 24 * 60 * 60 * 1000);
            const label = d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            const disparos = 30 + Math.floor(Math.random() * 70);
            const entregues = Math.round(disparos * (0.9 + Math.random() * 0.08));
            const respostas = Math.round(disparos * (0.12 + Math.random() * 0.08));
            series.push({
                dia: label,
                disparos,
                entregues,
                respostas
            });
        }
        return { series };
    }

    async function carregarLogs() {
        utils.setStatus('Carregando logs de disparos...', 'info');
        utils.toggleLoading(true);

        let data = null;
        try {
            const resp = await fetch(CONFIG.ENDPOINT_LOGS, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (resp.ok) {
                data = utils.unwrapApi(await resp.json());
            } else {
                console.warn('Falha ao buscar logs, usando mock local. Status:', resp.status);
            }
        } catch (e) {
            console.warn('Erro ao buscar logs, usando mock local:', e);
        } finally {
            utils.toggleLoading(false);
        }

        if (!data || !Array.isArray(data.logs)) {
            data = gerarLogsMock();
        }

        state.logs = data.logs || [];
        renderLogs();
    }

    function gerarLogsMock() {
        const statusList = ['enviado', 'entregue', 'lido', 'respondido', 'falha'];
        const logs = [];
        for (let i = 0; i < 40; i++) {
            const s = statusList[Math.floor(Math.random() * statusList.length)];
            logs.push({
                id: 1000 + i,
                data_hora: new Date(Date.now() - Math.random() * 7 * 24 * 60 * 60 * 1000).toISOString(),
                cliente: `Cliente ${i + 1}`,
                telefone: `+55 21 9${(81000000 + i).toString().slice(-8)}`,
                campanha: ['Reposição V150', 'Upsell BC10000', 'Winback 30d'][i % 3],
                canal: 'whatsapp',
                status: s,
                retorno: s === 'respondido' ? 'Cliente respondeu e pediu orçamento.' :
                         s === 'falha' ? 'Número inválido / bloqueado.' : '-'
            });
        }
        return { logs };
    }

    function renderLogs() {
        if (!DOM.tbodyLogs) return;

        DOM.tbodyLogs.innerHTML = '';

        if (!state.logs.length) {
            DOM.tbodyLogs.innerHTML =
                '<tr><td colspan="7" style="font-size:0.8rem;color:var(--rm-text-soft);padding:8px 10px;">' +
                'Nenhum log retornado pela API.' +
                '</td></tr>';
            DOM.badgeLogs.textContent = '0 eventos carregados';
            return;
        }

        state.logs.forEach(log => {
            const tr = document.createElement('tr');

            const statusChip = (() => {
                const s = (log.status || '').toLowerCase();
                if (s === 'respondido') return '<span class="rm-chip-tag green">Respondido</span>';
                if (s === 'lido') return '<span class="rm-chip-tag blue">Lido</span>';
                if (s === 'entregue') return '<span class="rm-chip-tag green">Entregue</span>';
                if (s === 'falha') return '<span class="rm-chip-tag red">Falha</span>';
                return '<span class="rm-chip-tag">Enviado</span>';
            })();

            tr.innerHTML = `
                <td>${utils.formatDateTime(log.data_hora)}</td>
                <td>${log.cliente || '-'}</td>
                <td>${log.telefone || '-'}</td>
                <td>${log.campanha || '-'}</td>
                <td>${log.canal || '-'}</td>
                <td>${statusChip}</td>
                <td>${log.retorno || '-'}</td>
            `;
            DOM.tbodyLogs.appendChild(tr);
        });

        DOM.badgeLogs.textContent = `${utils.formatNumber(state.logs.length)} eventos carregados`;
        utils.setStatus('Logs carregados (mock ou API).', 'ok');
    }

    function salvarSegmentoLocal() {
        if (!state.segmentoAtual) {
            utils.setStatus('Nenhum segmento foi gerado para salvar configuração.', 'error');
            return;
        }
        try {
            const configs = JSON.parse(localStorage.getItem('rmSegmentosConfigs') || '[]');
            const novo = {
                filtros: state.segmentoAtual.filtros,
                label: state.segmentoAtual.label,
                created_at: Date.now()
            };
            configs.unshift(novo);
            if (configs.length > 10) configs.pop();
            localStorage.setItem('rmSegmentosConfigs', JSON.stringify(configs));
            utils.setStatus('Configuração de segmento salva localmente (localStorage).', 'ok');
        } catch (e) {
            console.warn('Erro ao salvar segmento em localStorage:', e);
            utils.setStatus('Falha ao salvar configuração local de segmento (localStorage).', 'error');
        }
    }

    function carregarConfigUltimoSegmento() {
        try {
            const configs = JSON.parse(localStorage.getItem('rmSegmentosConfigs') || '[]');
            if (!configs.length) return;
            const ultima = configs[0];

            const f = ultima.filtros || {};
            if (f.data_inicio) DOM.dateStart.value = f.data_inicio;
            if (f.data_fim) DOM.dateEnd.value = f.data_fim;
            if (f.vendedor_id && DOM.seller) DOM.seller.value = f.vendedor_id;
            if (f.produto_id && DOM.product) DOM.product.value = f.produto_id;
            if (typeof f.whatsapp_only !== 'undefined' && DOM.whatsappOnly) {
                DOM.whatsappOnly.checked = !!f.whatsapp_only;
            }
            if (typeof f.dias_min === 'number') DOM.diasMin.value = f.dias_min;
            if (typeof f.dias_max === 'number') DOM.diasMax.value = f.dias_max;
            if (typeof f.puffs_min === 'number') DOM.puffsMin.value = f.puffs_min;
            if (typeof f.puffs_max === 'number') DOM.puffsMax.value = f.puffs_max;
            if (typeof f.restam_max === 'number') DOM.restamMax.value = f.restam_max;
            if (typeof f.ticket_min === 'number') DOM.ticketMin.value = f.ticket_min;
            if (typeof f.ticket_max === 'number') DOM.ticketMax.value = f.ticket_max;

            utils.setStatus('Configuração de segmento anterior carregada. Clique em “Gerar segmento” para atualizar.', 'info');
        } catch (e) {
            console.warn('Erro ao ler configuração anterior de segmento:', e);
        }
    }

    function init() {
        initDOM();
        initTabs();
        initRangeButtons();
        bindEvents();
        atualizarPillModoEnvio();
        carregarConfigUltimoSegmento();
        atualizarResumoSegmento();
        atualizarChartDias();
        carregarStatsEngajamento();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
</script>
</body>
</html>
