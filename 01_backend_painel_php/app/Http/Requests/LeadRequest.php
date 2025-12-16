<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Requests;

use InvalidArgumentException;

/**
 * LeadRequest
 *
 * Responsável por:
 *  - Ler e normalizar dados de entrada (JSON ou x-www-form-urlencoded)
 *  - Expor apenas os campos necessários para o caso de uso de Leads
 *  - Centralizar regras básicas de validação de request (não de negócio).
 */
final class LeadRequest
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

    /**
     * Cria instância a partir dos superglobais.
     */
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
     * Valida payload para criação de Lead (POST /api/v2/leads).
     *
     * Retorna apenas os campos relevantes, já normalizados.
     *
     * @return array{telefone:string, nome?:string, origem?:string, url_origem?:?string, observacao?:?string, status?:string}
     */
    public function validatedForStore(): array
    {
        $data = $this->body;

        $telefone = isset($data['telefone']) ? trim((string) $data['telefone']) : '';
        if ($telefone === '') {
            throw new InvalidArgumentException('Campo telefone é obrigatório.');
        }

        $payload = [
            'telefone' => $telefone,
        ];

        // Campos opcionais
        $map = [
            'nome',
            'origem',
            'url_origem',
            'observacao',
            'status',
        ];

        foreach ($map as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if (is_string($value)) {
                    $value = trim($value);
                }
                $payload[$field] = $value;
            }
        }

        return $payload;
    }

    /**
     * Extrai telefone da querystring para GET /api/v2/leads.
     */
    public function getTelefoneFromQuery(): ?string
    {
        $q = $this->query;

        $telefone = null;
        if (isset($q['telefone'])) {
            $telefone = trim((string) $q['telefone']);
        } elseif (isset($q['phone'])) {
            $telefone = trim((string) $q['phone']);
        }

        return $telefone !== '' ? $telefone : null;
    }

    /**
     * Extrai ID numérico da querystring para GET /api/v2/leads.
     */
    public function getIdFromQuery(): ?int
    {
        $q = $this->query;

        if (isset($q['id']) && $q['id'] !== '') {
            $id = filter_var($q['id'], FILTER_VALIDATE_INT);
            if ($id !== false && $id > 0) {
                return (int) $id;
            }
        }

        return null;
    }
}

