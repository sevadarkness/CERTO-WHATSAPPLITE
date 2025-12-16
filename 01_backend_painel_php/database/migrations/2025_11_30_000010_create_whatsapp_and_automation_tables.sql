-- Tabelas de WhatsApp, fluxos versionados e automação
-- IMPORTANTE: ajuste conforme o schema real do seu ambiente de produção.

-- Mensagens de WhatsApp (histórico básico)
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id VARCHAR(64) NOT NULL,
    contato_nome VARCHAR(255) NULL,
    contato_telefone VARCHAR(32) NOT NULL,
    direction ENUM('in', 'out') NOT NULL,
    conteudo TEXT NOT NULL,
    canal VARCHAR(32) NOT NULL DEFAULT 'whatsapp',
    vendedor_id INT NULL,
    enviado_por_ia TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_thread_id (thread_id),
    INDEX idx_vendedor_id_created_at (vendedor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fluxos de WhatsApp
CREATE TABLE IF NOT EXISTS whatsapp_flows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Versionamento de fluxos
CREATE TABLE IF NOT EXISTS whatsapp_flow_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flow_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    conteudo_json LONGTEXT NOT NULL,
    criado_por INT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_flow_id_version (flow_id, version),
    CONSTRAINT fk_flow_versions_flow
        FOREIGN KEY (flow_id) REFERENCES whatsapp_flows(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Regras de automação
CREATE TABLE IF NOT EXISTS automation_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    gatilho_tipo VARCHAR(64) NOT NULL,           -- ex.: "mensagem_recebida", "dia_da_semana"
    gatilho_config_json LONGTEXT NOT NULL,       -- JSON com parâmetros do gatilho
    acao_tipo VARCHAR(64) NOT NULL,              -- ex.: "enviar_mensagem", "criar_tarefa"
    acao_config_json LONGTEXT NOT NULL,          -- JSON com payload da ação
    prioridade INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_ativo_prioridade (ativo, prioridade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Eventos de automação (fila/event log)
CREATE TABLE IF NOT EXISTS automation_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_id INT UNSIGNED NULL,
    event_tipo VARCHAR(64) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    status ENUM('pendente', 'processando', 'concluido', 'erro') NOT NULL DEFAULT 'pendente',
    tentativa INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    scheduled_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_status_scheduled_at (status, scheduled_at),
    INDEX idx_rule_id (rule_id),
    CONSTRAINT fk_automation_events_rule
        FOREIGN KEY (rule_id) REFERENCES automation_rules(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preferências de IA por vendedor
CREATE TABLE IF NOT EXISTS llm_vendor_prefs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendedor_id INT NOT NULL,
    preferred_tone VARCHAR(64) NULL,
    preferred_template_slug VARCHAR(128) NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_vendor (vendedor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
