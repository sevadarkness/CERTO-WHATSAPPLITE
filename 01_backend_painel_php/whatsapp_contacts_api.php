<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



require_once __DIR__ . '/rbac.php';
require_role(['Administrador', 'Gerente', 'Vendedor']);
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/whatsapp_contacts_utils.php';

header('Content-Type: application/json; charset=utf-8');
/**
 * Calcula diferença em dias entre agora e uma data/hora (YYYY-mm-dd HH:ii:ss).
 */
function whatsapp_diff_days_from_now(?string $datetime): ?int
{
    if (!$datetime) {
        return null;
    }

    try {
        $dt  = new DateTime($datetime);
        $now = new DateTime('now', $dt->getTimezone());
        $diff = $now->getTimestamp() - $dt->getTimestamp();
        if ($diff < 0) {
            return 0;
        }
        return (int)floor($diff / 86400);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Segmenta contato por faixa de inatividade (D0–D30+), inspirado em CRMs de WhatsApp.
 */
function whatsapp_segment_inactivity(?string $datetime): ?string
{
    $days = whatsapp_diff_days_from_now($datetime);
    if ($days === null) {
        return null;
    }

    if ($days <= 3) {
        return 'D0–D3';
    }
    if ($days <= 7) {
        return 'D4–D7';
    }
    if ($days <= 15) {
        return 'D8–D15';
    }
    if ($days <= 30) {
        return 'D16–D30';
    }

    return 'D30+';
}



$action = strtolower(trim((string)($_GET['action'] ?? $_GET['acao'] ?? 'chats')));
if ($action === '') {
    $action = 'chats';
}

$response = [
    'ok'      => false,
    'action'  => $action,
    'error'   => null,
    'data'    => null,
    'meta'    => null,
];

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($action) {
        case 'chats':
        case 'getchatscontacts':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
            if ($limit <= 0) {
                $limit = 500;
            }
            if ($limit > 5000) {
                $limit = 5000;
            }

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
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $contacts = [];
            foreach ($rows as $r) {
                $tel   = (string)$r['telefone_cliente'];
                $ddi   = wa_extract_country_code($tel);
                $ddd   = wa_extract_area_code($tel, $ddi);

                $contacts[] = [
                    'conversa_id'        => (int)$r['id'],
                    'telefone'           => $tel,
                    'status_conversa'    => $r['status'],
                    'ultima_mensagem'    => $r['ultima_msg'],
                    'ultima_mensagem_em' => $r['ultima_mensagem_em'],
                    'total_mensagens'    => (int)$r['total_msgs'],
                    'dias_desde_ultima_mensagem' => whatsapp_diff_days_from_now($r['ultima_mensagem_em']),
                    'faixa_inatividade'  => whatsapp_segment_inactivity($r['ultima_mensagem_em']),
                    'lead'               => [
                        'id'     => $r['lead_id'] !== null ? (int)$r['lead_id'] : null,
                        'nome'   => $r['lead_nome'] ?? null,
                        'status' => $r['lead_status'] ?? null,
                        'origem' => $r['lead_origem'] ?? null,
                    ],
                    'pais_ddi'           => $ddi,
                    'ddd'                => $ddd,
                ];
            }

            $response['ok']   = true;
            $response['data'] = $contacts;
            $response['meta'] = [
                'total'        => count($contacts),
                'limit'        => $limit,
                'generated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            ];
            break;

        case 'exists':
        case 'isexistnumber':
            $telInput = trim((string)($_GET['telefone'] ?? $_GET['phone'] ?? ''));
            if ($telInput === '') {
                throw new InvalidArgumentException('Parâmetro telefone/phone é obrigatório.');
            }

            $digits = wa_only_digits($telInput);

            $sql = "
                SELECT 
                    c.id AS conversa_id,
                    c.telefone_cliente,
                    l.id AS lead_id
                FROM whatsapp_conversas c
                LEFT JOIN leads l ON l.telefone_cliente = c.telefone_cliente
                WHERE c.telefone_cliente = :tel
                   OR REPLACE(REPLACE(REPLACE(REPLACE(c.telefone_cliente, ' ', ''), '-', ''), '(', ''), ')', '') = :tel_digits
                ORDER BY c.id ASC
                LIMIT 1
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tel'        => $telInput,
                ':tel_digits' => $digits,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $exists = $row !== false;

            $response['ok']   = true;
            $response['data'] = [
                'exists'        => $exists,
                'conversa_id'   => $exists ? (int)$row['conversa_id'] : null,
                'lead_id'       => $exists && $row['lead_id'] !== null ? (int)$row['lead_id'] : null,
                'telefone_base' => $exists ? (string)$row['telefone_cliente'] : null,
            ];
            $response['meta'] = [
                'query_telefone' => $telInput,
            ];
            break;

        case 'labels':
        case 'getalllabels':
            $sql = "
                SELECT 
                    l.status AS label,
                    COUNT(DISTINCT l.id)  AS total_leads,
                    COUNT(DISTINCT c.id)  AS total_conversas
                FROM leads l
                JOIN whatsapp_conversas c ON c.telefone_cliente = l.telefone_cliente
                WHERE l.status IS NOT NULL AND l.status <> ''
                GROUP BY l.status
                ORDER BY l.status
            ";
            $stmt = $pdo->query($sql);
            $labels = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response['ok']   = true;
            $response['data'] = $labels;
            $response['meta'] = [
                'total_labels'  => count($labels),
                'generated_at'  => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            ];
            break;

        case 'labels_contacts':
        case 'getlabelscontacts':
            $sql = "
                SELECT 
                    l.status       AS label,
                    c.id           AS conversa_id,
                    c.telefone_cliente,
                    l.id           AS lead_id,
                    l.nome_cliente AS lead_nome,
                    l.origem       AS lead_origem
                FROM leads l
                JOIN whatsapp_conversas c ON c.telefone_cliente = l.telefone_cliente
                WHERE l.status IS NOT NULL AND l.status <> ''
                ORDER BY l.status, c.id
            ";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = [];
            foreach ($rows as $r) {
                $label = (string)$r['label'];
                if (!isset($map[$label])) {
                    $map[$label] = [
                        'label'    => $label,
                        'contacts' => [],
                    ];
                }
                $tel = (string)$r['telefone_cliente'];
                $ddi = wa_extract_country_code($tel);
                $ddd = wa_extract_area_code($tel, $ddi);

                $map[$label]['contacts'][] = [
                    'conversa_id' => (int)$r['conversa_id'],
                    'telefone'    => $tel,
                    'lead_id'     => $r['lead_id'] !== null ? (int)$r['lead_id'] : null,
                    'lead_nome'   => $r['lead_nome'] ?? null,
                    'lead_origem' => $r['lead_origem'] ?? null,
                    'pais_ddi'    => $ddi,
                    'ddd'         => $ddd,
                ];
            }

            $data = array_values($map);
            $response['ok']   = true;
            $response['data'] = $data;
            $response['meta'] = [
                'total_labels'   => count($data),
                'generated_at'   => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            ];
            break;

        case 'countries':
        case 'getcountriescontacts':
            $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappContactsApiRepository($pdo))->query_9479();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $counts = [];
            foreach ($rows as $r) {
                $tel = (string)$r['telefone_cliente'];
                $ddi = wa_extract_country_code($tel);
                if ($ddi === null || $ddi === '') {
                    continue;
                }
                if (!isset($counts[$ddi])) {
                    $counts[$ddi] = 0;
                }
                $counts[$ddi]++;
            }

            $countries = [];
            foreach ($counts as $ddi => $qtd) {
                $countries[] = [
                    'ddi'   => $ddi,
                    'total' => $qtd,
                ];
            }

            usort($countries, static function (array $a, array $b): int {
                return $b['total'] <=> $a['total'];
            });

            $response['ok']   = true;
            $response['data'] = $countries;
            $response['meta'] = [
                'total_ddi'     => count($countries),
                'generated_at'  => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            ];
            break;

        case 'areas':
        case 'getareacodelist':
            $stmt = (new \RedeAlabama\Repositories\Screens\WhatsappContactsApiRepository($pdo))->query_10755();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $counts = [];
            foreach ($rows as $r) {
                $tel = (string)$r['telefone_cliente'];
                $ddi = wa_extract_country_code($tel);
                $ddd = wa_extract_area_code($tel, $ddi);
                if ($ddd === null || $ddd === '') {
                    continue;
                }
                $key = ($ddi ?? '') . '-' . $ddd;
                if (!isset($counts[$key])) {
                    $counts[$key] = ['ddi' => $ddi, 'ddd' => $ddd, 'total' => 0];
                }
                $counts[$key]['total']++;
            }

            $areas = array_values($counts);
            usort($areas, static function (array $a, array $b): int {
                return $b['total'] <=> $a['total'];
            });

            $response['ok']   = true;
            $response['data'] = $areas;
            $response['meta'] = [
                'total_areas'   => count($areas),
                'generated_at'  => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            ];
            break;

        default:
            throw new InvalidArgumentException('Ação inválida: ' . $action);
    }
} catch (Throwable $e) {
    http_response_code(400);
    $response['ok']    = false;
    $response['error'] = $e->getMessage();
    log_app_event('whatsapp_contacts_api', 'erro', [
        'action' => $action,
        'erro'   => $e->getMessage(),
    ]);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
