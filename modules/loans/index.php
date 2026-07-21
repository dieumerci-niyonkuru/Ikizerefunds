<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

requirePermission('loans.access');
$user = currentUser();

// Finer-grained than the page-level 'loans.access' gate above: these
// control which actions/sections within the page are actually usable.
$canApply = userHasPermission($user, 'loans.apply');
$canApprove = userHasPermission($user, 'loans.approve');
$canRecordPayment = userHasPermission($user, 'loans.record_payment');
$isStaff = $canApprove || $canRecordPayment;

$stmt = db()->prepare('SELECT id FROM members WHERE user_id = ?');
$stmt->execute([$user['id']]);
$myMemberId = $stmt->fetchColumn();

// ------------------------------------------------------------
// Member: apply for a loan (optionally nominating guarantors)
// ------------------------------------------------------------
if ($canApply && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_loan') {
    verifyCsrf();

    $loanTypeId = (int) ($_POST['loan_type_id'] ?? 0);
    $amount     = (float) ($_POST['amount'] ?? 0);
    $termMonths = (int) ($_POST['term_months'] ?? 0);

    $stmt = db()->prepare('SELECT * FROM loan_types WHERE id = ?');
    $stmt->execute([$loanTypeId]);
    $loanType = $stmt->fetch();

    // Guarantor slots: skip any slot left blank; a member can't guarantee their own loan.
    $guarantors = [];
    foreach ([1, 2] as $slot) {
        $guarantorMemberId = (int) ($_POST["guarantor_{$slot}_member_id"] ?? 0);
        $guarantorAmount = (float) ($_POST["guarantor_{$slot}_amount"] ?? 0);
        if ($guarantorMemberId > 0 && $guarantorAmount > 0) {
            if ($guarantorMemberId === (int) $myMemberId) {
                setFlash('error', 'You cannot nominate yourself as a guarantor.');
                redirect('modules/loans/index.php');
            }
            $guarantors[] = ['member_id' => $guarantorMemberId, 'amount' => $guarantorAmount];
        }
    }

    if (!$loanType || $amount <= 0 || $termMonths <= 0) {
        setFlash('error', 'Please choose a loan type, amount, and term.');
    } elseif ($loanType['max_amount'] && $amount > $loanType['max_amount']) {
        setFlash('error', 'Amount exceeds the maximum allowed for this loan type (' . formatMoney($loanType['max_amount']) . ').');
    } elseif ($loanType['max_term_months'] && $termMonths > $loanType['max_term_months']) {
        setFlash('error', 'Term exceeds the maximum allowed for this loan type (' . $loanType['max_term_months'] . ' months).');
    } else {
        $interestAmount = round($amount * $loanType['interest_rate'] / 100, 2);
        $totalPayable = $amount + $interestAmount;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO loans (member_id, loan_type_id, amount, interest_rate, interest_amount, total_payable, term_months, status, applied_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending", CURDATE())'
            );
            $stmt->execute([$myMemberId, $loanTypeId, $amount, $loanType['interest_rate'], $interestAmount, $totalPayable, $termMonths]);
            $newLoanId = $pdo->lastInsertId();

            $insertGuarantor = $pdo->prepare(
                'INSERT INTO loan_guarantors (loan_id, guarantor_member_id, amount_guaranteed) VALUES (?, ?, ?)'
            );
            foreach ($guarantors as $g) {
                $insertGuarantor->execute([$newLoanId, $g['member_id'], $g['amount']]);
            }

            $pdo->commit();
            setFlash('success', 'Loan application submitted for review.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not submit application: ' . $e->getMessage());
        }
    }
    redirect('modules/loans/index.php');
}

// ------------------------------------------------------------
// Member: accept or decline a guarantor request
// ------------------------------------------------------------
if (!$isStaff && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'respond_guarantor') {
    verifyCsrf();
    $guarantorId = (int) ($_POST['guarantor_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    if (in_array($decision, ['accepted', 'declined'], true)) {
        $stmt = db()->prepare(
            "UPDATE loan_guarantors SET status = ? WHERE id = ? AND guarantor_member_id = ? AND status = 'pending'"
        );
        $stmt->execute([$decision, $guarantorId, $myMemberId]);
        setFlash('success', 'Response recorded.');
    }
    redirect('modules/loans/index.php');
}

// ------------------------------------------------------------
// Staff: approve / reject a pending loan
// ------------------------------------------------------------
if ($canApprove && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'decide_loan') {
    verifyCsrf();

    $loanId = (int) ($_POST['loan_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    $stmt = db()->prepare('SELECT * FROM loans WHERE id = ? AND status = "pending"');
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();

    if (!$loan) {
        setFlash('error', 'Loan not found or already decided.');
    } elseif ($decision === 'approve') {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $dueDate = date('Y-m-d', strtotime("+{$loan['term_months']} months"));
            $stmt = $pdo->prepare(
                'UPDATE loans SET status = "active", approved_date = CURDATE(), approved_by = ?, due_date = ? WHERE id = ?'
            );
            $stmt->execute([$user['id'], $dueDate, $loanId]);

            $installment = round($loan['total_payable'] / $loan['term_months'], 2);
            $runningTotal = 0;
            $insert = $pdo->prepare(
                'INSERT INTO repayment_schedule (loan_id, installment_number, due_date, expected_amount) VALUES (?, ?, ?, ?)'
            );
            for ($i = 1; $i <= $loan['term_months']; $i++) {
                $amountDue = $installment;
                if ($i === (int) $loan['term_months']) {
                    // Last installment absorbs any rounding remainder.
                    $amountDue = round($loan['total_payable'] - $runningTotal, 2);
                }
                $runningTotal += $amountDue;
                $insert->execute([$loanId, $i, date('Y-m-d', strtotime("+{$i} months")), $amountDue]);
            }

            $pdo->commit();

            $memberUser = $pdo->prepare(
                'SELECT users.id, users.full_name FROM users JOIN members ON members.user_id = users.id WHERE members.id = ?'
            );
            $memberUser->execute([$loan['member_id']]);
            if ($mu = $memberUser->fetch()) {
                queueNotification((int) $mu['id'], 'loan_approval', [
                    'name' => $mu['full_name'],
                    'amount' => formatMoney((float) $loan['amount']),
                    'total_payable' => formatMoney((float) $loan['total_payable']),
                    'due_date' => $dueDate,
                ]);
            }

            setFlash('success', 'Loan approved and repayment schedule generated.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not approve loan: ' . $e->getMessage());
        }
    } elseif ($decision === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? '') ?: null;
        $stmt = db()->prepare('UPDATE loans SET status = "rejected", rejection_reason = ? WHERE id = ?');
        $stmt->execute([$reason, $loanId]);
        setFlash('success', 'Loan rejected.');
    }
    redirect('modules/loans/index.php');
}

// ------------------------------------------------------------
// Staff: delete a loan (only if it never became a real financial
// obligation - pending or rejected; active/completed loans have real
// payment history and can't be removed here)
// ------------------------------------------------------------
if ($canApprove && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_loan') {
    verifyCsrf();
    $loanId = (int) ($_POST['loan_id'] ?? 0);

    $stmt = db()->prepare("SELECT id FROM loans WHERE id = ? AND status IN ('pending', 'rejected')");
    $stmt->execute([$loanId]);

    if (!$stmt->fetch()) {
        setFlash('error', 'Only pending or rejected loans can be deleted.');
    } else {
        db()->prepare('DELETE FROM loans WHERE id = ?')->execute([$loanId]);
        setFlash('success', 'Loan deleted.');
    }
    redirect('modules/loans/index.php');
}

// ------------------------------------------------------------
// Staff: record a repayment
// ------------------------------------------------------------
if ($canRecordPayment && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_payment') {
    verifyCsrf();

    $loanId = (int) ($_POST['loan_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $penalty = (float) ($_POST['penalty_amount'] ?? 0);
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $method = $_POST['payment_method'] ?? 'cash';

    $stmt = db()->prepare('SELECT * FROM loans WHERE id = ? AND status = "active"');
    $stmt->execute([$loanId]);
    $loan = $stmt->fetch();

    if (!$loan || $amount <= 0) {
        setFlash('error', 'Invalid loan or amount.');
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $scheduleStmt = $pdo->prepare(
                "SELECT * FROM repayment_schedule WHERE loan_id = ? AND status IN ('pending','late','partially_paid')
                 ORDER BY due_date ASC LIMIT 1"
            );
            $scheduleStmt->execute([$loanId]);
            $schedule = $scheduleStmt->fetch();

            $insert = $pdo->prepare(
                'INSERT INTO loan_payments (loan_id, repayment_schedule_id, amount, penalty_amount, payment_date, payment_method, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([$loanId, $schedule['id'] ?? null, $amount, $penalty, $paymentDate, $method, $user['id']]);

            if ($schedule) {
                $newStatus = $amount >= $schedule['expected_amount'] ? 'paid' : 'partially_paid';
                $pdo->prepare('UPDATE repayment_schedule SET status = ? WHERE id = ?')
                    ->execute([$newStatus, $schedule['id']]);
            }

            $totalPaidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM loan_payments WHERE loan_id = ?');
            $totalPaidStmt->execute([$loanId]);
            $totalPaid = (float) $totalPaidStmt->fetchColumn();

            if ($totalPaid >= $loan['total_payable']) {
                $pdo->prepare('UPDATE loans SET status = "completed" WHERE id = ?')->execute([$loanId]);
            }

            $pdo->commit();
            setFlash('success', 'Payment recorded.');
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not record payment: ' . $e->getMessage());
        }
    }
    redirect('modules/loans/index.php');
}

// ------------------------------------------------------------
// Data for display
// ------------------------------------------------------------
$loanTypes = db()->query('SELECT * FROM loan_types ORDER BY name')->fetchAll();

const GUARANTOR_COUNT_SQL = "(SELECT COUNT(*) FROM loan_guarantors WHERE loan_guarantors.loan_id = loans.id) AS guarantor_count,
        (SELECT COUNT(*) FROM loan_guarantors WHERE loan_guarantors.loan_id = loans.id AND status = 'accepted') AS guarantor_accepted_count";

if ($isStaff) {
    $pendingLoans = db()->query(
        "SELECT loans.*, users.full_name, members.member_number, loan_types.name AS loan_type_name,
                " . GUARANTOR_COUNT_SQL . "
         FROM loans
         JOIN members ON members.id = loans.member_id
         JOIN users ON users.id = members.user_id
         JOIN loan_types ON loan_types.id = loans.loan_type_id
         WHERE loans.status = 'pending'
         ORDER BY loans.applied_date"
    )->fetchAll();

    $activeLoans = db()->query(
        "SELECT loans.*, users.full_name, members.member_number,
                COALESCE((SELECT SUM(amount) FROM loan_payments WHERE loan_payments.loan_id = loans.id), 0) AS paid_so_far
         FROM loans
         JOIN members ON members.id = loans.member_id
         JOIN users ON users.id = members.user_id
         WHERE loans.status = 'active'
         ORDER BY loans.due_date"
    )->fetchAll();

    $allLoans = db()->query(
        "SELECT loans.*, users.full_name, members.member_number,
                " . GUARANTOR_COUNT_SQL . "
         FROM loans
         JOIN members ON members.id = loans.member_id
         JOIN users ON users.id = members.user_id
         ORDER BY loans.applied_date DESC
         LIMIT 50"
    )->fetchAll();
} else {
    $stmt = db()->prepare(
        "SELECT loans.*, loan_types.name AS loan_type_name,
                COALESCE((SELECT SUM(amount) FROM loan_payments WHERE loan_payments.loan_id = loans.id), 0) AS paid_so_far,
                " . GUARANTOR_COUNT_SQL . "
         FROM loans
         JOIN loan_types ON loan_types.id = loans.loan_type_id
         WHERE member_id = ?
         ORDER BY applied_date DESC"
    );
    $stmt->execute([$myMemberId]);
    $myLoans = $stmt->fetchAll();

    $otherMembers = db()->prepare(
        "SELECT members.id, members.member_number, users.full_name
         FROM members JOIN users ON users.id = members.user_id
         WHERE members.id != ?
         ORDER BY users.full_name"
    );
    $otherMembers->execute([$myMemberId]);
    $otherMembers = $otherMembers->fetchAll();

    $guarantorRequests = db()->prepare(
        "SELECT loan_guarantors.id, loan_guarantors.amount_guaranteed, loan_guarantors.status,
                loans.id AS loan_id, loans.amount AS loan_amount, loans.status AS loan_status,
                loan_types.name AS loan_type_name, users.full_name AS applicant_name, members.member_number AS applicant_number
         FROM loan_guarantors
         JOIN loans ON loans.id = loan_guarantors.loan_id
         JOIN loan_types ON loan_types.id = loans.loan_type_id
         JOIN members ON members.id = loans.member_id
         JOIN users ON users.id = members.user_id
         WHERE loan_guarantors.guarantor_member_id = ?
         ORDER BY (loan_guarantors.status = 'pending') DESC, loans.applied_date DESC"
    );
    $guarantorRequests->execute([$myMemberId]);
    $guarantorRequests = $guarantorRequests->fetchAll();
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Loans</h1>
</div>

<?php if (!$isStaff): ?>

    <?php if ($canApply): ?>
    <div class="card max-w-lg">
        <h2>Apply for a Loan</h2>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="apply_loan">

            <label for="loan_type_id">Loan Type</label>
            <select id="loan_type_id" name="loan_type_id" required>
                <?php foreach ($loanTypes as $lt): ?>
                    <option value="<?= e((string) $lt['id']) ?>">
                        <?= e($lt['name']) ?> (<?= e($lt['interest_rate']) ?>% interest)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="amount">Amount</label>
            <input type="number" id="amount" name="amount" min="1" step="0.01" required>

            <label for="term_months">Term (months)</label>
            <input type="number" id="term_months" name="term_months" min="1" required>

            <h3 class="mb-1" style="margin-top:1rem;">Guarantors (optional)</h3>
            <p class="text-gray-500 text-sm" style="margin-top:-0.5rem;">
                A <strong>guarantor</strong> is a fellow member who vouches for you by promising to
                cover part of your loan if you're unable to repay it. Having one or two guarantors
                doesn't guarantee approval, but it gives leadership more confidence when they review
                your application &mdash; especially for larger amounts. Here's how it works:
            </p>
            <p class="text-gray-500 text-sm" style="margin-top:-0.5rem;">
                1) Pick a member below and the amount you'd like them to guarantee (this can be less
                than the full loan). 2) They'll see your request under their own "Guarantor Requests"
                and can Accept or Decline &mdash; it doesn't count until they accept. 3) Leadership sees
                how many of your guarantors have accepted before deciding on your application. You can't
                nominate yourself, and leaving both fields blank is completely fine if you don't have one.
            </p>

            <label for="guarantor_1_member_id">Guarantor 1</label>
            <select id="guarantor_1_member_id" name="guarantor_1_member_id">
                <option value="">-- None --</option>
                <?php foreach ($otherMembers as $m): ?>
                    <option value="<?= e((string) $m['id']) ?>"><?= e($m['member_number'] . ' - ' . $m['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="guarantor_1_amount">Amount Guaranteed</label>
            <input type="number" id="guarantor_1_amount" name="guarantor_1_amount" min="0" step="0.01">

            <label for="guarantor_2_member_id">Guarantor 2</label>
            <select id="guarantor_2_member_id" name="guarantor_2_member_id">
                <option value="">-- None --</option>
                <?php foreach ($otherMembers as $m): ?>
                    <option value="<?= e((string) $m['id']) ?>"><?= e($m['member_number'] . ' - ' . $m['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="guarantor_2_amount">Amount Guaranteed</label>
            <input type="number" id="guarantor_2_amount" name="guarantor_2_amount" min="0" step="0.01">

            <button type="submit">Submit Application</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>My Loans</h2>
        <table>
            <thead><tr><th>Type</th><th>Amount</th><th>Total Payable</th><th>Paid</th><th>Status</th><th>Due Date</th><th>Guarantors</th></tr></thead>
            <tbody>
            <?php foreach ($myLoans as $l): ?>
                <tr>
                    <td><?= e($l['loan_type_name']) ?></td>
                    <td><?= formatMoney((float) $l['amount']) ?></td>
                    <td><?= formatMoney((float) $l['total_payable']) ?></td>
                    <td><?= formatMoney((float) $l['paid_so_far']) ?></td>
                    <td><?= statusBadge($l['status']) ?></td>
                    <td><?= e($l['due_date']) ?></td>
                    <td><?= $l['guarantor_count'] > 0 ? e($l['guarantor_accepted_count'] . '/' . $l['guarantor_count'] . ' accepted') : '&mdash;' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$myLoans): ?>
                <tr><td colspan="7">You have no loans yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Guarantor Requests</h2>
        <p class="text-gray-500 text-sm">
            Fellow members have nominated you here as someone who can vouch for their loan.
            <strong>Accepting</strong> means you're promising to help cover the amount requested if they
            can't repay it &mdash; only accept if you're genuinely willing to do that. <strong>Declining</strong>
            simply removes you from their application with no consequence to you.
        </p>
        <table>
            <thead><tr><th>Applicant</th><th>Loan Type</th><th>Loan Amount</th><th>Amount Requested</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($guarantorRequests as $g): ?>
                <tr>
                    <td><?= e($g['applicant_number'] . ' - ' . $g['applicant_name']) ?></td>
                    <td><?= e($g['loan_type_name']) ?></td>
                    <td><?= formatMoney((float) $g['loan_amount']) ?></td>
                    <td><?= formatMoney((float) $g['amount_guaranteed']) ?></td>
                    <td><?= statusBadge($g['status']) ?></td>
                    <td>
                        <?php if ($g['status'] === 'pending'): ?>
                            <form method="post" style="display:inline-block">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="respond_guarantor">
                                <input type="hidden" name="guarantor_id" value="<?= e((string) $g['id']) ?>">
                                <input type="hidden" name="decision" value="accepted">
                                <button type="submit">Accept</button>
                            </form>
                            <form method="post" style="display:inline-block">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="respond_guarantor">
                                <input type="hidden" name="guarantor_id" value="<?= e((string) $g['id']) ?>">
                                <input type="hidden" name="decision" value="declined">
                                <button type="submit">Decline</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$guarantorRequests): ?>
                <tr><td colspan="6">No guarantor requests.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>

    <?php if ($canApprove): ?>
    <div class="card">
        <h2>Pending Applications</h2>
        <p class="text-gray-500 text-sm">
            The <strong>Guarantors</strong> column shows how many of the applicant's nominated
            guarantors have accepted so far (e.g. "1/2 accepted"). It's informational, not a hard
            requirement &mdash; use your judgment alongside it when approving or rejecting.
        </p>
        <table>
            <thead><tr><th>Member</th><th>Type</th><th>Amount</th><th>Total Payable</th><th>Term</th><th>Applied</th><th>Guarantors</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($pendingLoans as $l): ?>
                <tr>
                    <td><?= e($l['member_number'] . ' - ' . $l['full_name']) ?></td>
                    <td><?= e($l['loan_type_name']) ?></td>
                    <td><?= formatMoney((float) $l['amount']) ?></td>
                    <td><?= formatMoney((float) $l['total_payable']) ?></td>
                    <td><?= e((string) $l['term_months']) ?> mo</td>
                    <td><?= e($l['applied_date']) ?></td>
                    <td><?= $l['guarantor_count'] > 0 ? e($l['guarantor_accepted_count'] . '/' . $l['guarantor_count'] . ' accepted') : '&mdash;' ?></td>
                    <td>
                        <form method="post" style="display:inline-block">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="decide_loan">
                            <input type="hidden" name="loan_id" value="<?= e((string) $l['id']) ?>">
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit">Approve</button>
                        </form>
                        <form method="post" style="display:inline-block">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="decide_loan">
                            <input type="hidden" name="loan_id" value="<?= e((string) $l['id']) ?>">
                            <input type="hidden" name="decision" value="reject">
                            <button type="submit">Reject</button>
                        </form>
                        <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure? This cannot be undone.')">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_loan">
                            <input type="hidden" name="loan_id" value="<?= e((string) $l['id']) ?>">
                            <button type="submit" style="background:#dc2626;color:#fff;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pendingLoans): ?>
                <tr><td colspan="8">No pending applications.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($canRecordPayment): ?>
    <div class="card max-w-lg">
        <h2>Record a Repayment</h2>
        <form method="post" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="record_payment">

            <label for="loan_id">Active Loan</label>
            <select id="loan_id" name="loan_id" required>
                <option value="">-- Select loan --</option>
                <?php foreach ($activeLoans as $l): ?>
                    <option value="<?= e((string) $l['id']) ?>">
                        <?= e($l['member_number'] . ' - ' . $l['full_name']) ?> (balance: <?= formatMoney($l['total_payable'] - $l['paid_so_far']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="amount">Amount Paid</label>
            <input type="number" id="amount" name="amount" min="0.01" step="0.01" required>

            <label for="penalty_amount">Penalty (if late)</label>
            <input type="number" id="penalty_amount" name="penalty_amount" min="0" step="0.01" value="0">

            <label for="payment_date">Payment Date</label>
            <input type="date" id="payment_date" name="payment_date" value="<?= e(date('Y-m-d')) ?>" required>

            <label for="payment_method">Method</label>
            <select id="payment_method" name="payment_method">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
                <option value="mobile_money">Mobile Money</option>
            </select>

            <button type="submit">Record Payment</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>All Loans</h2>
        <table>
            <thead><tr><th>Member</th><th>Amount</th><th>Total Payable</th><th>Status</th><th>Applied</th><th>Due</th><th>Guarantors</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($allLoans as $l): ?>
                <tr>
                    <td><?= e($l['member_number'] . ' - ' . $l['full_name']) ?></td>
                    <td><?= formatMoney((float) $l['amount']) ?></td>
                    <td><?= formatMoney((float) $l['total_payable']) ?></td>
                    <td><?= statusBadge($l['status']) ?></td>
                    <td><?= e($l['applied_date']) ?></td>
                    <td><?= e($l['due_date']) ?></td>
                    <td><?= $l['guarantor_count'] > 0 ? e($l['guarantor_accepted_count'] . '/' . $l['guarantor_count'] . ' accepted') : '&mdash;' ?></td>
                    <td>
                        <?php if ($l['status'] === 'rejected'): ?>
                            <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure? This cannot be undone.')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_loan">
                                <input type="hidden" name="loan_id" value="<?= e((string) $l['id']) ?>">
                                <button type="submit" style="background:#dc2626;color:#fff;">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$allLoans): ?>
                <tr><td colspan="8">No loans yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
