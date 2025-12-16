<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Llm;

/**
 * Roteador de LLM multi-modelo.
 *
 * Permite trocar entre provedores/modelos com base em configurações.
 */
final class LlmRouter
{
    /**
     * Normaliza configurações de LLM.
     *
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    public static function normalize(array $settings): array
    {
        $provider = $settings['llm_provider'] ?? getenv('ALABAMA_LLM_PROVIDER') ?: 'openai';
        $model    = $settings['llm_model']    ?? getenv('ALABAMA_LLM_MODEL')    ?: 'gpt-4o-mini';

        $settings['llm_provider'] = $provider;
        $settings['llm_model']    = $model;

        // Roteamento simples por prefixo de modelo
        if (str_starts_with($model, 'gpt-')) {
            $settings['llm_provider'] = 'openai';
        } elseif (str_starts_with($model, 'claude-')) {
            $settings['llm_provider'] = 'anthropic';
        } elseif (str_starts_with($model, 'gemini-')) {
            $settings['llm_provider'] = 'google';
        }

        return $settings;
    }
}

