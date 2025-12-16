<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Controllers\Api\V2;

use Throwable;
use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Http\Requests\EntregadoresRequest;
use RedeAlabama\Services\Entregadores\EntregadoresService;

/**
 * Controller da API v2 para Entregadores.
 */
final class EntregadoresController
{
    public function __construct(
        private readonly EntregadoresService $service,
        private readonly array $currentUser
    ) {
    }

    public function handle(EntregadoresRequest $request): void
    {
        $method = $request->method();

        if ($method !== 'GET') {
            ApiResponse::jsonError('method_not_allowed', 'Método não permitido para entregadores.', 405);
            return;
        }

        try {
            $result = $this->service->listEntregadores($request->filters(), $this->currentUser);

            $success = (bool) ($result['success'] ?? false);
            unset($result['success']);

            if ($success) {
                ApiResponse::jsonSuccess($result);
            } else {
                $msg = isset($result['error']) && is_string($result['error']) ? $result['error'] : 'Erro ao consultar entregadores.';
                ApiResponse::jsonError('entregadores_error', $msg, 400, $result);
            }
        } catch (Throwable $e) {
            ApiResponse::jsonError('internal_error', 'Erro interno ao consultar entregadores.', 500, [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

