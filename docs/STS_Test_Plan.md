# Mizan — System Test Specification (STS) Draft
**Version:** 1.0  
**Date:** 2026-05-01  
**System:** Mizan Personal Finance Manager  

---

## 1. Scope

This document covers black-box and grey-box system test cases for the Mizan web application, focusing on security, file handling, and session integrity.

---

## 2. Test Environment

| Item | Detail |
|------|--------|
| Server | XAMPP / Apache 2.4 + PHP 8.x |
| Database | MariaDB / MySQL |
| Browser | Chrome 124+, Firefox 125+ |
| Test user | Dedicated test account (non-admin) |

---

## 3. Test Cases

### 3.1 File Upload — 500 MB Limit

| Field | Value |
|-------|-------|
| **TC-ID** | TC-UPLOAD-001 |
| **Title** | File upload rejected above 500 MB |
| **Preconditions** | Authenticated user; project exists |
| **Steps** | 1. Navigate to Project → Vault tab. 2. Select a file larger than 500 MB. 3. Click Upload. |
| **Expected Result** | Server returns HTTP 400 / JSON `{ "success": false, "error": "حجم الملف تجاوز الحد الأقصى (500MB)" }`. No file is stored on disk. |
| **Pass Criteria** | Error message displayed; upload directory unchanged. |

| Field | Value |
|-------|-------|
| **TC-ID** | TC-UPLOAD-002 |
| **Title** | File upload accepted at exactly 500 MB |
| **Preconditions** | Authenticated user; project exists |
| **Steps** | 1. Create a 500 MB test file. 2. Upload it via the Vault tab. |
| **Expected Result** | File is stored successfully; `files` table updated. |
| **Pass Criteria** | HTTP 200; `data.path` returned; file present on disk. |

| Field | Value |
|-------|-------|
| **TC-ID** | TC-UPLOAD-003 |
| **Title** | Dangerous file extension rejected |
| **Preconditions** | Authenticated user |
| **Steps** | 1. Attempt to upload `malware.php`. |
| **Expected Result** | HTTP 400; `"اسم الملف يحتوي امتداداً خطيراً"`. |
| **Pass Criteria** | File not written to disk. |

---

### 3.2 XSS Prevention — Project Names

| Field | Value |
|-------|-------|
| **TC-ID** | TC-XSS-001 |
| **Title** | Script tag in project name is neutralised on display |
| **Preconditions** | Authenticated user |
| **Steps** | 1. Create a project with name `<script>alert('xss')</script>`. 2. View the Projects list page. 3. Open Project Detail. 4. Open the public share link. |
| **Expected Result** | Project name is rendered as literal text in all three views; no alert fires. |
| **Pass Criteria** | Browser devtools show `&lt;script&gt;…` in the DOM; no JS execution. |

| Field | Value |
|-------|-------|
| **TC-ID** | TC-XSS-002 |
| **Title** | XSS payload in expense title does not execute |
| **Preconditions** | Authenticated user; project exists |
| **Steps** | 1. Add an expense with title `"><img src=x onerror=alert(1)>`. 2. View the expense list on the detail page and on the share page. |
| **Expected Result** | Title rendered as escaped text; `onerror` never fires. |
| **Pass Criteria** | No JS alert; HTML source shows entity-encoded output. |

| Field | Value |
|-------|-------|
| **TC-ID** | TC-XSS-003 |
| **Title** | Search results do not reflect unsanitised input |
| **Preconditions** | Authenticated user |
| **Steps** | 1. Type `<svg onload=alert(1)>` into the project search box. |
| **Expected Result** | Search results rendered via `textContent` / DOM creation; no JS execution. |
| **Pass Criteria** | No alert fires; source shows text node, not raw HTML. |

---

### 3.3 Session Fixation Prevention

| Field | Value |
|-------|-------|
| **TC-ID** | TC-SESS-001 |
| **Title** | Session ID is regenerated after successful login |
| **Preconditions** | Fresh browser; no active session |
| **Steps** | 1. Record the `PHPSESSID` cookie value before logging in. 2. Submit valid credentials. 3. Record `PHPSESSID` after login. |
| **Expected Result** | The session ID after login differs from the pre-login ID. |
| **Pass Criteria** | `session_id_before !== session_id_after`. |

| Field | Value |
|-------|-------|
| **TC-ID** | TC-SESS-002 |
| **Title** | Injected pre-login session ID is not elevated |
| **Preconditions** | Attacker-known session ID |
| **Steps** | 1. Set `PHPSESSID` cookie to a known value before login. 2. Log in with valid credentials. 3. Check if the same `PHPSESSID` now has an authenticated session. |
| **Expected Result** | `session_regenerate_id(true)` invalidates the old session; attacker's session ID no longer works. |
| **Pass Criteria** | Using the pre-login session ID returns HTTP 401 / redirect to login. |

| Field | Value |
|-------|-------|
| **TC-ID** | TC-SESS-003 |
| **Title** | Session is fully destroyed on logout |
| **Preconditions** | Authenticated user |
| **Steps** | 1. Copy the `PHPSESSID` value. 2. Click Logout. 3. Replay a protected API request using the old cookie. |
| **Expected Result** | Server returns HTTP 401; session data is absent. |
| **Pass Criteria** | `session_destroy()` called; no residual session data. |

---

## 4. Traceability Matrix

| Requirement | Test Case(s) |
|-------------|-------------|
| File size limit 500 MB | TC-UPLOAD-001, TC-UPLOAD-002 |
| Dangerous extension blocking | TC-UPLOAD-003 |
| XSS prevention (output encoding) | TC-XSS-001, TC-XSS-002, TC-XSS-003 |
| Session fixation mitigation | TC-SESS-001, TC-SESS-002, TC-SESS-003 |

---

*End of STS Test Plan Draft*
