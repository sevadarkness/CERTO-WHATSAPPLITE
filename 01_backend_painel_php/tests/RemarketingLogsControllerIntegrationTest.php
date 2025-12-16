<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingLogsController;
use RedeAlabama\Http\Requests\RemarketingLogsRequest;
use RedeAlabama\Services\Remarketing\RemarketingLogsService;



final class RemarketingLogsControllerIntegrationTest extends TestCase
{
    public function testLogsComSucesso(): void
    {
        $service = $this->createMock(RemarketingLogsService::class);

        $service->method('listar')
            ->with(['id' => 1, 'nivel_acesso' => 'Administrador'])
            ->willReturn([
                'success' => true,
                'logs'    => [
                    ['id' => 10, 'tipo' => 'DISPARO'],
                ],
            ]);

        $controller = new RemarketingLogsController($service, ['id' => 1, 'nivel_acesso' => 'Administrador']);
        $request = new RemarketingLogsRequest('GET');

        ob_start();
        $controller->handle($request);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);

        $this->assertTrue($decoded['ok']);
        $this->assertCount(1, $decoded['data']['logs']);
        $this->assertSame(10, $decoded['data']['logs'][0]['id']);
    }

    public function testLogsMetodoNaoPermitido(): void
    {
        $service = $this->createMock(RemarketingLogsService::class);
        $controller = new RemarketingLogsController($service, ['id' => 1, 'nivel_acesso' => 'Administrador']);
        $request = new RemarketingLogsRequest('POST');

        ob_start();
        $controller->handle($request);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);

        $this->assertFalse($decoded['ok']);
        $this->assertSame('method_not_allowed', $decoded['error']['code']);
    }
}

