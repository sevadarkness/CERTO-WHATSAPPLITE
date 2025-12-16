<?php
/**
 * whatsapp_flow_worker.php
 *
 * Worker responsável por consumir a tabela whatsapp_flow_queue
 * e enviar efetivamente as mensagens via API oficial do WhatsApp
 * (WhatsApp Cloud API - Meta).
 *
 * Recomenda-se executar este script via CLI/CRON, por exemplo:
 *   0-59/2 * * * * php /caminho/para/adm.redealabama/whatsapp_flow_worker.php >> /var/log/whatsapp_worker.log 2>&1
 */

declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



// Em worker CLI não é necessário iniciar sessão, apenas DB + logger.
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/whatsapp_official_api.php';

if (PHP_SAPI !== 'cli') {
    // Se quiser permitir HTTP, troque a política aqui (exigir auth, etc.).
    header('Content-Type: text/plain; charset=utf-8');
    echo "Este worker deve ser executado via CLI (php whatsapp_flow_worker.php)." . PHP_EOL;
    // Não dá exit para permitir testes HTTP em ambiente controlado, mas evita rodar o loop.
    return;
}

/**
 * Processa um lote de mensagens pendentes na fila.
 *
 * @param PDO $pdo
 * @param int $limit
 */
function process_flow_queue(PDO $pdo, int $limit = 50): void
{
    $sql = "SELECT id, flow_id, flow_step_id, conversa_id, telefone, mensagem
            FROM whatsapp_flow_queue
            WHERE status = 'pendente'
            ORDER BY id ASC
            LIMIT :limite";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo "Nenhuma mensagem pendente na fila." . PHP_EOL;
        return;
    }

    foreach ($rows as $row) {
        $queueId    = (int)$row['id'];
        $telefoneDb = (string)$row['telefone'];
        $mensagem   = (string)$row['mensagem'];

        $toE164 = whatsapp_normalize_phone_e164($telefoneDb, '55');
        if ($toE164 === '') {
            $err = "Telefone inválido: " . $telefoneDb;
            echo "[ERRO] {$err}" . PHP_EOL;

            // Marca como erro permanente
            $upd = (new \RedeAlabama\Repositories\Screens\WhatsappFlowWorkerRepository($pdo))->prepare_2039();
            $upd->execute([
                ':err' => mb_substr($err, 0, 500),
                ':id'  => $queueId,
            ]);

            if (function_exists('log_app_event')) {
                log_app_event('whatsapp_flow_worker', 'invalid_phone', [
                    'queue_id' => $queueId,
                    'telefone' => $telefoneDb,
                ]);
            }
            continue;
        }

        echo "Enviando mensagem da fila #{$queueId} para {$toE164}..." . PHP_EOL;

        $result = whatsapp_api_send_text($toE164, $mensagem, [
            'queue_id'    => $queueId,
            'flow_id'     => (int)$row['flow_id'],
            'flow_step_id'=> (int)$row['flow_step_id'],
            'conversa_id' => (int)$row['conversa_id'],
        ]);

        if (!$result['ok']) {
            $err = $result['error'] ?? 'Erro desconhecido no envio WhatsApp.';
            echo "[ERRO] Falha ao enviar fila #{$queueId}: {$err}" . PHP_EOL;

            $upd = (new \RedeAlabama\Repositories\Screens\WhatsappFlowWorkerRepository($pdo))->prepare_3226();
            $upd->execute([
                ':err' => mb_substr($err, 0, 1000),
                ':id'  => $queueId,
            ]);

            if (function_exists('log_app_event')) {
                log_app_event('whatsapp_flow_worker', 'send_error', [
                    'queue_id' => $queueId,
                    'telefone' => $telefoneDb,
                    'to_e164'  => $toE164,
                    'status'   => $result['status'] ?? null,
                    'error'    => $err,
                ]);
            }
        } else {
            echo "[OK] Mensagem da fila #{$queueId} enviada com sucesso." . PHP_EOL;

            $upd = (new \RedeAlabama\Repositories\Screens\WhatsappFlowWorkerRepository($pdo))->prepare_4077();
            $upd->execute([
                ':id' => $queueId,
            ]);

            if (function_exists('log_app_event')) {
                log_app_event('whatsapp_flow_worker', 'send_ok', [
                    'queue_id' => $queueId,
                    'telefone' => $telefoneDb,
                    'to_e164'  => $toE164,
                    'status'   => $result['status'] ?? null,
                ]);
            }
        }
    }
}

try {
    echo "Iniciando worker de envio WhatsApp..." . PHP_EOL;
    process_flow_queue($pdo, 50);
    echo "Worker finalizado." . PHP_EOL;
} catch (Throwable $e) {
    echo "Erro fatal no worker: " . $e->getMessage() . PHP_EOL;
    if (function_exists('log_app_event')) {
        log_app_event('whatsapp_flow_worker', 'fatal_error', [
            'error' => $e->getMessage(),
        ]);
    }
}
?>
