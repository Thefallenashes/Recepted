<?php

function build_app_base_url(): string
{
    $fromEnv = trim((string)(getenv('APP_BASE_URL') ?: ''));
    if ($fromEnv !== '') {
        return rtrim($fromEnv, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '/';
    $scriptDir = str_replace('\\', '/', $scriptDir);

    if ($scriptDir === '/' || $scriptDir === '.') {
        return $scheme . '://' . $host;
    }

    return $scheme . '://' . $host . rtrim($scriptDir, '/');
}

function generate_email_verification_token(): string
{
    return bin2hex(random_bytes(32));
}

function email_verification_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function build_email_verification_link(string $token): string
{
    return build_app_base_url() . '/verify_email.php?token=' . urlencode($token);
}

function send_account_verification_email(string $recipientEmail, string $token): bool
{
    $verifyLink = build_email_verification_link($token);
    $subject = 'Verifica tu cuenta en Recepted';
    $message = "Hola,\n\n";
    $message .= "Gracias por registrarte en Recepted.\n";
    $message .= "Para activar tu cuenta, verifica tu correo haciendo clic en este enlace:\n\n";
    $message .= $verifyLink . "\n\n";
    $message .= "Si no solicitaste esta cuenta, puedes ignorar este correo.\n";

    $from = trim((string)(getenv('MAIL_FROM') ?: 'no-reply@recepted.local'));
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: Recepted <' . $from . '>';

    $ok = @mail($recipientEmail, $subject, $message, implode("\r\n", $headers));
    if (!$ok) {
        error_log('No se pudo enviar el correo de verificacion a ' . $recipientEmail);
    }

    return $ok;
}
