<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('members.manage');
$user = currentUser();
$canEdit = userHasPermission($user, 'members.edit');

// ------------------------------------------------------------
// Edit an existing member's info
// ------------------------------------------------------------
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_member') {
    verifyCsrf();

    $memberId = (int) ($_POST['member_id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $nationalId = trim($_POST['national_id'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? '') ?: null;
    $gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : null;
    $membershipStatus = in_array($_POST['membership_status'] ?? '', ['active', 'inactive', 'withdrawn', 'suspended'], true)
        ? $_POST['membership_status'] : 'active';

    $stmt = db()->prepare('SELECT user_id FROM members WHERE id = ?');
    $stmt->execute([$memberId]);
    $targetUserId = $stmt->fetchColumn();

    if (!$targetUserId || $fullName === '') {
        setFlash('error', 'Member not found, or full name is required.');
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?')
                ->execute([$fullName, $email, $phone, $targetUserId]);
            $pdo->prepare(
                'UPDATE members SET national_id = ?, address = ?, gender = ?, membership_status = ? WHERE id = ?'
            )->execute([$nationalId, $address, $gender, $membershipStatus, $memberId]);
            $pdo->commit();
            setFlash('success', 'Member updated.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not update member: that email, phone, or national ID may already be in use.');
        }
    }
    redirect('modules/members/index.php');
}

// ------------------------------------------------------------
// Delete a member (their membership record + history; the login
// account is deactivated rather than deleted, so historical
// records elsewhere - e.g. who recorded a transaction - stay intact)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_member') {
    verifyCsrf();

    $memberId = (int) ($_POST['member_id'] ?? 0);
    $stmt = db()->prepare('SELECT user_id FROM members WHERE id = ?');
    $stmt->execute([$memberId]);
    $targetUserId = $stmt->fetchColumn();

    $activeLoan = db()->prepare("SELECT id FROM loans WHERE member_id = ? AND status = 'active'");
    $activeLoan->execute([$memberId]);

    if (!$targetUserId) {
        setFlash('error', 'Member not found.');
    } elseif ($activeLoan->fetch()) {
        setFlash('error', 'Cannot delete this member: they have an active loan. Settle or close it first.');
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM members WHERE id = ?')->execute([$memberId]);
            $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$targetUserId]);
            $pdo->commit();
            setFlash('success', 'Member deleted and their login deactivated.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not delete member: they may still be guaranteeing another member\'s loan.');
        }
    }
    redirect('modules/members/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_member') {
    verifyCsrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '') ?: null;
    $phone    = trim($_POST['phone'] ?? '') ?: null;
    $joinDate = $_POST['join_date'] ?? date('Y-m-d');
    $memberNo = trim($_POST['member_number'] ?? '');
    $nationalId = trim($_POST['national_id'] ?? '') ?: null;
    $address    = trim($_POST['address'] ?? '') ?: null;
    $kinName    = trim($_POST['kin_name'] ?? '');
    $kinRelationship = trim($_POST['kin_relationship'] ?? '') ?: null;
    $kinPhone   = trim($_POST['kin_phone'] ?? '') ?: null;
    $prefillPhoto = trim($_POST['prefill_photo'] ?? '') ?: null;

    $photoPath = $prefillPhoto;
    if (!empty($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['photo']['tmp_name']);
        $size = $_FILES['photo']['size'];

        if (in_array($mime, $allowed, true) && $size <= 5 * 1024 * 1024) {
            $ext = match($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', default => 'jpg' };
            $filename = 'member-photo-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../../assets/uploads';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
            move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . '/' . $filename);
            $photoPath = 'assets/uploads/' . $filename;
        }
    }

    if ($fullName === '' || $username === '' || $memberNo === '') {
        setFlash('error', 'Full name, username, and member number are required.');
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $tempPassword = bin2hex(random_bytes(4)); // shared with the member out-of-band
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = "member"');
            $roleStmt->execute();
            $roleId = $roleStmt->fetchColumn();

            $stmt = $pdo->prepare(
                'INSERT INTO users (role_id, full_name, username, email, phone, photo_path, password_hash, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "active")'
            );
            $stmt->execute([$roleId, $fullName, $username, $email, $phone, $photoPath, password_hash($tempPassword, PASSWORD_DEFAULT)]);
            $userId = $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO members (user_id, member_number, join_date, national_id, address) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $memberNo, $joinDate, $nationalId, $address]);
            $memberId = $pdo->lastInsertId();

            if ($kinName !== '') {
                $stmt = $pdo->prepare(
                    'INSERT INTO next_of_kin (member_id, full_name, relationship, phone) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$memberId, $kinName, $kinRelationship, $kinPhone]);
            }

            $pdo->commit();
            setFlash('success', "Member registered. Temporary password: {$tempPassword} (share securely).");
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not register member: username or member number may already be in use.');
        }
    }
    redirect('modules/members/index.php');
}

$searchQ = trim($_GET['q'] ?? '');
$statusFilter = in_array($_GET['status'] ?? '', ['active', 'inactive', 'withdrawn', 'suspended'], true)
    ? $_GET['status'] : '';

$where = [];
$params = [];
if ($searchQ !== '') {
    $where[] = '(users.full_name LIKE ? OR users.username LIKE ? OR members.member_number LIKE ?)';
    $like = '%' . $searchQ . '%';
    array_push($params, $like, $like, $like);
}
if ($statusFilter !== '') {
    $where[] = 'members.membership_status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = db()->prepare(
    "SELECT members.id, members.member_number, members.join_date, members.membership_status,
            members.national_id, members.address, members.gender,
            users.full_name, users.username, users.phone, users.email, users.photo_path
     FROM members
     JOIN users ON users.id = members.user_id
     $whereSql
     ORDER BY members.join_date DESC"
);
$stmt->execute($params);
$members = $stmt->fetchAll();

$editMember = null;
if ($canEdit && !empty($_GET['edit'])) {
    $editStmt = db()->prepare(
        'SELECT members.id, members.national_id, members.address, members.gender, members.membership_status,
                users.full_name, users.email, users.phone
         FROM members JOIN users ON users.id = members.user_id
         WHERE members.id = ?'
    );
    $editStmt->execute([$_GET['edit']]);
    $editMember = $editStmt->fetch() ?: null;
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Members</h1>
    <form method="get" action="" class="filter-bar">
        <div>
            <label for="q">Search</label>
            <input type="text" id="q" name="q" placeholder="Name, username, or member #" value="<?= e($searchQ) ?>">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All</option>
                <?php foreach (['active', 'inactive', 'withdrawn', 'suspended'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">Filter</button>
        <?php if ($searchQ !== '' || $statusFilter !== ''): ?>
            <a class="btn btn-plain" style="color:#6b7280;" href="<?= e(APP_URL) ?>/modules/members/index.php">Clear</a>
        <?php endif; ?>
    </form>
    <div class="table-wrap">
    <table>
        <thead>
        <tr><th></th><th>#</th><th>Name</th><th>Username</th><th>Phone</th><th>Joined</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($members as $m): ?>
            <tr>
                <td><?= avatarHtml($m['photo_path'], $m['full_name']) ?></td>
                <td><?= e($m['member_number']) ?></td>
                <td><?= e($m['full_name']) ?></td>
                <td><?= e($m['username']) ?></td>
                <td><?= e($m['phone']) ?></td>
                <td><?= e($m['join_date']) ?></td>
                <td><?= statusBadge($m['membership_status']) ?></td>
                <td>
                    <?php if ($canEdit): ?>
                        <a class="btn" href="<?= e(APP_URL) ?>/modules/members/index.php?edit=<?= e((string) $m['id']) ?>">Edit</a>
                    <?php endif; ?>
                    <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure? This cannot be undone.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_member">
                        <input type="hidden" name="member_id" value="<?= e((string) $m['id']) ?>">
                        <button type="submit" style="background:#dc2626;color:#fff;">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$members): ?>
            <tr><td colspan="8">No members registered yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($editMember): ?>
<div class="card max-w-lg">
    <h2>Edit Member: <?= e($editMember['full_name']) ?></h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_member">
        <input type="hidden" name="member_id" value="<?= e((string) $editMember['id']) ?>">

        <label for="edit_full_name">Full Name</label>
        <input type="text" id="edit_full_name" name="full_name" value="<?= e($editMember['full_name']) ?>" required>

        <label for="edit_email">Email</label>
        <input type="email" id="edit_email" name="email" value="<?= e($editMember['email']) ?>">

        <label for="edit_phone">Phone</label>
        <input type="text" id="edit_phone" name="phone" value="<?= e($editMember['phone']) ?>">

        <label for="edit_national_id">National ID</label>
        <input type="text" id="edit_national_id" name="national_id" value="<?= e($editMember['national_id']) ?>">

        <label for="edit_address">Address</label>
        <input type="text" id="edit_address" name="address" value="<?= e($editMember['address']) ?>">

        <label for="edit_gender">Gender</label>
        <select id="edit_gender" name="gender">
            <option value="">-- Select --</option>
            <option value="male" <?= $editMember['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= $editMember['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
            <option value="other" <?= $editMember['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
        </select>

        <label for="edit_membership_status">Membership Status</label>
        <select id="edit_membership_status" name="membership_status">
            <?php foreach (['active', 'inactive', 'withdrawn', 'suspended'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $editMember['membership_status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Save Changes</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-2">
        <a href="<?= e(APP_URL) ?>/modules/members/index.php">Cancel</a>
    </p>
</div>
<?php endif; ?>

<div class="card max-w-lg">
    <h2>Register New Member</h2>
    <?php $prefillPhoto = $_GET['prefill_photo'] ?? ''; ?>
    <?php if ($prefillPhoto): ?>
        <div class="flex items-center gap-3 mb-4 p-3 bg-green-50 rounded-lg">
            <img src="<?= e(APP_URL) ?>/<?= e($prefillPhoto) ?>" alt="Applicant photo" class="w-12 h-12 rounded-full object-cover border border-gray-200">
            <div class="text-sm text-green-800">Photo from membership request will be used as profile picture.</div>
        </div>
    <?php endif; ?>
    <form method="post" action="" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_member">
        <input type="hidden" name="prefill_photo" value="<?= e($prefillPhoto) ?>">

        <label for="member_number">Member Number</label>
        <input type="text" id="member_number" name="member_number" required>

        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($_GET['prefill_name'] ?? '') ?>" required>

        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($_GET['prefill_email'] ?? '') ?>">

        <label for="phone">Phone</label>
        <input type="text" id="phone" name="phone" value="<?= e($_GET['prefill_phone'] ?? '') ?>">

        <label for="join_date">Join Date</label>
        <input type="date" id="join_date" name="join_date" value="<?= e(date('Y-m-d')) ?>" required>

        <label for="national_id">National ID</label>
        <input type="text" id="national_id" name="national_id">

        <label for="address">Address</label>
        <input type="text" id="address" name="address">

        <label for="photo">Profile Photo (optional)</label>
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
        <small class="text-gray-400">JPG, PNG, GIF, or WebP. Max 5 MB.</small>

        <h3 class="mb-1" style="margin-top:1rem;">Next of Kin (optional)</h3>
        <label for="kin_name">Full Name</label>
        <input type="text" id="kin_name" name="kin_name">

        <label for="kin_relationship">Relationship</label>
        <input type="text" id="kin_relationship" name="kin_relationship" placeholder="e.g. Spouse, Parent, Sibling">

        <label for="kin_phone">Phone</label>
        <input type="text" id="kin_phone" name="kin_phone">

        <button type="submit">Register Member</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
