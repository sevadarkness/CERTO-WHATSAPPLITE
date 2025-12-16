<?php
declare(strict_types=1);

/**
 * Gestão de templates LLM genéricos (V16 Ultra).
 *
 * Tabelas de apoio sugeridas em rede_alabama_migration_v16_llm_templates_flows.sql:
 *
 *  llm_templates:
 *      id INT AUTO_INCREMENT PRIMARY KEY
 *      context VARCHAR(64) NOT NULL        -- ex.: 'admin_assistant', 'whatsapp_ai'
 *      slug VARCHAR(64) NOT NULL           -- ex.: 'whatsapp_cobranca'
 *      label VARCHAR(255) NOT NULL         -- ex.: 'Cobrança amigável'
 *      description TEXT NULL
 *      body TEXT NULL                      -- instruções adicionais, texto base etc.
 *      is_active TINYINT(1) NOT NULL DEFAULT 1
 *      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
 *      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 *
 * A ideia é permitir gerenciamento via painel sem quebrar o fallback em código.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

/**
 * Carrega templates de um contexto específico.
 *
 * @return array<string,array> Array associativo slug => row
 */
function alabama_llm_templates_fetch(PDO $pdo, string $context): array
{
    try {
        $sql = "SELECT context, slug, label, description, body, is_active
                  FROM llm_templates
                 WHERE context = :context
                 ORDER BY label ASC, slug ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':context' => $context]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Tabela pode não existir ainda – não quebra o restante do painel.
        if (function_exists('log_app_event')) {
            log_app_event('llm_templates', 'fetch_error', [
                'context' => $context,
                'error'   => $e->getMessage(),
            ]);
        }
        return [];
    }

    $bySlug = [];
    foreach ($rows as $row) {
        $slug = (string)($row['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $bySlug[$slug] = $row;
    }

    return $bySlug;
}

/**
 * Retorna templates já mesclados com um fallback estático.
 *
 * Se existirem templates ativos no banco para o contexto informado,
 * eles são retornados no lugar do fallback. Se não houver, volta o fallback.
 *
 * @param array<string,string> $fallback slug => descrição
 * @return array<string,string>
 */
function alabama_llm_templates_get(PDO $pdo, string $context, array $fallback): array
{
    $rows = alabama_llm_templates_fetch($pdo, $context);
    if (!$rows) {
        return $fallback;
    }

    $result = [];
    foreach ($rows as $slug => $row) {
        if (isset($row['is_active']) && (int)$row['is_active'] === 0) {
            continue;
        }
        $label = (string)($row['label'] ?? $slug);
        $desc  = (string)($row['description'] ?? '');
        $text  = $label;
        if ($desc !== '') {
            $text .= ' – ' . $desc;
        }
        $result[$slug] = $text;
    }

    return $result ?: $fallback;
}

/**
 * Utilitário para listar todos os contexts/slug disponíveis (útil para dashboards).
 *
 * @return array<int,array{context:string,slug:string,label:string,is_active:int}>
 */
function alabama_llm_templates_list_all(PDO $pdo): array
{
    try {
        $sql = "SELECT context, slug, label, is_active
                  FROM llm_templates
              ORDER BY context ASC, label ASC, slug ASC";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static function (array $row): array {
            return [
                'context'   => (string)($row['context'] ?? ''),
                'slug'      => (string)($row['slug'] ?? ''),
                'label'     => (string)($row['label'] ?? ''),
                'is_active' => (int)($row['is_active'] ?? 0),
            ];
        }, $rows);
    } catch (Throwable $e) {
        if (function_exists('log_app_event')) {
            log_app_event('llm_templates', 'list_all_error', [
                'error' => $e->getMessage(),
            ]);
        }
        return [];
    }
}
