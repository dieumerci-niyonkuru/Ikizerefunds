<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$user = currentUser();
$canManage = userHasPermission($user, 'documents.manage');

const DOC_MAX_BYTES = 5 * 1024 * 1024; // 5 MB
const DOC_ALLOWED_TYPES = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];
const DOC_CATEGORIES = ['constitution', 'bylaws', 'agm_report', 'other'];

if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_document') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $category = in_array($_POST['category'] ?? '', DOC_CATEGORIES, true) ? $_POST['category'] : 'other';

    if ($title === '') {
        setFlash('error', 'Please provide a title.');
    } elseif (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Please choose a file to upload.');
    } else {
        $file = $_FILES['file'];
        $mime = mime_content_type($file['tmp_name']);

        if ($file['size'] > DOC_MAX_BYTES) {
            setFlash('error', 'File is too large (max 5 MB).');
        } elseif (!isset(DOC_ALLOWED_TYPES[$mime])) {
            setFlash('error', 'File must be a PDF or Word document.');
        } else {
            $ext = DOC_ALLOWED_TYPES[$mime];
            $filename = 'doc_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destination = __DIR__ . '/../../assets/uploads/documents/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $stmt = db()->prepare(
                    'INSERT INTO documents (title, category, file_path, uploaded_by) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$title, $category, 'assets/uploads/documents/' . $filename, $user['id']]);
                setFlash('success', 'Document uploaded.');
            } else {
                setFlash('error', 'Could not save the uploaded file.');
            }
        }
    }
    redirect('modules/documents/index.php');
}

if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_document') {
    verifyCsrf();
    $id = (int) ($_POST['document_id'] ?? 0);

    $stmt = db()->prepare('SELECT file_path FROM documents WHERE id = ?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if ($doc) {
        db()->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
        $fullPath = __DIR__ . '/../../' . $doc['file_path'];
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
        setFlash('success', 'Document deleted.');
    }
    redirect('modules/documents/index.php');
}

$documents = db()->query(
    "SELECT documents.*, users.full_name AS uploaded_by_name, users.photo_path AS uploaded_by_photo
     FROM documents
     JOIN users ON users.id = documents.uploaded_by
     ORDER BY documents.uploaded_at DESC"
)->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Club Documents</h1>
    <p class="text-gray-500 text-sm">Constitution, bylaws, AGM reports, and other official club records.</p>
</div>

<?php if ($canManage): ?>
<div class="card max-w-lg">
    <h2>Upload a Document</h2>
    <form method="post" action="" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload_document">

        <label for="title">Title</label>
        <input type="text" id="title" name="title" required>

        <label for="category">Category</label>
        <select id="category" name="category">
            <option value="constitution">Constitution</option>
            <option value="bylaws">Bylaws</option>
            <option value="agm_report">AGM Report</option>
            <option value="other">Other</option>
        </select>

        <label for="file">File (PDF or Word, max 5 MB)</label>
        <input type="file" id="file" name="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>

        <button type="submit">Upload</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>Title</th><th>Category</th><th>Uploaded By</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($documents as $d): ?>
            <tr>
                <td><?= e($d['title']) ?></td>
                <td><?= e(str_replace('_', ' ', ucfirst($d['category']))) ?></td>
                <td class="flex items-center gap-2"><?= avatarHtml($d['uploaded_by_photo'] ?? null, $d['uploaded_by_name']) ?> <?= e($d['uploaded_by_name']) ?></td>
                <td><?= e($d['uploaded_at']) ?></td>
                <td>
                    <a class="btn" href="<?= e(APP_URL) ?>/<?= e($d['file_path']) ?>" target="_blank" rel="noopener">Download</a>
                    <?php if ($canManage): ?>
                        <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure? This cannot be undone.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_document">
                            <input type="hidden" name="document_id" value="<?= e((string) $d['id']) ?>">
                            <button type="submit" style="background:#dc2626;color:#fff;">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$documents): ?>
            <tr><td colspan="5">No documents uploaded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
