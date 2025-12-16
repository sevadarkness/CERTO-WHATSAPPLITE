-- 2025_12_12_000002_create_smoke_test_tables.sql
--
-- Objetivo:
-- - Garantir a existência das tabelas verificadas em tests/smoke_tests.php
-- - Tornar o deploy/migrate.php mais previsível, inclusive quando a baseline
--   foi aplicada com esquemas legados/alternativos.
--
-- Observação:
-- Este arquivo foi escrito para rodar via PDO->exec() (multi-statements)
-- sem uso de DELIMITER.

/* ==============================
   Estoque (painel)
   ============================== */
CREATE TABLE IF NOT EXISTS estoque_vendedores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  produto_id BIGINT UNSIGNED NOT NULL,
  sabor_id BIGINT UNSIGNED NOT NULL,
  vendedor_id BIGINT UNSIGNED NOT NULL,
  quantidade INT NOT NULL DEFAULT 0,
  updated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_estoque (produto_id, sabor_id, vendedor_id),
  KEY idx_vendedor (vendedor_id),
  KEY idx_produto (produto_id),
  KEY idx_sabor (sabor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ==============================
   Matching
   ============================== */
-- matching_registros é criado na migration 2025_12_12_000001_create_matching_registros.sql

/* ==============================
   WhatsApp Bot (tabelas PT-BR usadas pelo painel)
   ============================== */
CREATE TABLE IF NOT EXISTS whatsapp_bot_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  phone_number_id VARCHAR(64) NULL,
  verify_token VARCHAR(255) NULL,
  meta_access_token TEXT NULL,
  llm_provider VARCHAR(64) NULL,
  llm_model VARCHAR(128) NULL,
  llm_temperature DECIMAL(4,2) NULL,
  llm_max_tokens INT NULL,
  llm_system_prompt TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_conversas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  telefone_cliente VARCHAR(64) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'ativa',
  ultima_mensagem_em DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tel (telefone_cliente),
  KEY idx_status (status),
  KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_mensagens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversa_id BIGINT UNSIGNED NOT NULL,
  direction ENUM('in','out') NOT NULL,
  author VARCHAR(32) NOT NULL DEFAULT 'user',
  conteudo TEXT NOT NULL,
  raw_payload LONGTEXT NULL,
  llm_model VARCHAR(128) NULL,
  llm_tokens_total INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conversa (conversa_id),
  KEY idx_direction (direction),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ==============================
   LLM logs (tabela usada por llm_analytics_dashboard + exports)
   ============================== */
CREATE TABLE IF NOT EXISTS llm_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider VARCHAR(64) NULL,
  model VARCHAR(128) NULL,
  prompt_tokens INT NULL,
  completion_tokens INT NULL,
  total_tokens INT NULL,
  latency_ms INT NULL,
  request_id VARCHAR(128) NULL,
  meta_json LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created (created_at),
  KEY idx_provider_model (provider, model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ==============================
   Jobs (V10 / Supremacy) - schema compatível com jobs_runner.php
   ============================== */
CREATE TABLE IF NOT EXISTS jobs_agendados (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo VARCHAR(100) NOT NULL,
  payload JSON NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pendente',
  tentativas INT NOT NULL DEFAULT 0,
  max_tentativas INT NOT NULL DEFAULT 3,
  agendado_para DATETIME NULL,
  executado_em DATETIME NULL,
  erro_ultimo TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_status_agendado (status, agendado_para),
  KEY idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(100) NOT NULL,
  status VARCHAR(32) NOT NULL,
  mensagem TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_job (job_id),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ==============================
   WhatsApp atendimentos (handover bot/humano)
   ============================== */
CREATE TABLE IF NOT EXISTS whatsapp_atendimentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversa_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NULL,
  modo VARCHAR(16) NOT NULL DEFAULT 'bot',
  status VARCHAR(16) NOT NULL DEFAULT 'aberto',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  encerrado_em DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_conversa_status (conversa_id, status),
  KEY idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ==============================
   LLM training hub (dataset)
   ============================== */
CREATE TABLE IF NOT EXISTS llm_training_samples (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fonte VARCHAR(32) NOT NULL DEFAULT 'manual',
  conversa_id BIGINT UNSIGNED NULL,
  mensagem_usuario TEXT NOT NULL,
  resposta_bot TEXT NULL,
  resposta_ajustada TEXT NULL,
  aprovado TINYINT(1) NOT NULL DEFAULT 0,
  marcado_por_id BIGINT UNSIGNED NULL,
  tags TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_created (created_at),
  KEY idx_aprovado (aprovado),
  KEY idx_conversa (conversa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ==============================
   Compatibilidade/Correções: jobs_agendados legado (baseline)
   ============================== */
-- Se a baseline criou jobs_agendados com schema legado (pending/done/error),
-- ajustamos para aceitar os valores usados no código (pendente/concluido/falhou)
-- e adicionamos as colunas que o jobs_runner.php e repos exigem.

SET @db := DATABASE();

-- 1) Garantir colunas necessárias
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jobs_agendados' AND COLUMN_NAME='tentativas');
SET @sql := IF(@col=0, 'ALTER TABLE jobs_agendados ADD COLUMN tentativas INT NOT NULL DEFAULT 0', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jobs_agendados' AND COLUMN_NAME='max_tentativas');
SET @sql := IF(@col=0, 'ALTER TABLE jobs_agendados ADD COLUMN max_tentativas INT NOT NULL DEFAULT 3', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jobs_agendados' AND COLUMN_NAME='agendado_para');
SET @sql := IF(@col=0, 'ALTER TABLE jobs_agendados ADD COLUMN agendado_para DATETIME NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jobs_agendados' AND COLUMN_NAME='executado_em');
SET @sql := IF(@col=0, 'ALTER TABLE jobs_agendados ADD COLUMN executado_em DATETIME NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jobs_agendados' AND COLUMN_NAME='erro_ultimo');
SET @sql := IF(@col=0, 'ALTER TABLE jobs_agendados ADD COLUMN erro_ultimo TEXT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Expandir enum/status (se existir como ENUM) e normalizar valores
-- Se status não for ENUM (ex.: VARCHAR), o MODIFY pode falhar. Nesse caso,
-- tentamos apenas garantir que seja VARCHAR(32).

SET @is_enum := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jobs_agendados' AND COLUMN_NAME='status'
                   AND DATA_TYPE='enum');

SET @sql := IF(
  @is_enum>0,
  "ALTER TABLE jobs_agendados MODIFY COLUMN status ENUM('pending','running','done','error','pendente','concluido','falhou') NOT NULL DEFAULT 'pendente'",
  "ALTER TABLE jobs_agendados MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pendente'"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Migração best-effort de dados antigos -> novos
-- scheduled_for -> agendado_para
SET @has_scheduled_for := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jobs_agendados' AND COLUMN_NAME='scheduled_for');
SET @sql := IF(@has_scheduled_for>0, 'UPDATE jobs_agendados SET agendado_para = scheduled_for WHERE agendado_para IS NULL AND scheduled_for IS NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- last_run_at -> executado_em
SET @has_last_run_at := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jobs_agendados' AND COLUMN_NAME='last_run_at');
SET @sql := IF(@has_last_run_at>0, 'UPDATE jobs_agendados SET executado_em = last_run_at WHERE executado_em IS NULL AND last_run_at IS NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- error_message -> erro_ultimo
SET @has_error_message := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='jobs_agendados' AND COLUMN_NAME='error_message');
SET @sql := IF(@has_error_message>0, 'UPDATE jobs_agendados SET erro_ultimo = error_message WHERE erro_ultimo IS NULL AND error_message IS NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- status mapping
UPDATE jobs_agendados
SET status = CASE
  WHEN status = 'pending' THEN 'pendente'
  WHEN status = 'done' THEN 'concluido'
  WHEN status = 'error' THEN 'falhou'
  ELSE status
END
WHERE status IN ('pending','done','error');

