<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Controllers\Api\V2;

use Throwable;
use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Http\Requests\RemarketingDisparosRequest;
use RedeAlabama\Services\Remarketing\RemarketingDisparosService;

/**
 * Controller da API v2 para disparos de remarketing.
 */
final class RemarketingDisparosController
{
    public function __construct(
        private readonly RemarketingDisparosService $service,
        private readonly array $currentUser
    ) {
    }

    public function handle(RemarketingDisparosRequest $request): void
    {
        $method = $request->method();

        if ($method !== 'POST') {
            ApiResponse::jsonError('method_not_allowed', 'Método não permitido para disparos de remarketing.', 405);
            return;
        }

        try {
            $result = $this->service->disparar($request->payload(), $this->currentUser);

            $success = (bool) ($result['success'] ?? false);
            unset($result['success']);

            if ($success) {
                ApiResponse::jsonSuccess($result);
            } else {
                $msg = isset($result['error']) && is_string($result['error']) ? $result['error'] : 'Erro ao processar disparos de remarketing.';
                ApiResponse::jsonError('remarketing_disparos_error', $msg, 400, $result);
            }
        } catch (Throwable $e) {
            ApiResponse::jsonError('internal_error', 'Erro interno ao processar disparos de remarketing.', 500, [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

