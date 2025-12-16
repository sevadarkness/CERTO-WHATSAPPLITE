<?php
declare(strict_types=1);

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}



/**
 * automation_core.php
 *
 * Núcleo de automação genérico:
 * - fila de eventos (automation_events)
 * - carregamento de regras (automation_rules)
 * - execução de ações
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/rule_engine_simple.php';

/**
 * Verifica se uma tabela de automação existe.
 */
function automation_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\AutomationCoreRepository($pdo))->query_595();
        $cache[$table] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        log_app_error('automation_core', 'table_exists_failed', [
            'table' => $table,
            'error' => $e->getMessage(),
        ]);
        $cache[$table] = false;
    }

    return $cache[$table];
}

/**
 * Verifica se automação está habilitada (tabelas básicas existem).
 */
function automation_is_enabled(PDO $pdo): bool
{
    return automation_table_exists($pdo, 'automation_rules')
        && automation_table_exists($pdo, 'automation_events');
}

/**
 * Enfileira um evento de automação.
 *
 * @param array<string,mixed> $payload
 */
function automation_emit_event(PDO $pdo, string $eventKey, array $payload = []): void
{
    if (!automation_is_enabled($pdo)) {
        return;
    }

    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\AutomationCoreRepository($pdo))->prepare_1487();
        $stmt->execute([
            ':event_key'    => $eventKey,
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':status'       => 'pending',
        ]);

        log_app_event('automation', 'event_enqueued', [
            'event_key' => $eventKey,
        ]);
    } catch (Throwable $e) {
        log_app_error('automation', 'emit_failed', [
            'event_key' => $eventKey,
            'error'     => $e->getMessage(),
        ]);
    }
}

/**
 * Carrega regras ativas, opcionalmente filtrando por event_key.
 *
 * @return array<int, array<string,mixed>>
 */
function automation_load_active_rules(PDO $pdo, ?string $eventKey = null): array
{
    if (!automation_table_exists($pdo, 'automation_rules')) {
        return [];
    }

    try {
        if ($eventKey !== null && $eventKey !== '') {
            $stmt = (new \RedeAlabama\Repositories\Screens\AutomationCoreRepository($pdo))->prepare_2554();
            $stmt->execute([':event_key' => $eventKey]);
        } else {
            $stmt = (new \RedeAlabama\Repositories\Screens\AutomationCoreRepository($pdo))->query_2694();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        log_app_error('automation', 'load_rules_failed', [
            'event_key' => $eventKey,
            'error'     => $e->getMessage(),
        ]);
        return [];
    }
}

/**
 * Executa as ações para um evento.
 *
 * @param array<string,mixed> $eventRow   Linha crua de automation_events
 * @param array<int,array>    $rulesForEvent Conjunto de regras ativas que podem responder a este evento
 */
function automation_process_event(PDO $pdo, array $eventRow, array $rulesForEvent): void
{
    if (!automation_table_exists($pdo, 'automation_events')) {
        return;
    }

    $eventId   = (int) ($eventRow['id'] ?? 0);
    $eventKey  = (string) ($eventRow['event_key'] ?? '');
    $payload   = $eventRow['payload_json'] ?? '{}';
    $payloadAr = json_decode((string) $payload, true);
    if (!is_array($payloadAr)) {
        $payloadAr = [];
    }

    $context = [
        'event' => [
            'id'      => $eventId,
            'key'     => $eventKey,
            'payload' => $payloadAr,
        ],
    ];

    $erroGlobal = null;

    try {
        foreach ($rulesForEvent as $rule) {
            $ruleId = (int) ($rule['id'] ?? 0);

            $matches = rule_engine_simple_matches($rule, $context);
            if (!$matches) {
                continue;
            }

            // Executa ação
            automation_execute_action($pdo, $rule, $context, $eventRow);
        }

        // Marca evento como processado
        $stmtUp = (new \RedeAlabama\Repositories\Screens\AutomationCoreRepository($pdo))->prepare_4402();
        $stmtUp->execute([
            ':status' => 'done',
            ':id'     => $eventId,
        ]);
    } catch (Throwable $e) {
        $erroGlobal = $e->getMessage();

        $stmtUp = (new \RedeAlabama\Repositories\Screens\AutomationCoreRepository($pdo))->prepare_4717();
        $stmtUp->execute([
            ':status' => 'error',
            ':err'    => $erroGlobal,
            ':id'     => $eventId,
        ]);

        log_app_error('automation', 'process_event_failed', [
            'event_id' => $eventId,
            'error'    => $erroGlobal,
        ]);
    }
}

/**
 * Executa uma ação de automação, de forma simples.
 *
 * Ações suportadas (núcleo):
 * - log_only
 *
 * Pontos de extensão marcados para acionar fluxos/jobs no futuro.
 *
 * @param array<string,mixed> $rule
 * @param array<string,mixed> $context
 * @param array<string,mixed> $eventRow
 */
function automation_execute_action(PDO $pdo, array $rule, array $context, array $eventRow): void
{
    $ruleId      = (int) ($rule['id'] ?? 0);
    $actionType  = (string) ($rule['action_type'] ?? 'log_only');
    $actionRaw   = $rule['action_payload_json'] ?? null;
    $actionData  = null;

    if ($actionRaw !== null && $actionRaw !== '') {
        $tmp = json_decode((string) $actionRaw, true);
        if (is_array($tmp)) {
            $actionData = $tmp;
        }
    }
    if (!is_array($actionData)) {
        $actionData = [];
    }

    switch ($actionType) {
        case 'log_only':
        default:
            log_app_event('automation', 'rule_fired', [
                'rule_id'  => $ruleId,
                'event_id' => $eventRow['id'] ?? null,
                'event_key'=> $eventRow['event_key'] ?? null,
                'context'  => $context,
            ]);
            break;

        // Pontos de extensão para acionar fluxos / jobs:
        // case 'start_flow_for_conversation':
        //     // Exemplo:
        //     // $flowId = (int) ($actionData['flow_id'] ?? 0);
        //     // $conversaId = (int) ($context['event']['payload']['conversa_id'] ?? 0);
        //     // ...
        //     break;
    }
}

/**
 * Processa eventos pendentes.
 */
function automation_run_pending(PDO $pdo, int $maxEvents = 50): void
{
    if (!automation_is_enabled($pdo)) {
        return;
    }

    try {
        $stmt = (new \RedeAlabama\Repositories\Screens\AutomationCoreRepository($pdo))->prepare_6878();
        $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $maxEvents, PDO::PARAM_INT);
        $stmt->execute();

        $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$events) {
            return;
        }

        foreach ($events as $eventRow) {
            $eventKey = (string) ($eventRow['event_key'] ?? '');
            $rules    = automation_load_active_rules($pdo, $eventKey);
            if (!$rules) {
                // nada a fazer, apenas marca como done
                $stmtUp = (new \RedeAlabama\Repositories\Screens\AutomationCoreRepository($pdo))->prepare_7535();
                $stmtUp->execute([
                    ':status' => 'done',
                    ':id'     => (int) ($eventRow['id'] ?? 0),
                ]);
                continue;
            }

            automation_process_event($pdo, $eventRow, $rules);
        }
    } catch (Throwable $e) {
        log_app_error('automation', 'run_pending_failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
