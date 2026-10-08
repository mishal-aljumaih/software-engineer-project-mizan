<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: welcome.php
 * PURPOSE: Public-facing landing page for Mizan.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
require_once __DIR__ . '/includes/security.php';
mz_send_security_headers();
mz_session_start();
// Show "Dashboard" CTA for returning users and "Sign up" for new visitors based on this flag
$is_logged_in = !empty($_SESSION['user_id']);
// ─────────────────────────────────────────────────────────────────────────
// END PHP LOGIC — BEGIN HTML VIEW (public landing / marketing page)
// ─────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>ميزان — أرشيفك المالي الشخصي</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    <!-- SEO / Open Graph / Twitter Card -->
    <meta name="description" content="ميزان — أرشفة الفواتير، تتبع المصاريف، وإدارة الضمانات في مكان واحد.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="ميزان — أرشيفك المالي الشخصي">
    <meta property="og:description" content="ميزان — أرشفة الفواتير، تتبع المصاريف، وإدارة الضمانات في مكان واحد.">
    <meta name="image" content="/assets/icons/meta-banner.png">
    <meta property="og:image" content="/assets/icons/meta-banner.png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="ميزان — أرشيفك المالي الشخصي">
    <meta name="twitter:description" content="ميزان — أرشفة الفواتير، تتبع المصاريف، وإدارة الضمانات في مكان واحد.">
    <meta name="twitter:image" content="/assets/icons/meta-banner.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/welcome.css">
</head>

<body>

    <canvas id="bg-canvas" aria-hidden="true"></canvas>

    <!-- ════ NAV ════ -->
    <nav id="nav">
        <div class="nav-left" id="navLinks"></div>
        <div class="nav-center">
            <a href="/welcome.php" class="nav-brand">
                <img src="/assets/icons/logo.png" alt="ميزان" style="height:32px;width:auto;" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'brand-icon',textContent:'⚖️'}))">
                <span class="brand-text">ميزان</span>
            </a>
        </div>
        <div class="nav-right">
            <button class="ctrl" id="themeBtn" onclick="toggleTheme()" title="الوضع الليلي">🌙</button>
            <button class="ctrl" id="langBtn" onclick="toggleLang()">EN</button>
            <div class="nav-sep"></div>
            <?php if ($is_logged_in): ?>
            <a href="/index.php" class="nav-cta" id="navDash" data-t="dashboard_btn">توجه إلى لوحة التحكم</a>
            <?php else: ?>
            <a href="login.php" class="nav-signin" id="navLogin">دخول</a>
            <a href="register.php" class="nav-cta" id="navReg">ابدأ مجاناً ←</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- ════ UNIVERSITY CARD ════ -->
    <section class="uni-sec" id="team">
        <div class="uni-card">
            <div class="uni-top">
                <div class="uni-top-inner">
                    <div class="uni-info">
                        <div class="uni-name" id="uniName">جامعة الإمام عبدالرحمن بن فيصل</div>
                        <div class="uni-sub" id="uniNamesub">كلية الحاسب وتقنية المعلومات</div>
                        <div class="uni-chips">
                            <span class="chip chip-dr" id="chipDr">🎓 د. سقيب سعيد</span>
                            <span class="chip chip-cs" id="chipCs">هندسة البرمجيات</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="uni-body">
                <!-- Declaration -->
                <div class="decl-box">
                    <div class="dpane on" id="dp-ar">
                        <strong>إقرار وتعريف المشروع:</strong><br>
                        نُقرّ بأن هذا المشروع المعنون <strong>"ميزان (Mizan)"</strong> هو نتاج عملنا الأصيل، باستثناء
                        الاستشهادات والاقتباسات الموثّقة، ولم يُقدَّم سابقاً لأي مقرر أو مؤسسة أخرى، وقد تم إعداده
                        استيفاءً لمتطلبات مقرر <strong>هندسة البرمجيات BCS 202</strong>.

                        يهدف مشروع ميزان إلى معالجة مشكلة "تكدس البيانات" التي يواجهها المستخدمون في العصر الرقمي، حيث
                        تنقسم التطبيقات الحالية بين أنظمة بسيطة لا توفر تتبعاً كافياً للبيانات المالية والمهام، وأنظمة
                        معقدة تعيق سهولة الاستخدام.

                        يستهدف المشروع فئة المستخدمين مثل طلاب الجامعات والمستقلين الذين يحتاجون إلى نظام يساعدهم على
                        إدارة الفواتير (مثل صيانة السيارة والمشتريات الشخصية)، وتتبع الأهداف المالية، وتنظيم الالتزامات
                        اليومية.

                        ومن هذا المنطلق، يقدم مشروع <strong>ميزان (Mizan)</strong> حلاً موحداً يعمل كـ "ميزان" رقمي
                        يوازن بين إدارة المدفوعات، وتنظيم المهام، وجدولة الالتزامات بطريقة بسيطة وفعالة.
                    </div>
                    <div class="dpane" id="dp-en">
                        <strong>Project Declaration and Overview:</strong><br>
                        We hereby declare that this project entitled <strong>"Mizan"</strong> is based on our original
                        work, except for properly cited references and quotations. It has not been previously submitted
                        to any course or institution, and it is prepared as part of the requirements for the
                        <strong>Software Engineering BCS 202</strong> course.

                        The Mizan project aims to address the issue of "data overload" faced by users in the modern
                        digital environment, where existing applications are either too simple to effectively track
                        financial data and tasks, or too complex, limiting usability and productivity.

                        This project targets users such as university students and freelancers who require an efficient
                        system to manage invoices (e.g., car maintenance, personal expenses), track financial goals, and
                        organize daily commitments.

                        Therefore, <strong>Mizan</strong> provides a unified solution that acts as a digital "scale" to
                        balance payments, task management, and life commitments in a simple and effective way.
                    </div>
                </div>

                <!-- Team -->
                <div class="team-ttl" id="teamTtl">أعضاء الفريق</div>

                <div class="scrum-wrap">
                    <div class="scrum-triangle">
                        <svg class="tri-lines" width="360" height="280" viewBox="-180 0 360 280"
                            style="position:absolute;top:0;left:50%;transform:translateX(-50%);z-index:0;pointer-events:none;overflow:visible;">
                            <line x1="0" y1="69" x2="-85" y2="217" stroke="rgba(255,255,255,0.75)" stroke-width="2.5"
                                stroke-linecap="round" />
                            <line x1="0" y1="69" x2="85" y2="217" stroke="rgba(255,255,255,0.75)" stroke-width="2.5"
                                stroke-linecap="round" />
                            <line x1="-85" y1="217" x2="85" y2="217" stroke="rgba(255,255,255,0.75)" stroke-width="2.5"
                                stroke-linecap="round" />
                            <circle cx="0" cy="69" r="5" fill="rgba(255,255,255,.9)" />
                            <circle cx="-85" cy="217" r="5" fill="rgba(255,255,255,.9)" />
                            <circle cx="85" cy="217" r="5" fill="rgba(255,255,255,.9)" />
                        </svg>

                        <div class="sn lead">
                            <div class="sn-role" id="rl">مسؤول Scrum / قائد الفريق</div>
                            <div class="sn-name">Mishal Al-jumaih</div>
                            <div class="sn-ar">مشعل الجميعه</div>
                            <div class="sn-id">2250030163</div>
                        </div>

                        <div class="sc-mems">
                            
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ════ HERO ════ -->
    <section class="hero">
        <div class="blob b1"></div>
        <div class="blob b2"></div>
        <div class="blob b3"></div>
        <div class="hero-inner">
            <div class="hero-logo">
                <img src="/assets/icons/logo.png" alt="ميزان" style="height:72px;width:auto;object-fit:contain;" onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'⚖️'}))">
            </div>
            <div class="hero-badge"><span class="pdot"></span> <span data-t="badge">أرشيف مالي ذكي</span></div>
            <h1><span data-t="h1a">كل ريال تصرفه</span><br><span class="hl" data-t="h1b">محفوظ ولا يضيع</span></h1>
            <p class="hero-sub" data-t="hsub">اعرف بالضبط كم ربحت، كم خسرت، وأين صرفت. فواتير، ضمانات، وأرشيف مالي كامل
                — في مكان واحد.</p>
            <div class="hero-btns">
                <?php if ($is_logged_in): ?>
                <a href="/index.php" class="btn-p" data-t="dashboard_btn">توجه إلى لوحة التحكم</a>
                <?php else: ?>
                <a href="/register.php" class="btn-p" data-t="start">ابدأ مجاناً</a>
                <a href="/login.php" class="btn-g" data-t="signin">تسجيل الدخول</a>
                <?php endif; ?>
            </div>
            <div class="dash-card" id="dashCard">
                <div class="dc-hdr">
                    <div class="dc-hlogo">⚖️</div>
                    <div>
                        <div class="dc-htitle" data-t="dc_t">لوحة التحكم</div>
                        <div class="dc-hsub" data-t="dc_s">نظرة عامة على مشاريعك</div>
                    </div>
                </div>
                <div class="dc-stats">
                    <div class="dc-stat">
                        <div class="dc-num" id="n1">0</div>
                        <div class="dc-lbl" data-t="s1">مشاريع نشطة</div>
                    </div>
                    <div class="dc-stat">
                        <div class="dc-num" id="n2">0</div>
                        <div class="dc-lbl" data-t="s2">فاتورة مؤرشفة</div>
                    </div>
                    <div class="dc-stat">
                        <div class="dc-num" id="n3">0</div>
                        <div class="dc-lbl" data-t="s3">ضمانات فعّالة</div>
                    </div>
                </div>
                <div class="dc-bars">
                    <div class="dc-br">
                        <div class="dc-bl" data-t="b1">سيارة</div>
                        <div class="dc-bt">
                            <div class="dc-bf c1" data-w="78"></div>
                        </div>
                        <div class="dc-bv">78%</div>
                    </div>
                    <div class="dc-br">
                        <div class="dc-bl" data-t="b2">منزل</div>
                        <div class="dc-bt">
                            <div class="dc-bf c2" data-w="45"></div>
                        </div>
                        <div class="dc-bv">45%</div>
                    </div>
                    <div class="dc-br">
                        <div class="dc-bl" data-t="b3">أجهزة</div>
                        <div class="dc-bt">
                            <div class="dc-bf c3" data-w="92"></div>
                        </div>
                        <div class="dc-bv">92%</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ════ FEATURES ════ -->
    <section class="sec" id="features">
        <div class="wrap">
            <div class="sec-lbl" data-t="flbl">المميزات</div>
            <h2 class="sec-title" data-t="fttl">كل ما تحتاجه في مكان واحد</h2>
            <p class="sec-sub" data-t="fsub">ميزان يغطي كل شيء.</p>
            <div class="fg">
                <div class="fc reveal">
                    <div class="fc-ic">⚖️</div>
                    <div class="fc-t" data-t="f1t">تتبع دقيق للمصاريف</div>
                    <div class="fc-d" data-t="f1d">سجّل كل ريال مع التاريخ والمورّد والتصنيف. لا شيء يغيب.</div>
                </div>
                <div class="fc reveal">
                    <div class="fc-ic">📊</div>
                    <div class="fc-t" data-t="f2t">حساب الربح والخسارة</div>
                    <div class="fc-d" data-t="f2d">أدخل سعر البيع ويحسب ميزان تلقائياً كم ربحت أو خسرت.</div>
                </div>
                <div class="fc reveal">
                    <div class="fc-ic">🛡️</div>
                    <div class="fc-t" data-t="f3t">تنبيهات انتهاء الضمان</div>
                    <div class="fc-d" data-t="f3d">قبل ثلاثين يوماً من انتهاء أي ضمان يصلك تنبيه واضح.</div>
                </div>
                <div class="fc reveal">
                    <div class="fc-ic">📁</div>
                    <div class="fc-t" data-t="f4t">أرشفة الفواتير والملفات</div>
                    <div class="fc-d" data-t="f4d">ارفع صور الفواتير وملفات PDF واربطها بكل عملية.</div>
                </div>
                <div class="fc reveal">
                    <div class="fc-ic">💰</div>
                    <div class="fc-t" data-t="f5t">تنبيه تجاوز الميزانية</div>
                    <div class="fc-d" data-t="f5d">حدّد ميزانية لكل مشروع وسيحذّرك ميزان فور الاقتراب.</div>
                </div>
                <div class="fc reveal">
                    <div class="fc-ic">🎨</div>
                    <div class="fc-t" data-t="f6t">مشاريع ملوّنة ومرتّبة</div>
                    <div class="fc-d" data-t="f6d">لكل مشروع لونه وأيقونته. ميّز بين مشاريعك بنظرة.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ════ USE CASES ════ -->
    <section class="uc-bg" id="usecases">
        <div class="sec wrap">
            <div class="sec-lbl" data-t="uclbl">لمن ميزان؟</div>
            <h2 class="sec-title" data-t="ucttl">قوالب جاهزة لكل موقف</h2>
            <p class="sec-sub" data-t="ucsub">اختر القالب وابدأ فوراً، أو أنشئ مشروعاً مخصصاً من الصفر.</p>
            <div class="uc-r1">
                <div class="uc reveal"><span class="uc-em">🚗</span>
                    <div class="uc-t" data-t="u1t">شراء وإصلاح سيارة</div>
                    <div class="uc-d" data-t="u1d">كل شيء محسوب. واعرف كم ربحت حين بعتها.</div>
                </div>
                <div class="uc reveal"><span class="uc-em">🏠</span>
                    <div class="uc-t" data-t="u2t">بناء أو تجديد منزل</div>
                    <div class="uc-d" data-t="u2d">مقاول ومواد بناء وكهرباء — مشروع واحد منظّم.</div>
                </div>
                <div class="uc reveal"><span class="uc-em">💼</span>
                    <div class="uc-t" data-t="u3t">مصاريف عمل للتعويض</div>
                    <div class="uc-d" data-t="u3d">وثّق كل مصروف وتابع حالة التعويض.</div>
                </div>
            </div>
            <div class="uc-r2">
                <div class="uc reveal"><span class="uc-em">📦</span>
                    <div class="uc-t" data-t="u4t">أجهزة وإلكترونيات</div>
                    <div class="uc-d" data-t="u4d">احفظ ضمان كل جهاز ولا يفوتك انتهاؤه.</div>
                </div>
                <div class="uc reveal"><span class="uc-em">✏️</span>
                    <div class="uc-t" data-t="u6t">مشروع مخصص</div>
                    <div class="uc-d" data-t="u6d">أنشئ مشروعك من الصفر بأي تصنيفات تناسبك.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ════ HOW ════ -->
    <section class="sec" id="how">
        <div class="wrap">
            <div class="sec-lbl" data-t="hwlbl">كيف يعمل؟</div>
            <h2 class="sec-title" data-t="hwttl">ثلاث خطوات وانتهى الأمر</h2>
            <p class="sec-sub" data-t="hwsub">لا تحتاج خبرة — الواجهة بسيطة من أول استخدام.</p>
            <div class="sw">
                <div class="step reveal">
                    <div class="step-n" data-t="sn1">١</div>
                    <div class="step-body">
                        <div class="step-t" data-t="st1">أنشئ مشروعاً</div>
                        <div class="step-d" data-t="sd1">اختر من ستة قوالب أو ابدأ من الصفر. حدّد الميزانية والاسم
                            واللون.</div>
                    </div>
                </div>
                <div class="step reveal">
                    <div class="step-n" data-t="sn2">٢</div>
                    <div class="step-body">
                        <div class="step-t" data-t="st2">سجّل كل مصروف</div>
                        <div class="step-d" data-t="sd2">أضف كل عملية بتفاصيلها. ارفع الفاتورة وأضف الضمان إن وُجد.
                        </div>
                    </div>
                </div>
                <div class="step reveal">
                    <div class="step-n" data-t="sn3">٣</div>
                    <div class="step-body">
                        <div class="step-t" data-t="st3">تابع وحلّل النتائج</div>
                        <div class="step-d" data-t="sd3">استعرض تقارير مصاريفك. اعرف كم ربحت أو خسرت في لحظة.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ════ CTA ════ -->
    <section class="cta">
        <h2 data-t="ctat">جاهز لتنظيم أمورك المالية؟</h2>
        <p data-t="ctas">أنشئ حسابك مجاناً وابدأ تتبّع مشاريعك</p>
        <a href="/register.php" class="btn-cta" data-t="ctab">ابدأ مع ميزان</a>
    </section>

    <!-- ════ FOOTER ════ -->
    <footer>
        <div class="fl">⚖️</div>
        <strong>ميزان</strong>
        <div class="ft-main" data-t="foot">أرشيفك المالي الشخصي · بُني بواسطة طلاب جامعة الإمام عبدالرحمن بن فيصل</div>
        <div class="ft-sub">© 2025 Mizan — BCS 202</div>
    </footer>

    <script>
    const T = {
        ar: {
            badge: 'أرشيف مالي ذكي',
            h1a: 'كل ريال تصرفه',
            h1b: 'محفوظ ولا يضيع',
            hsub: 'اعرف بالضبط كم ربحت، كم خسرت، وأين صرفت. فواتير، ضمانات، وأرشيف مالي كامل — في مكان واحد.',
            start: 'ابدأ مجاناً',
            signin: 'تسجيل الدخول',
            dashboard_btn: ' حسابي  ',
            dc_t: 'لوحة التحكم',
            dc_s: 'نظرة عامة على مشاريعك',
            s1: 'مشاريع نشطة',
            s2: 'فاتورة مؤرشفة',
            s3: 'ضمانات فعّالة',
            b1: 'سيارة',
            b2: 'منزل',
            b3: 'أجهزة',
            flbl: 'المميزات',
            fttl: 'كل ما تحتاجه في مكان واحد',
            fsub: 'ميزان يغطي كل شيء.',
            f1t: 'تتبع دقيق للمصاريف',
            f1d: 'سجّل كل ريال مع التاريخ والمورّد والتصنيف. لا شيء يغيب.',
            f2t: 'حساب الربح والخسارة',
            f2d: 'أدخل سعر البيع ويحسب ميزان تلقائياً كم ربحت أو خسرت.',
            f3t: 'تنبيهات انتهاء الضمان',
            f3d: 'قبل ثلاثين يوماً من انتهاء أي ضمان يصلك تنبيه واضح.',
            f4t: 'أرشفة الفواتير والملفات',
            f4d: 'ارفع صور الفواتير وملفات PDF واربطها بكل عملية.',
            f5t: 'تنبيه تجاوز الميزانية',
            f5d: 'حدّد ميزانية لكل مشروع وسيحذّرك ميزان فور الاقتراب.',
            f6t: 'مشاريع ملوّنة ومرتّبة',
            f6d: 'لكل مشروع لونه وأيقونته. ميّز بين مشاريعك بنظرة.',
            uclbl: 'لمن ميزان؟',
            ucttl: 'قوالب جاهزة لكل موقف',
            ucsub: 'اختر القالب وابدأ فوراً، أو أنشئ مشروعاً من الصفر.',
            u1t: 'شراء وإصلاح سيارة',
            u1d: 'كل شيء محسوب. واعرف كم ربحت حين بعتها.',
            u2t: 'بناء أو تجديد منزل',
            u2d: 'مقاول ومواد بناء وكهرباء — مشروع واحد منظّم.',
            u3t: 'مصاريف عمل للتعويض',
            u3d: 'وثّق كل مصروف وتابع حالة التعويض.',
            u4t: 'أجهزة وإلكترونيات',
            u4d: 'احفظ ضمان كل جهاز ولا يفوتك انتهاؤه.',
            u6t: 'مشروع مخصص',
            u6d: 'أنشئ مشروعك من الصفر بأي تصنيفات تناسبك.',
            hwlbl: 'كيف يعمل؟',
            hwttl: 'ثلاث خطوات وانتهى الأمر',
            hwsub: 'لا تحتاج خبرة — الواجهة بسيطة من أول استخدام.',
            st1: 'أنشئ مشروعاً',
            sd1: 'اختر من ستة قوالب أو ابدأ من الصفر. حدّد الميزانية والاسم واللون.',
            st2: 'سجّل كل مصروف',
            sd2: 'أضف كل عملية بتفاصيلها. ارفع الفاتورة وأضف الضمان إن وُجد.',
            st3: 'تابع وحلّل النتائج',
            sd3: 'استعرض تقارير مصاريفك. اعرف كم ربحت أو خسرت في لحظة.',
            ctat: 'جاهز لتنظيم أمورك المالية؟',
            ctas: 'أنشئ حسابك مجاناً وابدأ تتبّع مشاريعك',
            ctab: 'ابدأ مع ميزان',
            foot: 'أرشيفك المالي الشخصي · بُني بواسطة طلاب جامعة الإمام عبدالرحمن بن فيصل',
            login: 'دخول',
            reg: 'ابدأ مجاناً ←',
            uniName: 'جامعة الإمام عبدالرحمن بن فيصل',
            uniNamesub: 'كلية الحاسب وتقنية المعلومات',
            chipDr: '🎓 د. سقيب سعيد',
            chipCs: 'هندسة البرمجيات',
            teamTtl: 'أعضاء الفريق',
            rl: 'مسؤول Scrum / قائد الفريق',
            rm1: 'فريق التطوير / عضو',
            rm2: 'فريق التطوير / عضو',
            sn1: '١',
            sn2: '٢',
            sn3: '٣',
            nav: {
                team: 'الفريق',
                features: 'المميزات',
                usecases: 'القوالب',
                how: 'كيف يعمل؟'
            }
        },
        en: {
            badge: 'Smart Personal Financial Archive',
            h1a: 'Every Riyal You Spend',
            h1b: 'Saved & Never Lost',
            hsub: 'Know exactly how much you profited, lost, or spent anywhere. Invoices, warranties, and your full financial archive — all in one place.',
            start: 'Get Started Free',
            signin: 'Sign In',
            dashboard_btn: 'Account',
            dc_t: 'Dashboard',
            dc_s: 'Overview of your projects',
            s1: 'Active Projects',
            s2: 'Archived Invoices',
            s3: 'Active Warranties',
            b1: 'Car',
            b2: 'Home',
            b3: 'Devices',
            flbl: 'Features',
            fttl: 'Everything You Need in One Place',
            fsub: 'Mizan covers it all.',
            f1t: 'Precise Expense Tracking',
            f1d: 'Record every expense with date, vendor, and category. Nothing slips through.',
            f2t: 'Profit & Loss Calculation',
            f2d: 'Enter the selling price and Mizan calculates profit or loss instantly.',
            f3t: 'Warranty Expiry Alerts',
            f3d: '30 days before any warranty expires, you receive a clear alert.',
            f4t: 'Invoice & File Archiving',
            f4d: 'Upload invoice photos and PDFs linked to each transaction.',
            f5t: 'Budget Overrun Alerts',
            f5d: 'Set a budget per project and Mizan warns you before you exceed it.',
            f6t: 'Organized Colorful Projects',
            f6d: 'Each project gets its own color and icon. Tell them apart at a glance.',
            uclbl: 'Who is Mizan for?',
            ucttl: 'Ready Templates for Every Situation',
            ucsub: 'Pick a template and start immediately, or build from scratch.',
            u1t: 'Car Purchase & Repair',
            u1d: 'All accounted for. Know your profit when you sell.',
            u2t: 'Home Building & Renovation',
            u2d: 'Contractor, materials, electrical — one organized project.',
            u3t: 'Work Expenses Reimbursement',
            u3d: 'Document every expense and track reimbursement status.',
            u4t: 'Devices & Electronics',
            u4d: 'Save every device warranty and never miss an expiry.',
            u6t: 'Custom Project',
            u6d: 'Build from scratch with your own categories.',
            hwlbl: 'How does it work?',
            hwttl: 'Three Steps and Done',
            hwsub: 'No experience needed — simple and clear from day one.',
            st1: 'Create a Project',
            sd1: 'Choose from six templates or start from scratch. Set budget, name, and color.',
            st2: 'Record Every Expense',
            sd2: 'Add each transaction with full details. Upload invoice and warranty info.',
            st3: 'Track & Analyze',
            sd3: 'View monthly expense reports. Know profit, loss, and upcoming expiries instantly.',
            ctat: 'Ready to take control of your finances?',
            ctas: 'Create your free account and start tracking',
            ctab: 'Start with Mizan',
            foot: 'Your Personal Financial Archive · Built by students of Imam Abdulrahman Bin Faisal University',
            login: 'Sign In',
            reg: 'Get Started →',
            uniName: 'Imam Abdulrahman Bin Faisal University',
            uniNamesub: 'college of computer and information sciences',
            chipDr: '🎓 Dr. Saqib Saeed',
            chipCs: 'Software Engineering',
            teamTtl: 'Team Members',
            rl: 'ScrumMaster / Team Leader',
            rm1: 'Development Team / Member',
            rm2: 'Development Team / Member',
            sn1: '1',
            sn2: '2',
            sn3: '3',
            nav: {
                team: 'Team',
                features: 'Features',
                usecases: 'Templates',
                how: 'How it works?'
            }
        }
    };

    let lang = localStorage.getItem('mizan_lang') || 'ar';
    let theme = localStorage.getItem('mizan_theme') || 'light';
    const NAV_IDS = ['team', 'features', 'usecases', 'how'];

    // Builds the nav.
    function buildNav(l) {
        const wrap = document.getElementById('navLinks');
        wrap.innerHTML = '';
        NAV_IDS.forEach(id => {
            const a = document.createElement('a');
            a.href = '#' + id;
            a.className = 'nav-lnk';
            a.dataset.nav = id;
            a.textContent = T[l].nav[id];
            wrap.appendChild(a);
        });
    }

    // Applies the lang.
    function applyLang(l) {
        lang = l;
        localStorage.setItem('mizan_lang', l);
        const t = T[l];
        document.documentElement.lang = l;
        document.documentElement.dir = l === 'ar' ? 'rtl' : 'ltr';

        document.querySelectorAll('[data-t]').forEach(el => {
            const k = el.dataset.t;
            if (t[k] !== undefined) el.textContent = t[k];
        });

        const ids = ['uniName', 'uniNamesub', 'chipDr', 'chipCs', 'teamTtl', 'rl', 'rm1', 'rm2'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el && t[id] !== undefined) el.textContent = t[id];
        });

        const navLoginEl = document.getElementById('navLogin');
        const navRegEl   = document.getElementById('navReg');
        const navDashEl  = document.getElementById('navDash');
        if (navLoginEl) navLoginEl.textContent = t.login;
        if (navRegEl)   navRegEl.textContent   = t.reg;
        if (navDashEl)  navDashEl.textContent  = t.dashboard_btn;
        document.getElementById('langBtn').textContent = l === 'ar' ? 'EN' : 'ع';

        document.querySelectorAll('.dpane').forEach(p => p.classList.remove('on'));
        document.querySelectorAll('.dtab').forEach(b => b.classList.remove('on'));
        document.getElementById('dp-' + l).classList.add('on');
        document.querySelectorAll('.dtab').forEach(b => {
            if ((l === 'ar' && b.textContent === 'العربية') || (l === 'en' && b.textContent === 'English'))
                b.classList.add('on');
        });

        buildNav(l);
    }

    // Toggles the lang.
    function toggleLang() {
        applyLang(lang === 'ar' ? 'en' : 'ar');
    }

    // Applies the theme.
    function applyTheme(th) {
        theme = th;
        localStorage.setItem('mizan_theme', th);
        document.documentElement.setAttribute('data-theme', th);
        document.getElementById('themeBtn').textContent = th === 'dark' ? '☀️' : '🌙';
    }

    // Toggles the theme.
    function toggleTheme() {
        applyTheme(theme === 'dark' ? 'light' : 'dark');
    }

    // Switches the decl.
    function switchDecl(l, btn) {
        document.querySelectorAll('.dpane').forEach(p => p.classList.remove('on'));
        document.querySelectorAll('.dtab').forEach(b => b.classList.remove('on'));
        document.getElementById('dp-' + l).classList.add('on');
        btn.classList.add('on');
    }

    applyLang(lang);
    applyTheme(theme);

    /* ── Background particle canvas ── */
    (function initBgCanvas() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const cv = document.getElementById('bg-canvas');
        if (!cv) return;
        const ctx = cv.getContext('2d');
        let W, H, pts;

        // Defines the resize routine.
        function resize() {
            W = cv.width  = window.innerWidth;
            H = cv.height = window.innerHeight;
        }

        // Defines the spawn routine.
        function spawn() {
            pts = Array.from({ length: 55 }, () => ({
                x: Math.random() * W, y: Math.random() * H,
                vx: (Math.random() - .5) * .45,
                vy: (Math.random() - .5) * .45,
                r: Math.random() * 2 + 1,
            }));
        }

        // Returns whether the the dark.
        function isDark() {
            return document.documentElement.getAttribute('data-theme') === 'dark';
        }

        // Renders.
        function draw() {
            ctx.clearRect(0, 0, W, H);
            const dark = isDark();
            const dotC = dark ? 'rgba(0,255,178,.55)'   : 'rgba(29,30,82,.30)';
            const linC = dark ? 'rgba(0,255,178,'        : 'rgba(29,30,82,';

            pts.forEach(p => {
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0) p.x = W; if (p.x > W) p.x = 0;
                if (p.y < 0) p.y = H; if (p.y > H) p.y = 0;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = dotC;
                ctx.fill();
            });

            for (let i = 0; i < pts.length; i++) {
                for (let j = i + 1; j < pts.length; j++) {
                    const dx = pts[i].x - pts[j].x, dy = pts[i].y - pts[j].y;
                    const d  = Math.sqrt(dx * dx + dy * dy);
                    if (d < 120) {
                        ctx.beginPath();
                        ctx.moveTo(pts[i].x, pts[i].y);
                        ctx.lineTo(pts[j].x, pts[j].y);
                        ctx.strokeStyle = linC + (1 - d / 120) * .25 + ')';
                        ctx.lineWidth = .8;
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(draw);
        }

        resize();
        spawn();
        draw();
        window.addEventListener('resize', () => { resize(); spawn(); }, { passive: true });
    })();

    /* scroll effects */
    window.addEventListener('scroll', () => {
        document.getElementById('nav').classList.toggle('scrolled', scrollY > 20);
        let cur = '';
        ['team', 'features', 'usecases', 'how'].forEach(id => {
            const el = document.getElementById(id);
            if (el && scrollY >= el.offsetTop - 90) cur = id;
        });
        document.querySelectorAll('.nav-lnk').forEach(a => a.classList.toggle('act', a.dataset.nav === cur));
    }, {
        passive: true
    });

    /* reveal */
    const ro = new IntersectionObserver(entries => {
        entries.forEach((e, i) => {
            if (e.isIntersecting) {
                setTimeout(() => e.target.classList.add('visible'), i * 85);
                ro.unobserve(e.target);
            }
        });
    }, {
        threshold: .1
    });
    document.querySelectorAll('.reveal').forEach(el => ro.observe(el));

    /* dashboard counter animation */
    function countUp(el, target) {
        const dur = 1400,
            t0 = performance.now();
        (function s(now) {
            const p = Math.min((now - t0) / dur, 1),
                e = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * e);
            if (p < 1) requestAnimationFrame(s);
        })(t0);
    }

    const dObs = new IntersectionObserver(en => {
        if (en[0].isIntersecting) {
            countUp(document.getElementById('n1'), 5);
            countUp(document.getElementById('n2'), 47);
            countUp(document.getElementById('n3'), 12);
            document.querySelectorAll('.dc-bf').forEach(b => b.style.width = b.dataset.w + '%');
            dObs.disconnect();
        }
    }, {
        threshold: .3
    });
    dObs.observe(document.getElementById('dashCard'));
    </script>
</body>

</html>
