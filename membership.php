<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$savingTypes = db()->query('SELECT name, minimum_amount, is_withdrawable, description FROM saving_types ORDER BY name')->fetchAll();
$loanTypes = db()->query('SELECT name, interest_rate, max_amount, max_term_months, description FROM loan_types ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_join') {
    verifyCsrf();
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $message = trim($_POST['message'] ?? '') ?: null;

    if ($fullName === '') {
        setFlash('error', 'Please provide your full name.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO membership_requests (full_name, email, phone, message) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$fullName, $email, $phone, $message]);
        setFlash('success', 'Thank you! Your request to join has been sent to our leadership. They will reach out to you soon.');
    }
    redirect('membership.php');
}

require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Membership</h1>
    <p>Joining <?= e($siteName) ?> at <strong>Tumba College, Rulindo District</strong> gives you access to structured savings, affordable
    loans, and a transparent, digitally-managed club. Here's how it works.</p>
</div>

<div class="card">
    <h2>How to Join</h2>
    <div class="dashboard-grid">
        <div class="card">
            <h3 class="mb-1">1. Contact Us</h3>
            <p class="text-gray-500 text-sm">Reach out via the <a href="<?= e(APP_URL) ?>/contact.php">Contact page</a> or speak to any club leader listed under <a href="<?= e(APP_URL) ?>/leadership.php">Leadership</a>.</p>
        </div>
        <div class="card">
            <h3 class="mb-1">2. Get Registered</h3>
            <p class="text-gray-500 text-sm">The Accountant or Secretary registers your details in the system and issues you a member number and login.</p>
        </div>
        <div class="card">
            <h3 class="mb-1">3. Start Saving</h3>
            <p class="text-gray-500 text-sm">Log in anytime to track your savings balance, apply for a loan, and stay updated on meetings.</p>
        </div>
    </div>
</div>

<?php if ($savingTypes): ?>
<div class="card">
    <h2>Savings Plans</h2>
    <table>
        <thead><tr><th>Plan</th><th>Minimum Amount</th><th>Withdrawable</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($savingTypes as $st): ?>
            <tr>
                <td><?= e($st['name']) ?></td>
                <td><?= formatMoney((float) $st['minimum_amount']) ?></td>
                <td><?= $st['is_withdrawable'] ? 'Yes' : 'No' ?></td>
                <td><?= e($st['description']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($loanTypes): ?>
<div class="card">
    <h2>Loan Products</h2>
    <table>
        <thead><tr><th>Loan Type</th><th>Interest Rate</th><th>Max Amount</th><th>Max Term</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($loanTypes as $lt): ?>
            <tr>
                <td><?= e($lt['name']) ?></td>
                <td><?= e($lt['interest_rate']) ?>%</td>
                <td><?= $lt['max_amount'] ? formatMoney((float) $lt['max_amount']) : 'No cap' ?></td>
                <td><?= $lt['max_term_months'] ? e($lt['max_term_months']) . ' months' : 'Flexible' ?></td>
                <td><?= e($lt['description']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card max-w-lg">
    <h2>Request to Join</h2>
    <p class="text-gray-500 text-sm">Fill this in and a leader will follow up to complete your registration.</p>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="request_join">

        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email">

        <label for="phone">Phone</label>
        <input type="text" id="phone" name="phone">

        <label for="message">Message (optional)</label>
        <textarea id="message" name="message" rows="3"></textarea>

        <button type="submit">Request to Join</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
