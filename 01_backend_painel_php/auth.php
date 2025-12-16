<?php
declare(strict_types=1);

/**
 * Endpoint legado de autenticação.
 *
 * Mantido apenas para compatibilidade. Toda autenticação agora é tratada em login.php.
 */
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = 'login.php' . ($qs ? '?' . $qs : '');
header('Location: ' . $target);
exit;
