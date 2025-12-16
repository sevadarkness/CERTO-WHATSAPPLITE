<?php
declare(strict_types=1);

require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/whatsapp_contacts_utils.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5000;
if ($limit <= 0) {
    $limit = 5000;
}
if ($limit > 50000) {
    $limit = 50000;
}

$filename = 'whatsapp_contatos_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
if ($out === false) {
    throw new RuntimeException('Não foi possível abrir o output para CSV.');
}

// Cabeçalho
fputcsv($out, [
    'conversa_id',
    'telefone',
    'status_conversa',
    'ultima_mensagem',
    'ultima_mensagem_em',
    'total_mensagens',
    'lead_id',
    'lead_nome',
    'lead_status',
    'lead_origem',
    'pais_ddi',
    'ddd',
]);

$sql = "
    SELECT 
        c.id,
        c.telefone_cliente,
        c.status,
        c.ultima_mensagem_em,
        c.created_at,
        (SELECT m.conteudo 
           FROM whatsapp_mensagens m 
          WHERE m.conversa_id = c.id 
          ORDER BY m.id DESC 
          LIMIT 1) AS ultima_msg,
        (SELECT COUNT(*) 
           FROM whatsapp_mensagens m 
          WHERE m.conversa_id = c.id) AS total_msgs,
        l.id           AS lead_id,
        l.nome_cliente AS lead_nome,
        l.status       AS lead_status,
        l.origem       AS lead_origem
    FROM whatsapp_conversas c
    LEFT JOIN leads l ON l.telefone_cliente = c.telefone_cliente
    ORDER BY c.ultima_mensagem_em DESC, c.id DESC
    LIMIT :limit
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $tel = (string)$r['telefone_cliente'];
    $ddi = wa_extract_country_code($tel);
    $ddd = wa_extract_area_code($tel, $ddi);

    fputcsv($out, [
        (int)$r['id'],
        $tel,
        $r['status'],
        $r['ultima_msg'],
        $r['ultima_mensagem_em'],
        (int)$r['total_msgs'],
        $r['lead_id'] !== null ? (int)$r['lead_id'] : null,
        $r['lead_nome'],
        $r['lead_status'],
        $r['lead_origem'],
        $ddi,
        $ddd,
    ]);
}

fclose($out);
