<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



/**
 * flows_versioning.php
 *
 * Versionamento simples de fluxos (whatsapp_flows) + rollback.
 *
 * Este módulo é opcional: se a tabela whatsapp_flow_versions não existir,
 * as funções retornam sem lançar erro fatal.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/app/Support/PrometheusMetrics.php';

/**
 * Verifica se a tabela de versões existe.
 */
function flow_versioning_is_enabled(PDO $pdo): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->query_550();
        $cache = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        log_app_error('flow_versioning', 'check_failed', [
            'error' => $e->getMessage(),
        ]);
        $cache = false;
    }

    return $cache;
}

/**
 * Cria um snapshot do fluxo + steps em JSON.
 */
function flow_versioning_snapshot(PDO $pdo, int $flowId, ?int $userId = null, ?string $reason = null): void
{
    if ($flowId <= 0) {
        return;
    }
    if (!flow_versioning_is_enabled($pdo)) {
        return;
    }

    try {
        // Carrega dados do fluxo
        $stmtFlow = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_1190();
        $stmtFlow->execute([':id' => $flowId]);
        $flow = $stmtFlow->fetch(PDO::FETCH_ASSOC);

        if (!$flow) {
            return;
        }

        // Carrega steps
        $stmtSteps = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_1452();
        $stmtSteps->execute([':flow_id' => $flowId]);
        $steps = $stmtSteps->fetchAll(PDO::FETCH_ASSOC);

        $snapshot = [
            'flow'  => $flow,
            'steps' => $steps,
        ];

        // Calcula próximo número de versão
        $stmtMax = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_1823();
        $stmtMax->execute([':flow_id' => $flowId]);
        $maxV = (int) ($stmtMax->fetchColumn() ?: 0);
        $nextV = $maxV + 1;

        $stmtIns = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_2084();

        $stmtIns->execute([
            ':flow_id'       => $flowId,
            ':version_number'=> $nextV,
            ':created_by'    => $userId,
            ':reason'        => $reason,
            ':snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        log_app_event('flow_versioning', 'snapshot_created', [
            'flow_id'        => $flowId,
            'version_number' => $nextV,
            'user_id'        => $userId,
        ]);
    } catch (Throwable $e) {
        log_app_error('flow_versioning', 'snapshot_error', [
            'flow_id' => $flowId,
            'error'   => $e->getMessage(),
        ]);
    }
}

/**
 * Lista versões existentes para um fluxo.
 *
 * @return array<int, array<string,mixed>>
 */
function flow_versioning_list(PDO $pdo, int $flowId): array
{
    if ($flowId <= 0) {
        return [];
    }
    if (!flow_versioning_is_enabled($pdo)) {
        return [];
    }

    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_3327();
        $stmt->execute([':flow_id' => $flowId]);

        // Métrica Prometheus: snapshot criado
        \RedeAlabama\Support\PrometheusMetrics::instance()->incCounter(
            'whatsapp_flows_snapshot_total',
            ['flow_id' => $flowId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        log_app_error('flow_versioning', 'list_error', [
            'flow_id' => $flowId,
            'error'   => $e->getMessage(),
        ]);
        return [];
    }
}

/**
 * Rollback de um fluxo para uma versão anterior.
 *
 * - Atualiza dados básicos do fluxo.
 * - Apaga e recria steps conforme snapshot.
 */
function flow_versioning_rollback(PDO $pdo, int $flowId, int $versionId, ?int $userId = null): bool
{
    if ($flowId <= 0 || $versionId <= 0) {
        return false;
    }
    if (!flow_versioning_is_enabled($pdo)) {
        return false;
    }

    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_4161();
        $stmt->execute([
            ':id'      => $versionId,
            ':flow_id' => $flowId,
        ]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$version) {
            return false;
        }

        $snapshot = json_decode($version['flow_snapshot_json'] ?? '', true);
        if (!is_array($snapshot) || !isset($snapshot['flow']) || !isset($snapshot['steps'])) {
            return false;
        }

        $flowData  = is_array($snapshot['flow']) ? $snapshot['flow'] : [];
        $stepsData = is_array($snapshot['steps']) ? $snapshot['steps'] : [];

        $pdo->beginTransaction();

        // Atualiza campos básicos do fluxo (sem mexer em IDs/created_at)
        $allowedCols = ['name', 'description', 'status', 'target_segment'];

        $sets   = [];
        $params = [':id' => $flowId];

        foreach ($allowedCols as $col) {
            if (array_key_exists($col, $flowData)) {
                $sets[]             = "$col = :$col";
                $params[":$col"] = $flowData[$col];
            }
        }

        if ($sets) {
            $sqlUpdate = 'UPDATE whatsapp_flows SET ' . implode(', ', $sets) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
            $stmtUp    = $pdo->prepare($sqlUpdate);
            $stmtUp->execute($params);
        }

        // Remove steps atuais
        $stmtDel = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_5615();
        $stmtDel->execute([':flow_id' => $flowId]);

        // Reinsere steps
        $sqlIns = 'INSERT INTO whatsapp_flow_steps (flow_id, step_order, step_type, template_slug, delay_minutes, is_active)
                   VALUES (:flow_id, :step_order, :step_type, :template_slug, :delay_minutes, :is_active)';
        $stmtIns = $pdo->prepare($sqlIns);

        foreach ($stepsData as $s) {
            if (!is_array($s)) {
                continue;
            }
            $stmtIns->execute([
                ':flow_id'       => $flowId,
                ':step_order'    => (int) ($s['step_order'] ?? 0),
                ':step_type'     => (string) ($s['step_type'] ?? 'mensagem_whatsapp'),
                ':template_slug' => (string) ($s['template_slug'] ?? ''),
                ':delay_minutes' => (int) ($s['delay_minutes'] ?? 0),
                ':is_active'     => (int) ($s['is_active'] ?? 1),
            ]);
        }

        // Cria snapshot automático da situação pós-rollback
        flow_versioning_snapshot($pdo, $flowId, $userId, 'rollback from version ' . (int) ($version['version_number'] ?? 0));

        $pdo->commit();

        log_app_event('flow_versioning', 'rollback_ok', [
            'flow_id'    => $flowId,
            'version_id' => $versionId,
            'user_id'    => $userId,
        ]);

        // Métrica Prometheus: rollback bem-sucedido
        \RedeAlabama\Support\PrometheusMetrics::instance()->incCounter(
            'whatsapp_flows_rollback_total',
            ['flow_id' => $flowId, 'result' => 'success']
        );

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        log_app_error('flow_versioning', 'rollback_error', [
            'flow_id'    => $flowId,
            'version_id' => $versionId,
            'error'      => $e->getMessage(),
        ]);

        // Métrica Prometheus: rollback com erro
        \RedeAlabama\Support\PrometheusMetrics::instance()->incCounter(
            'whatsapp_flows_rollback_total',
            ['flow_id' => $flowId, 'result' => 'error']
        );

        return false;
    }
}

/**
 * Carrega snapshot corrente do fluxo (estado atual no banco),
 * no mesmo formato utilizado em snapshots de versão.
 *
 * @return array{flow:array<array-key,mixed>,steps:array<int,array<string,mixed>>}|null
 */
function flow_versioning_current_snapshot(PDO $pdo, int $flowId): ?array
{
    if ($flowId <= 0) {
        return null;
    }
    if (!flow_versioning_is_enabled($pdo)) {
        return null;
    }

    try {
        // Mesmo padrão da função flow_versioning_snapshot
        $stmtFlow = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_1190();
        $stmtFlow->execute([':id' => $flowId]);
        $flow = $stmtFlow->fetch(PDO::FETCH_ASSOC);

        if (!$flow) {
            return null;
        }

        $stmtSteps = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_1452();
        $stmtSteps->execute([':flow_id' => $flowId]);
        $steps = $stmtSteps->fetchAll(PDO::FETCH_ASSOC);

        return [
            'flow'  => $flow,
            'steps' => $steps,
        ];
    } catch (\Throwable $e) {
        log_app_error('flow_versioning', 'current_snapshot_error', [
            'flow_id' => $flowId,
            'error'   => $e->getMessage(),
        ]);
        return null;
    }
}

/**
 * Compara dois snapshots (current vs version) e devolve um diff estruturado.
 *
 * @param array{flow:array,steps:array} $current
 * @param array{flow:array,steps:array} $target
 * @return array{
 *   flow_diff: array<int,array{field:string,current:mixed,version:mixed}>,
 *   steps_diff: array{
 *     added: array<int,array<string,mixed>>,
 *     removed: array<int,array<string,mixed>>,
 *     changed: array<int,array{step_order:int,current:array<string,mixed>,version:array<string,mixed>}>
 *   }
 * }
 */
function flow_versioning_compute_diff_arrays(array $current, array $target): array
{
    $flowCurrent = isset($current['flow']) && is_array($current['flow']) ? $current['flow'] : [];
    $flowTarget  = isset($target['flow']) && is_array($target['flow']) ? $target['flow'] : [];

    $flowDiff = [];
    $allKeys  = array_unique(array_merge(array_keys($flowCurrent), array_keys($flowTarget)));

    foreach ($allKeys as $field) {
        $cur = $flowCurrent[$field] ?? null;
        $tar = $flowTarget[$field] ?? null;
        if ($cur !== $tar) {
            $flowDiff[] = [
                'field'   => (string) $field,
                'current' => $cur,
                'version' => $tar,
            ];
        }
    }

    $stepsCurrent = isset($current['steps']) && is_array($current['steps']) ? $current['steps'] : [];
    $stepsTarget  = isset($target['steps']) && is_array($target['steps']) ? $target['steps'] : [];

    $indexByOrder = function (array $steps): array {
        $out = [];
        foreach ($steps as $s) {
            if (!is_array($s)) {
                continue;
            }
            $order = isset($s['step_order']) ? (int) $s['step_order'] : 0;
            $out[$order] = $s;
        }
        ksort($out);
        return $out;
    };

    $curIdx = $indexByOrder($stepsCurrent);
    $tarIdx = $indexByOrder($stepsTarget);

    $added   = []; // estarão na versão, mas não no atual -> serão "adicionados" após rollback
    $removed = []; // estão no atual, mas não na versão -> serão removidos após rollback
    $changed = [];

    $allOrders = array_unique(array_merge(array_keys($curIdx), array_keys($tarIdx)));
    sort($allOrders);

    foreach ($allOrders as $order) {
        $cur = $curIdx[$order] ?? null;
        $tar = $tarIdx[$order] ?? null;

        if ($cur === null && $tar !== null) {
            $added[] = $tar;
        } elseif ($cur !== null && $tar === null) {
            $removed[] = $cur;
        } elseif (is_array($cur) && is_array($tar)) {
            // Verifica se houve alguma diferença relevante no step
            if ($cur !== $tar) {
                $changed[] = [
                    'step_order' => (int) $order,
                    'current'    => $cur,
                    'version'    => $tar,
                ];
            }
        }
    }

    return [
        'flow_diff'  => $flowDiff,
        'steps_diff' => [
            'added'   => $added,
            'removed' => $removed,
            'changed' => $changed,
        ],
    ];
}

/**
 * Calcula o diff entre o estado atual do fluxo e uma versão específica.
 *
 * @return array{
 *   version: array<string,mixed>,
 *   flow_diff: array<int,array{field:string,current:mixed,version:mixed}>,
 *   steps_diff: array{
 *     added: array<int,array<string,mixed>>,
 *     removed: array<int,array<string,mixed>>,
 *     changed: array<int,array{step_order:int,current:array<string,mixed>,version:array<string,mixed>}>
 *   }
 * }|null
 */
function flow_versioning_diff_version(PDO $pdo, int $flowId, int $versionId): ?array
{
    if ($flowId <= 0 || $versionId <= 0) {
        return null;
    }
    if (!flow_versioning_is_enabled($pdo)) {
        return null;
    }

    try {
        // Carrega versão alvo
        $stmt = (new \RedeAlabama\Repositories\Screens\FlowsVersioningRepository($pdo))->prepare_4161();
        $stmt->execute([
            ':id'      => $versionId,
            ':flow_id' => $flowId,
        ]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$version) {
            return null;
        }

        $snapshot = json_decode($version['flow_snapshot_json'] ?? '', true);
        if (!is_array($snapshot) || !isset($snapshot['flow']) || !isset($snapshot['steps'])) {
            return null;
        }

        $currentSnapshot = flow_versioning_current_snapshot($pdo, $flowId);
        if ($currentSnapshot === null) {
            return null;
        }

        $diff = flow_versioning_compute_diff_arrays($currentSnapshot, $snapshot);

        // Métrica Prometheus: diff de versão solicitado
        \RedeAlabama\Support\PrometheusMetrics::instance()->incCounter(
            'whatsapp_flows_diff_total',
            ['flow_id' => $flowId]
        );
        $diff['version'] = $version;

        return $diff;
    } catch (\Throwable $e) {
        log_app_error('flow_versioning', 'diff_error', [
            'flow_id'    => $flowId,
            'version_id' => $versionId,
            'error'      => $e->getMessage(),
        ]);
        return null;
    }
}
