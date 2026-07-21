<?php
// Queueing and dispatch for the four notification types from the project
// spec (saving_reminder, loan_approval, payment_due, meeting_reminder,
// late_payment). Delivery is currently a stub — see dispatchPendingNotifications().

require_once __DIR__ . '/../config/database.php';

// Fills a template's {{placeholders}} with the given values and returns the text.
function renderNotificationTemplate(string $type, array $vars): string
{
    $stmt = db()->prepare('SELECT body FROM notification_templates WHERE type = ?');
    $stmt->execute([$type]);
    $body = $stmt->fetchColumn();

    if ($body === false) {
        return '';
    }

    foreach ($vars as $key => $value) {
        $body = str_replace('{{' . $key . '}}', (string) $value, $body);
    }

    return $body;
}

// Queues a notification for a user. Default channel is email; SMS requires
// a phone number and a configured SMS gateway (see dispatchPendingNotifications).
function queueNotification(int $userId, string $type, array $vars, string $channel = 'email'): void
{
    $message = renderNotificationTemplate($type, $vars);
    if ($message === '') {
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, type, channel, message, status) VALUES (?, ?, ?, ?, "pending")'
    );
    $stmt->execute([$userId, $type, $channel, $message]);
}

// Sends every pending notification. Currently a stub: it marks messages as
// "sent" without calling a real SMS/email provider. To go live, plug an
// actual gateway in here (e.g. an SMTP call for 'email', or an HTTP request
// to an SMS aggregator like Africa's Talking / Twilio for 'sms') and only
// mark a row "sent" once that call succeeds — mark "failed" otherwise.
function dispatchPendingNotifications(): int
{
    $rows = db()->query(
        "SELECT id, channel FROM notifications WHERE status = 'pending' ORDER BY id"
    )->fetchAll();

    $sent = 0;
    $update = db()->prepare('UPDATE notifications SET status = ?, sent_at = NOW() WHERE id = ?');

    foreach ($rows as $row) {
        // TODO: replace with a real send call per $row['channel'].
        $update->execute(['sent', $row['id']]);
        $sent++;
    }

    return $sent;
}
