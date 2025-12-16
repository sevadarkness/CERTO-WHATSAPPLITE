<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Requests;

/**
 * RemarketingDisparosRequest
 *
 * Envolve o payload de disparo de remarketing (normalmente JSON).
 */
final class RemarketingDisparosRequest
{
    private string $method;
    private array $body;

    public function __construct(string $method, array $body)
    {
        $this->method = strtoupper($method);
        $this->body   = $body;
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'POST');
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

        return new self($method, $body);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function payload(): array
    {
        return $this->body;
    }
}

