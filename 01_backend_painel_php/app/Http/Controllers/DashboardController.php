<?php
declare(strict_types=1);

namespace RedeAlabama\Http\Controllers;

use RedeAlabama\Http\Controller;

final class DashboardController extends Controller
{
    public function index(int $tenantId): void
    {
        $this->json([
            'ok'        => true,
            'tenant_id' => $tenantId,
            'message'   => 'Bem-vindo ao Router V6 Ultra Enterprise.',
        ]);
    }
}
