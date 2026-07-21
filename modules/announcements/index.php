<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('announcements.publish');
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_announcement') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        setFlash('error', 'Title and content are required.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO announcements (title, content, posted_by) VALUES (?, ?, ?)'
        );
        $stmt->execute([$title, $content, $user['id']]);
        setFlash('success', 'Announcement published.');
    }
    redirect('modules/announcements/index.php');
}

// ------------------------------------------------------------
// Edit an announcement's title/content
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_announcement') {
    verifyCsrf();
    $id = (int) ($_POST['announcement_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        setFlash('error', 'Title and content are required.');
    } else {
        db()->prepare('UPDATE announcements SET title = ?, content = ? WHERE id = ?')
            ->execute([$title, $content, $id]);
        setFlash('success', 'Announcement updated.');
    }
    redirect('modules/announcements/index.php');
}

// ------------------------------------------------------------
// Delete an announcement
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_announcement') {
    verifyCsrf();
    $id = (int) ($_POST['announcement_id'] ?? 0);
    db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
    setFlash('success', 'Announcement deleted.');
    redirect('modules/announcements/index.php');
}

$announcements = db()->query(
    'SELECT id, title, content, posted_at, is_published FROM announcements ORDER BY posted_at DESC'
)->fetchAll();

$editAnnouncement = null;
if (!empty($_GET['edit'])) {
    foreach ($announcements as $a) {
        if ((int) $a['id'] === (int) $_GET['edit']) {
            $editAnnouncement = $a;
            break;
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Announcements</h1>
    <table>
        <thead><tr><th>Title</th><th>Posted</th><th>Published</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($announcements as $a): ?>
            <tr>
                <td><?= e($a['title']) ?></td>
                <td><?= e($a['posted_at']) ?></td>
                <td><?= statusBadge($a['is_published'] ? 'published' : 'pending') ?></td>
                <td>
                    <a class="btn" href="<?= e(APP_URL) ?>/modules/announcements/index.php?edit=<?= e((string) $a['id']) ?>">Edit</a>
                    <form method="post" style="display:inline-block">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_announcement">
                        <input type="hidden" name="announcement_id" value="<?= e((string) $a['id']) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$announcements): ?>
            <tr><td colspan="4">No announcements yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($editAnnouncement): ?>
<div class="card max-w-lg">
    <h2>Edit Announcement</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit_announcement">
        <input type="hidden" name="announcement_id" value="<?= e((string) $editAnnouncement['id']) ?>">
        <label for="edit_title">Title</label>
        <input type="text" id="edit_title" name="title" value="<?= e($editAnnouncement['title']) ?>" required>
        <label for="edit_content">Content</label>
        <textarea id="edit_content" name="content" rows="5" required><?= e($editAnnouncement['content']) ?></textarea>
        <button type="submit">Save Changes</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-2"><a href="<?= e(APP_URL) ?>/modules/announcements/index.php">Cancel</a></p>
</div>
<?php endif; ?>

<div class="card max-w-lg">
    <h2>New Announcement</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_announcement">

        <label for="title">Title</label>
        <input type="text" id="title" name="title" required>

        <label for="content">Content</label>
        <textarea id="content" name="content" rows="5" required></textarea>

        <button type="submit">Publish</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
