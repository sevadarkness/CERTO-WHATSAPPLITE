<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Support\RateLimiter;

require_once __DIR__ . '/../../app/Support/RateLimiter.php';

final class RateLimiterTest extends TestCase
{
    private string $testBucket;

    protected function setUp(): void
    {
        parent::setUp();
        // Use unique bucket name for each test
        $this->testBucket = 'test_' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up test bucket file if it exists
        $storageDir = sys_get_temp_dir() . '/alabama_ratelimit';
        $file = $storageDir . '/' . preg_replace('/[^a-zA-Z0-9_\-:]/', '_', $this->testBucket) . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    public function testAllowsUnderLimit(): void
    {
        $maxAttempts = 3;
        $decaySeconds = 60;
        
        // First 3 attempts should be allowed
        $this->assertTrue(RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds));
        $this->assertTrue(RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds));
        $this->assertTrue(RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds));
    }

    public function testBlocksOverLimit(): void
    {
        $maxAttempts = 3;
        $decaySeconds = 60;
        
        // First 3 attempts should be allowed
        RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds);
        RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds);
        RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds);
        
        // 4th attempt should be blocked
        $this->assertFalse(RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds));
    }

    public function testWindowExpiration(): void
    {
        $maxAttempts = 2;
        $decaySeconds = 1; // Very short window for testing
        
        // Use up the limit
        RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds);
        RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds);
        
        // Should be blocked now
        $this->assertFalse(RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds));
        
        // Wait for window to expire
        sleep(2);
        
        // Should be allowed again after window expires
        $this->assertTrue(RateLimiter::hit($this->testBucket, $maxAttempts, $decaySeconds));
    }

    public function testDifferentBucketsAreIndependent(): void
    {
        $bucket1 = $this->testBucket . '_1';
        $bucket2 = $this->testBucket . '_2';
        $maxAttempts = 2;
        $decaySeconds = 60;
        
        // Use up bucket1
        RateLimiter::hit($bucket1, $maxAttempts, $decaySeconds);
        RateLimiter::hit($bucket1, $maxAttempts, $decaySeconds);
        $this->assertFalse(RateLimiter::hit($bucket1, $maxAttempts, $decaySeconds));
        
        // bucket2 should still work
        $this->assertTrue(RateLimiter::hit($bucket2, $maxAttempts, $decaySeconds));
        $this->assertTrue(RateLimiter::hit($bucket2, $maxAttempts, $decaySeconds));
    }
}
