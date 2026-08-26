<?php
// SMS delivery for notifications.
//
// Two providers are supported. Africa's Talking is the default because it has
// local Rwandan rates and shortcodes; Twilio is there for anyone already using
// it. Configure ONE of them:
//
//   Africa's Talking            Twilio
//   ------------------------    -------------------------------
//   AT_USERNAME                 TWILIO_SID
//   AT_API_KEY                  TWILIO_TOKEN
//   AT_SENDER_ID   (optional)   TWILIO_FROM   (your Twilio number)
//
// Optional:
//   SMS_PROVIDER    africastalking | twilio   (auto-detected when unset)
//   SMS_FALLBACK    1 to text members who have a phone but no email address
//   SMS_TIMEOUT     seconds, default 15
//
// Use AT_USERNAME=sandbox with a sandbox API key to test without spending
// credit — the client switches to Africa's Talking' sandbox host automatically.

require_once __DIR__ . '/../config/config.php';

function smsConfig(): array
{
    $provider = strtolower(getenv('SMS_PROVIDER') ?: '');

    if ($provider === '') {
        // Auto-detect from whichever credentials are present.
        if (getenv('AT_API_KEY')) {
            $provider = 'africastalking';
        } elseif (getenv('TWILIO_SID')) {
            $provider = 'twilio';
        }
    }

    return [
        'provider' => $provider,
        'atUser'   => getenv('AT_USERNAME') ?: '',
        'atKey'    => getenv('AT_API_KEY') ?: '',
        'atSender' => getenv('AT_SENDER_ID') ?: '',
        'twSid'    => getenv('TWILIO_SID') ?: '',
        'twToken'  => getenv('TWILIO_TOKEN') ?: '',
        'twFrom'   => getenv('TWILIO_FROM') ?: '',
        'fallback' => getenv('SMS_FALLBACK') === '1',
        'timeout'  => (int) (getenv('SMS_TIMEOUT') ?: 15),
    ];
}

// SMS is optional, exactly like email: the app must run without it.
function smsConfigured(): bool
{
    $c = smsConfig();

    if ($c['provider'] === 'africastalking') {
        return $c['atUser'] !== '' && $c['atKey'] !== '';
    }

    if ($c['provider'] === 'twilio') {
        return $c['twSid'] !== '' && $c['twToken'] !== '' && $c['twFrom'] !== '';
    }

    return false;
}

/**
 * Converts a locally-written number to E.164.
 *
 * Members are registered with numbers like "0790974685" or "+250 790 974 685";
 * every gateway wants "+250790974685".
 */
function normalisePhone(?string $raw, string $countryCode = '250'): ?string
{
    if ($raw === null) {
        return null;
    }

    $hadPlus = str_starts_with(trim($raw), '+');
    $digits  = preg_replace('/\D/', '', $raw);

    if ($digits === '') {
        return null;
    }

    // Already international.
    if ($hadPlus) {
        return strlen($digits) >= 8 ? '+' . $digits : null;
    }

    // 250XXXXXXXXX written without the plus.
    if (str_starts_with($digits, $countryCode) && strlen($digits) === strlen($countryCode) + 9) {
        return '+' . $digits;
    }

    // National format: leading 0 then 9 digits (07XXXXXXXX in Rwanda).
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '+' . $countryCode . substr($digits, 1);
    }

    // Bare subscriber number.
    if (strlen($digits) === 9) {
        return '+' . $countryCode . $digits;
    }

    return null;
}

/**
 * POSTs a form body and returns [httpStatus, responseBody, transportError].
 * Uses cURL when present and falls back to a stream context otherwise, so this
 * keeps working on a PHP build without ext-curl.
 */
function smsHttpPost(string $url, array $fields, array $headers, int $timeout, ?array $basicAuth = null): array
{
    $body = http_build_query($fields);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($basicAuth !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $basicAuth[0] . ':' . $basicAuth[1]);
        }

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        return [$status, (string) $response, $error];
    }

    if ($basicAuth !== null) {
        $headers[] = 'Authorization: Basic ' . base64_encode($basicAuth[0] . ':' . $basicAuth[1]);
    }

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $headers),
            'content'       => $body,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $response = @file_get_contents($url, false, $context);

    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
        }
    }

    return [$status, (string) $response, $response === false ? 'Request failed' : ''];
}

/**
 * Sends one SMS. Returns ['ok' => bool, 'error' => string, 'log' => string].
 * Never throws — callers record the outcome against the notification row.
 */
function sendSms(string $phone, string $message): array
{
    $c = smsConfig();

    if (!smsConfigured()) {
        return ['ok' => false, 'error' => 'No SMS gateway is configured', 'log' => ''];
    }

    $to = normalisePhone($phone);
    if ($to === null) {
        return ['ok' => false, 'error' => 'Unusable phone number: ' . $phone, 'log' => ''];
    }

    // Gateways bill per 160-character segment; keep one message to one segment.
    $message = trim($message);
    if (mb_strlen($message) > 300) {
        $message = mb_substr($message, 0, 297) . '...';
    }

    return $c['provider'] === 'twilio'
        ? sendSmsViaTwilio($c, $to, $message)
        : sendSmsViaAfricasTalking($c, $to, $message);
}

function sendSmsViaAfricasTalking(array $c, string $to, string $message): array
{
    // The sandbox account uses a separate host and costs nothing.
    $host = $c['atUser'] === 'sandbox'
        ? 'https://api.sandbox.africastalking.com'
        : 'https://api.africastalking.com';

    $fields = ['username' => $c['atUser'], 'to' => $to, 'message' => $message];
    if ($c['atSender'] !== '') {
        $fields['from'] = $c['atSender'];
    }

    [$status, $body, $transportError] = smsHttpPost(
        $host . '/version1/messaging',
        $fields,
        [
            'apiKey: ' . $c['atKey'],
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        $c['timeout']
    );

    return interpretAfricasTalkingResponse(
        $status,
        $body,
        $transportError,
        "POST {$host}/version1/messaging\nHTTP {$status}\n{$body}"
    );
}

// Split out from the HTTP call so the success and failure shapes can be tested
// without credentials or a network round trip.
function interpretAfricasTalkingResponse(int $status, string $body, string $transportError, string $log): array
{
    if ($transportError !== '') {
        return ['ok' => false, 'error' => 'Cannot reach Africa\'s Talking: ' . $transportError, 'log' => $log];
    }

    $json = json_decode($body, true);
    $recipient = $json['SMSMessageData']['Recipients'][0] ?? null;

    if ($recipient === null) {
        // No recipient block means the request itself was rejected.
        $reason = $json['SMSMessageData']['Message']
            ?? $json['message']
            ?? ($body !== '' ? substr($body, 0, 120) : 'empty response');

        return ['ok' => false, 'error' => "Africa's Talking rejected the request (HTTP {$status}): " . $reason, 'log' => $log];
    }

    // 100 processed, 101 sent, 102 queued — anything else is a failure.
    $code = (int) ($recipient['statusCode'] ?? 0);

    if (in_array($code, [100, 101, 102], true)) {
        return ['ok' => true, 'error' => '', 'log' => $log];
    }

    return [
        'ok'    => false,
        'error' => sprintf('Africa\'s Talking status %d: %s', $code, $recipient['status'] ?? 'unknown'),
        'log'   => $log,
    ];
}

function sendSmsViaTwilio(array $c, string $to, string $message): array
{
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($c['twSid']) . '/Messages.json';

    [$status, $body, $transportError] = smsHttpPost(
        $url,
        ['To' => $to, 'From' => $c['twFrom'], 'Body' => $message],
        ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        $c['timeout'],
        [$c['twSid'], $c['twToken']]
    );

    return interpretTwilioResponse($status, $body, $transportError, "POST {$url}\nHTTP {$status}\n{$body}");
}

// Split out from the HTTP call so the success and failure shapes can be tested
// without credentials or a network round trip.
function interpretTwilioResponse(int $status, string $body, string $transportError, string $log): array
{
    if ($transportError !== '') {
        return ['ok' => false, 'error' => 'Cannot reach Twilio: ' . $transportError, 'log' => $log];
    }

    $json = json_decode($body, true);

    // Twilio reports a rejected message with an error_code even on HTTP 201.
    if ($status >= 200 && $status < 300 && !empty($json['sid']) && empty($json['error_code'])) {
        return ['ok' => true, 'error' => '', 'log' => $log];
    }

    $reason = $json['message']
        ?? $json['error_message']
        ?? ($body !== '' ? substr($body, 0, 120) : 'empty response');

    return ['ok' => false, 'error' => "Twilio rejected the message (HTTP {$status}): " . $reason, 'log' => $log];
}
