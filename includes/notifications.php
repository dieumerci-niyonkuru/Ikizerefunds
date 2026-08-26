<?php
// Queueing and dispatch for the notification types from the project spec
// (saving_reminder, loan_approval, payment_due, meeting_reminder,
// late_payment, password_reset_request, membership_approval).
//
// Every notification lands in the member's in-app inbox. Email delivery on top
// of that is real but optional: it runs over SMTP when configured, and is
// skipped honestly when it is not. See dispatchPendingNotifications().

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

// Queues a notification for a user. Default channel is email; 'sms' is accepted
// but has no gateway wired up yet (see dispatchPendingNotifications).
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

// Returns the human-readable subject for a notification type, falling back to
// a title-cased version of the type when the template has no subject.
function notificationSubject(string $type): string
{
    static $cache = [];

    if (!array_key_exists($type, $cache)) {
        $stmt = db()->prepare('SELECT subject FROM notification_templates WHERE type = ?');
        $stmt->execute([$type]);
        $subject = $stmt->fetchColumn();

        $cache[$type] = ($subject !== false && $subject !== null && $subject !== '')
            ? $subject
            : ucwords(str_replace('_', ' ', $type));
    }

    return $cache[$type];
}

/**
 * Delivers every pending notification.
 *
 * Email goes out over SMTP (see includes/mailer.php). A row is only marked
 * "sent" once the server has accepted the message; anything else is marked
 * "failed" with the reason stored on the row.
 *
 * When SMTP is not configured, rows are deliberately LEFT pending rather than
 * marked sent — they still appear in each member's in-app inbox, and they will
 * go out once credentials are added. Anything older than $maxAgeDays is marked
 * "expired" so configuring SMTP later cannot blast out months of stale
 * reminders.
 *
 * SMS has no gateway wired up. Those rows are marked "failed" with a clear
 * reason rather than silently claiming success.
 *
 * @return array{sent:int,failed:int,skipped:int,expired:int,reason:string}
 */
function dispatchPendingNotifications(int $maxAgeDays = 14): array
{
    $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'expired' => 0, 'reason' => ''];

    $pdo = db();

    // Retire stale pending rows first, whatever the SMTP state.
    $expire = $pdo->prepare(
        "UPDATE notifications SET status = 'expired'
         WHERE status = 'pending' AND created_at < (NOW() - INTERVAL ? DAY)"
    );
    $expire->execute([$maxAgeDays]);
    $result['expired'] = $expire->rowCount();

    $rows = $pdo->query(
        "SELECT n.id, n.channel, n.type, n.message,
                u.full_name, u.email, u.phone
         FROM notifications n
         JOIN users u ON u.id = n.user_id
         WHERE n.status = 'pending'
         ORDER BY n.id"
    )->fetchAll();

    if (!$rows) {
        return $result;
    }

    require_once __DIR__ . '/mailer.php';
    require_once __DIR__ . '/sms.php';

    $canEmail = mailerConfigured();
    $canSms   = smsConfigured();

    // With neither channel available there is nothing to do. Leave the rows
    // pending rather than marking them sent: they still show in the in-app
    // inbox, and they go out once a gateway is configured.
    if (!$canEmail && !$canSms) {
        $result['skipped'] = count($rows);
        $result['reason'] = 'No delivery gateway is configured. Set SMTP_* for email '
            . 'and/or AT_API_KEY / TWILIO_SID for SMS. Notifications remain pending and '
            . 'are still visible in each member\'s inbox.';

        return $result;
    }

    $smsFallback = smsConfig()['fallback'];

    $markSent = $pdo->prepare(
        "UPDATE notifications SET status = 'sent', sent_at = NOW(), error = NULL WHERE id = ?"
    );
    $markFailed = $pdo->prepare(
        "UPDATE notifications SET status = 'failed', error = ? WHERE id = ?"
    );

    foreach ($rows as $row) {
        $channel = strtolower($row['channel'] ?: 'email');
        $subject = notificationSubject($row['type']);

        // Route to SMS when explicitly asked for, or when SMS_FALLBACK is on
        // and the member has a phone but no email to write to.
        $useSms = $channel === 'sms'
            || ($smsFallback && $canSms && empty($row['email']) && !empty($row['phone']));

        if ($useSms) {
            if (!$canSms) {
                $markFailed->execute(['No SMS gateway is configured', $row['id']]);
                $result['failed']++;
                continue;
            }

            if (empty($row['phone'])) {
                $markFailed->execute(['Member has no phone number on file', $row['id']]);
                $result['failed']++;
                continue;
            }

            // SMS is billed per segment, so lead with the club name and keep
            // the body to the template text.
            $outcome = sendSms($row['phone'], APP_NAME . ': ' . $row['message']);
        } else {
            if (!$canEmail) {
                $markFailed->execute(['Email is not configured (set SMTP_HOST)', $row['id']]);
                $result['failed']++;
                continue;
            }

            if (empty($row['email'])) {
                $markFailed->execute(['Member has no email address on file', $row['id']]);
                $result['failed']++;
                continue;
            }

            $outcome = sendMail(
                $row['email'],
                $row['full_name'] ?? '',
                $subject,
                $row['message'],
                notificationEmailHtml($subject, $row['message'], $row['full_name'] ?? 'member')
            );
        }

        if ($outcome['ok']) {
            $markSent->execute([$row['id']]);
            $result['sent']++;
        } else {
            $markFailed->execute([substr($outcome['error'], 0, 255), $row['id']]);
            $result['failed']++;
        }
    }

    return $result;
}
