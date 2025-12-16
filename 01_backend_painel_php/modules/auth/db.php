<?php
/**
 * Conexão PDO central (adm_redealabama 2).
 *
 * Corrigido: removidas credenciais hardcoded (localhost/usuario/senha).
 * Agora lê DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD do .env/ambiente.
 */

declare(strict_types=1);

require_once __DIR__ . '/../_support/env.php';

function db(): PDO
{
    rede_alabama_load_env();

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_DATABASE') ?: 'redealabama';
    $user = getenv('DB_USERNAME') ?: 'root';
    $pass = getenv('DB_PASSWORD') ?: '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
