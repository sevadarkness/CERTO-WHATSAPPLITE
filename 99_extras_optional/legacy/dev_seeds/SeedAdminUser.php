<?php
declare(strict_types=1);

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../logger.php';

echo "Seed: admin default..." . PHP_EOL;

try {
    // Verifica se tabela 'usuarios' existe
    $check = $pdo->query("SHOW TABLES LIKE 'usuarios'");
    if (!$check || $check->rowCount() === 0) {
        echo "Tabela 'usuarios' não encontrada. Seed ignorado." . PHP_EOL;
        return;
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel_acesso = 'Administrador'");
    $count = (int) $stmt->fetchColumn();

    if ($count > 0) {
        echo "Já existe ao menos um administrador. Seed não necessário." . PHP_EOL;
        return;
    }

    $senhaHash = password_hash('admin@123', defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);

    $insert = $pdo->prepare("
        INSERT INTO usuarios (nome, telefone, senha, nivel_acesso)
        VALUES (:nome, :telefone, :senha, 'Administrador')
    ");
    $insert->execute([
        ':nome'     => 'Admin Padrão',
        ':telefone' => '00000000000',
        ':senha'    => $senhaHash,
    ]);

    log_app_event('seed', 'admin_user_created', []);
    echo "Usuário administrador padrão criado com sucesso (telefone 00000000000, senha admin@123)." . PHP_EOL;
} catch (Throwable $e) {
    echo "Erro no seed de admin: " . $e->getMessage() . PHP_EOL;
    log_app_event('seed', 'admin_user_error', ['error' => $e->getMessage()]);
}
