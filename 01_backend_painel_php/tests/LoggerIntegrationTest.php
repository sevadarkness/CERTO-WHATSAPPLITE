<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;



final class LoggerIntegrationTest extends TestCase
{
    public function testLogAppEventCreatesLogFiles(): void
    {
        // Usa um diretório temporário para logs
        $tmpDir = sys_get_temp_dir() . '/alabama_logs_test_' . uniqid();
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        define('ALABAMA_LOG_DIR', $tmpDir);

        require_once __DIR__ . '/../logger.php';

        log_app_event('test_channel', 'evento_teste', ['foo' => 'bar']);

        $this->assertFileExists($tmpDir . '/app.log');
        $this->assertFileExists($tmpDir . '/test_channel.log');

        $contents = file_get_contents($tmpDir . '/app.log');
        $this->assertStringContainsString('evento_teste', (string)$contents);
    }
}
