<?php
/**
 * Loader simples de .env para o pacote "adm_redealabama 2".
 *
 * - Procura o arquivo .env na raiz do projeto (ao lado de README.md)
 * - Carrega as variáveis em getenv()/$_ENV apenas se ainda não existirem
 */

declare(strict_types=1);

if (!function_exists('rede_alabama_load_env')) {
    function rede_alabama_load_env(?string $path = null): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $root = dirname(__DIR__, 2); // modules/_support -> modules -> raiz do projeto
        $file = $path ?: ($root . '/.env');

        if (!is_file($file)) {
            return;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $name  = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            if ($name === '') {
                continue;
            }

            // Remove aspas simples/duplas ao redor, se houver
            if ((($value[0] ?? '') === '"' && substr($value, -1) === '"')
                || (($value[0] ?? '') === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            if (getenv($name) === false && !array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv($name . '=' . $value);
            }
        }
    }
}
