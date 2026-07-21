-- ============================================================
-- IKIZERE FUNDS Club Website
-- Full database schema (MySQL / MariaDB, InnoDB, utf8mb4)
-- ============================================================

CREATE DATABASE IF NOT EXISTS ikizere_funds
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ikizere_funds;

-- ============================================================
-- SECTION 1: ACCESS CONTROL
-- roles/permissions are normalized (instead of a hardcoded enum)
-- so the President can create new roles later without a code change.
-- ============================================================

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,          -- e.g. president, accountant, member
  description VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,          -- e.g. loans.approve, members.register
  description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 2: USERS & AUTHENTICATION
-- Every login (leaders and members) is a row here; members get
-- an extra profile row in `members`.
-- ============================================================

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) UNIQUE,
  phone VARCHAR(20) UNIQUE,
  photo_path VARCHAR(255) NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- One-time tokens for "forgot password" flows.
CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tracks login attempts for basic brute-force lockout logic.
CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  ip_address VARCHAR(45),
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_username_time (username, attempted_at)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 3: MEMBERS
-- ============================================================

CREATE TABLE members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  member_number VARCHAR(20) NOT NULL UNIQUE,
  national_id VARCHAR(30) UNIQUE,
  gender ENUM('male','female','other'),
  date_of_birth DATE,
  address VARCHAR(255),
  occupation VARCHAR(100),
  photo_path VARCHAR(255),
  join_date DATE NOT NULL,
  membership_status ENUM('active','inactive','withdrawn','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A member can list more than one next of kin, so this is its own table
-- rather than flat columns on `members`.
CREATE TABLE next_of_kin (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  relationship VARCHAR(50),
  phone VARCHAR(20),
  address VARCHAR(255),
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Scanned ID cards, signed application forms, etc.
CREATE TABLE member_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  document_type VARCHAR(50) NOT NULL,        -- e.g. national_id, application_form
  file_path VARCHAR(255) NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 4: SAVINGS
-- saving_types lets the club offer more than one savings product
-- (e.g. regular monthly savings vs. a fixed-term plan) without
-- restructuring the savings table later.
-- ============================================================

CREATE TABLE saving_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,           -- e.g. Regular Savings, Fixed Deposit
  minimum_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_withdrawable TINYINT(1) NOT NULL DEFAULT 1,
  description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE savings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  saving_type_id INT UNSIGNED NOT NULL,
  transaction_type ENUM('deposit','withdrawal') NOT NULL DEFAULT 'deposit',
  amount DECIMAL(12,2) NOT NULL,
  saving_date DATE NOT NULL,
  recorded_by INT UNSIGNED NOT NULL,
  notes VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (saving_type_id) REFERENCES saving_types(id),
  FOREIGN KEY (recorded_by) REFERENCES users(id),
  INDEX idx_member_date (member_id, saving_date)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 5: LOANS
-- loan_types separates interest rate/policy from the loan record
-- itself, since a SACCO-style club usually offers several loan
-- products (emergency, development, school fees, ...).
-- ============================================================

CREATE TABLE loan_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,           -- e.g. Emergency Loan, Development Loan
  interest_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  max_amount DECIMAL(12,2),
  max_term_months INT UNSIGNED,
  penalty_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,  -- % applied per late period
  description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE loans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  loan_type_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  interest_rate DECIMAL(5,2) NOT NULL,        -- copied from loan_types at approval time
  interest_amount DECIMAL(12,2) NOT NULL,
  total_payable DECIMAL(12,2) NOT NULL,
  term_months INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','rejected','active','completed','defaulted') NOT NULL DEFAULT 'pending',
  applied_date DATE NOT NULL,
  approved_date DATE NULL,
  approved_by INT UNSIGNED NULL,
  rejection_reason VARCHAR(255) NULL,
  due_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (loan_type_id) REFERENCES loan_types(id),
  FOREIGN KEY (approved_by) REFERENCES users(id),
  INDEX idx_member_status (member_id, status)
) ENGINE=InnoDB;

-- SACCO-style clubs typically require a fellow member to guarantee a loan.
CREATE TABLE loan_guarantors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id INT UNSIGNED NOT NULL,
  guarantor_member_id INT UNSIGNED NOT NULL,
  amount_guaranteed DECIMAL(12,2) NOT NULL,
  status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  UNIQUE KEY uniq_loan_guarantor (loan_id, guarantor_member_id),
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  FOREIGN KEY (guarantor_member_id) REFERENCES members(id)
) ENGINE=InnoDB;

-- Generated once a loan is approved: one row per expected installment,
-- so "amount due this month" is a lookup, not a recalculation.
CREATE TABLE repayment_schedule (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id INT UNSIGNED NOT NULL,
  installment_number INT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  expected_amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','paid','late','partially_paid') NOT NULL DEFAULT 'pending',
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  INDEX idx_loan_due (loan_id, due_date)
) ENGINE=InnoDB;

-- Actual payments made against a loan; penalty_amount is broken out
-- so reports can distinguish principal+interest recovered vs. fines collected.
CREATE TABLE loan_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id INT UNSIGNED NOT NULL,
  repayment_schedule_id INT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  penalty_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_date DATE NOT NULL,
  payment_method ENUM('cash','bank','mobile_money') NOT NULL DEFAULT 'cash',
  recorded_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  FOREIGN KEY (repayment_schedule_id) REFERENCES repayment_schedule(id),
  FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 6: MEETINGS
-- ============================================================

CREATE TABLE meetings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  meeting_date DATETIME NOT NULL,
  location VARCHAR(150),
  agenda TEXT,
  minutes TEXT,
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE meeting_attendance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  status ENUM('present','absent','excused') NOT NULL DEFAULT 'present',
  UNIQUE KEY uniq_meeting_member (meeting_id, member_id),
  FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 7: CLUB FINANCE (beyond savings/loans)
-- ============================================================

-- Fines for things like meeting absence or late savings — kept
-- separate from loan penalties since they have a different cause.
CREATE TABLE fines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id INT UNSIGNED NOT NULL,
  reason VARCHAR(150) NOT NULL,              -- e.g. absence, late_saving
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid',
  fine_date DATE NOT NULL,
  paid_date DATE NULL,
  recorded_by INT UNSIGNED NOT NULL,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Club operating expenses (stationery, transport, refreshments...).
CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(80) NOT NULL,
  description VARCHAR(255),
  amount DECIMAL(12,2) NOT NULL,
  expense_date DATE NOT NULL,
  approved_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Other income the club receives outside member savings/loan interest
-- (e.g. registration fees, donations) — needed for a complete financial report.
CREATE TABLE income (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(80) NOT NULL,               -- e.g. registration_fee, donation
  description VARCHAR(255),
  amount DECIMAL(12,2) NOT NULL,
  income_date DATE NOT NULL,
  recorded_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 8: COMMUNICATION
-- ============================================================

-- Reusable message templates (so wording is edited in one place).
CREATE TABLE notification_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('saving_reminder','loan_approval','payment_due','meeting_reminder','late_payment') NOT NULL UNIQUE,
  subject VARCHAR(150),
  body TEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('saving_reminder','loan_approval','payment_due','meeting_reminder','late_payment') NOT NULL,
  channel ENUM('sms','email') NOT NULL,
  message TEXT NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Public home-page news/announcements.
CREATE TABLE announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  content TEXT NOT NULL,
  photo_path VARCHAR(255),
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  posted_by INT UNSIGNED NOT NULL,
  posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (posted_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 9: SYSTEM / GOVERNANCE
-- ============================================================

-- Key-value store for club-wide settings (name, logo, default
-- interest rate, currency...) editable from the admin dashboard
-- without touching code.
CREATE TABLE club_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT,
  updated_by INT UNSIGNED,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- General club documents: constitution, bylaws, AGM reports (distinct
-- from member_documents, which are per-member records).
CREATE TABLE documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  category VARCHAR(80),                      -- e.g. constitution, bylaws, agm_report
  file_path VARCHAR(255) NOT NULL,
  uploaded_by INT UNSIGNED NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Tracks who held which leadership position and when — useful once
-- elections rotate the President/Secretary/etc. across terms.
CREATE TABLE board_terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- Records sensitive admin actions for accountability (who approved
-- a loan, who edited a member's record, etc.).
CREATE TABLE audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  action VARCHAR(100) NOT NULL,
  target_table VARCHAR(60),
  target_id INT UNSIGNED,
  details TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- SECTION 10: COMMUNICATION CHANNELS & PUBLIC SUBMISSIONS
-- ============================================================

-- Two kinds of threads in one table: a member's question to leadership
-- (channel = member_leadership, visible to that member + all leaders), and
-- an internal leadership-only discussion board (channel = leadership_only).
-- parent_id NULL marks the start of a thread; replies point back to it.
CREATE TABLE messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  channel ENUM('member_leadership', 'leadership_only') NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  subject VARCHAR(150),
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES messages(id) ON DELETE CASCADE,
  INDEX idx_channel_parent (channel, parent_id)
) ENGINE=InnoDB;

-- Public "share an idea" box on the website — no login required, so no
-- user_id FK; name/email are optional and self-reported.
CREATE TABLE feedback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  email VARCHAR(150),
  message TEXT NOT NULL,
  status ENUM('new', 'reviewed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Public "request to join" submissions from prospective members. Approving
-- one doesn't auto-create a user — staff still register the member via the
-- Members module, using these details as reference.
CREATE TABLE membership_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150),
  phone VARCHAR(20),
  message TEXT,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  reviewed_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA: default roles and permission codes
-- ============================================================

INSERT INTO roles (name, description) VALUES
  ('president', 'Full control over the system'),
  ('vice_president', 'Assists the President'),
  ('secretary', 'Manages meeting records and announcements'),
  ('accountant', 'Manages savings and loans'),
  ('auditor', 'Reviews financial records and monitors compliance'),
  ('member', 'Regular club member');

INSERT INTO permissions (code, description) VALUES
  ('members.register', 'Register a new member'),
  ('members.edit', 'Edit member profile'),
  ('savings.record', 'Record a savings transaction'),
  ('loans.apply', 'Apply for a loan'),
  ('loans.approve', 'Approve or reject a loan'),
  ('loans.record_payment', 'Record a loan repayment'),
  ('meetings.manage', 'Create meetings and record minutes/attendance'),
  ('reports.view', 'View and export reports'),
  ('announcements.publish', 'Publish home page announcements'),
  ('settings.manage', 'Change club-wide settings'),
  ('members.manage', 'View and register members'),
  ('member_documents.manage', 'Upload and manage member documents'),
  ('membership_requests.manage', 'Review public join requests'),
  ('password_resets.manage', 'Fulfill or perform password resets'),
  ('savings.access', 'View and record savings'),
  ('loans.access', 'View, apply for, or approve loans'),
  ('meetings.access', 'View and manage meetings'),
  ('finance.manage', 'Record fines, expenses, and other income'),
  ('documents.manage', 'Upload and delete club documents'),
  ('feedback.review', 'Review visitor feedback'),
  ('messages.manage', 'See all member-leadership threads and post to the leadership-only channel'),
  ('dashboard.overview', 'See club-wide dashboard stats instead of just your own');

-- Page-level permissions actually enforced by requirePermission() in each
-- module, seeded to exactly match the requireRole() lists they replaced —
-- this seed changes nothing on a fresh install, it just makes the same
-- policy editable from Club Settings > Permissions afterward.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'members.manage','member_documents.manage','membership_requests.manage','password_resets.manage',
  'savings.access','loans.access','meetings.access','finance.manage','reports.view',
  'announcements.publish','documents.manage','feedback.review','settings.manage',
  'members.register','members.edit','savings.record','loans.apply','loans.approve',
  'loans.record_payment','meetings.manage','messages.manage','dashboard.overview'
) WHERE r.name = 'president';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'members.manage','member_documents.manage','membership_requests.manage','password_resets.manage',
  'savings.access','loans.access','meetings.access','finance.manage','reports.view','feedback.review',
  'members.register','members.edit','savings.record','loans.approve','loans.record_payment','meetings.manage',
  'messages.manage','dashboard.overview'
) WHERE r.name = 'vice_president';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'members.manage','member_documents.manage','membership_requests.manage','password_resets.manage',
  'meetings.access','announcements.publish','documents.manage','feedback.review',
  'members.register','members.edit','meetings.manage','messages.manage','dashboard.overview'
) WHERE r.name = 'secretary';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'members.manage','member_documents.manage','membership_requests.manage','password_resets.manage',
  'savings.access','loans.access','finance.manage','reports.view',
  'members.register','members.edit','savings.record','loans.approve','loans.record_payment','messages.manage',
  'dashboard.overview'
) WHERE r.name = 'accountant';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN ('reports.view', 'messages.manage', 'dashboard.overview')
WHERE r.name = 'auditor';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code IN (
  'savings.access','loans.access','meetings.access','loans.apply'
) WHERE r.name = 'member';

INSERT INTO saving_types (name, minimum_amount, is_withdrawable, description) VALUES
  ('Regular Savings', 0.00, 1, 'Standard monthly member savings'),
  ('Fixed Deposit', 0.00, 0, 'Locked savings held for a fixed term');

INSERT INTO loan_types (name, interest_rate, max_amount, max_term_months, penalty_rate, description) VALUES
  ('Emergency Loan', 5.00, 500000.00, 6, 2.00, 'Short-term loan for urgent member needs'),
  ('Development Loan', 5.00, 2000000.00, 12, 2.00, 'Longer-term loan for development projects');

INSERT INTO notification_templates (type, subject, body) VALUES
  ('saving_reminder', 'Savings Reminder', 'Dear {{name}}, this is a reminder to make your monthly savings contribution to IKIZERE FUNDS Club.'),
  ('loan_approval', 'Loan Approved', 'Dear {{name}}, your loan application of {{amount}} has been approved. Total payable: {{total_payable}}, due by {{due_date}}.'),
  ('payment_due', 'Loan Payment Due', 'Dear {{name}}, your loan installment of {{amount}} is due on {{due_date}}. Please make your payment on time to avoid penalties.'),
  ('meeting_reminder', 'Upcoming Meeting', 'Dear {{name}}, reminder: "{{title}}" is scheduled on {{meeting_date}} at {{location}}.'),
  ('late_payment', 'Late Payment Alert', 'Dear {{name}}, your loan installment of {{amount}} due on {{due_date}} is now overdue. A penalty may apply.'),
  ('password_reset_request', 'Password Reset Request', '{{username}} ({{name}}) has requested a password reset. Please set a new temporary password for them via the Password Resets page.');
