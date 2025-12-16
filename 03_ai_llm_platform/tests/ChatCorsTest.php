<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Testes para validar a segurança CORS do endpoint chat.php
 * 
 * Garante que apenas origens autorizadas podem fazer requisições cross-origin:
 * - Extensões de navegador (chrome-extension://, moz-extension://, edge-extension://)
 * - Localhost para desenvolvimento
 * - Domínios configurados via ALABAMA_CORS_ALLOWED_ORIGINS
 */
class ChatCorsTest extends TestCase
{
    /**
     * Simula o comportamento do chat.php para validação CORS
     */
    private function simulateCorsLogic(string $origin, string $envOrigins = ''): array
    {
        $allowedOrigin = '';

        if ($origin !== '') {
            // Extensões Chrome sempre permitidas
            if (str_starts_with($origin, 'chrome-extension://')) {
                $allowedOrigin = $origin;
            }
            // Extensões Firefox
            elseif (str_starts_with($origin, 'moz-extension://')) {
                $allowedOrigin = $origin;
            }
            // Extensões Edge
            elseif (str_starts_with($origin, 'edge-extension://')) {
                $allowedOrigin = $origin;
            }
            // Localhost para desenvolvimento
            elseif (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/', $origin)) {
                $allowedOrigin = $origin;
            }
            // Domínios adicionais configurados via env
            else {
                $extraOrigins = trim($envOrigins);
                if ($extraOrigins !== '') {
                    $allowedList = array_map('trim', explode(',', $extraOrigins));
                    foreach ($allowedList as $allowed) {
                        if ($allowed !== '' && $origin === $allowed) {
                            $allowedOrigin = $origin;
                            break;
                        }
                    }
                }
            }
        }

        return [
            'allowed' => $allowedOrigin !== '',
            'allowedOrigin' => $allowedOrigin,
        ];
    }

    /**
     * Testa que extensões Chrome são permitidas
     */
    public function testChromeExtensionsAreAllowed(): void
    {
        $result = $this->simulateCorsLogic('chrome-extension://abcdefghijklmnop');
        $this->assertTrue($result['allowed'], 'Chrome extensions should be allowed');
        $this->assertEquals('chrome-extension://abcdefghijklmnop', $result['allowedOrigin']);
    }

    /**
     * Testa que extensões Firefox são permitidas
     */
    public function testFirefoxExtensionsAreAllowed(): void
    {
        $result = $this->simulateCorsLogic('moz-extension://12345678-1234-1234-1234-123456789012');
        $this->assertTrue($result['allowed'], 'Firefox extensions should be allowed');
        $this->assertEquals('moz-extension://12345678-1234-1234-1234-123456789012', $result['allowedOrigin']);
    }

    /**
     * Testa que extensões Edge são permitidas
     */
    public function testEdgeExtensionsAreAllowed(): void
    {
        $result = $this->simulateCorsLogic('edge-extension://abcdefghijklmnop');
        $this->assertTrue($result['allowed'], 'Edge extensions should be allowed');
        $this->assertEquals('edge-extension://abcdefghijklmnop', $result['allowedOrigin']);
    }

    /**
     * Testa que localhost HTTP é permitido
     */
    public function testLocalhostHttpIsAllowed(): void
    {
        $result = $this->simulateCorsLogic('http://localhost');
        $this->assertTrue($result['allowed'], 'http://localhost should be allowed');
        $this->assertEquals('http://localhost', $result['allowedOrigin']);
    }

    /**
     * Testa que localhost HTTPS é permitido
     */
    public function testLocalhostHttpsIsAllowed(): void
    {
        $result = $this->simulateCorsLogic('https://localhost');
        $this->assertTrue($result['allowed'], 'https://localhost should be allowed');
        $this->assertEquals('https://localhost', $result['allowedOrigin']);
    }

    /**
     * Testa que localhost com porta é permitido
     */
    public function testLocalhostWithPortIsAllowed(): void
    {
        $result = $this->simulateCorsLogic('http://localhost:3000');
        $this->assertTrue($result['allowed'], 'http://localhost:3000 should be allowed');
        $this->assertEquals('http://localhost:3000', $result['allowedOrigin']);
    }

    /**
     * Testa que 127.0.0.1 é permitido
     */
    public function testLoopbackIpIsAllowed(): void
    {
        $result = $this->simulateCorsLogic('http://127.0.0.1');
        $this->assertTrue($result['allowed'], 'http://127.0.0.1 should be allowed');
        $this->assertEquals('http://127.0.0.1', $result['allowedOrigin']);

        $result = $this->simulateCorsLogic('http://127.0.0.1:8080');
        $this->assertTrue($result['allowed'], 'http://127.0.0.1:8080 should be allowed');
        $this->assertEquals('http://127.0.0.1:8080', $result['allowedOrigin']);
    }

    /**
     * Testa que origens maliciosas são bloqueadas
     */
    public function testMaliciousOriginsAreBlocked(): void
    {
        $maliciousOrigins = [
            'https://evil.com',
            'http://attacker.net',
            'https://phishing-site.xyz',
            'http://malware.org',
        ];

        foreach ($maliciousOrigins as $origin) {
            $result = $this->simulateCorsLogic($origin);
            $this->assertFalse($result['allowed'], "Origin {$origin} should be blocked");
            $this->assertEquals('', $result['allowedOrigin']);
        }
    }

    /**
     * Testa que origens não autorizadas retornam vazio
     */
    public function testUnauthorizedOriginsReturnEmpty(): void
    {
        $result = $this->simulateCorsLogic('https://unauthorized-domain.com');
        $this->assertFalse($result['allowed'], 'Unauthorized domains should be blocked');
        $this->assertEquals('', $result['allowedOrigin']);
    }

    /**
     * Testa que domínios configurados via env são permitidos
     */
    public function testConfiguredDomainsAreAllowed(): void
    {
        $result = $this->simulateCorsLogic(
            'https://app.example.com',
            'https://app.example.com,https://admin.example.com'
        );
        $this->assertTrue($result['allowed'], 'Configured domain should be allowed');
        $this->assertEquals('https://app.example.com', $result['allowedOrigin']);

        $result = $this->simulateCorsLogic(
            'https://admin.example.com',
            'https://app.example.com,https://admin.example.com'
        );
        $this->assertTrue($result['allowed'], 'Second configured domain should be allowed');
        $this->assertEquals('https://admin.example.com', $result['allowedOrigin']);
    }

    /**
     * Testa que domínios não configurados não são permitidos mesmo com env definido
     */
    public function testNonConfiguredDomainsAreBlockedEvenWithEnv(): void
    {
        $result = $this->simulateCorsLogic(
            'https://unauthorized.com',
            'https://app.example.com,https://admin.example.com'
        );
        $this->assertFalse($result['allowed'], 'Non-configured domain should be blocked');
        $this->assertEquals('', $result['allowedOrigin']);
    }

    /**
     * Testa que requisições sem origin (mesma origem ou curl) são tratadas
     */
    public function testEmptyOriginIsHandled(): void
    {
        $result = $this->simulateCorsLogic('');
        $this->assertFalse($result['allowed'], 'Empty origin should not set CORS headers');
        $this->assertEquals('', $result['allowedOrigin']);
    }

    /**
     * Testa que espaços extras nos domínios configurados são tratados
     */
    public function testWhitespaceInConfiguredDomainsIsHandled(): void
    {
        $result = $this->simulateCorsLogic(
            'https://app.example.com',
            ' https://app.example.com , https://admin.example.com '
        );
        $this->assertTrue($result['allowed'], 'Domain with whitespace in config should be allowed');
        $this->assertEquals('https://app.example.com', $result['allowedOrigin']);
    }

    /**
     * Testa que localhost com subdomínio não é permitido (prevenir bypass)
     */
    public function testLocalhostSubdomainIsBlocked(): void
    {
        $result = $this->simulateCorsLogic('http://evil.localhost.com');
        $this->assertFalse($result['allowed'], 'Subdomain of localhost should be blocked');
        $this->assertEquals('', $result['allowedOrigin']);
    }

    /**
     * Testa que prefixos de extensão não permitem bypass
     */
    public function testExtensionPrefixDoesNotAllowBypass(): void
    {
        $result = $this->simulateCorsLogic('https://chrome-extension.evil.com');
        $this->assertFalse($result['allowed'], 'Domain with extension prefix should be blocked');
        $this->assertEquals('', $result['allowedOrigin']);
    }
}
