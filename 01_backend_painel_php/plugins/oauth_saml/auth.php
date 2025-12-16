<?php
declare(strict_types=1);

/**
 * Helper de integração OAuth2/SAML para o painel Rede Alabama.
 *
 * Esta implementação não amarra em nenhum IdP específico: ela apenas
 * monta a URL de login e faz o POST de troca de código por token.
 * Para usar em produção, configure as variáveis de ambiente:
 *
 *  - OAUTH_AUTHORIZE_URL
 *  - OAUTH_TOKEN_URL
 *  - OAUTH_CLIENT_ID
 *  - OAUTH_CLIENT_SECRET
 *  - OAUTH_REDIRECT_URI
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Monta a URL de login no provedor OAuth2.
 */
function alabama_oauth_login_url(): string
{
    $authorizeUrl = getenv('OAUTH_AUTHORIZE_URL') ?: '';
    $clientId     = getenv('OAUTH_CLIENT_ID') ?: '';
    $redirectUri  = getenv('OAUTH_REDIRECT_URI') ?: '';

    if ($authorizeUrl === '' || $clientId === '' || $redirectUri === '') {
        throw new RuntimeException('OAuth não configurado (verifique variáveis de ambiente).');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
        'response_type' => 'code',
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'scope'         => 'openid profile email',
        'state'         => $state,
    ]);

    return $authorizeUrl . '?' . $params;
}

/**
 * Trata o callback OAuth2/SAML, troca código por token e devolve o payload.
 *
 * @return array<string,mixed>
 */
function alabama_handle_oauth_callback(): array
{
    if (!isset($_GET['code'], $_GET['state'], $_SESSION['oauth_state'])) {
        throw new RuntimeException('Callback OAuth inválido (code/state ausente).');
    }

    if (!hash_equals($_SESSION['oauth_state'], (string)$_GET['state'])) {
        throw new RuntimeException('State OAuth inválido.');
    }

    $tokenUrl     = getenv('OAUTH_TOKEN_URL') ?: '';
    $clientId     = getenv('OAUTH_CLIENT_ID') ?: '';
    $clientSecret = getenv('OAUTH_CLIENT_SECRET') ?: '';
    $redirectUri  = getenv('OAUTH_REDIRECT_URI') ?: '';

    if ($tokenUrl === '' || $clientId === '' || $clientSecret === '' || $redirectUri === '') {
        throw new RuntimeException('Token endpoint OAuth não configurado.');
    }

    $payload = [
        'grant_type'    => 'authorization_code',
        'code'          => $_GET['code'],
        'redirect_uri'  => $redirectUri,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS     => http_build_query($payload),
    ]);

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status !== 200) {
        throw new RuntimeException('Falha na requisição de token OAuth: ' . ($error ?: 'status ' . $status));
    }

    /** @var array<string,mixed>|null $json */
    $json = json_decode((string)$body, true);
    if (!is_array($json)) {
        throw new RuntimeException('Resposta de token OAuth inválida.');
    }

    return $json;
}

// Rota de exemplo para uso via HTTP:
//   - /plugins/oauth_saml/auth.php?action=login     -> redireciona para o IdP
//   - /plugins/oauth_saml/auth.php?action=callback  -> trata callback e imprime JSON
if (PHP_SAPI !== 'cli') {
    $action = $_GET['action'] ?? null;

    if ($action === 'login') {
        header('Location: ' . alabama_oauth_login_url());
        exit;
    }

    if ($action === 'callback') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $tokens = alabama_handle_oauth_callback();
            $_SESSION['oauth_tokens'] = $tokens;
            echo json_encode(['ok' => true, 'tokens' => $tokens], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
