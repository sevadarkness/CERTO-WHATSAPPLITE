<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Requests;

/**
 * EntregadoresRequest
 *
 * Request wrapper para o endpoint de entregadores (API v2).
 */
final class EntregadoresRequest
{
    private string $method;
    private array $query;

    public function __construct(string $method, array $query)
    {
        $this->method = strtoupper($method);
        $this->query  = $query;
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $query  = $_GET ?? [];

        return new self($method, $query);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function query(): array
    {
        return $this->query;
    }

    /**
     * Retorna filtros normalizados (por enquanto, apenas espelha a query).
     */
    public function filters(): array
    {
        return $this->query;
    }
}

