<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador']);
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/jsonl; charset=utf-8');
header('Content-Disposition: attachment; filename="alabama_llm_dataset.jsonl"');

// Exporta apenas samples aprovados
$sql = "SELECT mensagem_usuario, COALESCE(resposta_ajustada, resposta_bot) AS resposta, tags
        FROM llm_training_samples
        WHERE aprovado = 1
        ORDER BY id ASC";
$stmt = $pdo->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $user = (string)$row['mensagem_usuario'];
    $assistant = (string)($row['resposta'] ?? '');
    $tags = $row['tags'] ?? null;

    $data = [
        'messages' => [
            ['role' => 'user', 'content' => $user],
        ],
    ];

    if ($assistant !== '') {
        $data['messages'][] = ['role' => 'assistant', 'content' => $assistant];
    }

    if ($tags) {
        $data['tags'] = $tags;
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
}
exit;
