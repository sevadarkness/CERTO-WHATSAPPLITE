-- Tabela para controle de disparos de remarketing
CREATE TABLE IF NOT EXISTS remarketing_disparos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id BIGINT UNSIGNED NULL,
    telefone VARCHAR(32) NOT NULL,
    mensagem TEXT NOT NULL,
    status ENUM('pendente', 'enviado', 'falha') NOT NULL DEFAULT 'pendente',
    erro TEXT NULL,
    agendado_para DATETIME NULL,
    enviado_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_agendado (status, agendado_para),
    INDEX idx_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
