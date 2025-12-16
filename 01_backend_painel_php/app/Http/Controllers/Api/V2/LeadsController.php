<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Controllers\Api\V2;

use InvalidArgumentException;
use Throwable;
use RedeAlabama\Support\ApiResponse;
use RedeAlabama\Http\Requests\LeadRequest;
use RedeAlabama\Services\Lead\LeadsService;

/**
 * Controller da API v2 para Leads.
 *
 * Orquestra:
 *  - Validação de request (LeadRequest)
 *  - Chamada ao serviço de domínio (LeadsService)
 *  - Padronização de respostas via ApiResponse
 */
final class LeadsController
{
    public function __construct(
        private readonly LeadsService $service,
        private readonly array $currentUser
    ) {
    }

    /**
     * Ponto de entrada único para /api/v2/leads
     * Decide o fluxo com base no método HTTP.
     */
    public function handle(LeadRequest $request): void
    {
        $method = $request->method();

        try {
            if ($method === 'POST') {
                $payload = $request->validatedForStore();
                $result  = $this->service->createLead($payload, $this->currentUser);
                ApiResponse::jsonSuccess($result);
                return;
            }

            if ($method === 'GET') {
                $telefone = $request->getTelefoneFromQuery();
                $id       = $request->getIdFromQuery();

                $lead = $this->service->getLead($telefone, $id);
                if ($lead === null) {
                    ApiResponse::jsonError('lead_not_found', 'Lead não encontrado.', 404);
                    return;
                }

                ApiResponse::jsonSuccess(['lead' => $lead]);
                return;
            }

            ApiResponse::jsonError('method_not_allowed', 'Método não permitido.', 405);
        } catch (InvalidArgumentException $e) {
            ApiResponse::jsonError('invalid_payload', $e->getMessage(), 400);
        } catch (Throwable $e) {
            ApiResponse::jsonError('internal_error', 'Erro interno ao processar lead.', 500, [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

