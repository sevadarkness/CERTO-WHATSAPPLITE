<?php
/**
 * RBAC simples baseado em sessão (V14 Ultra).
 *
 * Uso:
 *   require_once __DIR__ . '/rbac.php';
 *   require_role(['Administrador']); // ou ['Administrador', 'Gerente']
 */

declare(strict_types=1);

require_once __DIR__ . '/session_bootstrap.php';

/**
 * Detecta se a requisição atual é "API-like" (espera JSON), para evitar redirects
 * em endpoints /api/* ou chamadas AJAX.
 */
function alabama_is_api_request(): bool
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    if (str_starts_with($path, '/api/')) {
        return true;
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (str_contains($accept, 'application/json')) {
        return true;
    }

    $xhr = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($xhr === 'xmlhttprequest') {
        return true;
    }

    return false;
}

/**
 * Deriva o "base path" público do app (ex.: "" ou "/adm"), baseado no SCRIPT_NAME.
 * Serve para gerar URLs absolutas consistentes para login/MFA mesmo em subpastas.
 */
function alabama_base_web_path(): string
{
    $baseFromEnv = trim((string) (getenv('ALABAMA_BASE_PATH') ?: ''));
    if ($baseFromEnv !== '') {
        // Normaliza: sempre começa com / e nunca termina com /
        $baseFromEnv = '/' . trim($baseFromEnv, '/');
        return $baseFromEnv === '/' ? '' : $baseFromEnv;
    }

    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '') {
        return '';
    }

    foreach (['/modules/', '/api/', '/exports/', '/plugins/', '/tests/', '/assets/', '/themes/', '/routes/'] as $needle) {
        $pos = strpos($script, $needle);
        if ($pos !== false) {
            return rtrim(substr($script, 0, $pos), '/');
        }
    }

    // Script no root: dirname("/painel_admin.php") == "/"
    $dir = rtrim(dirname($script), '/');
    return $dir === '' || $dir === '.' || $dir === '/' ? '' : $dir;
}

function alabama_join_base(string $base, string $path): string
{
    $base = rtrim($base, '/');
    $path = '/' . ltrim($path, '/');
    return ($base === '' ? '' : $base) . $path;
}

/**
 * Retorna o usuário atual a partir da sessão, ou null se não autenticado.
 *
 * @return array|null
 */
function current_user(): ?array
{
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    return [
        'id'           => $_SESSION['usuario_id'],
        'nivel_acesso' => $_SESSION['nivel_acesso'] ?? null,
        'nome'         => $_SESSION['nome_usuario'] ?? null,
    ];
}

/**
 * Exige que o usuário tenha um dos papéis informados.
 *
 * @param array $allowedRoles Lista de papéis aceitos (ex.: ['Administrador', 'Gerente'])
 */
function require_role(array $allowedRoles): void
{
    $user = current_user();
    if (!$user) {
        if (alabama_is_api_request()) {
            if (!headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'ok'    => false,
                'data'  => null,
                'error' => [
                    'code'    => 'unauthenticated',
                    'message' => 'Não autenticado.',
                ],
                'meta'  => [],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $base = alabama_base_web_path();
        header('Location: ' . alabama_join_base($base, 'login.php'));
        exit;
    }

    $nivel = $user['nivel_acesso'] ?? null;

    // Zero-trust: exige MFA para Administrador, quando habilitado por ambiente
    $forceMfa = getenv('ALABAMA_FORCE_MFA_ADMIN') === '1';
    if ($forceMfa && $nivel === 'Administrador') {
        if (empty($_SESSION['mfa_ok'])) {
            $target = $_SERVER['REQUEST_URI'] ?? 'painel_admin.php';
            $base = alabama_base_web_path();
            header('Location: ' . alabama_join_base($base, 'modules/auth/mfa_verify.php') . '?redirect=' . urlencode($target));
            exit;
        }
    }

    if (!$nivel || !in_array($nivel, $allowedRoles, true)) {
        if (alabama_is_api_request()) {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'ok'    => false,
                'data'  => null,
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Acesso negado.',
                ],
                'meta'  => [
                    'required_roles' => $allowedRoles,
                    'user_role'      => $nivel,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        http_response_code(403);
        echo '<h1>Acesso negado</h1>';
        echo '<p>Você não possui permissão para acessar esta área.</p>';
        exit;
    }
}
