-- Adiciona coluna para controlar se lembrete foi enviado
SET @db := DATABASE();

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=@db AND TABLE_NAME='agenda_compromissos' AND COLUMN_NAME='lembrete_enviado');
SET @sql := IF(@col=0, 'ALTER TABLE agenda_compromissos ADD COLUMN lembrete_enviado TINYINT(1) NOT NULL DEFAULT 0 AFTER status', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Índice para busca eficiente de lembretes pendentes
SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA=@db AND TABLE_NAME='agenda_compromissos' AND INDEX_NAME='idx_lembrete_pendente');
SET @sql := IF(@idx=0, 'CREATE INDEX idx_lembrete_pendente ON agenda_compromissos(status, lembrete_enviado, data_hora_inicio)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
