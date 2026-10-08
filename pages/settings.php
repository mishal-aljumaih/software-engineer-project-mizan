<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: settings.php
 * PURPOSE: User profile, preferences, currency, and password settings UI.
 * OWNER: Alwaleed Alzahrani - Development & Database Admin (Lead Programmer)
 * ========================================================================
 */
require_once '../includes/auth.php';
require_once '../config/db.php';

$pdo = getDB();

// Load profile + preferences for the logged-in user only (auth.php has already populated $current_user_id)
$stmt = $pdo->prepare("SELECT id, name, email, currency, theme, language, notif_email, notif_warranty, notif_budget FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$user = $stmt->fetch();

// Defensive: if the session references a deleted user, push them back to register
if (!$user) {
    header("Location: /register.php");
    exit;
}

$page_title = 'الإعدادات';
require_once '../includes/header.php';
// ─────────────────────────────────────────────────────────────────────────
// END PHP BOOTSTRAP — BEGIN HTML VIEW (profile / security / preferences cards)
// ─────────────────────────────────────────────────────────────────────────
?>

<!-- ===== Settings Page ===== -->
<div class="page-header reveal">
    <h1 class="page-title" data-i18n="settings">الإعدادات</h1>
    <p class="text-muted" data-i18n="settings_desc">إدارة حسابك وتفضيلاتك</p>
</div>

<div class="settings-grid">

    <!-- ===== 1. Profile ===== -->
    <div class="glass-card reveal" id="profileCard">
        <div class="card-header">
            <h3 class="card-title">👤 <span data-i18n="profile">الملف الشخصي</span></h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" data-i18n="full_name">الاسم الكامل</label>
                <input type="text" class="form-input" id="settingsName" value="<?= htmlspecialchars($user['name']) ?>"
                    maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label" data-i18n="email">البريد الإلكتروني</label>
                <input type="email" class="form-input" id="settingsEmail"
                    value="<?= htmlspecialchars($user['email']) ?>" maxlength="150">
            </div>
            <!-- Save Profile button — POSTs name/email to /api/settings.php (action=update_profile) -->
            <button class="btn btn-primary" onclick="saveProfile()" data-i18n="save_changes">حفظ التغييرات</button>
        </div>
    </div>

    <!-- ===== 2. Security ===== -->
    <div class="glass-card reveal" id="securityCard">
        <div class="card-header">
            <h3 class="card-title">🔒 <span data-i18n="security">الأمان</span></h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" data-i18n="current_password">كلمة المرور الحالية</label>
                <input type="password" class="form-input" id="currentPassword" autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="form-label" data-i18n="new_password">كلمة المرور الجديدة</label>
                <input type="password" class="form-input" id="newPassword" autocomplete="new-password"
                    oninput="updateStrength()">
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <span class="strength-label" id="strengthLabel"></span>
            </div>
            <div class="form-group">
                <label class="form-label" data-i18n="confirm_new_password">تأكيد كلمة المرور</label>
                <input type="password" class="form-input" id="confirmPassword" autocomplete="new-password">
            </div>
            <button class="btn btn-primary" onclick="changePassword()" data-i18n="change_password">تغيير كلمة
                المرور</button>
        </div>
    </div>

    <!-- ===== 3. Preferences ===== -->
    <div class="glass-card reveal" id="preferencesCard">
        <div class="card-header">
            <h3 class="card-title">🎨 <span data-i18n="preferences">التفضيلات</span></h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" data-i18n="currency_label">العملة</label>
                <select class="form-input" id="settingsCurrency">
                    <option value="SAR" <?= $user['currency'] === 'SAR' ? 'selected' : '' ?>>🇸🇦 ريال سعودي (SAR)</option>
                    <option value="USD" <?= $user['currency'] === 'USD' ? 'selected' : '' ?>>🇺🇸 دولار أمريكي (USD)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" data-i18n="theme_label">المظهر</label>
                <div class="toggle-group">
                    <button class="toggle-btn <?= ($user['theme'] ?? 'dark') === 'light' ? 'active' : '' ?>"
                        data-value="light" onclick="selectToggle(this, 'theme')">
                        ☀️ <span data-i18n="light_mode">فاتح</span>
                    </button>
                    <button class="toggle-btn <?= ($user['theme'] ?? 'dark') === 'dark' ? 'active' : '' ?>"
                        data-value="dark" onclick="selectToggle(this, 'theme')">
                        🌙 <span data-i18n="dark_mode">داكن</span>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" data-i18n="language_label">اللغة</label>
                <div class="toggle-group">
                    <button class="toggle-btn <?= ($user['language'] ?? 'ar') === 'ar' ? 'active' : '' ?>"
                        data-value="ar" onclick="selectToggle(this, 'lang')">
                        🇸🇦 العربية
                    </button>
                    <button class="toggle-btn <?= ($user['language'] ?? 'ar') === 'en' ? 'active' : '' ?>"
                        data-value="en" onclick="selectToggle(this, 'lang')">
                        🇬🇧 English
                    </button>
                </div>
            </div>
            <button class="btn btn-primary" onclick="savePreferences()" data-i18n="save_preferences">حفظ
                التفضيلات</button>
        </div>
    </div>

    <!-- ===== 4. Notifications ===== -->
    <div class="glass-card reveal" id="notificationsCard">
        <div class="card-header">
            <h3 class="card-title">🔔 <span data-i18n="notification_settings">إعدادات التنبيهات</span></h3>
        </div>
        <div class="card-body">
            <div class="switch-row">
                <div class="switch-info">
                    <span class="switch-label" data-i18n="email_notifications">تنبيهات البريد الإلكتروني</span>
                    <span class="switch-desc text-muted" data-i18n="email_notif_desc">إرسال التنبيهات المهمة عبر
                        البريد</span>
                </div>
                <label class="switch">
                    <input type="checkbox" id="notifEmail" <?= $user['notif_email'] ? 'checked' : '' ?>>
                    <span class="switch-slider"></span>
                </label>
            </div>
            <div class="switch-row">
                <div class="switch-info">
                    <span class="switch-label" data-i18n="warranty_notifications">تنبيهات الضمانات</span>
                    <span class="switch-desc text-muted" data-i18n="warranty_notif_desc">تنبيه عند اقتراب انتهاء
                        الضمان</span>
                </div>
                <label class="switch">
                    <input type="checkbox" id="notifWarranty" <?= $user['notif_warranty'] ? 'checked' : '' ?>>
                    <span class="switch-slider"></span>
                </label>
            </div>
            <div class="switch-row">
                <div class="switch-info">
                    <span class="switch-label" data-i18n="budget_notifications">تنبيهات الميزانية</span>
                    <span class="switch-desc text-muted" data-i18n="budget_notif_desc">تنبيه عند تجاوز ميزانية
                        المشروع</span>
                </div>
                <label class="switch">
                    <input type="checkbox" id="notifBudget" <?= $user['notif_budget'] ? 'checked' : '' ?>>
                    <span class="switch-slider"></span>
                </label>
            </div>
            <button class="btn btn-primary mt-3" onclick="savePreferences()" data-i18n="save_preferences">حفظ التفضيلات</button>

        </div>
    </div>

    <!-- ===== 5. Danger Zone ===== -->
    <div class="glass-card reveal danger-zone" id="dangerCard">
        <div class="card-header">
            <h3 class="card-title danger-title">⚠️ <span data-i18n="danger_zone">منطقة الخطر</span></h3>
        </div>
        <div class="card-body">
            <div class="danger-row">
                <div>
                    <strong data-i18n="export_data">تصدير البيانات</strong>
                    <p class="text-muted" data-i18n="export_desc">تحميل جميع بياناتك بصيغة JSON</p>
                </div>
                <button class="btn btn-ghost" onclick="exportData()" data-i18n="export">تصدير</button>
            </div>
            <div class="danger-row">
                <div>
                    <strong data-i18n="delete_account">حذف الحساب</strong>
                    <p class="text-muted" data-i18n="delete_account_desc">حذف حسابك وجميع بياناتك نهائياً</p>
                </div>
                <button class="btn btn-danger" onclick="deleteAccount()" data-i18n="delete">حذف</button>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== Settings Layout ===== */
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
    gap: 24px;
    margin-top: 20px;
    align-items: start;
}

@media (max-width: 500px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }
}

/* ===== Glass Card ===== */
.glass-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    overflow: hidden;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.glass-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
}

.card-header {
    padding: 20px 24px 0;
}

.card-title {
    font-size: 17px;
    font-weight: 700;
    margin: 0;
}

.card-body {
    padding: 16px 24px 24px;
}

/* ===== Form Styling ===== */
.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.form-input {
    width: 100%;
    padding: 10px 14px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: var(--border-focus);
    box-shadow: 0 0 0 3px rgba(3, 105, 161, 0.1);
}

/* ===== Password Strength ===== */
.password-strength {
    height: 4px;
    background: var(--surface2);
    border-radius: 2px;
    margin-top: 8px;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    width: 0;
    border-radius: 2px;
    transition: width 0.3s, background 0.3s;
}

.strength-label {
    font-size: 12px;
    margin-top: 4px;
    display: block;
    color: var(--text-muted);
}

/* ===== Toggle Group ===== */
.toggle-group {
    display: flex;
    gap: 8px;
}

.toggle-btn {
    flex: 1;
    padding: 10px 16px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    font-size: 14px;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s;
}

.toggle-btn:hover {
    border-color: var(--primary);
}

.toggle-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
    font-weight: 600;
}

/* ===== Switch / Toggle ===== */
.switch-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid var(--border);
}

.switch-row:last-of-type {
    border-bottom: none;
}

.switch-info {
    flex: 1;
}

.switch-label {
    font-weight: 600;
    font-size: 14px;
    display: block;
}

.switch-desc {
    font-size: 12px;
    margin-top: 2px;
    display: block;
}

.switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
    flex-shrink: 0;
    margin-inline-start: 16px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.switch-slider {
    position: absolute;
    inset: 0;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 26px;
    cursor: pointer;
    transition: all 0.3s;
}

.switch-slider::before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    /* Toggle knob — uses --surface so it harmonizes with the layered
       light theme instead of standing out as pure white. */
    background: var(--surface);
    top: 2px;
    inset-inline-start: 3px;
    transition: all 0.3s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}

.switch input:checked+.switch-slider {
    background: var(--primary);
    border-color: var(--primary);
}

.switch input:checked+.switch-slider::before {
    inset-inline-start: calc(100% - 23px);
}

/* ===== Danger Zone ===== */
.danger-zone {
    border-color: rgba(239, 68, 68, 0.3);
}

.danger-zone:hover {
    border-color: var(--danger);
    box-shadow: 0 4px 24px rgba(239, 68, 68, 0.08);
}

.danger-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid var(--border);
    gap: 16px;
}

.danger-row:last-child {
    border-bottom: none;
}

.danger-row p {
    margin: 4px 0 0;
    font-size: 13px;
}

.danger-title {
    color: var(--danger);
}

.mt-3 {
    margin-top: 12px;
}
</style>

<script>
const API = '/api/settings.php';

// ===== Profile =====
function saveProfile() {
    const name = document.getElementById('settingsName').value.trim();
    const email = document.getElementById('settingsEmail').value.trim();
    if (!name || !email) return showToast(t('fill_all_fields'), 'error');

    mizanFetch(API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update_profile',
                name,
                email
            })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(t(d.message) || d.message, 'success');
                const nav = document.getElementById('userNameNav');
                if (nav) nav.textContent = name;
                const av = document.getElementById('userAvatar');
                if (av) av.textContent = name.charAt(0);
            } else {
                showToast(t(d.error) || t('generic_error'), 'error');
            }
        })
        .catch(() => showToast(t('server_error'), 'error'));
}

// ===== Password =====
function changePassword() {
    const current = document.getElementById('currentPassword').value;
    const pass = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;

    if (!current || !pass || !confirm) return showToast(t('fill_all_fields'), 'error');
    if (pass.length < 6) return showToast(t('pass_min_6'), 'error');
    if (pass !== confirm) return showToast(t('pass_mismatch'), 'error');

    mizanFetch(API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update_password',
                current_password: current,
                new_password: pass
            })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(t(d.message) || d.message, 'success');
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
                document.getElementById('strengthBar').style.width = '0';
                document.getElementById('strengthLabel').textContent = '';
            } else {
                showToast(t(d.error) || t('generic_error'), 'error');
            }
        })
        .catch(() => showToast(t('server_error'), 'error'));
}

// Updates the strength.
function updateStrength() {
    const pw = document.getElementById('newPassword').value;
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    let score = 0;
    if (pw.length >= 6) score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const levels = [{
            w: '0%',
            c: 'transparent',
            t: ''
        },
        {
            w: '20%',
            c: 'var(--danger)',
            t: 'ضعيفة جداً'
        },
        {
            w: '40%',
            c: 'var(--danger)',
            t: 'ضعيفة'
        },
        {
            w: '60%',
            c: 'var(--warning)',
            t: 'متوسطة'
        },
        {
            w: '80%',
            c: 'var(--primary)',
            t: 'قوية'
        },
        {
            w: '100%',
            c: 'var(--success)',
            t: 'قوية جداً'
        },
    ];
    const l = levels[score];
    bar.style.width = l.w;
    bar.style.background = l.c;
    lbl.textContent = l.t;
    lbl.style.color = l.c;
}

// ===== Toggle Buttons =====
function selectToggle(btn, group) {
    btn.parentElement.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    if (group === 'theme') {
        if (typeof applyTheme === 'function') {
            applyTheme(btn.dataset.value);
        } else {
            document.documentElement.setAttribute('data-theme', btn.dataset.value);
            localStorage.setItem('mizan_theme', btn.dataset.value);
        }
    }
    if (group === 'lang') {
        if (typeof applyLang === 'function') {
            applyLang(btn.dataset.value);
        } else {
            localStorage.setItem('mizan_lang', btn.dataset.value);
        }
    }
}

// ===== Preferences =====
function savePreferences() {
    const currency = document.getElementById('settingsCurrency').value;
    const theme = document.querySelector('#preferencesCard .toggle-btn.active[data-value]')?.dataset.value ||
        document.querySelector('[onclick*="selectToggle(this, \'theme\')"].active')?.dataset.value ||
        localStorage.getItem('mizan_theme') || 'dark';
    const language = document.querySelector('#preferencesCard .toggle-group:last-of-type .toggle-btn.active')?.dataset
        .value ||
        localStorage.getItem('mizan_lang') || 'ar';
    const notif_email = document.getElementById('notifEmail').checked ? 1 : 0;
    const notif_warranty = document.getElementById('notifWarranty').checked ? 1 : 0;
    const notif_budget = document.getElementById('notifBudget').checked ? 1 : 0;

    mizanFetch(API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update_preferences',
                currency,
                theme,
                language,
                notif_email,
                notif_warranty,
                notif_budget
            })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) showToast(t(d.message) || d.message, 'success');
            else showToast(t(d.error) || t('generic_error'), 'error');
        })
        .catch(() => showToast(t('server_error'), 'error'));
}

// ===== Export Data =====
function exportData() {
    Swal.fire({
        title: t('export_data_title'),
        text: t('export_data_msg'),
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: 'var(--primary)',
        confirmButtonText: t('export'),
        cancelButtonText: t('cancel'),
    }).then(result => {
        if (!result.isConfirmed) return;
        mizanFetch(API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'export_data'
                })
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) return showToast(t(d.error) || t('generic_error'), 'error');
                const blob = new Blob([JSON.stringify(d.data, null, 2)], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'mizan_export_' + new Date().toISOString().slice(0, 10) + '.json';
                a.click();
                URL.revokeObjectURL(url);
                showToast(t('export_success'), 'success');
            })
            .catch(() => showToast(t('export_failed'), 'error'));
    });
}

// ===== Delete Account =====
function deleteAccount() {
    Swal.fire({
        title: t('delete_account_confirm'),
        html: 'سيتم حذف جميع بياناتك بشكل <strong>نهائي</strong> ولا يمكن التراجع.<br><br>' + t('type_delete'),
        icon: 'warning',
        input: 'text',
        inputPlaceholder: 'حذف',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        confirmButtonText: t('delete_account'),
        cancelButtonText: t('cancel'),
        preConfirm: (val) => {
            if (val !== 'حذف') {
                Swal.showValidationMessage(t('must_type_delete'));
                return false;
            }
        }
    }).then(result => {
        if (!result.isConfirmed) return;
        mizanFetch(API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'delete_account'
                })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    Swal.fire({
                            title: t('account_deleted'),
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        })
                        .then(() => window.location.href = '/register.php');
                } else {
                    showToast(t(d.error) || t('generic_error'), 'error');
                }
            })
            .catch(() => showToast(t('delete_account_failed'), 'error'));
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>