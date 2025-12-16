-- V102 - Módulos de Vendas com IA
-- IA Vendedora PRO, Campanhas de Recuperação e Vendedor Copiloto

CREATE TABLE IF NOT EXISTS sales_offer_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    nome_combo VARCHAR(180) NOT NULL,
    descricao TEXT NULL,
    produtos_json JSON NOT NULL,
    desconto_max_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    lucro_min_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_sales_offer_templates_tenant (tenant_id),
    INDEX idx_sales_offer_templates_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_ai_offers_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    vendedor_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NULL,
    thread_id VARCHAR(190) NOT NULL,
    proposta_json JSON NOT NULL,
    origem VARCHAR(50) NOT NULL DEFAULT 'ia',
    aceita_pelo_vendedor TINYINT(1) NOT NULL DEFAULT 0,
    cliente_aceitou TINYINT(1) NULL,
    ticket_gerado DECIMAL(12,2) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_sales_ai_offers_tenant (tenant_id),
    INDEX idx_sales_ai_offers_vendedor (vendedor_id),
    INDEX idx_sales_ai_offers_cliente (cliente_id),
    INDEX idx_sales_ai_offers_thread (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_recovery_campaigns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(190) NOT NULL,
    tipo_segmento VARCHAR(50) NOT NULL,
    dias_inatividade INT UNSIGNED NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    janela_envio_hora_inicial TINYINT UNSIGNED NOT NULL DEFAULT 9,
    janela_envio_hora_final   TINYINT UNSIGNED NOT NULL DEFAULT 21,
    limite_envios_dia_por_tenant INT UNSIGNED NOT NULL DEFAULT 500,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_sales_recovery_campaigns_tenant (tenant_id),
    INDEX idx_sales_recovery_campaigns_tipo (tipo_segmento),
    INDEX idx_sales_recovery_campaigns_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_recovery_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    campaign_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    thread_id VARCHAR(190) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pendente',
    proxima_execucao_at DATETIME NULL,
    ultima_execucao_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_sales_recovery_enroll_tenant (tenant_id),
    INDEX idx_sales_recovery_enroll_campaign (campaign_id),
    INDEX idx_sales_recovery_enroll_cliente (cliente_id),
    INDEX idx_sales_recovery_enroll_status (status),
    INDEX idx_sales_recovery_enroll_next (proxima_execucao_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_objection_library (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(60) NOT NULL,
    titulo VARCHAR(190) NOT NULL,
    descricao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uq_sales_objection_codigo_tenant (tenant_id, codigo),
    INDEX idx_sales_objection_tenant (tenant_id),
    INDEX idx_sales_objection_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_objection_ai_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    vendedor_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NULL,
    thread_id VARCHAR(190) NOT NULL,
    objection_codigo VARCHAR(60) NOT NULL,
    prompt_usado TEXT NOT NULL,
    resposta_ia TEXT NOT NULL,
    vendedor_editou TINYINT(1) NOT NULL DEFAULT 0,
    cliente_aceitou TINYINT(1) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_sales_objection_log_tenant (tenant_id),
    INDEX idx_sales_objection_log_vendedor (vendedor_id),
    INDEX idx_sales_objection_log_cliente (cliente_id),
    INDEX idx_sales_objection_log_codigo (objection_codigo),
    INDEX idx_sales_objection_log_thread (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
