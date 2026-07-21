<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['president']);
$user = currentUser();

// Leadership positions only — "member" isn't a board seat.
const LEADERSHIP_ROLE_IDS_STMT = "SELECT id, name FROM roles WHERE name != 'member' ORDER BY id";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_term') {
    verifyCsrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $roleId = (int) ($_POST['role_id'] ?? 0);
    $startDate = $_POST['start_date'] ?? date('Y-m-d');

    // Guard against the President locking themselves out by reassigning their
    // own role away from President — that leaves nobody able to fix it here.
    $newRoleName = db()->prepare('SELECT name FROM roles WHERE id = ?');
    $newRoleName->execute([$roleId]);
    $newRoleName = $newRoleName->fetchColumn();

    if ($userId === (int) $user['id'] && $newRoleName !== 'president') {
        setFlash('error', 'You cannot remove your own President access here. Have another President make this change.');
        redirect('modules/board_terms/index.php');
    }

    $leadershipRoleIds = array_column(db()->query(LEADERSHIP_ROLE_IDS_STMT)->fetchAll(), 'id');

    $stmt = db()->prepare('SELECT id, role_id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();

    if (!$targetUser || !in_array($roleId, $leadershipRoleIds, true)) {
        setFlash('error', 'Please choose a valid member and leadership role.');
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Close whoever currently holds this role, if it's someone else.
            $stmt = $pdo->prepare(
                "SELECT id, user_id FROM board_terms WHERE role_id = ? AND end_date IS NULL AND user_id != ?"
            );
            $stmt->execute([$roleId, $userId]);
            $incumbent = $stmt->fetch();

            if ($incumbent) {
                $pdo->prepare('UPDATE board_terms SET end_date = ? WHERE id = ?')
                    ->execute([$startDate, $incumbent['id']]);

                // Only step them down from the system role if it's the one they're vacating.
                $incumbentUser = $pdo->prepare('SELECT role_id FROM users WHERE id = ?');
                $incumbentUser->execute([$incumbent['user_id']]);
                if ((int) $incumbentUser->fetchColumn() === $roleId) {
                    $pdo->prepare("UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'member') WHERE id = ?")
                        ->execute([$incumbent['user_id']]);
                }
            }

            // Close any other open term this same person holds in a *different* role
            // (e.g. moving the Accountant to Vice President) — one system role at a time.
            $pdo->prepare(
                'UPDATE board_terms SET end_date = ? WHERE user_id = ? AND role_id != ? AND end_date IS NULL'
            )->execute([$startDate, $userId, $roleId]);

            // Skip if this exact person already has an open term in this role.
            $stmt = $pdo->prepare(
                'SELECT id FROM board_terms WHERE user_id = ? AND role_id = ? AND end_date IS NULL'
            );
            $stmt->execute([$userId, $roleId]);
            if (!$stmt->fetch()) {
                $pdo->prepare('INSERT INTO board_terms (user_id, role_id, start_date) VALUES (?, ?, ?)')
                    ->execute([$userId, $roleId, $startDate]);
            }

            $pdo->prepare('UPDATE users SET role_id = ? WHERE id = ?')->execute([$roleId, $userId]);

            $pdo->commit();
            setFlash('success', 'Term started and system access updated.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not start term: ' . $e->getMessage());
        }
    }
    redirect('modules/board_terms/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'end_term') {
    verifyCsrf();
    $termId = (int) ($_POST['term_id'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM board_terms WHERE id = ? AND end_date IS NULL');
    $stmt->execute([$termId]);
    $term = $stmt->fetch();

    if ($term && (int) $term['user_id'] === (int) $user['id'] && $user['role_name'] === 'president') {
        setFlash('error', 'You cannot end your own President term here. Have another President make this change.');
        redirect('modules/board_terms/index.php');
    }

    if ($term) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE board_terms SET end_date = CURDATE() WHERE id = ?')->execute([$termId]);

            $stmt = $pdo->prepare('SELECT role_id FROM users WHERE id = ?');
            $stmt->execute([$term['user_id']]);
            if ((int) $stmt->fetchColumn() === (int) $term['role_id']) {
                $pdo->prepare("UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'member') WHERE id = ?")
                    ->execute([$term['user_id']]);
            }

            $pdo->commit();
            setFlash('success', 'Term ended; that user was reverted to Member access.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not end term: ' . $e->getMessage());
        }
    }
    redirect('modules/board_terms/index.php');
}

$allUsers = db()->query('SELECT id, full_name, username FROM users ORDER BY full_name')->fetchAll();
$leadershipRoles = db()->query(LEADERSHIP_ROLE_IDS_STMT)->fetchAll();

$terms = db()->query(
    "SELECT board_terms.*, users.full_name, users.username, roles.name AS role_name
     FROM board_terms
     JOIN users ON users.id = board_terms.user_id
     JOIN roles ON roles.id = board_terms.role_id
     ORDER BY (board_terms.end_date IS NULL) DESC, board_terms.start_date DESC"
)->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Board Terms</h1>
    <p class="text-gray-500 text-sm">Tracks who has held each leadership position and when. Starting a new term
    for a role automatically ends the previous holder's term and updates both accounts' system access.</p>
</div>

<div class="card max-w-lg">
    <h2>Start a New Term</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="start_term">

        <label for="user_id">Person</label>
        <select id="user_id" name="user_id" required>
            <option value="">-- Select person --</option>
            <?php foreach ($allUsers as $u): ?>
                <option value="<?= e((string) $u['id']) ?>"><?= e($u['full_name'] . ' (' . $u['username'] . ')') ?></option>
            <?php endforeach; ?>
        </select>

        <label for="role_id">Role</label>
        <select id="role_id" name="role_id" required>
            <?php foreach ($leadershipRoles as $r): ?>
                <option value="<?= e((string) $r['id']) ?>"><?= e(str_replace('_', ' ', ucfirst($r['name']))) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="start_date">Start Date</label>
        <input type="date" id="start_date" name="start_date" value="<?= e(date('Y-m-d')) ?>" required>

        <button type="submit">Start Term</button>
    </form>
</div>

<div class="card">
    <h2>Term History</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Role</th><th>Start Date</th><th>End Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($terms as $t): ?>
            <tr>
                <td><?= e($t['full_name']) ?></td>
                <td><?= e(str_replace('_', ' ', ucfirst($t['role_name']))) ?></td>
                <td><?= e($t['start_date']) ?></td>
                <td><?= $t['end_date'] ? e($t['end_date']) : statusBadge('active') ?></td>
                <td>
                    <?php if (!$t['end_date']): ?>
                        <form method="post" style="display:inline-block">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="end_term">
                            <input type="hidden" name="term_id" value="<?= e((string) $t['id']) ?>">
                            <button type="submit">End Term</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$terms): ?>
            <tr><td colspan="5">No board terms recorded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
