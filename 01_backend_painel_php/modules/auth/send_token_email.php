<?php
/**
 * Envia token de reset de senha por e-mail.
 *
 * Correções aplicadas:
 * - Evita fatal error quando PHPMailer não está presente no ZIP
 * - Remove credenciais SMTP hardcoded (lê via .env)
 * - Retorno em JSON estruturado
 */

declare(strict_types=1);

require_once __DIR__ . '/../_support/env.php';
require_once __DIR__ . '/db.php';

rede_alabama_load_env();

header('Content-Type: application/json; charset=UTF-8');

// Por padrão, endpoints legacy de reset ficam desabilitados (evita abuso/spam).
$enabled = getenv('ALABAMA_ENABLE_LEGACY_PASSWORD_RESET') ?: (getenv('ENABLE_LEGACY_PASSWORD_RESET') ?: '');
$enabled = strtolower(trim((string)$enabled));
if (!in_array($enabled, ['1','true','yes','on'], true)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'disabled'], JSON_UNESCAPED_UNICODE);
    exit;
}


$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(['ok' => false, 'error' => 'invalid_email'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare('UPDATE usuarios SET reset_token = ? WHERE email = ?');
    $stmt->execute([$token, $email]);
} catch (Throwable $e) {
    echo json_encode([
        'ok'    => false,
        'error' => 'db_error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// SMTP via .env (opcional)
$smtpHost = getenv('SMTP_HOST') ?: '';
$smtpPort = (int) (getenv('SMTP_PORT') ?: 587);
$smtpUser = getenv('SMTP_USERNAME') ?: '';
$smtpPass = getenv('SMTP_PASSWORD') ?: '';
$smtpSecure = getenv('SMTP_SECURE') ?: 'tls';

$fromEmail = getenv('SMTP_FROM') ?: ($smtpUser ?: 'no-reply@localhost');
$fromName  = getenv('SMTP_FROM_NAME') ?: 'Rede Alabama';

$subject = getenv('RESET_TOKEN_SUBJECT') ?: 'Token de Reset de Senha';
$body    = "Seu token de reset é: {$token}";

$phpmailer = __DIR__ . '/PHPMailer/PHPMailerAutoload.php';

$sent = false;
$error = null;

if (is_file($phpmailer)) {
    require_once $phpmailer;

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        if ($smtpHost !== '') {
            $mail->Host = $smtpHost;
        }
        $mail->SMTPAuth = ($smtpUser !== '');
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $smtpPort;

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $sent = $mail->send();
        if (!$sent) {
            $error = $mail->ErrorInfo;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} else {
    // Fallback: mail() nativo
    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $sent = @mail($email, $subject, $body, $headers);
    if (!$sent) {
        $error = 'phpmailer_missing_and_mail_failed';
    }
}

echo json_encode([
    'ok'      => (bool) $sent,
    'email'   => $email,
    'message' => $sent ? 'Token enviado com sucesso.' : 'Falha ao enviar o token.',
    'error'   => $error,
], JSON_UNESCAPED_UNICODE);
