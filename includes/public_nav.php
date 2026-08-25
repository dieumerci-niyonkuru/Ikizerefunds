<?php
// Public-facing tab navigation shown to signed-out visitors.
//
// 'icon' names map to includes/icons.php. 'desc' is shown as a subtitle in the
// desktop dropdown menus, so each tab explains itself before you click it.

return [
    ['label' => 'Home', 'href' => 'index.php', 'icon' => 'home'],
    ['label' => 'About', 'href' => 'about.php', 'icon' => 'info', 'children' => [
        ['label' => 'About Us', 'href' => 'about.php', 'icon' => 'info',
         'desc' => 'Our story, mission and how the club works'],
        ['label' => 'Membership', 'href' => 'membership.php', 'icon' => 'user-plus',
         'desc' => 'Requirements, benefits and how to join'],
        ['label' => 'Leadership', 'href' => 'leadership.php', 'icon' => 'leadership',
         'desc' => 'Meet the committee running the club'],
    ]],
    ['label' => 'Community', 'href' => 'announcements.php', 'icon' => 'megaphone', 'children' => [
        ['label' => 'Announcements', 'href' => 'announcements.php', 'icon' => 'megaphone',
         'desc' => 'Latest news and notices from leadership'],
        ['label' => 'Share an Idea', 'href' => 'feedback.php', 'icon' => 'bulb',
         'desc' => 'Send a suggestion to the committee'],
    ]],
    ['label' => 'Contact', 'href' => 'contact.php', 'icon' => 'mail'],
];
