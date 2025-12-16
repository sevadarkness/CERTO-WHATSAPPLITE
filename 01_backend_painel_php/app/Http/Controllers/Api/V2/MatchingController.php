<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Controllers\Api\V2;

use Throwable;
use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Http\Requests\MatchingRequest;
use RedeAlabama\Services\Matching\MatchingService;

/**
 * Controller da API v2 para Matching.
 */
final class MatchingController
{
    public function __construct(
        private readonly MatchingService $service,
        private readonly array $currentUser
    ) {
    }

    public function handle(MatchingRequest $request): void
    {
        $method = $request->method();

        if ($method !== 'POST') {
            ApiResponse::jsonError('method_not_allowed', 'Método não permitido para matching.', 405);
            return;
        }

        try {
            $result = $this->service->registerMatch($request->payload(), $this->currentUser);

            $success = (bool) ($result['success'] ?? false);
            unset($result['success']);

            if ($success) {
                ApiResponse::jsonSuccess($result);
            } else {
                $msg = isset($result['error']) && is_string($result['error']) ? $result['error'] : 'Erro ao registrar matching.';
                ApiResponse::jsonError('matching_error', $msg, 400, $result);
            }
        } catch (Throwable $e) {
            ApiResponse::jsonError('internal_error', 'Erro interno ao registrar matching.', 500, [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

