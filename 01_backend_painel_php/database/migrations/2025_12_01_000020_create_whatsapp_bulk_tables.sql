-- Tabelas para envio em massa de WhatsApp (Rede Alabama)
-- Criadas para suportar campanhas em lote com agendamento, simulação e reprocessamento de falhas.

CREATE TABLE IF NOT EXISTS whatsapp_bulk_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    nome_campanha VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    media_url VARCHAR(512) DEFAULT NULL,
    status ENUM('queued','running','paused','finished','failed') NOT NULL DEFAULT 'queued',
    total_destinatarios INT UNSIGNED NOT NULL DEFAULT 0,
    enviados_sucesso INT UNSIGNED NOT NULL DEFAULT 0,
    enviados_falha INT UNSIGNED NOT NULL DEFAULT 0,
    agendado_para DATETIME NULL,
    iniciado_em DATETIME NULL,
    finalizado_em DATETIME NULL,
    min_delay_ms INT UNSIGNED NOT NULL DEFAULT 3000,
    max_delay_ms INT UNSIGNED NOT NULL DEFAULT 7000,
    is_simulation TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_agenda (status, agendado_para),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_bulk_job_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bulk_job_id BIGINT UNSIGNED NOT NULL,
    telefone_raw VARCHAR(64) NOT NULL,
    telefone_normalizado VARCHAR(64) NOT NULL,
    to_e164 VARCHAR(32) NOT NULL,
    status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    last_error TEXT NULL,
    tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
    enviado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_whatsapp_bulk_items_job
        FOREIGN KEY (bulk_job_id) REFERENCES whatsapp_bulk_jobs(id)
        ON DELETE CASCADE,
    INDEX idx_job_status (bulk_job_id, status),
    INDEX idx_to_e164 (to_e164)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
