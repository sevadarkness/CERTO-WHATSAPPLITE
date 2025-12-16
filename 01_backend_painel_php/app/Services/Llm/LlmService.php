<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Llm;

use RedeAlabama\Support\Config;

/**
 * Camada fina para chamadas a LLM (OpenAI ou outro provider).
 *
 * Mantém compatibilidade com a factory fromEnv() já usada no painel,
 * mas adiciona um método de alto nível para realizar chamadas reais
 * ao provider configurado.
 */
final class LlmService
{
    public function __construct(
        private readonly string $provider,
        private readonly int $timeoutSeconds
    ) {
    }

    /**
     * Factory tradicional baseada em variáveis de ambiente.
     *
     * LLM_PROVIDER=openai|stub
     * LLM_TIMEOUT_SECONDS=15
     */
    public static function fromEnv(): self
    {
        $envProvider = (string) (getenv('LLM_PROVIDER') ?: 'openai');
        $envTimeout  = (int) (getenv('LLM_TIMEOUT_SECONDS') ?: 15);

        $provider = (string) Config::get('llm.provider', $envProvider);
        if ($provider === '') {
            $provider = 'openai';
        }

        $timeout = (int) Config::get('llm.timeout', (string) $envTimeout);
        if ($timeout <= 0) {
            $timeout = $envTimeout > 0 ? $envTimeout : 15;
        }

        return new self($provider, $timeout);
    }

    /**
     * Factory alternativa puramente baseada em Config.
     */
    public static function fromConfig(): self
    {
        $provider = (string) Config::get('llm.provider', 'openai');
        if ($provider === '') {
            $provider = 'openai';
        }

        $timeout = (int) Config::get('llm.timeout', '15');
        if ($timeout <= 0) {
            $timeout = 15;
        }

        return new self($provider, $timeout);
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    /**
     * Gera uma resposta de chat a partir de um prompt.
     *
     * @return string|null Texto retornado pela LLM ou null em caso de erro.
     */
    public function generateChatCompletion(
        string $prompt,
        ?string $systemPrompt = null,
        ?string $model = null
    ): ?string {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return null;
        }

        // Caminho de desenvolvimento sem LLM real.
        if ($this->provider === 'stub') {
            return '[STUB LLM] ' . mb_substr($prompt, 0, 200);
        }

        // Por enquanto suportamos apenas OpenAI via HTTP direto.
        if ($this->provider !== 'openai') {
            return null;
        }

        // OPENAI_API_KEY / ALABAMA_OPENAI_API_KEY / LLM_OPENAI_API_KEY (legado)
        $apiKey = (string) Config::get('openai.api_key', '');
        if ($apiKey === '') {
            $apiKey = (string) Config::get('alabama_openai.api_key', '');
        }
        if ($apiKey === '') {
            // Compatibilidade: algumas instalações antigas usam LLM_OPENAI_API_KEY
            $apiKey = (string) Config::get('llm.openai_api_key', '');
        }
        if ($apiKey === '') {
            return null;
        }

        $endpoint = (string) Config::get('openai.chat_endpoint', 'https://api.openai.com/v1/chat/completions');
        $model    = $model ?: (string) Config::get('openai.model', 'gpt-4.1-mini');

        $temperature = (float) Config::get('openai.temperature', '0.4');
        $maxTokens   = (int) Config::get('openai.max_tokens', '400');
        if ($maxTokens <= 0) {
            $maxTokens = 400;
        }

        $systemPrompt = $systemPrompt ?: (string) Config::get(
            'openai.system_prompt',
            'Você é o assistente oficial da Rede Alabama no WhatsApp. Responda em português do Brasil, de forma objetiva, educada e voltada para vendas e atendimento.'
        );

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $temperature,
            'max_tokens'  => $maxTokens,
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return null;
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return null;
        }

        // Use centralized header builder when available
        $securityPath = __DIR__ . '/../../Support/Security.php';
        if (file_exists($securityPath)) {
            require_once $securityPath;
        }
        $sec = __DIR__ . '/../../Support/Security.php';
        if (file_exists($sec)) {
            require_once $sec;
        }
        $authHeader = class_exists('Security') ? \Security::build_bearer_header($apiKey) : ('Authorization: Bearer ' . $apiKey);
        $headers = [
            'Content-Type: application/json',
            $authHeader,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            curl_close($ch);
            return null;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        // OpenAI chat completion: choices[0].message.content
        if (!isset($decoded['choices'][0]['message']['content'])) {
            return null;
        }

        $text = (string) $decoded['choices'][0]['message']['content'];
        return trim($text);
    }
}
