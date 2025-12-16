<?php
declare(strict_types=1);

/**
 * Worker de envio em massa de WhatsApp.
 *
 * Deve ser executado via CLI/cron, por exemplo:
 *   php whatsapp_contacts_bulk_worker.php
 *
 * Estratégia:
 *  - Busca jobs em status queued com agendado_para <= agora ou running.
 *  - Marca queued -> running no início.
 *  - Processa um lote de itens pending por job (ex.: 20).
 *  - Entre cada envio, aguarda um delay randômico entre min_delay_ms e max_delay_ms.
 *  - Respeita modo simulação (não chama API oficial).
 *  - Quando não houver mais pending, marca job como finished.
 */

if (php_sapi_name() !== 'cli') {
    echo "Este script deve ser executado apenas via CLI." . PHP_EOL;
    exit(1);
}

// File lock para prevenir execuções concorrentes
$lockFile = sys_get_temp_dir() . '/whatsapp_bulk_worker.lock';
$lockHandle = fopen($lockFile, 'w');
if ($lockHandle === false) {
    echo "[whatsapp_bulk_worker] Erro ao criar arquivo de lock." . PHP_EOL;
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    echo "[whatsapp_bulk_worker] Outra instância já está rodando." . PHP_EOL;
    exit(0);
}
register_shutdown_function(function() use ($lockHandle, $lockFile) {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
    @unlink($lockFile);
});

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/whatsapp_official_api.php';

$pdo = get_db_connection();

// Config: quantidade de itens por rodada
$batchSize = 20;

echo "[whatsapp_bulk_worker] Iniciando..." . PHP_EOL;

// Initialize bulk log table once at startup
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_bulk_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bulk_job_id INT NOT NULL,
            item_id INT NOT NULL,
            to_phone VARCHAR(32) NOT NULL,
            status VARCHAR(32) NOT NULL,
            enviado_em DATETIME NOT NULL,
            UNIQUE KEY uq_job_item (bulk_job_id, item_id),
            INDEX idx_bulk_job (bulk_job_id),
            INDEX idx_enviado_em (enviado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table already exists, continue
}

try {
    // Busca jobs elegíveis
    $stmtJobs = $pdo->prepare("
        SELECT *
        FROM whatsapp_bulk_jobs
        WHERE
            (
                status = 'queued'
                AND (agendado_para IS NULL OR agendado_para <= NOW())
            )
            OR status = 'running'
        ORDER BY id ASC
        LIMIT 5
    ");
    $stmtJobs->execute();
    $jobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

    if (!$jobs) {
        echo "[whatsapp_bulk_worker] Nenhum job em fila." . PHP_EOL;
        exit(0);
    }

    foreach ($jobs as $job) {
        $jobId = (int)$job['id'];

        // Garante status running
        if ($job['status'] === 'queued') {
            $update = $pdo->prepare("
                UPDATE whatsapp_bulk_jobs
                SET status = 'running', iniciado_em = NOW()
                WHERE id = :id AND status = 'queued'
            ");
            $update->execute([':id' => $jobId]);
            $job['status'] = 'running';
        }

        // Busca itens pendentes para este job
        $stmtItems = $pdo->prepare("
            SELECT *
            FROM whatsapp_bulk_job_items
            WHERE bulk_job_id = :job_id
              AND status = 'pending'
            ORDER BY id ASC
            LIMIT :limit
        ");
        $stmtItems->bindValue(':job_id', $jobId, PDO::PARAM_INT);
        $stmtItems->bindValue(':limit', $batchSize, PDO::PARAM_INT);
        $stmtItems->execute();
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        if (!$items) {
            // Sem itens pendentes -> finaliza job se ainda estiver running
            $finish = $pdo->prepare("
                UPDATE whatsapp_bulk_jobs
                SET status = 'finished', finalizado_em = NOW()
                WHERE id = :id AND status = 'running'
            ");
            $finish->execute([':id' => $jobId]);

            echo "[whatsapp_bulk_worker] Job {$jobId} finalizado." . PHP_EOL;
            continue;
        }

        $minDelay = (int)$job['min_delay_ms'];
        $maxDelay = (int)$job['max_delay_ms'];
        if ($minDelay <= 0) {
            $minDelay = 3000;
        }
        if ($maxDelay < $minDelay) {
            $maxDelay = $minDelay + 2000;
        }

        $isSimulation = (int)$job['is_simulation'] === 1;

        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            $toE164 = $item['to_phone_e164'] ?? $item['to_e164'] ?? '';

            try {
                // Idempotency check: verify if message already sent
                $checkSent = $pdo->prepare("
                    SELECT 1 FROM whatsapp_bulk_log 
                    WHERE bulk_job_id = :job_id AND item_id = :item_id AND status = 'sent'
                    LIMIT 1
                ");
                $checkSent->execute([':job_id' => $jobId, ':item_id' => $itemId]);
                if ($checkSent->fetch()) {
                    echo "[whatsapp_bulk_worker] Item {$itemId} já foi enviado (idempotência). Pulando..." . PHP_EOL;
                    continue;
                }

                $ok = true;
                $result = null;

                if ($isSimulation) {
                    // Não envia de fato, apenas registra.
                    $result = ['ok' => true, 'status' => null, 'simulated' => true];
                } else {
                    $result = whatsapp_api_send_text($toE164, $job['mensagem'], [
                        'mode'       => 'bulk_job',
                        'bulk_job_id'=> $jobId,
                        'item_id'    => $itemId,
                    ]);
                    $ok = $result['ok'] ?? false;
                }

                if ($ok) {
                    $stmtUpdateItem = $pdo->prepare("
                        UPDATE whatsapp_bulk_job_items
                        SET status = 'sent',
                            enviado_em = NOW(),
                            tentativas = tentativas + 1,
                            last_error = NULL
                        WHERE id = :id
                    ");
                    $stmtUpdateItem->execute([':id' => $itemId]);

                    $stmtUpdateJob = $pdo->prepare("
                        UPDATE whatsapp_bulk_jobs
                        SET enviados_sucesso = enviados_sucesso + 1
                        WHERE id = :job_id
                    ");
                    $stmtUpdateJob->execute([':job_id' => $jobId]);

                    // Register in bulk log for idempotency
                    $stmtLog = $pdo->prepare("
                        INSERT INTO whatsapp_bulk_log (bulk_job_id, item_id, to_phone, status, enviado_em)
                        VALUES (:job_id, :item_id, :to_phone, 'sent', NOW())
                        ON DUPLICATE KEY UPDATE status = 'sent', enviado_em = NOW()
                    ");
                    $stmtLog->execute([
                        ':job_id' => $jobId,
                        ':item_id' => $itemId,
                        ':to_phone' => $toE164,
                    ]);

                    log_app_event('whatsapp_bulk', 'item_sent', [
                        'job_id'   => $jobId,
                        'item_id'  => $itemId,
                        'to_e164'  => $toE164,
                        'simulated'=> $isSimulation,
                    ]);
                } else {
                    $errorMsg = $result['error'] ?? 'Falha desconhecida na API WhatsApp';

                    $stmtUpdateItem = $pdo->prepare("
                        UPDATE whatsapp_bulk_job_items
                        SET status = 'failed',
                            tentativas = tentativas + 1,
                            last_error = :err
                        WHERE id = :id
                    ");
                    $stmtUpdateItem->execute([
                        ':id'  => $itemId,
                        ':err' => mb_substr($errorMsg, 0, 1000),
                    ]);

                    $stmtUpdateJob = $pdo->prepare("
                        UPDATE whatsapp_bulk_jobs
                        SET enviados_falha = enviados_falha + 1
                        WHERE id = :job_id
                    ");
                    $stmtUpdateJob->execute([':job_id' => $jobId]);

                    log_app_event('whatsapp_bulk', 'item_failed', [
                        'job_id'  => $jobId,
                        'item_id' => $itemId,
                        'to_e164' => $toE164,
                        'error'   => $errorMsg,
                    ]);
                }

            } catch (Throwable $e) {
                $stmtUpdateItem = $pdo->prepare("
                    UPDATE whatsapp_bulk_job_items
                    SET status = 'failed',
                        tentativas = tentativas + 1,
                        last_error = :err
                    WHERE id = :id
                ");
                $stmtUpdateItem->execute([
                    ':id'  => $itemId,
                    ':err' => mb_substr($e->getMessage(), 0, 1000),
                ]);

                $stmtUpdateJob = $pdo->prepare("
                    UPDATE whatsapp_bulk_jobs
                    SET enviados_falha = enviados_falha + 1
                    WHERE id = :job_id
                ");
                $stmtUpdateJob->execute([':job_id' => $jobId]);

                log_app_event('whatsapp_bulk', 'item_exception', [
                    'job_id'  => $jobId,
                    'item_id' => $itemId,
                    'to_e164' => $toE164,
                    'error'   => $e->getMessage(),
                ]);
            }

            // Delay randômico entre envios
            $delayMs = random_int($minDelay, $maxDelay);
            usleep($delayMs * 1000);
        }

        echo "[whatsapp_bulk_worker] Processado lote do job {$jobId}." . PHP_EOL;
    }

    echo "[whatsapp_bulk_worker] Concluído." . PHP_EOL;
} catch (Throwable $e) {
    log_app_event('whatsapp_bulk', 'worker_exception', [
        'error' => $e->getMessage(),
    ]);
    echo "ERRO no worker: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
