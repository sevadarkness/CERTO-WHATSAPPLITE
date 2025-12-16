<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Requests;

/**
 * RemarketingLogsRequest
 *
 * Hoje não usa filtros, mas fica preparado.
 */
final class RemarketingLogsRequest
{
    private string $method;

    public function __construct(string $method)
    {
        $this->method = strtoupper($method);
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        return new self($method);
    }

    public function method(): string
    {
        return $this->method;
    }
}

