<?php
declare(strict_types=1);

/**
 * Implementação simples de TOTP (Google Authenticator compatível) baseada em HMAC-SHA1.
 *
 * Para produção, use uma lib auditada. Aqui é apenas um baseline.
 */

function alabama_mfa_secret(): string
{
    $envSecret = getenv('ALABAMA_MFA_SECRET_ADMIN') ?: '';
    if ($envSecret !== '') {
        return $envSecret;
    }
    // Fallback fraco: apenas para ambientes de teste.
    return 'ALABAMA_DEMO_SECRET_1234';
}

function alabama_mfa_current_code(string $secret = null, int $window = 0): string
{
    $secret = $secret ?? alabama_mfa_secret();
    $timeStep = 30;
    $t = (int) floor(time() / $timeStep) + $window;
    $key = $secret;

    $binTime = pack('N*', 0) . pack('N*', $t);
    $hash = hash_hmac('sha1', $binTime, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    $code = $truncated % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

function alabama_mfa_verify_code(string $code): bool
{
    $code = trim($code);
    if ($code === '' || !ctype_digit($code)) {
        return false;
    }
    // Janela de tolerância +/- 1 step
    foreach ([-1, 0, 1] as $w) {
        if (alabama_mfa_current_code(null, $w) === $code) {
            return true;
        }
    }
    return false;
}
