<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('member_documents.manage');
$user = currentUser();

const MEMBER_DOC_MAX_BYTES = 5 * 1024 * 1024; // 5 MB
const MEMBER_DOC_ALLOWED_TYPES = [
    'application/pdf' => 'pdf',
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
];
const MEMBER_DOC_TYPES = ['national_id', 'application_form', 'other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_member_document') {
    verifyCsrf();
    $memberId = (int) ($_POST['member_id'] ?? 0);
    $documentType = in_array($_POST['document_type'] ?? '', MEMBER_DOC_TYPES, true) ? $_POST['document_type'] : 'other';

    if ($memberId <= 0) {
        setFlash('error', 'Please select a member.');
    } elseif (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Please choose a file to upload.');
    } else {
        $file = $_FILES['file'];
        $mime = mime_content_type($file['tmp_name']);

        if ($file['size'] > MEMBER_DOC_MAX_BYTES) {
            setFlash('error', 'File is too large (max 5 MB).');
        } elseif (!isset(MEMBER_DOC_ALLOWED_TYPES[$mime])) {
            setFlash('error', 'File must be a PDF, PNG, or JPEG.');
        } else {
            $ext = MEMBER_DOC_ALLOWED_TYPES[$mime];
            $filename = 'mdoc_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destination = __DIR__ . '/../../assets/uploads/member_documents/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $stmt = db()->prepare(
                    'INSERT INTO member_documents (member_id, document_type, file_path, uploaded_by) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$memberId, $documentType, 'assets/uploads/member_documents/' . $filename, $user['id']]);
                setFlash('success', 'Document uploaded.');
            } else {
                setFlash('error', 'Could not save the uploaded file.');
            }
        }
    }
    redirect('modules/member_documents/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_member_document') {
    verifyCsrf();
    $id = (int) ($_POST['document_id'] ?? 0);

    $stmt = db()->prepare('SELECT file_path FROM member_documents WHERE id = ?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if ($doc) {
        db()->prepare('DELETE FROM member_documents WHERE id = ?')->execute([$id]);
        $fullPath = __DIR__ . '/../../' . $doc['file_path'];
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
        setFlash('success', 'Document deleted.');
    }
    redirect('modules/member_documents/index.php');
}

$members = db()->query(
    'SELECT members.id, members.member_number, users.full_name
     FROM members JOIN users ON users.id = members.user_id
     ORDER BY users.full_name'
)->fetchAll();

$documents = db()->query(
    "SELECT member_documents.*, users.full_name AS uploaded_by_name,
            members.member_number, member_users.full_name AS member_name
     FROM member_documents
     JOIN users ON users.id = member_documents.uploaded_by
     JOIN members ON members.id = member_documents.member_id
     JOIN users AS member_users ON member_users.id = members.user_id
     ORDER BY member_documents.uploaded_at DESC"
)->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Member Documents</h1>
    <p class="text-gray-500 text-sm">ID scans, signed application forms, and other per-member records.</p>
</div>

<div class="card max-w-lg">
    <h2>Upload a Document</h2>
    <form method="post" action="" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload_member_document">

        <label for="member_id">Member</label>
        <select id="member_id" name="member_id" required>
            <option value="">-- Select member --</option>
            <?php foreach ($members as $m): ?>
                <option value="<?= e((string) $m['id']) ?>"><?= e($m['member_number'] . ' - ' . $m['full_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="document_type">Document Type</label>
        <select id="document_type" name="document_type">
            <option value="national_id">National ID</option>
            <option value="application_form">Application Form</option>
            <option value="other">Other</option>
        </select>

        <label for="file">File (PDF, PNG, or JPEG, max 5 MB)</label>
        <input type="file" id="file" name="file" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg" required>

        <button type="submit">Upload</button>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>Member</th><th>Type</th><th>Uploaded By</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($documents as $d): ?>
            <tr>
                <td><?= e($d['member_number'] . ' - ' . $d['member_name']) ?></td>
                <td><?= e(str_replace('_', ' ', ucfirst($d['document_type']))) ?></td>
                <td><?= e($d['uploaded_by_name']) ?></td>
                <td><?= e($d['uploaded_at']) ?></td>
                <td>
                    <a class="btn" href="<?= e(APP_URL) ?>/<?= e($d['file_path']) ?>" target="_blank" rel="noopener">Download</a>
                    <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure? This cannot be undone.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_member_document">
                        <input type="hidden" name="document_id" value="<?= e((string) $d['id']) ?>">
                        <button type="submit" style="background:#dc2626;color:#fff;">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$documents): ?>
            <tr><td colspan="5">No member documents uploaded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
