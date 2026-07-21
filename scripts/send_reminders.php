<?php
// Intended to run once daily via cron/Task Scheduler, e.g.:
//   php scripts/send_reminders.php
// It queues reminder notifications, then dispatches whatever is pending
// (dispatch is currently a stub — see includes/notifications.php).

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

$pdo = db();
$today = date('Y-m-d');

// ------------------------------------------------------------
// 1. Loan payments due within the next 3 days.
// ------------------------------------------------------------
$dueSoon = $pdo->query(
    "SELECT repayment_schedule.id, repayment_schedule.due_date, repayment_schedule.expected_amount,
            users.id AS user_id, users.full_name
     FROM repayment_schedule
     JOIN loans ON loans.id = repayment_schedule.loan_id
     JOIN members ON members.id = loans.member_id
     JOIN users ON users.id = members.user_id
     WHERE repayment_schedule.status = 'pending'
       AND repayment_schedule.due_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 3 DAY)"
)->fetchAll();

foreach ($dueSoon as $row) {
    queueNotification((int) $row['user_id'], 'payment_due', [
        'name' => $row['full_name'],
        'amount' => number_format((float) $row['expected_amount'], 2),
        'due_date' => $row['due_date'],
    ]);
}
echo count($dueSoon) . " payment_due reminder(s) queued.\n";

// ------------------------------------------------------------
// 2. Loan installments that are now overdue -> mark late + alert.
// ------------------------------------------------------------
$overdue = $pdo->query(
    "SELECT repayment_schedule.id, repayment_schedule.due_date, repayment_schedule.expected_amount,
            users.id AS user_id, users.full_name
     FROM repayment_schedule
     JOIN loans ON loans.id = repayment_schedule.loan_id
     JOIN members ON members.id = loans.member_id
     JOIN users ON users.id = members.user_id
     WHERE repayment_schedule.status = 'pending'
       AND repayment_schedule.due_date < CURDATE()"
)->fetchAll();

$markLate = $pdo->prepare("UPDATE repayment_schedule SET status = 'late' WHERE id = ?");
foreach ($overdue as $row) {
    $markLate->execute([$row['id']]);
    queueNotification((int) $row['user_id'], 'late_payment', [
        'name' => $row['full_name'],
        'amount' => number_format((float) $row['expected_amount'], 2),
        'due_date' => $row['due_date'],
    ]);
}
echo count($overdue) . " late_payment alert(s) queued.\n";

// ------------------------------------------------------------
// 3. Meetings happening in the next 24 hours.
// ------------------------------------------------------------
$upcomingMeetings = $pdo->query(
    "SELECT id, title, meeting_date, location FROM meetings
     WHERE status = 'scheduled'
       AND meeting_date BETWEEN NOW() AND (NOW() + INTERVAL 1 DAY)"
)->fetchAll();

$members = $pdo->query(
    "SELECT users.id AS user_id, users.full_name FROM users
     JOIN members ON members.user_id = users.id
     WHERE users.status = 'active'"
)->fetchAll();

foreach ($upcomingMeetings as $meeting) {
    foreach ($members as $m) {
        queueNotification((int) $m['user_id'], 'meeting_reminder', [
            'name' => $m['full_name'],
            'title' => $meeting['title'],
            'meeting_date' => $meeting['meeting_date'],
            'location' => $meeting['location'],
        ]);
    }
}
echo count($upcomingMeetings) . ' meeting(s) reminded to ' . count($members) . " member(s).\n";

// ------------------------------------------------------------
// 4. Monthly savings reminder, sent once on the 25th of each month.
// ------------------------------------------------------------
if (date('d') === '25') {
    foreach ($members as $m) {
        queueNotification((int) $m['user_id'], 'saving_reminder', [
            'name' => $m['full_name'],
        ]);
    }
    echo 'saving_reminder queued for ' . count($members) . " member(s).\n";
}

// ------------------------------------------------------------
// Dispatch everything queued (stubbed delivery, see includes/notifications.php).
// ------------------------------------------------------------
$sent = dispatchPendingNotifications();
echo "{$sent} notification(s) dispatched.\n";
