<?php
declare(strict_types=1);

namespace RedeAlabama\Services\Auth;

use RedeAlabama\Repositories\UsuarioRepository;
use function log_app_event;

final class LoginService
{
    public function __construct(
        private readonly UsuarioRepository $usuarios
    ) {
    }

    /**
     * Autentica o usuário pelo telefone e senha.
     *
     * @return array{ok:bool,user:?array,error:?string}
     */
    public function authenticate(string $telefone, string $senha, bool $lembrar): array
    {
        $telefone = trim($telefone);
        $senha    = trim($senha);

        if ($telefone === '' || $senha === '') {
            return [
                'ok'    => false,
                'user'  => null,
                'error' => 'Informe telefone e senha.',
            ];
        }

        $user = $this->usuarios->findByTelefone($telefone);

        if (!$user || !isset($user['senha']) || !password_verify($senha, (string)$user['senha'])) {
            log_app_event('auth', 'login_falha', [
                'telefone' => $telefone,
            ]);

            return [
                'ok'    => false,
                'user'  => null,
                'error' => 'Telefone ou senha inválidos.',
            ];
        }

        // Login bem-sucedido
        log_app_event('auth', 'login_sucesso', [
            'usuario_id' => $user['id'] ?? null,
            'telefone'   => $telefone,
        ]);

        return [
            'ok'    => true,
            'user'  => $user,
            'error' => null,
        ];
    }
}
