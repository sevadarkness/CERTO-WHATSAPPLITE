<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RedeAlabama\Repositories\UsuarioRepository;
use RedeAlabama\Services\Auth\LoginService;



final class AuthLoginServiceTest extends TestCase
{
    public function testLoginFailsWithEmptyCredentials(): void
    {
        $repo    = $this->createMock(UsuarioRepository::class);
        $service = new LoginService($repo);

        $result = $service->authenticate('', '', false);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['user']);
        $this->assertSame('Informe telefone e senha.', $result['error']);
    }

    public function testLoginFailsWithInvalidUser(): void
    {
        $repo = $this->createMock(UsuarioRepository::class);
        $repo->method('findByTelefone')->willReturn(null);

        $service = new LoginService($repo);
        $result  = $service->authenticate('11999999999', 'senha', false);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['user']);
        $this->assertSame('Telefone ou senha inválidos.', $result['error']);
    }

    public function testLoginSucceedsWithValidUser(): void
    {
        $hash  = password_hash('segredo', PASSWORD_DEFAULT);
        $user  = ['id' => 10, 'telefone' => '11999999999', 'senha' => $hash];

        $repo = $this->createMock(UsuarioRepository::class);
        $repo->method('findByTelefone')->willReturn($user);

        $service = new LoginService($repo);
        $result  = $service->authenticate('11999999999', 'segredo', true);

        $this->assertTrue($result['ok']);
        $this->assertNotNull($result['user']);
        $this->assertNull($result['error']);
    }
}
