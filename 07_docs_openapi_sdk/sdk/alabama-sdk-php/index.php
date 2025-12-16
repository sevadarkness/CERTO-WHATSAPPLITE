<?php
declare(strict_types=1);

/**
 * Alabama SDK PHP - cliente mínimo para a Test Prompt API.
 *
 * Observação importante (formato de resposta):
 * A API responde no formato padronizado (ApiResponse):
 *   [ 'ok' => bool, 'data' => ..., 'error' => ['code','message']|null, 'meta' => ... ]
 *
 * Exemplo:
 *   require __DIR__ . '/sdk/alabama-sdk-php/index.php';
 *   $client = new AlabamaSdkPhp('http://localhost:8000');
 *   $resposta = $client->testPrompt('Olá, IA!');
 *   echo $resposta['data']['answer'] ?? '';
 */

final class AlabamaSdkPhp
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $env = getenv('ALABAMA_API_BASE_URL') ?: null;
        $this->baseUrl = rtrim($baseUrl ?? $env ?? 'http://localhost:8000', '/');
    }

    /**
     * @param string $prompt
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function testPrompt(string $prompt, array $params = []): array
    {
        $payload = [
            'prompt'      => $prompt,
            'temperature' => $params['temperature'] ?? 0.2,
            'max_tokens'  => $params['max_tokens'] ?? 256,
            'model'       => $params['model'] ?? null,
        ];

        $url = $this->baseUrl . '/api/test_prompt.php';
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Falha ao chamar API: ' . ($error ?: 'body vazio'));
        }

        /** @var array<string,mixed>|null $json */
        $json = json_decode((string)$body, true);
        if (!is_array($json)) {
            throw new RuntimeException('Resposta inválida da API: ' . $body);
        }

        $apiOk = ($json['ok'] ?? true) !== false;

        if ($status < 200 || $status >= 300 || !$apiOk) {
            // Formato atual (ApiResponse)
            $msg = null;
            if (isset($json['error']) && is_array($json['error'])) {
                $msg = $json['error']['message'] ?? $json['error']['code'] ?? null;
            }
            // Compat (legado)
            if ($msg === null && isset($json['erro']) && is_string($json['erro'])) {
                $msg = $json['erro'];
            }
            if ($msg === null) {
                $msg = 'HTTP ' . $status;
            }
            throw new RuntimeException('Erro da API: ' . $msg);
        }

        return $json;
    }
}
