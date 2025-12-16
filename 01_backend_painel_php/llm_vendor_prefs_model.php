<?php
declare(strict_types=1);

/**
 * Preferências de IA por usuário/vendedor (V16 Ultra).
 *
 * Tabela sugerida (ver migration V16):
 *
 *  llm_vendor_prefs:
 *      id INT AUTO_INCREMENT PRIMARY KEY
 *      usuario_id INT NOT NULL
 *      preferred_tone VARCHAR(32) NULL
 *      preferred_template_slug VARCHAR(64) NULL
 *      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
 *      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';

/**
 * Retorna as preferências de IA do usuário informado.
 *
 * @return array{preferred_tone:?string,preferred_template_slug:?string}
 */
function alabama_llm_get_vendor_prefs(PDO $pdo, int $usuarioId): array
{
    try {
        $sql = "SELECT preferred_tone, preferred_template_slug
                  FROM llm_vendor_prefs
                 WHERE usuario_id = :uid
                 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $usuarioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'preferred_tone'          => null,
                'preferred_template_slug' => null,
            ];
        }

        return [
            'preferred_tone'          => $row['preferred_tone'] !== null ? (string)$row['preferred_tone'] : null,
            'preferred_template_slug' => $row['preferred_template_slug'] !== null ? (string)$row['preferred_template_slug'] : null,
        ];
    } catch (Throwable $e) {
        if (function_exists('log_app_event')) {
            log_app_event('llm_vendor_prefs', 'get_error', [
                'usuario_id' => $usuarioId,
                'error'      => $e->getMessage(),
            ]);
        }
        return [
            'preferred_tone'          => null,
            'preferred_template_slug' => null,
        ];
    }
}

/**
 * Salva (upsert) preferências de IA do usuário.
 */
function alabama_llm_save_vendor_prefs(PDO $pdo, int $usuarioId, ?string $tone, ?string $templateSlug): void
{
    try {
        $sql = "INSERT INTO llm_vendor_prefs (usuario_id, preferred_tone, preferred_template_slug)
                     VALUES (:uid, :tone, :tpl)
                ON DUPLICATE KEY UPDATE
                    preferred_tone = VALUES(preferred_tone),
                    preferred_template_slug = VALUES(preferred_template_slug),
                    updated_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid'  => $usuarioId,
            ':tone' => $tone,
            ':tpl'  => $templateSlug,
        ]);

        if (function_exists('log_app_event')) {
            log_app_event('llm_vendor_prefs', 'save', [
                'usuario_id' => $usuarioId,
                'tone'       => $tone,
                'template'   => $templateSlug,
            ]);
        }
    } catch (Throwable $e) {
        if (function_exists('log_app_event')) {
            log_app_event('llm_vendor_prefs', 'save_error', [
                'usuario_id' => $usuarioId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
