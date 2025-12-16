<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Controllers\Api\V2;

use Throwable;
use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Services\Remarketing\RemarketingStatsService;

/**
 * Controller da API v2 para estatísticas de remarketing.
 */
final class RemarketingStatsController
{
    public function __construct(
        private readonly RemarketingStatsService $service,
        private readonly array $currentUser
    ) {
    }

    public function handle(): void
    {
        try {
            $result = $this->service->stats($this->currentUser);

            $success = (bool) ($result['success'] ?? false);
            unset($result['success']);

            if ($success) {
                ApiResponse::jsonSuccess($result);
            } else {
                $msg = isset($result['error']) && is_string($result['error']) ? $result['error'] : 'Erro ao gerar estatísticas de remarketing.';
                ApiResponse::jsonError('remarketing_stats_error', $msg, 400, $result);
            }
        } catch (Throwable $e) {
            ApiResponse::jsonError('internal_error', 'Erro interno ao gerar estatísticas de remarketing.', 500, [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

