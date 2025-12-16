<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Requests;

/**
 * MatchingRequest
 *
 * Request wrapper para o endpoint de matching (API v2).
 * Lê e expõe o corpo/json e a query string de forma normalizada.
 */
final class MatchingRequest
{
    private string $method;
    private array $body;
    private array $query;

    public function __construct(string $method, array $body, array $query)
    {
        $this->method = strtoupper($method);
        $this->body   = $body;
        $this->query  = $query;
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $query  = $_GET ?? [];
        $body   = [];

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            } else {
                $body = $_POST ?? [];
            }
        }

        return new self($method, $body, $query);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function query(): array
    {
        return $this->query;
    }

    /**
     * Para o caso de uso atual, retornamos o payload bruto normalizado,
     * permitindo que a camada de serviço faça validações mais específicas.
     */
    public function payload(): array
    {
        return $this->body;
    }
}

