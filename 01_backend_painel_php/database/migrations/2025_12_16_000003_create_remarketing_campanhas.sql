-- Migration: Create remarketing_campanhas table
-- Purpose: Store remarketing campaigns in MySQL instead of file system
-- Date: 2025-12-16

CREATE TABLE IF NOT EXISTS remarketing_campanhas (
    id VARCHAR(32) PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    config_json TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    INDEX idx_ativo (ativo),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
