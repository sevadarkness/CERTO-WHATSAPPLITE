<?php
/**
 * PHPUnit Bootstrap File
 * 
 * Sets up the testing environment for Alabama tests.
 */

declare(strict_types=1);

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set test environment
putenv('APP_ENV=testing');
putenv('ALABAMA_TEST_MODE=true');

// Define test constants
if (!defined('ALABAMA_TEST_MODE')) {
    define('ALABAMA_TEST_MODE', true);
}

// Start session for tests that need it (with @ to suppress headers already sent warnings)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
