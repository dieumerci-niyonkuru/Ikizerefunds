<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('savings.access');
$user = currentUser();
$canRecord = userHasPermission($user, 'savings.record');

if ($canRecord && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_saving') {
    verifyCsrf();

    $memberId      = (int) ($_POST['member_id'] ?? 0);
    $savingTypeId  = (int) ($_POST['saving_type_id'] ?? 0);
    $transactionType = ($_POST['transaction_type'] ?? '') === 'withdrawal' ? 'withdrawal' : 'deposit';
    $amount        = (float) ($_POST['amount'] ?? 0);
    $savingDate    = $_POST['saving_date'] ?? date('Y-m-d');
    $notes         = trim($_POST['notes'] ?? '') ?: null;

    if ($memberId <= 0 || $savingTypeId <= 0 || $amount <= 0) {
        setFlash('error', 'Please select a member, savings type, and a valid amount.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO savings (member_id, saving_type_id, transaction_type, amount, saving_date, recorded_by, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$memberId, $savingTypeId, $transactionType, $amount, $savingDate, $user['id'], $notes]);
        setFlash('success', 'Savings transaction recorded.');
    }
    redirect('modules/savings/index.php');
}

// ------------------------------------------------------------
// Edit a previously recorded transaction
// ------------------------------------------------------------
if ($canRecord && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_saving') {
    verifyCsrf();

    $savingId = (int) ($_POST['saving_id'] ?? 0);
    $savingTypeId = (int) ($_POST['saving_type_id'] ?? 0);
    $transactionType = ($_POST['transaction_type'] ?? '') === 'withdrawal' ? 'withdrawal' : 'deposit';
    $amount = (float) ($_POST['amount'] ?? 0);
    $savingDate = $_POST['saving_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '') ?: null;

    if ($savingTypeId <= 0 || $amount <= 0) {
        setFlash('error', 'Please choose a savings type and a valid amount.');
    } else {
        db()->prepare(
            'UPDATE savings SET saving_type_id = ?, transaction_type = ?, amount = ?, saving_date = ?, notes = ? WHERE id = ?'
        )->execute([$savingTypeId, $transactionType, $amount, $savingDate, $notes, $savingId]);
        setFlash('success', 'Transaction updated.');
    }
    redirect('modules/savings/index.php');
}

// ------------------------------------------------------------
// Delete a previously recorded transaction
// ------------------------------------------------------------
if ($canRecord && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_saving') {
    verifyCsrf();
    $savingId = (int) ($_POST['saving_id'] ?? 0);
    db()->prepare('DELETE FROM savings WHERE id = ?')->execute([$savingId]);
    setFlash('success', 'Transaction deleted.');
    redirect('modules/savings/index.php');
}

$savingTypes = db()->query('SELECT id, name FROM saving_types ORDER BY name')->fetchAll();

if ($canRecord) {
    // Staff view: every member's running balance + the full transaction log.
    $balances = db()->query(
        "SELECT members.id, members.member_number, users.full_name,
                COALESCE(SUM(CASE WHEN savings.transaction_type = 'deposit' THEN savings.amount ELSE -savings.amount END), 0) AS balance
         FROM members
         JOIN users ON users.id = members.user_id
         LEFT JOIN savings ON savings.member_id = members.id
         GROUP BY members.id, members.member_number, users.full_name
         ORDER BY users.full_name"
    )->fetchAll();

    $members = db()->query(
        'SELECT members.id, members.member_number, users.full_name
         FROM members JOIN users ON users.id = members.user_id
         ORDER BY users.full_name'
    )->fetchAll();

    $transactions = db()->query(
        "SELECT savings.*, saving_types.name AS saving_type_name, users.full_name AS member_name
         FROM savings
         JOIN members ON members.id = savings.member_id
         JOIN users ON users.id = members.user_id
         JOIN saving_types ON saving_types.id = savings.saving_type_id
         ORDER BY savings.saving_date DESC, savings.id DESC
         LIMIT 50"
    )->fetchAll();

    $editSaving = null;
    if (!empty($_GET['edit'])) {
        foreach ($transactions as $t) {
            if ((int) $t['id'] === (int) $_GET['edit']) {
                $editSaving = $t;
                break;
            }
        }
    }
} else {
    // Member view: only their own balance and history.
    $stmt = db()->prepare('SELECT id FROM members WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $memberId = $stmt->fetchColumn();

    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END), 0) AS balance
         FROM savings WHERE member_id = ?"
    );
    $stmt->execute([$memberId]);
    $myBalance = $stmt->fetchColumn();

    $stmt = db()->prepare(
        "SELECT savings.*, saving_types.name AS saving_type_name
         FROM savings
         JOIN saving_types ON saving_types.id = savings.saving_type_id
         WHERE member_id = ?
         ORDER BY saving_date DESC, id DESC"
    );
    $stmt->execute([$memberId]);
    $myTransactions = $stmt->fetchAll();
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Savings</h1>

    <?php if ($canRecord): ?>
        <h2>Member Balances</h2>
        <table>
            <thead><tr><th>Member #</th><th>Name</th><th>Balance</th></tr></thead>
            <tbody>
            <?php foreach ($balances as $b): ?>
                <tr>
                    <td><?= e($b['member_number']) ?></td>
                    <td><?= e($b['full_name']) ?></td>
                    <td><?= formatMoney((float) $b['balance']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$balances): ?>
                <tr><td colspan="3">No members yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <h2>My Balance: <?= formatMoney((float) $myBalance) ?></h2>
    <?php endif; ?>
</div>

<?php if ($canRecord): ?>
<div class="card max-w-lg">
    <h2>Record Savings Transaction</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="record_saving">

        <label for="member_id">Member</label>
        <select id="member_id" name="member_id" required>
            <option value="">-- Select member --</option>
            <?php foreach ($members as $m): ?>
                <option value="<?= e((string) $m['id']) ?>"><?= e($m['member_number'] . ' - ' . $m['full_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="saving_type_id">Savings Type</label>
        <select id="saving_type_id" name="saving_type_id" required>
            <?php foreach ($savingTypes as $st): ?>
                <option value="<?= e((string) $st['id']) ?>"><?= e($st['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="transaction_type">Transaction Type</label>
        <select id="transaction_type" name="transaction_type" required>
            <option value="deposit">Deposit</option>
            <option value="withdrawal">Withdrawal</option>
        </select>

        <label for="amount">Amount</label>
        <input type="number" id="amount" name="amount" min="0.01" step="0.01" required>

        <label for="saving_date">Date</label>
        <input type="date" id="saving_date" name="saving_date" value="<?= e(date('Y-m-d')) ?>" required>

        <label for="notes">Notes</label>
        <input type="text" id="notes" name="notes">

        <button type="submit">Save Transaction</button>
    </form>
</div>
<?php endif; ?>

<?php if ($canRecord && $editSaving): ?>
<div class="card max-w-lg">
    <h2>Edit Transaction: <?= e($editSaving['member_name']) ?></h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit_saving">
        <input type="hidden" name="saving_id" value="<?= e((string) $editSaving['id']) ?>">

        <label for="edit_saving_type_id">Savings Type</label>
        <select id="edit_saving_type_id" name="saving_type_id" required>
            <?php foreach ($savingTypes as $st): ?>
                <option value="<?= e((string) $st['id']) ?>" <?= (int) $editSaving['saving_type_id'] === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="edit_transaction_type">Transaction Type</label>
        <select id="edit_transaction_type" name="transaction_type" required>
            <option value="deposit" <?= $editSaving['transaction_type'] === 'deposit' ? 'selected' : '' ?>>Deposit</option>
            <option value="withdrawal" <?= $editSaving['transaction_type'] === 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
        </select>

        <label for="edit_amount">Amount</label>
        <input type="number" id="edit_amount" name="amount" min="0.01" step="0.01" value="<?= e((string) $editSaving['amount']) ?>" required>

        <label for="edit_saving_date">Date</label>
        <input type="date" id="edit_saving_date" name="saving_date" value="<?= e($editSaving['saving_date']) ?>" required>

        <label for="edit_notes">Notes</label>
        <input type="text" id="edit_notes" name="notes" value="<?= e($editSaving['notes']) ?>">

        <button type="submit">Save Changes</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-2">
        <a href="<?= e(APP_URL) ?>/modules/savings/index.php">Cancel</a>
    </p>
</div>
<?php endif; ?>

<div class="card">
    <h2><?= $canRecord ? 'Recent Transactions' : 'My Transaction History' ?></h2>
    <table>
        <thead>
        <tr>
            <?php if ($canRecord): ?><th>Member</th><?php endif; ?>
            <th>Type</th><th>Category</th><th>Amount</th><th>Date</th><th>Notes</th>
            <?php if ($canRecord): ?><th></th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach (($canRecord ? $transactions : $myTransactions) as $t): ?>
            <tr>
                <?php if ($canRecord): ?><td><?= e($t['member_name']) ?></td><?php endif; ?>
                <td><?= statusBadge($t['transaction_type']) ?></td>
                <td><?= e($t['saving_type_name']) ?></td>
                <td><?= formatMoney((float) $t['amount']) ?></td>
                <td><?= e($t['saving_date']) ?></td>
                <td><?= e($t['notes']) ?></td>
                <?php if ($canRecord): ?>
                <td>
                    <a class="btn" href="<?= e(APP_URL) ?>/modules/savings/index.php?edit=<?= e((string) $t['id']) ?>">Edit</a>
                    <form method="post" style="display:inline-block">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_saving">
                        <input type="hidden" name="saving_id" value="<?= e((string) $t['id']) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!($canRecord ? $transactions : $myTransactions)): ?>
            <tr><td colspan="<?= $canRecord ? 7 : 5 ?>">No transactions yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
