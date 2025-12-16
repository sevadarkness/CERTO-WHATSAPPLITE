<?php
declare(strict_types=1);

/**
 * rule_engine_simple.php
 *
 * Engine simples de regras baseado em JSON declarativo.
 *
 * Estrutura básica em automation_rules.conditions_json:
 *
 * [
 *   {
 *     "field": "event.payload.segmento",
 *     "op": "equals",
 *     "value": "D0_D7"
 *   }
 * ]
 */

require_once __DIR__ . '/logger.php';

/**
 * Resolve um caminho do tipo "event.payload.x.y" dentro de um array.
 *
 * @param array<string,mixed> $context
 * @param string              $path
 * @return mixed
 */
function rule_engine_simple_get_field(array $context, string $path)
{
    $parts = explode('.', $path);
    $value = $context;

    foreach ($parts as $p) {
        if ($p === '') {
            continue;
        }
        if (!is_array($value) || !array_key_exists($p, $value)) {
            return null;
        }
        $value = $value[$p];
    }

    return $value;
}

/**
 * Funções avançadas de avaliação de regras.
 */

/**
 * Avalia um grupo de condições com suporte a operadores lógicos (AND/OR) e modificadores.
 *
 * Estrutura esperada:
 * {
 *   "logical": "AND" | "OR",
 *   "conditions": [
 *      { "field": "...", "op": "equals", "value": "..." },
 *      { "logical": "OR", "conditions": [ ... ] }
 *   ]
 * }
 *
 * @param array<string,mixed> $group
 * @param array<string,mixed> $eventContext
 * @param array<string,mixed> $rule
 */
function rule_engine_simple_match_group(array $group, array $eventContext, array $rule): bool
{
    $logical = strtoupper((string) ($group['logical'] ?? 'AND'));
    $conditions = $group['conditions'] ?? [];
    if (!is_array($conditions)) {
        return true;
    }

    $defaultResult = $logical === 'OR' ? false : true;

    foreach ($conditions as $c) {
        if (isset($c['conditions']) && is_array($c['conditions'])) {
            $result = rule_engine_simple_match_group($c, $eventContext, $rule);
        } else {
            $field = (string) ($c['field'] ?? '');
            $op    = strtolower((string) ($c['op'] ?? 'equals'));
            $value = $c['value'] ?? null;
            $mod   = isset($c['modifier']) ? (string) $c['modifier'] : null;

            if ($field === '') {
                continue;
            }

            $left = rule_engine_simple_get_field($eventContext, $field);

            // Aplicação opcional de modificadores (normalização)
            if ($mod === 'lowercase') {
                $left  = is_string($left) ? mb_strtolower($left) : $left;
                $value = is_string($value) ? mb_strtolower($value) : $value;
            } elseif ($mod === 'uppercase') {
                $left  = is_string($left) ? mb_strtoupper($left) : $left;
                $value = is_string($value) ? mb_strtoupper($value) : $value;
            } elseif ($mod === 'trim') {
                $left  = is_string($left) ? trim($left) : $left;
                $value = is_string($value) ? trim($value) : $value;
            }

            $result = rule_engine_simple_evaluate_condition($rule, $op, $left, $value);
        }

        if ($logical === 'AND' && !$result) {
            return false;
        }
        if ($logical === 'OR' && $result) {
            return true;
        }
    }

    return $defaultResult;
}

/**
 * Avalia uma única condição básica.
 *
 * @param array<string,mixed> $rule
 * @param string $op
 * @param mixed $left
 * @param mixed $value
 */
function rule_engine_simple_evaluate_condition(array $rule, string $op, $left, $value): bool
{
    switch ($op) {
        case 'equals':
            return $left == $value;

        case 'not_equals':
            return $left != $value;

        case 'contains':
            $leftStr  = is_scalar($left) ? (string) $left : '';
            $valueStr = is_scalar($value) ? (string) $value : '';
            if ($valueStr === '') {
                return true;
            }
            return mb_stripos($leftStr, $valueStr) !== false;

        case 'not_contains':
            $leftStr  = is_scalar($left) ? (string) $left : '';
            $valueStr = is_scalar($value) ? (string) $value : '';
            if ($valueStr === '') {
                return true;
            }
            return mb_stripos($leftStr, $valueStr) === false;

        case 'greater_than':
            if (!is_numeric($left) || !is_numeric($value)) {
                return false;
            }
            return (float) $left > (float) $value;

        case 'less_than':
            if (!is_numeric($left) || !is_numeric($value)) {
                return false;
            }
            return (float) $left < (float) $value;

        case 'in':
            if (!is_array($value)) {
                return false;
            }
            return in_array($left, $value, true);

        case 'not_in':
            if (!is_array($value)) {
                return false;
            }
            return !in_array($left, $value, true);

        default:
            log_app_error('rule_engine_simple', 'unknown_operator', [
                'rule_id'  => $rule['id'] ?? null,
                'operator' => $op,
            ]);
            return false;
    }
}


/**
 * Avalia uma lista de condições contra um evento.
 *
 * @param array<string,mixed> $rule
 * @param array<string,mixed> $eventContext
 */
function rule_engine_simple_matches(array $rule, array $eventContext): bool
{
    $raw = $rule['conditions_json'] ?? null;
    if ($raw === null || $raw === '') {
        // sem condições = sempre verdadeiro
        return true;
    }

    $conds = json_decode((string) $raw, true);
    if (!is_array($conds)) {
        log_app_error('rule_engine_simple', 'invalid_conditions_json', [
            'rule_id'   => $rule['id'] ?? null,
            'raw_value' => $raw,
        ]);
        return false;
    }

    // Suporte a estrutura avançada: { logical, conditions: [...] }
    if (isset($conds['logical']) && isset($conds['conditions']) && is_array($conds['conditions'])) {
        return rule_engine_simple_match_group($conds, $eventContext, $rule);
    }
    // Fora do modo avançado, esperamos uma lista de condições.
    $isList = function_exists('array_is_list')
        ? array_is_list($conds)
        : (array_keys($conds) === range(0, count($conds) - 1));

    if (!$isList) {
        // Permite um único objeto-condição: {"field": "...", "op": "...", "value": ...}
        if (isset($conds['field'])) {
            $conds = [$conds];
        } else {
            log_app_error('rule_engine_simple', 'invalid_conditions_json', [
                'rule_id'   => $rule['id'] ?? null,
                'raw_value' => $raw,
            ]);
            return false;
        }
    }

    foreach ($conds as $c) {
        if (!is_array($c)) {
            continue;
        }

        // Permite grupos aninhados dentro de uma lista (tratando como AND)
        if (isset($c['conditions']) && is_array($c['conditions'])) {
            if (!rule_engine_simple_match_group($c, $eventContext, $rule)) {
                return false;
            }
            continue;
        }

        $field = (string) ($c['field'] ?? '');
        $op    = strtolower((string) ($c['op'] ?? 'equals'));
        $value = $c['value'] ?? null;
        $mod   = isset($c['modifier']) ? (string) $c['modifier'] : null;

        if ($field === '') {
            continue;
        }

        $left = rule_engine_simple_get_field($eventContext, $field);

        // Aplicação opcional de modificadores (normalização)
        if ($mod === 'lowercase') {
            $left  = is_string($left) ? mb_strtolower($left) : $left;
            $value = is_string($value) ? mb_strtolower($value) : $value;
        } elseif ($mod === 'uppercase') {
            $left  = is_string($left) ? mb_strtoupper($left) : $left;
            $value = is_string($value) ? mb_strtoupper($value) : $value;
        } elseif ($mod === 'trim') {
            $left  = is_string($left) ? trim($left) : $left;
            $value = is_string($value) ? trim($value) : $value;
        }

        // AND implícito: se qualquer condição falhar, regra não casa
        if (!rule_engine_simple_evaluate_condition($rule, $op, $left, $value)) {
            return false;
        }
    }

    return true;
}
