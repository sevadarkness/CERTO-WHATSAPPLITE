<?php
declare(strict_types=1);
/**
 * Template de PDF para Audit Log.
 *
 * Variáveis esperadas:
 *  - array $rows       Lista de eventos de auditoria (cada um é um array decodificado do JSON)
 *  - string $generatedAt Data/hora de geração
 *  - array $filters    (opcional) Filtros aplicados, ex.: ['from' => '...', 'to' => '...']
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Audit Log - Exportação PDF</title>
    <style>
        :root {
            --bg-page: #ffffff;
            --text-main: #111827;
            --text-muted: #6b7280;
            --accent: #6366f1;
            --accent-soft: #eef2ff;
            --border-soft: #e5e7eb;
            --danger: #dc2626;
            --mono: "SF Mono", "Roboto Mono", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            font-size: 12px;
        }

        .page {
            padding: 24px 32px;
        }

        h1, h2, h3, h4 {
            margin: 0;
            font-weight: 600;
        }

        .header {
            border-bottom: 2px solid var(--border-soft);
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: baseline;
            justify-content: space-between;
        }

        .brand {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .tagline {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .meta {
            text-align: right;
            font-size: 11px;
            color: var(--text-muted);
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            background-color: var(--accent-soft);
            color: var(--accent);
        }

        .filters {
            border: 1px solid var(--border-soft);
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 16px;
            font-size: 11px;
            background-color: #f9fafb;
        }

        .filters strong {
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        thead {
            background-color: #f3f4f6;
        }

        th, td {
            border: 1px solid var(--border-soft);
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            font-size: 11px;
            font-weight: 600;
        }

        td {
            font-size: 11px;
        }

        .mono {
            font-family: var(--mono);
            font-size: 10px;
            word-break: break-all;
        }

        .muted {
            color: var(--text-muted);
        }

        .no-data {
            margin-top: 24px;
            text-align: center;
            color: var(--text-muted);
            font-style: italic;
        }

        .section-title {
            margin-top: 4px;
            margin-bottom: 4px;
            font-size: 13px;
        }

        .footer {
            margin-top: 16px;
            border-top: 1px solid var(--border-soft);
            padding-top: 8px;
            font-size: 10px;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                background-color: #ffffff;
            }
            .page {
                padding: 16mm 12mm;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            <div class="brand">Rede Alabama</div>
            <div class="tagline">Relatório de Auditoria (Audit Log)</div>
        </div>
        <div class="meta">
            <div>Gerado em: <strong><?php echo htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8'); ?></strong></div>
            <?php if (!empty($filters)): ?>
                <div class="muted">
                    <?php if (!empty($filters['from'])): ?>
                        De: <strong><?php echo htmlspecialchars($filters['from'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($filters['to'])): ?>
                        &nbsp;Até: <strong><?php echo htmlspecialchars($filters['to'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="muted">Últimos eventos recentes (limite interno)</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <p class="no-data">Nenhum evento de auditoria encontrado para os critérios atuais.</p>
    <?php else: ?>
        <h2 class="section-title">
            Eventos de Auditoria
            <span class="badge"><?php echo count($rows); ?> evento(s)</span>
        </h2>
        <table>
            <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Evento</th>
                <th>Entidade</th>
                <th>Usuário</th>
                <th>Contexto</th>
                <th>Hash da Cadeia</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['ts'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($r['event'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['context']['entity_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['context']['usuario_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="mono">
                        <?php
                        $ctx = $r['context'] ?? [];
                        // Remove possíveis campos muito verbosos que não ajudam na visão PDF
                        unset($ctx['before'], $ctx['after'], $ctx['payload']);
                        echo htmlspecialchars(json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                        ?>
                    </td>
                    <td class="mono"><?php echo htmlspecialchars((string)($r['chain_hash'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        <div>Relatório gerado pelo painel adm.redealabama (módulo de auditoria).</div>
        <div>Pensado para impressão / exportação como PDF via navegador.</div>
    </div>
</div>
</body>
</html>
