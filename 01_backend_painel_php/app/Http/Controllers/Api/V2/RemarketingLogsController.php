<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Controllers\Api\V2;

use Throwable;
use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Http\Requests\RemarketingLogsRequest;
use RedeAlabama\Services\Remarketing\RemarketingLogsService;

/**
 * Controller da API v2 para logs de remarketing.
 */
final class RemarketingLogsController
{
    public function __construct(
        private readonly RemarketingLogsService $service,
        private readonly array $currentUser
    ) {
    }

    public function handle(RemarketingLogsRequest $request): void
    {
        $method = $request->method();

        if ($method !== 'GET') {
            ApiResponse::jsonError('method_not_allowed', 'Método não permitido para logs de remarketing.', 405);
            return;
        }

        try {
            $result = $this->service->listar($this->currentUser);

            $success = (bool) ($result['success'] ?? false);
            unset($result['success']);

            if ($success) {
                ApiResponse::jsonSuccess($result);
            } else {
                $msg = isset($result['error']) && is_string($result['error']) ? $result['error'] : 'Erro ao recuperar logs de remarketing.';
                ApiResponse::jsonError('remarketing_logs_error', $msg, 400, $result);
            }
        } catch (Throwable $e) {
            ApiResponse::jsonError('internal_error', 'Erro interno ao recuperar logs de remarketing.', 500, [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

