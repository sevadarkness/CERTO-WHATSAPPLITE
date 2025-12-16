<?php
declare(strict_types=1);

namespace RedeAlabama\Support;

/**
 * Loader simples de .env para o painel.
 *
 * - Lê o arquivo .env na raiz do webroot (ao lado de index.php)
 * - Preenche getenv()/$_ENV apenas se ainda não houver valor setado
 */
final class Env
{
    private static bool $loaded = false;

    /**
     * Variáveis efetivamente setadas por este loader.
     *
     * Isso permite que, caso o .env contenha linhas duplicadas, a última
     * ocorrência no arquivo "vença" sem sobrescrever variáveis já definidas
     * pelo ambiente externo (Railway/Docker/etc.).
     *
     * @var array<string,bool>
     */
    private static array $loadedVars = [];

    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $file = $path ?? self::defaultPath();
        if (!is_string($file) || $file === '' || !is_file($file)) {
            self::$loaded = true;
            return;
        }

        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            self::$loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
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
            if (($value[0] ?? '') === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            } elseif (($value[0] ?? '') === "'" && substr($value, -1) === "'") {
                $value = substr($value, 1, -1);
            }

            $alreadySetByLoader = isset(self::$loadedVars[$name]);

            // Não sobrescreve valores vindos do ambiente externo.
            // Mas se a variável já foi setada por este loader anteriormente
            // (por ex. por uma linha duplicada), deixa a última ocorrência vencer.
            $envExists = getenv($name);
            $existsOutside = ($envExists !== false) || array_key_exists($name, $_ENV);

            if (!$existsOutside || $alreadySetByLoader) {
                $_ENV[$name] = $value;
                putenv($name . '=' . $value);
                self::$loadedVars[$name] = true;
            }
        }

        self::$loaded = true;
    }



    /**
     * Obtém variáveis de ambiente com fallback e garante que o .env foi carregado.
     */
    public static function get(string $key, string $default = ''): string
    {
        self::load();

        $val = getenv($key);
        if ($val === false && array_key_exists($key, $_ENV)) {
            $val = $_ENV[$key];
        }

        if ($val === false || $val === null || $val === '') {
            return $default;
        }

        return (string)$val;
    }

    private static function defaultPath(): string
    {
        // /app/Support -> sobe 2 níveis até o webroot
        return dirname(__DIR__, 2) . '/.env';
    }
}
