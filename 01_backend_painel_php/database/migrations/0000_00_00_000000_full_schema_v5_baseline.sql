-- FULL SCHEMA BASELINE V5/V6 (opcional)
-- Use apenas em bancos NOVOS.

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  -- IMPORTANTE (deploy): o painel atual cria usuários por telefone + senha.
  -- Portanto, e-mail deve ser opcional para não quebrar a criação via UI.
  telefone VARCHAR(30) NULL UNIQUE,
  email VARCHAR(150) NULL UNIQUE,
  nivel VARCHAR(50) DEFAULT 'vendedor',
  -- Campos atuais do painel (RBAC usa nivel_acesso).
  nivel_acesso VARCHAR(50) NULL,
  senha_hash VARCHAR(255) NULL,
  senha VARCHAR(255) NULL,
  reset_token VARCHAR(255) NULL,
  token_expira BIGINT NULL,
  status VARCHAR(20) DEFAULT 'ativo',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  ultimo_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_email VARCHAR(150),
  acao TEXT,
  momento DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS llm_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  contexto VARCHAR(100) NOT NULL,
  modelo VARCHAR(100) NOT NULL,
  prompt_tokens INT DEFAULT 0,
  completion_tokens INT DEFAULT 0,
  custo NUMERIC(10,4) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  meta_json JSON NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_flows (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  tenant_id INT DEFAULT 1,
  ativo TINYINT(1) DEFAULT 1,
  definition_json JSON NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_flow_queue (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  flow_id BIGINT NOT NULL,
  telefone VARCHAR(30) NOT NULL,
  payload_json JSON NOT NULL,
  status ENUM('pending','sent','error') DEFAULT 'pending',
  error_message TEXT NULL,
  tentativas INT DEFAULT 0,
  scheduled_for DATETIME DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_flow_status (flow_id, status),
  INDEX idx_scheduled (scheduled_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_bulk_jobs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nome_campanha VARCHAR(150) NOT NULL,
  tenant_id INT DEFAULT 1,
  status ENUM('pending','running','finished','error') DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS whatsapp_bulk_job_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT NOT NULL,
  telefone VARCHAR(30) NOT NULL,
  status ENUM('pending','sent','error') DEFAULT 'pending',
  error_message TEXT NULL,
  last_attempt_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_job_status (job_id, status),
  INDEX idx_tel (telefone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS automation_rules (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  tenant_id INT DEFAULT 1,
  event_key VARCHAR(100) NOT NULL,
  condicoes_json JSON NOT NULL,
  acoes_json JSON NOT NULL,
  ativo TINYINT(1) DEFAULT 1,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS automation_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_key VARCHAR(100) NOT NULL,
  tenant_id INT DEFAULT 1,
  payload_json JSON NOT NULL,
  status ENUM('pending','processing','done','error') DEFAULT 'pending',
  error_message TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_event (event_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs_agendados (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(100) NOT NULL,
  tenant_id INT DEFAULT 1,
  payload JSON NULL,
  status ENUM('pending','running','done','error') DEFAULT 'pending',
  error_message TEXT NULL,
  scheduled_for DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_run_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tipo_status (tipo, status),
  INDEX idx_scheduled_for (scheduled_for)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
