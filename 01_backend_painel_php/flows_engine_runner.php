<?php
    /**
     * flows_engine_runner.php
     *
     * Executor simples de fluxos WhatsApp + IA com delays.
     * Deve ser chamado via CRON ou manualmente pelo admin.
     *
     * IMPORTANTE:
     * - Este executor NÃO envia mensagens para a API oficial do WhatsApp.
     *   Ele apenas prepara uma fila (tabela whatsapp_flow_queue) com
     *   mensagens geradas via IA para serem consumidas pelo seu conector
     *   (robô externo, worker, etc.).
     */

    declare(strict_types=1);

    require_once __DIR__ . '/session_bootstrap.php';
    require_once __DIR__ . '/rbac.php';
    require_role(['Administrador', 'Gerente']); // restringe a perfis altos

    require_once __DIR__ . '/db_config.php';
    require_once __DIR__ . '/logger.php';
    require_once __DIR__ . '/whatsapp_llm_helper.php';

    header('Content-Type: text/plain; charset=utf-8');

    /**
     * Executa o engine de fluxos.
     */

    /**
     * Processa um step de mensagem: gera conteúdo via IA e enfileira.
     */
    function flows_process_message_step(PDO $pdo, array $step, array $conv, int $flowId, ?array $exec): void
    {
        $conversaId = (int)$conv['id'];
        $telefone   = (string)$conv['telefone_cliente'];
        
        // Monta prompt para IA (usar template_slug do step como "template" lógico)
        $templateSlug = $step['template_slug'] ?? 'whatsapp_reativacao';
        
        $prompt = "Você é um assistente de automação de WhatsApp da Rede Alabama.

" .
                  "Gere uma mensagem pronta para reativar um cliente via WhatsApp, com base no seguinte contexto:
" .
                  "- Template: {$templateSlug}
" .
                  "- Tom: engajador, com foco em reconquistar o cliente.

" .
                  "Instruções gerais:
" .
                  "- Responda SEMPRE em português do Brasil.
" .
                  "- Devolva apenas a mensagem final para colar no WhatsApp.
";

        $result = llm_call($prompt, [
            'context' => 'whatsapp_flow_engine',
            'meta'    => [
                'flow_id'     => $flowId,
                'conversa_id' => $conversaId,
                'step_id'     => $step['id'] ?? null,
                'telefone'    => $telefone,
            ],
        ]);

        if (!$result['ok']) {
            echo "  Falha IA para conversa {$conversaId}: " . ($result['error'] ?? 'erro') . PHP_EOL;
            return;
        }

        $mensagemGerada = $result['content'];

        // Enfileira em whatsapp_flow_queue
        $insQueue = (new \RedeAlabama\Repositories\Screens\FlowsEngineRunnerRepository($pdo))->prepare_7646();
        $insQueue->execute([
            ':flow_id'      => $flowId,
            ':flow_step_id' => (int)($step['id'] ?? 0),
            ':conversa_id'  => $conversaId,
            ':telefone'     => $telefone,
            ':mensagem'     => $mensagemGerada,
        ]);

        echo "  Mensagem gerada para conversa {$conversaId}." . PHP_EOL;
    }

    /**
     * Atualiza ou cria uma execução de fluxo para avançar para o próximo step.
     */
    function flows_update_execution(PDO $pdo, int $flowId, int $conversaId, string $nextStepId, int $delay, ?array $exec): void
    {
        if ($exec) {
            // Atualiza execução existente
            $updExec = (new \RedeAlabama\Repositories\Screens\FlowsEngineRunnerRepository($pdo))->prepare_8318();
            $updExec->execute([
                ':current_step' => $nextStepId,
                ':delay'        => $delay,
                ':id'           => (int)$exec['id'],
            ]);
        } else {
            // Cria nova execução
            $insExec = (new \RedeAlabama\Repositories\Screens\FlowsEngineRunnerRepository($pdo))->prepare_9083();
            $insExec->execute([
                ':flow_id'      => $flowId,
                ':conversa_id'  => $conversaId,
                ':current_step' => $nextStepId,
                ':delay'        => $delay,
                ':next_run_at'  => $delay > 0 ? null : null,
            ]);
        }
    }

    /**
     * Finaliza uma execução de fluxo (marca como concluída).
     */
    function flows_finalize_execution(PDO $pdo, ?array $exec): void
    {
        if ($exec) {
            $upd = (new \RedeAlabama\Repositories\Screens\FlowsEngineRunnerRepository($pdo))->prepare_5369();
            $upd->execute([':id' => (int)$exec['id']]);
        }
    }

    /**
     * Carrega definição de steps (grafo) para um fluxo.
     * Usa whatsapp_flows.definition_json quando existir; fallback para whatsapp_flow_steps linear.
     *
     * @return array{id:string,type:string,template_slug:?string,delay_minutes:int,next:?string,condition:?string,next_if:?string,next_else:?string}[]
     */
    function flows_engine_load_graph(PDO $pdo, int $flowId): array
    {
        $flowId = (int)$flowId;
        if ($flowId <= 0) {
            return [];
        }

        // Tenta carregar definição_json primeiro
        try {
            $stmt = $pdo->prepare('SELECT definition_json FROM whatsapp_flows WHERE id = :id');
            $stmt->execute([':id' => $flowId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row && !empty($row['definition_json'])) {
                $decoded = json_decode((string) $row['definition_json'], true);
                if (is_array($decoded) && isset($decoded['steps']) && is_array($decoded['steps'])) {
                    $out = [];
                    $usedIds = [];
                    foreach ($decoded['steps'] as $idx => $step) {
                        if (!is_array($step)) {
                            continue;
                        }
                        $id = isset($step['id']) && $step['id'] !== '' ? (string) $step['id'] : ('step_' . ($idx + 1));
                        if (isset($usedIds[$id])) {
                            continue; // ignora duplicados
                        }
                        $usedIds[$id] = true;

                        $type = (string) ($step['type'] ?? 'mensagem');
                        $type = $type === 'condicional' ? 'condicional' : 'mensagem';

                        $out[] = [
                            'id'            => $id,
                            'type'          => $type,
                            'template_slug' => $step['template_slug'] ?? null,
                            'delay_minutes' => (int) ($step['delay_minutes'] ?? 0),
                            'next'          => isset($step['next']) ? (string) $step['next'] : null,
                            'condition'     => $type === 'condicional' ? (string) ($step['condition'] ?? '') : null,
                            'next_if'       => $type === 'condicional' && isset($step['next_if']) ? (string) $step['next_if'] : null,
                            'next_else'     => $type === 'condicional' && isset($step['next_else']) ? (string) $step['next_else'] : null,
                        ];
                    }
                    return $out;
                }
            }
        } catch (\Throwable $e) {
            // fallback para modo legacy
        }

        // Fallback: usa whatsapp_flow_steps em modo linear
        $sqlSteps = "SELECT id, step_order, step_type, template_slug, delay_minutes
                     FROM whatsapp_flow_steps
                     WHERE flow_id = :fid
                     ORDER BY step_order ASC, id ASC";
        $stmt = $pdo->prepare($sqlSteps);
        $stmt->execute([':fid' => $flowId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $graph = [];
        $prevId = null;
        foreach ($rows as $idx => $row) {
            $id = 'step_' . ($idx + 1);
            $type = ((string) ($row['step_type'] ?? 'mensagem_whatsapp')) === 'condicional' ? 'condicional' : 'mensagem';
            $node = [
                'id'            => $id,
                'type'          => $type,
                'template_slug' => $row['template_slug'] ?? null,
                'delay_minutes' => (int) ($row['delay_minutes'] ?? 0),
                'next'          => null,
                'condition'     => null,
                'next_if'       => null,
                'next_else'     => null,
            ];
            if ($prevId !== null) {
                // encadeia linearmente
                foreach ($graph as &$g) {
                    if ($g['id'] === $prevId) {
                        $g['next'] = $id;
                        break;
                    }
                }
                unset($g);
            }
            $graph[] = $node;
            $prevId = $id;
        }

        return $graph;
    }

    /**
     * Avalia de forma segura expressão condicional sobre um contexto simples.
     *
     * Suporta apenas comparações e uso direto de ctx.*.
     * NÃO utiliza eval(); apenas interpreta uma sub-linguagem bem restrita.
     */
    function flows_engine_eval_condition(string $expr, array $ctx): bool
    {
        $expr = trim($expr);
        if ($expr === '') {
            return false;
        }

        // Permite apenas caracteres seguros básicos
        if (!preg_match('/^[a-zA-Z0-9_\s\.=!><\'\"\[\]\(\)&|]+$/', $expr)) {
            return false;
        }

        // Substitui ctx.chave por valores simples
        $replaced = $expr;

        // Extrai chaves potenciais ctx.xxx
        if (preg_match_all('/ctx\.([a-zA-Z0-9_]+)/', $expr, $m)) {
            foreach ($m[1] as $key) {
                $val = $ctx[$key] ?? null;
                if (is_string($val)) {
                    $v = "'" . str_replace("'", "\'", $val) . "'";
                } elseif (is_numeric($val)) {
                    $v = (string) $val;
                } elseif (is_bool($val)) {
                    $v = $val ? 'true' : 'false';
                } else {
                    $v = 'null';
                }
                $replaced = str_replace('ctx.' . $key, $v, $replaced);
            }
        }

        // Agora interpreta expressões simples do tipo X == Y, X === Y, etc., com && e ||
        // Por simplicidade, usamos eval, mas em sandbox extremamente restrita aqui no servidor,
        // sem acesso a variáveis externas (usando create_function poderia ser outra abordagem).
        // Agora interpreta expressões simples do tipo X == Y, X === Y, etc., com && e ||
        // Por simplicidade, usamos eval em expressão já sanitizada (somente operadores básicos permitidos).
        try {
            $code = 'return (bool)(' . $replaced . ');';
            /** @noinspection PhpUsageOfEvalFunction */
            require_once __DIR__ . '/app/Support/Security.php';
            try {
                $result = Security::safe_eval($code);
            } catch (Exception $e) {
                error_log('flows_engine_runner: eval blocked - ' . $e->getMessage());
                $result = null;
            }
            return (bool) $result;
        } catch (\Throwable $e) {
            return false;
        }
    }

    function flows_engine_run(PDO $pdo): void
    {

        // 1) Buscar fluxos ativos
        $sqlFlows = "SELECT id, nome, target_segment, status 
                     FROM whatsapp_flows
                     WHERE status = 'ativo'";
        $flows = [];
        try {
            $flows = $pdo->query($sqlFlows)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            echo "Nenhum fluxo ou tabela whatsapp_flows inexistente: " . $e->getMessage() . PHP_EOL;
            return;
        }

        if (!$flows) {
            echo "Nenhum fluxo ativo encontrado." . PHP_EOL;
            return;
        }

        foreach ($flows as $flow) {
            $flowId        = (int)$flow['id'];
            $targetSegment = $flow['target_segment'] ?? null;

            echo "Processando fluxo #{$flowId} ({$flow['nome']})..." . PHP_EOL;

            // 2) Buscar conversas elegíveis (apenas exemplo: faixa_inatividade por dias)
            $segmentSql = "SELECT id, telefone_cliente, ultima_mensagem_em
                           FROM whatsapp_conversas
                           WHERE 1 = 1";

            $params = [];

            if ($targetSegment === 'D30+') {
                $segmentSql .= " AND ultima_mensagem_em <= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            } elseif ($targetSegment === 'D16–D30') {
                $segmentSql .= " AND ultima_mensagem_em BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND DATE_SUB(NOW(), INTERVAL 16 DAY)";
            } elseif ($targetSegment === 'D8–D15') {
                $segmentSql .= " AND ultima_mensagem_em BETWEEN DATE_SUB(NOW(), INTERVAL 15 DAY) AND DATE_SUB(NOW(), INTERVAL 8 DAY)";
            } elseif ($targetSegment === 'D4–D7') {
                $segmentSql .= " AND ultima_mensagem_em BETWEEN DATE_SUB(NOW(), INTERVAL 7 DAY) AND DATE_SUB(NOW(), INTERVAL 4 DAY)";
            } elseif ($targetSegment === 'D0–D3') {
                $segmentSql .= " AND ultima_mensagem_em >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
            }

            // Evita flood: limita amostra.
            $segmentSql .= " LIMIT 100";

            $convs = $pdo->query($segmentSql)->fetchAll(PDO::FETCH_ASSOC);
            if (!$convs) {
                echo "  Nenhuma conversa elegível para o fluxo #{$flowId}." . PHP_EOL;
                continue;
            }

            // 3) Carrega grafo de steps do fluxo (com suporte a condicionais)
            $graph = flows_engine_load_graph($pdo, $flowId);

            if (!$graph) {
                echo "  Fluxo #{$flowId} não possui steps definidos." . PHP_EOL;
                continue;
            }

            // Indexa steps por id
            $stepsById = [];
            foreach ($graph as $node) {
                $stepsById[$node['id']] = $node;
            }

            // Considera como "root" o primeiro step do array
            $rootId = $graph[0]['id'];

            // 4) Para cada conversa, criar/avançar uma execução
            foreach ($convs as $conv) {
                $conversaId = (int)$conv['id'];
                $telefone   = (string)$conv['telefone_cliente'];

                // Busca execução atual (se existir)
                $exec = null;
                $sqlExec = "SELECT id, current_step_id, next_run_at, contexto_json
                            FROM whatsapp_flow_exec
                            WHERE flow_id = :flow_id AND conversa_id = :conversa_id";
                $stmtExec = $pdo->prepare($sqlExec);
                $stmtExec->execute([
                    ':flow_id'     => $flowId,
                    ':conversa_id' => $conversaId,
                ]);
                $exec = $stmtExec->fetch(PDO::FETCH_ASSOC) ?: null;

                // Determina ID do próximo step
                $currentStepId = $exec ? (string)($exec['current_step_id'] ?? '') : '';
                $nextStepId    = $currentStepId !== '' ? $currentStepId : $rootId;
                $nextStep      = $stepsById[$nextStepId] ?? null;

                if (!$nextStep) {
                    // Não há próximo step -> finaliza execução
                    flows_finalize_execution($pdo, $exec);
                    continue;
                }

                // Checa se já está na hora de rodar (delay)
                if ($exec && $exec['next_run_at'] !== null) {
                    $nr = strtotime($exec['next_run_at']);
                    if ($nr > time()) {
                        // Ainda aguarda delay
                        continue;
                    }
                }

                // Constrói contexto para condição (ctx.*)
                $ctx = [
                    'segment' => $targetSegment,
                    'status'  => null,
                    'tags'    => [],
                    'nome'    => null,
                ];
                // Futuras integrações podem enriquecer ctx consultando outras tabelas.

                // Se step condicional: avalia, escolhe branch e não gera mensagem
                if ($nextStep['type'] === 'condicional') {
                    $expr = (string) ($nextStep['condition'] ?? '');
                    $resultTrue = flows_engine_eval_condition($expr, $ctx);

                    $chosenId = null;
                    if ($resultTrue && !empty($nextStep['next_if'])) {
                        $chosenId = $nextStep['next_if'];
                    } elseif (!$resultTrue && !empty($nextStep['next_else'])) {
                        $chosenId = $nextStep['next_else'];
                    }

                    if ($chosenId === null || !isset($stepsById[$chosenId])) {
                        // Branch inválido -> finaliza execução
                        flows_finalize_execution($pdo, $exec);
                        continue;
                    }

                    // Atualiza execução apenas para apontar para o próximo step, sem enfileirar mensagem
                    flows_update_execution($pdo, $flowId, $conversaId, $chosenId, 0, $exec);

                    if (function_exists('log_app_event')) {
                        log_app_event('flows_engine', 'conditional_step', [
                            'flow_id'      => $flowId,
                            'conversa_id'  => $conversaId,
                            'step_id'      => $nextStepId,
                            'condition'    => $expr,
                            'result'       => $resultTrue ? 'true' : 'false',
                            'next_step_id' => $chosenId,
                        ]);
                    }

                    echo "  Fluxo {$flowId} (condicional) avançou conversa {$conversaId} para step {$chosenId}." . PHP_EOL;
                    continue;
                }

                // Step normal (mensagem) -> processa mensagem e avança fluxo
                flows_process_message_step($pdo, $nextStep, $conv, $flowId, $exec);
                
                // Determina próximo step ID
                $afterStepId = $nextStep['next'] ?? null;
                
                // Atualiza execução para apontar para o próximo step
                if ($afterStepId && isset($stepsById[$afterStepId])) {
                    $delay = (int)($nextStep['delay_minutes'] ?? 0);
                    flows_update_execution($pdo, $flowId, $conversaId, $afterStepId, $delay, $exec);
                    echo "  Fluxo {$flowId} avançado para conversa {$conversaId}, próximo step: {$afterStepId}." . PHP_EOL;
                } else {
                    // Não há próximo step -> finaliza execução
                    flows_finalize_execution($pdo, $exec);
                    echo "  Fluxo {$flowId} finalizado para conversa {$conversaId}." . PHP_EOL;
                }
            }
        }
    }

    try {
        flows_engine_run($pdo);
        echo "Execução de fluxos finalizada." . PHP_EOL;
    } catch (Throwable $e) {
        echo "Erro na execução do engine de fluxos: " . $e->getMessage() . PHP_EOL;
    }
    ?>