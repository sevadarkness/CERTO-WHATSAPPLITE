<?php

declare(strict_types=1);

namespace RedeAlabama\Services\Llm;

/**
 * Sanitizes user input before sending to LLM to prevent prompt injection.
 */
final class PromptSanitizer
{
    private const MAX_LENGTH = 8000;
    
    private const FORBIDDEN_KEYWORDS = [
        'SYSTEM',
        'ASSISTANT',
        'USER:',
        'HUMAN:',
        'AI:',
    ];

    /**
     * Sanitize input text to prevent prompt injection attacks.
     */
    public static function sanitize(string $input): string
    {
        if ($input === '') {
            return '';
        }

        // Remove control characters
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        if (!is_string($sanitized)) {
            $sanitized = '';
        }

        // Filter out forbidden keywords (case-insensitive)
        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            $sanitized = str_ireplace($keyword, '', $sanitized);
        }

        // Truncate to max length
        if (strlen($sanitized) > self::MAX_LENGTH) {
            $sanitized = substr($sanitized, 0, self::MAX_LENGTH);
        }

        return trim($sanitized);
    }

    /**
     * Sanitize an array of inputs.
     * 
     * @param array<string> $inputs
     * @return array<string>
     */
    public static function sanitizeArray(array $inputs): array
    {
        return array_map([self::class, 'sanitize'], $inputs);
    }
}
