<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('finance.manage');
$user = currentUser();

// ------------------------------------------------------------
// Issue a fine
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_fine') {
    verifyCsrf();
    $memberId = (int) ($_POST['member_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $fineDate = $_POST['fine_date'] ?? date('Y-m-d');

    if ($memberId <= 0 || $reason === '' || $amount <= 0) {
        setFlash('error', 'Please select a member, reason, and a valid amount.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO fines (member_id, reason, amount, fine_date, recorded_by) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$memberId, $reason, $amount, $fineDate, $user['id']]);
        setFlash('success', 'Fine recorded.');
    }
    redirect('modules/finance/index.php');
}

// ------------------------------------------------------------
// Mark a fine paid / waived
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_fine') {
    verifyCsrf();
    $fineId = (int) ($_POST['fine_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (in_array($status, ['paid', 'waived'], true)) {
        $stmt = db()->prepare('UPDATE fines SET status = ?, paid_date = ? WHERE id = ?');
        $stmt->execute([$status, $status === 'paid' ? date('Y-m-d') : null, $fineId]);
        setFlash('success', 'Fine updated.');
    }
    redirect('modules/finance/index.php');
}

// ------------------------------------------------------------
// Edit a fine's core details
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_fine') {
    verifyCsrf();
    $fineId = (int) ($_POST['fine_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $fineDate = $_POST['fine_date'] ?? date('Y-m-d');

    if ($reason === '' || $amount <= 0) {
        setFlash('error', 'Please provide a reason and a valid amount.');
    } else {
        db()->prepare('UPDATE fines SET reason = ?, amount = ?, fine_date = ? WHERE id = ?')
            ->execute([$reason, $amount, $fineDate, $fineId]);
        setFlash('success', 'Fine updated.');
    }
    redirect('modules/finance/index.php');
}

// ------------------------------------------------------------
// Delete a fine
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_fine') {
    verifyCsrf();
    $fineId = (int) ($_POST['fine_id'] ?? 0);
    db()->prepare('DELETE FROM fines WHERE id = ?')->execute([$fineId]);
    setFlash('success', 'Fine deleted.');
    redirect('modules/finance/index.php');
}

// ------------------------------------------------------------
// Record an expense
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
    verifyCsrf();
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $amount = (float) ($_POST['amount'] ?? 0);
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');

    if ($category === '' || $amount <= 0) {
        setFlash('error', 'Please provide a category and a valid amount.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO expenses (category, description, amount, expense_date, approved_by) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$category, $description, $amount, $expenseDate, $user['id']]);
        setFlash('success', 'Expense recorded.');
    }
    redirect('modules/finance/index.php');
}

// ------------------------------------------------------------
// Edit / delete an expense
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_expense') {
    verifyCsrf();
    $expenseId = (int) ($_POST['expense_id'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $amount = (float) ($_POST['amount'] ?? 0);
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');

    if ($category === '' || $amount <= 0) {
        setFlash('error', 'Please provide a category and a valid amount.');
    } else {
        db()->prepare('UPDATE expenses SET category = ?, description = ?, amount = ?, expense_date = ? WHERE id = ?')
            ->execute([$category, $description, $amount, $expenseDate, $expenseId]);
        setFlash('success', 'Expense updated.');
    }
    redirect('modules/finance/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_expense') {
    verifyCsrf();
    $expenseId = (int) ($_POST['expense_id'] ?? 0);
    db()->prepare('DELETE FROM expenses WHERE id = ?')->execute([$expenseId]);
    setFlash('success', 'Expense deleted.');
    redirect('modules/finance/index.php');
}

// ------------------------------------------------------------
// Record other income
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_income') {
    verifyCsrf();
    $source = trim($_POST['source'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $amount = (float) ($_POST['amount'] ?? 0);
    $incomeDate = $_POST['income_date'] ?? date('Y-m-d');

    if ($source === '' || $amount <= 0) {
        setFlash('error', 'Please provide a source and a valid amount.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO income (source, description, amount, income_date, recorded_by) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$source, $description, $amount, $incomeDate, $user['id']]);
        setFlash('success', 'Income recorded.');
    }
    redirect('modules/finance/index.php');
}

// ------------------------------------------------------------
// Edit / delete an income entry
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_income') {
    verifyCsrf();
    $incomeId = (int) ($_POST['income_id'] ?? 0);
    $source = trim($_POST['source'] ?? '');
    $description = trim($_POST['description'] ?? '') ?: null;
    $amount = (float) ($_POST['amount'] ?? 0);
    $incomeDate = $_POST['income_date'] ?? date('Y-m-d');

    if ($source === '' || $amount <= 0) {
        setFlash('error', 'Please provide a source and a valid amount.');
    } else {
        db()->prepare('UPDATE income SET source = ?, description = ?, amount = ?, income_date = ? WHERE id = ?')
            ->execute([$source, $description, $amount, $incomeDate, $incomeId]);
        setFlash('success', 'Income updated.');
    }
    redirect('modules/finance/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_income') {
    verifyCsrf();
    $incomeId = (int) ($_POST['income_id'] ?? 0);
    db()->prepare('DELETE FROM income WHERE id = ?')->execute([$incomeId]);
    setFlash('success', 'Income deleted.');
    redirect('modules/finance/index.php');
}

// ------------------------------------------------------------
// Data for display
// ------------------------------------------------------------
$members = db()->query(
    'SELECT members.id, members.member_number, users.full_name
     FROM members JOIN users ON users.id = members.user_id
     ORDER BY users.full_name'
)->fetchAll();

$fines = db()->query(
    "SELECT fines.*, users.full_name, members.member_number
     FROM fines
     JOIN members ON members.id = fines.member_id
     JOIN users ON users.id = members.user_id
     ORDER BY fines.fine_date DESC LIMIT 30"
)->fetchAll();

$expenses = db()->query('SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 20')->fetchAll();
$income = db()->query('SELECT * FROM income ORDER BY income_date DESC LIMIT 20')->fetchAll();

$findById = fn(array $rows, $id) => current(array_filter($rows, fn($r) => (int) $r['id'] === (int) $id)) ?: null;
$editFine = !empty($_GET['edit_fine']) ? $findById($fines, $_GET['edit_fine']) : null;
$editExpense = !empty($_GET['edit_expense']) ? $findById($expenses, $_GET['edit_expense']) : null;
$editIncome = !empty($_GET['edit_income']) ? $findById($income, $_GET['edit_income']) : null;

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Finance</h1>
    <p class="text-gray-500 text-sm">Fines, expenses, and other income &mdash; these feed directly into the Financial Report.</p>
</div>

<div class="dashboard-grid" style="margin-bottom:1.5rem;">
    <div class="card">
        <h2>Issue a Fine</h2>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_fine">
            <label for="member_id">Member</label>
            <select id="member_id" name="member_id" required>
                <option value="">-- Select member --</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= e((string) $m['id']) ?>"><?= e($m['member_number'] . ' - ' . $m['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="reason">Reason</label>
            <input type="text" id="reason" name="reason" placeholder="e.g. Meeting absence" required>
            <label for="fine_amount">Amount</label>
            <input type="number" id="fine_amount" name="amount" min="0.01" step="0.01" required>
            <label for="fine_date">Date</label>
            <input type="date" id="fine_date" name="fine_date" value="<?= e(date('Y-m-d')) ?>" required>
            <button type="submit">Record Fine</button>
        </form>
    </div>

    <div class="card">
        <h2>Record Expense</h2>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_expense">
            <label for="category">Category</label>
            <input type="text" id="category" name="category" placeholder="e.g. Stationery" required>
            <label for="exp_description">Description</label>
            <input type="text" id="exp_description" name="description">
            <label for="exp_amount">Amount</label>
            <input type="number" id="exp_amount" name="amount" min="0.01" step="0.01" required>
            <label for="expense_date">Date</label>
            <input type="date" id="expense_date" name="expense_date" value="<?= e(date('Y-m-d')) ?>" required>
            <button type="submit">Record Expense</button>
        </form>
    </div>

    <div class="card">
        <h2>Record Other Income</h2>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_income">
            <label for="source">Source</label>
            <input type="text" id="source" name="source" placeholder="e.g. Registration fee">
            <label for="inc_description">Description</label>
            <input type="text" id="inc_description" name="description">
            <label for="inc_amount">Amount</label>
            <input type="number" id="inc_amount" name="amount" min="0.01" step="0.01" required>
            <label for="income_date">Date</label>
            <input type="date" id="income_date" name="income_date" value="<?= e(date('Y-m-d')) ?>" required>
            <button type="submit">Record Income</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>Fines</h2>
    <table>
        <thead><tr><th>Member</th><th>Reason</th><th>Amount</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($fines as $f): ?>
            <tr>
                <td><?= e($f['member_number'] . ' - ' . $f['full_name']) ?></td>
                <td><?= e($f['reason']) ?></td>
                <td><?= formatMoney((float) $f['amount']) ?></td>
                <td><?= e($f['fine_date']) ?></td>
                <td><?= statusBadge($f['status']) ?></td>
                <td>
                    <?php if ($f['status'] === 'unpaid'): ?>
                        <form method="post" style="display:inline-block">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_fine">
                            <input type="hidden" name="fine_id" value="<?= e((string) $f['id']) ?>">
                            <input type="hidden" name="status" value="paid">
                            <button type="submit">Mark Paid</button>
                        </form>
                        <form method="post" style="display:inline-block">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_fine">
                            <input type="hidden" name="fine_id" value="<?= e((string) $f['id']) ?>">
                            <input type="hidden" name="status" value="waived">
                            <button type="submit">Waive</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn" href="<?= e(APP_URL) ?>/modules/finance/index.php?edit_fine=<?= e((string) $f['id']) ?>">Edit</a>
                    <form method="post" style="display:inline-block">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_fine">
                        <input type="hidden" name="fine_id" value="<?= e((string) $f['id']) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$fines): ?>
            <tr><td colspan="6">No fines recorded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($editFine): ?>
<div class="card max-w-lg">
    <h2>Edit Fine: <?= e($editFine['full_name']) ?></h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit_fine">
        <input type="hidden" name="fine_id" value="<?= e((string) $editFine['id']) ?>">
        <label for="edit_fine_reason">Reason</label>
        <input type="text" id="edit_fine_reason" name="reason" value="<?= e($editFine['reason']) ?>" required>
        <label for="edit_fine_amount">Amount</label>
        <input type="number" id="edit_fine_amount" name="amount" min="0.01" step="0.01" value="<?= e((string) $editFine['amount']) ?>" required>
        <label for="edit_fine_date">Date</label>
        <input type="date" id="edit_fine_date" name="fine_date" value="<?= e($editFine['fine_date']) ?>" required>
        <button type="submit">Save Changes</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-2"><a href="<?= e(APP_URL) ?>/modules/finance/index.php">Cancel</a></p>
</div>
<?php endif; ?>

<div class="card">
    <h2>Recent Expenses</h2>
    <table>
        <thead><tr><th>Category</th><th>Description</th><th>Amount</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($expenses as $e): ?>
            <tr>
                <td><?= e($e['category']) ?></td>
                <td><?= e($e['description']) ?></td>
                <td><?= formatMoney((float) $e['amount']) ?></td>
                <td><?= e($e['expense_date']) ?></td>
                <td>
                    <a class="btn" href="<?= e(APP_URL) ?>/modules/finance/index.php?edit_expense=<?= e((string) $e['id']) ?>">Edit</a>
                    <form method="post" style="display:inline-block">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_expense">
                        <input type="hidden" name="expense_id" value="<?= e((string) $e['id']) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$expenses): ?>
            <tr><td colspan="5">No expenses recorded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($editExpense): ?>
<div class="card max-w-lg">
    <h2>Edit Expense</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit_expense">
        <input type="hidden" name="expense_id" value="<?= e((string) $editExpense['id']) ?>">
        <label for="edit_exp_category">Category</label>
        <input type="text" id="edit_exp_category" name="category" value="<?= e($editExpense['category']) ?>" required>
        <label for="edit_exp_description">Description</label>
        <input type="text" id="edit_exp_description" name="description" value="<?= e($editExpense['description']) ?>">
        <label for="edit_exp_amount">Amount</label>
        <input type="number" id="edit_exp_amount" name="amount" min="0.01" step="0.01" value="<?= e((string) $editExpense['amount']) ?>" required>
        <label for="edit_exp_date">Date</label>
        <input type="date" id="edit_exp_date" name="expense_date" value="<?= e($editExpense['expense_date']) ?>" required>
        <button type="submit">Save Changes</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-2"><a href="<?= e(APP_URL) ?>/modules/finance/index.php">Cancel</a></p>
</div>
<?php endif; ?>

<div class="card">
    <h2>Recent Other Income</h2>
    <table>
        <thead><tr><th>Source</th><th>Description</th><th>Amount</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($income as $i): ?>
            <tr>
                <td><?= e($i['source']) ?></td>
                <td><?= e($i['description']) ?></td>
                <td><?= formatMoney((float) $i['amount']) ?></td>
                <td><?= e($i['income_date']) ?></td>
                <td>
                    <a class="btn" href="<?= e(APP_URL) ?>/modules/finance/index.php?edit_income=<?= e((string) $i['id']) ?>">Edit</a>
                    <form method="post" style="display:inline-block">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_income">
                        <input type="hidden" name="income_id" value="<?= e((string) $i['id']) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$income): ?>
            <tr><td colspan="5">No other income recorded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($editIncome): ?>
<div class="card max-w-lg">
    <h2>Edit Income</h2>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="edit_income">
        <input type="hidden" name="income_id" value="<?= e((string) $editIncome['id']) ?>">
        <label for="edit_inc_source">Source</label>
        <input type="text" id="edit_inc_source" name="source" value="<?= e($editIncome['source']) ?>" required>
        <label for="edit_inc_description">Description</label>
        <input type="text" id="edit_inc_description" name="description" value="<?= e($editIncome['description']) ?>">
        <label for="edit_inc_amount">Amount</label>
        <input type="number" id="edit_inc_amount" name="amount" min="0.01" step="0.01" value="<?= e((string) $editIncome['amount']) ?>" required>
        <label for="edit_inc_date">Date</label>
        <input type="date" id="edit_inc_date" name="income_date" value="<?= e($editIncome['income_date']) ?>" required>
        <button type="submit">Save Changes</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-2"><a href="<?= e(APP_URL) ?>/modules/finance/index.php">Cancel</a></p>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
