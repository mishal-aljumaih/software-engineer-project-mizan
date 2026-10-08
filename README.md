# Mizan (ميزان) — Financial Archiving & Project Management Platform

<p align="center">
  <img src="assets/images/logo.png" alt="Mizan Logo" width="120" onerror="this.style.display='none'"/>
</p>

<p align="center">
  <strong>منصة ميزان للأرشفة والإدارة المالية الشخصية والتجارية</strong><br>
  <em>A secure, lightweight, and full-featured financial archiving and project management platform built with PHP 8.x and MySQL.</em>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/version-v4.5-blue.svg" alt="Version 4.5">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.x">
  <img src="https://img.shields.io/badge/MySQL-8.0%20%7C%20MariaDB-4479A1?logo=mysql&logoColor=white" alt="MySQL / MariaDB">
  <img src="https://img.shields.io/badge/Playwright-E2E%20Tested-45ba4b?logo=playwright&logoColor=white" alt="Playwright Tested">
  <img src="https://img.shields.io/badge/Security-Hardened%20(CSRF%20%7C%20CSP%20%7C%20PDO)-success" alt="Security Hardened">
  <img src="https://img.shields.io/badge/Languages-Arabic%20%7C%20English%20(RTL%2FLTR)-orange" alt="Bilingual Arabic / English">
  <img src="https://img.shields.io/badge/License-Academic%20%2F%20Educational-lightgrey" alt="License">
</p>

---

## 📑 Table of Contents

- [Overview](#-overview)
- [Key Features](#-key-features)
- [System Architecture & Tech Stack](#-system-architecture--tech-stack)
- [Project Directory Structure](#-project-directory-structure)
- [Security Architecture](#-security-architecture)
- [Prerequisites](#-prerequisites)
- [Quick Start & Local Setup](#-quick-start--local-setup)
  - [1. Clone Repository](#1-clone-repository)
  - [2. Database Setup & Migration](#2-database-setup--migration)
  - [3. Environment Configuration](#3-environment-configuration)
  - [4. Launching the Web Server](#4-launching-the-web-server)
- [Configuration Reference (`.env`)](#-configuration-reference-env)
- [Database Schema & Models](#-database-schema--models)
- [API Endpoints Reference](#-api-endpoints-reference)
- [Testing & Quality Assurance](#-testing--quality-assurance)
  - [Smoke Tests (PHP CLI)](#smoke-tests-php-cli)
  - [Browser & E2E Testing (Playwright)](#browser--e2e-testing-playwright)
- [Academic & Engineering Documentation](#-academic--engineering-documentation)
- [Authors & Acknowledgments](#-authors--acknowledgments)

---

## 🌟 Overview

**Mizan (ميزان)** is an integrated financial management and digital vault solution created to address everyday budgeting, commercial project tracking, and asset warranty management. Designed for university students, freelancers, contractors, and small teams, Mizan eliminates the complexity of heavyweight enterprise ERPs in favor of an intuitive, blazing-fast, and hardened web platform.

Mizan offers a dual-nature architecture:
1. **Personal Mode:** Focuses on expense logging, budget ceiling enforcement, warranty expiration tracking, and multi-format document archiving.
2. **Commercial Mode:** Unlocks revenue tracking, calculated profit & loss (P&L), return-on-investment (ROI) analysis, and vendor reconciliation.

---

## 🚀 Key Features

### 📊 Real-Time Financial Dashboard
- **KPI Metrics:** Immediate visibility into total expenses, active projects count, and net commercial profits.
- **Budget Threshold Alerts:** Visual status indicators that dynamically turn green (<75%), amber (75–90%), or red (>90%) when approaching or breaching budget caps.
- **Interactive Visualizations:** Chart.js powered expense distributions and monthly spending trends.

### 📁 Structured Project Management
- **Classification & Tagging:** Support for dedicated project types (*Automotive, Housing/Real Estate, Occasions/Events, Work/Commercial, Devices/Tech, Custom*).
- **Lifecycle Management:** Formal status transitions (`active` → `done` → `archived`).
- **Nature Toggle:** Instant configuration between Personal (expense analytics) and Commercial (P&L calculations).

### 🧾 Expenses & Financial Transactions
- **Detailed Tracking:** Line-item expenses with title, amount, category tags, vendor names, dates, and notes.
- **Dynamic Recalculations:** Instant updates to project balances and budget margins upon each entry.

### 🛡️ Warranties & Guarantee Monitoring
- **Dedicated Expiry Tracking:** Associate warranties directly with project expenses and hardware purchases.
- **Status Badging:** Real-time calculation of active, expiring soon (within 30 days), and expired guarantees.
- **Direct Vault Linkage:** Inspect warranty certificates and receipts in one click.

### 🗄️ Mizan Digital Vault (Secure Document Storage)
- **High-Capacity Storage:** Configured for attachments up to **500 MB** per file.
- **Broad Format Support:** PDFs, images (JPEG, PNG, WEBP, GIF, HEIC), Office documents (DOCX, XLSX), archives (ZIP), and text files.
- **Categorized Tabs:** Filter vault contents by document kind (*Invoices, Warranties, Contracts, Other*).
- **Isolated & Safe:** Files are validated on upload, scanned for double-extensions, given cryptographically random names, and downloaded via authenticated streams.

### 🔗 Secure Read-Only Sharing
- **Tokenized Access:** Generate isolated 30-day read-only shareable links (`/share.php?token=...`) with no account required for recipients.
- **Zero-Trust Scope:** External reviewers can view expense lists, charts, and warranty statuses without exposing private settings or permitting modifications.
- **One-Click Revocation:** Revoke active share tokens at any time to immediately disable access.

### 📦 Reporting & Archival Exports
- **Summary Reports:** Aggregate spending metrics, category breakdowns, and exportable audit tables.
- **Excel & CSV Export:** Download structured spreadsheet reports for external accounting.
- **Full ZIP Backup:** Generate an on-the-fly ZIP archive containing project metadata, CSV expense logs, and all raw vault documents.

### 🛡️ Administrative Console & Auditing
- **Superadmin Dashboard:** Manage registered accounts, review open support tickets, and view system health metrics.
- **Activity Audit Trail:** Immutable audit logs (`activity_logs`) recording user actions, timestamps, and validated client IP addresses.
- **System-Wide Announcements:** Broadcast sticky notification banners in Arabic and English across user interfaces.
- **Support Ticket System:** Built-in help desk allowing users to file support inquiries and admins to resolve them with custom replies.

### 🌐 Bilingual & Modern UI/UX
- **Bilingual Interface:** Flawless Arabic (RTL) and English (LTR) localisation with seamless instant toggling.
- **Theme Modes:** High-contrast Light Mode and eye-friendly Dark Mode with persistent local preferences.
- **Multi-Currency Support:** Format monetary figures across regional and global currencies (SAR ﷼, USD $, EUR €, GBP £, AED د.إ, KWD د.ك).

---

## 🛠️ System Architecture & Tech Stack

```
┌─────────────────────────────────────────────────────────────┐
│                       Client Layer                          │
│   Vanilla JS (ES6+) · Responsive CSS3 · Chart.js · SweetAlert2 │
│                 Bilingual (AR/EN) · RTL/LTR                 │
└──────────────────────────────▲──────────────────────────────┘
                               │ HTTPS / JSON & HTML
┌──────────────────────────────▼──────────────────────────────┐
│                    Application Layer (PHP 8.x)              │
│  ┌─────────────────────────┐     ┌───────────────────────┐  │
│  │     Pages & Router      │     │    RESTful JSON API   │  │
│  │ (index, welcome, share) │     │    (/api/*.php)       │  │
│  └────────────┬────────────┘     └───────────┬───────────┘  │
│               │                              │              │
│  ┌────────────▼──────────────────────────────▼───────────┐  │
│  │  Security Engine (CSRF, CSP, Rate-Limit, Auth Gate)   │  │
│  └────────────────────────────┬──────────────────────────┘  │
└───────────────────────────────┼─────────────────────────────┘
                                │ PDO (Emulated Prepares OFF)
┌───────────────────────────────▼─────────────────────────────┐
│                 Persistence & Storage Layer                 │
│        MySQL 8.x / MariaDB  (InnoDB, utf8mb4)               │
│        Local Uploads Vault (/uploads/projects/...)           │
└─────────────────────────────────────────────────────────────┘
```

- **Backend:** PHP 8.x (Native PDO, Strict Typing, Session Management, GD / Fileinfo extensions).
- **Database:** MySQL 8.0+ / MariaDB 10.4+ (InnoDB engine, `utf8mb4_unicode_ci` encoding).
- **Frontend:** Vanilla JavaScript (ES6+), Modern Semantic HTML5, CSS Custom Properties (Theme/Direction variables).
- **Third-Party Libraries (CDN):** Chart.js (analytics charts), SweetAlert2 (accessible interactive dialogs).
- **Testing Engine:** Playwright Test (Browser automation) + Custom PHP CLI smoke runner.
- **Web Server:** Apache 2.4 (with `.htaccess` rewrite rules) or PHP Built-in CLI server.

---

## 📂 Project Directory Structure

```text
mizan/
├── admin/                         # Administrative control panel
│   └── dashboard.php              # Superadmin analytics, user oversight & ticket handling
├── admin_login.php                # Dedicated admin authentication portal
├── api/                           # Backend API handlers (JSON endpoints)
│   ├── admin_actions.php          # Admin user/system operations
│   ├── expenses.php               # Expense CRUD operations
│   ├── export_logs.php            # Activity audit trail CSV export
│   ├── export_zip.php             # Full project archive packager (.zip)
│   ├── notifications.php          # Notification polling & read status
│   ├── projects.php               # Project lifecycle and CRUD
│   ├── reports.php                # Summary data aggregation
│   ├── settings.php               # User preferences & profile updater
│   ├── support.php                # Support ticket dispatch
│   └── upload.php                 # Safe multi-format file upload handler
├── assets/                        # Static client-side assets
│   ├── css/
│   │   └── style.css              # Main application stylesheet (Dark/Light, RTL/LTR)
│   ├── js/
│   │   └── main.js                # Core UI scripting, i18n dictionary & theme manager
│   └── images/                    # UI branding, badges, and icons
├── config/                        # Core configuration files
│   ├── .env                       # Local environment variables (DB credentials, app host)
│   ├── .env.example               # Template environment configuration
│   ├── db.php                     # Database connection singleton & formatting helpers
│   └── mail.php                   # Mailer utility (SMTP / PHP mail fallback)
├── docs/                          # Developer & system documentation
│   ├── STS_Test_Plan.md           # System Test Specification & test case suite
│   └── User_Manual_Outline.md     # User guide outline & feature workflows
├── includes/                      # Shared reusable backend modules
│   ├── api_helpers.php            # JSON response formatting & status emitters
│   ├── auth.php                   # Authentication guards and session validation
│   ├── footer.php                 # Standard authenticated view footer
│   ├── header.php                 # Responsive navigation bar & head metadata
│   ├── notifications_helper.php   # Internal notification generation utility
│   ├── security.php               # Security headers, CSRF token issuance, sanitization
│   └── smart_upload_modal.php     # Drag-and-drop file upload interface component
├── mizan_academic_docs/           # University capstone / academic project records
│   ├── 1 - BCS 202- Project Proposal Form.docx
│   ├── 2 - BCS 202 - Status Report.docx
│   ├── 6a -Software test plan (group 4) .docx
│   ├── Mizan_SRS_v2.0.docx       # Software Requirements Specification
│   ├── SDS -Software Design Specifications For Mizan Group (4).docx
│   └── Software Project Management plan (SPMP V2.0).docx
├── pages/                         # Core authenticated page views
│   ├── files.php                  # Project vault documents explorer
│   ├── invoices.php               # Invoice & receipt archive
│   ├── project-detail.php         # Single-project view, finances, and attachments
│   ├── projects.php               # Projects gallery & creation modal
│   ├── reports.php                # Aggregate analytics, chart views & exports
│   ├── settings.php               # Profile, security, notifications & currency settings
│   ├── support.php                # User support ticket interface
│   └── warranties.php             # Warranty status timeline & tracker
├── tests/                         # Test suites & automation scripts
│   ├── admin/                     # Admin dashboard Playwright specs
│   ├── auth/                      # Login, register, and password-reset specs
│   ├── core/                      # Navigation and user dashboard specs
│   ├── e2e/                       # End-to-end user workflows
│   ├── features/                  # Vault, expense, invoice, and warranty specs
│   ├── public/                    # Welcome landing page specs
│   ├── academic-stubs.spec.ts     # Verification suite for academic test plans
│   └── test_runner.php            # Instant PHP CLI smoke test runner
├── uploads/                       # Vault attachment storage directory (auto-created)
├── views/
│   └── home.php                   # Partial dashboard views
├── .gitignore                     # Git exclusion rules
├── .htaccess                      # Apache security headers & rewrite rules
├── database.sql                   # MySQL database schema and initial setup
├── forgot-password.php            # Password reset request flow
├── index.php                      # Main authenticated dashboard router
├── login.php                      # User sign-in interface
├── logout.php                     # Secure session termination
├── package.json                   # Node.js dependencies for Playwright testing
├── playwright.config.js           # Playwright automation configuration
├── README.md                      # Primary project documentation (Markdown)
├── register.php                   # New user registration interface
├── reset-password.php             # Token-verified password reset form
├── share.php                      # Public read-only project sharing view
└── welcome.php                    # Marketing landing page
```

---

## 🔒 Security Architecture

Mizan implements defense-in-depth across the entire application lifecycle:

1. **SQL Injection Defense:** All queries utilize native PHP PDO prepared statements with `$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false)`. Dynamic SQL concatenation is strictly forbidden.
2. **CSRF Protection:** State-altering operations (`POST`, `PUT`, `DELETE`) require a cryptographically secure token (`mz_generate_csrf_token()`) validated via `mz_validate_csrf()`.
3. **Brute-Force & Rate Limiting:** Login attempts are monitored by client IP address in the `login_attempts` table. Exceeding threshold limits enforces a cooldown lock.
4. **Session Hardening:** 
   - Sessions are regenerated (`session_regenerate_id(true)`) upon privilege transitions and logins.
   - Cookies enforce `HttpOnly`, `SameSite=Lax`, and `Secure` (over TLS).
5. **Defensive HTTP Headers:** Injected via both `includes/security.php` and `.htaccess`:
   - `Content-Security-Policy (CSP)`: Whitelists trusted resources while preventing unauthorised script injection.
   - `X-Frame-Options: DENY`: Blocks clickjacking attacks.
   - `X-Content-Type-Options: nosniff`: Prevents MIME-confusion attacks.
   - `Referrer-Policy: strict-origin-when-cross-origin`.
   - `Permissions-Policy`: Disables unused device hardware (geolocation, camera, microphone).
6. **Strict Upload Sandbox:**
   - Files are validated against a strict MIME-type and extension whitelist.
   - Dangerous extensions (`.php`, `.phtml`, `.exe`, `.sh`, `.bat`, etc.) are unconditionally rejected.
   - Uploaded files are renamed using SHA-256 / random hex hashes before disk writes to prevent path traversal.
7. **XSS Mitigation:** All user-controlled text rendered into the DOM is escaped using `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`.

---

## 📋 Prerequisites

Ensure your environment meets the following requirements:

- **PHP:** Version 8.1 or higher (PHP 8.2+ recommended)
  - Required Extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `gd`, `openssl`
- **Database:** MySQL 8.0+ or MariaDB 10.4+
- **Web Server:** Apache 2.4 (with `mod_rewrite`) or PHP Built-in Server for testing
- **Node.js (Optional, for Playwright tests):** Version 18.x or 20.x LTS

---

## ⚡ Quick Start & Local Setup

### 1. Clone Repository

```bash
git clone https://github.com/your-username/mizan.git
cd mizan
```

### 2. Database Setup & Migration

1. Ensure your MySQL server is running (e.g., via XAMPP, Docker, or native service).
2. Connect to MySQL and create the database:
   ```sql
   CREATE DATABASE mizan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the initial schema and tables from `database.sql`:
   ```bash
   # Using MySQL CLI:
   mysql -u root -p mizan_db < database.sql
   ```

*(Alternatively, import `database.sql` through phpMyAdmin or MySQL Workbench).*

### 3. Environment Configuration

Copy the example environment configuration:

```bash
# Windows (PowerShell / Command Prompt)
copy config\.env.example config\.env

# Linux / macOS
cp config/.env.example config/.env
```

Open `config/.env` in your text editor and supply your local database credentials:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=mizan_db
DB_USER=root
DB_PASS=your_password_here
APP_URL=http://localhost:8000
```

> **Note:** If your MySQL service runs on a custom port (such as `3307`), update `DB_PORT` accordingly.

### 4. Launching the Web Server

#### Option A: PHP Built-in Development Server (Fastest)

Run the PHP development server directly from the project root:

```bash
php -S 127.0.0.1:8000
```

Open your browser and navigate to:
```text
http://127.0.0.1:8000/welcome.php
```

#### Option B: Apache / XAMPP

1. Place or link the `mizan` repository inside your web root (e.g., `C:\xampp\htdocs\mizan` or `/var/www/html/mizan`).
2. Verify Apache and MySQL services are running in your XAMPP Control Panel.
3. Open your browser and navigate to:
   ```text
   http://localhost/mizan/welcome.php
   ```

---

## ⚙️ Configuration Reference (`.env`)

| Variable | Type | Default | Description |
|---|---|---|---|
| `DB_HOST` | String | `127.0.0.1` | Hostname or IP address of the MySQL/MariaDB server. |
| `DB_PORT` | Integer | `3306` | Port number of the MySQL server (e.g., `3306` or `3307`). |
| `DB_NAME` | String | `mizan_db` | Name of the database schema. |
| `DB_USER` | String | `root` | Database username with read/write permissions. |
| `DB_PASS` | String | *(empty)* | Database user password. |
| `APP_URL` | String | `http://localhost:8000` | Canonical application base URL used in generated email links and share tokens. |

---

## 🗄️ Database Schema & Models

The system architecture utilizes 13 primary relational tables:

```
┌─────────────┐       1:N       ┌──────────────┐       1:N       ┌──────────────┐
│    users    ├────────────────►│   projects   ├────────────────►│   expenses   │
└──────┬──────┘                 └──────┬───────┘                 └──────┬───────┘
       │                               │                                │
       │ 1:N                           │ 1:N                            │ 1:N
       ├──────────────► activity_logs  ├──────────────► files           ├──────► warranties
       ├──────────────► notifications  └──────────────► share_tokens    └──────► invoices
       ├──────────────► support_tickets
       └──────────────► password_resets
```

| Table | Purpose |
|---|---|
| `users` | Accounts, hashed credentials (BCrypt/Argon2), role flags (`is_admin`), interface preferences. |
| `projects` | Projects with budget ceiling, nature (`personal`/`commercial`), selling price, and color tags. |
| `expenses` | Itemized expenditures linked to projects with vendor, category, and date. |
| `warranties` | Hardware & item warranties with expiry dates, status, and receipt attachment links. |
| `invoices` | Scanned receipts, billings, and proof of purchase documents. |
| `files` | High-capacity digital vault files classified by `file_kind` (*invoice, warranty, contract, other*). |
| `notifications` | System alerts, budget warnings, and warranty expiration reminders. |
| `password_resets` | Cryptographic single-use password recovery tokens with expiry deadlines. |
| `login_attempts` | IP-based request ledger for brute-force rate limiting and temporary lockouts. |
| `project_share_tokens`| Cryptographic 64-character tokens for 30-day read-only project sharing. |
| `activity_logs` | Immutable audit trail of authenticated user actions, IPs, and timestamps. |
| `support_tickets` | User help desk inquiries, resolution status, and administrative replies. |
| `announcements` | Global banner announcements broadcasted by administrators. |

---

## 🔌 API Endpoints Reference

All endpoints under `/api/` accept session cookies and require valid CSRF tokens for state mutations.

| Endpoint | Method | Action Parameter | Description |
|---|---|---|---|
| `/api/projects.php` | `GET`, `POST` | `list`, `create`, `update`, `delete`, `archive` | Manage projects and fetch project metrics. |
| `/api/expenses.php` | `GET`, `POST` | `list`, `add`, `update`, `delete` | Manage itemized expenses and recalculate budget totals. |
| `/api/upload.php` | `POST` | — | Upload attachments (up to 500 MB) into the project vault. |
| `/api/notifications.php`| `GET`, `POST` | `list`, `mark_read`, `mark_all_read` | Poll user notifications and update read states. |
| `/api/reports.php` | `GET` | `summary`, `export_csv` | Fetch aggregate data and stream CSV accounting reports. |
| `/api/export_zip.php` | `GET` | `project_id` | Stream on-the-fly ZIP archive containing project documents & logs. |
| `/api/settings.php` | `POST` | `profile`, `password`, `currency`, `theme`, `lang` | Update user account settings and interface preferences. |
| `/api/support.php` | `POST` | `create`, `list` | Submit inquiries to the support desk. |
| `/api/admin_actions.php`| `POST` | `delete_user`, `toggle_admin`, `reply_ticket`, `announcement` | Superadmin operations and system maintenance. |

---

## 🧪 Testing & Quality Assurance

Mizan includes comprehensive multi-tier testing suites covering unit smoke tests, security checks, and end-to-end browser automation.

### Smoke Tests (PHP CLI)

A zero-dependency PHP smoke test runner validates database connectivity, table integrity, session handling, file presence, and syntax linting across all codebase files:

```bash
php tests/test_runner.php
```

Sample output:
```text
=== Mizan Smoke Tests ===
--- Database ---
[PASS] DB Connection — PDO connected to mizan_db
[PASS] Table: users — exists
[PASS] Table: projects — exists
[PASS] Table: expenses — exists
...
--- PHP Lint ---
[PASS] Lint: config/db.php
[PASS] Lint: api/projects.php
...
=== Summary ===
Passed: 54  |  Failed: 0  |  Total: 54
ALL TESTS PASSED
```

### Browser & E2E Testing (Playwright)

End-to-end workflows are validated using Playwright across Chromium, Firefox, and WebKit:

1. Install Node.js dependencies:
   ```bash
   npm install
   ```

2. Run the full test suite:
   ```bash
   npx playwright test
   ```

3. Run targeted test suites:
   ```bash
   # Authentication flow tests
   npx playwright test tests/auth/

   # Features (Vault, Expenses, Warranties)
   npx playwright test tests/features/

   # Academic requirement verification
   npx playwright test tests/academic-stubs.spec.ts
   ```

4. View test reports:
   ```bash
   npx playwright show-report
   ```

---

## 📚 Academic & Engineering Documentation

This repository represents the production engineering artifact for course **BCS 202** at **Imam Abdulrahman Bin Faisal University (IAU)**. Complete specification documentation is organized in `mizan_academic_docs/` and `docs/`:

- **Software Requirements Specification (SRS):** [`mizan_academic_docs/Mizan_SRS_v2.0.docx`](file:///C:/Users/misha/Desktop/mizan/mizan_academic_docs/Mizan_SRS_v2.0.docx)
- **Software Design Specifications (SDS):** [`mizan_academic_docs/SDS -Software Design Specifications For Mizan Group (4).docx`](file:///C:/Users/misha/Desktop/mizan/mizan_academic_docs/SDS%20-Software%20Design%20Specifications%20For%20Mizan%20Group%20(4).docx)
- **Software Project Management Plan (SPMP):** [`mizan_academic_docs/Software Project Management plan (SPMP V2.0).docx`](file:///C:/Users/misha/Desktop/mizan/mizan_academic_docs/Software%20Project%20Management%20plan%20(SPMP%20V2.0).docx)
- **Software Test Plan (STP / STS):** [`mizan_academic_docs/6a -Software test plan (group 4)  .docx`](file:///C:/Users/misha/Desktop/mizan/mizan_academic_docs/6a%20-Software%20test%20plan%20(group%204)%20%20.docx) & [`docs/STS_Test_Plan.md`](file:///C:/Users/misha/Desktop/mizan/docs/STS_Test_Plan.md)
- **User Manual & Workflow Guide:** [`docs/User_Manual_Outline.md`](file:///C:/Users/misha/Desktop/mizan/docs/User_Manual_Outline.md)

---

## 👥 Authors & Acknowledgments

- **Lead Developer & Scrum Master:** Mishal Al-jumaih (مشعل الجميعه)
- **Academic Program:** College of Computer Science and Information Technology (CCSIT), Imam Abdulrahman Bin Faisal University (IAU).
- **Course:** BCS 202 — Software Engineering Capstone / Project.

---

<p align="center">
  <sub>Developed with pride for academic excellence and practical engineering · Mizan Platform v4.5</sub>
</p>
