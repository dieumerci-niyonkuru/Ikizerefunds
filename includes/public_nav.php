<?php
// Public-facing tab navigation shown to signed-out visitors.

return [
    ['label' => 'Home', 'href' => 'index.php'],
    ['label' => 'About', 'href' => 'about.php', 'children' => [
        ['label' => 'About Us', 'href' => 'about.php'],
        ['label' => 'Membership', 'href' => 'membership.php'],
        ['label' => 'Leadership', 'href' => 'leadership.php'],
    ]],
    ['label' => 'Community', 'href' => 'announcements.php', 'children' => [
        ['label' => 'Announcements', 'href' => 'announcements.php'],
        ['label' => 'Share an Idea', 'href' => 'feedback.php'],
    ]],
    ['label' => 'Contact', 'href' => 'contact.php'],
];
