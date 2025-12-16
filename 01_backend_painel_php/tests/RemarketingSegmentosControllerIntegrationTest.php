<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingSegmentosController;
use RedeAlabama\Http\Requests\RemarketingSegmentosRequest;
use RedeAlabama\Services\Remarketing\RemarketingSegmentosService;



final class RemarketingSegmentosControllerIntegrationTest extends TestCase
{
    public function testSegmentosComSucesso(): void
    {
        $service = $this->createMock(RemarketingSegmentosService::class);

        $service->method('gerarSegmentos')
            ->with(
                ['foo' => 'bar'],
                ['data_inicio' => '2025-01-01'],
                ['id' => 1, 'nivel_acesso' => 'Administrador']
            )
            ->willReturn([
                'success'        => true,
                'total_clientes' => 2,
                'segmentos'      => ['D0_D7' => 1, 'GT_D30' => 1],
                'clientes'       => [],
            ]);

        $controller = new RemarketingSegmentosController($service, ['id' => 1, 'nivel_acesso' => 'Administrador']);
        $request = new RemarketingSegmentosRequest('POST', ['foo' => 'bar'], ['data_inicio' => '2025-01-01']);

        ob_start();
        $controller->handle($request);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);

        $this->assertTrue($decoded['ok']);
        $this->assertSame(2, $decoded['data']['total_clientes']);
        $this->assertSame(1, $decoded['data']['segmentos']['D0_D7']);
        $this->assertSame(1, $decoded['data']['segmentos']['GT_D30']);
    }

    public function testSegmentosMetodoNaoPermitido(): void
    {
        $service = $this->createMock(RemarketingSegmentosService::class);
        $controller = new RemarketingSegmentosController($service, ['id' => 1, 'nivel_acesso' => 'Administrador']);
        $request = new RemarketingSegmentosRequest('DELETE', [], []);

        ob_start();
        $controller->handle($request);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);

        $this->assertFalse($decoded['ok']);
        $this->assertSame('method_not_allowed', $decoded['error']['code']);
    }
}

