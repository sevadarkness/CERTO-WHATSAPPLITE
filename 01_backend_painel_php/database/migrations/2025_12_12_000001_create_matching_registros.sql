-- 2025_12_12_000001_create_matching_registros.sql
--
-- Tabela usada pelo módulo de Matching (API v2) para registrar execuções.
-- Criada de forma idempotente para facilitar deploy/atualizações.

CREATE TABLE IF NOT EXISTS matching_registros (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    cliente_telefone VARCHAR(32) NULL,
    best_vendor_id BIGINT UNSIGNED NULL,
    strategy VARCHAR(64) NULL,
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_matching_usuario_id (usuario_id),
    KEY idx_matching_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
