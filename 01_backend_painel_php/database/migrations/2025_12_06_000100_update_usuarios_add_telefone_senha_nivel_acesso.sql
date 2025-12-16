-- Ajuste tabela usuarios para login por telefone (V14 Ultra)
--
-- Observação importante:
-- Evita sintaxe "ADD COLUMN IF NOT EXISTS" para compatibilidade com MySQL/MariaDB
-- mais antigos, usando checagem via INFORMATION_SCHEMA + PREPARE.

SET @db := DATABASE();

-- Adiciona telefone (se não existir)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=@db AND TABLE_NAME='usuarios' AND COLUMN_NAME='telefone');
SET @sql := IF(@col=0, 'ALTER TABLE usuarios ADD COLUMN telefone VARCHAR(20) NULL AFTER nome', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Adiciona senha (se não existir)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=@db AND TABLE_NAME='usuarios' AND COLUMN_NAME='senha');
SET @sql := IF(@col=0, 'ALTER TABLE usuarios ADD COLUMN senha VARCHAR(255) NULL AFTER telefone', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Adiciona nivel_acesso (se não existir)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=@db AND TABLE_NAME='usuarios' AND COLUMN_NAME='nivel_acesso');
SET @sql := IF(@col=0, 'ALTER TABLE usuarios ADD COLUMN nivel_acesso VARCHAR(50) NULL AFTER senha', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill: se existia senha_hash, copia para senha
UPDATE usuarios
SET senha = senha_hash
WHERE (senha IS NULL OR senha = '') AND senha_hash IS NOT NULL AND senha_hash <> '';

-- Backfill: mapeia "nivel" legado -> nivel_acesso
UPDATE usuarios
SET nivel_acesso = CASE
    WHEN nivel IN ('admin', 'Administrador') THEN 'Administrador'
    WHEN nivel IN ('suporte', 'Suporte') THEN 'Suporte'
    ELSE 'Vendedor'
END
WHERE (nivel_acesso IS NULL OR nivel_acesso = '') AND nivel IS NOT NULL;
