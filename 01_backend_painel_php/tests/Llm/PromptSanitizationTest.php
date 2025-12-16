<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Services/Llm/PromptSanitizer.php';

final class PromptSanitizationTest extends TestCase
{
    public function testFiltersSystemKeyword(): void
    {
        $input = "This is a SYSTEM command that should be filtered";
        $sanitized = \RedeAlabama\Services\Llm\PromptSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString('SYSTEM', $sanitized);
    }

    public function testFiltersAssistantKeyword(): void
    {
        $input = "ASSISTANT: malicious content here";
        $sanitized = \RedeAlabama\Services\Llm\PromptSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString('ASSISTANT', $sanitized);
    }

    public function testFiltersUserKeyword(): void
    {
        $input = "USER: attempting to inject prompt";
        $sanitized = \RedeAlabama\Services\Llm\PromptSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString('USER:', $sanitized);
    }

    public function testTruncatesLongContent(): void
    {
        $longInput = str_repeat('A', 10000);
        $sanitized = \RedeAlabama\Services\Llm\PromptSanitizer::sanitize($longInput);
        
        $this->assertLessThanOrEqual(8000, strlen($sanitized));
    }

    public function testRemovesControlCharacters(): void
    {
        $input = "Normal text\x00\x01\x02with control chars";
        $sanitized = \RedeAlabama\Services\Llm\PromptSanitizer::sanitize($input);
        
        $this->assertStringNotContainsString("\x00", $sanitized);
        $this->assertStringNotContainsString("\x01", $sanitized);
        $this->assertStringContainsString('Normal text', $sanitized);
    }

    public function testPreservesNormalText(): void
    {
        $input = "This is normal Portuguese text: Olá, como vai?";
        $sanitized = \RedeAlabama\Services\Llm\PromptSanitizer::sanitize($input);
        
        $this->assertStringContainsString('Portuguese text', $sanitized);
        $this->assertStringContainsString('Olá', $sanitized);
    }

    public function testHandlesEmptyInput(): void
    {
        $sanitized = \RedeAlabama\Services\Llm\PromptSanitizer::sanitize('');
        
        $this->assertIsString($sanitized);
        $this->assertEquals('', $sanitized);
    }
}
