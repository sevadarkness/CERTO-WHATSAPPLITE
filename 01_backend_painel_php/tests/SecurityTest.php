<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/Support/Security.php';

final class SecurityTest extends TestCase
{
    public function testEvaluateBooleanExpression(): void
    {
        $this->assertTrue(Security::safe_eval('1 == 1'));
        $this->assertFalse(Security::safe_eval("'a' === 'b'"));
        $this->assertTrue(Security::safe_eval('2 + 2 == 4'));
        $this->assertFalse(Security::safe_eval('10 < 5'));
        // Additional edge cases
        $this->assertTrue(Security::safe_eval("'x' != 'y' && (2 * 3) == 6"));
        $this->assertFalse(Security::safe_eval("'x' === 'x' && 5 > 10"));
    }

    public function testSafeExecAllowsWhenAdminAndEnvEnabled(): void
    {
        // Enable admin shell via env for test
        putenv('ALLOW_ADMIN_SHELL=true');
        putenv('ALLOW_UNSAFE_SHELL_EXEC=true');

        $res = Security::safe_exec('echo security-test-unique');
        $this->assertIsArray($res);
        $this->assertArrayHasKey('exit_code', $res);
        $this->assertEquals(0, $res['exit_code']);
        $this->assertStringContainsString('security-test-unique', $res['output']);
    }

    public function testSafeEvalBlocksUnsafeCode(): void
    {
        $this->expectException(\Exception::class);
        // attempt to use a disallowed token/function
        Security::safe_eval('system("ls")');
    }

    public function testBuildBearerHeaderRejectsCRLF(): void
    {
        $this->expectException(\Exception::class);
        Security::build_bearer_header("abc\r\n: injected");
    }
}
