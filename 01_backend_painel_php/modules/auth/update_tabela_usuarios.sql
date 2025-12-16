
ALTER TABLE usuarios
ADD COLUMN status ENUM('ativo','inativo') DEFAULT 'ativo',
ADD COLUMN ultimo_login DATETIME;
