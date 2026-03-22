<?php
// register.php - phiên bản đã được làm sạch, giữ cấu trúc gốc nhưng sửa lỗi redirect/JSON
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Prevent accidental output before redirects/includes by buffering output
ob_start();

include __DIR__ . '/../includes/language.php';
require_once '../includes/db.php';

require_once __DIR__ . '/../vendor/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer-master/src/SMTP.php';


// sau khi đã sinh $code, thay phần mail() bằng PHPMailer:
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ---------- helper functions ----------
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function old($f, $d = '')
{
    return $_SESSION['old'][$f] ?? $d;
}
function hasError($f)
{
    return isset($_SESSION['errors'][$f]);
}
function getError($f)
{
    $e = $_SESSION['errors'][$f] ?? '';
    unset($_SESSION['errors'][$f]);
    return $e;
}

// Helper to respond to AJAX requests with JSON (safe: clears buffer)
function respond_json($data)
{
    header('Content-Type: application/json; charset=utf-8');
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode($data);
    if (ob_get_length()) {
        ob_end_flush();
    }
    exit;
}

// ================== XỬ LÝ POST ================== //
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect input
    $ho = trim($_POST['ho'] ?? '');
    $ten = trim($_POST['ten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $matkhau = $_POST['matkhau'] ?? '';
    $day = (int)($_POST['birthday_day'] ?? 0);
    $month = (int)($_POST['birthday_month'] ?? 0);
    $year = (int)($_POST['birthday_year'] ?? 0);
    $age_input = trim($_POST['age_input'] ?? '');
    $sex = $_POST['sex'] ?? '';
    $custom_gender = $_POST['custom_gender'] ?? '';
    $custom_gender_text = trim($_POST['custom_gender_text'] ?? '');

    // Keep old values in session for form repopulation
    $_SESSION['old'] = compact('ho', 'ten', 'email', 'day', 'month', 'year', 'age_input', 'sex', 'custom_gender', 'custom_gender_text');
    $valid = true;
    $_SESSION['errors'] = [];

    // Basic validation
    if (empty($ho)) {
        $_SESSION['errors']['ho'] = ($text['surname'] ?? 'Họ') . ' không được để trống';
        $valid = false;
    }
    if (empty($ten)) {
        $_SESSION['errors']['ten'] = ($text['first_name'] ?? 'Tên') . ' không được để trống';
        $valid = false;
    }

    if (empty($email)) {
        $_SESSION['errors']['email'] = ($text['mobile_or_email'] ?? 'Email hoặc SĐT') . ' không được để trống';
        $valid = false;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/^\d{10,11}$/', $email)) {
        $_SESSION['errors']['email'] = 'Email hoặc số điện thoại không hợp lệ';
        $valid = false;
    }

    if (empty($matkhau) || strlen($matkhau) < 6) {
        $_SESSION['errors']['matkhau'] = 'Mật khẩu phải ít nhất 6 ký tự';
        $valid = false;
    }

    // Age validation: either age_input OR birthday (but not both required)
    $isAgeMode = $age_input !== '';
    if ($isAgeMode) {
        if (!is_numeric($age_input) || (int)$age_input < 13 || (int)$age_input > 120) {
            $_SESSION['errors']['age'] = 'Tuổi không hợp lệ';
            $valid = false;
        }
    } else {
        if ($day == 0 || $month == 0 || $year == 0 || !checkdate($month, $day, $year)) {
            $_SESSION['errors']['birthday'] = 'Ngày sinh không hợp lệ';
            $valid = false;
        } else {
            $age = (int)date('Y') - $year - ((date('md') < sprintf('%02d%02d', $month, $day)) ? 1 : 0);
            if ($age < 13) {
                $_SESSION['errors']['birthday'] = 'Bạn phải ít nhất 13 tuổi';
                $valid = false;
            }
        }
    }

    // AJAX detection
    $isAjax = false;
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $isAjax = true;
    }
    // Fallback: client có thể gửi ?ajax=1, ?_ajax=1 hoặc POST field
    if (!$isAjax) {
        if ((!empty($_GET['ajax']) && $_GET['ajax']) || (!empty($_POST['ajax']) && $_POST['ajax']) ||
            (!empty($_GET['_ajax']) && $_GET['_ajax']) || (!empty($_POST['_ajax']) && $_POST['_ajax'])
        ) {
            $isAjax = true;
        }
    }

    // If validation failed: return JSON for AJAX or let page re-render for normal
    if (!$valid) {
        if ($isAjax) {
            respond_json([
                'ok' => false,
                'errors' => $_SESSION['errors'] ?? [],
                'global_error' => $_SESSION['global_error'] ?? null
            ]);
        }
        // non-AJAX: fall through to render HTML with session errors
    }

    // If still valid -> insert into pending_registrations
    if ($valid) {
        $name = trim("$ho $ten");
        $gender = $sex === '1' ? 'female' : ($sex === '2' ? 'male' : 'custom');

        // generate code securely
        try {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (\Throwable $ex) {
            $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        $expires = time() + 300; // 5 minutes

        try {
            // remove any existing pending for this email
            Database::NonQuery("DELETE FROM pending_registrations WHERE email = ?", [$email]);

            // insert new pending (if your column expires_at is DATETIME, convert)
            Database::NonQuery("INSERT INTO pending_registrations 
                (email, name, password_hash, birthday, gender, pronoun, pronoun_text, code, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                $email,
                $name,
                password_hash($matkhau, PASSWORD_DEFAULT),
                ($year && $month && $day) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null,
                $gender,
                $custom_gender,
                $custom_gender_text,
                $code,
                $expires
            ]);
        } catch (\Throwable $ex) {
            error_log("Pending insert failed: " . $ex->getMessage());
            $_SESSION['global_error'] = [
                'title' => 'Lỗi máy chủ',
                'message' => 'Đã xảy ra lỗi khi lưu, thử lại sau.'
            ];
            if ($isAjax) {
                respond_json(['ok' => false, 'global_error' => $_SESSION['global_error']]);
            }
            $valid = false;
        }

        // If insert succeeded
        if ($valid) {

            // PHPMailer send + debug (thay chỗ PHPMailer hiện tại)
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $subject = "Mã xác thực của bạn";
                $message = "Mã xác nhận của bạn là: $code\r\n\r\nMã này có hiệu lực trong 5 phút.";

                $sent = false;

                $mailConfig = [];
                $mailConfigFile = __DIR__ . '/../includes/mail_config.php';
                if (is_file($mailConfigFile)) {
                    $loadedConfig = require $mailConfigFile;
                    if (is_array($loadedConfig)) {
                        $mailConfig = $loadedConfig;
                    }
                }

                $smtpHost = getenv('FB_SMTP_HOST') ?: ($mailConfig['SMTP_HOST'] ?? '');
                $smtpPort = (int)(getenv('FB_SMTP_PORT') ?: ($mailConfig['SMTP_PORT'] ?? 587));
                $smtpUser = getenv('FB_SMTP_USER') ?: ($mailConfig['SMTP_USER'] ?? '');
                $smtpPass = getenv('FB_SMTP_PASS') ?: ($mailConfig['SMTP_PASS'] ?? '');
                $smtpSecure = getenv('FB_SMTP_SECURE') ?: ($mailConfig['SMTP_SECURE'] ?? 'tls');
                $fromEmail = getenv('FB_FROM_EMAIL') ?: ($mailConfig['FROM_EMAIL'] ?? $smtpUser);
                $siteName = getenv('FB_SITE_NAME') ?: ($mailConfig['SITE_NAME'] ?? 'Facebook');

                if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                    try {
                        $mail = new PHPMailer(true);

                        // Debug: 0 = off, 2 = client+server messages. Mở khi debugging, tắt (0) khi OK.
                        $mail->SMTPDebug = 0; // set to 2 while debugging
                        $mail->Debugoutput = function ($str, $level) {
                            error_log("PHPMailer debug level $level; message: $str");
                        };

                        // SMTP config from env/mail_config.php
                        $mail->isSMTP();
                        $mail->Host = $smtpHost;
                        $mail->SMTPAuth = true;
                        $mail->Username = $smtpUser;
                        $mail->Password = $smtpPass;
                        $mail->SMTPSecure = strtolower((string)$smtpSecure) === 'ssl'
                            ? PHPMailer::ENCRYPTION_SMTPS
                            : PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = $smtpPort > 0 ? $smtpPort : 587;

                        // Optional: in some environments with self-signed certs
                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer' => true,
                                'verify_peer_name' => true,
                                'allow_self_signed' => false,
                            ]
                        ];

                        // Use same From as Username to reduce rejections
                        $mail->setFrom($fromEmail ?: $smtpUser, $siteName ?: 'No Reply');
                        $mail->addAddress($email, $name ?: '');

                        $mail->isHTML(false);
                        $mail->Subject = $subject;
                        $mail->Body    = $message;
                        $mail->CharSet = 'UTF-8';

                        $mail->send();
                        $sent = true;
                        error_log("REGISTER INFO: PHPMailer (SMTP) sent to {$email} (code {$code})");
                    } catch (PHPMailerException $e) {
                        $sent = false;
                        error_log("REGISTER ERROR: PHPMailer exception: " . $e->getMessage());
                    } catch (\Throwable $t) {
                        $sent = false;
                        error_log("REGISTER ERROR: PHPMailer throwable: " . $t->getMessage());
                    }
                }

                // fallback to PHP mail()
                if (!$sent) {
                    $headers  = "From: \"No Reply\" <no-reply@yourdomain.com>\r\n";
                    $headers .= "Reply-To: no-reply@yourdomain.com\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                    $sent = mail($email, $subject, $message, $headers, "-fno-reply@yourdomain.com");
                    if ($sent) {
                        error_log("REGISTER INFO: PHP mail() succeeded for {$email} (code {$code})");
                    } else {
                        $last = error_get_last();
                        error_log("REGISTER ERROR: PHP mail() failed for {$email}. last error: " . print_r($last, true));
                    }
                }

                if (!$sent) {
                    $_SESSION['global_error'] = [
                        'title' => 'Không gửi được email',
                        'message' => 'Máy chủ không thể gửi mã xác nhận. Vui lòng thử lại hoặc dùng email khác.'
                    ];
                }
            } else {
                error_log("REGISTER INFO: contact provided is not an email: {$email} — skipping mail()");
            }


            $_SESSION['verify_code']    = $code;
            $_SESSION['verify_expires'] = $expires;
            $_SESSION['last_resend_time'] = time();


            // set session values for verification page
            $_SESSION['pending_email'] = $email;
            $_SESSION['register_email'] = $email;
            $_SESSION['register_name']  = $name;

            $_SESSION['register_data'] = [
                'email' => $email,
                'name'  => $name,
            ];

            // clear old inputs/errors
            unset($_SESSION['old'], $_SESSION['errors'], $_SESSION['global_error']);

            // IMPORTANT: write session to storage before redirecting
            session_write_close();

            // For AJAX return this direct redirect target (client does window.location)
            if ($isAjax) {
                respond_json(['ok' => true, 'redirect' => 'verify.php']);
            }

            // Non-AJAX: server-side redirect
            if (!headers_sent()) {
                if (ob_get_length()) {
                    ob_clean();
                }
                header('Location: verify.php');
                exit;
            } else {
                // headers already sent - fallback client-side redirect
                error_log("Redirect failed: headers already sent. headers_list: " . print_r(headers_list(), true));
                echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirect</title></head><body>';
                echo '<p>Chuyển hướng tới trang xác thực...</p>';
                echo '<script>window.location.href = "verify.php";</script>';
                echo '</body></html>';
                if (ob_get_length()) {
                    ob_end_flush();
                }
                exit;
            }
        }
    }
}
// end POST handling -> render HTML below
?>


<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký Facebook</title>
    <link rel="icon" href="../uploads/fb_logo.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Helvetica+Neue:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/register.css">
</head>

<body>
    <div class="fb-logo fb-text-logo">fɑcebook</div>
    <div class="form-container">
        <div class="form-header">
            <h1><?= e($text['signup_title'] ?? 'Tạo tài khoản mới') ?></h1>
            <div class="subtitle"><?= $text['signup_slogan'] ?? 'Nhanh chóng và dễ dàng.' ?></div>
            <?php if (isset($_SESSION['global_error'])): ?>
                <div style="
                background: #fdf2f2;
                border: 1px solid #f5c2c7;
                border-radius: 8px;
                padding: 14px 16px;
                margin: 16px 0;
                color: #b00020;
                font-size: 15px;
                line-height: 1.4;
                font-weight: 500;">
                    <strong><?= e($_SESSION['global_error']['title']) ?></strong><br>
                    <?= e($_SESSION['global_error']['message']) ?>
                </div>
            <?php unset($_SESSION['global_error']);
            endif; ?>
        </div>
        <div class="form-content">
            <form method="POST" id="registerForm" accept-charset="utf-8">
                <div class="hovaten d-flex gap-2">
                    <div class="input-group <?= hasError('ho') ? 'error' : '' ?>">
                        <input type="text" name="ho" placeholder="<?= e($text['surname'] ?? 'Họ') ?>" value="<?= e(old('ho')) ?>">
                        <div class="error-tooltip"><?= e(getError('ho') ?: ($text['enter_surname'] ?? 'Tên bạn là gì?')) ?></div>
                    </div>
                    <div class="input-group <?= hasError('ten') ? 'error' : '' ?>">
                        <input type="text" name="ten" placeholder="<?= e($text['first_name'] ?? 'Tên') ?>" value="<?= e(old('ten')) ?>">
                        <div class="error-tooltip"><?= e(getError('ten') ?: ($text['enter_firstname'] ?? 'Tên bạn là gì?')) ?></div>
                    </div>
                </div>

                <div class="label-with-help <?= hasError('birthday') ? 'error' : '' ?>" id="birthdayLabel"><?= e($text['date_of_birth'] ?? 'Ngày sinh') ?>
                    <span class="help-icon">?</span>
                </div>

                <div class="birthday-wrapper <?= hasError('birthday') ? 'error' : '' ?>">
                    <div class="birthday-inner">
                        <div class="birthday_area" id="birthdayArea">
                            <div class="fake-select" data-name="birthday_day">
                                <div class="selected-value"><?= (int)old('day') > 0 ? e(old('day')) : e($text['day'] ?? 'Ngày') ?></div>
                                <div class="arrow">
                                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                                        <path d="M5 8 L10 13 L15 8" stroke="#1c1e21" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </div>
                                <ul class="options">
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                        <li data-value="<?= $i ?>" <?= (int)old('day') == $i ? 'class="selected"' : '' ?>><?= $i ?></li>
                                    <?php endfor; ?>
                                </ul>
                            </div>

                            <div class="fake-select" data-name="birthday_month">
                                <div class="selected-value">
                                    <?php
                                    $months = [
                                        'Tháng 1',
                                        'Tháng 2',
                                        'Tháng 3',
                                        'Tháng 4',
                                        'Tháng 5',
                                        'Tháng 6',
                                        'Tháng 7',
                                        'Tháng 8',
                                        'Tháng 9',
                                        'Tháng 10',
                                        'Tháng 11',
                                        'Tháng 12'
                                    ];
                                    echo (int)old('month') > 0 ? e($months[old('month') - 1]) : e($text['month'] ?? 'Tháng');
                                    ?>
                                </div>
                                <div class="arrow">
                                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                                        <path d="M5 8 L10 13 L15 8" stroke="#1c1e21" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </div>
                                <ul class="options">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <li data-value="<?= $i ?>" <?= (int)old('month') == $i ? 'class="selected"' : '' ?>><?= e($months[$i - 1]) ?></li>
                                    <?php endfor; ?>
                                </ul>
                            </div>

                            <div class="fake-select" data-name="birthday_year">
                                <div class="selected-value"><?= (int)old('year') > 0 ? e(old('year')) : e($text['year'] ?? 'Năm') ?></div>
                                <div class="arrow">
                                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                                        <path d="M5 8 L10 13 L15 8" stroke="#1c1e21" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </div>
                                <ul class="options" style="max-height:200px; overflow-y:auto;">
                                    <?php
                                    $current_year = date('Y');
                                    for ($i = $current_year; $i >= 1905; $i--):
                                    ?>
                                        <li data-value="<?= $i ?>" <?= (int)old('year') == $i ? 'class="selected"' : '' ?>><?= $i ?></li>
                                    <?php endfor; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- hidden inputs -->
                        <input type="hidden" name="birthday_day" value="<?= e(old('day', 0)) ?>">
                        <input type="hidden" name="birthday_month" value="<?= e(old('month', 0)) ?>">
                        <input type="hidden" name="birthday_year" value="<?= e(old('year', 0)) ?>">

                        <div class="error-tooltip"><?= e(getError('birthday') ?: ($text['birthday_hint'] ?? 'Hình như bạn đã nhập sai thông tin. Hãy nhớ dùng ngày sinh thật của mình nhé.')) ?></div>
                    </div>

                    <div class="age-area" id="ageArea" style="display:none;">
                        <?= e($text['age_title'] ?? 'Tuổi') ?>
                        <input type="text" name="age_input" placeholder="<?= e($text['age_input'] ?? 'Tuổi của bạn') ?>" min="13" max="120" value="<?= e(old('age_input')) ?>">
                        <span class="use-birthday" onclick="switchToBirthday()"><?= e($text['use_birthday'] ?? 'Dùng ngày sinh') ?></span>
                        <div class="error-tooltip"><?= e(getError('age') ?: ($text['age_required'] ?? 'Vui lòng nhập tuổi của bạn')) ?></div>
                    </div>
                </div>

                <div class="label-with-help <?= hasError('sex') ? 'error' : '' ?>" id="genderLabel"><?= e($text['gender'] ?? 'Giới tính') ?> <span class="help-icon">?</span></div>
                <div class="gender-wrapper">
                    <div class="gt-outer">
                        <label class="gender-label"><span><?= e($text['female'] ?? 'Nữ') ?></span><input type="radio" name="sex" value="1" <?= old('sex') == '1' ? 'checked' : '' ?> onclick="hideCustom()"><span class="radio-check"></span></label>
                        <label class="gender-label"><span><?= e($text['male'] ?? 'Nam') ?></span><input type="radio" name="sex" value="2" <?= old('sex') == '2' ? 'checked' : '' ?> onclick="hideCustom()"><span class="radio-check"></span></label>
                        <label class="gender-label custom-label"><span><?= e($text['custom'] ?? 'Tùy chỉnh') ?></span><input type="radio" name="sex" value="-1" <?= old('sex') == '-1' ? 'checked' : '' ?> onclick="showCustom()"><span class="radio-check"></span></label>
                    </div>
                </div>

                <div id="customouter" style="display:<?= old('sex') == '-1' ? 'block' : 'none' ?>;">
                    <select name="custom_gender">
                        <option value=""><?= e($text['choose_pronoun'] ?? 'Chọn danh xưng') ?></option>
                        <option value="she" <?= old('custom_gender') == 'she' ? 'selected' : '' ?>><?= e($text['custom_pronoun_she'] ?? 'Cô ấy: "Chúc cô ấy sinh nhật vui vẻ!"') ?></option>
                        <option value="he" <?= old('custom_gender') == 'he' ? 'selected' : '' ?>><?= e($text['custom_pronoun_he'] ?? 'Anh ấy: "Chúc anh ấy sinh nhật vui vẻ!"') ?></option>
                        <option value="they" <?= old('custom_gender') == 'they' ? 'selected' : '' ?>><?= e($text['custom_pronoun_they'] ?? 'Họ: "Chúc họ sinh nhật vui vẻ!"') ?></option>
                    </select>
                    <div class="error-tooltip" style="left:-300px; width:280px;"><?= e(getError('sex') ?: '') ?></div>
                    <span class="dx"><?= e($text['contact_upload'] ?? 'Danh xưng của bạn hiển thị với tất cả mọi người.') ?></span>
                    <input type="text" name="custom_gender_text" placeholder="<?= e($text['custom_gender_label'] ?? 'Giới tính (Không bắt buộc)') ?>" value="<?= e(old('custom_gender_text')) ?>">
                </div>

                <div class="input-group em<?= hasError('email') ? ' error' : '' ?>">
                    <input type="text" name="email" autocomplete="email" placeholder="<?= e($text['mobile_or_email'] ?? 'Số di động hoặc email') ?>" value="<?= e(old('email')) ?>">
                    <div class="error-tooltip"><?= e(getError('email') ?: ($text['email_hint'] ?? 'Bạn sẽ sử dụng thông tin này khi đăng nhập và khi cần đặt lại mật khẩu.')) ?></div>
                </div>

                <div class="input-group mk <?= hasError('matkhau') ? 'error' : '' ?>">
                    <input type="password" name="matkhau" placeholder="<?= e($text['new_password'] ?? 'Mật khẩu mới') ?>">
                    <div class="error-tooltip"><?= e(getError('matkhau') ?: ($text['password_hint'] ?? 'Nhập mật khẩu có tối thiểu 6 ký tự bao gồm số, chữ cái và dấu chấm câu (như ! và &).')) ?></div>
                </div>

                <p class="thongbao"><?= $text['contact_upload_info'] ?? 'Những người dùng dịch vụ của chúng tôi có thể đã tải thông tin liên hệ của bạn lên Facebook.' ?> <br><br>
                    <?= $text['terms_policy'] ?? 'Bằng cách nhấp vào Đăng ký, bạn đồng ý với các điều khoản.' ?>
                </p>

                <button type="submit"><?= e($text['signup'] ?? 'Đăng ký') ?></button>

                <div class="login-link">
                    <a href="login.php"><?= e($text['already_account'] ?? 'Bạn đã có tài khoản ư?') ?></a>
                </div>
            </form>
        </div>
    </div>


    <script src="../assets/js/register.js"></script>
    <?php include '../includes/footer.php'; ?>
</body>

</html>