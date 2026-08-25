<?php
// Single source of truth for the club's public contact details.
//
// Values set by leadership in Settings (club_settings) always win. When those
// have not been filled in yet — a fresh install — we fall back to the
// president's already-published contact from includes/leadership.php rather
// than showing the visitor an empty header.

function clubInfo(array $settings = []): array
{
    static $leadership = null;
    if ($leadership === null) {
        $leadership = require __DIR__ . '/leadership.php';
    }

    $president = null;
    foreach ($leadership as $leader) {
        if (strcasecmp($leader['title'], 'President') === 0) {
            $president = $leader;
            break;
        }
    }

    $email = $settings['club_email'] ?? '';
    $phone = $settings['club_phone'] ?? '';

    if ($email === '' && !empty($president['email'])) {
        $email = $president['email'];
    }
    if ($phone === '' && !empty($president['phone'])) {
        $phone = $president['phone'];
    }

    return [
        'email'    => $email,
        'phone'    => $phone,
        'location' => 'Tumba College, Rulindo',
        'address'  => 'Tumba College, Rulindo District, Northern Province, Rwanda',
        'mapQuery' => 'IPRC Tumba, Rulindo District, Rwanda',
    ];
}
