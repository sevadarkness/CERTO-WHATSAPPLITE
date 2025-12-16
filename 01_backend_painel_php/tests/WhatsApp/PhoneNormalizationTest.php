<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PhoneNormalizationTest extends TestCase
{
    /**
     * Helper function to normalize phone numbers for WhatsApp.
     * Removes non-numeric characters and ensures proper format.
     */
    private function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (!is_string($cleaned)) {
            return '';
        }

        // If starts with 0, remove it (common in Brazilian numbers)
        if (str_starts_with($cleaned, '0')) {
            $cleaned = substr($cleaned, 1);
        }

        // If doesn't start with country code, add Brazil's (+55)
        if (!str_starts_with($cleaned, '55') && strlen($cleaned) >= 10) {
            $cleaned = '55' . $cleaned;
        }

        return $cleaned;
    }

    public function testBrazilianPhoneWithDDD(): void
    {
        $phone = '(11) 98765-4321';
        $normalized = $this->normalizePhone($phone);
        
        $this->assertEquals('5511987654321', $normalized);
    }

    public function testPhoneWithCountryCode(): void
    {
        $phone = '+55 11 98765-4321';
        $normalized = $this->normalizePhone($phone);
        
        $this->assertEquals('5511987654321', $normalized);
    }

    public function testPhoneWithSpecialChars(): void
    {
        $phone = '55 (11) 9.8765-4321';
        $normalized = $this->normalizePhone($phone);
        
        $this->assertEquals('5511987654321', $normalized);
    }

    public function testEmptyPhone(): void
    {
        $normalized = $this->normalizePhone('');
        
        $this->assertEquals('', $normalized);
    }

    public function testPhoneWithLeadingZero(): void
    {
        $phone = '011987654321';
        $normalized = $this->normalizePhone($phone);
        
        $this->assertEquals('5511987654321', $normalized);
    }

    public function testPhoneWithOnlyNumbers(): void
    {
        $phone = '11987654321';
        $normalized = $this->normalizePhone($phone);
        
        $this->assertEquals('5511987654321', $normalized);
    }

    public function testPhoneAlreadyNormalized(): void
    {
        $phone = '5511987654321';
        $normalized = $this->normalizePhone($phone);
        
        $this->assertEquals('5511987654321', $normalized);
    }
}
