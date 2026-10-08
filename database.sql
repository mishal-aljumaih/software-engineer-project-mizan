
USE mizan_db;

-- جدول المستخدمين
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    currency   VARCHAR(10) DEFAULT 'SAR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول المشاريع
CREATE TABLE projects (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    name       VARCHAR(200) NOT NULL,
    type       ENUM('car','house','occasion','work','devices','custom') DEFAULT 'custom',
    status     ENUM('active','done','archived') DEFAULT 'active',
    budget     DECIMAL(12,2) DEFAULT 0.00,
    sell_price DECIMAL(12,2) DEFAULT 0.00,
    color      VARCHAR(10) DEFAULT '#3b82f6',
    notes      TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول العمليات والمصاريف
CREATE TABLE expenses (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    project_id    INT NOT NULL,
    title         VARCHAR(200) NOT NULL,
    amount        DECIMAL(12,2) NOT NULL,
    category      VARCHAR(100) DEFAULT 'عام',
    vendor        VARCHAR(150),
    purchase_date DATE,
    notes         TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الضمانات
CREATE TABLE warranties (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    expense_id INT NOT NULL,
    start_date DATE,
    end_date   DATE,
    file_path  VARCHAR(255),
    notes      VARCHAR(255),
    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الفواتير
CREATE TABLE invoices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    expense_id  INT NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    file_type   VARCHAR(20) DEFAULT 'image',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول الملفات العامة
CREATE TABLE files (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    expense_id  INT,
    file_name   VARCHAR(255) NOT NULL,
    file_path   VARCHAR(500) NOT NULL,
    file_size   INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول التنبيهات
CREATE TABLE notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    message    TEXT NOT NULL,
    type       VARCHAR(50) DEFAULT 'general',
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول إعادة تعيين كلمة المرور
CREATE TABLE password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Settings columns on users ──
ALTER TABLE users
  ADD COLUMN theme          VARCHAR(10)  DEFAULT 'light',
  ADD COLUMN language       VARCHAR(5)   DEFAULT 'ar',
  ADD COLUMN notif_email    TINYINT(1)   DEFAULT 1,
  ADD COLUMN notif_warranty TINYINT(1)   DEFAULT 1,
  ADD COLUMN notif_budget   TINYINT(1)   DEFAULT 1;

-- ── Project nature column (Personal vs Commercial) ──
-- Commercial projects show profit/loss; personal projects show expense analytics.
-- Run this only if the column doesn't yet exist:
ALTER TABLE projects
  ADD COLUMN nature ENUM('personal','commercial') DEFAULT 'personal';

-- ── File kind column (used by the Mizan Vault tabs) ──
-- Drives the top-tab filter: All / Invoices / Warranties / Contracts / Others.
ALTER TABLE files
  ADD COLUMN file_kind ENUM('invoice','warranty','contract','other') DEFAULT 'other',
  ADD INDEX idx_files_kind (file_kind);

-- ── Rate-limiting table ──
CREATE TABLE login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45) NOT NULL,
    attempts     INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Secure project share links (random token + expiry + revocation) ──
CREATE TABLE IF NOT EXISTS project_share_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    token CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_active (project_id, revoked_at, expires_at),
    INDEX idx_user_project (user_id, project_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL COMMENT 'نوع الحدث مثل: login, create_project, export_csv',
  `details` text DEFAULT NULL COMMENT 'تفاصيل إضافية عن الحدث',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة صلاحية المدير لجدول المستخدمين
ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) DEFAULT 0;

-- إنشاء جدول الشكاوى والدعم الفني
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ترقية حسابك ليكون مديراً (تأكد أن إيميلك صحيح أو عدله)
UPDATE `users` SET `is_admin` = 1 WHERE `id` = 1;

-- ── Mizan 3.0 Schema Additions ───────────────────────────────────────────────

-- Support loop: admin can now write a reply when closing a ticket
ALTER TABLE `support_tickets`
    ADD COLUMN IF NOT EXISTS `admin_reply` VARCHAR(2000) NULL,
    ADD COLUMN IF NOT EXISTS `replied_at` DATETIME NULL;

-- Announcements: admin broadcast banner shown to all users
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message_ar` VARCHAR(500) NOT NULL,
    `message_en` VARCHAR(500) NOT NULL DEFAULT '',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NULL,
    INDEX `idx_ann_active` (`is_active`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Performance indexes (run once on existing DBs) ──────────────────────────
-- Composite index for fast unread-notifications count per user
ALTER TABLE notifications
    ADD INDEX IF NOT EXISTS idx_notif_user_read (user_id, is_read);

-- Token lookup index for password reset (token is the search key)
ALTER TABLE password_resets
    ADD INDEX IF NOT EXISTS idx_reset_token (token(64));

-- Index on activity_logs.created_at for admin dashboard ORDER BY / date filters
ALTER TABLE activity_logs
    ADD INDEX IF NOT EXISTS idx_activity_created (created_at);

-- Index on support_tickets.status for admin open-tickets queries
ALTER TABLE support_tickets
    ADD INDEX IF NOT EXISTS idx_ticket_status (status);