<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    private string $testSecret = 'test_webhook_secret_key';

    /**
     * Helper to generate HMAC signature for webhook validation.
     */
    private function generateSignature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Helper to validate webhook signature.
     */
    private function validateSignature(string $payload, string $signature, string $secret): bool
    {
        $expected = $this->generateSignature($payload, $secret);
        return hash_equals($expected, $signature);
    }

    public function testValidSignature(): void
    {
        $payload = '{"event":"message","data":"test"}';
        $signature = $this->generateSignature($payload, $this->testSecret);
        
        $this->assertTrue($this->validateSignature($payload, $signature, $this->testSecret));
    }

    public function testInvalidSignature(): void
    {
        $payload = '{"event":"message","data":"test"}';
        $wrongSignature = 'invalid_signature_here';
        
        $this->assertFalse($this->validateSignature($payload, $wrongSignature, $this->testSecret));
    }

    public function testMalformedSignature(): void
    {
        $payload = '{"event":"message","data":"test"}';
        $malformedSignature = '';
        
        $this->assertFalse($this->validateSignature($payload, $malformedSignature, $this->testSecret));
    }

    public function testSignatureWithDifferentPayload(): void
    {
        $payload1 = '{"event":"message","data":"test1"}';
        $payload2 = '{"event":"message","data":"test2"}';
        
        $signature1 = $this->generateSignature($payload1, $this->testSecret);
        
        // Signature from payload1 should not validate payload2
        $this->assertFalse($this->validateSignature($payload2, $signature1, $this->testSecret));
    }

    public function testSignatureTimingSafeComparison(): void
    {
        $payload = '{"event":"message","data":"test"}';
        $validSignature = $this->generateSignature($payload, $this->testSecret);
        
        // Create a similar but different signature (same length)
        $similarSignature = substr($validSignature, 0, -1) . 'x';
        
        // Should fail validation even though lengths match
        $this->assertFalse($this->validateSignature($payload, $similarSignature, $this->testSecret));
    }

    public function testSignatureWithEmptyPayload(): void
    {
        $payload = '';
        $signature = $this->generateSignature($payload, $this->testSecret);
        
        $this->assertTrue($this->validateSignature($payload, $signature, $this->testSecret));
    }

    public function testSignatureFormat(): void
    {
        $payload = '{"event":"message","data":"test"}';
        $signature = $this->generateSignature($payload, $this->testSecret);
        
        // SHA256 produces 64 character hex string
        $this->assertEquals(64, strlen($signature));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);
    }
}
