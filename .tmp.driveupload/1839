/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: main.js
 * PURPOSE: Global frontend bootstrap: navigation, i18n, toasts, helpers.
 * OWNER: Abdullah Radhi - UI/UX & Frontend Lead
 * ========================================================================
 */
// ============================================================
// Mizan main frontend bundle — owns navigation, i18n, theme toggling,
// global search, toasts, project/expense modals, and helper fetch logic.
// Loaded on every authenticated page via includes/footer.php.
// ============================================================

// ============================================================
// 0. Performance utilities — throttle / debounce / rafThrottle
// ------------------------------------------------------------
// Centralised so any listener (scroll, resize, input, mousemove)
// can opt into rate-limited dispatch instead of running on every
// tick. raf-throttle is preferred for visual updates because it
// aligns with the browser's compositor frame.
// ============================================================
function throttle(fn, wait = 100) {
    let last = 0, timer = null;
    return function throttled(...args) {
        const now = Date.now();
        const remaining = wait - (now - last);
        if (remaining <= 0) {
            if (timer) { clearTimeout(timer); timer = null; }
            last = now;
            fn.apply(this, args);
        } else if (!timer) {
            timer = setTimeout(() => {
                last = Date.now();
                timer = null;
                fn.apply(this, args);
            }, remaining);
        }
    };
}

// Defines the debounce routine.
function debounce(fn, wait = 200) {
    let timer = null;
    return function debounced(...args) {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), wait);
    };
}

/** RAF-throttled callback for scroll / pointer-move / mouse-move. */
function rafThrottle(fn) {
    let queued = false, lastArgs = null;
    return function rafd(...args) {
        lastArgs = args;
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            fn.apply(this, lastArgs);
        });
    };
}

// Expose for inline-script callers in PHP pages.
window.mzThrottle    = throttle;
window.mzDebounce    = debounce;
window.mzRafThrottle = rafThrottle;


// ============================================================
// 0.1 CSRF Protection
// ============================================================
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

/**
 * Wrapper around fetch() that auto-injects the CSRF token header
 * for POST/PUT/DELETE requests.
 * Usage: mizanFetch(url, { method:'POST', body: formData })
 */
function mizanFetch(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    if (['POST','PUT','DELETE','PATCH'].includes(method)) {
        options.headers = options.headers || {};
        if (options.headers instanceof Headers) {
            options.headers.set('X-CSRF-Token', getCsrfToken());
        } else {
            options.headers['X-CSRF-Token'] = getCsrfToken();
        }
        // Let browser set Content-Type + boundary for FormData
        if (options.body instanceof FormData) {
            if (options.headers instanceof Headers) {
                options.headers.delete('Content-Type');
            } else {
                delete options.headers['Content-Type'];
            }
        }
    }
    return fetch(url, options);
}


// ============================================================
// 1. الوضع الداكن (Dark Mode)
// ============================================================
function initTheme() {
    const saved       = localStorage.getItem('mizan_theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(saved || (prefersDark ? 'dark' : 'light'));
}

// Applies the theme.
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('mizan_theme', theme);
    // Persist as a cookie too so PHP can render the right data-theme
    // on the very first paint (prevents FOUC across navigations).
    try { document.cookie = 'mizan_theme=' + theme + '; path=/; max-age=31536000; SameSite=Lax'; } catch (e) {}

    // حدّث كل أزرار الثيم في الصفحة
    document.querySelectorAll('#themeToggle').forEach(btn => {
        if (!btn) return;
        btn.textContent = theme === 'dark' ? '☀️' : '🌙';
        btn.title       = theme === 'dark' ? 'الوضع النهاري' : 'الوضع الليلي';
    });

    // Notify any chart / Swal / modal that wants to re-paint when the
    // theme flips. Chart.js doesn't auto-resolve CSS variables, so charts
    // that need theme-aware grid/tick colors should listen for this event
    // and rebuild their datasets via getComputedStyle().
    try {
        window.dispatchEvent(new CustomEvent('mizan:theme-change', { detail: { theme } }));
    } catch (e) { /* CustomEvent not supported — skip */ }
}

// Toggles the theme.
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    // إضافة فلاش لطيف
    document.body.classList.add('theme-switching');
    setTimeout(() => document.body.classList.remove('theme-switching'), 300);
    applyTheme(current === 'dark' ? 'light' : 'dark');
}


// ============================================================
// 2. اللغة (Arabic / English)
// ============================================================
const translations = {
    ar: {
        // Core
        app_name:'ميزان', app_subtitle:'أرشيفك المالي الشخصي',
        login_title:'تسجيل الدخول', register_title:'إنشاء حساب جديد',
        email_label:'البريد الإلكتروني', password_label:'كلمة المرور',
        confirm_pass_label:'تأكيد كلمة المرور', name_label:'الاسم الكامل',
        login_btn:'دخول', register_btn:'إنشاء الحساب',
        no_account:'ما عندك حساب؟', create_account:'أنشئ حساب',
        have_account:'عندك حساب؟', sign_in:'سجّل دخول',
        forgot_pass:'نسيت كلمة المرور',
        // Sidebar
        dashboard:'لوحة التحكم', projects:'المشاريع', invoices:'الفواتير',
        warranties:'الضمانات', files:'ملفاتي', reports:'التقارير',
        support:'الدعم', settings:'الإعدادات', logout:'تسجيل الخروج',
        // Dashboard
        add_project:'مشروع جديد', total_expenses:'إجمالي المصاريف',
        active_projects:'مشاريع نشطة', expiring_warranties:'ضمانات قاربت الانتهاء',
        total_profit:'الربح الإجمالي', notifications:'التنبيهات',
        expenses_chart:'المصاريف — آخر 6 أشهر',
        months_6:'6 أشهر', months_3:'3 أشهر', months_12:'12 شهر',
        expiring_soon:'ضمانات تنتهي قريباً', view_all:'عرض الكل',
        all_warranties_valid:'كل الضمانات سارية، لا توجد تنبيهات',
        recent_projects:'آخر المشاريع', no_chart_data:'لا توجد بيانات بعد. ابدأ بإضافة مصاريف!',
        start_first_project:'ابدأ بإنشاء أول مشروع لك!',
        th_project:'المشروع', th_type:'النوع', th_status:'الحالة',
        th_total_cost:'التكلفة الإجمالية', th_profit_loss:'الربح / الخسارة',
        th_date:'التاريخ', details:'تفاصيل ←',
        // Actions
        save:'حفظ', cancel:'إلغاء', delete:'حذف', edit:'تعديل',
        add:'إضافة', search:'بحث', currency:'﷼',
        save_changes:'حفظ التغييرات', send_message:'إرسال الرسالة',
        // Settings page
        profile:'الملف الشخصي', security:'الأمان', full_name:'الاسم الكامل',
        email:'البريد الإلكتروني', current_password:'كلمة المرور الحالية',
        new_password:'كلمة المرور الجديدة', confirm_new_password:'تأكيد كلمة المرور الجديدة',
        change_password:'تغيير كلمة المرور', preferences:'التفضيلات',
        settings_desc:'إدارة حسابك وتفضيلاتك',
        danger_zone:'منطقة الخطر', delete_account:'حذف الحساب',
        export_data:'تصدير البيانات', export_desc:'تحميل جميع بياناتك بصيغة JSON',
        activity_log_title:'سجل النشاطات', activity_log_desc:'تحميل سجل كامل بنشاطاتك — متوافق مع Excel',
        download_log:'تحميل السجل (Excel)',
        delete_account_desc:'حذف حسابك وجميع بياناتك نهائياً', export:'تصدير',
        save_preferences:'حفظ التفضيلات',
        currency_label:'العملة', theme_label:'المظهر', language_label:'اللغة',
        light_mode:'فاتح', dark_mode:'داكن',
        notification_settings:'إعدادات التنبيهات',
        email_notifications:'تنبيهات البريد الإلكتروني',
        email_notif_desc:'إرسال التنبيهات المهمة عبر البريد',
        warranty_notifications:'تنبيهات الضمانات',
        warranty_notif_desc:'تنبيه عند اقتراب انتهاء الضمان',
        budget_notifications:'تنبيهات الميزانية',
        budget_notif_desc:'تنبيه عند تجاوز ميزانية المشروع',
        // Support page
        support_title:'الدعم والمساعدة', support_desc:'أسئلة شائعة ونموذج تواصل',
        faq:'الأسئلة الشائعة', contact_us:'تواصل معنا',
        faq_q1:'كيف أنشئ مشروع جديد؟',
        faq_a1:'اذهب إلى صفحة المشاريع واضغط على زر "مشروع جديد". اختر القالب المناسب أو ابدأ من الصفر.',
        faq_q2:'كيف أضيف مصروف أو فاتورة؟',
        faq_a2:'افتح تفاصيل المشروع ثم اضغط "إضافة مصروف". أدخل العنوان والمبلغ والتصنيف والتاريخ.',
        faq_q3:'كيف يعمل حساب الربح والخسارة؟',
        faq_a3:'عند إنهاء المشروع، أدخل سعر البيع. يحسب ميزان الفرق تلقائياً.',
        faq_q4:'ما هي تنبيهات الضمان؟',
        faq_a4:'يراقب ميزان تاريخ الانتهاء وينبهك قبل 30 يوماً من انتهائه.',
        faq_q5:'كيف أشارك مشروع مع شخص آخر؟',
        faq_a5:'اضغط على أيقونة المشاركة بجانب المشروع. سيتم نسخ رابط للقراءة فقط.',
        faq_q6:'كيف أصدّر بياناتي؟',
        faq_a6:'يمكنك تصدير أي مشروع كملف ZIP أو تصدير التقارير كـ CSV.',
        contact_subject:'الموضوع', select_subject:'اختر الموضوع...',
        report_bug:'الإبلاغ عن خطأ', feature_request:'اقتراح ميزة',
        account_issue:'مشكلة في الحساب', other:'أخرى',
        message:'الرسالة', message_sent:'تم إرسال رسالتك بنجاح',
        message_sent_desc:'سنرد عليك في أقرب وقت ممكن.',
        // Reports page
        reports_desc:'تحليلات مالية شاملة لمشاريعك',
        export_csv:'تصدير CSV', print_pdf:'طباعة / PDF',
        total_spent_range:'إجمالي المصروفات',
        monthly_spending:'المصروفات الشهرية', category_breakdown:'توزيع الفئات',
        budget_vs_actual:'الميزانية مقابل الفعلي', warranty_status:'حالة الضمانات',
        top_category:'أعلى فئة إنفاقاً', no_data:'لا توجد بيانات',
        no_data_desc:'أضف مشاريع ومصروفات لعرض التقارير',
        select_period:'اختر الفترة...',
        // Projects page
        new_project:'مشروع جديد', all_projects:'كل المشاريع',
        projects_desc:'جميع مشاريعك في مكان واحد',
        all_statuses:'جميع الحالات',
        filter_active:'نشط', filter_done:'مكتمل', filter_archived:'مؤرشف',
        no_projects:'لا توجد مشاريع', no_projects_desc:'ابدأ بإنشاء مشروعك الأول لتتبع مصاريفك',
        total:'إجمالي', status_active:'نشط', status_done:'مكتمل', status_archived:'مؤرشف',
        search_projects:'بحث في المشاريع...',
        // Files page
        files_desc:'جميع ملفاتك ومستنداتك في مكان واحد',
        upload_file:'رفع ملف', total_files:'إجمالي الملفات',
        total_size:'الحجم الكلي', pdf_files:'ملفات PDF', images:'صور',
        search_files:'بحث في الملفات...', sort_newest:'الأحدث أولاً',
        sort_oldest:'الأقدم أولاً', sort_size:'الأكبر حجماً', sort_name:'الاسم',
        view_grid:'شبكة', view_list:'قائمة',
        tab_all:'الكل', tab_invoices:'الفواتير', tab_vault_warranties:'الضمانات',
        tab_contracts:'العقود', tab_other:'أخرى',
        min_price:'أدنى ﷼', max_price:'أعلى ﷼',
        no_files:'لا توجد ملفات', upload_first_file:'ارفع أول ملف لأحد مشاريعك',
        upload_new_file:'رفع ملف جديد', select_project:'اختر مشروعاً',
        expense_optional:'العملية (اختياري)', no_expense_link:'بدون ربط بعملية معينة',
        choose_file:'اختر الملف', drag_file_here:'اسحب الملف هنا أو',
        choose_a_file:'اختر ملفاً',
        // Global extras
        days:'يوم', mark_all_read:'تحديد كمقروء', loading:'جاري التحميل...',
        cannot_load_notifs:'تعذر تحميل التنبيهات', no_notifications:'لا توجد تنبيهات جديدة',
        search_placeholder:'بحث في المشاريع، العمليات، الملفات...',
        search_hint:'اكتب للبحث... أو استخدم Ctrl+K لفتح البحث',
        file_unit:'ملف', file_formats_hint:'صور — PDF — Word — Excel — ZIP — حتى 500 MB',
        uploading:'جاري الرفع...', upload_success:'تم الرفع!',
        warning_title:'تنبيه', error_title:'خطأ',
        select_project_file:'اختر المشروع والملف',
        no_expense_option:'بدون ربط بعملية',
        delete_file_title:'حذف الملف؟',
        no_budget:'بدون ميزانية', expense_unit:'عملية', warranty_unit:'ضمان',
        cannot_load_projects:'تعذّر تحميل المشاريع',
        project_deleted:'تم حذف المشروع', cannot_delete:'تعذّر الحذف',
        cannot_create:'تعذّر الإنشاء',
        fetch_error:'خطأ في جلب البيانات', server_error:'تعذّر الاتصال بالخادم',
        no_export_data:'لا توجد بيانات للتصدير',
        fill_all_fields:'الرجاء ملء جميع الحقول',
        generic_error:'حدث خطأ',
        pass_min_6:'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
        pass_mismatch:'كلمة المرور غير متطابقة',
        pw_very_weak:'ضعيفة جداً', pw_weak:'ضعيفة', pw_medium:'متوسطة',
        pw_strong:'قوية', pw_very_strong:'قوية جداً',
        pw_good:'جيدة', pw_excellent:'ممتازة ✓',
        // Register placeholders
        name_ph:'محمد العمري', pass_min_8_ph:'8 أحرف على الأقل', confirm_pass_ph:'أعد كتابة كلمة المرور',
        // Forgot-password page
        forgot_password_title:'نسيت كلمة المرور',
        forgot_password_subtitle:'أدخل بريدك وسنرسل لك رابط الاستعادة',
        send_reset_link:'إرسال رابط الاستعادة 📧',
        reset_email_hint:'تأكد من إدخال البريد الذي سجّلت به حسابك في ميزان',
        back_to_login:'← العودة لتسجيل الدخول',
        reset_check_email:'تحقق من بريدك',
        // Reset-password page
        new_password_title:'كلمة مرور جديدة',
        new_password_subtitle:'اختر كلمة مرور قوية لحسابك',
        new_password_label:'كلمة المرور الجديدة',
        confirm_password_label:'تأكيد كلمة المرور',
        save_new_password:'حفظ كلمة المرور الجديدة ✓',
        reset_password_success:'تم تغيير كلمة المرور!',
        reset_password_success_msg:'كلمة المرور الجديدة حُفظت بنجاح. يمكنك الآن تسجيل الدخول.',
        request_new_link:'طلب رابط جديد',
        export_data_title:'تصدير البيانات', export_data_msg:'سيتم تحميل جميع بياناتك بصيغة JSON',
        export_success:'تم تصدير البيانات بنجاح',
        delete_account_confirm:'حذف الحساب نهائياً؟',
        delete_account_msg:'سيتم حذف جميع بياناتك بشكل نهائي ولا يمكن التراجع.',
        type_delete:'اكتب "حذف" للتأكيد:',
        must_type_delete:'يجب كتابة "حذف" للتأكيد',
        account_deleted:'تم حذف حسابك',
        sending:'جاري الإرسال...', write_message_here:'اكتب رسالتك هنا...',
        // File type selector
        file_type_label:'نوع الملف', file_type_invoice:'فاتورة',
        file_type_warranty:'ضمان', file_type_report:'تقرير',
        file_type_image:'صورة', file_type_other:'أخرى',
        file_type_contract:'عقد',
        custom_type_placeholder:'أدخل النوع...',
        // Navigation
        back_to_projects:'← المشاريع',
        invoices_desc:'أرشيف فواتير وإيصالات مشاريعك',
        warranties_desc:'تابع كل ضماناتك وتواريخ انتهائها',
        war_total:'إجمالي الضمانات', war_active:'ضمان نشط',
        war_expiring:'ينتهي قريباً (30 يوم)', war_expired:'منتهي الصلاحية',
        war_filter_active:'نشط', war_filter_expiring:'ينتهي قريباً', war_filter_expired:'منتهي',
        search_warranties:'بحث في الضمانات...',
        no_warranties:'لا توجد ضمانات',
        warranties_hint:'الضمانات تُضاف عند إدخال مصاريف في صفحة المشروع',
        go_to_projects:'الذهاب للمشاريع',
        // Project Nature
        project_nature:'نوع المشروع', nature_personal:'شخصي', nature_commercial:'تجاري',
        // Smart upload
        smart_upload:'رفع ذكي', upload_type:'النوع',
        purchase_date:'تاريخ الشراء', expiry_date:'تاريخ انتهاء الضمان',
        invoice_name:'اسم الفاتورة', invoice_category:'الفئة', custom_note:'ملاحظة',
        contract_title:'عنوان العقد', signed_date:'تاريخ التوقيع',
        custom_label:'وصف الملف', category:'التصنيف',
        invoice_name_ph:'مثال: فاتورة كهرباء', category_ph:'مثال: مرافق',
        custom_category_ph:'اكتب التصنيف...',
        contract_title_ph:'مثال: عقد إيجار', custom_label_ph:'مثال: شهادة ضمان مصنع',
        custom_type_placeholder:'أدخل النوع...',
        expense_required:'يجب اختيار العملية لربط هذا الملف',
        select_project_file:'اختر المشروع والملف أولاً',
        warning_title:'تنبيه', error_title:'خطأ', success_title:'تم',
        no_expense_link:'بدون ربط بعملية معينة', no_expense_option:'بدون عملية',
        // Analytics filters
        last_1m:'آخر شهر', last_3m:'آخر 3 أشهر', last_6m:'آخر 6 أشهر', last_12m:'آخر 12 شهر',
        analytics_title:'تحليل المصروفات', profit_loss_title:'الربح / الخسارة',
        total_in:'الإيرادات', total_out:'المصروفات', net:'الصافي',
        margin:'الهامش', avg_monthly:'المتوسط الشهري',
        // Project-detail dynamic strings
        pd_no_expenses_title:'لا توجد عمليات بعد',
        pd_no_expenses_desc:'ابدأ بإضافة أول عملية لهذا المشروع',
        pd_add_expense:'＋ إضافة عملية',
        pd_no_warranties_title:'لا توجد ضمانات',
        pd_no_warranties_desc:'أضف ضماناً عند إضافة عملية جديدة',
        pd_no_files_title:'لا توجد ملفات',
        pd_no_files_desc:'الملفات تُرفع تلقائياً عند إضافة فاتورة أو ضمان',
        pd_warranty_ends_in:'الضمان ينتهي', pd_remaining:'متبقي',
        pd_expired_since:'انتهى منذ', pd_days:'يوم',
        w_status_expired:'منتهي', w_status_expiring_days:'ينتهي خلال أيام',
        w_status_expiring_soon:'ينتهي قريباً', w_status_active:'ساري',
        invoice_label:'فاتورة', warranty_label:'ضمان',
        delete_expense_confirm:'تأكيد الحذف',
        delete_expense_msg:'سيتم حذف العملية وكل ملفاتها (فاتورة/ضمان).',
        deleted_ok:'تم الحذف', cannot_delete_short:'تعذّر الحذف',
        invalid_id:'معرّف غير صالح',
        edit_expense_title:'تعديل العملية',
        title_label:'العنوان', amount_label:'المبلغ', vendor_label:'المورد',
        saved_ok:'تم الحفظ', cannot_save:'تعذّر الحفظ',
        expense_not_found:'العملية غير موجودة',
        load_expenses_failed:'تعذّر تحميل العمليات',
        load_failed_short:'تعذّر التحميل',
        add_invoice:'إضافة فاتورة', add_warranty:'إضافة ضمان',
        update_status:'✏️ تحديث الحالة', upload_file_btn:'☁️ رفع ملف',
        add_expense_btn:'＋ إضافة عملية',
        total_cost:'إجمالي التكلفة', expenses_count:'عدد العمليات',
        warranties_count:'الضمانات', sar:'﷼',
        budget_label:'الميزانية', budget_warning:'⚠️ تجاوزت أو اقتربت من حد الميزانية!',
        tab_expenses:'📋 العمليات', tab_warranties:'🛡️ الضمانات', tab_files:'📁 الملفات',
        modal_add_expense:'＋ إضافة عملية / مصروف',
        expense_name:'اسم العملية', expense_name_ph:'مثال: شراء كفرات جديدة',
        amount_sar:'المبلغ (﷼)',
        purchase_date_field:'تاريخ الشراء',
        vendor_field:'المورد / الجهة', vendor_ph:'اسم المحل أو الشركة',
        upload_invoice_label:'رفع الفاتورة (صورة أو PDF)', upload_hint_500:'حد أقصى 500MB',
        add_warranty_for_expense:'إضافة ضمان لهذه العملية',
        warranty_start:'بداية الضمان', warranty_end_field:'انتهاء الضمان',
        notes_label:'ملاحظات', notes_ph:'أي تفاصيل إضافية...',
        save_expense:'حفظ العملية ✓',
        modal_update_status:'✏️ تحديث حالة المشروع',
        status_label:'الحالة',
        status_active_o:'🟢 نشط', status_done_o:'✅ مكتمل', status_archived_o:'📦 مؤرشف',
        sell_price_sar:'سعر البيع (﷼)', sell_price_hint:'اتركه 0 إذا لم يُباع بعد',
        save_short:'حفظ ✓',
        // Support loop (Mizan 3.0)
        my_tickets:'تذاكري', refresh:'تحديث',
        view_my_tickets:'عرض تذاكري',
        ticket_open:'مفتوحة', ticket_replied:'تم الرد',
        no_tickets_title:'لا توجد تذاكر بعد',
        no_tickets_desc:'أرسل رسالة لفريق الدعم من تبويب "تواصل معنا"',
        support_reply:'رد فريق الدعم',
        // Clone project
        clone_project:'نسخ المشروع', project_cloned:'تم نسخ المشروع',
        cloning:'جاري النسخ...',
        // Export feedback
        download_complete:'تم التحميل بنجاح',
        preparing_zip:'جاري تجهيز ملف ZIP...',
        // Password update
        pass_min_8:'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
        // Dynamic error keys — auth pages
        err_session_expired:'انتهت صلاحية الجلسة، أعد المحاولة',
        err_locked_out:'تم تجاوز عدد المحاولات المسموح. حاول مجدداً بعد 15 دقيقة',
        err_fields_required:'الرجاء إدخال الإيميل وكلمة المرور',
        err_invalid_credentials:'الإيميل أو كلمة المرور غير صحيحة',
        err_invalid_email_domain:'صيغة البريد الإلكتروني غير صحيحة أو نطاقها غير موجود',
        err_email_in_use:'هذا الإيميل مستخدم من قبل',
        err_reg_rate_limited:'تم تجاوز عدد المحاولات المسموح. حاول مجدداً بعد 15 دقيقة',
        err_all_fields:'الرجاء ملء جميع الحقول',
        err_admin_credentials:'الإيميل أو كلمة المرور غير صحيحة، أو الحساب لا يملك صلاحيات المدير',
        err_unauthorized:'غير مصرح — الرجاء تسجيل الدخول',
        err_csrf_invalid:'CSRF token غير صالح',
        // Dynamic toast keys — projects
        project_created:'تم إنشاء المشروع',
        project_updated:'تم تحديث المشروع',
        update_failed:'تعذّر التحديث',
        project_not_found:'المشروع غير موجود',
        share_link_failed:'فشل إنشاء رابط المشاركة',
        share_link_copied:'تم نسخ رابط المشاركة',
        // Swal modal labels — projects
        delete_project_title:'حذف المشروع؟',
        delete_permanently:'حذف نهائياً',
        new_project_title:'مشروع جديد',
        edit_project_title:'تعديل المشروع',
        project_name_swal:'اسم المشروع',
        project_name_ph_swal:'مثال: سيارة كامري 2024',
        type_swal:'النوع',
        nature_swal:'الطبيعة',
        budget_swal:'الميزانية',
        template_swal:'قالب جاهز (اختياري)',
        no_template_opt:'بدون قالب',
        color_swal:'اللون',
        optional_ph:'اختياري',
        create_btn:'إنشاء',
        save_edits_btn:'حفظ التعديلات',
        project_name_required_msg:'اسم المشروع مطلوب',
        share_link_modal_title:'رابط المشاركة',
        close_btn:'إغلاق',
        copy_link_prompt:'انسخ الرابط:',
        suggested_expenses_head:'العمليات المقترحة — اختر ما تريد إضافته:',
        // Dynamic toast keys — project-detail
        file_too_large:'الملف أكبر من 500 MB',
        file_type_unsupported:'نوع الملف غير مدعوم (صور أو PDF فقط)',
        expense_added_ok:'تم إضافة العملية بنجاح ✓',
        expense_add_failed:'تعذّر الإضافة',
        project_status_updated:'تم تحديث حالة المشروع ✓',
        // Dynamic toast keys — files, settings, reports
        deleted_title:'تم الحذف',
        export_failed:'تعذّر التصدير',
        delete_account_failed:'تعذّر حذف الحساب',
        report_exported:'تم تصدير التقرير',
        // Dynamic toast keys — support
        ticket_submitted:'تم إرسال تذكرتك بنجاح ✅',
        write_message_required:'الرجاء كتابة رسالة',
        reply_sent_ok:'تم إرسال ردك ✅',
        select_rating:'الرجاء اختيار تقييم',
        rating_submitted_ok:'تم إرسال تقييمك، شكراً ⭐',
        connection_error:'تعذّر الاتصال',
        // Settings API success/error messages
        profile_updated:'تم تحديث الملف الشخصي',
        password_changed:'تم تغيير كلمة المرور بنجاح',
        preferences_saved:'تم حفظ التفضيلات',
        err_name_email_required:'الاسم والبريد مطلوبان',
        err_invalid_email_api:'بريد إلكتروني غير صالح أو نطاقه غير موجود',
        err_name_too_long:'الاسم طويل جداً (100 حرف كحد أقصى)',
        err_email_taken:'البريد الإلكتروني مستخدم من حساب آخر',
        err_all_fields_api:'جميع الحقول مطلوبة',
        err_wrong_current_pass:'كلمة المرور الحالية غير صحيحة',
        err_generic_server:'حدث خطأ',
        // Phase 4 audit additions
        copied:'تم النسخ', upload_warranty:'رفع ضمان', create_ticket:'إنشاء تذكرة',
        faq_privacy:'الأسئلة الشائعة وسياسة الخصوصية',
        send_close:'إرسال وإغلاق', reply_open:'رد بدون إغلاق',
        recent_status:'الحالة', recent_type:'النوع',
        expense_date:'التاريخ', expense_amount:'المبلغ',
        share_title:'مشاركة المشروع', read_only:'للقراءة فقط',
        print_report:'طباعة التقرير',
        reports_summary:'ملخص', total_spent:'إجمالي المصروفات',
        monthly_spending:'الإنفاق الشهري', expense_breakdown:'توزيع المصروفات',
        top_projects:'أعلى المشاريع', export_pdf:'تصدير PDF', export_csv:'تصدير CSV',
        invoices_title:'الفواتير', invoice_number:'رقم الفاتورة', invoice_total:'الإجمالي',
        invoice_date:'تاريخ الفاتورة', invoice_status:'حالة الفاتورة',
        no_invoices:'لا توجد فواتير', add_invoice:'إضافة فاتورة',
        faq_q1:'كيف أضيف مشروعاً جديداً؟',
        faq_a1:'من لوحة التحكم اضغط على "مشروع جديد" واملأ البيانات.',
        faq_q2:'هل بياناتي آمنة؟',
        faq_a2:'نعم — جميع بياناتك مشفرة ومخزنة بأمان.',
        faq_q3:'كيف أصدّر بياناتي؟',
        faq_a3:'من صفحة الإعدادات يمكنك تصدير جميع بياناتك بصيغة JSON أو ZIP.',
        privacy_policy:'سياسة الخصوصية',
        privacy_text:'نحن نحترم خصوصيتك. لا نشارك بياناتك مع أي طرف ثالث، ويمكنك حذف حسابك في أي وقت من صفحة الإعدادات.',
        // Terms & Data Policy (support page)
        terms_of_service:'شروط الاستخدام',
        terms_text:'يُمنع استخدام النظام في أي أعمال غير مشروعة. النظام مخصص للإدارة المالية الشخصية والتجارية البسيطة.',
        data_retention:'حفظ البيانات',
        data_retention_text:'يتم تشفير بياناتك، ويمكنك حذف حسابك نهائياً متى شئت من صفحة الإعدادات، مما سيمسح كافة ملفاتك وفواتيرك من خوادمنا.',
        policies_section:'السياسات والشروط',
        // Cookie consent banner
        cookie_title:'تفضيلات ملفات الارتباط',
        cookie_desc:'نستخدم ملفات الارتباط الأساسية فقط لتشغيل النظام وتذكر تفضيلاتك. لا توجد ملفات تتبع أو إعلانات من أي نوع.',
        cookie_msg:'نستخدم ملفات الارتباط الأساسية فقط لتشغيل النظام وتذكر تفضيلاتك.',
        cookie_accept:'قبول الكل', cookie_essential:'الأساسية فقط',
        cookie_accept_all:'قبول الكل', cookie_essential_only:'الأساسية فقط',
        cookie_toast_all:'تم قبول جميع ملفات الارتباط ✓', cookie_toast_essential:'تم قبول الملفات الأساسية فقط ✓',
        // Support page — 8 detailed FAQs
        sp_faq_q1:'هل ميزان مجاني بالكامل؟',
        sp_faq_a1:'نعم. ميزان مجاني تماماً بدون رسوم خفية أو خطط مدفوعة في الوقت الحالي.',
        sp_faq_q2:'كيف أصدّر بياناتي (CSV / ZIP)؟',
        sp_faq_a2:'يمكنك تصدير أي مشروع كملف ZIP يحتوي على جميع المرفقات والفواتير. كما يمكنك تصدير تقارير المصاريف كملف CSV متوافق مع Excel من صفحة التقارير.',
        sp_faq_q3:'كيف تعمل تنبيهات الضمان؟',
        sp_faq_a3:'عند إضافة تاريخ ضمان لأي مصروف، يراقب ميزان تواريخ الانتهاء وينبّهك قبل 30 يوماً عبر إشعار في لوحة التحكم حتى لا تفوّت أي ضمان.',
        sp_faq_q4:'كيف أحذف حسابي نهائياً؟',
        sp_faq_a4:'من صفحة الإعدادات، اضغط "حذف الحساب نهائياً". تُحذف جميع بياناتك (المشاريع، المصاريف، الملفات) من خوادمنا فوراً ونهائياً دون أي إمكانية للاسترداد.',
        sp_faq_q5:'كم درجة أمان بياناتي في السحابة؟',
        sp_faq_a5:'بياناتك محفوظة على خوادم مؤمَّنة. جميع الاتصالات مشفرة عبر HTTPS. كلمات المرور محفوظة بخوارزمية bcrypt ولا يمكن الاطلاع عليها. لا يمكن لأي موظف الوصول لبياناتك المالية.',
        sp_faq_q6:'كيف أشارك مشروعاً مع شخص آخر؟',
        sp_faq_a6:'من صفحة المشاريع، اضغط أيقونة المشاركة. سيُولَّد رابط للقراءة فقط مؤمَّن بتوكن تشفيري عشوائي. يمكن للمستلم الاطلاع على التفاصيل دون تسجيل دخول أو تعديل.',
        sp_faq_q7:'كيف يحسب ميزان الربح والخسارة؟',
        sp_faq_a7:'عند إنهاء مشروع وإدخال سعر البيع، يطرح ميزان إجمالي المصاريف المسجّلة من سعر البيع تلقائياً. النتيجة الموجبة ربح والسالبة خسارة.',
        sp_faq_q8:'هل يمكنني إرفاق الفواتير والملفات؟',
        sp_faq_a8:'نعم. يمكنك رفع صور الفواتير والمستندات (PDF، صور، Excel وغيرها بحجم حتى 500 MB) وربطها بأي مصروف أو مشروع. وتوجد صفحة ملفات مركزية لاستعراض جميع مرفقاتك.',
        // Terms of Service
        tos_title:'شروط الاستخدام',
        tos_c1_title:'الاستخدام المقبول',
        tos_c1_body:'يُمنع استخدام ميزان في أي أعمال أو أنشطة غير مشروعة. المنصة مخصصة للإدارة المالية الشخصية والتجارية المشروعة فقط. أي استخدام مسيء سيؤدي إلى تعليق الحساب فوراً.',
        tos_c2_title:'إخلاء المسؤولية',
        tos_c2_body:'ميزان أداة لتتبع الإنفاق فقط ولا يُقدم استشارات ضريبية أو قانونية. لن تتحمل المنصة أي مسؤولية قانونية عن أخطاء في الحسابات الضريبية أو المالية الناتجة عن استخدام البيانات المُدخلة.',
        tos_c3_title:'مسؤولية كلمة المرور',
        tos_c3_body:'أنت مسؤول كلياً عن الحفاظ على سرية بيانات دخولك. لا تشارك كلمة المرور مع أي طرف. إذا اشتبهت باختراق حسابك، غيّر كلمة المرور فوراً وتواصل مع الدعم.',
        // Privacy Policy & Data Retention
        pp_title:'سياسة الخصوصية وحفظ البيانات',
        pp_c1_title:'البيانات المُجمَّعة',
        pp_c1_body:'نجمع فقط البيانات الضرورية لتشغيل الخدمة: البريد الإلكتروني، الاسم، والبيانات المالية التي تُدخلها أنت. لا نجمع بيانات سلوكية أو بيانات تصفح من أي نوع.',
        pp_c2_title:'مشاركة البيانات والإعلانات',
        pp_c2_body:'صفر. لا نشارك بياناتك مع أي طرف ثالث تحت أي ظرف. لا توجد إعلانات، ولا تتبع، ولا تحليلات خارجية من أي نوع.',
        pp_c3_title:'الاحتفاظ بالبيانات والحذف',
        pp_c3_body:'نحتفظ ببياناتك ما دمت تمتلك حساباً نشطاً. عند حذف حسابك، تُحذف جميع بياناتك (المشاريع، المصاريف، الملفات) فوراً ونهائياً من خوادمنا دون أي إمكانية للاسترداد.',
        // Support page tab switcher & card subtitles
        sp_tab_info:'مركز المعلومات والسياسات', sp_tab_contact:'التواصل والدعم الفني',
        sp_faq_section_sub:'أبرز الأسئلة التي يطرحها مستخدمو ميزان',
        sp_tos_sub:'الاتفاقية المنظّمة لاستخدامك لمنصة ميزان',
        sp_pp_sub:'كيف نحمي بياناتك والمدة التي نحتفظ بها',
        // Admin panel
        admin_panel:'لوحة الإدارة', admin_dashboard:'لوحة تحكم المدير',
        admin_dashboard_sub:'إدارة المستخدمين، تذاكر الدعم، وسجلات النشاط',
        admin_total_users:'إجمالي المستخدمين', admin_total_projects:'إجمالي المشاريع',
        admin_total_expenses:'إجمالي المصاريف', admin_total_files:'إجمالي الملفات',
        admin_open_tickets:'تذاكر مفتوحة',
        admin_avg_satisfaction:'متوسط رضا التذاكر',
        admin_support_tickets:'تذاكر الدعم الفني',
        admin_col_user:'المستخدم', admin_col_subject:'الموضوع',
        admin_col_status:'الحالة', admin_col_date:'التاريخ', admin_col_action:'إجراء',
        admin_reply_close:'رد وإغلاق', admin_reply_open:'رد بدون إغلاق',
        admin_send_close:'↩ إرسال وإغلاق',
        admin_cancel:'إلغاء', admin_write_reply:'اكتب ردك هنا... (يظهر للمستخدم)',
        admin_optional_note:'اختياري: اتركه فارغاً لإغلاق بدون رد',
        admin_ticket_open:'مفتوحة', admin_ticket_closed:'مغلقة',
        admin_replied:'تم الرد',
        admin_activity_log:'سجل النشاطات',
        admin_activity_desc:'سجل نشاطات جميع المستخدمين',
        admin_download_log:'📥 تحميل السجل (Excel)',
        admin_rows_per_page:'سجلات لكل صفحة:',
        admin_col_name:'الاسم', admin_col_action_type:'الإجراء',
        admin_col_details:'التفاصيل', admin_col_datetime:'التاريخ والوقت', admin_col_ip:'IP',
        admin_announce_title:'إعلان للمستخدمين',
        admin_announce_status_active:'نشط', admin_announce_status_none:'لا يوجد',
        admin_current_ann:'الإعلان الحالي', admin_cancel_ann:'× إلغاء الإعلان',
        admin_new_announce:'نشر إعلان جديد (يظهر لجميع المستخدمين كبانر في الأعلى)',
        admin_ann_ar_ph:'النص بالعربية (مطلوب)', admin_ann_en_ph:'English text (optional)',
        admin_publish:'نشر الإعلان', admin_publish_note:'سيظهر فوراً لجميع المستخدمين',
        admin_recent_users:'آخر المستخدمين المسجلين',
        admin_col_reg_date:'تاريخ التسجيل', admin_col_role:'الصلاحية',
        admin_role_admin:'مدير', admin_role_user:'مستخدم',
        admin_role_super_admin:'مدير أعلى',
        admin_promote_btn:'ترقية لمدير', admin_demote_btn:'إزالة الصلاحية',
        admin_open_label:'مفتوحة', admin_user_label:'مستخدم',
        admin_ann_active:'نشط', admin_ann_none:'لا يوجد',
        admin_csv_desc:'تحميل ملف CSV يحتوي على آخر ٥٠٫٠٠٠ سجل — متوافق مع Excel (BOM UTF-8)',
        log_user_login:'تسجيل دخول', log_user_logout:'تسجيل خروج',
        log_create_project:'إنشاء مشروع', log_export_data:'تصدير بيانات',
        admin_log_filter_user:'تصفية بالمستخدم', admin_all_users:'كل المستخدمين',
        admin_log_filter_date:'تصفية بالتاريخ', admin_select_date:'اختر تاريخاً',
        admin_filter_apply:'تطبيق', admin_filter_reset:'إعادة ضبط',
        admin_no_tickets:'لا توجد تذاكر دعم حتى الآن',
        admin_toast_reply_sent:'تم إرسال الرد وإغلاق التذكرة ✉️',
        admin_toast_reply_open:'تم إرسال الرد — التذكرة لا تزال مفتوحة',
        admin_toast_ann_published:'تم نشر الإعلان لجميع المستخدمين',
        admin_toast_ann_cleared:'تم إلغاء الإعلان',
        // Admin login (T1)
        admin_badge:'وصول المدير فقط', admin_login_subtitle:'لوحة التحكم — صلاحيات محدودة',
        admin_login_h1:'تسجيل دخول المدير', admin_email_label:'البريد الإلكتروني',
        admin_email_ph:'admin@example.com', admin_pass_label:'كلمة المرور',
        admin_pass_ph:'••••••••', admin_login_btn:'🔐 الدخول إلى لوحة التحكم',
        admin_back:'← العودة إلى الصفحة الرئيسية',
        // Dashboard (T2-T8)
        admin_storage_uploads:'تخزين الملفات', admin_storage_db:'حجم قاعدة البيانات',
        admin_analytics_title:'التحليلات والإحصاءات',
        admin_chart_ratings:'توزيع التقييمات', admin_chart_satisfaction:'مستوى الرضا',
        admin_chart_projects:'المشاريع: شخصي / تجاري', admin_chart_top_users:'أكثر المستخدمين نشاطاً',
        admin_feedback_title:'آراء العملاء', admin_no_feedback:'لا توجد آراء مكتوبة بعد',
        admin_ann_bg_color:'لون الخلفية', admin_ann_text_color:'لون النص',
        admin_ann_font_size:'حجم الخط', admin_ann_preview:'معاينة مباشرة:',
        admin_ann_preview_placeholder:'اكتب النص لتظهر المعاينة...',
        admin_reset_pw:'🔑 إعادة كلمة المرور', admin_send_email:'✉️ إرسال بريد',
        admin_custom_email_title:'إرسال بريد مخصص', admin_to:'إلى:',
        admin_email_subject_ph:'الموضوع', admin_send_email_btn:'✉️ إرسال',
        admin_email_tester_title:'اختبار قوالب البريد الإلكتروني',
        admin_et_tpl_welcome:'ترحيب', admin_et_tpl_reset:'إعادة كلمة المرور',
        admin_et_tpl_warranty:'تنبيه ضمان', admin_et_tpl_budget:'تنبيه ميزانية',
        admin_et_lang_ar:'عربي', admin_et_lang_en:'English',
        admin_et_target_ph:'البريد المستهدف', admin_et_send_btn:'إرسال بريد اختباري',
        admin_prev:'← السابق', admin_next:'التالي →', admin_no_data:'لا توجد بيانات',
        // V4.2 new analytics keys
        admin_total_budgets:'الميزانيات المُدارة',
        admin_sla_resolved:'تذاكر مُحلّة', admin_avg_hours:'متوسط', admin_hours:'ساعة',
        admin_chart_monthly_title:'اتجاه المصاريف الشهرية', admin_last_12:'آخر 12 شهراً',
        admin_rating_tracker:'متابعة تقييمات المستخدمين', admin_active_users:'مستخدم',
        admin_rt_user:'المستخدم', admin_rt_tickets:'التذاكر', admin_rt_rated:'قيّموا',
        admin_rt_status:'الحالة', admin_rt_avg:'متوسط التقييم',
        admin_rt_badge_rated:'✓ قيّم', admin_rt_badge_pending:'— لم يقيّم',
        // Logout page
        logout_page_title:'تسجيل الخروج',
        logout_confirm:'هل أنت متأكد أنك تريد الخروج من حسابك في ميزان؟',
        logout_data_saved:'بياناتك محفوظة وستجدها عند عودتك.',
        logout_btn:'خروج', logout_stay:'ابقَ في الحساب',
        logout_countdown:'سيتم الخروج تلقائياً خلال', logout_sec:'ثانية',
        // Support V4.3
        platform_guidelines:'إرشادات المنصة', contact_support:'التواصل والدعم',
    },
    en: {
        // Core
        app_name:'Mizan', app_subtitle:'Your Personal Financial Archive',
        login_title:'Sign In', register_title:'Create New Account',
        email_label:'Email Address', password_label:'Password',
        confirm_pass_label:'Confirm Password', name_label:'Full Name',
        login_btn:'Sign In', register_btn:'Create Account',
        no_account:"Don't have an account?", create_account:'Sign Up',
        have_account:'Already have an account?', sign_in:'Sign In',
        forgot_pass:'Forgot password',
        // Sidebar
        dashboard:'Dashboard', projects:'Projects', invoices:'Invoices',
        warranties:'Warranties', files:'My Files', reports:'Reports',
        support:'Support', settings:'Settings', logout:'Logout',
        // Dashboard
        add_project:'New Project', total_expenses:'Total Expenses',
        active_projects:'Active Projects', expiring_warranties:'Expiring Warranties',
        total_profit:'Total Profit', notifications:'Notifications',
        expenses_chart:'Expenses — Last 6 Months',
        months_6:'6 Months', months_3:'3 Months', months_12:'12 Months',
        expiring_soon:'Warranties Expiring Soon', view_all:'View All',
        all_warranties_valid:'All warranties are valid, no alerts',
        recent_projects:'Recent Projects', no_chart_data:'No data yet. Start adding expenses!',
        start_first_project:'Start by creating your first project!',
        th_project:'Project', th_type:'Type', th_status:'Status',
        th_total_cost:'Total Cost', th_profit_loss:'Profit / Loss',
        th_date:'Date', details:'Details →',
        // Actions
        save:'Save', cancel:'Cancel', delete:'Delete', edit:'Edit',
        add:'Add', search:'Search', currency:'SAR',
        save_changes:'Save Changes', send_message:'Send Message',
        // Settings page
        profile:'Profile', security:'Security', full_name:'Full Name',
        email:'Email', current_password:'Current Password',
        new_password:'New Password', confirm_new_password:'Confirm New Password',
        change_password:'Change Password', preferences:'Preferences',
        settings_desc:'Manage your account and preferences',
        danger_zone:'Danger Zone', delete_account:'Delete Account',
        export_data:'Export Data', export_desc:'Download all your data as JSON',
        activity_log_title:'Activity Log', activity_log_desc:'Download your full activity history — Excel compatible',
        download_log:'Download Log (Excel)',
        delete_account_desc:'Permanently delete your account and all data', export:'Export',
        save_preferences:'Save Preferences',
        currency_label:'Currency', theme_label:'Theme', language_label:'Language',
        light_mode:'Light', dark_mode:'Dark',
        notification_settings:'Notification Settings',
        email_notifications:'Email Notifications',
        email_notif_desc:'Send important alerts via email',
        warranty_notifications:'Warranty Notifications',
        warranty_notif_desc:'Alert when a warranty is about to expire',
        budget_notifications:'Budget Notifications',
        budget_notif_desc:'Alert when project budget is exceeded',
        // Support page
        support_title:'Support & Help', support_desc:'FAQ and contact form',
        faq:'Frequently Asked Questions', contact_us:'Contact Us',
        faq_q1:'How do I create a new project?',
        faq_a1:'Go to the Projects page and click "New Project". Choose a template or start from scratch.',
        faq_q2:'How do I add an expense or invoice?',
        faq_a2:'Open the project details and click "Add Expense". Enter title, amount, category, and date.',
        faq_q3:'How does profit and loss calculation work?',
        faq_a3:'When you finish a project, enter the selling price. Mizan calculates the difference automatically.',
        faq_q4:'What are warranty alerts?',
        faq_a4:'Mizan monitors expiry dates and alerts you 30 days before any warranty expires.',
        faq_q5:'How do I share a project?',
        faq_a5:'Click the share icon next to the project. A read-only link will be copied to your clipboard.',
        faq_q6:'How do I export my data?',
        faq_a6:'You can export any project as a ZIP file or export reports as CSV.',
        contact_subject:'Subject', select_subject:'Select subject...',
        report_bug:'Report a Bug', feature_request:'Feature Request',
        account_issue:'Account Issue', other:'Other',
        message:'Message', message_sent:'Your message has been sent',
        message_sent_desc:'We will reply as soon as possible.',
        // Reports page
        reports_desc:'Comprehensive financial analytics for your projects',
        export_csv:'Export CSV', print_pdf:'Print / PDF',
        total_spent_range:'Total Spent',
        monthly_spending:'Monthly Spending', category_breakdown:'Category Breakdown',
        budget_vs_actual:'Budget vs Actual', warranty_status:'Warranty Status',
        top_category:'Top Spending Category', no_data:'No Data',
        no_data_desc:'Add projects and expenses to view reports',
        select_period:'Select period...',
        // Projects page
        new_project:'New Project', all_projects:'All Projects',
        projects_desc:'All your projects in one place',
        all_statuses:'All Statuses',
        filter_active:'Active', filter_done:'Completed', filter_archived:'Archived',
        no_projects:'No projects', no_projects_desc:'Start by creating your first project to track expenses',
        total:'Total', status_active:'Active', status_done:'Completed', status_archived:'Archived',
        search_projects:'Search projects...',
        // Files page
        files_desc:'All your files and documents in one place',
        upload_file:'Upload File', total_files:'Total Files',
        total_size:'Total Size', pdf_files:'PDF Files', images:'Images',
        search_files:'Search files...', sort_newest:'Newest First',
        sort_oldest:'Oldest First', sort_size:'Largest First', sort_name:'Name',
        view_grid:'Grid', view_list:'List',
        tab_all:'All', tab_invoices:'Invoices', tab_vault_warranties:'Warranties',
        tab_contracts:'Contracts', tab_other:'Other',
        min_price:'Min SAR', max_price:'Max SAR',
        no_files:'No files', upload_first_file:'Upload your first file for a project',
        upload_new_file:'Upload New File', select_project:'Select a project',
        expense_optional:'Expense (optional)', no_expense_link:'No expense link',
        choose_file:'Choose File', drag_file_here:'Drag file here or',
        choose_a_file:'Choose a file',
        // Global extras
        days:'days', mark_all_read:'Mark all read', loading:'Loading...',
        cannot_load_notifs:'Could not load notifications', no_notifications:'No new notifications',
        search_placeholder:'Search projects, transactions, files...',
        search_hint:'Type to search... or use Ctrl+K to open search',
        file_unit:'file(s)', file_formats_hint:'Images — PDF ��� Word — Excel — ZIP — up to 500 MB',
        uploading:'Uploading...', upload_success:'Uploaded!',
        warning_title:'Warning', error_title:'Error',
        select_project_file:'Select a project and file',
        no_expense_option:'No expense link',
        delete_file_title:'Delete file?',
        no_budget:'No budget', expense_unit:'expense(s)', warranty_unit:'warranty',
        cannot_load_projects:'Could not load projects',
        project_deleted:'Project deleted', cannot_delete:'Could not delete',
        cannot_create:'Could not create',
        fetch_error:'Error fetching data', server_error:'Could not connect to server',
        no_export_data:'No data to export',
        fill_all_fields:'Please fill in all fields',
        generic_error:'An error occurred',
        pass_min_6:'Password must be at least 6 characters',
        pass_mismatch:'Passwords do not match',
        pw_very_weak:'Very weak', pw_weak:'Weak', pw_medium:'Medium',
        pw_strong:'Strong', pw_very_strong:'Very strong',
        pw_good:'Good', pw_excellent:'Excellent ✓',
        // Register placeholders
        name_ph:'Mohammed Al-Omari', pass_min_8_ph:'At least 8 characters', confirm_pass_ph:'Re-enter your password',
        // Forgot-password page
        forgot_password_title:'Forgot Password',
        forgot_password_subtitle:'Enter your email and we\'ll send you a reset link',
        send_reset_link:'Send Reset Link 📧',
        reset_email_hint:'Make sure to enter the email you registered with',
        back_to_login:'← Back to Login',
        reset_check_email:'Check Your Email',
        // Reset-password page
        new_password_title:'New Password',
        new_password_subtitle:'Choose a strong password for your account',
        new_password_label:'New Password',
        confirm_password_label:'Confirm Password',
        save_new_password:'Save New Password ✓',
        reset_password_success:'Password Changed!',
        reset_password_success_msg:'Your new password has been saved. You can now log in.',
        request_new_link:'Request New Link',
        export_data_title:'Export Data', export_data_msg:'All your data will be downloaded as JSON',
        export_success:'Data exported successfully',
        delete_account_confirm:'Delete account permanently?',
        delete_account_msg:'All your data will be permanently deleted and cannot be undone.',
        type_delete:'Type "delete" to confirm:',
        must_type_delete:'You must type "delete" to confirm',
        account_deleted:'Your account has been deleted',
        sending:'Sending...', write_message_here:'Write your message here...',
        // File type selector
        file_type_label:'File Type', file_type_invoice:'Invoice',
        file_type_warranty:'Warranty', file_type_report:'Report',
        file_type_image:'Image', file_type_other:'Other',
        file_type_contract:'Contract',
        custom_type_placeholder:'Enter type...',
        // Navigation
        back_to_projects:'← Projects',
        invoices_desc:'Archive of project invoices and receipts',
        warranties_desc:'Track all your warranties and expiry dates',
        war_total:'Total Warranties', war_active:'Active Warranties',
        war_expiring:'Expiring Soon (30 days)', war_expired:'Expired',
        war_filter_active:'Active', war_filter_expiring:'Expiring Soon', war_filter_expired:'Expired',
        search_warranties:'Search warranties...',
        no_warranties:'No warranties found',
        warranties_hint:'Warranties are added when you log an expense with warranty info',
        go_to_projects:'Go to Projects',
        // Project Nature
        project_nature:'Project Nature', nature_personal:'Personal', nature_commercial:'Commercial',
        // Smart upload
        smart_upload:'Smart Upload', upload_type:'Type',
        purchase_date:'Purchase Date', expiry_date:'Warranty Expiry Date',
        invoice_name:'Invoice Name', invoice_category:'Category', custom_note:'Note',
        contract_title:'Contract Title', signed_date:'Signed Date',
        custom_label:'Description', category:'Category',
        invoice_name_ph:'e.g. Electricity bill', category_ph:'e.g. Utilities',
        custom_category_ph:'Type a category...',
        contract_title_ph:'e.g. Lease contract', custom_label_ph:'e.g. Manufacturer warranty',
        expense_required:'Please select an expense to link this file',
        select_project_file:'Pick a project and file first',
        warning_title:'Warning', error_title:'Error', success_title:'Success',
        no_expense_link:'No expense link', no_expense_option:'No expense',
        last_1m:'Last 1M', last_3m:'Last 3M', last_6m:'Last 6M', last_12m:'Last 12M',
        analytics_title:'Expense Analytics', profit_loss_title:'Profit / Loss',
        total_in:'Income', total_out:'Expenses', net:'Net',
        margin:'Margin', avg_monthly:'Monthly Avg',
        // Project-detail dynamic strings
        pd_no_expenses_title:'No expenses yet',
        pd_no_expenses_desc:'Add your first expense to this project',
        pd_add_expense:'＋ Add expense',
        pd_no_warranties_title:'No warranties',
        pd_no_warranties_desc:'Add a warranty when creating a new expense',
        pd_no_files_title:'No files',
        pd_no_files_desc:'Files are uploaded automatically when adding an invoice or warranty',
        pd_warranty_ends_in:'Warranty ends', pd_remaining:'remaining',
        pd_expired_since:'Expired', pd_days:'days',
        w_status_expired:'Expired', w_status_expiring_days:'Ending in days',
        w_status_expiring_soon:'Ending soon', w_status_active:'Active',
        invoice_label:'Invoice', warranty_label:'Warranty',
        delete_expense_confirm:'Confirm delete',
        delete_expense_msg:'This will delete the expense and all its files (invoice/warranty).',
        deleted_ok:'Deleted', cannot_delete_short:'Could not delete',
        invalid_id:'Invalid ID',
        edit_expense_title:'Edit expense',
        title_label:'Title', amount_label:'Amount', vendor_label:'Vendor',
        saved_ok:'Saved', cannot_save:'Could not save',
        expense_not_found:'Expense not found',
        load_expenses_failed:'Could not load expenses',
        load_failed_short:'Load failed',
        add_invoice:'Add invoice', add_warranty:'Add warranty',
        update_status:'✏️ Update status', upload_file_btn:'☁️ Upload file',
        add_expense_btn:'＋ Add expense',
        total_cost:'Total cost', expenses_count:'Expenses', warranties_count:'Warranties',
        sar:'SAR',
        budget_label:'Budget', budget_warning:'⚠️ You have exceeded or are close to the budget limit!',
        tab_expenses:'📋 Expenses', tab_warranties:'🛡️ Warranties', tab_files:'📁 Files',
        modal_add_expense:'＋ Add expense / cost',
        expense_name:'Expense title', expense_name_ph:'e.g. Buy new tires',
        amount_sar:'Amount (SAR)',
        purchase_date_field:'Purchase date',
        vendor_field:'Vendor', vendor_ph:'Shop or company name',
        upload_invoice_label:'Upload invoice (image or PDF)', upload_hint_500:'Up to 500MB',
        add_warranty_for_expense:'Add a warranty for this expense',
        warranty_start:'Warranty start', warranty_end_field:'Warranty end',
        notes_label:'Notes', notes_ph:'Any additional details...',
        save_expense:'Save expense ✓',
        modal_update_status:'✏️ Update project status',
        status_label:'Status',
        status_active_o:'🟢 Active', status_done_o:'✅ Done', status_archived_o:'📦 Archived',
        sell_price_sar:'Sell price (SAR)', sell_price_hint:'Leave 0 if not sold yet',
        save_short:'Save ✓',
        // Support loop (Mizan 3.0)
        my_tickets:'My Tickets', refresh:'Refresh',
        view_my_tickets:'View My Tickets',
        ticket_open:'Open', ticket_replied:'Replied',
        no_tickets_title:'No tickets yet',
        no_tickets_desc:'Send a message via the Contact tab',
        support_reply:'Support Reply',
        // Clone project
        clone_project:'Clone Project', project_cloned:'Project cloned',
        cloning:'Cloning...',
        // Export feedback
        download_complete:'Download complete',
        preparing_zip:'Preparing ZIP...',
        // Password update
        pass_min_8:'Password must be at least 8 characters',
        // Dynamic error keys — auth pages
        err_session_expired:'Session expired, please try again',
        err_locked_out:'Too many attempts. Try again in 15 minutes',
        err_fields_required:'Please enter your email and password',
        err_invalid_credentials:'Invalid email or password',
        err_invalid_email_domain:'Invalid email format or domain does not exist',
        err_email_in_use:'This email is already registered',
        err_reg_rate_limited:'Too many registration attempts. Try again in 15 minutes',
        err_all_fields:'Please fill in all fields',
        err_admin_credentials:'Invalid credentials or account lacks admin privileges',
        err_unauthorized:'Unauthorized — please log in',
        err_csrf_invalid:'Invalid security token',
        // Dynamic toast keys — projects
        project_created:'Project created',
        project_updated:'Project updated',
        update_failed:'Update failed',
        project_not_found:'Project not found',
        share_link_failed:'Failed to create share link',
        share_link_copied:'Share link copied',
        // Swal modal labels — projects
        delete_project_title:'Delete project?',
        delete_permanently:'Delete',
        new_project_title:'New Project',
        edit_project_title:'Edit Project',
        project_name_swal:'Project Name',
        project_name_ph_swal:'e.g. Camry 2024',
        type_swal:'Type',
        nature_swal:'Nature',
        budget_swal:'Budget',
        template_swal:'Starter Template (optional)',
        no_template_opt:'No template',
        color_swal:'Color',
        optional_ph:'Optional',
        create_btn:'Create',
        save_edits_btn:'Save Changes',
        project_name_required_msg:'Project name is required',
        share_link_modal_title:'Share Link',
        close_btn:'Close',
        copy_link_prompt:'Copy the link:',
        suggested_expenses_head:'Suggested expenses — pick what to insert:',
        // Dynamic toast keys — project-detail
        file_too_large:'File exceeds 500 MB limit',
        file_type_unsupported:'Unsupported file type (images or PDF only)',
        expense_added_ok:'Expense added successfully ✓',
        expense_add_failed:'Could not add expense',
        project_status_updated:'Project status updated ✓',
        // Dynamic toast keys — files, settings, reports
        deleted_title:'Deleted',
        export_failed:'Export failed',
        delete_account_failed:'Could not delete account',
        report_exported:'Report exported',
        // Dynamic toast keys — support
        ticket_submitted:'Ticket submitted successfully ✅',
        write_message_required:'Please write a message',
        reply_sent_ok:'Reply sent ✅',
        select_rating:'Please select a rating',
        rating_submitted_ok:'Rating submitted, thank you ⭐',
        connection_error:'Connection error',
        // Settings API success/error messages
        profile_updated:'Profile updated',
        password_changed:'Password changed successfully',
        preferences_saved:'Preferences saved',
        err_name_email_required:'Name and email are required',
        err_invalid_email_api:'Invalid email or domain does not exist',
        err_name_too_long:'Name too long (max 100 characters)',
        err_email_taken:'Email is already used by another account',
        err_all_fields_api:'All fields are required',
        err_wrong_current_pass:'Current password is incorrect',
        err_generic_server:'An error occurred',
        // Phase 4 audit additions
        copied:'Copied!', upload_warranty:'Upload Warranty', create_ticket:'Create Ticket',
        faq_privacy:'FAQ & Privacy Policy',
        send_close:'Send & Close', reply_open:'Reply (Leave Open)',
        recent_status:'Status', recent_type:'Type',
        expense_date:'Date', expense_amount:'Amount',
        share_title:'Project Share', read_only:'Read-only',
        print_report:'Print Report',
        reports_summary:'Summary', total_spent:'Total Expenses',
        monthly_spending:'Monthly Spending', expense_breakdown:'Expense Breakdown',
        top_projects:'Top Projects', export_pdf:'Export PDF', export_csv:'Export CSV',
        invoices_title:'Invoices', invoice_number:'Invoice #', invoice_total:'Total',
        invoice_date:'Invoice Date', invoice_status:'Status',
        no_invoices:'No invoices yet', add_invoice:'Add Invoice',
        faq_q1:'How do I add a new project?',
        faq_a1:'From the dashboard click "New Project" and fill in the details.',
        faq_q2:'Is my data secure?',
        faq_a2:'Yes — all your data is encrypted and stored securely.',
        faq_q3:'How can I export my data?',
        faq_a3:'From the Settings page you can export all your data as JSON or ZIP.',
        privacy_policy:'Privacy Policy',
        privacy_text:'We respect your privacy. We do not share your data with any third parties, and you can delete your account at any time from the Settings page.',
        // Terms & Data Policy (support page)
        terms_of_service:'Terms of Service',
        terms_text:'Use of this system for any unlawful purpose is strictly prohibited. The platform is intended for personal and simple commercial financial management only.',
        data_retention:'Data Retention',
        data_retention_text:'Your data is encrypted. You may permanently delete your account at any time from the Settings page, which will erase all your files and invoices from our servers.',
        policies_section:'Policies & Terms',
        // Cookie consent banner
        cookie_title:'Cookie Preferences',
        cookie_desc:'We use only essential cookies to operate the platform and remember your preferences. No tracking or advertising cookies of any kind.',
        cookie_msg:'We use only essential cookies to operate the platform and remember your preferences.',
        cookie_accept:'Accept All', cookie_essential:'Essential Only',
        cookie_accept_all:'Accept All', cookie_essential_only:'Essential Only',
        cookie_toast_all:'All cookies accepted ✓', cookie_toast_essential:'Essential cookies only accepted ✓',
        // Support page — 8 detailed FAQs
        sp_faq_q1:'Is Mizan completely free?',
        sp_faq_a1:'Yes. Mizan is completely free with no hidden fees or paid plans at this time.',
        sp_faq_q2:'How do I export my data (CSV / ZIP)?',
        sp_faq_a2:'You can export any project as a ZIP file containing all attachments and invoices. You can also export expense reports as an Excel-compatible CSV from the Reports page.',
        sp_faq_q3:'How do warranty alerts work?',
        sp_faq_a3:'When you add a warranty date to any expense, Mizan monitors it and sends a dashboard notification 30 days before expiry, so you never miss a warranty claim.',
        sp_faq_q4:'How do I permanently delete my account?',
        sp_faq_a4:'From the Settings page, click "Delete Account Permanently". All your data (projects, expenses, files) will be immediately and irreversibly removed from our servers.',
        sp_faq_q5:'How secure is my data in the cloud?',
        sp_faq_a5:'Your data is stored on secured servers. All connections use HTTPS encryption. Passwords are hashed with bcrypt and are unreadable. No staff member can access your financial data.',
        sp_faq_q6:'How do I share a project with someone?',
        sp_faq_a6:'On the Projects page, click the share icon. A read-only link secured with a cryptographic token is generated. The recipient can view project details without signing in or modifying anything.',
        sp_faq_q7:'How does Mizan calculate profit and loss?',
        sp_faq_a7:'When you close a project and enter a sale price, Mizan automatically subtracts total recorded expenses from the sale price. A positive result is profit; a negative result is a loss.',
        sp_faq_q8:'Can I attach invoices and files to expenses?',
        sp_faq_a8:'Yes. You can upload invoice photos and documents (PDF, images, Excel, etc., up to 500 MB) and link them to any expense or project. A central Files page shows all your attachments in one place.',
        // Terms of Service
        tos_title:'Terms of Service',
        tos_c1_title:'Acceptable Use',
        tos_c1_body:'Mizan must not be used for any unlawful activities or operations. The platform is intended solely for legitimate personal and business financial management. Abusive use will result in immediate account suspension.',
        tos_c2_title:'Disclaimer',
        tos_c2_body:'Mizan is a spending tracking tool only and does not provide tax or legal advice. The platform bears no legal liability for any tax or financial calculation errors arising from the use of entered data.',
        tos_c3_title:'Password Security',
        tos_c3_body:'You are fully responsible for maintaining the confidentiality of your login credentials. Do not share your password with any third party. If you suspect a breach, change your password immediately and contact support.',
        // Privacy Policy & Data Retention
        pp_title:'Privacy Policy & Data Retention',
        pp_c1_title:'Data We Collect',
        pp_c1_body:'We collect only the data necessary to operate the service: your email address, name, and the financial data you input. We do not collect behavioral or browsing data of any kind.',
        pp_c2_title:'Third-Party Sharing & Ads',
        pp_c2_body:'Zero. We do not share your data with any third party under any circumstances. There are no ads, no tracking, and no external analytics of any kind.',
        pp_c3_title:'Retention & Deletion',
        pp_c3_body:'We retain your data as long as you have an active account. Upon account deletion, all your data (projects, expenses, files) is immediately and permanently purged from our servers with no possibility of recovery.',
        // Support page tab switcher & card subtitles
        sp_tab_info:'Information & Policies', sp_tab_contact:'Contact & Support',
        sp_faq_section_sub:'Common questions from Mizan users',
        sp_tos_sub:'The agreement governing your use of the Mizan platform',
        sp_pp_sub:'How we protect your data and how long we keep it',
        // Admin panel
        admin_panel:'Admin Panel', admin_dashboard:'Admin Dashboard',
        admin_dashboard_sub:'Manage users, support tickets, and activity logs',
        admin_total_users:'Total Users', admin_total_projects:'Total Projects',
        admin_total_expenses:'Total Expenses', admin_total_files:'Total Files',
        admin_open_tickets:'Open Tickets',
        admin_avg_satisfaction:'Avg. Ticket Satisfaction',
        admin_support_tickets:'Support Tickets',
        admin_col_user:'User', admin_col_subject:'Subject',
        admin_col_status:'Status', admin_col_date:'Date', admin_col_action:'Action',
        admin_reply_close:'Reply & Close', admin_reply_open:'Reply (Keep Open)',
        admin_send_close:'↩ Send & Close',
        admin_cancel:'Cancel', admin_write_reply:'Write your reply here... (visible to user)',
        admin_optional_note:'Optional: leave blank to close without reply',
        admin_ticket_open:'Open', admin_ticket_closed:'Closed',
        admin_replied:'Replied',
        admin_activity_log:'Activity Log',
        admin_activity_desc:'Activity log for all users',
        admin_download_log:'📥 Download Log (Excel)',
        admin_rows_per_page:'Rows per page:',
        admin_col_name:'Name', admin_col_action_type:'Action',
        admin_col_details:'Details', admin_col_datetime:'Date & Time', admin_col_ip:'IP',
        admin_announce_title:'Announcement',
        admin_announce_status_active:'Active', admin_announce_status_none:'None',
        admin_current_ann:'Current Announcement', admin_cancel_ann:'× Dismiss',
        admin_new_announce:'Publish a new announcement (displayed as a banner to all users)',
        admin_ann_ar_ph:'Arabic text (required)', admin_ann_en_ph:'English text (optional)',
        admin_publish:'Publish Announcement', admin_publish_note:'Visible to all users immediately',
        admin_recent_users:'Recent Registered Users',
        admin_col_reg_date:'Registration Date', admin_col_role:'Role',
        admin_role_admin:'Admin', admin_role_user:'User',
        admin_role_super_admin:'Super Admin',
        admin_promote_btn:'Promote to Admin', admin_demote_btn:'Revoke Admin',
        admin_open_label:'Open', admin_user_label:'User',
        admin_ann_active:'Active', admin_ann_none:'None',
        admin_csv_desc:'Download CSV with the last 50,000 records — Excel compatible (UTF-8 BOM)',
        log_user_login:'Login', log_user_logout:'Logout',
        log_create_project:'Create Project', log_export_data:'Export Data',
        admin_log_filter_user:'Filter by User', admin_all_users:'All Users',
        admin_log_filter_date:'Filter by Date', admin_select_date:'Pick a date',
        admin_filter_apply:'Apply', admin_filter_reset:'Reset',
        admin_no_tickets:'No support tickets yet',
        admin_toast_reply_sent:'Reply sent and ticket closed ✉️',
        admin_toast_reply_open:'Reply sent — ticket remains open',
        admin_toast_ann_published:'Announcement published to all users',
        admin_toast_ann_cleared:'Announcement cleared',
        // Admin login (T1)
        admin_badge:'Admin Access Only', admin_login_subtitle:'Control Panel — Restricted Access',
        admin_login_h1:'Admin Login', admin_email_label:'Email',
        admin_email_ph:'admin@example.com', admin_pass_label:'Password',
        admin_pass_ph:'••••••••', admin_login_btn:'🔐 Access Control Panel',
        admin_back:'← Return to Homepage',
        // Dashboard (T2-T8)
        admin_storage_uploads:'File Storage', admin_storage_db:'Database Size',
        admin_analytics_title:'Analytics & Statistics',
        admin_chart_ratings:'Ratings Distribution', admin_chart_satisfaction:'Satisfaction Score',
        admin_chart_projects:'Projects: Personal / Commercial', admin_chart_top_users:'Most Active Users',
        admin_feedback_title:'Customer Feedback', admin_no_feedback:'No written feedback yet',
        admin_ann_bg_color:'Background Color', admin_ann_text_color:'Text Color',
        admin_ann_font_size:'Font Size', admin_ann_preview:'Live Preview:',
        admin_ann_preview_placeholder:'Type text to see preview...',
        admin_reset_pw:'🔑 Reset Password', admin_send_email:'✉️ Send Email',
        admin_custom_email_title:'Send Custom Email', admin_to:'To:',
        admin_email_subject_ph:'Subject', admin_send_email_btn:'✉️ Send',
        admin_email_tester_title:'Email Template Tester',
        admin_et_tpl_welcome:'Welcome', admin_et_tpl_reset:'Reset Password',
        admin_et_tpl_warranty:'Warranty Alert', admin_et_tpl_budget:'Budget Alert',
        admin_et_lang_ar:'Arabic', admin_et_lang_en:'English',
        admin_et_target_ph:'Target email', admin_et_send_btn:'Send Test Email',
        admin_prev:'← Previous', admin_next:'Next →', admin_no_data:'No data',
        // V4.2 new analytics keys
        admin_total_budgets:'Budgets Managed',
        admin_sla_resolved:'Resolved Tickets', admin_avg_hours:'Avg', admin_hours:'hrs',
        admin_chart_monthly_title:'Monthly Expense Trend', admin_last_12:'Last 12 Months',
        admin_rating_tracker:'User Rating Tracker', admin_active_users:'users',
        admin_rt_user:'User', admin_rt_tickets:'Tickets', admin_rt_rated:'Rated',
        admin_rt_status:'Status', admin_rt_avg:'Avg Rating',
        admin_rt_badge_rated:'✓ Rated', admin_rt_badge_pending:'— Pending',
        // Logout page
        logout_page_title:'Sign Out',
        logout_confirm:'Are you sure you want to sign out of your Mizan account?',
        logout_data_saved:'Your data is saved and will be here when you return.',
        logout_btn:'Sign Out', logout_stay:'Stay Logged In',
        logout_countdown:"You'll be signed out automatically in", logout_sec:'seconds',
        // Support V4.3
        platform_guidelines:'Platform Guidelines', contact_support:'Contact & Support',
    }
};

// Strict alias — same dictionary, simpler name. Use either `i18n` or
// `translations`; they point at the same object.
const i18n = translations;
window.i18n = i18n;
window.translations = translations;

/**
 * Translation helper — use in inline JS: t('key')
 * Returns the translated string for the current language, or the key itself as fallback.
 */
function t(key) {
    const lang = localStorage.getItem('mizan_lang') || 'ar';
    return i18n[lang]?.[key] || i18n['ar']?.[key] || key;
}
window.t = t;

// Initializes the lang.
function initLang() {
    applyLang(localStorage.getItem('mizan_lang') || 'ar');
}

// Applies the lang.
function applyLang(lang) {
    if (lang !== 'ar' && lang !== 'en') lang = 'ar';
    localStorage.setItem('mizan_lang', lang);
    // Cookie mirror so PHP can render the right <html lang/dir> on the
    // first paint — prevents the page from briefly rendering in the
    // wrong locale before main.js runs.
    try { document.cookie = 'mizan_lang=' + lang + '; path=/; max-age=31536000; SameSite=Lax'; } catch (e) {}
    document.documentElement.setAttribute('lang', lang);
    document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');

    // Loop every [data-i18n] node in the DOM and swap its visible text.
    // For form fields with a placeholder, swap the placeholder instead.
    // For <input type="button|submit"> swap the value attribute.
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        const val = i18n[lang]?.[key];
        if (!val) return;
        const tag = el.tagName;
        if (tag === 'INPUT' && (el.type === 'button' || el.type === 'submit' || el.type === 'reset')) {
            el.value = val;
        } else if ((tag === 'INPUT' || tag === 'TEXTAREA') && el.hasAttribute('placeholder')) {
            el.placeholder = val;
        } else {
            // innerText preserves trim semantics; textContent is faster
            // and avoids reflow on hidden nodes. Use textContent.
            el.textContent = val;
        }
    });

    // Inputs whose visible label is rendered separately use
    // data-i18n-placeholder for placeholder-only translation.
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        const val = i18n[lang]?.[key];
        if (val) el.placeholder = val;
    });

    // data-i18n-title swaps the tooltip/title attribute.
    document.querySelectorAll('[data-i18n-title]').forEach(el => {
        const key = el.getAttribute('data-i18n-title');
        const val = i18n[lang]?.[key];
        if (val) el.setAttribute('title', val);
    });

    document.querySelectorAll('#langToggle').forEach(btn => {
        if (!btn) return;
        btn.textContent = lang === 'ar' ? 'EN' : 'ع';
        btn.title       = lang === 'ar' ? 'Switch to English' : 'التبديل للعربية';
    });

    // Notify any page that builds strings dynamically (project-detail tabs,
    // expense lists, etc.) so they can re-render with the new locale.
    try {
        window.dispatchEvent(new CustomEvent('mizan:lang-change', { detail: { lang } }));
    } catch (e) { /* CustomEvent not supported — skip */ }
}

// Toggles the lang.
function toggleLang() {
    const current = localStorage.getItem('mizan_lang') || 'ar';
    applyLang(current === 'ar' ? 'en' : 'ar');
}


// ============================================================
// 3. تنسيق الأرقام
// ============================================================
function getCurrencyCode() {
    const fromServer = (window.MIZAN_CURRENCY || '').toUpperCase();
    const fromLocal  = (localStorage.getItem('mizan_currency') || '').toUpperCase();
    return fromServer || fromLocal || 'SAR';
}
window.getCurrencyCode = getCurrencyCode;

// Returns the currency symbol.
function getCurrencySymbol() {
    const c = getCurrencyCode();
    const lang = (document.documentElement.lang || 'ar');
    if (c === 'SAR') return lang === 'en' ? 'SAR' : '﷼';
    if (c === 'USD') return '$';
    const symbols = { EUR: '€', GBP: '£', AED: 'د.إ', KWD: 'د.ك' };
    return symbols[c] || c;
}
window.getCurrencySymbol = getCurrencySymbol;

// Defines the setCurrency routine.
function setCurrency(code) {
    const next = (code || 'SAR').toUpperCase();
    localStorage.setItem('mizan_currency', next);
    window.MIZAN_CURRENCY = next;
    refreshCurrencyUI();
    document.dispatchEvent(new CustomEvent('mizan:currency-change', { detail: { currency: next } }));
}
window.setCurrency = setCurrency;

// Refreshes the currency ui.
function refreshCurrencyUI() {
    const sym = getCurrencySymbol();
    document.querySelectorAll('.currency-symbol').forEach(el => { el.textContent = ' ' + sym; });
    applyAutoFormat();
}
window.refreshCurrencyUI = refreshCurrencyUI;
window.addEventListener('mizan:lang-change', refreshCurrencyUI);

// Formats the amount.
function formatAmount(number, showCurrency = true) {
    const currency = ' ' + getCurrencySymbol();
    const formatted = Number(number).toLocaleString('en-US', {
        minimumFractionDigits: 0, maximumFractionDigits: 2
    });
    return formatted + (showCurrency ? currency : '');
}

// Applies the auto format.
function applyAutoFormat() {
    document.querySelectorAll('.auto-format').forEach(el => {
        const raw = parseFloat(el.dataset.value || el.textContent);
        if (!isNaN(raw)) el.textContent = formatAmount(raw);
    });
}


// ============================================================
// 4. Sidebar (Mobile)
// ============================================================
function toggleSidebar() {
    const sidebar  = document.getElementById('mainSidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const icon     = document.getElementById('hamburgerIcon');
    if (!sidebar) return;

    const isOpen = sidebar.classList.toggle('open');
    overlay?.classList.toggle('show', isOpen);

    // Hamburger → X animation
    if (icon) {
        const spans = icon.querySelectorAll('span');
        if (isOpen) {
            spans[0].style.cssText = 'transform: translateY(6px) rotate(45deg)';
            spans[1].style.cssText = 'opacity: 0; transform: scaleX(0)';
            spans[2].style.cssText = 'transform: translateY(-6px) rotate(-45deg)';
        } else {
            spans.forEach(s => s.style.cssText = '');
        }
    }
}

// Closes the sidebar.
function closeSidebar() {
    document.getElementById('mainSidebar') ?.classList.remove('open');
    document.getElementById('sidebarOverlay')?.classList.remove('show');
    document.getElementById('hamburgerIcon')
        ?.querySelectorAll('span')
        .forEach(s => { s.style.cssText = ''; });
}


// ============================================================
// 5. المودال
// ============================================================
function openModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        // Focus أول input
        setTimeout(() => {
            const first = overlay.querySelector('input:not([type=hidden]), select, textarea');
            first?.focus();
        }, 350);
    }
}

// Closes the modal.
function closeModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// إغلاق بالضغط خارج المودال
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
});

// إغلاق بـ Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        // Close search overlay first if open
        const searchOv = document.getElementById('searchOverlay');
        if (searchOv?.classList.contains('open')) { closeSearch(); return; }
        document.querySelectorAll('.modal-overlay.open')
            .forEach(m => closeModal(m.id));
        closeNotifPanel();
    }
});


// ============================================================
// 6. Toast Notifications
// ============================================================
let toastContainer;

// Returns the toast container.
function getToastContainer() {
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    return toastContainer;
}

/**
 * showToast(message, type?, duration?)
 * Uses SweetAlert2 toast mixin when Swal is loaded (richer UI, progress bar,
 * pause-on-hover). Falls back to the custom DOM toast so pages that don't
 * load SweetAlert2 still work identically.
 * type: 'success' | 'error' | 'warning' | 'info'
 */
function showToast(message, type = 'success', duration = 3500) {
    if (typeof Swal !== 'undefined') {
        Swal.mixin({
            toast:             true,
            position:          document.documentElement.dir === 'rtl' ? 'top-start' : 'top-end',
            showConfirmButton: false,
            timer:             duration,
            timerProgressBar:  true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            },
        }).fire({ icon: type, title: String(message ?? '') });
        return;
    }

    // DOM fallback — no SweetAlert2 dependency
    const icons     = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const container = getToastContainer();
    const toast     = document.createElement('div');
    toast.className = `toast ${type}`;
    const iconEl    = document.createElement('span');
    iconEl.textContent = icons[type] || '•';
    const msgEl     = document.createElement('span');
    msgEl.textContent  = String(message ?? '');
    toast.appendChild(iconEl);
    toast.appendChild(msgEl);
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}


// ============================================================
// 6.1 Action Utilities
// ============================================================

/**
 * withLoading(buttonElement, asyncCallback, options?)
 *
 * Wraps any async action with:
 *   • Button disable + aria-busy spinner (via setButtonBusy)
 *   • Optional loading-label text injected into the button
 *   • Strict backend-envelope error catching: { success: false, error: "..." }
 *   • Network/parse error catching with a generic toast
 *   • Optional success toast
 *   • Button restoration in finally (always runs)
 *
 * The asyncCallback must return the parsed JSON object from the backend,
 * or throw on a network/parse failure.
 *
 * Example:
 *   await withLoading(btn, async () => {
 *     const res = await mizanFetch('/api/...', { method: 'POST', body: fd });
 *     return res.json();
 *   }, { successMessage: 'تم الحفظ بنجاح' });
 */
async function withLoading(buttonElement, asyncCallback, options = {}) {
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const {
        successMessage = null,
        loadingLabel   = isAr ? 'جاري التنفيذ...' : 'Processing...',
        errorMessage   = null,
    } = options;

    // Save original inner HTML so we can restore it even if it contains SVG/icons
    const originalHTML = buttonElement ? buttonElement.innerHTML : null;

    setButtonBusy(buttonElement, true);
    if (buttonElement && loadingLabel) {
        buttonElement.innerHTML =
            `<span class="btn-spinner" aria-hidden="true"></span>${loadingLabel}`;
    }

    try {
        const result = await asyncCallback();

        // Inspect the backend envelope: { success: false, error: "SMTP timeout..." }
        if (result && typeof result === 'object' && result.success === false) {
            const msg = result.error
                || (isAr ? 'حدث خطأ غير متوقع' : 'An unexpected error occurred');
            showToast(msg, 'error');
            return result;
        }

        if (successMessage) showToast(successMessage, 'success');
        return result;

    } catch (err) {
        // Network failure, DNS error, or explicit throw from the callback
        const msg = errorMessage
            || (isAr ? 'تعذّر الاتصال بالخادم' : 'Connection failed — please try again');
        showToast(msg, 'error');
        return null;

    } finally {
        setButtonBusy(buttonElement, false);
        // Restore original label (setButtonBusy only removes aria-busy/disabled;
        // it does not restore innerHTML when we overwrote it with loadingLabel)
        if (buttonElement && originalHTML !== null) {
            buttonElement.innerHTML = originalHTML;
        }
    }
}
window.withLoading = withLoading;


/**
 * confirmDestructiveAction(options?)
 *
 * SweetAlert2-powered confirmation dialog for irreversible actions.
 * Falls back to native window.confirm() when Swal is not available.
 * Returns Promise<boolean> — true means the user confirmed.
 *
 * Example:
 *   const ok = await confirmDestructiveAction({ title: 'حذف المشروع؟' });
 *   if (!ok) return;
 */
async function confirmDestructiveAction(options = {}) {
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const {
        title       = isAr ? 'هل أنت متأكد؟'                       : 'Are you sure?',
        text        = isAr ? 'لا يمكن التراجع عن هذا الإجراء.'     : 'This action cannot be undone.',
        confirmText = isAr ? 'نعم، تأكيد'                           : 'Yes, confirm',
        cancelText  = isAr ? 'إلغاء'                                : 'Cancel',
        icon        = 'warning',
    } = options;

    if (typeof Swal !== 'undefined') {
        const result = await Swal.fire({
            title,
            text,
            icon,
            showCancelButton:   true,
            confirmButtonText:  confirmText,
            cancelButtonText:   cancelText,
            confirmButtonColor: '#ef4444',
            reverseButtons:     document.documentElement.dir !== 'rtl',
            customClass:        { popup: 'mz-confirm-popup' },
        });
        return result.isConfirmed;
    }

    return window.confirm(`${title}\n${text}`);
}
window.confirmDestructiveAction = confirmDestructiveAction;


/**
 * copyToClipboard(text)
 *
 * Copies text to the clipboard with a guaranteed UX response:
 *   1. Navigator Clipboard API (modern, requires HTTPS/localhost)
 *   2. execCommand fallback (legacy browsers / HTTP)
 *   3. Swal input dialog as last resort so the user can copy manually
 * Always shows a success or error toast.
 * Returns Promise<boolean>.
 *
 * Example:
 *   await copyToClipboard(data.url);
 */
async function copyToClipboard(text) {
    const isAr       = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const successMsg = isAr ? 'تم نسخ الرابط بنجاح!'              : 'Link copied successfully!';
    const errorMsg   = isAr ? 'تعذّر النسخ — انسخ الرابط يدوياً' : 'Copy failed — please copy manually';

    // Path 1: modern Clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            showToast(successMsg, 'success');
            return true;
        } catch { /* fall through */ }
    }

    // Path 2: execCommand (deprecated but still widely supported)
    const ta = document.createElement('textarea');
    ta.value      = text;
    ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;pointer-events:none;';
    document.body.appendChild(ta);
    ta.select();
    const ok = document.execCommand('copy');
    document.body.removeChild(ta);

    if (ok) {
        showToast(successMsg, 'success');
        return true;
    }

    // Path 3: manual-copy fallback via Swal input
    if (typeof Swal !== 'undefined') {
        await Swal.fire({
            title:            isAr ? 'انسخ الرابط' : 'Copy the link',
            input:            'text',
            inputValue:       text,
            inputAttributes:  { readonly: true, style: 'direction:ltr;text-align:left;font-size:13px;' },
            confirmButtonText: isAr ? 'تم' : 'Done',
            didOpen: (popup) => { popup.querySelector('.swal2-input')?.select(); },
        });
    } else {
        showToast(errorMsg, 'warning');
    }
    return false;
}
window.copyToClipboard = copyToClipboard;


// ============================================================
// 7. لوحة التنبيهات
// ============================================================
function toggleNotifPanel() {
    const panel   = document.getElementById('notifPanel');
    const overlay = document.getElementById('notifOverlay');
    if (!panel) return;

    // Close user dropdown when opening notifications
    document.querySelector('.navbar-user')?.classList.remove('open');

    const isOpen = panel.classList.toggle('open');
    overlay?.classList.toggle('show', isOpen);
    if (isOpen) loadNotifications();
}

// Closes the notif panel.
function closeNotifPanel() {
    document.getElementById('notifPanel')?.classList.remove('open');
    document.getElementById('notifOverlay')?.classList.remove('show');
}

// Loads the notifications.
function loadNotifications() {
    const list = document.getElementById('notifList');
    if (!list) return;

    fetch('/api/notifications.php?action=list')
        .then(r => r.json())
        .then(data => {
            const items = Array.isArray(data.notifications) ? data.notifications : [];
            const badge = document.querySelector('.notif-badge');
            if (data.unread_count !== undefined && badge) {
                badge.textContent = data.unread_count;
                badge.style.display = data.unread_count > 0 ? '' : 'none';
            }
            if (!items.length) {
                list.textContent = '';
                const empty = document.createElement('div');
                empty.className = 'notif-loading';
                empty.textContent = t('no_notifications');
                list.appendChild(empty);
                return;
            }
            list.textContent = '';
            items.forEach(n => {
                const item = document.createElement('div');
                item.className = `notif-item ${n.is_read == 0 ? 'unread' : ''}`;
                item.dataset.id = String(n.id ?? '');
                item.style.cursor = 'pointer';

                const msgEl = document.createElement('div');
                msgEl.textContent = String(n.message ?? '');
                const timeEl = document.createElement('div');
                timeEl.style.fontSize = '11px';
                timeEl.style.color = 'var(--text-muted)';
                timeEl.style.marginTop = '3px';
                timeEl.textContent = String(n.ago ?? n.created_at ?? '');

                item.appendChild(msgEl);
                item.appendChild(timeEl);
                item.addEventListener('click', () => {
                    if (n.is_read == 0) {
                        mizanFetch(`/api/notifications.php?action=markRead&id=${n.id}`, { method: 'POST' })
                            .then(() => { item.classList.remove('unread'); n.is_read = 1; });
                    }
                });
                list.appendChild(item);
            });
        })
        .catch(() => {
            list.textContent = '';
            const fail = document.createElement('div');
            fail.className = 'notif-loading';
            fail.textContent = t('cannot_load_notifs');
            list.appendChild(fail);
        });
}

// Defines the markAllRead routine.
function markAllRead() {
    mizanFetch('/api/notifications.php?action=markAllRead', { method: 'POST' })
        .then(() => {
            document.querySelectorAll('.notif-badge').forEach(b => b.remove());
            document.querySelectorAll('.notif-item').forEach(i => i.classList.remove('unread'));
            showToast('تم تحديد كل التنبيهات كمقروءة', 'success');
        });
}


// ============================================================
// 8. Scroll Reveal (للصفحات التي لا تستخدم IntersectionObserver داخلها)
// ============================================================
function initScrollReveal() {
    if (typeof IntersectionObserver === 'undefined') {
        document.querySelectorAll('.reveal').forEach(el => el.classList.add('visible'));
        return;
    }
    const obs = new IntersectionObserver((entries) => {
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                setTimeout(() => entry.target.classList.add('visible'), i * 60);
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08 });

    document.querySelectorAll('.reveal:not([data-observed])').forEach(el => {
        el.setAttribute('data-observed', '1');
        obs.observe(el);
    });
}


// ============================================================
// 9. Navbar Scroll Shadow
// ============================================================
function initNavbarScroll() {
    const navbar = document.getElementById('mainNavbar');
    if (!navbar) return;
    // RAF-throttled so the class toggle runs at most once per frame
    // instead of dozens of times per scroll event. passive:true keeps
    // the listener out of the scroll-blocking path.
    const onScroll = rafThrottle(() => {
        navbar.classList.toggle('scrolled', window.scrollY > 10);
    });
    window.addEventListener('scroll', onScroll, { passive: true });
}


// ============================================================
// 10. Global Search Overlay
// ============================================================
function openSearch() {
    const overlay = document.getElementById('searchOverlay');
    if (!overlay) return;
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    const input = document.getElementById('globalSearchInput');
    input.value = '';
    document.getElementById('searchResults').innerHTML =
        '<div class="search-hint">اكتب للبحث... أو استخدم <kbd>Ctrl+K</kbd> لفتح البحث</div>';
    setTimeout(() => input.focus(), 100);
}

// Closes the search.
function closeSearch() {
    const overlay = document.getElementById('searchOverlay');
    if (!overlay) return;
    overlay.classList.remove('open');
    document.body.style.overflow = '';
}

let searchDebounce = null;
// Handles the search input.
function handleSearchInput(e) {
    const q = e.target.value.trim();
    clearTimeout(searchDebounce);
    if (q.length < 2) {
        document.getElementById('searchResults').innerHTML =
            '<div class="search-hint">اكتب حرفين على الأقل للبحث...</div>';
        return;
    }
    searchDebounce = setTimeout(() => performSearch(q), 300);
}

// Defines the performSearch routine.
function performSearch(q) {
    const results = document.getElementById('searchResults');
    results.textContent = '';
    const loading = document.createElement('div');
    loading.className = 'search-hint';
    loading.textContent = 'جاري البحث...';
    results.appendChild(loading);

    mizanFetch(`/api/projects.php?action=search&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.results?.length) {
                const hint = document.createElement('div');
                hint.className = 'search-hint';
                hint.textContent = 'لا توجد نتائج لـ "' + String(q) + '"';
                results.textContent = '';
                results.appendChild(hint);
                return;
            }
            results.textContent = '';
            d.results.forEach(r => {
                const a = document.createElement('a');
                const rawUrl = String(r.url ?? '');
                a.href = rawUrl.startsWith('/') ? rawUrl : '#';
                a.className = 'search-result-item';

                const icon = document.createElement('span');
                icon.className = 'search-result-icon';
                icon.textContent = String(r.icon || '📄');

                const body = document.createElement('div');
                const title = document.createElement('div');
                title.className = 'search-result-title';
                title.textContent = String(r.title ?? '');
                const meta = document.createElement('div');
                meta.className = 'search-result-meta';
                meta.textContent = String(r.meta ?? '');

                body.appendChild(title);
                body.appendChild(meta);
                a.appendChild(icon);
                a.appendChild(body);
                results.appendChild(a);
            });
        })
        .catch(() => {
            results.textContent = '';
            const fail = document.createElement('div');
            fail.className = 'search-hint';
            fail.textContent = 'تعذّر البحث';
            results.appendChild(fail);
        });
}


// ============================================================
// 11. Keyboard Shortcuts
// ============================================================
function initKeyboardShortcuts() {
    document.addEventListener('keydown', e => {
        // Ctrl+K / Cmd+K → Open Search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const overlay = document.getElementById('searchOverlay');
            if (overlay?.classList.contains('open')) closeSearch();
            else openSearch();
            return;
        }
        // Alt+N → New Project modal (if showCreateModal exists)
        if (e.altKey && e.key === 'n') {
            e.preventDefault();
            if (typeof showCreateModal === 'function') showCreateModal();
            else if (typeof openModal === 'function') {
                const m = document.getElementById('addExpenseModal') || document.getElementById('createProjectModal');
                if (m) openModal(m.id);
            }
            return;
        }
    });

    // Search overlay: close on click outside
    const overlay = document.getElementById('searchOverlay');
    if (overlay) {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) closeSearch();
        });
    }

    // Search input listener (overlay)
    const searchInput = document.getElementById('globalSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearchInput);
    }
}


// ============================================================
// 11.5 Navbar Global Search — 300ms debounce + dropdown results
// ============================================================
let navSearchDebounce = null;
let navSearchActiveIdx = -1;

// Initializes the nav global search.
function initNavGlobalSearch() {
    const input = document.getElementById('navGlobalSearch');
    const dd    = document.getElementById('navSearchDropdown');
    const wrap  = document.getElementById('globalSearchWrap');
    if (!input || !dd || !wrap) return;

    // Focus: seed with hint state
    input.addEventListener('focus', () => {
        const v = input.value.trim();
        if (v.length < 2) dd.innerHTML = `<div class="gs-hint">${t('search_hint')||'اكتب حرفين على الأقل...'}</div>`;
        openNavDropdown();
    });

    // Debounced input (matches Anthropic/GitHub search-as-you-type pattern)
    input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(navSearchDebounce);
        navSearchActiveIdx = -1;

        if (q.length < 2) {
            dd.innerHTML = `<div class="gs-hint">${t('search_hint')||'اكتب حرفين على الأقل...'}</div>`;
            openNavDropdown();
            return;
        }

        dd.innerHTML = `<div class="gs-hint"><span class="gs-spinner"></span>${t('loading')||'جاري البحث...'}</div>`;
        openNavDropdown();

        navSearchDebounce = setTimeout(() => performNavSearch(q), 300);
    });

    // Keyboard navigation
    input.addEventListener('keydown', e => {
        const items = dd.querySelectorAll('.gs-item');
        if (e.key === 'Escape') { input.blur(); closeNavDropdown(); return; }
        if (!items.length) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            navSearchActiveIdx = (navSearchActiveIdx + 1) % items.length;
            updateNavActive(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            navSearchActiveIdx = (navSearchActiveIdx - 1 + items.length) % items.length;
            updateNavActive(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const target = navSearchActiveIdx >= 0 ? items[navSearchActiveIdx] : items[0];
            if (target) window.location.href = target.getAttribute('href');
        }
    });

    // Close on outside click
    document.addEventListener('click', e => {
        if (!wrap.contains(e.target)) closeNavDropdown();
    });
}

// Updates the nav active.
function updateNavActive(items) {
    items.forEach((el, i) => el.classList.toggle('active', i === navSearchActiveIdx));
    if (navSearchActiveIdx >= 0) items[navSearchActiveIdx].scrollIntoView({ block: 'nearest' });
}

// Opens the nav dropdown.
function openNavDropdown()  { document.getElementById('navSearchDropdown')?.classList.add('open'); }
// Closes the nav dropdown.
function closeNavDropdown() { document.getElementById('navSearchDropdown')?.classList.remove('open'); }

// Defines the performNavSearch routine.
function performNavSearch(q) {
    const dd = document.getElementById('navSearchDropdown');
    if (!dd) return;

    mizanFetch(`/api/projects.php?action=search&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.results?.length) {
                dd.innerHTML = `<div class="gs-empty">🔍 ${t('no_results')||'لا توجد نتائج'} "${escHtml(q)}"</div>`;
                return;
            }
            // Group by kind
            const groups = { project: [], expense: [], file: [] };
            d.results.forEach(r => (groups[r.kind || 'project'] || groups.project).push(r));
            const heads = {
                project: t('projects')   || 'المشاريع',
                expense: t('expenses')   || 'العمليات',
                file:    t('files')      || 'الملفات',
            };
            let html = '';
            Object.entries(groups).forEach(([k, rows]) => {
                if (!rows.length) return;
                html += `<div class="gs-group-head">${heads[k]}</div>`;
                html += rows.map(r => `
                    <a href="${escHtml(r.url)}" class="gs-item" data-kind="${escHtml(r.kind || '')}">
                        <span class="gs-icon-wrap">${escHtml(r.icon || '📄')}</span>
                        <div class="gs-item-body">
                            <div class="gs-item-title">${escHtml(r.title)}</div>
                            <div class="gs-item-meta">${escHtml(r.meta || '')}</div>
                        </div>
                    </a>`).join('');
            });
            dd.innerHTML = html;
        })
        .catch(() => {
            dd.innerHTML = `<div class="gs-empty">⚠️ ${t('search_failed')||'تعذّر البحث'}</div>`;
        });
}

// Defines the escHtml routine.
function escHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[c]));
}


// ============================================================
// 11.55 Global stagger-enter initializer
//       Any element marked .stagger-enter gets its children
//       indexed via --i so the CSS cascade picks up the delay.
// ============================================================
function initStaggerEnter() {
    document.querySelectorAll('.stagger-enter').forEach(container => {
        Array.from(container.children).forEach((child, idx) => {
            // Respect explicit overrides: only set if not already set.
            if (!child.style.getPropertyValue('--i')) {
                child.style.setProperty('--i', idx);
            }
        });
    });
}


// ============================================================
// 11.6 Global "Create Project" trigger — shim that works from any page.
//      If projects.php is the current page, call the local modal directly.
//      Otherwise, navigate to projects.php?new=1 and let it auto-open.
// ============================================================
window.openProjectCreate = function openProjectCreate() {
    if (typeof window.showCreateModal === 'function') {
        window.showCreateModal();
        return;
    }
    window.location.href = '/pages/projects.php?new=1';
};

// Unified modal-close helper — closes SweetAlert2 popups AND native .modal-overlay,
// destroys any leftover SweetAlert backdrop, and unlocks body scroll. Safe to
// call repeatedly; used as a defensive cleanup after Swal cancel/confirm so
// the dashboard "create project" flow can never leave the page frozen.
window.closeModals = function closeModals() {
    document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
    document.querySelectorAll('#smartUploadModal.active').forEach(m => m.classList.remove('active'));
    if (typeof window.Swal !== 'undefined') {
        try { window.Swal.close(); } catch (e) {}
    }
    document.querySelectorAll('.swal2-container').forEach(c => c.remove());
    document.body.classList.remove('swal2-shown', 'swal2-height-auto');
    document.documentElement.classList.remove('swal2-shown', 'swal2-height-auto');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
};


// ============================================================
// 11.65 Global Share Link — copies a read-only share URL to the
//       clipboard. Caller passes (projectId, createdAt) so we can
//       request a server-issued share token from the API
//       expects. Falls back to a Swal text input on browsers that
//       block clipboard.writeText (or non-secure contexts).
// ============================================================
window.generateShareLink = async function generateShareLink(projectId, createdAt) {
    const id = parseInt(projectId, 10);
    if (!id) {
        if (typeof showToast === 'function') showToast('بيانات المشروع ناقصة', 'error');
        return null;
    }
    let url = null;
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';

    try {
        const res = await mizanFetch('/api/projects.php?action=create_share_link', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ project_id: id })
        });
        const data = await res.json();
        if (!data.success || !data.url) {
            if (typeof showToast === 'function') showToast(data.error || (isAr ? 'فشل إنشاء رابط المشاركة' : 'Failed to create share link'), 'error');
            return null;
        }
        url = String(data.url);
    } catch (err) {
        if (typeof showToast === 'function') showToast((isAr ? 'فشل إنشاء الرابط: ' : 'Failed to create link: ') + err.message, 'error');
        return null;
    }

    await copyToClipboard(url);
    return url;
};


// ============================================================
// 11.7 Global Export ZIP — accepts either exportZip(id) or
//      exportZip(event, id). Uses fetch() + Blob so the browser
//      stays on the page; if the server returns a JSON error
//      (no files / php_zip not installed / permission denied),
//      we surface it as a toast instead of navigating to a
//      broken JSON page. Falls back to window.location.href on
//      truly ancient browsers.
// ============================================================
window.exportZip = async function exportZip(a, b) {
    let id, triggerBtn = null;
    if (a && typeof a.preventDefault === 'function') {
        try { a.stopPropagation(); } catch (e) {}
        id = b;
        triggerBtn = a.currentTarget || a.target;
    } else {
        id = a;
    }
    id = parseInt(id, 10);
    if (!id) {
        if (typeof showToast === 'function') showToast('معرّف المشروع غير صالح', 'error');
        return;
    }

    const url  = `/api/export_zip.php?project_id=${id}`;
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';

    // Fallback for environments without fetch/Blob
    if (typeof fetch !== 'function' || typeof Blob === 'undefined') {
        window.location.href = url;
        return;
    }

    // Visual feedback on the trigger button
    if (triggerBtn && triggerBtn.tagName) {
        if (typeof setButtonBusy === 'function') setButtonBusy(triggerBtn, true);
    }
    if (typeof showToast === 'function') {
        showToast(isAr ? '📦 جاري تجهيز ملف ZIP...' : '📦 Preparing ZIP...', 'info', 3000);
    }

    try {
        const res = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        const ct = (res.headers.get('Content-Type') || '').toLowerCase();
        if (!res.ok || ct.includes('application/json')) {
            let msg = isAr ? 'تعذّر تصدير ZIP' : 'ZIP export failed';
            try { const d = await res.json(); if (d?.error) msg = d.error; } catch (e) {}
            if (typeof showToast === 'function') showToast(msg, 'error');
            return;
        }

        const blob      = await res.blob();
        const objectUrl = URL.createObjectURL(blob);

        let filename = `mizan_project_${id}.zip`;
        const cd = res.headers.get('Content-Disposition') || '';
        const m  = cd.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
        if (m?.[1]) filename = decodeURIComponent(m[1].replace(/"/g, ''));

        const link = document.createElement('a');
        link.href = objectUrl; link.download = filename; link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        setTimeout(() => { document.body.removeChild(link); URL.revokeObjectURL(objectUrl); }, 1500);

        if (typeof showToast === 'function') {
            showToast(isAr ? '✅ تم التحميل بنجاح' : '✅ Download complete', 'success', 4000);
        }
    } catch (err) {
        if (typeof showToast === 'function') {
            showToast((isAr ? 'فشل التصدير: ' : 'Export failed: ') + err.message, 'error');
        }
    } finally {
        if (triggerBtn && typeof setButtonBusy === 'function') setButtonBusy(triggerBtn, false);
    }
};


// ============================================================
// 12. User Dropdown Toggle + Click-Outside-to-Close
// ============================================================
function toggleUserMenu() {
    const el = document.querySelector('.navbar-user');
    if (!el) return;
    // Close notifications panel before toggling user menu
    closeNotifPanel();
    el.classList.toggle('open');
}

// Initializes the user dropdown.
function initUserDropdown() {
    document.addEventListener('click', e => {
        const userEl  = document.querySelector('.navbar-user');
        const notifEl = document.getElementById('notifPanel');
        const notifBtn = e.target.closest('[onclick*="toggleNotifPanel"], #notifBtn, .notif-btn');
        if (userEl && !userEl.contains(e.target)) {
            userEl.classList.remove('open');
        }
        // Close notif panel on outside click (but not when clicking the toggle button itself)
        if (notifEl && notifEl.classList.contains('open') && !notifEl.contains(e.target) && !notifBtn) {
            closeNotifPanel();
        }
    });
}


// ============================================================
// 12.4 Button busy-state helper — prevents double-submit, surfaces
// loading via aria-busy + a small CSS spinner (.btn[aria-busy="true"]).
// Usage: setButtonBusy(btn, true) to start; setButtonBusy(btn, false)
// when the operation finishes (success or error). Stores the original
// label in dataset so it can be restored without losing translation.
// ============================================================
function setButtonBusy(btn, busy = true) {
    if (!btn) return;
    if (busy) {
        if (!btn.dataset.idleLabel) {
            btn.dataset.idleLabel = btn.innerHTML;
        }
        btn.setAttribute('aria-busy', 'true');
        btn.disabled = true;
        // Keep accessible name; the spinner is purely decorative.
        // We don't replace text — the CSS overlays a spinner.
    } else {
        btn.removeAttribute('aria-busy');
        btn.disabled = false;
    }
}
window.setButtonBusy = setButtonBusy;

/** Auto-bind native <form data-mz-busy> — disable submit on submit so
 *  hammering the Enter key can't fire two POSTs. The form is re-enabled
 *  on the page's bfcache restore (pageshow). */
function initFormDoubleSubmitGuard() {
    document.querySelectorAll('form[data-mz-busy]').forEach(form => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('button[type="submit"], input[type="submit"]')
                .forEach(b => setButtonBusy(b, true));
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('button[aria-busy="true"], input[aria-busy="true"]')
            .forEach(b => setButtonBusy(b, false));
    });
}


// ============================================================
// 12.5 Idle auto-logout — 15 minutes of no activity → /logout.php
// ------------------------------------------------------------
// Bound to mouse, keyboard, touch, and scroll events. Skipped on
// pages that have no session (welcome.php has no main.js loaded
// for unauth flow, and logout.php handles the no-session case
// gracefully on redirect). Visible-tab gating prevents the timer
// from firing while the tab is backgrounded — wakes are very
// imprecise on long-idle background tabs.
// ============================================================
const IDLE_TIMEOUT_MS = 15 * 60 * 1000; // 15 minutes
let _idleTimer = null;

// Defines the  idleLogout routine.
function _idleLogout() {
    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';
    const msg  = isAr ? 'تم تسجيل الخروج لعدم النشاط' : 'Logged out due to inactivity';
    if (typeof showToast === 'function') showToast(msg, 'warning', 2800);
    setTimeout(() => {
        try { window.location.href = '/logout.php?reason=idle'; }
        catch (e) { window.location = '/logout.php'; }
    }, 2800);
}

// Resets the idle timer.
function resetIdleTimer() {
    if (_idleTimer) clearTimeout(_idleTimer);
    _idleTimer = setTimeout(_idleLogout, IDLE_TIMEOUT_MS);
}

// Initializes the idle timer.
function initIdleTimer() {
    // Skip on welcome / share pages (no session). We detect by the
    // CSRF meta tag, which header.php emits only for authenticated
    // pages. Auth pages (login/register/forgot/reset) include main.js
    // but have no CSRF meta — they're safe to skip.
    if (!document.querySelector('meta[name="csrf-token"]')) return;

    const events = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'wheel', 'click'];
    events.forEach(ev => {
        window.addEventListener(ev, resetIdleTimer, { passive: true });
    });

    // Pause/resume on tab visibility change so a backgrounded tab
    // doesn't keep counting toward the 15-minute timeout.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') resetIdleTimer();
        else if (_idleTimer) { clearTimeout(_idleTimer); _idleTimer = null; }
    });

    resetIdleTimer();
}


// ============================================================
// 13. Network Particle Canvas Background
// ============================================================
function initBgCanvas() {
    // Auto-inject the canvas on pages that didn't render one in markup
    // (auth pages, etc.) so the particle network is visible everywhere.
    let cv = document.getElementById('bg-canvas');
    if (!cv) {
        cv = document.createElement('canvas');
        cv.id = 'bg-canvas';
        // Insert as the first child of <body> so it sits behind everything.
        if (document.body.firstChild) {
            document.body.insertBefore(cv, document.body.firstChild);
        } else {
            document.body.appendChild(cv);
        }
    }
    const ctx = cv.getContext('2d');
    let pts = [];

    // Defines the resize routine.
    function resize() {
        cv.width  = innerWidth;
        cv.height = innerHeight;
    }

    // Initializes the pts.
    function initPts() {
        pts = [];
        const n = Math.floor((innerWidth * innerHeight) / 14000);
        for (let i = 0; i < n; i++) pts.push({
            x:  Math.random() * innerWidth,
            y:  Math.random() * innerHeight,
            r:  Math.random() * 2 + .5,
            vx: (Math.random() - .5) * .3,
            vy: (Math.random() - .5) * .3,
            a:  Math.random() * .5 + .1
        });
    }

    resize();
    initPts();
    // Debounced resize: rebuilding the particle field on every
    // continuous resize event (drag-resize, devtools open) wastes
    // CPU; debounce until the user stops resizing for 150ms.
    window.addEventListener('resize', debounce(() => { resize(); initPts(); }, 150));

    // Smooth fade-in: paint one frame before revealing to avoid flash
    requestAnimationFrame(() => {
        requestAnimationFrame(() => cv.classList.add('ready'));
    });

    // Renders.
    function draw() {
        ctx.clearRect(0, 0, cv.width, cv.height);
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        // Per the manually-applied SaaS rework: light mode uses muted slate
        // (no green dominance); dark mode keeps existing mint identity.
        const c = dark ? '52,211,153' : '71,85,105';

        for (let i = 0; i < pts.length; i++) {
            for (let j = i + 1; j < pts.length; j++) {
                const dx = pts[i].x - pts[j].x,
                      dy = pts[i].y - pts[j].y,
                      d  = Math.sqrt(dx * dx + dy * dy);
                if (d < 130) {
                    ctx.beginPath();
                    ctx.strokeStyle = `rgba(${c},${(.14 * (1 - d / 130)).toFixed(3)})`;
                    ctx.lineWidth = .6;
                    ctx.moveTo(pts[i].x, pts[i].y);
                    ctx.lineTo(pts[j].x, pts[j].y);
                    ctx.stroke();
                }
            }
        }

        pts.forEach(p => {
            p.x += p.vx; p.y += p.vy;
            if (p.x < 0) p.x = innerWidth;
            if (p.x > innerWidth) p.x = 0;
            if (p.y < 0) p.y = innerHeight;
            if (p.y > innerHeight) p.y = 0;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            const alpha = dark ? p.a : Math.min(0.55, p.a + 0.08);
            ctx.fillStyle = `rgba(${c},${alpha})`;
            ctx.fill();
        });

        requestAnimationFrame(draw);
    }
    draw();
}


// ============================================================
// 13.5 Smart Upload — unified modal API (used across all pages)
// ============================================================
const SmartUpload = {
    file: null,
    type: 'invoice',
    presetProject: null,
    presetExpense: null,
    onSuccess: null,
};

// Opens the smart upload.
function openSmartUpload(opts = {}) {
    const m = document.getElementById('smartUploadModal');
    if (!m) return;
    SmartUpload.file = null;
    SmartUpload.presetProject = opts.projectId || null;
    SmartUpload.presetExpense = opts.expenseId || null;
    SmartUpload.onSuccess = opts.onSuccess || null;

    setSmartUploadType(opts.type || 'invoice');

    const proj = document.getElementById('suProject');
    if (SmartUpload.presetProject) {
        proj.value = String(SmartUpload.presetProject);
        proj.disabled = true;
        suLoadExpenses(proj.value);
    } else {
        proj.disabled = false;
        proj.value = '';
        // Reset the expense <select> via the same safe-DOM path used by
        // suLoadExpenses(); avoids interpolating any string into innerHTML.
        const expSel = document.getElementById('suExpense');
        if (expSel) {
            suResetSelect(expSel);
            expSel.appendChild(suMakePlaceholderOption());
        }
    }

    document.getElementById('suPreview').style.display = 'none';
    // Preview is owner-controlled HTML (image data URL or icon tile we
    // build ourselves), but clear via textContent for consistency.
    document.getElementById('suPreview').textContent = '';
    document.getElementById('suDropZone').style.display = '';
    document.getElementById('suProgress').style.display = 'none';
    document.getElementById('suFill').style.width = '0%';
    document.getElementById('suSubmitBtn').disabled = false;
    document.getElementById('suFileInput').value = '';

    m.classList.add('active');
}

// Closes the smart upload.
function closeSmartUpload() {
    const m = document.getElementById('smartUploadModal');
    if (m) m.classList.remove('active');
}

// Defines the setSmartUploadType routine.
function setSmartUploadType(type) {
    SmartUpload.type = type;
    document.querySelectorAll('#smartUploadModal .su-type-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.type === type);
    });
    ['Invoice','Warranty','Contract','Other'].forEach(t => {
        const el = document.getElementById('suFields' + t);
        if (el) el.style.display = (t.toLowerCase() === type) ? '' : 'none';
    });
    // Warranty/invoice need an expense; contract/other are project-level
    const grp = document.getElementById('suExpenseGroup');
    if (grp) grp.style.display = (type === 'invoice' || type === 'warranty') ? '' : 'none';
}

// Build the placeholder "no expense link" option via safe DOM APIs.
// textContent prevents an attacker-controlled translation value (or future
// dictionary additions) from escaping the option element.
function suMakePlaceholderOption() {
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = t('no_expense_link') || '';
    return opt;
}

// Replace every child of <select> in one pass — safer and faster than
// looping removeChild, and avoids the innerHTML='' string parser.
function suResetSelect(sel) {
    while (sel.firstChild) sel.removeChild(sel.firstChild);
}

// Defines the suLoadExpenses routine.
function suLoadExpenses(pid) {
    const sel = document.getElementById('suExpense');
    if (!sel) return;

    // Empty / no-project state: just the placeholder.
    if (!pid) {
        suResetSelect(sel);
        sel.appendChild(suMakePlaceholderOption());
        return;
    }

    mizanFetch(`/api/projects.php?action=expenses&project_id=${pid}`)
        .then(r => r.json())
        .then(d => {
            const preset = SmartUpload.presetExpense;
            suResetSelect(sel);
            sel.appendChild(suMakePlaceholderOption());

            // Each expense title comes from user input (project owner's
            // own expense names). Treat it as untrusted: build the option
            // node and assign textContent so any HTML/script payload in
            // the title is rendered inert.
            (d.expenses || []).forEach(e => {
                const opt = document.createElement('option');
                opt.value       = String(e.id ?? '');
                opt.textContent = String(e.title ?? '');
                if (preset != null && String(preset) === String(e.id)) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            });

            if (preset) sel.disabled = true;
        })
        .catch(() => {
            suResetSelect(sel);
            sel.appendChild(suMakePlaceholderOption());
        });
}

// Defines the suHandleSelect routine.
function suHandleSelect(input) { if (input.files[0]) suSetFile(input.files[0]); }
// Defines the suHandleDrop routine.
function suHandleDrop(e) {
    e.preventDefault();
    document.getElementById('suDropZone').classList.remove('drag-over');
    if (e.dataTransfer.files[0]) suSetFile(e.dataTransfer.files[0]);
}
const SU_MAX_BYTES = 500 * 1024 * 1024; // 500 MB

// Defines the suSetFile routine.
function suSetFile(file) {
    if (file.size > SU_MAX_BYTES) {
        if (typeof Swal !== 'undefined') {
            Swal.fire(t('error_title')||'خطأ', `الملف أكبر من 500 MB (${suFmtSize(file.size)})`, 'error');
        } else {
            alert(`الملف أكبر من 500 MB (${suFmtSize(file.size)})`);
        }
        document.getElementById('suFileInput').value = '';
        return;
    }
    SmartUpload.file = file;
    const prev = document.getElementById('suPreview');
    prev.style.display = 'block';

    if (file.type.startsWith('image/')) {
        // Image preview: FileReader emits a data: URL; build the <img>
        // node and set src via the property setter (not innerHTML) so
        // even a contrived data URL string can't break out of the tag.
        const r = new FileReader();
        r.onload = ev => {
            prev.textContent = '';
            const img = document.createElement('img');
            img.src = String(ev.target.result || '');
            img.style.maxHeight    = '140px';
            img.style.borderRadius = '8px';
            prev.appendChild(img);
        };
        r.readAsDataURL(file);
    } else {
        // Generic file tile: file.name comes from the user's local file
        // picker — treat as untrusted (a co-located attacker could plant
        // a file named "<img onerror=…>"). Build the tree via createElement
        // and set every label with textContent.
        const ext   = (file.name.split('.').pop() || '').toLowerCase();
        const icons = { pdf:'📕', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊', zip:'📦', rar:'📦' };

        prev.textContent = '';
        const wrap = document.createElement('div');
        wrap.className = 'up-file-info';

        const iconEl = document.createElement('span');
        iconEl.style.fontSize = '24px';
        iconEl.textContent = icons[ext] || '📄';

        const body  = document.createElement('div');
        const name  = document.createElement('div');
        name.style.fontWeight = '700';
        name.textContent = String(file.name || '');

        const size  = document.createElement('div');
        size.style.fontSize = '11px';
        size.style.color    = 'var(--mut)';
        size.textContent = suFmtSize(file.size);

        body.appendChild(name);
        body.appendChild(size);
        wrap.appendChild(iconEl);
        wrap.appendChild(body);
        prev.appendChild(wrap);
    }

    document.getElementById('suDropZone').style.display = 'none';
}
// Defines the suFmtSize routine.
function suFmtSize(b) {
    if (b >= 1073741824) return (b/1073741824).toFixed(1)+' GB';
    if (b >= 1048576)    return (b/1048576).toFixed(1)+' MB';
    if (b >= 1024)       return (b/1024).toFixed(1)+' KB';
    return b + ' B';
}

// Defines the submitSmartUpload routine.
function submitSmartUpload() {
    const pid = document.getElementById('suProject').value;
    if (!pid || !SmartUpload.file) {
        if (typeof Swal !== 'undefined') Swal.fire(t('warning_title')||'تنبيه', t('select_project_file')||'اختر المشروع والملف', 'warning');
        else alert(t('select_project_file')||'اختر المشروع والملف');
        return;
    }
    const eid = document.getElementById('suExpense').value || 0;
    const type = SmartUpload.type;

    // Expense link is OPTIONAL for every upload type — the label
    // says "(اختياري)" / "(optional)", so we trust the user. The
    // API also accepts a missing expense_id and stores the file at
    // the project level.

    const fd = new FormData();
    fd.append('file', SmartUpload.file);
    fd.append('type', (type === 'contract' || type === 'other') ? 'file' : type);
    fd.append('project_id', pid);
    fd.append('expense_id', eid);

    // Type-specific metadata
    if (type === 'invoice') {
        fd.append('invoice_name', document.getElementById('suInvoiceName').value || '');
        fd.append('invoice_category', document.getElementById('suInvoiceCategory').value || '');
        fd.append('price', document.getElementById('suInvoicePrice')?.value || '');
    } else if (type === 'warranty') {
        fd.append('purchase_date', document.getElementById('suPurchaseDate').value || '');
        fd.append('expiry_date', document.getElementById('suExpiryDate').value || '');
        fd.append('price', document.getElementById('suWarrantyPrice')?.value || '');
    } else if (type === 'contract') {
        fd.append('contract_title', document.getElementById('suContractTitle').value || '');
        fd.append('signed_date', document.getElementById('suSignedDate').value || '');
        fd.append('file_kind', 'contract');
    } else {
        fd.append('custom_label', document.getElementById('suCustomLabel').value || '');
        fd.append('file_kind', 'other');
    }

    document.getElementById('suProgress').style.display = 'block';
    document.getElementById('suSubmitBtn').disabled = true;
    const fill = document.getElementById('suFill');
    const txt = document.getElementById('suText');

    const xhr = new XMLHttpRequest();
    xhr.upload.onprogress = e => {
        if (e.lengthComputable) {
            const p = Math.round(e.loaded / e.total * 100);
            fill.style.width = p + '%';
            txt.textContent = `${t('uploading')||'جاري الرفع...'} ${p}%`;
        }
    };
    xhr.onload = () => {
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.success) {
                if (typeof Swal !== 'undefined') Swal.fire(t('success_title')||'تم', t('upload_success')||'تم الرفع بنجاح', 'success');
                closeSmartUpload();
                if (typeof SmartUpload.onSuccess === 'function') SmartUpload.onSuccess(res);
                else setTimeout(() => location.reload(), 600);
            } else {
                if (typeof Swal !== 'undefined') Swal.fire(t('error_title')||'خطأ', res.error || 'Failed', 'error');
                else alert(res.error || 'Failed');
                document.getElementById('suSubmitBtn').disabled = false;
            }
        } catch (e) {
            if (typeof Swal !== 'undefined') Swal.fire('Error', 'Invalid response', 'error');
            document.getElementById('suSubmitBtn').disabled = false;
        }
    };
    xhr.onerror = () => {
        if (typeof Swal !== 'undefined') Swal.fire('Error', 'Network error', 'error');
        document.getElementById('suSubmitBtn').disabled = false;
    };
    xhr.open('POST', '/api/upload.php');
    xhr.setRequestHeader('X-CSRF-Token', getCsrfToken());
    xhr.send(fd);
}


// ============================================================
// 14. تشغيل عند تحميل الصفحة
// ============================================================
// ============================================================
// 14.5 Drifting shapes — waiting.php-style ambient overlay
// ------------------------------------------------------------
// Auto-injects #mz-shapes if the page didn't render one, then
// spawns a steady supply of triangles / squares / circles that
// drift up the viewport. Tinted via --shape-c1/c2/c3 tokens so
// dark mode and light mode each get an appropriate look.
// ============================================================
function initDriftingShapes() {
    let host = document.getElementById('mz-shapes');
    if (!host) {
        host = document.createElement('div');
        host.id = 'mz-shapes';
        host.setAttribute('aria-hidden', 'true');
        // Insert right after #bg-canvas so they share the same z-stack.
        const bg = document.getElementById('bg-canvas');
        if (bg && bg.parentNode) bg.parentNode.insertBefore(host, bg.nextSibling);
        else document.body.insertBefore(host, document.body.firstChild);
    }

    // Respect reduced-motion preference: render a few static shapes
    // and skip the spawner so users with prefers-reduced-motion don't
    // see an animated background.
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const types  = ['', 'tri', 'sq'];
    const colors = ['c1', 'c2', 'c3'];

    // Defines the spawnShape routine.
    function spawnShape() {
        const s = document.createElement('div');
        const type  = types[Math.floor(Math.random() * types.length)];
        const color = colors[Math.floor(Math.random() * colors.length)];
        s.className = 'mz-shape' + (type ? ' ' + type : '') + ' ' + color;
        const sz = Math.random() * 55 + 22;
        s.style.left            = (Math.random() * 100) + '%';
        s.style.width           = sz + 'px';
        s.style.height          = sz + 'px';
        s.style.animationDuration = (Math.random() * 18 + 12) + 's';
        s.style.animationDelay  = (Math.random() * -20) + 's';
        host.appendChild(s);
        // GC: each shape lives ~32s then removes itself so the DOM
        // doesn't accumulate long-running shape nodes.
        setTimeout(() => s.remove(), 32000);
    }

    // Seed
    const seed = reduce ? 6 : 18;
    for (let i = 0; i < seed; i++) spawnShape();

    if (!reduce) {
        // Continuous spawn — slow enough to keep DOM count low.
        setInterval(spawnShape, 1700);
    }
}


// ── Cookie Consent ────────────────────────────────────────────
function initCookieConsent() {
    if (localStorage.getItem('mizan_cookie')) return;
    const banner = document.getElementById('cookieBanner');
    if (!banner) return;
    setTimeout(function () {
        banner.style.display = '';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { banner.classList.add('cookie-visible'); });
        });
    }, 900);
}

window.acceptCookies = function (type) {
    localStorage.setItem('mizan_cookie', type);
    const banner = document.getElementById('cookieBanner');
    if (banner) {
        banner.classList.remove('cookie-visible');
        setTimeout(function () { banner.style.display = 'none'; }, 380);
    }
    const key = type === 'all' ? 'cookie_toast_all' : 'cookie_toast_essential';
    if (typeof showToast === 'function') showToast(window.t ? window.t(key) : key, 'success', 3200);
};

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initLang();
    applyAutoFormat();
    initScrollReveal();
    initNavbarScroll();
    initKeyboardShortcuts();
    initUserDropdown();
    initBgCanvas();
    initDriftingShapes();
    initNavGlobalSearch();
    initStaggerEnter();
    initIdleTimer();
    initFormDoubleSubmitGuard();
    initCookieConsent();
    initMustChangePassword();
});

// ============================================================
// Must-change-password persistent nag (after admin-triggered reset)
// ============================================================
function initMustChangePassword() {
    if (!window.MIZAN_MUST_CHANGE_PASSWORD) return;
    if (typeof Swal === 'undefined') return;
    const isAr = (document.documentElement.lang || 'ar') !== 'en';
    const path = (location.pathname || '').toLowerCase();
    // Don't nag while user is already on the settings page changing their password
    if (path.indexOf('/pages/settings') !== -1) return;

    Swal.fire({
        icon: 'warning',
        title: isAr ? 'يجب تغيير كلمة المرور' : 'Password change required',
        html: isAr
            ? 'قام مدير النظام بإعادة تعيين كلمة مرورك. يرجى تغييرها فوراً من صفحة الإعدادات قبل المتابعة.'
            : 'Your password was reset by an administrator. Please change it immediately from the Settings page before continuing.',
        confirmButtonText: isAr ? 'اذهب للإعدادات' : 'Go to Settings',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showCancelButton: false,
    }).then(() => { window.location.href = '/pages/settings.php#password'; });
}


// ============================================================
// 15. Global Site Rating Popup (SweetAlert2-based)
// ============================================================

/**
 * Sets the selected star value in the rating popup.
 * Called via inline onclick on the star buttons inside Swal's html.
 */
window.mzSetStar = function mzSetStar(val) {
    const row = document.getElementById('mzStarRow');
    if (!row) return;
    row.dataset.selected = val;
    row.querySelectorAll('.mz-star').forEach(function (btn) {
        var bv = parseInt(btn.dataset.v, 10);
        btn.classList.toggle('active', bv <= val);
        btn.setAttribute('aria-pressed', String(bv <= val));
    });
};

/**
 * Shows the global site-rating popup.
 * Triggered by header.php's load-event handler after the check_rating_prompt
 * API confirms the popup should appear. Uses SweetAlert2 which is loaded globally.
 */
window.showSiteRatingPopup = async function showSiteRatingPopup() {
    if (typeof Swal === 'undefined') return;

    const isAr = (localStorage.getItem('mizan_lang') || 'ar') === 'ar';

    // Inject star-button CSS once
    if (!document.getElementById('mz-rating-css')) {
        var s = document.createElement('style');
        s.id = 'mz-rating-css';
        s.textContent = [
            '.mz-star-row{display:flex;gap:6px;justify-content:center;direction:ltr;margin:10px 0;}',
            '.mz-star{font-size:32px;color:#d1d5db;background:none;border:none;cursor:pointer;padding:2px 3px;',
            'transition:color .12s,transform .1s;line-height:1;border-radius:4px;}',
            '.mz-star.active{color:#f59e0b;}',
            '.mz-star:hover{color:#f59e0b;transform:scale(1.12);}',
            '.mz-star:focus-visible{outline:2px solid #f59e0b;outline-offset:2px;}',
        ].join('');
        document.head.appendChild(s);
    }

    var starsHtml = [1, 2, 3, 4, 5].map(function (n) {
        return '<button type="button" class="mz-star" data-v="' + n + '" '
             + 'onclick="mzSetStar(' + n + ')" '
             + 'aria-label="' + n + (isAr ? ' نجوم' : ' stars') + '" '
             + 'aria-pressed="false">★</button>';
    }).join('');

    var result = await Swal.fire({
        title: isAr ? 'كيف تجد تجربتك مع ميزان؟' : 'How do you like Mizan?',
        html: [
            '<p style="color:var(--text-muted,#6b7280);font-size:13px;margin:0 0 4px;">',
            isAr ? 'تقييمك يساعدنا في تطوير التطبيق باستمرار' : 'Your feedback helps us improve Mizan',
            '</p>',
            '<div class="mz-star-row" id="mzStarRow" data-selected="0" role="group" aria-label="' + (isAr ? 'التقييم' : 'Rating') + '">',
            starsHtml,
            '</div>',
            '<textarea id="mzRatingFeedback" rows="2" ',
            'placeholder="' + (isAr ? 'تعليق اختياري...' : 'Optional feedback...') + '" ',
            'style="width:100%;padding:8px 10px;border:1px solid var(--border,#e5e7eb);border-radius:8px;',
            'font-size:13px;resize:vertical;box-sizing:border-box;margin-top:6px;font-family:inherit;',
            'background:var(--surface2,#f8fafc);color:var(--text,#1e293b);">',
            '</textarea>',
        ].join(''),
        showCancelButton: true,
        confirmButtonText: isAr ? 'إرسال التقييم' : 'Submit Rating',
        cancelButtonText:  isAr ? 'لاحقاً' : 'Later',
        confirmButtonColor: '#1d4ed8',
        reverseButtons: isAr,
        focusConfirm: false,
        preConfirm: function () {
            var row    = document.getElementById('mzStarRow');
            var rating = parseInt((row && row.dataset.selected) || '0', 10);
            if (!rating) {
                Swal.showValidationMessage(isAr ? 'الرجاء اختيار تقييم أولاً ⭐' : 'Please select a star rating first ⭐');
                return false;
            }
            var comment = (document.getElementById('mzRatingFeedback') || {}).value || '';
            return { rating: rating, comment: comment.trim() };
        }
    });

    if (result.isDismissed) {
        // User clicked "Later" — set dismiss date so it shows again tomorrow
        try {
            await mizanFetch('/api/support.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'dismiss_rating_prompt', csrf_token: getCsrfToken() })
            });
        } catch (_) {}
        return;
    }

    if (result.value) {
        try {
            var res  = await mizanFetch('/api/support.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action:   'rate_site',
                    rating:   result.value.rating,
                    comment:  result.value.comment,
                    csrf_token: getCsrfToken()
                })
            });
            var d = await res.json();
            if (d.success) {
                Swal.fire({
                    title: isAr ? 'شكراً لك! 🎉' : 'Thank you! 🎉',
                    text:  isAr ? 'نقدّر وقتك — رأيك يساعدنا على تحسين ميزان' : 'We appreciate your feedback — it helps us improve Mizan',
                    icon:  'success',
                    timer: 2800,
                    showConfirmButton: false,
                    timerProgressBar: true,
                });
            }
        } catch (_) {}
    }
};