<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: smart_upload_modal.php
 * PURPOSE: Reusable smart-upload modal markup with drag-and-drop support.
 * OWNER: Abdullah Radhi - Development Member, DB Admin & UI/UX Lead
 * ========================================================================
 */
// ============================================================
// Mizan ميزان — includes/smart_upload_modal.php
// ------------------------------------------------------------
// Single, shared "Smart Upload" modal. Included once globally
// (header.php). All pages call openSmartUpload(opts) to launch
// it and let api/upload.php handle persistence.
//
// Dynamic fields:
//   invoice  → invoice_name + category
//   warranty → purchase_date + expiry_date
//   contract → contract_title + signed_date
//   other    → custom_label
// ============================================================

// Lazily acquire a PDO if the including page didn't already provide one
if (!isset($pdo)) { require_once __DIR__ . '/../config/db.php'; $pdo = getDB(); }
// Anonymous visitors never get the upload modal — silently return without emitting markup
if (!isset($current_user_id)) { return; }

$su_projects_stmt = $pdo->prepare("SELECT id, name FROM projects WHERE user_id = ? ORDER BY name");
$su_projects_stmt->execute([$current_user_id]);
$su_projects = $su_projects_stmt->fetchAll();
?>

<!-- Smart Upload Modal — single shared instance included once globally via footer.php.
     Any page calls window.openSmartUpload({...}) to launch this and POSTs to api/upload.php. -->
<div class="modal" id="smartUploadModal">
    <div class="modal-overlay" onclick="closeSmartUpload()"></div>
    <div class="modal-box">
        <div class="modal-header">
            <h3>⬆️ <span data-i18n="smart_upload">رفع ذكي</span></h3>
            <button class="modal-close" onclick="closeSmartUpload()">✕</button>
        </div>
        <div class="modal-body">
            <!-- Type selector -->
            <div class="form-group">
                <label data-i18n="upload_type">نوع المستند</label>
                <div class="su-type-grid">
                    <button type="button" class="su-type-btn active" data-type="invoice"
                        onclick="setSmartUploadType('invoice')">🧾 <span
                            data-i18n="file_type_invoice">فاتورة</span></button>
                    <button type="button" class="su-type-btn" data-type="warranty"
                        onclick="setSmartUploadType('warranty')">🛡️ <span
                            data-i18n="file_type_warranty">ضمان</span></button>
                    <button type="button" class="su-type-btn" data-type="contract"
                        onclick="setSmartUploadType('contract')">📑 <span
                            data-i18n="file_type_contract">عقد</span></button>
                    <button type="button" class="su-type-btn" data-type="other" onclick="setSmartUploadType('other')">📦
                        <span data-i18n="file_type_other">أخرى</span></button>
                </div>
            </div>

            <!-- Project + expense -->
            <div class="form-group">
                <label data-i18n="th_project">المشروع</label>
                <select id="suProject" onchange="suLoadExpenses(this.value)">
                    <option value="" data-i18n="select_project">اختر مشروعاً</option>
                    <?php foreach ($su_projects as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="suExpenseGroup">
                <label data-i18n="expense_optional">العملية (اختياري)</label>
                <select id="suExpense">
                    <option value="" data-i18n="no_expense_link">بدون ربط بعملية معينة</option>
                </select>
            </div>

            <!-- Dynamic fields per type -->
            <div class="su-dynamic" id="suFieldsInvoice">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label data-i18n="invoice_name">اسم الفاتورة</label>
                        <input type="text" id="suInvoiceName" data-i18n-placeholder="invoice_name_ph"
                            placeholder="مثال: فاتورة كهرباء">
                    </div>
                    <div class="form-group">
                        <label data-i18n="category">التصنيف</label>
                        <input type="text" id="suInvoiceCategory" data-i18n-placeholder="category_ph"
                            placeholder="مثال: مرافق">
                    </div>
                </div>
                <div class="form-group">
                    <label data-i18n="amount_label">المبلغ</label>
                    <input type="number" step="0.01" min="0" id="suInvoicePrice" placeholder="0.00">
                </div>
            </div>
            <div class="su-dynamic" id="suFieldsWarranty" style="display:none;">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label data-i18n="purchase_date">تاريخ الشراء</label>
                        <input type="date" id="suPurchaseDate" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label data-i18n="expiry_date">تاريخ الانتهاء</label>
                        <input type="date" id="suExpiryDate" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label data-i18n="amount_label">المبلغ</label>
                    <input type="number" step="0.01" min="0" id="suWarrantyPrice" placeholder="0.00">
                </div>
            </div>
            <div class="su-dynamic" id="suFieldsContract" style="display:none;">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label data-i18n="contract_title">عنوان العقد</label>
                        <input type="text" id="suContractTitle" data-i18n-placeholder="contract_title_ph"
                            placeholder="مثال: عقد إيجار">
                    </div>
                    <div class="form-group">
                        <label data-i18n="signed_date">تاريخ التوقيع</label>
                        <input type="date" id="suSignedDate" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            <div class="su-dynamic" id="suFieldsOther" style="display:none;">
                <div class="form-group">
                    <label data-i18n="custom_label">وصف الملف</label>
                    <input type="text" id="suCustomLabel" data-i18n-placeholder="custom_label_ph"
                        placeholder="مثال: شهادة ضمان مصنع">
                </div>
            </div>

            <!-- Drop zone -->
            <div class="form-group">
                <label data-i18n="choose_file">اختر الملف</label>
                <div class="drop-zone" id="suDropZone"
                    ondragover="event.preventDefault();this.classList.add('drag-over')"
                    ondragleave="this.classList.remove('drag-over')" ondrop="suHandleDrop(event)">
                    <div class="dz-icon">📁</div>
                    <div class="dz-text" data-i18n="drag_file_here">اسحب الملف هنا أو</div>
                    <label class="btn btn-secondary" for="suFileInput" style="cursor:pointer;"
                        data-i18n="choose_a_file">اختر ملفاً</label>
                    <input type="file" id="suFileInput" hidden onchange="suHandleSelect(this)">
                    <div class="dz-hint" data-i18n="file_formats_hint">صور — PDF — Word — Excel — ZIP — حتى 500 MB</div>
                </div>
                <div class="upload-preview" id="suPreview" style="display:none;"></div>
            </div>

            <div class="upload-progress" id="suProgress" style="display:none;">
                <div class="up-bar">
                    <div class="up-fill" id="suFill"></div>
                </div>
                <div class="up-text" id="suText" data-i18n="uploading">جاري الرفع...</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeSmartUpload()" data-i18n="cancel">إلغاء</button>
            <button class="btn btn-primary" id="suSubmitBtn" onclick="submitSmartUpload()">⬆️ <span
                    data-i18n="upload_file">رفع الملف</span></button>
        </div>
    </div>
</div>

<style>
.su-type-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

.su-type-btn {
    padding: 12px 6px;
    border-radius: 10px;
    border: 1.5px solid var(--bdr);
    background: var(--surf);
    color: var(--txt);
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all .2s;
}

.su-type-btn:hover {
    border-color: var(--g);
    transform: translateY(-1px);
}

.su-type-btn.active {
    background: var(--g);
    color: #fff;
    border-color: var(--g);
    box-shadow: 0 4px 14px rgba(0, 108, 53, .25);
}

.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

@media (max-width: 600px) {
    .su-type-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .form-grid-2 {
        grid-template-columns: 1fr;
    }
}
</style>