<?php
// Single source of truth for authenticated navigation, shared by the
// sidebar (includes/header.php) and the dashboard quick-links.
//
// Items with a 'permission' key are checked live against role_permissions
// (see userCanSeeNavItem()), so they stay in sync if the President
// reconfigures access under Permissions. Items with a 'roles' key are the
// two "constitutional" pages hardcoded to the literal president role
// (Board Terms, Permissions itself). Items with *neither* key are
// universal — visible to any logged-in user, including a brand-new
// custom role that isn't in any hardcoded list.

return [
    ['label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => '&#9737;'],
    ['label' => 'My Profile', 'href' => 'modules/members/profile.php', 'icon' => '&#128100;'],
    ['label' => 'Members', 'href' => 'modules/members/index.php', 'icon' => '&#128101;', 'permission' => 'members.manage'],
    ['label' => 'Member Documents', 'href' => 'modules/member_documents/index.php', 'icon' => '&#128193;', 'permission' => 'member_documents.manage'],
    ['label' => 'Join Requests', 'href' => 'modules/membership_requests/index.php', 'icon' => '&#128221;', 'permission' => 'membership_requests.manage'],
    ['label' => 'Password Resets', 'href' => 'modules/password_resets/index.php', 'icon' => '&#128273;', 'permission' => 'password_resets.manage'],
    ['label' => 'Savings', 'href' => 'modules/savings/index.php', 'icon' => '&#128176;', 'permission' => 'savings.access'],
    ['label' => 'Loans', 'href' => 'modules/loans/index.php', 'icon' => '&#127974;', 'permission' => 'loans.access'],
    ['label' => 'Meetings', 'href' => 'modules/meetings/index.php', 'icon' => '&#128197;', 'permission' => 'meetings.access'],
    ['label' => 'Messages', 'href' => 'modules/messages/index.php', 'icon' => '&#128172;'],
    ['label' => 'Finance', 'href' => 'modules/finance/index.php', 'icon' => '&#128181;', 'permission' => 'finance.manage'],
    ['label' => 'Reports', 'href' => 'modules/reports/index.php', 'icon' => '&#128202;', 'permission' => 'reports.view'],
    ['label' => 'Announcements', 'href' => 'modules/announcements/index.php', 'icon' => '&#128226;', 'permission' => 'announcements.publish'],
    ['label' => 'Documents', 'href' => 'modules/documents/index.php', 'icon' => '&#128196;'],
    ['label' => 'Feedback', 'href' => 'modules/feedback/index.php', 'icon' => '&#128161;', 'permission' => 'feedback.review'],
    ['label' => 'Notifications', 'href' => 'modules/notifications/index.php', 'icon' => '&#128276;'],
    ['label' => 'Board Terms', 'href' => 'modules/board_terms/index.php', 'icon' => '&#127942;', 'roles' => ['president']],
    ['label' => 'Permissions', 'href' => 'modules/permissions/index.php', 'icon' => '&#128274;', 'roles' => ['president']],
    ['label' => 'Club Settings', 'href' => 'modules/settings/index.php', 'icon' => '&#9881;', 'permission' => 'settings.manage'],
];
