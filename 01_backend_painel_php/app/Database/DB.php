<?php
declare(strict_types=1);

namespace RedeAlabama\Database;

use PDO;
use PDOException;
use RedeAlabama\Support\Env;

final class DB
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn  = Env::get('DB_DSN', '');
        $user = Env::get('DB_USERNAME', Env::get('DB_USER', ''));
        $pass = Env::get('DB_PASSWORD', Env::get('DB_PASS', ''));

        if ($dsn === '') {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $db   = Env::get('DB_DATABASE', Env::get('DB_NAME', 'adm_redealabama'));
            $port = Env::get('DB_PORT', '3306');
            $dsn  = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        }

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo 'Erro ao conectar ao banco de dados (DB::connection).';
            exit;
        }

        return self::$pdo;
    }
}
