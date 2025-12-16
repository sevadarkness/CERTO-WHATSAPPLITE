<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Http\Controllers\Api\V2\LeadsController;
use RedeAlabama\Http\Requests\LeadRequest;
use RedeAlabama\Services\Lead\LeadsService;



/**
 * Testes de integração básicos da stack:
 *   LeadRequest + LeadsController + ApiResponse
 *
 * Não sobe HTTP real, mas valida o contrato de saída JSON da API v2.
 */
final class LeadsControllerIntegrationTest extends TestCase
{
    public function testPostCriaLeadComPayloadValido(): void
    {
        $service = $this->createMock(LeadsService::class);

        $service->method('createLead')
            ->with(
                $this->callback(function (array $payload): bool {
                    return isset($payload['telefone']) && $payload['telefone'] === '11999999999';
                }),
                $this->equalTo(['id' => 10, 'nivel_acesso' => 'Vendedor'])
            )
            ->willReturn([
                'lead_id'     => 123,
                'telefone'    => '11999999999',
                'status'      => 'novo',
                'was_created' => true,
                'message'     => 'Lead criado com sucesso.',
            ]);

        $controller = new LeadsController($service, ['id' => 10, 'nivel_acesso' => 'Vendedor']);

        $request = new LeadRequest('POST', [
            'telefone' => '11999999999',
            'nome'     => 'Cliente Teste',
        ], []);

        ob_start();
        $controller->handle($request);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertTrue($decoded['ok']);
        $this->assertSame(123, $decoded['data']['lead_id']);
        $this->assertSame('11999999999', $decoded['data']['telefone']);
    }

    public function testGetRetornaErroQuandoLeadNaoEncontrado(): void
    {
        $service = $this->createMock(LeadsService::class);

        $service->method('getLead')
            ->with('11999999999', null)
            ->willReturn(null);

        $controller = new LeadsController($service, ['id' => 10, 'nivel_acesso' => 'Vendedor']);

        $request = new LeadRequest('GET', [], ['telefone' => '11999999999']);

        ob_start();
        $controller->handle($request);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['ok']);
        $this->assertSame('lead_not_found', $decoded['error']['code']);
    }
}

