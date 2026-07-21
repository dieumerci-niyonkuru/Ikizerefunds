<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$user = currentUser();
$isLeadership = userHasPermission($user, 'messages.manage');

$rootId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    "SELECT messages.*, users.full_name AS sender_name, users.photo_path AS sender_photo, roles.name AS sender_role
     FROM messages JOIN users ON users.id = messages.sender_id
     JOIN roles ON roles.id = users.role_id
     WHERE messages.id = ? AND messages.parent_id IS NULL"
);
$stmt->execute([$rootId]);
$root = $stmt->fetch();

if (!$root) {
    setFlash('error', 'Message thread not found.');
    redirect('modules/messages/index.php');
}

$canView = $isLeadership || ($root['channel'] === 'member_leadership' && (int) $root['sender_id'] === (int) $user['id']);
if ($root['channel'] === 'leadership_only' && !$isLeadership) {
    $canView = false;
}
if (!$canView) {
    http_response_code(403);
    die('You do not have permission to view this message.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    verifyCsrf();
    $body = trim($_POST['body'] ?? '');
    if ($body === '') {
        setFlash('error', 'Reply cannot be empty.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO messages (channel, sender_id, parent_id, subject, body) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$root['channel'], $user['id'], $rootId, 'Re: ' . $root['subject'], $body]);
        setFlash('success', 'Reply sent.');
    }
    redirect('modules/messages/thread.php?id=' . $rootId);
}

$stmt = db()->prepare(
    "SELECT messages.*, users.full_name AS sender_name, users.photo_path AS sender_photo, roles.name AS sender_role
     FROM messages JOIN users ON users.id = messages.sender_id
     JOIN roles ON roles.id = users.role_id
     WHERE messages.parent_id = ?
     ORDER BY messages.created_at ASC"
);
$stmt->execute([$rootId]);
$replies = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <p><a href="<?= e(APP_URL) ?>/modules/messages/index.php">&larr; Back to Messages</a></p>
    <h1><?= e($root['subject']) ?></h1>

    <div class="flex items-start gap-3" style="padding:1rem 0;border-bottom:1px solid #e5e7eb;">
        <?= avatarHtml($root['sender_photo'] ?? null, $root['sender_name'], 'w-10 h-10 text-sm') ?>
        <div>
            <div class="font-semibold"><?= e($root['sender_name']) ?></div>
            <div class="text-gray-400 text-xs mb-1"><?= e(str_replace('_', ' ', ucfirst($root['sender_role'] ?? ''))) ?></div>
            <div class="text-gray-500 text-sm mb-2"><?= e($root['created_at']) ?></div>
            <p><?= nl2br(e($root['body'])) ?></p>
        </div>
    </div>

    <?php foreach ($replies as $r): ?>
        <div class="flex items-start gap-3" style="padding:1rem 0;border-bottom:1px solid #e5e7eb;">
            <?= avatarHtml($r['sender_photo'] ?? null, $r['sender_name'], 'w-10 h-10 text-sm') ?>
            <div>
                <div class="font-semibold"><?= e($r['sender_name']) ?></div>
                <div class="text-gray-400 text-xs mb-1"><?= e(str_replace('_', ' ', ucfirst($r['sender_role'] ?? ''))) ?></div>
                <div class="text-gray-500 text-sm mb-2"><?= e($r['created_at']) ?></div>
                <p><?= nl2br(e($r['body'])) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card max-w-lg">
    <h2>Reply</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reply">
        <label for="body">Message</label>
        <textarea id="body" name="body" rows="4" required></textarea>
        <button type="submit">Send Reply</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
