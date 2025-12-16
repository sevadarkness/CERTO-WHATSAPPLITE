<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../csrf.php';

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear session before each test
        if (isset($_SESSION[ALABAMA_CSRF_SESSION_KEY])) {
            unset($_SESSION[ALABAMA_CSRF_SESSION_KEY]);
        }
    }

    public function testTokenGeneration(): void
    {
        $token = csrf_token();
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
    }

    public function testTokenValidation(): void
    {
        $token = csrf_token();
        
        // Valid token should pass
        $this->assertTrue(csrf_validate_from_array(['_csrf_token' => $token]));
        
        // Invalid token should fail
        $this->assertFalse(csrf_validate_from_array(['_csrf_token' => 'invalid']));
    }

    public function testTokenExpiration(): void
    {
        $token = csrf_token();
        
        // Manipulate token timestamp to simulate expiration
        $_SESSION[ALABAMA_CSRF_SESSION_KEY]['created_at'] = time() - (ALABAMA_CSRF_TTL + 100);
        
        // Expired token should fail validation
        $this->assertFalse(csrf_validate_from_array(['_csrf_token' => $token]));
    }

    public function testHashEqualsUsed(): void
    {
        // Test that timing-safe comparison is working
        $token = csrf_token();
        
        // Same token should validate
        $this->assertTrue(csrf_validate_from_array(['_csrf_token' => $token]));
        
        // Similar but different token should fail
        $similarToken = substr($token, 0, -1) . 'x';
        $this->assertFalse(csrf_validate_from_array(['_csrf_token' => $similarToken]));
    }

    public function testTokenReusable(): void
    {
        $token = csrf_token();
        
        // Same token can be validated multiple times within TTL
        $this->assertTrue(csrf_validate_from_array(['_csrf_token' => $token]));
        $this->assertTrue(csrf_validate_from_array(['_csrf_token' => $token]));
        $this->assertTrue(csrf_validate_from_array(['_csrf_token' => $token]));
    }

    public function testCsrfFieldGeneration(): void
    {
        $token = csrf_token();
        $field = csrf_field();
        
        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf_token"', $field);
        $this->assertStringContainsString($token, $field);
    }
}
