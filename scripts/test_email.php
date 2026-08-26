<?php
// Verifies your SMTP settings by sending one real email.
//
//   php scripts/test_email.php you@example.com
//
// On Railway: open the service, click the shell/exec option, and run the same
// command. It prints the full SMTP conversation when something goes wrong, so
// you can see exactly which step the server rejected.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';

$to = $argv[1] ?? '';

if ($to === '') {
    fwrite(STDERR, "Usage: php scripts/test_email.php recipient@example.com\n");
    exit(1);
}

$c = mailerConfig();

echo "=== SMTP configuration ===\n";
printf("  host      : %s\n", $c['host'] !== '' ? $c['host'] : '(not set)');
printf("  port      : %d\n", $c['port']);
printf("  security  : %s\n", $c['secure']);
printf("  username  : %s\n", $c['user'] !== '' ? $c['user'] : '(none — sending unauthenticated)');
printf("  password  : %s\n", $c['pass'] !== '' ? str_repeat('*', 8) : '(none)');
printf("  from      : %s <%s>\n", $c['fromName'], $c['from'] !== '' ? $c['from'] : '(not set)');
echo "\n";

if (!mailerConfigured()) {
    fwrite(STDERR, "SMTP is not configured. Set at least SMTP_HOST and SMTP_USER (or SMTP_FROM).\n");
    fwrite(STDERR, "See the 'Email notifications' section of README.md.\n");
    exit(1);
}

echo "Sending to {$to} ...\n\n";

$subject = 'Test email from ' . APP_NAME;
$body = "This is a test message from " . APP_NAME . ".\n\n"
    . "If you are reading this, SMTP is configured correctly and members will\n"
    . "receive savings reminders, loan updates and meeting notices by email.\n\n"
    . "Sent: " . date('Y-m-d H:i:s T');

$result = sendMail($to, '', $subject, $body, notificationEmailHtml($subject, $body, 'tester'));

if ($result['ok']) {
    echo "SUCCESS - the server accepted the message.\n";
    echo "Check the inbox for {$to} (and the spam folder).\n";
    exit(0);
}

fwrite(STDERR, "FAILED: {$result['error']}\n\n");

if ($result['log'] !== '') {
    fwrite(STDERR, "=== SMTP conversation ===\n{$result['log']}\n");
}

fwrite(STDERR, "Common causes:\n");
fwrite(STDERR, "  - Gmail/Outlook need an APP PASSWORD, not your normal login password.\n");
fwrite(STDERR, "  - Wrong SMTP_SECURE: use 'tls' with port 587, or 'ssl' with port 465.\n");
fwrite(STDERR, "  - The host blocks outbound SMTP; try a provider API instead.\n");
exit(1);
