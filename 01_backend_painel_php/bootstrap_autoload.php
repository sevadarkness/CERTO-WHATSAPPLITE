<?php
declare(strict_types=1);

/**
 * bootstrap_autoload.php
 *
 * - Carrega o autoloader do Composer quando /vendor/autoload.php existir
 * - Caso o vendor não esteja presente (ZIP sem dependências), registra um fallback PSR-4 simples
 *   compatível com composer.json: RedeAlabama\ => app/
 */

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix  = 'RedeAlabama\\';
    $baseDir = __DIR__ . '/app/';

    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});
