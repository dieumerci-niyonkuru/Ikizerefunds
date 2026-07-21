<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$user = currentUser();
$isLeadership = userHasPermission($user, 'messages.manage');

// ------------------------------------------------------------
// Start a new thread to leadership (any logged-in user)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_thread') {
    verifyCsrf();
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($subject === '' || $body === '') {
        setFlash('error', 'Please provide a subject and message.');
    } else {
        $stmt = db()->prepare(
            "INSERT INTO messages (channel, sender_id, subject, body) VALUES ('member_leadership', ?, ?, ?)"
        );
        $stmt->execute([$user['id'], $subject, $body]);
        setFlash('success', 'Message sent to leadership.');
    }
    redirect('modules/messages/index.php');
}

// ------------------------------------------------------------
// Leadership: post to the leadership-only board
// ------------------------------------------------------------
if ($isLeadership && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_board_post') {
    verifyCsrf();
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($subject === '' || $body === '') {
        setFlash('error', 'Please provide a subject and message.');
    } else {
        $stmt = db()->prepare(
            "INSERT INTO messages (channel, sender_id, subject, body) VALUES ('leadership_only', ?, ?, ?)"
        );
        $stmt->execute([$user['id'], $subject, $body]);
        setFlash('success', 'Posted to the leadership channel.');
    }
    redirect('modules/messages/index.php');
}

// ------------------------------------------------------------
// Data for display
// ------------------------------------------------------------
if ($isLeadership) {
    $threads = db()->query(
        "SELECT messages.id, messages.subject, messages.created_at, users.full_name AS sender_name, users.photo_path AS sender_photo,
                (SELECT COUNT(*) FROM messages r WHERE r.parent_id = messages.id) AS reply_count
         FROM messages
         JOIN users ON users.id = messages.sender_id
         WHERE messages.channel = 'member_leadership' AND messages.parent_id IS NULL
         ORDER BY messages.created_at DESC"
    )->fetchAll();
} else {
    $stmt = db()->prepare(
        "SELECT messages.id, messages.subject, messages.created_at,
                (SELECT COUNT(*) FROM messages r WHERE r.parent_id = messages.id) AS reply_count
         FROM messages
         WHERE messages.channel = 'member_leadership' AND messages.parent_id IS NULL AND messages.sender_id = ?
         ORDER BY messages.created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $threads = $stmt->fetchAll();
}

$boardPosts = [];
if ($isLeadership) {
    $boardPosts = db()->query(
        "SELECT messages.id, messages.subject, messages.created_at, users.full_name AS sender_name, users.photo_path AS sender_photo,
                (SELECT COUNT(*) FROM messages r WHERE r.parent_id = messages.id) AS reply_count
         FROM messages
         JOIN users ON users.id = messages.sender_id
         WHERE messages.channel = 'leadership_only' AND messages.parent_id IS NULL
         ORDER BY messages.created_at DESC"
    )->fetchAll();
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Messages</h1>
    <p class="text-gray-500 text-sm">
        <?= $isLeadership
            ? 'Messages from members are visible to all leadership. Replies come from the club, not any one person.'
            : 'Send a message to club leadership. Any leader may reply.' ?>
    </p>
</div>

<div class="dashboard-grid" style="margin-bottom:1.5rem;">
    <?php if (!$isLeadership): ?>
    <div class="card">
        <h2>New Message to Leadership</h2>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="new_thread">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" required>
            <label for="body">Message</label>
            <textarea id="body" name="body" rows="4" required></textarea>
            <button type="submit">Send</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($isLeadership): ?>
    <div class="card">
        <h2>New Leadership Post</h2>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="new_board_post">
            <label for="lb_subject">Subject</label>
            <input type="text" id="lb_subject" name="subject" required>
            <label for="lb_body">Message</label>
            <textarea id="lb_body" name="body" rows="4" required></textarea>
            <button type="submit">Post</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?= $isLeadership ? 'Member Messages' : 'My Messages' ?></h2>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <?php if ($isLeadership): ?><th>From</th><?php endif; ?>
            <th>Subject</th><th>Replies</th><th>Date</th><th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($threads as $t): ?>
            <tr>
                <?php if ($isLeadership): ?><td class="flex items-center gap-2"><?= avatarHtml($t['sender_photo'], $t['sender_name']) ?> <?= e($t['sender_name']) ?></td><?php endif; ?>
                <td><?= e($t['subject']) ?></td>
                <td><?= e((string) $t['reply_count']) ?></td>
                <td><?= e($t['created_at']) ?></td>
                <td><a class="btn" href="<?= e(APP_URL) ?>/modules/messages/thread.php?id=<?= e((string) $t['id']) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$threads): ?>
            <tr><td colspan="<?= $isLeadership ? 5 : 4 ?>">No messages yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($isLeadership): ?>
<div class="card">
    <h2>Leadership Channel <span class="text-gray-500 text-sm">(only visible to leadership)</span></h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>From</th><th>Subject</th><th>Replies</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($boardPosts as $p): ?>
            <tr>
                <td class="flex items-center gap-2"><?= avatarHtml($p['sender_photo'], $p['sender_name']) ?> <?= e($p['sender_name']) ?></td>
                <td><?= e($p['subject']) ?></td>
                <td><?= e((string) $p['reply_count']) ?></td>
                <td><?= e($p['created_at']) ?></td>
                <td><a class="btn" href="<?= e(APP_URL) ?>/modules/messages/thread.php?id=<?= e((string) $p['id']) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$boardPosts): ?>
            <tr><td colspan="5">No leadership posts yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
