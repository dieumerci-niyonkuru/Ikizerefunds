# IKIZERE FUNDS Club Website

A web-based management system for a savings and credit club: member registration,
savings, loans, meetings, reports, notifications, and role-based administration.

Plain PHP (PDO/MySQL), no framework. Tailwind CSS via CDN for styling.

## Tech stack

| Layer      | Choice                                                         |
|------------|-----------------------------------------------------------------|
| Backend    | PHP 8+, plain procedural/functional style, PDO for all DB access |
| Database   | MySQL / MariaDB (InnoDB, utf8mb4)                                |
| Frontend   | Server-rendered PHP + Tailwind CSS (Play CDN, `cdn.tailwindcss.com`) |
| Auth       | Sessions + `password_hash`/`password_verify`, CSRF tokens on every form |
| PDF export | Browser "Print / Save as PDF" on report pages (no server library) |

Tailwind loads from a CDN, so **the browser needs internet access** to render
the styling. There is no Node/npm build step.

## Project structure

```
ikizere_funds/
  config/
    config.php        Env-based settings, session hardening
    database.php       PDO singleton via db()
  includes/
    auth.php            Login, rate limiting, requireLogin()/requireRole()
    functions.php        e(), CSRF helpers, flash messages, statusBadge()
    notifications.php    queueNotification() / dispatchPendingNotifications()
    nav.php              Single source of truth for the logged-in sidebar
    public_nav.php       Single source of truth for the public tab bar
    leadership.php       Data source for the public Leadership page (name/title/photo)
    header.php / footer.php   Shared layout (topbar + sidebar + Tailwind setup)
    page_loader.php       Branded full-screen loading overlay, included by header.php on every page
    flash_toasts.php      Renders getFlashes() as auto-dismissing top-right toasts, included by header.php
  index.php, about.php, membership.php, leadership.php, announcements.php,
  contact.php, feedback.php, forgot_password.php
                          Public site: tabbed nav, no login required.
                          membership.php also takes join requests; feedback.php
                          takes visitor ideas/suggestions; forgot_password.php
                          logs a password-reset request for leadership to act on.
  modules/
    members/    Register (incl. national ID, address, next of kin) + list members;
                 member's own profile (editable, incl. photo/document upload + next of kin)
    membership_requests/  Review public join requests; approve/reject; "Register as
                            Member" link pre-fills the Members form
    member_documents/  Staff-side upload/list/delete of any member's ID scans, application
                         forms etc. (members can also self-upload from their own profile)
    password_resets/  Fulfill pending reset requests, or reset any user's password ad hoc
    savings/     Record deposits/withdrawals, balances, history
    loans/       Apply, approve/reject, repayment schedule, payments
    meetings/    Schedule, attendance, minutes
    messages/    Member <-> leadership threads, plus a leadership-only channel; sender avatars
                  throughout; an "Unanswered Member Messages" / "My Threads Awaiting Reply"
                  stat surfaces on the main Dashboard depending on messages.manage
    notifications/  Per-user notification inbox, presented as a card list (icon per type +
                     status badge) rather than a plain table
    finance/     Issue fines, record expenses/other income (feeds the Financial Report)
    feedback/    Review visitor ideas submitted via the public feedback form
    documents/   Upload/list/delete club documents (constitution, bylaws, AGM reports)
    reports/     Membership / Loan / Financial / Monthly reports
    announcements/  Publish home-page news
    board_terms/  President-only: appoint/end leadership terms; history of who has
                   held each position and when
    settings/    President-only club settings (name, contact, logo)
  scripts/
    create_admin.php     CLI: bootstrap a login (used for all 4 leaders, not just President)
    send_reminders.php   CLI: queue + dispatch reminder notifications (for cron)
    crop_passport.php    CLI: one-off GD tool to crop a photo to a tight passport-style square
  database/
    schema.sql   Full schema + seed data (roles, loan/saving types, templates)
  assets/uploads/   Club logo, member profile pictures, and club documents (.htaccess blocks execution)
  assets/images/    Static images bundled with the app (e.g. leadership photos)
  login.php, logout.php, dashboard.php
```

## Public site vs. logged-in system

The site is now two distinct experiences sharing one layout shell
(`includes/header.php` / `footer.php`):

- **Public** (no login): a tabbed marketing site — Home, About, Leadership,
  Announcements, Contact — plus a Login button. Tabs are defined once in
  `includes/public_nav.php`. The Home page has a hero banner, feature
  highlights, a leadership teaser, and the 3 latest announcements; each
  teaser links to its full page.
- **Logged-in**: the topbar switches to a user chip + Logout, and a
  role-filtered **sidebar** (defined once in `includes/nav.php`) replaces the
  public tabs for navigating every module.

Both are responsive: under the `md` breakpoint the public tabs collapse
into a hamburger-triggered dropdown, and the sidebar collapses into a
hamburger-triggered slide-in panel.

The login page (`login.php`) uses a two-panel layout — club branding with
logo/tagline on the left (desktop), the form on the right — instead of a
bare form. Its password field has a Show/Hide toggle.

**Profile pictures are universal** — `photo_path` lives on `users` (not
`members`), so every account can set one from "My Profile", including
leaders who have no `members` row (this used to be a real gap: the upload
card only rendered `if ($member)`, so the President/VP/etc. had no way to
set a photo at all unless they also happened to be a registered member).
`avatarHtml()` in `includes/functions.php` renders the photo or a colored
initial circle as a fallback, and is used consistently in the topbar (next
to your own name), the staff Members list, and Messages (next to each
sender's name in both the thread list and individual thread view) — so a
photo set once is visible everywhere a person's identity shows up, not
just their own profile page.

**Flash messages render as toasts**, not inline banners — `setFlash()`/
`getFlashes()` are unchanged, but `includes/header.php` now reads
`getFlashes()` once and hands the result to `includes/flash_toasts.php`,
which renders them top-right, auto-dismissing after 5 seconds (staggered
if there are several), with a manual close button. Login's own inline
`$error` (a same-request re-render, not a session flash) is untouched and
still shows as the original inline banner right above the form.

Every page shows a branded full-screen loading overlay (club logo, gentle
pulse animation) on first paint and on any form submit — `header.php`
includes `includes/page_loader.php` right after `<body>`, so it's automatic
site-wide with no per-page wiring. It fades out on `window.load`, and
re-appears with a "Please wait…" message (or a form's own
`data-loading-text="..."` attribute, e.g. login's "Signing you in…") the
moment any form on the page is submitted — masking both the brief unstyled
flash while the Tailwind CDN script processes and the dead pause while the
server handles the request. A `<noscript>` rule hides it instantly if JS is
off, and a 4-second timeout guarantees it can never get stuck open.

## Requirements

- PHP 8.0+ with `pdo_mysql` and `fileinfo` extensions
- MySQL 5.7+ / MariaDB 10.3+
- A web server (Apache/Nginx via XAMPP/WAMP, or PHP's built-in server for testing)

## Setup

1. **Database**: create it from the schema, which also seeds default roles,
   permission codes, savings/loan types, and notification templates:
   ```
   mysql -u root -p < database/schema.sql
   ```

2. **Configuration**: `config/config.php` reads these environment variables,
   falling back to XAMPP-friendly defaults if unset (`DB_HOST=127.0.0.1`,
   `DB_NAME=ikizere_funds`, `DB_USER=root`, `DB_PASS=` empty):
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
   - `APP_URL` — base URL the app is served from (default `http://localhost/ikizere_funds`)
   - `APP_DEBUG` — `1` shows PHP errors; defaults to `0` (off) if unset, so
     set it to `1` explicitly for local development

   Either export these as real environment variables, or just edit the
   defaults in `config/config.php` directly for local testing.

3. **First login**: the schema does **not** create any user account (there is
   nothing to seed a password for). Create the first President account from
   the command line:
   ```
   php scripts/create_admin.php "Your Name" president1 "SomeStrongPass!" you@example.com 078xxxxxxx president
   ```
   Run it with no arguments to be prompted interactively instead. Every
   other account after this can be created normally through the Members
   module once you're logged in.

4. **Serve it**: point Apache's document root at this folder (XAMPP: copy
   into `htdocs/ikizere_funds`), or for quick local testing:
   ```
   php -S localhost:8000
   ```
   Then visit `/login.php`.

5. **Reminders (optional)**: `scripts/send_reminders.php` queues and
   dispatches savings/loan/meeting reminders. Run it once daily via cron
   (Linux) or Windows Task Scheduler:
   ```
   php scripts/send_reminders.php
   ```

## Roles & access

| Module          | President | VP | Secretary | Accountant | Auditor | Member |
|------------------|:---:|:---:|:---:|:---:|:---:|:---:|
| My Profile       | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Notifications    | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Members          | ✓ | ✓ | ✓ | ✓ |   |   |
| Join Requests    | ✓ | ✓ | ✓ | ✓ |   |   |
| Password Resets  | ✓ | ✓ | ✓ | ✓ |   |   |
| Savings          | ✓ | ✓ |   | ✓ | (own only) |
| Loans            | ✓ | ✓ |   | ✓ | (own only) |
| Meetings         | ✓ | ✓ | ✓ |   |   | (view + own attendance) |
| Messages         | ✓ leadership view | ✓ leadership view | ✓ leadership view | ✓ leadership view | ✓ leadership view | (own thread only) |
| Finance (fines/expenses/income) | ✓ | ✓ |   | ✓ |   |   |
| Feedback (visitor ideas) | ✓ | ✓ | ✓ |   |   |   |
| Documents        | ✓ upload | (view) | ✓ upload | (view) | (view) | (view) |
| Reports          | ✓ | ✓ |   | ✓ | ✓ |   |
| Announcements    | ✓ |   | ✓ |   |   |   |
| Club Settings    | ✓ |   |   |   |   |   |

The table above is the *default* configuration, seeded so a fresh install
behaves exactly as documented — but it's now editable. Every module gate is
enforced server-side via `requirePermission('code')`, which checks the
`role_permissions` table live (see "Permissions" below); the sidebar and
dashboard hide links the same way, so what a role sees always matches what
it can actually do. `modules/board_terms/` and `modules/permissions/`
themselves are the only two gates still hardcoded to the literal
`president` role name — they're the "constitutional" pages that must stay
reachable no matter how the rest of the matrix gets edited.

All 4 leadership roles have real login accounts (created via
`scripts/create_admin.php`), not just the President — each can sign in and
use exactly the modules their role grants above.

## Permissions

President-only screen (`modules/permissions/index.php`) with two parts:

- **Permission matrix** — every permission code as a row, every role as a
  column, checkboxes. Saving replaces the entire `role_permissions` table
  with whatever's checked, so the form always describes the complete
  desired state. Unchecking a box takes effect immediately (cached only
  per-request, not across page loads).
- **Create a new role** — name + description → a new row in `roles`. It
  needs no code change to become usable: it immediately appears in this
  same matrix (to assign it permissions) and in Board Terms' "Start a New
  Term" dropdown (to appoint someone to it, which — same as any other
  appointment — grants that user's account the new role's actual system
  access).

Every permission in the matrix is now live. `reports.view`,
`announcements.publish`, `settings.manage`, `members.manage`,
`member_documents.manage`, `membership_requests.manage`,
`password_resets.manage`, `savings.access`, `loans.access`,
`meetings.access`, `finance.manage`, `documents.manage`, and
`feedback.review` are the page-level gates. Within Loans specifically,
`loans.apply`, `loans.approve`, and `loans.record_payment` are also
enforced independently — a role can have any combination of the three, and
the page shows exactly the matching sections ("Apply for a Loan" +
"My Loans" + "Guarantor Requests" for `loans.apply`; "Pending Applications"
for `loans.approve`; "Record a Repayment" for `loans.record_payment`),
with each POST action re-checking its own permission server-side
regardless of what the UI shows (verified: revoking `loans.approve` from a
role hides that section and silently no-ops a crafted approve request,
while `loans.record_payment` keeps working for that same role
unaffected). `savings.record` (staff record-a-transaction view vs. a
member's own-balance view) and `meetings.manage` (staff schedule/manage
view vs. a member's own-attendance view) are enforced the same way in
their respective modules — each verified by revoking the permission from a
role, confirming the staff section disappeared and a crafted POST no-op'd,
then restoring it. `messages.manage` (leadership view of every member
thread + the leadership-only channel vs. a member's own-thread-only view)
is enforced the same way in the Messages module — this one replaced a
hardcoded 5-role array (`president`/`vice_president`/`secretary`/
`accountant`/`auditor`) rather than an already-permission-gated check, so
it fixes the same class of bug already found in the sidebar nav: a custom
role appointed via Board Terms would previously never be treated as
leadership in Messages no matter what the President intended, since the
array only ever recognized the 6 built-in role names. Verified the same
way: revoked `messages.manage` from Secretary, confirmed the leadership
view (Member Messages, New Leadership Post, Leadership Channel) was
replaced by the member-only view, a crafted `new_board_post` POST no-op'd,
and a direct GET to a `leadership_only` thread returned 403 instead of 200;
restored and confirmed all three came back. `dashboard.overview` (club-wide
stats — Total Members, Total Savings Held, Active Loans, Pending Loan
Applications — vs. a member's own-balance/own-loans view on the Dashboard
itself) replaced the same kind of hardcoded 5-role array; verified the same
way (revoked from Secretary, dashboard flipped from club stats to personal
stats, restored, flipped back). `members.edit` now gates the Edit Member
action (see "Full CRUD for the President" below) — no longer unused.
`members.register` remains seeded but unused: registering and viewing the
list are still the same single action, already covered by the page-level
`members.manage` gate, and there was no natural second action to split it
onto.

A subtlety worth knowing if you extend this: nav items with neither a
`'permission'` nor a `'roles'` key (Dashboard, My Profile, Messages,
Documents, Notifications) are *universal* — visible to any logged-in user
regardless of role, which matters for brand-new custom roles. A first pass
at this used a hardcoded 6-role array for "universal" pages instead, which
worked fine for the original roles but silently hid those pages from a
freshly-created custom role (the pages themselves were never blocked,
since they only call `requireLogin()` — just invisible in the sidebar).
Caught and fixed by testing a real custom role end-to-end rather than only
regression-testing the original 6.

## Communication & public submissions

- **Member <-> Leadership messages** (`modules/messages/`): a member starts a
  thread (visible only to them and all leadership); any leader can reply.
  Leadership sees every member thread in one list.
- **Leadership-only channel**: a separate internal board only the 5
  leadership roles can post to or view — enforced server-side in
  `modules/messages/thread.php` (a member hitting a leadership-only thread
  URL directly gets a 403, not just a hidden link).
- **Public "Share an Idea"** (`feedback.php`): no login needed. Visitors
  submit a suggestion (name/email optional); President/VP/Secretary review
  it under Feedback.
- **Public "Request to Join"** (on `membership.php`): no login needed.
  Prospective members submit name/email/phone/message; staff review under
  Join Requests, Approve/Reject, and an "Register as Member" link pre-fills
  their details into the Members registration form (no auto-account creation
  — a staff member still completes registration deliberately).
- **Forgot password** (`forgot_password.php`): no login needed, and gives the
  same generic response whether or not the username exists (no account
  enumeration). Since there's no email/SMS provider configured, it doesn't
  send a reset link — it logs a request that a leader fulfills under
  Password Resets, generating a new temporary password to hand over securely
  out of band, exactly like new-member registration already does. Leaders can
  also reset any user's password directly, without a pending request.

## Loan guarantors

Members can nominate up to 2 fellow members as guarantors when applying for
a loan (each guaranteeing a specified amount). A member can't nominate
themselves — blocked both by excluding themselves from the dropdown and by
a server-side check that rejects a crafted request too. The nominated
guarantor sees the request under "Guarantor Requests" on their own Loans
page and can Accept or Decline; once resolved, the action buttons disappear.
Staff see a live "X/Y accepted" summary on both Pending Applications and
All Loans so they can factor guarantor status into their approval decision
— it's informational rather than a hard block, since not every loan type
requires one.

The Apply form, the Guarantor Requests page, and the Pending Applications
page each carry plain-language explanations of what a guarantor is, what
accepting actually commits someone to, and what the acceptance count means
for staff reviewing an application — added after noticing the feature had
no in-context explanation for a first-time user, just labels.

## Full CRUD for the President

Every record-creating module now also supports Edit and Delete, not just
Create — Members, Savings, Loans (delete only, pending/rejected), Meetings,
Finance (fines/expenses/income), Announcements, Membership Requests, and
Feedback. As with everything else in this app, these are gated by the same
existing top-level permission each module already used (`members.manage`/
`members.edit`, `savings.record`, `loans.approve`, `meetings.manage`,
`finance.manage`, `announcements.publish`, `membership_requests.manage`,
`feedback.review`) rather than new hardcoded role checks — so the President
gets full CRUD everywhere by default (every one of those permissions is
seeded to `president`), and any other role holding the same permission
inherits the same completed CRUD set automatically, with no separate
Permissions-matrix entries needed.

A few of these carry data-integrity guards rather than being unconditional:
- **Members**: Delete is blocked if the member has an active loan (settle
  it first); the login account is deactivated (`status = 'inactive'`)
  rather than deleted outright, so their name still resolves correctly
  anywhere they appear historically (e.g. "recorded by" on an old
  transaction). Deleting a member who's still an accepted guarantor on
  someone else's loan is blocked by the database itself (`loan_guarantors`
  has no `ON DELETE CASCADE` on `guarantor_member_id`), surfaced as a
  friendly error rather than a raw SQL failure.
- **Loans**: Delete only works on `pending` or `rejected` loans — an
  `active` or `completed` loan has real repayment history behind it
  (`ON DELETE CASCADE` would silently wipe `loan_payments` and
  `repayment_schedule`), so those can only be resolved through the normal
  repayment flow, never deleted.
- Every other Delete (Savings, Meetings, Finance, Announcements,
  Membership Requests, Feedback) relies on the same `ON DELETE CASCADE`
  relationships already in the schema, with no special-casing needed.

## Board terms

President-only module tracking who has held each leadership position and
when. Starting a new term for a role (e.g. electing a new Secretary)
transactionally: closes the previous holder's open term for that role,
steps them down to Member system access (only if they haven't already been
moved to something else), creates the new term, and grants the incoming
person that role's actual system access — so an election result immediately
changes what that person can do in the system, not just a historical note.
"End Term" (stepping down with no immediate replacement) works the same way
minus the new appointment. The 4 original leader accounts were backfilled
with an open term dated to their account creation.

Moving someone from one board role directly to another (e.g. Accountant to
Vice President) also closes their *own* previous open term — a person holds
one system role at a time, so nothing is left dangling. The President also
can't demote or end their own presidency from this screen (both actions are
blocked with an explicit message) — that would lock everyone out of the
only screen that could undo it; another President has to make that change.

## What's implemented vs. modeled-only

Every table in the schema (`database/schema.sql`) now has a working screen.
`next_of_kin` (captured at registration, editable via "Add Next of Kin" on
the member's own profile), member `photo_path` (self-service upload, same
validation pattern as the club logo), `member_documents` (ID scans/application
forms — members self-upload from their own profile, staff can upload for
anyone under Member Documents), `fines`/`expenses`/`income` (Finance
module), `messages`/`feedback`/`membership_requests`, `documents`
(constitution/bylaws/AGM reports, PDF/Word upload with real MIME validation),
`password_resets` (staff-fulfilled reset requests), `loan_guarantors`
(peer-guarantee workflow, see above), `board_terms` (see above), and
`permissions`/`role_permissions` (see "Permissions" above) are all fully
wired up. The one caveat is the handful of finer-grained permission codes
noted in that section that exist for future use but aren't enforced yet.

Notification **delivery** is also a stub: `dispatchPendingNotifications()`
in `includes/notifications.php` marks queued messages as "sent" without
calling a real SMS/email provider. Plugging in one (e.g. Africa's Talking,
Twilio, or SMTP) is a one-function change.

## Security notes

- Every write endpoint checks `requireRole()`/`requireLogin()` server-side.
- Every form includes and verifies a CSRF token (`csrfField()` / `verifyCsrf()`).
- All SQL goes through PDO prepared statements — no string-built queries.
- Passwords are hashed with `password_hash()` (bcrypt) and never logged.
- Login attempts are rate-limited (5 failures / 15 minutes per username).
- Session cookies are `HttpOnly` + `SameSite=Strict`; session ID is
  regenerated on login.
- Logo uploads are validated by real file content (`mime_content_type`), size
  capped at 2 MB, saved under a random filename, and the upload folder has
  an `.htaccess` that blocks PHP execution as defense in depth.

## Suggested manual test flow

0. Before logging in, click through the public tabs (Home, About, Leadership, Announcements, Contact) and shrink the browser to confirm the mobile hamburger menu works.
1. Run `create_admin.php`, log in as President.
2. Club Settings — set club name, contact info, and upload a logo; confirm it shows in the topbar, login panel, and Contact page.
3. Members — register 2–3 members (note the generated temporary passwords).
4. Log in as a member (separate browser/incognito) — confirm they only see their own data.
5. As Accountant/President — record a savings deposit and a withdrawal for a member; confirm the balance matches on both the staff and member views.
6. As a member — apply for a loan. As President — approve it; confirm a repayment schedule was generated and a notification was queued (check Notifications as that member).
7. Record a loan payment against the active loan; confirm the schedule row and loan status update, and that it auto-completes once fully paid.
8. Secretary — schedule a meeting, then use "Manage" to set attendance and minutes.
9. Reports — open all four report pages, use "Print / Save as PDF" and confirm the numbers reconcile with what you entered in steps 5–7.
10. Run `php scripts/send_reminders.php` from the CLI and confirm reminder rows appear for a loan installment due soon / a meeting in the next 24h.
11. As a member — send a message to leadership; log in as any leader and reply; confirm the member sees the reply. Try opening a leadership-only thread URL directly as a member and confirm you get a 403.
12. Public — submit the "Share an Idea" form and the "Request to Join" form on Membership (no login). Log in as President and confirm both appear under Feedback / Join Requests; approve a join request and confirm "Register as Member" pre-fills the Members form.
13. As a member — edit your profile (contact info + national ID/address/gender/DOB/occupation), upload a profile picture, and add a next-of-kin entry; confirm all show correctly and the photo also appears in the staff Members list.
14. Finance — as Accountant, issue a fine, record an expense, and record other income; mark the fine paid or waived; confirm the Financial Report totals update accordingly (a waived fine should not count toward "Fines Collected").
15. Documents — as President/Secretary, upload a PDF (try a non-PDF file too and confirm it's rejected); confirm a member can view/download it but has no upload/delete controls.
16. Password reset — submit Forgot Password with a real username (and a fake one, to confirm the response looks identical either way); as a leader, fulfill the request under Password Resets and confirm the old password stops working and the new one logs in.
17. Loan guarantors — as a member, apply for a loan and nominate another member as guarantor; log in as that guarantor and Accept (try Decline on a separate application too); confirm staff see the correct "X/Y accepted" count before approving.
18. Board terms — under Board Terms, appoint someone new to a role that already has a holder and confirm the old holder is stepped down to Member; try (and expect to be blocked from) ending your own presidency.
19. Permissions — uncheck a permission for a role under Permissions and confirm that role immediately loses the corresponding sidebar link and page access; recheck it and confirm access returns. Then create a new custom role, grant it 1-2 permissions, appoint a test user to it via Board Terms, and confirm their sidebar shows exactly (and only) the universal pages plus what you granted.
20. Loans/Savings/Meetings fine-grained permissions — revoke `loans.approve` (or `savings.record` / `meetings.manage`) from one role and confirm the staff-only section disappears from that page while any *other* granted permission on the same page keeps working; confirm a crafted direct POST to the revoked action is silently ignored, not just hidden. Restore and confirm the section comes back.
21. Messages fine-grained permission — revoke `messages.manage` from one leadership role (e.g. Secretary) and confirm: the leadership view (Member Messages / New Leadership Post / Leadership Channel) is replaced by the plain member view; a crafted `new_board_post` POST no-ops; and browsing directly to a `leadership_only` thread URL returns 403 instead of 200. Restore and confirm all three come back. Then appoint a brand-new custom role to a leadership-style position via Board Terms and confirm it does *not* automatically get leadership Messages access unless you grant `messages.manage` to it explicitly under Permissions — this is the fix for the old hardcoded-role-array bug.
22. Dashboard fine-grained permission — revoke `dashboard.overview` from one leadership role and confirm their Dashboard flips from club-wide stats to the personal stats view (and the President's own dashboard is unaffected). Restore and confirm it flips back.

## Known limitations

- Tailwind CSS is loaded from a CDN — no offline styling without switching to a compiled build.
- No automated test suite; verification is manual (see above).
- SMS/email sending is stubbed, as noted above.
- PDF export relies on the browser's print dialog rather than a server-generated file.
