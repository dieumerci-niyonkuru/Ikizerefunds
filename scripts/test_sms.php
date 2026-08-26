<?php
// Verifies your SMS gateway by sending one real text message.
//
//   php scripts/test_sms.php 0790974685
//   php scripts/test_sms.php +250790974685
//
// Numbers may be written the local way (07XXXXXXXX) — they are converted to
// E.164 automatically. On failure the raw gateway response is printed.
//
// Tip: set AT_USERNAME=sandbox with a sandbox API key to test for free.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/sms.php';

$to = $argv[1] ?? '';

if ($to === '') {
    fwrite(STDERR, "Usage: php scripts/test_sms.php 0790974685\n");
    exit(1);
}

$c = smsConfig();

echo "=== SMS configuration ===\n";
printf("  provider  : %s\n", $c['provider'] !== '' ? $c['provider'] : '(none detected)');

if ($c['provider'] === 'twilio') {
    printf("  account   : %s\n", $c['twSid'] !== '' ? substr($c['twSid'], 0, 8) . '...' : '(not set)');
    printf("  token     : %s\n", $c['twToken'] !== '' ? str_repeat('*', 8) : '(not set)');
    printf("  from      : %s\n", $c['twFrom'] !== '' ? $c['twFrom'] : '(not set)');
} else {
    printf("  username  : %s%s\n", $c['atUser'] !== '' ? $c['atUser'] : '(not set)',
        $c['atUser'] === 'sandbox' ? '   [sandbox — no credit used]' : '');
    printf("  api key   : %s\n", $c['atKey'] !== '' ? str_repeat('*', 8) : '(not set)');
    printf("  sender id : %s\n", $c['atSender'] !== '' ? $c['atSender'] : '(default shortcode)');
}

printf("  fallback  : %s\n", $c['fallback'] ? 'on (members without email get SMS)' : 'off');
echo "\n";

if (!smsConfigured()) {
    fwrite(STDERR, "No SMS gateway is configured.\n");
    fwrite(STDERR, "Set AT_USERNAME + AT_API_KEY (Africa's Talking), or\n");
    fwrite(STDERR, "TWILIO_SID + TWILIO_TOKEN + TWILIO_FROM (Twilio).\n");
    fwrite(STDERR, "See the 'SMS notifications' section of README.md.\n");
    exit(1);
}

$normalised = normalisePhone($to);

if ($normalised === null) {
    fwrite(STDERR, "Could not read '{$to}' as a phone number.\n");
    fwrite(STDERR, "Use 07XXXXXXXX, 2507XXXXXXXX, or +2507XXXXXXXX.\n");
    exit(1);
}

echo "Sending to {$normalised} (from \"{$to}\") ...\n\n";

$result = sendSms($normalised, APP_NAME . ': test message. If you received this, SMS notifications are working.');

if ($result['ok']) {
    echo "SUCCESS - the gateway accepted the message.\n";
    echo "Check the handset. Delivery can take a few seconds.\n\n";
    echo "=== gateway response ===\n{$result['log']}\n";
    exit(0);
}

fwrite(STDERR, "FAILED: {$result['error']}\n\n");

if ($result['log'] !== '') {
    fwrite(STDERR, "=== gateway response ===\n{$result['log']}\n\n");
}

fwrite(STDERR, "Common causes:\n");
fwrite(STDERR, "  - Africa's Talking: sender ID not yet approved, or no credit on the account.\n");
fwrite(STDERR, "  - Africa's Talking: live API key used against the sandbox username (or vice versa).\n");
fwrite(STDERR, "  - Twilio: trial accounts can only text VERIFIED numbers.\n");
fwrite(STDERR, "  - Wrong country code — Rwandan mobiles are +250 7XXXXXXXX.\n");
exit(1);
