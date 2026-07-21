<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('meetings.access');
$user = currentUser();
$isStaff = userHasPermission($user, 'meetings.manage');

// ------------------------------------------------------------
// Staff: schedule a new meeting
// ------------------------------------------------------------
if ($isStaff && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_meeting') {
    verifyCsrf();

    $title = trim($_POST['title'] ?? '');
    $meetingDate = $_POST['meeting_date'] ?? '';
    $location = trim($_POST['location'] ?? '') ?: null;
    $agenda = trim($_POST['agenda'] ?? '') ?: null;

    if ($title === '' || $meetingDate === '') {
        setFlash('error', 'Title and meeting date/time are required.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO meetings (title, meeting_date, location, agenda, created_by) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$title, $meetingDate, $location, $agenda, $user['id']]);
        setFlash('success', 'Meeting scheduled.');
    }
    redirect('modules/meetings/index.php');
}

// ------------------------------------------------------------
// Staff: delete a meeting (cascades to its attendance records)
// ------------------------------------------------------------
if ($isStaff && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_meeting') {
    verifyCsrf();
    $meetingId = (int) ($_POST['meeting_id'] ?? 0);
    db()->prepare('DELETE FROM meetings WHERE id = ?')->execute([$meetingId]);
    setFlash('success', 'Meeting deleted.');
    redirect('modules/meetings/index.php');
}

// ------------------------------------------------------------
// Staff: save minutes + attendance for a meeting
// ------------------------------------------------------------
if ($isStaff && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_meeting_details') {
    verifyCsrf();

    $meetingId = (int) ($_POST['meeting_id'] ?? 0);
    $minutes = trim($_POST['minutes'] ?? '') ?: null;
    $status = $_POST['status'] ?? 'scheduled';
    $attendance = $_POST['attendance'] ?? []; // member_id => present|absent|excused

    $stmt = db()->prepare('SELECT id FROM meetings WHERE id = ?');
    $stmt->execute([$meetingId]);
    if (!$stmt->fetch()) {
        setFlash('error', 'Meeting not found.');
        redirect('modules/meetings/index.php');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE meetings SET minutes = ?, status = ? WHERE id = ?')
            ->execute([$minutes, $status, $meetingId]);

        $upsert = $pdo->prepare(
            'INSERT INTO meeting_attendance (meeting_id, member_id, status) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status)'
        );
        foreach ($attendance as $memberId => $attStatus) {
            if (!in_array($attStatus, ['present', 'absent', 'excused'], true)) {
                continue;
            }
            $upsert->execute([$meetingId, (int) $memberId, $attStatus]);
        }

        $pdo->commit();
        setFlash('success', 'Meeting details updated.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlash('error', 'Could not save meeting details: ' . $e->getMessage());
    }
    redirect('modules/meetings/index.php?manage=' . $meetingId);
}

// ------------------------------------------------------------
// Data for display
// ------------------------------------------------------------
$meetings = db()->query(
    "SELECT meetings.*, users.full_name AS created_by_name, users.photo_path AS created_by_photo
     FROM meetings
     JOIN users ON users.id = meetings.created_by
     ORDER BY meeting_date DESC"
)->fetchAll();

$manageMeeting = null;
$members = [];
$attendanceMap = [];
if ($isStaff && !empty($_GET['manage'])) {
    $manageId = (int) $_GET['manage'];
    $stmt = db()->prepare('SELECT * FROM meetings WHERE id = ?');
    $stmt->execute([$manageId]);
    $manageMeeting = $stmt->fetch();

    if ($manageMeeting) {
        $members = db()->query(
            'SELECT members.id, members.member_number, users.full_name, users.photo_path
             FROM members JOIN users ON users.id = members.user_id
             ORDER BY users.full_name'
        )->fetchAll();

        $stmt = db()->prepare('SELECT member_id, status FROM meeting_attendance WHERE meeting_id = ?');
        $stmt->execute([$manageId]);
        foreach ($stmt->fetchAll() as $row) {
            $attendanceMap[$row['member_id']] = $row['status'];
        }
    }
}

if (!$isStaff) {
    $stmt = db()->prepare('SELECT id FROM members WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $myMemberId = $stmt->fetchColumn();

    $stmt = db()->prepare(
        "SELECT meetings.title, meetings.meeting_date, meeting_attendance.status
         FROM meeting_attendance
         JOIN meetings ON meetings.id = meeting_attendance.meeting_id
         WHERE meeting_attendance.member_id = ?
         ORDER BY meetings.meeting_date DESC"
    );
    $stmt->execute([$myMemberId]);
    $myAttendance = $stmt->fetchAll();
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Meetings</h1>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Title</th><th>Date</th><th>Location</th><th>Status</th><th>Created By</th><?php if ($isStaff): ?><th>Action</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($meetings as $m): ?>
            <tr>
                <td><?= e($m['title']) ?></td>
                <td><?= e($m['meeting_date']) ?></td>
                <td><?= e($m['location']) ?></td>
                <td><?= statusBadge($m['status']) ?></td>
                <td class="flex items-center gap-2"><?= avatarHtml($m['created_by_photo'] ?? null, $m['created_by_name']) ?> <?= e($m['created_by_name']) ?></td>
                <?php if ($isStaff): ?>
                    <td>
                        <a class="btn" href="<?= e(APP_URL) ?>/modules/meetings/index.php?manage=<?= e((string) $m['id']) ?>">Manage</a>
                        <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure? This cannot be undone.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_meeting">
                            <input type="hidden" name="meeting_id" value="<?= e((string) $m['id']) ?>">
                            <button type="submit" style="background:#dc2626;color:#fff;">Delete</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$meetings): ?>
            <tr><td colspan="<?= $isStaff ? 6 : 5 ?>">No meetings scheduled yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($isStaff): ?>
    <div class="card max-w-lg">
        <h2>Schedule a Meeting</h2>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_meeting">

            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>

            <label for="meeting_date">Date &amp; Time</label>
            <input type="datetime-local" id="meeting_date" name="meeting_date" required>

            <label for="location">Location</label>
            <input type="text" id="location" name="location">

            <label for="agenda">Agenda</label>
            <textarea id="agenda" name="agenda" rows="3"></textarea>

            <button type="submit">Schedule Meeting</button>
        </form>
    </div>

    <?php if ($manageMeeting): ?>
        <div class="card">
            <h2>Manage: <?= e($manageMeeting['title']) ?></h2>
            <form method="post" action="">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_meeting_details">
                <input type="hidden" name="meeting_id" value="<?= e((string) $manageMeeting['id']) ?>">

                <label for="status">Meeting Status</label>
                <select id="status" name="status">
                    <option value="scheduled" <?= $manageMeeting['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                    <option value="completed" <?= $manageMeeting['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $manageMeeting['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <label for="minutes">Minutes</label>
                <textarea id="minutes" name="minutes" rows="5"><?= e($manageMeeting['minutes']) ?></textarea>

                <h3>Attendance</h3>
                <div class="table-wrap">
                <table>
                    <thead><tr><th>Member</th><th>Present</th><th>Absent</th><th>Excused</th></tr></thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <?php $current = $attendanceMap[$m['id']] ?? 'present'; ?>
                        <tr>
                            <td class="flex items-center gap-2"><?= avatarHtml($m['photo_path'] ?? null, $m['full_name']) ?> <?= e($m['member_number'] . ' - ' . $m['full_name']) ?></td>
                            <?php foreach (['present', 'absent', 'excused'] as $opt): ?>
                                <td class="text-center">
                                    <input type="radio" name="attendance[<?= e((string) $m['id']) ?>]" value="<?= e($opt) ?>" <?= $current === $opt ? 'checked' : '' ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$members): ?>
                        <tr><td colspan="4">No members to record attendance for.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>

                <button type="submit" class="mt-4">Save Meeting Details</button>
            </form>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="card">
        <h2>My Attendance History</h2>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Meeting</th><th>Date</th><th>My Status</th></tr></thead>
            <tbody>
            <?php foreach ($myAttendance as $a): ?>
                <tr>
                    <td><?= e($a['title']) ?></td>
                    <td><?= e($a['meeting_date']) ?></td>
                    <td><?= statusBadge($a['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$myAttendance): ?>
                <tr><td colspan="3">No attendance recorded yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
