<?php
declare(strict_types=1);

use RedeAlabama\Repositories\UsuarioRepository;
use RedeAlabama\Services\Auth\LoginService;


require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/rate_limiter.php';
require_once __DIR__ . '/app/Repositories/BaseRepository.php';
require_once __DIR__ . '/app/Repositories/UsuarioRepository.php';
require_once __DIR__ . '/app/Services/Auth/LoginService.php';


$erro = $erro ?? '';
$telefone_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $erro = 'Sessão expirada. Recarregue a página e tente novamente.';
        log_app_event('security', 'csrf_login_invalid', [
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    } else {
        // Rate limiting por IP
        $rateBucket = 'login_ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!rate_limit_or_fail($rateBucket, 10, 300)) { // 10 tentativas em 5 minutos
            $erro = 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.';
            log_app_event('security', 'login_rate_limited', [
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } else {
            $telefone = trim($_POST['telefone'] ?? '');
            $senha    = (string)($_POST['senha'] ?? '');
            $telefone_input = $telefone;
            $lembrar = isset($_POST['lembrar']);

            $repo    = new UsuarioRepository($pdo);
            $service = new LoginService($repo);
            $result  = $service->authenticate($telefone, $senha, $lembrar);

            if ($result['ok'] === true && is_array($result['user'])) {
                $user = $result['user'];

                // Proteção contra fixation: novo ID após login
                session_regenerate_id(true);

                $_SESSION['usuario_id']   = $user['id'] ?? null;
                $_SESSION['nivel_acesso'] = $user['nivel_acesso'] ?? null;
                $_SESSION['nome_usuario'] = $user['nome'] ?? null;

                // Lembrar telefone?
                $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

                if ($lembrar && $telefone !== '') {
                    setcookie('remember_phone', $telefone, [
                        'expires'  => time() + (86400 * 30), // 30 dias
                        'path'     => '/',
                        'secure'   => $secureCookie,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    if (!empty($_COOKIE['remember_phone'])) {
                        setcookie('remember_phone', '', [
                            'expires'  => time() - 3600,
                            'path'     => '/',
                            'secure'   => $secureCookie,
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                    }
                }

                // Redireciona conforme o nível de acesso
                $nivel = (string)($user['nivel_acesso'] ?? '');
                if ($nivel === 'Administrador') {
                    header('Location: painel_admin.php');
                } elseif ($nivel === 'Gerente') {
                    header('Location: painel_gerente.php');
                } else {
                    header('Location: painel_vendedor_hoje.php');
                }
                exit;
            } else {
                $erro = $result['error'] ?? 'Número de telefone ou senha incorretos!';
                log_app_event('auth', 'login_falha', [
                    'telefone' => $telefone,
                    'ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            }
        }
    }
} else {
    if (!empty($_COOKIE['remember_phone'])) {
        $telefone_input = (string)$_COOKIE['remember_phone'];
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlabamaCMS - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="alabama-theme.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: radial-gradient(circle at top, #111827 0%, #030014 45%, #020617 100%);
        }

        .login-card {
            background: linear-gradient(145deg, var(--al-bg-surface), var(--al-bg-base));
            border: 1px solid var(--al-border);
            border-radius: var(--al-radius-lg);
            padding: 2.5rem;
            width: 100%;
            max-width: 440px;
            box-shadow: var(--al-shadow-xl), 0 0 0 1px rgba(139, 92, 246, 0.1);
        }

        .logo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--al-primary), var(--al-accent));
            border-radius: var(--al-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: var(--al-shadow-glow);
        }

        .brand-text {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--al-primary), var(--al-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            color: var(--al-text-muted);
            font-size: 0.9375rem;
            font-weight: 500;
        }

        .login-footer {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--al-border);
            text-align: center;
            color: var(--al-text-muted);
            font-size: 0.875rem;
        }

        .password-toggle {
            cursor: pointer;
            transition: var(--al-transition);
        }

        .password-toggle:hover {
            color: var(--al-text-primary);
        }

        @media (max-width: 576px) {
            .login-card {
                padding: 1.5rem;
            }
            .logo-icon {
                width: 56px;
                height: 56px;
                font-size: 1.75rem;
            }
            .brand-text {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-container">
            <div class="logo-icon">
                <i class="fas fa-gem"></i>
            </div>
            <h1 class="brand-text mb-0">Alabama</h1>
            <p class="subtitle mb-0">Sistema de Gestão Empresarial</p>
        </div>

        <?php if (!empty($erro)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <?= csrf_field(); ?>
            
            <div class="al-form-group">
                <label class="al-form-label">
                    <i class="fas fa-mobile-alt me-2"></i>Telefone
                </label>
                <input 
                    type="tel" 
                    class="al-input form-control" 
                    id="telefone" 
                    name="telefone" 
                    pattern="\d{11}" 
                    placeholder="32999999999" 
                    required 
                    value="<?= htmlspecialchars($telefone_input, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="tel"
                >
                <small style="color: var(--al-text-muted); font-size: 0.875rem; margin-top: 0.25rem; display: block;">
                    Exemplo: 32999999999 (DDD + número)
                </small>
            </div>

            <div class="al-form-group">
                <label class="al-form-label">
                    <i class="fas fa-lock me-2"></i>Senha
                </label>
                <div class="input-group">
                    <input 
                        type="password" 
                        class="al-input form-control" 
                        id="senha" 
                        name="senha" 
                        placeholder="Digite sua senha" 
                        required
                        autocomplete="current-password"
                    >
                    <span class="input-group-text password-toggle" style="background: var(--al-bg-elevated); border: 1px solid var(--al-border); border-left: none; color: var(--al-text-muted);">
                        <i class="fas fa-eye-slash"></i>
                    </span>
                </div>
            </div>

            <div class="al-checkbox mb-4">
                <input class="form-check-input" type="checkbox" id="lembrar" name="lembrar">
                <label style="color: var(--al-text-secondary); margin-left: 0.5rem;" for="lembrar">
                    Lembrar meu telefone
                </label>
            </div>

            <button type="submit" class="al-btn al-btn-primary w-100 mb-3">
                <i class="fas fa-sign-in-alt me-2"></i>Entrar no Sistema
            </button>

            <div class="login-footer">
                <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                    <i class="fas fa-shield-alt" style="color: var(--al-primary);"></i>
                    <span>Acesso seguro e criptografado</span>
                </div>
                <p class="mb-0">
                    &copy; <?php echo date('Y'); ?> AlabamaCMS. Todos os direitos reservados.
                </p>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script <?php echo alabama_csp_nonce_attr(); ?>>
        // Toggle Password Visibility
        document.querySelector('.password-toggle').addEventListener('click', function() {
            const password = document.getElementById('senha');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                password.type = 'password';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        });

        // Phone Number Validation
        document.getElementById('telefone').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 11);
        });

        // Form Validation
        (() => {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>