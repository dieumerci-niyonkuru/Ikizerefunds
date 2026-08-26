# IKIZERE FUNDS Club

A web-based management system for a savings and credit club at **Tumba College,
Rulindo District, Northern Province, Rwanda** — member registration, savings,
loans, meetings, reports, notifications and role-based administration, plus a
public marketing site.

Plain PHP 8 (PDO/MySQL), no framework, no build step.

---

## Contents

- [Tech stack](#tech-stack)
- [Quick start (local)](#quick-start-local)
- [Deploying to Railway](#deploying-to-railway)
- [Redeploying on Railway](#redeploying-on-railway)
- [Environment variables](#environment-variables)
- [**Login credentials**](#login-credentials) — usernames, passwords, roles
- [Email notifications](#email-notifications)
- [SMS notifications](#sms-notifications)
- [After the first deploy](#after-the-first-deploy)
- [Troubleshooting](#troubleshooting)
- [Project structure](#project-structure)
- [Roles & access](#roles--access)
- [Security notes](#security-notes)

---

## Tech stack

| Layer      | Choice                                                              |
|------------|---------------------------------------------------------------------|
| Backend    | PHP 8.0+, procedural/functional, PDO for all DB access               |
| Database   | MySQL 5.7+ / MariaDB 10.3+ (InnoDB, utf8mb4)                         |
| Frontend   | Server-rendered PHP + Tailwind CSS (Play CDN, `cdn.tailwindcss.com`) |
| Auth       | Sessions + `password_hash`/`password_verify`, CSRF tokens on forms   |
| Icons      | Inline SVG (`includes/icons.php`) — no icon font, no extra requests  |
| Photos     | Unsplash CDN, defined in `includes/images.php`                       |
| PDF export | Browser "Print / Save as PDF" on report pages                        |

**The browser needs internet access** — Tailwind and the homepage photography
both load from CDNs. There is no Node/npm step; nothing is compiled.

---

## Quick start (local)

### Requirements

- PHP 8.0+ with `pdo_mysql`, `fileinfo` and `gd` extensions
- MySQL 5.7+ / MariaDB 10.3+
- XAMPP/WAMP is fine — or just PHP's built-in server

### Windows (XAMPP), one command

Start **MySQL** from the XAMPP Control Panel, then:

```bash
setup.bat
```

That creates the database, imports the schema, creates an admin, and starts the
server on <http://localhost:8000>.

### Any platform, manually

1. **Create the database and import the schema:**

   ```bash
   mysql -u root -p -e "CREATE DATABASE ikizere_funds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

   ```bash
   mysql -u root -p ikizere_funds < database/schema.sql
   ```

   This seeds roles, permission codes, savings/loan types and notification
   templates — but **no user accounts**.

2. **Create your `.env`:**

   ```bash
   cp .env.example .env
   ```

   For local development set `APP_DEBUG=1` so PHP errors are visible.

3. **Create the first login** (the schema seeds none):

   ```bash
   php scripts/create_admin.php "Your Name" president1 "SomeStrongPass!" you@example.com 0788000000 president
   ```

   Run it with no arguments to be prompted interactively instead.

4. **Serve it.** Always pass `router.php` — see the warning below:

   ```bash
   php -S localhost:8000 -t . router.php
   ```

   Then open <http://localhost:8000>.

> ### ⚠️ Always start the built-in server with `router.php`
>
> Apache reads `.htaccess`; **PHP's built-in server does not.** Started without
> the router, `php -S` will happily serve `/.env` and `/database/schema.sql` as
> plain text to anyone who asks — your database credentials and full schema.
>
> `router.php` enforces the same rules as `.htaccess` in PHP, so both hosts
> behave identically. The Dockerfile, `Procfile` and `railway.json` all use it.

---

## Deploying to Railway

Railway builds from the `Dockerfile` and runs `docker-entrypoint.sh`, which
waits for MySQL, runs `scripts/railway_setup.php`, then starts PHP with
`router.php`. The database is created, migrated and seeded automatically on
every boot — **no manual SQL import is needed.**

### Step 1 — Push your code to GitHub

```bash
git add -A && git commit -m "Deploy to Railway" && git push
```

### Step 2 — Create the Railway project

1. Go to <https://railway.app> and sign in.
2. **New Project → Deploy from GitHub repo**.
3. Pick this repository and authorise access.

Railway detects `railway.json` and builds with the Dockerfile automatically.

### Step 3 — Add the MySQL database

In the same project: **New → Database → Add MySQL**.

Railway injects `MYSQLHOST`, `MYSQLPORT`, `MYSQL_DATABASE`, `MYSQL_USER`,
`MYSQL_PASSWORD` and `MYSQL_URL` into your service. The app reads all of these
automatically — **you do not need to set any `DB_*` variable yourself.**

> If the MySQL service does not appear to be linked, open your **web service →
> Variables → Add Variable Reference** and add the `MYSQL_*` variables from the
> database service.

### Step 4 — Set your own admin password

Before the first boot, add these under **your web service → Variables**:

| Variable     | Example                | Why                                        |
|--------------|------------------------|--------------------------------------------|
| `ADMIN_USER` | `president`            | Username for the first president account    |
| `ADMIN_PASS` | *a strong password*    | **Set this** — otherwise a published default is used |
| `ADMIN_NAME` | `Iradukunda Daniel`    | Display name                                |
| `ADMIN_EMAIL`| `you@example.com`      | Must be unique                              |
| `ADMIN_PHONE`| `+250790974685`        | Optional                                    |
| `SEED_ROLE_ACCOUNTS` | `0`            | Skip the demo VP/secretary/accountant/auditor logins |

If you skip `ADMIN_PASS`, the deploy still succeeds but boots with the default
password documented below and prints a warning in the deploy logs.

### Step 5 — Generate a public URL

**Settings → Networking → Generate Domain.** Railway assigns
`your-app.up.railway.app` and terminates TLS for you.

### Step 6 — Add a volume so uploads survive redeploys

⚠️ **Important.** Container filesystems are wiped on every redeploy. Without a
volume, member photos and uploaded ID documents are **permanently lost** each
time you deploy.

**Your service → Variables/Settings → Volumes → New Volume**, mount path:

```
/var/www/html/assets/uploads
```

### Step 7 — Deploy and check the logs

Watch **Deployments → View Logs**. A healthy boot looks like:

```
=== IKIZERE FUNDS — Docker Setup ===
FINAL DB: host=... port=3306 name=railway user=root
Waiting for MySQL...
 MySQL is ready.
[Railway Setup] Importing database/schema.sql ...
[Railway Setup] Imported 42 SQL statements.
[Railway Setup] Created president: president
[Railway Setup] Done.
Starting server on port 8080...
```

Then open your domain and sign in at `/login.php`.

---

## Redeploying on Railway

**Redeploying is safe.** `scripts/railway_setup.php` is idempotent — on an
existing database it prints `Database already seeded … Skipping import` and
leaves every table untouched. Members, savings, loans, meetings and changed
passwords all survive. You can redeploy as often as you like.

### Method 1 — push to GitHub (normal way)

Any push to your default branch triggers a rebuild automatically:

```bash
git add -A && git commit -m "Your change" && git push
```

Railway picks it up within a few seconds. Watch **Deployments → View Logs**.

### Method 2 — redeploy the same code from the dashboard

Use this after changing an environment variable, or to retry a failed build:

1. Open your **web service** in Railway.
2. **Deployments** tab.
3. On the most recent deployment, click **⋯ → Redeploy**.

> Changing a variable under **Variables** already triggers a redeploy on its
> own — you do not need to do both.

### Method 3 — Railway CLI

```bash
npm i -g @railway/cli
```

```bash
railway login && railway link && railway up
```

### What survives a redeploy

| Survives | Does **not** survive |
|----------|----------------------|
| Everything in MySQL — members, savings, loans, meetings, messages, settings, changed passwords | Files in `assets/uploads/` **unless a volume is mounted** (see Step 6) |
| Environment variables | The container filesystem generally |
| Your generated domain | The settings cache file (harmless — it rebuilds in one request) |

### Rolling back

**Deployments → pick an earlier successful deployment → ⋯ → Redeploy.** Code
rolls back immediately. Note that database *migrations* are forward-only: the
setup script adds columns and templates but never drops them, so a rollback of
code is safe against a newer database.

### Deploy checklist

- [ ] `ADMIN_PASS` set (or all seeded passwords changed after first login)
- [ ] MySQL plugin attached, `MYSQL*` variables visible on the web service
- [ ] Volume mounted at `/var/www/html/assets/uploads`
- [ ] `APP_DEBUG` unset or `0`
- [ ] Domain generated under Settings → Networking
- [ ] Logs end with `[Railway Setup] Done.` then `Starting server on port …`

---

## Environment variables

Nothing is required on Railway if you added the MySQL plugin — but everything
can be overridden.

### Database (auto-detected on Railway)

Resolved in this order: `DB_*` → `DATABASE_URL`/`MYSQL_URL` → `MYSQL*` → defaults.

| Variable  | Default     | Notes                                        |
|-----------|-------------|----------------------------------------------|
| `DB_HOST` | `127.0.0.1` | Falls back to `MYSQLHOST`                     |
| `DB_PORT` | `3306`      | Falls back to `MYSQLPORT`                     |
| `DB_NAME` | `ikizere_funds` | Falls back to `MYSQL_DATABASE`            |
| `DB_USER` | `root`      | Falls back to `MYSQL_USER`                    |
| `DB_PASS` | *(empty)*   | Falls back to `MYSQL_PASSWORD`                |
| `DATABASE_URL` / `MYSQL_URL` | — | `mysql://user:pass@host:port/db` |

### Application

| Variable    | Default          | Notes                                                    |
|-------------|------------------|----------------------------------------------------------|
| `APP_URL`   | *auto-detected*  | Leave unset on Railway — detected from the request host   |
| `APP_DEBUG` | `0`              | `1` shows PHP errors. **Never set to 1 in production.**   |
| `PORT`      | `8080`           | Set by Railway automatically                              |

### First-run admin seeding

| Variable              | Default                | Notes                                     |
|-----------------------|------------------------|-------------------------------------------|
| `ADMIN_USER`          | `president`            |                                           |
| `ADMIN_PASS`          | `President@123`        | **Override this.** Published default.      |
| `ADMIN_NAME`          | `Club President`       |                                           |
| `ADMIN_EMAIL`         | `president@ikizere-funds.railway.app` | Must be unique          |
| `ADMIN_PHONE`         | `+250700000001`        |                                           |
| `SEED_ROLE_ACCOUNTS`  | *(enabled)*            | Set to `0` to skip the demo role logins    |

> **Real environment variables always win over `.env`.** A `.env` file only
> fills gaps, so a stray file can never override your platform config.

---

## Login credentials

Sign in at **`/login.php`** on your deployed domain
(`https://your-app.up.railway.app/login.php`).

### Seeded leadership accounts

`scripts/railway_setup.php` creates these on the first boot. They are created
**only** when the username and email are both still free, so re-deploying never
overwrites a password you have changed.

| # | Role | Username | Password | Seeded email |
|---|------|----------|----------|--------------|
| 1 | **President** | `president` | `President@123` | `president@ikizere-funds.railway.app` |
| 2 | **Vice President** | `vicepresident` | `VicePresident@123` | `vicepresident@ikizere-funds.railway.app` |
| 3 | **Secretary** | `secretary` | `Secretary@123` | `secretary@ikizere-funds.railway.app` |
| 4 | **Accountant** | `accountant` | `Accountant@123` | `accountant@ikizere-funds.railway.app` |
| 5 | **Auditor** | `auditor` | `Auditor@123` | `auditor@ikizere-funds.railway.app` |

> ### 🔴 These passwords are published in this README
>
> Anyone who finds your repository can read them. Before or immediately after
> your first deploy you **must** either set `ADMIN_PASS` (below), or log in and
> change every password you intend to keep and delete the rest.

### What each role can do

| Role | Primary responsibility | Key access |
|------|------------------------|------------|
| **President** | Runs the club; full administrative control | Everything — members, savings, loans, meetings, finance, reports, announcements, permissions, board terms, settings |
| **Vice President** | Deputises for the president | Members, savings, loans, meetings, finance, reports, documents (view) |
| **Secretary** | Register, minutes, correspondence | Members, join requests, meetings, documents (upload), announcements, feedback |
| **Accountant** | All money movement | Savings, loans, finance (fines/expenses/income), reports, members |
| **Auditor** | Independent oversight — read-only on money | Reports, documents (view), own savings/loans |
| **Member** | Ordinary saver | Own profile, own savings and loans, own messages, meetings (view + own attendance), notifications |

The full permission matrix is in [Roles & access](#roles--access), and every
permission is editable at runtime under **Permissions** — these are defaults,
not hard-coded rules.

### Changing the credentials

Three ways, best first:

1. **Before the first deploy — set env vars on Railway** (nothing is ever
   published):

   | Variable | Purpose |
   |----------|---------|
   | `ADMIN_PASS` | Password for the president account |
   | `ADMIN_USER` | Username, if you don't want `president` |
   | `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PHONE` | Profile details |
   | `SEED_ROLE_ACCOUNTS=0` | Skip accounts 2–5 entirely and create real people by hand |

2. **After logging in** — Members → Edit → set a new password for each account,
   and delete any seeded account you are not using.

3. **Locally, from the CLI** — create an account with your own password:

   ```bash
   php scripts/create_admin.php "Your Name" myusername "MyStrongPass!" you@example.com 0788000000 president
   ```

### Forgotten passwords (self-service)

Members reset their own password from **Forgot password?** on the login page:

1. They enter their username **or** the email on their account.
2. If SMTP is configured and they have an email on file, they get a link that
   works **once** and expires after **one hour**.
3. The link opens a page to choose a new password (minimum 8 characters).
4. Using it invalidates every other outstanding link for that account and
   clears any lockout from failed login attempts.

The database only ever stores `sha256(token)` — the usable value exists only in
the email. Requests are throttled to 3 live links per account per 15 minutes,
and the page returns the same message whether or not the account exists, so it
cannot be used to discover usernames.

**Without SMTP, or for an account with no email**, the request falls back to the
old behaviour: club leadership is notified and fulfils it under **Password
Resets**. Nothing breaks; the member just needs a person in the loop.

### If you are locked out

The president can reset anyone's password under **Password Resets**. If no
president account works, run `scripts/create_admin.php` against the Railway
database (Railway → MySQL service → **Connect** for the credentials), or connect
with any MySQL client and delete the stale user row so the next deploy re-seeds it.

---

## Email notifications

Savings reminders, loan approvals, payment-due alerts and meeting notices are
**always** written to each member's in-app inbox. Configuring SMTP additionally
delivers them by email.

**This is optional.** Leave it unset and email is skipped cleanly — the app
works exactly as before and notifications stay visible in the inbox.

### Configure

Add these to your Railway service variables (or your local `.env`):

| Variable | Example | Notes |
|----------|---------|-------|
| `SMTP_HOST` | `smtp.gmail.com` | Required to enable email |
| `SMTP_PORT` | `587` | Defaults to 587 (or 465 when `SMTP_SECURE=ssl`) |
| `SMTP_USER` | `ikizerefunds@gmail.com` | Omit to send unauthenticated |
| `SMTP_PASS` | *app password* | **Not** your normal login password |
| `SMTP_SECURE` | `tls` | `tls` (587) · `ssl` (465) · `none` |
| `SMTP_FROM` | `ikizerefunds@gmail.com` | Defaults to `SMTP_USER` |
| `SMTP_FROM_NAME` | `IKIZERE FUNDS Club` | Display name on the message |

> **Gmail:** enable 2-Step Verification, then create an **App Password**
> (Google Account → Security → App passwords) and use that 16-character value
> as `SMTP_PASS`. A normal Gmail password will be rejected.

### Verify

```bash
php scripts/test_email.php you@example.com
```

It prints the resolved configuration, sends one real message, and on failure
dumps the entire SMTP conversation so you can see which step was rejected.

### How delivery behaves

| Situation | Row status | Result |
|-----------|-----------|--------|
| Sent successfully | `sent` | `sent_at` recorded |
| SMTP not configured | stays `pending` | Nothing is lost; sends once configured |
| Member has no email | `failed` | Reason stored on the row |
| Server rejected / unreachable | `failed` | Real SMTP error stored on the row |
| Pending for over 14 days | `expired` | Stops stale reminders going out later |
| `channel = 'sms'` | `failed` | No SMS gateway is wired up |

Nothing is ever marked `sent` unless the mail server accepted it.

---

## SMS notifications

Optional, like email, and configured independently. Useful when members have a
phone but no email address — common for a village savings group.

### Configure

Pick **one** provider.

**Africa's Talking** (recommended for Rwanda — local rates and shortcodes):

| Variable | Example | Notes |
|----------|---------|-------|
| `AT_USERNAME` | `ikizerefunds` | Your account username |
| `AT_API_KEY` | *api key* | From the Africa's Talking dashboard |
| `AT_SENDER_ID` | `IKIZERE` | Optional; an approved sender ID or shortcode |

**Twilio** (alternative):

| Variable | Example |
|----------|---------|
| `TWILIO_SID` | `ACxxxxxxxx…` |
| `TWILIO_TOKEN` | *auth token* |
| `TWILIO_FROM` | `+15551234567` |

Shared options:

| Variable | Default | Purpose |
|----------|---------|---------|
| `SMS_PROVIDER` | auto-detected | `africastalking` or `twilio` |
| `SMS_FALLBACK` | off | `1` texts members who have a phone but no email |
| `SMS_TIMEOUT` | `15` | Seconds |

> **`SMS_FALLBACK` costs money.** It is off by default. Turn it on only when
> you are happy to pay per message for members with no email address.

### Phone numbers

Members are registered with local numbers and these are converted to E.164
automatically, so nothing has to be re-entered:

| Stored as | Sent as |
|-----------|---------|
| `0790974685` | `+250790974685` |
| `250790974685` | `+250790974685` |
| `790974685` | `+250790974685` |
| `+250 790 974 685` | `+250790974685` |

Anything unparseable is recorded as `failed` with the reason, never guessed at.

### Verify

```bash
php scripts/test_sms.php 0790974685
```

Set `AT_USERNAME=sandbox` with a sandbox API key to test without spending
credit — the client switches to the sandbox host automatically.

### How routing works

| Notification | Goes by |
|--------------|---------|
| `channel = 'sms'` | SMS |
| `channel = 'email'` (default) | Email |
| `channel = 'email'`, no email on file, `SMS_FALLBACK=1`, phone present | SMS |
| Neither gateway configured | Stays `pending`, still shown in the in-app inbox |

Messages are prefixed with the club name and capped at 300 characters, since
gateways bill per 160-character segment.

### Sending on a schedule

`scripts/send_reminders.php` queues the day's reminders and dispatches
everything pending. Run it once daily:

```bash
php scripts/send_reminders.php
```

On Railway, add it as a **Cron** service (Settings → Cron Schedule, e.g.
`0 8 * * *`) pointed at the same image. It exits non-zero if every send failed,
so a failing schedule is visible.

---

## After the first deploy

1. **Log in and change every seeded password** (Settings → or Members → Edit).
2. **Delete the role accounts you don't use.**
3. **Fill in club details** under Settings — `club_name`, `club_email`,
   `club_phone`, `logo_path`. These drive the header, footer and contact page.
   Until they're set, the site falls back to the president's published contact
   from `includes/leadership.php`.
4. **Optional social links** — `club_facebook`, `club_twitter`,
   `club_instagram`, `club_linkedin` in Settings. Icons appear in the footer
   only when a value is set, so there are never dead links.
5. **Confirm `APP_DEBUG` is unset or `0`.**
6. **Set up daily reminders** (optional) — run `php scripts/send_reminders.php`
   once a day via a Railway cron service or an external scheduler.

---

## Troubleshooting

| Symptom | Cause & fix |
|---|---|
| `exec /usr/local/bin/docker-entrypoint.sh: no such file or directory` | The shell script was committed with CRLF line endings. `.gitattributes` forces LF — make sure it is committed, then `git add --renormalize . && git commit`. |
| Deploy loops / restarts | Check logs. Usually MySQL isn't linked — confirm the `MYSQL*` variables are present on the **web service**, not just the database service. |
| `Cannot connect to MySQL` | The MySQL plugin isn't attached, or you set a partial `DB_*` override. Remove your `DB_*` variables and let the `MYSQL*` ones be used. |
| Site loads but is unstyled | The Tailwind CDN is blocked. The browser needs outbound internet access. |
| Homepage photos missing | `images.unsplash.com` is unreachable. Swap the ids in `includes/images.php`, or download them into `assets/` and point the entries at local paths. |
| Uploaded photos vanish after deploy | No volume mounted. See **Step 6**. |
| `/.env` or `/database/schema.sql` is downloadable | The server was started **without** `router.php`. See the warning in Quick start. |
| 404 page is the plain PHP one | Same cause — start with `router.php`. |
| Everything 404s / homepage on every URL | The old `railway.json` used `index.php` as the router. It must be `router.php`. |

---

## Project structure

```
config/
  config.php          Env-based settings, session hardening, security headers
  database.php        PDO singleton via db()
includes/
  auth.php            Login, rate limiting, requireLogin()/requireRole()
  functions.php       e(), CSRF helpers, flash messages, statusBadge()
  icons.php           Inline SVG icon set — icon() and brandIcon()
  images.php          Homepage photography (Unsplash ids + alt text)
  club_info.php       Club contact details, with fallback to the president's
  notifications.php   queueNotification() / dispatchPendingNotifications()
  nav.php             Logged-in sidebar navigation
  public_nav.php      Public tab bar (labels, icons, dropdown descriptions)
  leadership.php      Committee list for the public pages
  header.php          Two-tier public header + logged-in topbar, all styling
  footer.php          Site footer, back-to-top, scroll-reveal, FAQ accordion
  page_loader.php     Branded loading overlay
  flash_toasts.php    Auto-dismissing toasts

router.php            Front controller for `php -S` — enforces .htaccess rules
sitemap.php           XML sitemap (served at /sitemap.xml)
robots.txt            Crawl rules

Public pages: index, about, membership, leadership, announcements, contact,
feedback, forgot_password, privacy, terms, 404

modules/              members, membership_requests, member_documents,
                      password_resets, savings, loans, meetings, messages,
                      notifications, finance, reports, announcements, feedback,
                      documents, board_terms, permissions, settings

database/schema.sql   Full schema + seed data
scripts/
  create_admin.php    Bootstrap the first login
  railway_setup.php   Idempotent deploy bootstrap (schema + admin + migrations)
  send_reminders.php  Daily reminder dispatch
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

**Email notification delivery is live** — see
[Email notifications](#email-notifications). `dispatchPendingNotifications()`
sends over SMTP and only marks a row `sent` once the server accepts the
message; failures are recorded with the reason on the row.

**SMS delivery is also live** — see [SMS notifications](#sms-notifications).
Africa's Talking and Twilio are both supported, with local Rwandan numbers
converted to E.164 automatically. Like email it is optional: unconfigured, rows
stay `pending` and remain visible in the in-app inbox.

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
- `router.php` blocks direct HTTP access to `.env`, dotfiles, `config/`,
  `database/`, `scripts/`, `includes/` and build manifests, and refuses to
  execute any `.php` uploaded into `assets/uploads/`. This matters because
  PHP's built-in server — used by Docker and Railway — ignores `.htaccess`
  entirely; without the router those paths are publicly readable.
- Real environment variables take precedence over `.env`, so a leftover file
  cannot silently override production configuration.
- Security headers (`X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`) are sent from `config/config.php` on every response, so
  they apply on both Apache and the built-in server.
- The public site is `index, follow`; every logged-in page is served
  `noindex, nofollow`.

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
- Homepage photography is hotlinked from the Unsplash CDN (see `includes/images.php`).
  To self-host, download the images into `assets/images/` and point the `id`
  entries at local paths.
- Uploads live on the container filesystem; on Railway they need a mounted
  volume or they are lost on every redeploy.
- No automated test suite; verification is manual (see above).
- Email and SMS both work once configured; unconfigured, notifications stay
  in the in-app inbox only.
- PDF export relies on the browser's print dialog rather than a server-generated file.
