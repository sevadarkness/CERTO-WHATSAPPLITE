<?php
/**
 * ia_user_audit.php
 *
 * Auditoria por usuário das chamadas de IA, baseada na coluna meta_json de llm_logs.
 * Filtra por usuario_id presente em meta_json (campo 'usuario_id' ou 'by_user').
 */

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);
require_once __DIR__ . '/db_config.php';

// Recupera usuário atual (assume que já existe algo como $_SESSION['usuario_id'])
$usuarioId = $_SESSION['usuario_id'] ?? null;
if (!$usuarioId) {
    http_response_code(403);
    echo "Sessão inválida.";
    exit;
}

$pdo   = $pdo ?? null;
if (!$pdo) {
    // db_config.php deve ter criado $pdo
    http_response_code(500);
    echo "PDO não inicializado.";
    exit;
}

$days = isset($_GET['days']) ? (int) $_GET['days'] : 7;
if ($days < 1)  $days = 1;
if ($days > 90) $days = 90;

// Consulta usando JSON_EXTRACT, assumindo MySQL 5.7+ com JSON
$sql = "SELECT 
            created_at,
            context,
            provider,
            model,
            tokens_prompt,
            tokens_completion,
            tokens_total,
            http_status,
            latency_ms,
            error
        FROM llm_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
          AND (
                JSON_EXTRACT(meta_json, '$.usuario_id') = :uid
             OR JSON_EXTRACT(meta_json, '$.by_user') = :uid
          )
        ORDER BY created_at DESC
        LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':days' => $days,
    ':uid'  => (int)$usuarioId,
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Auditoria IA por usuário - Rede Alabama V18 Ultra</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f5f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1100px;
            margin: 32px auto;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(15,23,42,0.08);
            padding: 24px 28px 28px 28px;
        }
        h1 {
            margin-top: 0;
            font-size: 1.6rem;
            color: #111827;
        }
        .subtitle {
            margin-top: 4px;
            margin-bottom: 16px;
            color: #6b7280;
            font-size: 0.92rem;
        }
        form {
            margin-bottom: 16px;
        }
        label {
            font-size: 0.9rem;
            margin-right: 4px;
        }
        input[type="number"] {
            width: 80px;
            padding: 4px 6px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 0.9rem;
            margin-right: 8px;
        }
        button {
            background: #2563eb;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover {
            background: #1d4ed8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 0.88rem;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        tr:nth-child(even) td {
            background: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #e5e7eb;
            font-size: 0.75rem;
            color: #4b5563;
        }
        .error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 8px 10px;
            border-radius: 6px;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Auditoria IA (meu usuário)</h1>
        <div class="subtitle">
            V18 Ultra · Chamadas de IA associadas ao seu usuário nos últimos dias.
        </div>

        <form method="get">
            <label for="days">Período (dias, 1–90):</label>
            <input type="number" min="1" max="90" id="days" name="days" value="<?php echo htmlspecialchars((string)$days); ?>">
            <button type="submit">Atualizar</button>
            <span class="badge">Fonte: llm_logs.meta_json</span>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Contexto</th>
                    <th>Modelo</th>
                    <th>Tokens</th>
                    <th>HTTP</th>
                    <th>Latência (ms)</th>
                    <th>Erro</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7">Nenhum registro encontrado para o período.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($r['context']); ?></td>
                            <td><?php echo htmlspecialchars($r['model']); ?></td>
                            <td><?php echo (int)($r['tokens_total'] ?? 0); ?></td>
                            <td><?php echo (int)($r['http_status'] ?? 0); ?></td>
                            <td><?php echo (int)($r['latency_ms'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars($r['error'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
