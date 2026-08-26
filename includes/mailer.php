<?php
// Minimal SMTP client.
//
// The project deliberately has no Composer dependencies, so this speaks SMTP
// over a socket rather than pulling in PHPMailer. PHP's own mail() is not an
// option: the Railway container has no local MTA, so mail() silently fails.
//
// Configure with these environment variables (see .env.example):
//   SMTP_HOST       smtp.gmail.com
//   SMTP_PORT       587
//   SMTP_USER       you@gmail.com
//   SMTP_PASS       an app password, not your login password
//   SMTP_SECURE     tls (STARTTLS, default) | ssl (implicit, port 465) | none
//   SMTP_FROM       defaults to SMTP_USER
//   SMTP_FROM_NAME  defaults to the club name
//   SMTP_TIMEOUT    seconds, default 15

require_once __DIR__ . '/../config/config.php';

function mailerConfig(): array
{
    $secure = strtolower(getenv('SMTP_SECURE') ?: 'tls');
    if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
        $secure = 'tls';
    }

    $user = getenv('SMTP_USER') ?: '';

    return [
        'host'     => getenv('SMTP_HOST') ?: '',
        'port'     => (int) (getenv('SMTP_PORT') ?: ($secure === 'ssl' ? 465 : 587)),
        'user'     => $user,
        'pass'     => getenv('SMTP_PASS') ?: '',
        'secure'   => $secure,
        'from'     => getenv('SMTP_FROM') ?: $user,
        'fromName' => getenv('SMTP_FROM_NAME') ?: APP_NAME,
        'timeout'  => (int) (getenv('SMTP_TIMEOUT') ?: 15),
    ];
}

// Email delivery is optional. Everything still lands in the in-app inbox, so
// the app must work unconfigured rather than erroring.
function mailerConfigured(): bool
{
    $c = mailerConfig();

    return $c['host'] !== '' && $c['from'] !== '';
}

// Reads one SMTP reply, following multi-line continuations ("250-" vs "250 ").
function smtpRead($socket, string &$transcript): string
{
    $data = '';
    while (($line = fgets($socket, 615)) !== false) {
        $data .= $line;
        $transcript .= '< ' . rtrim($line) . "\n";
        // A space in the 4th column marks the final line of the reply.
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function smtpWrite($socket, string $command, string &$transcript, bool $secret = false): void
{
    $transcript .= '> ' . ($secret ? '***' : rtrim($command)) . "\n";
    fwrite($socket, $command . "\r\n");
}

// Sends one command and asserts the reply starts with an expected code.
function smtpCommand($socket, string $command, array $expect, string &$transcript, bool $secret = false): array
{
    if ($command !== '') {
        smtpWrite($socket, $command, $transcript, $secret);
    }

    $reply = smtpRead($socket, $transcript);
    $code  = (int) substr($reply, 0, 3);

    if (!in_array($code, $expect, true)) {
        return ['ok' => false, 'code' => $code, 'reply' => trim($reply)];
    }

    return ['ok' => true, 'code' => $code, 'reply' => trim($reply)];
}

// RFC 2047 encoding so non-ASCII subjects and display names survive.
function mimeHeaderEncode(string $text): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $text)) {
        return $text;
    }

    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

/**
 * Sends one message. Returns ['ok' => bool, 'error' => string, 'log' => string].
 * Never throws — callers record the outcome against the notification row.
 */
function sendMail(string $toEmail, string $toName, string $subject, string $bodyText, ?string $bodyHtml = null): array
{
    $c = mailerConfig();
    $transcript = '';

    if (!mailerConfigured()) {
        return ['ok' => false, 'error' => 'SMTP is not configured', 'log' => ''];
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient address: ' . $toEmail, 'log' => ''];
    }

    $host = $c['secure'] === 'ssl' ? 'ssl://' . $c['host'] : $c['host'];

    $context = stream_context_create([
        'ssl' => ['SNI_enabled' => true, 'peer_name' => $c['host']],
    ]);

    $socket = @stream_socket_client(
        $host . ':' . $c['port'],
        $errno,
        $errstr,
        $c['timeout'],
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        return [
            'ok'    => false,
            'error' => sprintf('Cannot reach %s:%d - %s (%d)', $c['host'], $c['port'], $errstr ?: 'connection failed', $errno),
            'log'   => '',
        ];
    }

    stream_set_timeout($socket, $c['timeout']);

    $fail = static function (array $step, string $stage) use ($socket, &$transcript): array {
        @fclose($socket);

        return [
            'ok'    => false,
            'error' => sprintf('%s failed (%d): %s', $stage, $step['code'], $step['reply']),
            'log'   => $transcript,
        ];
    };

    // Greeting
    $step = smtpCommand($socket, '', [220], $transcript);
    if (!$step['ok']) {
        return $fail($step, 'Server greeting');
    }

    $ehloName = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';

    $step = smtpCommand($socket, 'EHLO ' . $ehloName, [250], $transcript);
    if (!$step['ok']) {
        return $fail($step, 'EHLO');
    }

    // Upgrade the plaintext connection before authenticating.
    if ($c['secure'] === 'tls') {
        $step = smtpCommand($socket, 'STARTTLS', [220], $transcript);
        if (!$step['ok']) {
            return $fail($step, 'STARTTLS');
        }

        $crypto = @stream_socket_enable_crypto(
            $socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        if (!$crypto) {
            @fclose($socket);

            return ['ok' => false, 'error' => 'TLS negotiation failed', 'log' => $transcript];
        }

        $transcript .= "--- TLS established ---\n";

        // The ESMTP feature list must be re-read inside the TLS session.
        $step = smtpCommand($socket, 'EHLO ' . $ehloName, [250], $transcript);
        if (!$step['ok']) {
            return $fail($step, 'EHLO after STARTTLS');
        }
    }

    if ($c['user'] !== '') {
        $step = smtpCommand($socket, 'AUTH LOGIN', [334], $transcript);
        if (!$step['ok']) {
            return $fail($step, 'AUTH LOGIN');
        }

        $step = smtpCommand($socket, base64_encode($c['user']), [334], $transcript);
        if (!$step['ok']) {
            return $fail($step, 'SMTP username');
        }

        $step = smtpCommand($socket, base64_encode($c['pass']), [235], $transcript, true);
        if (!$step['ok']) {
            return $fail($step, 'SMTP password');
        }
    }

    $step = smtpCommand($socket, 'MAIL FROM:<' . $c['from'] . '>', [250], $transcript);
    if (!$step['ok']) {
        return $fail($step, 'MAIL FROM');
    }

    $step = smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251], $transcript);
    if (!$step['ok']) {
        return $fail($step, 'RCPT TO');
    }

    $step = smtpCommand($socket, 'DATA', [354], $transcript);
    if (!$step['ok']) {
        return $fail($step, 'DATA');
    }

    $boundary = 'b' . bin2hex(random_bytes(12));
    $toHeader = $toName !== ''
        ? mimeHeaderEncode($toName) . ' <' . $toEmail . '>'
        : $toEmail;

    $headers = [
        'Date: ' . date('r'),
        'From: ' . mimeHeaderEncode($c['fromName']) . ' <' . $c['from'] . '>',
        'To: ' . $toHeader,
        'Subject: ' . mimeHeaderEncode($subject),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $ehloName . '>',
        'MIME-Version: 1.0',
    ];

    if ($bodyHtml !== null) {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($bodyText))
            . "\r\n--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($bodyHtml))
            . "\r\n--{$boundary}--\r\n";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode($bodyText));
    }

    // Base64 bodies cannot produce a bare "." line, but dot-stuff anyway so
    // this stays correct if the encoding is ever changed to 8bit.
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $message = preg_replace('/^\./m', '..', $message);

    fwrite($socket, $message . "\r\n.\r\n");
    $transcript .= "> [message body, " . strlen($message) . " bytes]\n";

    $step = smtpCommand($socket, '', [250], $transcript);
    if (!$step['ok']) {
        return $fail($step, 'Message body');
    }

    smtpWrite($socket, 'QUIT', $transcript);
    @fclose($socket);

    return ['ok' => true, 'error' => '', 'log' => $transcript];
}

// Local escaper: this file is used from cron/CLI as well as the web app, so it
// deliberately does not depend on includes/functions.php being loaded.
function mailerEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Wraps a notification message in the club's branding.
function notificationEmailHtml(string $title, string $message, string $recipientName): string
{
    $url = rtrim(APP_URL, '/');

    return '<!doctype html><html><body style="margin:0;padding:24px;background:#F3F4F6;'
        . 'font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" '
        . 'style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;'
        . 'border:1px solid #E5E7EB;">'
        . '<tr><td style="background:#16234B;padding:20px 24px;">'
        . '<div style="color:#fff;font-size:16px;font-weight:700;">' . mailerEscape(APP_NAME) . '</div>'
        . '<div style="color:#C9A227;font-size:10px;font-weight:700;letter-spacing:2px;'
        . 'text-transform:uppercase;margin-top:4px;">Savings &amp; Credit Club</div>'
        . '</td></tr>'
        . '<tr><td style="padding:24px;">'
        . '<p style="margin:0 0 12px;font-size:15px;font-weight:700;color:#0D1730;">' . mailerEscape($title) . '</p>'
        . '<p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#4B5563;">'
        . nl2br(mailerEscape($message)) . '</p>'
        . '<a href="' . mailerEscape($url) . '/login.php" style="display:inline-block;background:#C9A227;'
        . 'color:#0D1730;font-weight:700;font-size:14px;text-decoration:none;padding:11px 22px;'
        . 'border-radius:999px;">Open your dashboard</a>'
        . '</td></tr>'
        . '<tr><td style="padding:16px 24px;background:#F9FAFB;border-top:1px solid #E5E7EB;'
        . 'font-size:11px;color:#6B7280;">'
        . 'Sent to ' . mailerEscape($recipientName) . ' by ' . mailerEscape(APP_NAME)
        . ' &middot; Tumba College, Rulindo District, Rwanda'
        . '</td></tr></table></body></html>';
}
