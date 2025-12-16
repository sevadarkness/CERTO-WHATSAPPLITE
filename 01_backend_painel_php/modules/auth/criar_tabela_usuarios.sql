
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100),
  email VARCHAR(100) UNIQUE,
  nivel ENUM('admin','vendedor','suporte') DEFAULT 'vendedor',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO usuarios (nome, email, nivel) VALUES
('Admin Master', 'admin@redealabama.com', 'admin'),
('Vendedor João', 'joao@redealabama.com', 'vendedor');
