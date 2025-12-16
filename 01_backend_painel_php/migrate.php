<?php
declare(strict_types=1);

/**
 * Runner simples de migrations da Rede Alabama.
 *
 * Uso:
 *   php migrate.php up
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

require_once __DIR__ . '/db_config.php';

/**
 * Divide um script SQL (que pode conter múltiplas statements) em statements individuais.
 *
 * Importante:
 * - Lida com strings (aspas simples/dobras) e identificadores com crases
 * - Remove comentários do tipo: -- ... , # ... e blocos /* ... * / (comentários em bloco)
 * - Evita depender de PDO::MYSQL_ATTR_MULTI_STATEMENTS (que pode estar desabilitado)
 *
 * @return string[]
 */
function alabama_split_sql_statements(string $sql): array
{
    // Normaliza quebras de linha
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);

    $len = strlen($sql);
    $statements = [];
    $buffer = '';

    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch   = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';
        $next2 = ($i + 2 < $len) ? $sql[$i + 2] : '';

        // Comentário de linha: consome até \n
        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
                $buffer .= "\n";
            }
            continue;
        }

        // Comentário de bloco: consome até */
        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++; // pula '/'
            }
            continue;
        }

        // Início de comentários (somente fora de strings)
        if (!$inSingle && !$inDouble && !$inBacktick) {
            // -- comentário (MySQL considera comentário se houver whitespace após)
            if ($ch === '-' && $next === '-' && ($next2 === ' ' || $next2 === "\t" || $next2 === "\n" || $next2 === "\0" || $next2 === '')) {
                $inLineComment = true;
                $i++; // pula o segundo '-'
                continue;
            }

            // # comentário
            if ($ch === '#') {
                $inLineComment = true;
                continue;
            }

            // /* comentário */
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++; // pula '*'
                continue;
            }
        }

        // Alternância de aspas (com escape por \)
        if (!$inDouble && !$inBacktick && $ch === "'") {
            $escaped = ($i > 0 && $sql[$i - 1] === '\\');
            if (!$escaped) {
                $inSingle = !$inSingle;
            }
            $buffer .= $ch;
            continue;
        }

        if (!$inSingle && !$inBacktick && $ch === '"') {
            $escaped = ($i > 0 && $sql[$i - 1] === '\\');
            if (!$escaped) {
                $inDouble = !$inDouble;
            }
            $buffer .= $ch;
            continue;
        }

        if (!$inSingle && !$inDouble && $ch === '`') {
            $inBacktick = !$inBacktick;
            $buffer .= $ch;
            continue;
        }

        // Final de statement
        if (!$inSingle && !$inDouble && !$inBacktick && $ch === ';') {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $stmt = trim($buffer);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}


if (!isset($argv) || !is_array($argv)) {
    echo "Este script deve ser executado via CLI." . PHP_EOL;
    exit(1);
}

$action = $argv[1] ?? 'up';
if ($action !== 'up') {
    echo "Ação não suportada: {$action}" . PHP_EOL;
    echo "Uso: php migrate.php up" . PHP_EOL;
    exit(1);
}

$migrationsDir = __DIR__ . '/database/migrations';

if (!is_dir($migrationsDir)) {
    echo "Diretório de migrations não encontrado: {$migrationsDir}" . PHP_EOL;
    exit(1);
}

// Garante tabela de controle de migrations
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Carrega já aplicadas
$applied = [];
$stmt = $pdo->query("SELECT migration FROM schema_migrations ORDER BY applied_at ASC");
if ($stmt !== false) {
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $mig) {
        $applied[(string)$mig] = true;
    }
}

// Lista arquivos *.sql ordenados pelo nome
$files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql');
sort($files, SORT_STRING);

if (!$files) {
    echo "Nenhum arquivo de migration encontrado em {$migrationsDir}" . PHP_EOL;
    exit(0);
}

foreach ($files as $file) {
    $name = basename($file);

    if (isset($applied[$name])) {
        echo "Já aplicada, pulando: {$name}" . PHP_EOL;
        continue;
    }

    $sql = @file_get_contents($file);
    if ($sql === false) {
        echo "Não foi possível ler migration: {$name}" . PHP_EOL;
        continue;
    }

    echo "Aplicando migration: {$name}..." . PHP_EOL;

    try {
        $statements = alabama_split_sql_statements($sql);
        if (!$statements) {
            // Migration vazia: ainda assim marca como aplicada para evitar reprocessar.
            $statements = [];
        }

        foreach ($statements as $idx => $stmtSql) {
            try {
                $pdo->exec($stmtSql);
            } catch (Throwable $eStmt) {
                $preview = preg_replace('/\s+/', ' ', trim((string)$stmtSql));
                if (strlen($preview) > 220) {
                    $preview = substr($preview, 0, 220) . '...';
                }
                throw new RuntimeException(
                    'Falha no statement #' . ($idx + 1) . ' da migration ' . $name . ': ' . $preview,
                    0,
                    $eStmt
                );
            }
        }

        $insert = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (:migration)");
        $insert->execute([':migration' => $name]);

        echo "OK: {$name}" . PHP_EOL;
    } catch (Throwable $e) {
        echo "ERRO ao aplicar {$name}: " . $e->getMessage() . PHP_EOL;
        exit(1);
    }
}

echo "Migrations concluídas." . PHP_EOL;
