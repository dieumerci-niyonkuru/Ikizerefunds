<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Privacy Policy';
$pageDescription = 'How IKIZERE FUNDS Club collects, uses, stores and protects the personal '
    . 'and financial information of its members and website visitors.';

require __DIR__ . '/includes/header.php';
$club = clubInfo($settings ?? []);
$updated = 'August 2026';
?>
<section class="page-head">
    <span class="section-eyebrow"><?= icon('lock', 'w-3.5 h-3.5') ?> Legal</span>
    <h1 class="section-title text-2xl sm:text-3xl">Privacy Policy</h1>
    <p class="section-sub">Last updated <?= e($updated) ?>. This policy explains what we collect,
    why we collect it, and what you can ask us to do with it.</p>
</section>

<div class="card prose-club">
    <h2>1. Who we are</h2>
    <p><?= e($siteName) ?> is a member-owned savings and credit club based at Tumba College,
    Rulindo District, Northern Province, Rwanda. We are the controller of the personal data
    described in this policy.</p>

    <h2>2. What we collect</h2>
    <p>We collect only what the club needs in order to operate:</p>
    <ul>
        <li><strong>Identity details</strong> &mdash; full name, national ID number, date of birth,
        gender, photograph, and next-of-kin details.</li>
        <li><strong>Contact details</strong> &mdash; phone number, email address, and postal or
        physical address.</li>
        <li><strong>Membership records</strong> &mdash; member number, join date, occupation and
        membership status.</li>
        <li><strong>Financial records</strong> &mdash; savings deposits and withdrawals, loan
        applications, repayment schedules, fines and expenses.</li>
        <li><strong>Documents you upload</strong> &mdash; ID scans, application forms and any other
        file you or the committee attach to your record.</li>
        <li><strong>Account and security data</strong> &mdash; your username, a hashed password, and
        login attempt records used to block brute-force attacks.</li>
    </ul>

    <h2>3. Why we collect it</h2>
    <p>We use your information to register and identify you as a member, to record and report on
    your savings and loans, to run meetings and attendance, to notify you about club activity, and
    to meet the record-keeping obligations that apply to a savings and credit group.</p>

    <h2>4. Who can see your data</h2>
    <p>Access is controlled by role. Members can see their own records. Elected leadership &mdash;
    the president, vice president, secretary, accountant and auditor &mdash; can see the records
    their role requires, and no more. We do not sell your data, and we do not share it with
    advertisers.</p>
    <p>We disclose information outside the club only where the law requires it, or where you have
    asked us to in writing.</p>

    <h2>5. How we protect it</h2>
    <p>Passwords are stored as one-way hashes and never in readable form. Forms are protected
    against cross-site request forgery, database access uses prepared statements, and repeated
    failed logins are rate-limited. Sensitive actions are written to an audit log.</p>
    <p>No system is perfect. If we ever discover a breach affecting your data, we will tell the
    affected members.</p>

    <h2>6. How long we keep it</h2>
    <p>Membership and financial records are kept for as long as you are a member, and afterwards
    for the period the club's own rules and Rwandan record-keeping practice require. Uploaded
    documents can be removed on request once they are no longer needed.</p>

    <h2>7. Your rights</h2>
    <p>You may ask us to show you the data we hold about you, correct anything that is wrong,
    delete anything we no longer need, or stop sending you notifications. Members can update much
    of this themselves from their profile page.</p>

    <h2>8. Cookies</h2>
    <p>This site sets a single session cookie so that the system can remember you are signed in
    while you move between pages. It is removed when you log out or close your browser. We do not
    use advertising or third-party tracking cookies.</p>

    <h2>9. Contact</h2>
    <p>To exercise any of the rights above, or to ask a question about this policy, contact the
    club committee:</p>
    <ul>
        <?php if ($club['email']): ?><li>Email: <a href="mailto:<?= e($club['email']) ?>"><?= e($club['email']) ?></a></li><?php endif; ?>
        <?php if ($club['phone']): ?><li>Phone: <a href="tel:<?= e(str_replace(' ', '', $club['phone'])) ?>"><?= e($club['phone']) ?></a></li><?php endif; ?>
        <li>Address: <?= e($club['address']) ?></li>
    </ul>
</div>

<div class="card text-center">
    <p class="text-sm text-gray-500 mb-3">See also our terms of use.</p>
    <a href="<?= e(APP_URL) ?>/terms.php" class="link-more justify-center">
        Terms &amp; Conditions <?= icon('arrow-right', 'w-4 h-4') ?>
    </a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
