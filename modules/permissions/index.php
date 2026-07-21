<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Hardcoded on purpose (not requirePermission()) — this is the screen that
// configures every other permission, so it must never be editable into
// inaccessibility. Board Terms is the other page held to this same rule.
requireRole(['president']);
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_role') {
    verifyCsrf();
    $name = strtolower(trim(str_replace(' ', '_', $_POST['name'] ?? '')));
    $description = trim($_POST['description'] ?? '') ?: null;

    if ($name === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
        setFlash('error', 'Role name must start with a letter and contain only letters, numbers, and underscores.');
    } else {
        try {
            db()->prepare('INSERT INTO roles (name, description) VALUES (?, ?)')->execute([$name, $description]);
            setFlash('success', "Role '{$name}' created. Assign its permissions below, then it's available anywhere a role can be picked (e.g. Board Terms).");
        } catch (PDOException $e) {
            setFlash('error', 'Could not create role: that name may already exist.');
        }
    }
    redirect('modules/permissions/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_matrix') {
    verifyCsrf();

    $roles = db()->query('SELECT id FROM roles')->fetchAll();
    $permissions = db()->query('SELECT id FROM permissions')->fetchAll();
    $grid = $_POST['perm'] ?? [];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM role_permissions');
        $insert = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($roles as $r) {
            foreach ($permissions as $p) {
                if (!empty($grid[$r['id']][$p['id']])) {
                    $insert->execute([$r['id'], $p['id']]);
                }
            }
        }
        $pdo->commit();
        setFlash('success', 'Permissions updated.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlash('error', 'Could not save permissions: ' . $e->getMessage());
    }
    redirect('modules/permissions/index.php');
}

$roles = db()->query('SELECT * FROM roles ORDER BY id')->fetchAll();
$permissions = db()->query('SELECT * FROM permissions ORDER BY code')->fetchAll();

$grantedPairs = db()->query('SELECT role_id, permission_id FROM role_permissions')->fetchAll();
$granted = [];
foreach ($grantedPairs as $pair) {
    $granted[$pair['role_id'] . ':' . $pair['permission_id']] = true;
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Permissions</h1>
    <p class="text-gray-500 text-sm">Controls which roles can access which modules. Board Terms and this
    Permissions screen itself always stay reachable to the President regardless of what's configured here,
    so misconfiguring the matrix below can never lock you out of fixing it.</p>
</div>

<div class="card max-w-lg">
    <h2>Create a New Role</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_role">

        <label for="name">Role Name</label>
        <input type="text" id="name" name="name" placeholder="e.g. treasurer" required>

        <label for="description">Description</label>
        <input type="text" id="description" name="description">

        <button type="submit">Create Role</button>
    </form>
</div>

<div class="card">
    <h2>Permission Matrix</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_matrix">
        <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Permission</th>
                <?php foreach ($roles as $r): ?>
                    <th><?= e(str_replace('_', ' ', ucfirst($r['name']))) ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($permissions as $p): ?>
                <tr>
                    <td>
                        <div class="font-semibold"><?= e($p['code']) ?></div>
                        <div class="text-gray-500 text-sm"><?= e($p['description']) ?></div>
                    </td>
                    <?php foreach ($roles as $r): ?>
                        <td style="text-align:center;">
                            <input type="checkbox"
                                   name="perm[<?= e((string) $r['id']) ?>][<?= e((string) $p['id']) ?>]"
                                   value="1"
                                   <?= isset($granted[$r['id'] . ':' . $p['id']]) ? 'checked' : '' ?>>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <button type="submit" class="mt-4">Save Permissions</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
