-- Migration: criação/ajuste da tabela usuarios e logs básicos.

CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  nivel ENUM('admin','vendedor','suporte') DEFAULT 'vendedor',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  senha_hash VARCHAR(255) NULL,
  reset_token VARCHAR(255) NULL,
  status ENUM('ativo','inativo') DEFAULT 'ativo',
  ultimo_login DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_email VARCHAR(100),
  acao TEXT,
  momento DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Seed removido: em produção, crie o usuário administrador manualmente (README) ou via painel_admin.php.
