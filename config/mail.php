<?php
/**
 * ========================================================================
 * PROJECT: Mizan Financial Archiving Platform v4.5
 * FILE: mail.php
 * PURPOSE: PHPMailer wrapper and bilingual transactional email templates.
 * OWNER: Mishal Al-jumaih - ScrumMaster & Development Member (Full Stack All Rounder)
 * ========================================================================
 */
// ── SMTP Settings ──────────────────────────────────────────
define('MAIL_SMTP_HOST',     'smtp.gmail.com');   // ← سيرفر الإيميل
define('MAIL_SMTP_PORT',     587);                   // 587 = TLS | 465 = SSL
define('MAIL_SMTP_SECURE',   'tls');                 // 'tls' أو 'ssl'
define('MAIL_SMTP_USER',     'mizan.system.iau@gmail.com
');// ← إيميل المرسل
define('MAIL_SMTP_PASS',     'qkonmuanbwgjuqup'); // ← كلمة مرور الإيميل

// ── Sender Info ────────────────────────────────────────────
define('MAIL_FROM_NAME',     'ميزان Mizan');
define('MAIL_FROM_EMAIL',    'mizan.system.iau@gmail.com');
define('MAIL_REPLY_TO',      'mizan.system.iau@gmail.com');

// ── App URL (للروابط في الإيميلات) ────────────────────────
define('APP_URL',            'https://mizan-iau.site/mizan');
define('APP_NAME',           'Mizan · ميزان');

// ── على LOCALHOST: استخدم PHP mail() مباشرة ───────────────
define('MAIL_USE_SMTP',      true); // ← اجعله true لما تنشر على السيرفر

// ============================================================
// دالة إرسال الإيميل الرئيسية
// ============================================================
function sendMail(string $to, string $subject, string $htmlBody): bool {

    // Branch: on the local XAMPP dev box we fall back to PHP's mail() so we don't ping Gmail SMTP from a laptop.
    // In production MAIL_USE_SMTP=true forces real SMTP delivery via the dedicated socket sender below.
    if (!MAIL_USE_SMTP) {
        // ─── localhost: PHP mail() ───
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . MAIL_REPLY_TO . "\r\n";
        return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
    }

    // ─── Production: SMTP via socket ───
    return _smtpSend($to, $subject, $htmlBody);
}

// ============================================================
// SMTP sender (بدون مكتبات خارجية)
// ============================================================
function _smtpSend(string $to, string $subject, string $body): bool {
    try {
        $host   = MAIL_SMTP_HOST;
        $port   = MAIL_SMTP_PORT;
        $secure = MAIL_SMTP_SECURE;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => true,
            ]
        ]);

        $addr = ($secure === 'ssl') ? "ssl://{$host}" : $host;
        $sock = stream_socket_client("{$addr}:{$port}", $errno, $errstr, 15,
                                     STREAM_CLIENT_CONNECT, $context);
        if (!$sock) throw new Exception("Connect failed: {$errstr}");

        $read = function() use ($sock) {
            $r = '';
            while ($l = fgets($sock, 512)) {
                $r .= $l;
                if ($l[3] === ' ') break;
            }
            return $r;
        };
        $write = function($cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

        $read(); // 220
        $write("EHLO " . gethostname());
        $ehlo = $read();

        // Opportunistic TLS upgrade — issue STARTTLS only if the server advertised it in EHLO response
        if ($secure === 'tls' && strpos($ehlo, 'STARTTLS') !== false) {
            $write("STARTTLS");
            $read();
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write("EHLO " . gethostname());
            $read();
        }

        $write("AUTH LOGIN");
        $read();
        $write(base64_encode(MAIL_SMTP_USER));
        $read();
        $write(base64_encode(MAIL_SMTP_PASS));
        $auth = $read();
        if (strpos($auth, '235') === false) throw new Exception("Auth failed");

        $write("MAIL FROM: <" . MAIL_FROM_EMAIL . ">");
        $read();
        $write("RCPT TO: <{$to}>");
        $read();
        $write("DATA");
        $read();

        $date    = date('r');
        $msgId   = '<' . uniqid() . '@' . gethostname() . '>';
        $encSubj = '=?UTF-8?B?' . base64_encode($subject) . '?=';

     $msg  = "Date: {$date}\r\n";
$msg .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
$msg .= "To: {$to}\r\n";
$msg .= "Subject: {$encSubj}\r\n";
$msg .= "Message-ID: {$msgId}\r\n";

$msg .= "Reply-To: " . MAIL_REPLY_TO . "\r\n";
$msg .= "MIME-Version: 1.0\r\n";
$msg .= "Content-Type: text/html; charset=UTF-8\r\n";
$msg .= "Content-Transfer-Encoding: base64\r\n";
$msg .= "X-Mailer: Mizan Mail System\r\n";
$msg .= "List-Unsubscribe: <mailto:" . MAIL_REPLY_TO . ">\r\n";
$msg .= "Content-Language: ar\r\n";

$msg .= "\r\n";
$msg .= chunk_split(base64_encode($body));
$msg .= "\r\n.";

        $write($msg);
        $sent = $read();

        $write("QUIT");
        fclose($sock);

        return strpos($sent, '250') !== false;

    } catch (Exception $e) {
        error_log("Mizan Mail Error: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// قوالب الإيميلات
// ============================================================

/** الغلاف الرئيسي للإيميل */
function mailWrap(string $content, string $title = '', string $titleSubline = ''): string {
    $year    = date('Y');
    $logoUrl = 'https://mizan-iau.site/assets/icons/logo.png';
    $appUrl  = defined('APP_URL') ? APP_URL : 'https://mizan-iau.site';
    $titleBlock = '';
    if ($title !== '') {
        $titleBlock = "
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
               style='background:linear-gradient(135deg,#0F1F3D 0%,#1d4ed8 100%);'>
          <tr>
            <td style='padding:24px 36px 22px;'>
              <p style='margin:0 0 4px;font-size:12px;font-weight:700;letter-spacing:1px;
                        color:rgba(255,255,255,.65);'>ميزان</p>
              <h1 style='margin:0;font-size:19px;font-weight:800;color:#fff;
                         font-family:Tahoma,Arial,sans-serif;line-height:1.3;'>{$title}</h1>"
              . ($titleSubline !== '' ? "<p style='margin:6px 0 0;font-size:13px;color:rgba(255,255,255,.70);'>{$titleSubline}</p>" : "") . "
            </td>
          </tr>
        </table>";
    }
    return "
<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<meta name='x-apple-disable-message-reformatting'>
<title>{$title}</title>
</head>
<body style='margin:0;padding:0;background:#EEF1F7;font-family:Tahoma,Arial,sans-serif;-webkit-text-size-adjust:100%;'>

<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
       style='background:#EEF1F7;padding:36px 0;'>
<tr><td align='center'>

  <!-- Outer card — max 580px -->
  <table role='presentation' width='580' cellpadding='0' cellspacing='0' border='0'
         style='max-width:580px;width:100%;'>

    <!-- ── Logo bar ── -->
    <tr>
      <td align='center' style='padding:0 0 18px;'>
        <a href='{$appUrl}' style='text-decoration:none;display:inline-flex;align-items:center;gap:8px;'>
          <img src='{$logoUrl}' alt='ميزان' width='32' height='32'
               style='display:inline-block;width:32px;height:32px;border:0;border-radius:8px;'
               onerror=\"this.style.display='none'\">
          <span style='font-size:20px;font-weight:800;color:#1A2B52;font-family:Tahoma,Arial,sans-serif;'>ميزان</span>
          <span style='font-size:12px;color:#64748b;font-weight:500;margin-right:6px;'>الأرشيف المالي</span>
        </a>
      </td>
    </tr>

    <!-- ── Card body ── -->
    <tr>
      <td style='background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(15,23,42,.08);
                 border:1px solid #DDE3EF;overflow:hidden;'>

        <!-- Top accent bar (shown only when no title block) -->
        " . ($titleBlock !== '' ? $titleBlock : "
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'>
          <tr><td style='height:5px;background:linear-gradient(90deg,#0F1F3D,#1d4ed8);'></td></tr>
        </table>") . "

        <!-- Content area -->
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'>
          <tr>
            <td style='padding:32px 36px 36px;direction:rtl;text-align:right;'>
              {$content}
            </td>
          </tr>
        </table>

        <!-- Divider -->
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'>
          <tr><td style='height:1px;background:#EEF1F7;'></td></tr>
        </table>

        <!-- Footer inside card -->
        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'>
          <tr>
            <td align='center' style='padding:20px 36px 22px;background:#F8FAFD;border-radius:0 0 16px 16px;'>
              <p style='color:#94A3B8;font-size:11.5px;margin:0 0 4px;line-height:1.6;'>
                هذا إيميل تلقائي — يُرجى عدم الرد عليه مباشرة.
              </p>
              <p style='color:#94A3B8;font-size:11px;margin:0 0 6px;'>
                © {$year}
                <a href='{$appUrl}' style='color:#1d4ed8;text-decoration:none;font-weight:600;'>ميزان</a>
                &nbsp;·&nbsp; منصة الأرشيف المالي
              </p>
              <p style='color:#CBD5E1;font-size:10.5px;margin:0;'>
                <a href='{$appUrl}/pages/support.php' style='color:#94A3B8;text-decoration:none;'>الدعم الفني</a>
                &nbsp;|&nbsp;
                <a href='{$appUrl}' style='color:#94A3B8;text-decoration:none;'>الموقع الرسمي</a>
              </p>
            </td>
          </tr>
        </table>

      </td>
    </tr>

    <!-- Bottom spacer -->
    <tr><td style='height:24px;'></td></tr>

  </table>
</td></tr>
</table>
</body>
</html>";
}

/** زر أساسي */
function mailBtn(string $url, string $text): string {
    return "<div style='text-align:center;margin:24px 0;'>
      <a href='{$url}' style='display:inline-block;background:#1d4ed8;color:#fff;
         padding:13px 30px;border-radius:10px;text-decoration:none;
         font-weight:700;font-size:15px;font-family:Tahoma,Arial,sans-serif;
         box-shadow:0 4px 18px rgba(29,78,216,.35);letter-spacing:.01em;'>
        {$text}
      </a>
    </div>";
}

/** ─── قالب: استعادة كلمة المرور ─── */
function mailResetPassword(string $userName, string $resetLink): string {
    $content = "
    <h2 style='color:#0F172A;margin:0 0 8px;font-size:20px;'>مرحباً، {$userName}</h2>
    <p style='color:#5A6478;line-height:1.75;margin:0 0 20px;font-size:14px;'>
        استلمنا طلباً لإعادة تعيين كلمة مرور حسابك في ميزان.<br>
        اضغط على الزر أدناه — الرابط صالح لمدة <strong style='color:#1d4ed8;'>ساعة واحدة</strong>.
    </p>
    " . mailBtn($resetLink, 'إعادة تعيين كلمة المرور ←') . "
    <p style='color:#9AA0AB;font-size:12px;text-align:center;margin:0;'>
        إذا لم تطلب هذا، تجاهل الإيميل وكلمة مرورك ستبقى كما هي.
    </p>";
    return mailWrap($content, 'استعادة كلمة المرور — ميزان');
}

/** ─── قالب: تنبيه انتهاء ضمان ─── */
function mailWarrantyAlert(string $userName, string $itemName, string $expireDate, int $daysLeft, string $dashLink): string {
    $urgency = $daysLeft <= 7
        ? "<div style='background:#FEF2F2;border:1.5px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:20px;text-align:center;'>
             <span style='font-size:20px;'>🚨</span>
             <strong style='color:#DC2626;font-size:14px;'> عاجل: متبقي {$daysLeft} يوم فقط!</strong>
           </div>"
        : "<div style='background:#FFFBEB;border:1.5px solid #FDE68A;border-radius:10px;padding:12px 16px;margin-bottom:20px;text-align:center;'>
             <span style='font-size:20px;'>⚠️</span>
             <strong style='color:#D97706;font-size:14px;'> تنبيه: متبقي {$daysLeft} يوم</strong>
           </div>";

    $content = "
    <h2 style='color:#0F172A;margin:0 0 8px;font-size:20px;'>مرحباً، {$userName}</h2>
    <p style='color:#5A6478;line-height:1.75;margin:0 0 16px;font-size:14px;'>
        ضمان المنتج التالي على وشك الانتهاء:
    </p>
    {$urgency}
    <table width='100%' style='border-collapse:collapse;margin-bottom:20px;'>
      <tr style='background:#F4F1E9;'>
        <td style='padding:10px 14px;border-radius:8px 8px 0 0;font-size:13px;color:#5A6478;'>المنتج / العنصر</td>
        <td style='padding:10px 14px;border-radius:8px 8px 0 0;font-size:14px;font-weight:700;color:#0F172A;text-align:left;'>{$itemName}</td>
      </tr>
      <tr style='background:#fff;border:1px solid #EDE9DF;'>
        <td style='padding:10px 14px;font-size:13px;color:#5A6478;'>تاريخ الانتهاء</td>
        <td style='padding:10px 14px;font-size:14px;font-weight:700;color:#EF4444;text-align:left;'>{$expireDate}</td>
      </tr>
      <tr style='background:#F4F1E9;'>
        <td style='padding:10px 14px;border-radius:0 0 8px 8px;font-size:13px;color:#5A6478;'>الأيام المتبقية</td>
        <td style='padding:10px 14px;border-radius:0 0 8px 8px;font-size:14px;font-weight:700;color:#D97706;text-align:left;'>{$daysLeft} يوم</td>
      </tr>
    </table>
    " . mailBtn($dashLink, 'عرض الضمانات في ميزان →') . "
    <p style='color:#9AA0AB;font-size:12px;text-align:center;margin:0;'>
        يمكنك تجديد الضمان أو تحديث بياناته من لوحة التحكم.
    </p>";
    return mailWrap($content, 'تنبيه انتهاء ضمان — ميزان');
}

/** ─── قالب: تجاوز الميزانية ─── */
function mailBudgetAlert(string $userName, string $projectName, float $budget, float $spent, string $dashLink): string {
    $overage = $spent - $budget;
    $pct     = $budget > 0 ? round(($spent / $budget) * 100) : 0;

    $content = "
    <h2 style='color:#0F172A;margin:0 0 8px;font-size:20px;'>مرحباً، {$userName}</h2>
    <p style='color:#5A6478;line-height:1.75;margin:0 0 16px;font-size:14px;'>
        تجاوز مشروعك الميزانية المحددة:
    </p>
    <div style='background:#FEF2F2;border:1.5px solid #FECACA;border-radius:12px;padding:20px;margin-bottom:20px;text-align:center;'>
      <div style='font-size:28px;margin-bottom:6px;'>⚠️</div>
      <div style='font-size:18px;font-weight:700;color:#DC2626;'>{$projectName}</div>
      <div style='font-size:13px;color:#5A6478;margin-top:4px;'>صرفت <strong>" . number_format($spent, 0) . " ﷼</strong> من ميزانية <strong>" . number_format($budget, 0) . " ﷼</strong> ({$pct}%)</div>
      <div style='font-size:13px;color:#DC2626;margin-top:4px;font-weight:700;'>تجاوز بمقدار: " . number_format($overage, 0) . " ﷼</div>
    </div>
    " . mailBtn($dashLink, 'مراجعة المشروع في ميزان →');
    return mailWrap($content, 'تنبيه تجاوز الميزانية — ميزان');
}

/** ─── قالب: إعادة تعيين كلمة المرور من قِبَل المدير ─── */
function mailAdminResetPassword(string $userName, string $newPass): string {
    $appUrl = defined('APP_URL') ? APP_URL : 'https://mizan-iau.site';
    $content = "
    <p style='color:#5A6478;line-height:1.75;margin:0 0 16px;font-size:14px;'>
        مرحباً <strong style='color:#0F172A;'>{$userName}</strong>،<br>
        قام مدير النظام بإعادة تعيين كلمة مرورك على منصة <strong style='color:#1d4ed8;'>ميزان</strong>.
    </p>

    <div style='background:linear-gradient(135deg,#0F1F3D,#1d4ed8);border-radius:12px;
                padding:24px 20px;margin-bottom:20px;text-align:center;'>
        <p style='margin:0 0 6px;font-size:11px;color:rgba(255,255,255,.6);
                  letter-spacing:2px;font-weight:700;text-transform:uppercase;'>كلمة مرورك الجديدة</p>
        <p style='margin:0;font-size:26px;font-weight:900;color:#fff;
                  letter-spacing:5px;font-family:monospace;'>{$newPass}</p>
    </div>

    <div style='background:#FFFBEB;border:1.5px solid #FDE68A;border-radius:10px;
                padding:12px 16px;margin-bottom:22px;'>
        <p style='color:#92400E;font-size:13px;margin:0;line-height:1.6;'>
            <strong>تنبيه:</strong> يُرجى تسجيل الدخول وتغيير كلمة المرور فوراً من صفحة الإعدادات.
            لا تشارك هذه الكلمة مع أحد.
        </p>
    </div>
    " . mailBtn($appUrl . '/login.php', 'تسجيل الدخول الآن ←') . "
    <p style='color:#9AA0AB;font-size:12px;text-align:center;margin:0;'>
        إذا لم تتوقع هذا الإجراء، يُرجى التواصل مع الدعم الفني فوراً.
    </p>";
    return mailWrap($content, 'إعادة تعيين كلمة المرور', 'تمت هذه العملية من قِبَل مدير النظام');
}

/** ─── قالب: بريد مخصص من المدير ─── */
function mailCustomAdmin(string $userName, string $subject, string $bodyHtml): string {
    $content = "
    <p style='color:#5A6478;line-height:1.75;margin:0 0 18px;font-size:14px;'>
        مرحباً <strong style='color:#0F172A;'>{$userName}</strong>،
    </p>
    <div style='background:#F8FAFD;border:1px solid #DDE3EF;border-radius:10px;
                padding:18px 20px;margin-bottom:20px;line-height:1.75;font-size:14px;color:#374151;'>
        {$bodyHtml}
    </div>
    <p style='color:#9AA0AB;font-size:12px;text-align:center;margin:0;'>
        هذه رسالة إدارية من فريق ميزان.
    </p>";
    return mailWrap($content, $subject, 'رسالة من فريق ميزان');
}

/** ─── قالب: ترحيب بمستخدم جديد ─── */
function mailWelcome(string $userName, string $dashLink): string {
    $content = "
    <h2 style='color:#0F172A;margin:0 0 8px;font-size:22px;'>أهلاً وسهلاً، {$userName}! 🎉</h2>
    <p style='color:#5A6478;line-height:1.75;margin:0 0 20px;font-size:14px;'>
        مرحباً بك في <strong style='color:#1d4ed8;'>ميزان</strong> — أرشيفك المالي الشخصي.<br>
        يمكنك الآن تتبع مشاريعك وفواتيرك وضماناتك بسهولة.
    </p>
    <div style='background:#F4F1E9;border-radius:12px;padding:20px;margin-bottom:20px;'>
      <p style='color:#0F172A;font-size:14px;font-weight:700;margin:0 0 10px;'>ابدأ بهذه الخطوات:</p>
      <p style='color:#5A6478;font-size:13px;margin:4px 0;'>✅ أنشئ مشروعك الأول</p>
      <p style='color:#5A6478;font-size:13px;margin:4px 0;'>✅ أضف مصاريفك الأولى</p>
      <p style='color:#5A6478;font-size:13px;margin:4px 0;'>✅ ارفع فواتيرك وضماناتك</p>
    </div>
    " . mailBtn($dashLink, 'ابدأ مع ميزان →');
    return mailWrap($content, 'مرحباً في ميزان');
}