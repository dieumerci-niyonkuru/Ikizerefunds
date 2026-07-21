<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$user = currentUser();

const PROFILE_MAX_PHOTO_BYTES = 2 * 1024 * 1024; // 2 MB
const PROFILE_ALLOWED_PHOTO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
const PROFILE_MAX_DOC_BYTES = 5 * 1024 * 1024; // 5 MB
const PROFILE_ALLOWED_DOC_TYPES = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg'];

$stmt = db()->prepare('SELECT * FROM members WHERE user_id = ?');
$stmt->execute([$user['id']]);
$member = $stmt->fetch() ?: null;

// ------------------------------------------------------------
// Update profile details (contact info + member-specific fields)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;

    try {
        db()->prepare('UPDATE users SET email = ?, phone = ? WHERE id = ?')->execute([$email, $phone, $user['id']]);

        if ($member) {
            $nationalId = trim($_POST['national_id'] ?? '') ?: null;
            $address = trim($_POST['address'] ?? '') ?: null;
            $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : null;
            $dob = trim($_POST['date_of_birth'] ?? '') ?: null;
            $occupation = trim($_POST['occupation'] ?? '') ?: null;

            db()->prepare(
                'UPDATE members SET national_id = ?, address = ?, gender = ?, date_of_birth = ?, occupation = ? WHERE id = ?'
            )->execute([$nationalId, $address, $gender, $dob, $occupation, $member['id']]);
        }
        setFlash('success', 'Profile updated.');
    } catch (PDOException $e) {
        setFlash('error', 'Could not update profile: that email, phone, or national ID may already be in use.');
    }
    redirect('modules/members/profile.php');
}

// ------------------------------------------------------------
// Upload a profile picture (any logged-in user - leaders included)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_photo') {
    verifyCsrf();

    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $mime = mime_content_type($file['tmp_name']);

        if ($file['size'] > PROFILE_MAX_PHOTO_BYTES) {
            setFlash('error', 'Photo is too large (max 2 MB).');
        } elseif (!isset(PROFILE_ALLOWED_PHOTO_TYPES[$mime])) {
            setFlash('error', 'Photo must be a PNG, JPEG, or WEBP image.');
        } else {
            $ext = PROFILE_ALLOWED_PHOTO_TYPES[$mime];
            $filename = 'user_' . $user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destination = __DIR__ . '/../../assets/uploads/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                db()->prepare('UPDATE users SET photo_path = ? WHERE id = ?')
                    ->execute(['assets/uploads/' . $filename, $user['id']]);
                setFlash('success', 'Profile picture updated.');
            } else {
                setFlash('error', 'Could not save the uploaded photo.');
            }
        }
    } else {
        setFlash('error', 'Please choose a photo to upload.');
    }
    redirect('modules/members/profile.php');
}

// ------------------------------------------------------------
// Add a next-of-kin entry (members only)
// ------------------------------------------------------------
if ($member && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_kin') {
    verifyCsrf();
    $kinName = trim($_POST['kin_name'] ?? '');
    $kinRelationship = trim($_POST['kin_relationship'] ?? '') ?: null;
    $kinPhone = trim($_POST['kin_phone'] ?? '') ?: null;
    $kinAddress = trim($_POST['kin_address'] ?? '') ?: null;

    if ($kinName === '') {
        setFlash('error', 'Please provide the next of kin\'s name.');
    } else {
        db()->prepare(
            'INSERT INTO next_of_kin (member_id, full_name, relationship, phone, address) VALUES (?, ?, ?, ?, ?)'
        )->execute([$member['id'], $kinName, $kinRelationship, $kinPhone, $kinAddress]);
        setFlash('success', 'Next of kin added.');
    }
    redirect('modules/members/profile.php');
}

// ------------------------------------------------------------
// Upload a personal document (members only)
// ------------------------------------------------------------
if ($member && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_document') {
    verifyCsrf();
    $documentType = in_array($_POST['document_type'] ?? '', ['national_id', 'application_form', 'other'], true)
        ? $_POST['document_type'] : 'other';

    if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Please choose a file to upload.');
    } else {
        $file = $_FILES['file'];
        $mime = mime_content_type($file['tmp_name']);

        if ($file['size'] > PROFILE_MAX_DOC_BYTES) {
            setFlash('error', 'File is too large (max 5 MB).');
        } elseif (!isset(PROFILE_ALLOWED_DOC_TYPES[$mime])) {
            setFlash('error', 'File must be a PDF, PNG, or JPEG.');
        } else {
            $ext = PROFILE_ALLOWED_DOC_TYPES[$mime];
            $filename = 'mdoc_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destination = __DIR__ . '/../../assets/uploads/member_documents/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                db()->prepare(
                    'INSERT INTO member_documents (member_id, document_type, file_path, uploaded_by) VALUES (?, ?, ?, ?)'
                )->execute([$member['id'], $documentType, 'assets/uploads/member_documents/' . $filename, $user['id']]);
                setFlash('success', 'Document uploaded.');
            } else {
                setFlash('error', 'Could not save the uploaded file.');
            }
        }
    }
    redirect('modules/members/profile.php');
}

// ------------------------------------------------------------
// Change password (all logged-in users)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    verifyCsrf();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Fetch current hash
    $hashStmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $hashStmt->execute([$user['id']]);
    $currentHash = $hashStmt->fetchColumn();

    if (!password_verify($currentPassword, $currentHash)) {
        setFlash('error', 'Current password is incorrect.');
    } elseif (strlen($newPassword) < 6) {
        setFlash('error', 'New password must be at least 6 characters.');
    } elseif ($newPassword !== $confirmPassword) {
        setFlash('error', 'New password and confirmation do not match.');
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
        setFlash('success', 'Password changed successfully.');
    }
    redirect('modules/members/profile.php');
}

// ------------------------------------------------------------
// Delete a user account (president only)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    verifyCsrf();
    $targetId = (int) ($_POST['target_user_id'] ?? 0);

    if ($user['role_name'] !== 'president') {
        setFlash('error', 'Only the president can delete user accounts.');
    } elseif ($targetId === $user['id']) {
        setFlash('error', 'You cannot delete your own account.');
    } else {
        $targetStmt = db()->prepare('SELECT id, role_id FROM users WHERE id = ?');
        $targetStmt->execute([$targetId]);
        $target = $targetStmt->fetch();

        if (!$target) {
            setFlash('error', 'User not found.');
        } else {
            // Check if they are a member with active loans
            $memberStmt = db()->prepare('SELECT id FROM members WHERE user_id = ?');
            $memberStmt->execute([$targetId]);
            $memberId = $memberStmt->fetchColumn();

            $hasActiveLoan = false;
            if ($memberId) {
                $loanStmt = db()->prepare("SELECT id FROM loans WHERE member_id = ? AND status = 'active'");
                $loanStmt->execute([$memberId]);
                $hasActiveLoan = (bool) $loanStmt->fetch();
            }

            if ($hasActiveLoan) {
                setFlash('error', 'Cannot delete: this user has an active loan. Settle it first.');
            } else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    if ($memberId) {
                        $pdo->prepare('DELETE FROM members WHERE id = ?')->execute([$memberId]);
                    }
                    $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$targetId]);
                    $pdo->commit();
                    setFlash('success', 'User account deleted.');
                } catch (PDOException $ex) {
                    $pdo->rollBack();
                    setFlash('error', 'Could not delete user: they may have related records.');
                }
            }
        }
    }
    redirect('modules/members/profile.php');
}

$nextOfKin = [];
$myDocuments = [];
if ($member) {
    $stmt = db()->prepare('SELECT * FROM next_of_kin WHERE member_id = ?');
    $stmt->execute([$member['id']]);
    $nextOfKin = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT * FROM member_documents WHERE member_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$member['id']]);
    $myDocuments = $stmt->fetchAll();
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>My Profile</h1>
    <div class="flex items-center gap-4 mb-4">
        <?= avatarHtml($user['photo_path'], $user['full_name'], 'w-20 h-20 text-2xl') ?>
        <div>
            <p class="mb-0"><strong><?= e($user['full_name']) ?></strong></p>
            <p class="text-gray-500 text-sm mb-0"><?= e(str_replace('_', ' ', ucfirst($user['role_name']))) ?></p>
        </div>
    </div>
    <p><strong>Username:</strong> <?= e($user['username']) ?></p>
    <p><strong>Email:</strong> <?= e($user['email']) ?></p>
    <p><strong>Phone:</strong> <?= e($user['phone']) ?></p>

    <?php if ($member): ?>
        <hr>
        <p><strong>Member Number:</strong> <?= e($member['member_number']) ?></p>
        <p><strong>Join Date:</strong> <?= e($member['join_date']) ?></p>
        <p><strong>Membership Status:</strong> <?= statusBadge($member['membership_status']) ?></p>
        <?php if ($member['national_id']): ?><p><strong>National ID:</strong> <?= e($member['national_id']) ?></p><?php endif; ?>
        <?php if ($member['address']): ?><p><strong>Address:</strong> <?= e($member['address']) ?></p><?php endif; ?>
        <?php if ($member['gender']): ?><p><strong>Gender:</strong> <?= e(ucfirst($member['gender'])) ?></p><?php endif; ?>
        <?php if ($member['date_of_birth']): ?><p><strong>Date of Birth:</strong> <?= e($member['date_of_birth']) ?></p><?php endif; ?>
        <?php if ($member['occupation']): ?><p><strong>Occupation:</strong> <?= e($member['occupation']) ?></p><?php endif; ?>
    <?php endif; ?>
</div>

<div class="dashboard-grid" style="margin-bottom:1.5rem;">
    <div class="card">
        <h2>Edit Profile</h2>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($user['email']) ?>">

            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="<?= e($user['phone']) ?>">

            <?php if ($member): ?>
                <label for="national_id">National ID</label>
                <input type="text" id="national_id" name="national_id" value="<?= e($member['national_id']) ?>">

                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?= e($member['address']) ?>">

                <label for="gender">Gender</label>
                <select id="gender" name="gender">
                    <option value="">-- Select --</option>
                    <option value="male" <?= $member['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= $member['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= $member['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                </select>

                <label for="date_of_birth">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="<?= e($member['date_of_birth']) ?>">

                <label for="occupation">Occupation</label>
                <input type="text" id="occupation" name="occupation" value="<?= e($member['occupation']) ?>">
            <?php endif; ?>

            <button type="submit">Save Changes</button>
        </form>
    </div>

    <div class="card">
        <h2>Profile Picture</h2>
        <form method="post" action="" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="upload_photo">
            <label for="photo">Upload a photo (PNG, JPEG, or WEBP, max 2 MB)</label>
            <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp" required>
            <button type="submit">Upload Photo</button>
        </form>
    </div>
</div>

<div class="card max-w-lg">
    <h2>Change Password</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_password">

        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" required>

        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" minlength="6" required>

        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>

        <button type="submit">Change Password</button>
    </form>
</div>

<?php if ($user['role_name'] === 'president'):
    $allUsersStmt = db()->query(
        "SELECT id, full_name, username, role_name, status FROM users ORDER BY id"
    );
    $allUsers = $allUsersStmt->fetchAll();
?>
<div class="card">
    <h2>Manage User Accounts (President Only)</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($allUsers as $u): ?>
            <tr>
                <td><?= e($u['full_name']) ?></td>
                <td><?= e($u['username']) ?></td>
                <td><?= e(str_replace('_', ' ', ucfirst($u['role_name']))) ?></td>
                <td><?= statusBadge($u['status']) ?></td>
                <td>
                    <?php if ((int) $u['id'] !== $user['id']): ?>
                    <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure you want to delete <?= e($u['full_name']) ?>? This cannot be undone.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="target_user_id" value="<?= e((string) $u['id']) ?>">
                        <button type="submit" style="background:#dc2626;color:#fff;">Delete</button>
                    </form>
                    <?php else: ?>
                        <span class="text-gray-400 text-sm">You</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php if ($member): ?>
<div class="card">
    <h2>Next of Kin</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Address</th></tr></thead>
        <tbody>
        <?php foreach ($nextOfKin as $kin): ?>
            <tr>
                <td><?= e($kin['full_name']) ?></td>
                <td><?= e($kin['relationship']) ?></td>
                <td><?= e($kin['phone']) ?></td>
                <td><?= e($kin['address']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$nextOfKin): ?>
            <tr><td colspan="4">No next of kin on file yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card max-w-lg">
    <h2>Add Next of Kin</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_kin">

        <label for="kin_name">Full Name</label>
        <input type="text" id="kin_name" name="kin_name" required>

        <label for="kin_relationship">Relationship</label>
        <input type="text" id="kin_relationship" name="kin_relationship" placeholder="e.g. Spouse, Parent, Sibling">

        <label for="kin_phone">Phone</label>
        <input type="text" id="kin_phone" name="kin_phone">

        <label for="kin_address">Address</label>
        <input type="text" id="kin_address" name="kin_address">

        <button type="submit">Add</button>
    </form>
</div>

<div class="card">
    <h2>My Documents</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Type</th><th>Uploaded</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($myDocuments as $doc): ?>
            <tr>
                <td><?= e(str_replace('_', ' ', ucfirst($doc['document_type']))) ?></td>
                <td><?= e($doc['uploaded_at']) ?></td>
                <td><a class="btn" href="<?= e(APP_URL) ?>/<?= e($doc['file_path']) ?>" target="_blank" rel="noopener">Download</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$myDocuments): ?>
            <tr><td colspan="3">No documents uploaded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card max-w-lg">
    <h2>Upload a Document</h2>
    <p class="text-gray-500 text-sm">e.g. a scan of your national ID.</p>
    <form method="post" action="" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload_document">

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
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
