<?php
/**
 * Runner de jobs agendados.
 *
 * Pode ser chamado via cron:
 *   php jobs_runner.php
 * ou via HTTP (Admin/Gerente).
 */

declare(strict_types=1);

// Anti-duplication lock: prevent multiple instances from running simultaneously
$lockFile = sys_get_temp_dir() . '/jobs_runner.lock';
$lockHandle = fopen($lockFile, 'w');
if ($lockHandle === false) {
    echo "[jobs_runner] Erro ao criar arquivo de lock." . PHP_EOL;
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    echo "[jobs_runner] Outra instância já está rodando." . PHP_EOL;
    exit(0);
}
register_shutdown_function(function() use ($lockHandle, $lockFile) {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
    if (file_exists($lockFile) && !unlink($lockFile)) {
        error_log("[jobs_runner] Failed to remove lock file: $lockFile");
    }
});

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    require_once __DIR__ . '/rbac.php';
    require_role(['Administrador', 'Gerente']);
}

// Lê jobs pendentes cujo agendado_para é nulo ou <= agora
$sql = "SELECT * FROM jobs_agendados
        WHERE status = 'pendente'
          AND (agendado_para IS NULL OR agendado_para <= NOW())
        ORDER BY id ASC
        LIMIT 20";
$stmt = $pdo->query($sql);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function runner_log(string $msg): void {
    if (php_sapi_name() === 'cli') {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    } else {
        echo '<div>' . htmlspecialchars('[' . date('Y-m-d H:i:s') . '] ' . $msg) . '</div>';
    }
}

if (!$jobs) {
    runner_log('Nenhum job pendente.');
    exit;
}

foreach ($jobs as $job) {
    $id         = (int)$job['id'];
    $tipo       = $job['tipo'];
    $payloadRaw = $job['payload'] ?? null;
    $tentativas = (int)$job['tentativas'];
    $maxTent    = (int)$job['max_tentativas'];

    runner_log("Executando job #$id tipo={$tipo} tentativa=" . ($tentativas+1));

    $ok    = false;
    $erro  = null;
    $msgOk = null;

    try {
        $payload = $payloadRaw ? json_decode($payloadRaw, true) : [];

        switch ($tipo) {
            case 'remarketing_disparo_batch':
                // Implementação real: processa disparos de remarketing pendentes
                $batchSize = (int)($payload['batch_size'] ?? 50);
                $campaignId = (int)($payload['campaign_id'] ?? 0);
                
                // Busca disparos pendentes
                $stmtDisparos = $pdo->prepare("
                    SELECT id, telefone, mensagem 
                    FROM remarketing_disparos 
                    WHERE status = 'pendente' 
                    AND (campaign_id = :cid OR :cid = 0)
                    ORDER BY id ASC 
                    LIMIT :limit
                ");
                $stmtDisparos->bindValue(':cid', $campaignId, PDO::PARAM_INT);
                $stmtDisparos->bindValue(':limit', $batchSize, PDO::PARAM_INT);
                $stmtDisparos->execute();
                $disparos = $stmtDisparos->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($disparos)) {
                    $ok = true;
                    $msgOk = 'Nenhum disparo pendente encontrado.';
                    break;
                }
                
                require_once __DIR__ . '/whatsapp_official_api.php';
                
                $enviados = 0;
                $falhas = 0;
                
                foreach ($disparos as $disparo) {
                    $phoneE164 = whatsapp_normalize_phone_e164($disparo['telefone']);
                    if ($phoneE164 === '') {
                        // Telefone inválido - marca como falha
                        $pdo->prepare("UPDATE remarketing_disparos SET status = 'falha', erro = 'Telefone inválido' WHERE id = :id")
                            ->execute([':id' => $disparo['id']]);
                        $falhas++;
                        continue;
                    }
                    
                    $result = whatsapp_api_send_text($phoneE164, $disparo['mensagem'], ['job_id' => $id]);
                    
                    if ($result['ok']) {
                        $pdo->prepare("UPDATE remarketing_disparos SET status = 'enviado', enviado_em = NOW() WHERE id = :id")
                            ->execute([':id' => $disparo['id']]);
                        $enviados++;
                    } else {
                        $pdo->prepare("UPDATE remarketing_disparos SET status = 'falha', erro = :erro WHERE id = :id")
                            ->execute([':id' => $disparo['id'], ':erro' => mb_substr($result['error'] ?? 'Erro desconhecido', 0, 500)]);
                        $falhas++;
                    }
                    
                    // Delay entre envios para evitar rate limiting
                    usleep(random_int(500000, 1500000)); // 0.5s a 1.5s
                }
                
                $ok = true;
                $msgOk = "Remarketing batch processado: {$enviados} enviados, {$falhas} falhas.";
                break;

            case 'lembrete_agenda_whatsapp':
                // Implementação real: envia lembretes de compromissos agendados
                $antecedeniaMinutos = (int)($payload['antecedencia_minutos'] ?? 60);
                
                // Busca compromissos que começam nos próximos X minutos e ainda não tiveram lembrete enviado
                $stmtAgenda = $pdo->prepare("
                    SELECT a.id, a.titulo, a.data_hora_inicio, a.usuario_id, a.lead_id,
                           u.nome AS usuario_nome, u.telefone AS usuario_telefone,
                           l.nome_cliente, l.telefone_cliente
                    FROM agenda_compromissos a
                    JOIN usuarios u ON u.id = a.usuario_id
                    LEFT JOIN leads l ON l.id = a.lead_id
                    WHERE a.status = 'agendado'
                    AND a.lembrete_enviado = 0
                    AND a.data_hora_inicio BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :min MINUTE)
                    ORDER BY a.data_hora_inicio ASC
                    LIMIT 50
                ");
                $stmtAgenda->execute([':min' => $antecedeniaMinutos]);
                $compromissos = $stmtAgenda->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($compromissos)) {
                    $ok = true;
                    $msgOk = 'Nenhum lembrete pendente.';
                    break;
                }
                
                require_once __DIR__ . '/whatsapp_official_api.php';
                
                $enviados = 0;
                $falhas = 0;
                
                foreach ($compromissos as $comp) {
                    try {
                        $dataFormatada = (new DateTime($comp['data_hora_inicio']))->format('d/m/Y H:i');
                    } catch (Exception $e) {
                        // Data inválida, pula este compromisso
                        runner_log("Compromisso #{$comp['id']} com data inválida: {$comp['data_hora_inicio']}");
                        continue;
                    }
                    
                    // Monta mensagem de lembrete
                    $mensagem = "🔔 *Lembrete de Compromisso*\n\n";
                    $mensagem .= "📋 *{$comp['titulo']}*\n";
                    $mensagem .= "📅 {$dataFormatada}\n";
                    if (!empty($comp['nome_cliente'])) {
                        $mensagem .= "👤 Cliente: {$comp['nome_cliente']}\n";
                    }
                    $mensagem .= "\n_Rede Alabama - Seu CRM Inteligente_";
                    
                    // Envia para o vendedor
                    $phoneVendedor = whatsapp_normalize_phone_e164($comp['usuario_telefone'] ?? '');
                    if ($phoneVendedor !== '') {
                        $result = whatsapp_api_send_text($phoneVendedor, $mensagem, ['tipo' => 'lembrete_agenda']);
                        if ($result['ok']) {
                            $enviados++;
                        } else {
                            $falhas++;
                        }
                    }
                    
                    // Marca lembrete como enviado
                    $pdo->prepare("UPDATE agenda_compromissos SET lembrete_enviado = 1 WHERE id = :id")
                        ->execute([':id' => $comp['id']]);
                    
                    usleep(random_int(300000, 800000)); // 0.3s a 0.8s
                }
                
                $ok = true;
                $msgOk = "Lembretes processados: {$enviados} enviados, {$falhas} falhas.";
                break;

            default:
                $erro = 'Tipo de job não reconhecido: ' . $tipo;
        }
    } catch (Throwable $e) {
        $erro = $e->getMessage();
    }

    if ($ok) {
        $stmtUp = (new \RedeAlabama\Repositories\Screens\JobsRunnerRepository($pdo))->prepare_2292();
        $stmtUp->execute([':id' => $id]);

        $stmtLog = (new \RedeAlabama\Repositories\Screens\JobsRunnerRepository($pdo))->prepare_2505();
        $stmtLog->execute([
            ':job_id'   => $id,
            ':tipo'     => $tipo,
            ':mensagem' => $msgOk ?: 'Job concluído.',
        ]);

        log_app_event('jobs_runner', 'job_sucesso', ['job_id' => $id, 'tipo' => $tipo]);
        runner_log("Job #$id concluído com sucesso.");
    } else {
        $tentativas++;
        $status = ($tentativas >= $maxTent) ? 'falhou' : 'pendente';

        $stmtUp = (new \RedeAlabama\Repositories\Screens\JobsRunnerRepository($pdo))->prepare_3056();
        $stmtUp->execute([
            ':status'     => $status,
            ':tentativas' => $tentativas,
            ':erro'       => $erro,
            ':id'         => $id,
        ]);

        $stmtLog = (new \RedeAlabama\Repositories\Screens\JobsRunnerRepository($pdo))->prepare_3388();
        $stmtLog->execute([
            ':job_id'   => $id,
            ':tipo'     => $tipo,
            ':status'   => $status,
            ':mensagem' => $erro ?: 'Erro desconhecido',
        ]);

        log_app_event('jobs_runner', 'job_erro', ['job_id' => $id, 'tipo' => $tipo, 'erro' => $erro]);
        runner_log("Job #$id terminou com erro: " . ($erro ?: 'erro desconhecido'));
    }
}
