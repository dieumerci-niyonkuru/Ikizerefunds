<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Terms & Conditions';
$pageDescription = 'The terms that govern membership of IKIZERE FUNDS Club and use of this '
    . 'website, including savings, loans, member conduct and account security.';

require __DIR__ . '/includes/header.php';
$club = clubInfo($settings ?? []);
$updated = 'August 2026';
?>
<section class="page-head">
    <span class="section-eyebrow"><?= icon('scale', 'w-3.5 h-3.5') ?> Legal</span>
    <h1 class="section-title text-2xl sm:text-3xl">Terms &amp; Conditions</h1>
    <p class="section-sub">Last updated <?= e($updated) ?>. These terms cover both club membership
    and your use of this website.</p>
</section>

<div class="card prose-club">
    <h2>1. Acceptance</h2>
    <p>By joining <?= e($siteName) ?> or using this website, you agree to these terms and to the
    club's constitution as adopted by the membership. If you do not agree, please do not use the
    site or apply for membership.</p>

    <h2>2. Membership</h2>
    <ul>
        <li>Membership is open to eligible applicants and takes effect only once the committee has
        approved your application.</li>
        <li>You must give accurate identity and contact information, and keep it up to date.</li>
        <li>Members are expected to attend meetings, meet their contribution schedule, and observe
        decisions taken by the membership.</li>
        <li>Membership may be suspended or withdrawn for persistent default, dishonesty, or conduct
        that damages the club.</li>
    </ul>

    <h2>3. Savings</h2>
    <p>Contributions are recorded against your member number on the date they are received.
    Balances shown on the site reflect the transactions entered by the accountant. If you believe a
    figure is wrong, raise it with the accountant or through the messages page &mdash; corrections
    are made by adjusting entry, so the history stays intact.</p>
    <p>Withdrawals follow the notice period and conditions set by the club's rules.</p>

    <h2>4. Loans</h2>
    <ul>
        <li>Applying for a loan does not guarantee approval. Each application is reviewed by the
        committee against your standing and the funds available.</li>
        <li>Approved loans carry the interest rate, term and repayment schedule shown at approval.</li>
        <li>Late or missed repayments may attract fines under the club's rules and may affect
        future applications.</li>
        <li>Guarantors, where required, accept responsibility as set out at the time of approval.</li>
    </ul>

    <h2>5. Your account</h2>
    <p>You are responsible for keeping your password confidential and for activity carried out
    under your login. Choose a strong password, do not share it, and tell the committee at once if
    you think your account has been used by someone else. We may suspend an account we believe is
    compromised.</p>

    <h2>6. Acceptable use</h2>
    <p>Do not attempt to access records that are not yours, probe or disrupt the system, upload
    malicious files, or use the messages feature to harass other members. Uploaded documents must
    be genuine and relevant to your membership.</p>

    <h2>7. Availability</h2>
    <p>We aim to keep the site available, but it is provided on an "as is" basis. Maintenance,
    connectivity or hosting problems may interrupt access. The club's official records remain the
    records held by the committee.</p>

    <h2>8. Limitation</h2>
    <p>To the extent permitted by Rwandan law, the club is not liable for indirect or consequential
    loss arising from use of this website. Nothing here limits the club's obligations to its
    members under its own constitution.</p>

    <h2>9. Changes</h2>
    <p>These terms may be updated as the club's rules evolve. Material changes will be announced on
    the announcements page. Continuing to use the site after a change means you accept it.</p>

    <h2>10. Governing law</h2>
    <p>These terms are governed by the laws of the Republic of Rwanda, and disputes fall to the
    competent courts of Rwanda.</p>

    <h2>11. Contact</h2>
    <ul>
        <?php if ($club['email']): ?><li>Email: <a href="mailto:<?= e($club['email']) ?>"><?= e($club['email']) ?></a></li><?php endif; ?>
        <?php if ($club['phone']): ?><li>Phone: <a href="tel:<?= e(str_replace(' ', '', $club['phone'])) ?>"><?= e($club['phone']) ?></a></li><?php endif; ?>
        <li>Address: <?= e($club['address']) ?></li>
    </ul>
</div>

<div class="card text-center">
    <p class="text-sm text-gray-500 mb-3">See also how we handle your data.</p>
    <a href="<?= e(APP_URL) ?>/privacy.php" class="link-more justify-center">
        Privacy Policy <?= icon('arrow-right', 'w-4 h-4') ?>
    </a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
