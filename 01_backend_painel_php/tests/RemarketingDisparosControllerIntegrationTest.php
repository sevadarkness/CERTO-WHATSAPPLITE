<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Http\Controllers\Api\V2\RemarketingDisparosController;
use RedeAlabama\Http\Requests\RemarketingDisparosRequest;
use RedeAlabama\Services\Remarketing\RemarketingDisparosService;



final class RemarketingDisparosControllerIntegrationTest extends TestCase
{
    public function testDisparosComSucesso(): void
    {
        $service = $this->createMock(RemarketingDisparosService::class);

        $service->method('disparar')
            ->with(
                $this->callback(function (array $payload): bool {
                    return ($payload['campanha'] ?? null) === 'teste';
                }),
                $this->equalTo(['id' => 1, 'nivel_acesso' => 'Administrador'])
            )
            ->willReturn([
                'success' => true,
                'payload' => ['campanha' => 'teste'],
            ]);

        $controller = new RemarketingDisparosController($service, ['id' => 1, 'nivel_acesso' => 'Administrador']);
        $request = new RemarketingDisparosRequest('POST', ['campanha' => 'teste']);

        ob_start();
        $controller->handle($request);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertTrue($decoded['ok']);
        $this->assertSame('teste', $decoded['data']['payload']['campanha']);
    }

    public function testDisparosMetodoNaoPermitido(): void
    {
        $service = $this->createMock(RemarketingDisparosService::class);
        $controller = new RemarketingDisparosController($service, ['id' => 1, 'nivel_acesso' => 'Administrador']);
        $request = new RemarketingDisparosRequest('GET', []);

        ob_start();
        $controller->handle($request);
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertFalse($decoded['ok']);
        $this->assertSame('method_not_allowed', $decoded['error']['code']);
    }
}

