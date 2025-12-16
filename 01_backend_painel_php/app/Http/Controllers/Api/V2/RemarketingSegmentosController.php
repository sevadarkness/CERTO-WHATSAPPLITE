<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Controllers\Api\V2;

use Throwable;
use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Http\Requests\RemarketingSegmentosRequest;
use RedeAlabama\Services\Remarketing\RemarketingSegmentosService;

/**
 * Controller da API v2 para segmentos de remarketing.
 */
final class RemarketingSegmentosController
{
    public function __construct(
        private readonly RemarketingSegmentosService $service,
        private readonly array $currentUser
    ) {
    }

    public function handle(RemarketingSegmentosRequest $request): void
    {
        $method = $request->method();

        if (!in_array($method, ['GET','POST'], true)) {
            ApiResponse::jsonError('method_not_allowed', 'Método não permitido para segmentos de remarketing.', 405);
            return;
        }

        try {
            $result = $this->service->gerarSegmentos($request->body(), $request->query(), $this->currentUser);

            $success = (bool) ($result['success'] ?? false);
            unset($result['success']);

            if ($success) {
                ApiResponse::jsonSuccess($result);
            } else {
                $msg = isset($result['error']) && is_string($result['error']) ? $result['error'] : 'Erro ao gerar segmentos de remarketing.';
                ApiResponse::jsonError('remarketing_segmentos_error', $msg, 400, $result);
            }
        } catch (Throwable $e) {
            ApiResponse::jsonError('internal_error', 'Erro interno ao gerar segmentos de remarketing.', 500, [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

