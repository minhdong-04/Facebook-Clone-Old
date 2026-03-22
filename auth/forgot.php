<?php
session_start();
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/db.php';

// === Cấu hình (sửa cho phù hợp) ===
// Ưu tiên: env vars -> includes/mail_config.php -> fallback values.
function env_str(string $key, string $default = ''): string {
  $v = getenv($key);
  if ($v === false || $v === null) return $default;
  $v = trim((string)$v);
  return $v === '' ? $default : $v;
}

$cfg = [];
$cfgFile = __DIR__ . '/../includes/mail_config.php';
if (is_file($cfgFile)) {
  $loaded = include $cfgFile;
  if (is_array($loaded)) $cfg = $loaded;
}

$FROM_EMAIL = env_str('FB_FROM_EMAIL', (string)($cfg['FROM_EMAIL'] ?? ''));
$SITE_NAME  = env_str('FB_SITE_NAME',  (string)($cfg['SITE_NAME']  ?? 'Facebook'));

// Base URL cho link reset (tự nhận dạng theo host + đường dẫn hiện tại)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/auth/forgot.php')), '/');
$RESET_URL_BASE = $scheme . '://' . $host . $basePath . '/reset.php';
// SMTP (nếu dùng PHPMailer + SMTP). Nếu để trống => fallback dùng mail()
$SMTP_HOST   = env_str('FB_SMTP_HOST',   (string)($cfg['SMTP_HOST']   ?? ''));   // ví dụ: smtp.gmail.com
$SMTP_PORT   = (int)env_str('FB_SMTP_PORT', (string)($cfg['SMTP_PORT'] ?? '587')); // 587 or 465
$SMTP_USER   = env_str('FB_SMTP_USER',   (string)($cfg['SMTP_USER']   ?? ''));   // email gửi
$SMTP_PASS   = env_str('FB_SMTP_PASS',   (string)($cfg['SMTP_PASS']   ?? ''));   // app password / smtp password
$SMTP_SECURE = env_str('FB_SMTP_SECURE', (string)($cfg['SMTP_SECURE'] ?? 'tls')); // 'tls' hoặc 'ssl'

// nạp thủ công PHPMailer (repo này không dùng composer autoload)
require_once __DIR__ . '/../vendor/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer-master/src/SMTP.php';

// helper: token (selector + validator)
function pr_random_base64(int $len = 18): string {
  return rtrim(strtr(base64_encode(random_bytes($len)), '+/', '-_'), '=');
}

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));

    // validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $notice = "Vui lòng nhập email hợp lệ.";
    } else {
        // tìm user (nếu có)
        $user = Database::GetRow("SELECT id, email, name FROM users WHERE email = ?", [$email]);

        // Không trả về error khác nhau để tránh lộ user tồn tại hay không
        // Nếu user tồn tại -> tạo token & gửi email, nếu không -> vẫn trả lời như thể đã gửi
        if ($user) {
          // tạo token, lưu hash vào DB (1 giờ) theo schema hiện có: password_resets(user_id, selector, validator_hash, expires_at)
          $userId = (int)$user['id'];
          $selector = substr(pr_random_base64(9), 0, 12);
          $validator_raw = bin2hex(random_bytes(32));
          $validator_hash = hash('sha256', $validator_raw);
          // Use MySQL to compute expires_at to avoid PHP/MySQL timezone mismatches

          try {
            Database::NonQuery("DELETE FROM password_resets WHERE user_id = ?", [$userId]);
            Database::NonQuery(
              "INSERT INTO password_resets (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))",
              [$userId, $selector, $validator_hash]
            );
          } catch (Throwable $e) {
            error_log("password_reset db error: " . $e->getMessage());
          }

          $publicToken = $selector . ':' . $validator_raw;
          $link = $RESET_URL_BASE . '?token=' . rawurlencode($publicToken);

            // chuẩn bị subject/message (plain text)
            $subject = "{$SITE_NAME} — Yêu cầu đặt lại mật khẩu";
            $message = "Xin chào,\n\n"
                     . "Bạn (hoặc ai đó) đã yêu cầu đặt lại mật khẩu cho tài khoản {$email}.\n\n"
                     . "Để đặt lại mật khẩu, nhấp vào liên kết sau (có hiệu lực 1 giờ):\n\n"
                     . "{$link}\n\n"
                     . "Nếu bạn không yêu cầu, hãy bỏ qua email này.\n\n"
                     . "Trân trọng,\n{$SITE_NAME}";

            // --- Gửi email: ưu tiên PHPMailer nếu đã cài ---
            $sent = false;

            // nếu FROM_EMAIL rỗng, mặc định dùng SMTP_USER để tránh bị SMTP chặn "From"
            $fromEmailEffective = $FROM_EMAIL !== '' ? $FROM_EMAIL : $SMTP_USER;
                        // Dùng PHPMailer + SMTP nếu đã cấu hình SMTP
                        if (
                            class_exists('PHPMailer\\PHPMailer\\PHPMailer')
                            && !empty($SMTP_HOST) && !empty($SMTP_USER) && !empty($SMTP_PASS)
                        ) {
                            try {
                                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                                $mail->CharSet = 'UTF-8';
                                $mail->isSMTP();
                                $mail->Host       = $SMTP_HOST;
                                $mail->SMTPAuth   = true;
                                $mail->Username   = $SMTP_USER;
                                $mail->Password   = $SMTP_PASS;
                                $mail->SMTPSecure = ($SMTP_SECURE === 'ssl')
                                    ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                                    : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port       = (int)$SMTP_PORT;

                                if ($fromEmailEffective !== '') {
                                  $mail->setFrom($fromEmailEffective, $SITE_NAME);
                                }
                                $mail->addAddress($email);
                                $mail->isHTML(false);
                                $mail->Subject = $subject;
                                $mail->Body    = $message;
                                $mail->send();
                                $sent = true;
                            } catch (Throwable $ex) {
                                error_log("PHPMailer send failed: " . $ex->getMessage());
                                $sent = false;
                            }
                        }

            // fallback: nếu PHPMailer không gửi được hoặc không cài, dùng mail()
            if (!$sent) {
                // Thiết lập headers UTF-8
                $fallbackFrom = $FROM_EMAIL !== '' ? $FROM_EMAIL : ('no-reply@' . ($host ?: 'localhost'));
                $headers = "From: {$SITE_NAME} <{$fallbackFrom}>\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                // @ để tránh warning hiển thị, đã log DB lỗi bên trên nếu xảy ra
                @mail($email, $subject, $message, $headers);
            }
        }

        // Thông báo chung (không tiết lộ đoạn user tồn tại hay không)
        $notice = "Nếu email tồn tại trong hệ thống, bạn sẽ nhận được hướng dẫn qua email.";
    }
}

// === Hiển thị form giống như yêu cầu ===
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Quên mật khẩu - <?=htmlspecialchars($SITE_NAME)?></title>
  <link rel="stylesheet" href="../assets/css/style.css"> <!-- nếu có -->
  <style>
    /* CSS nhỏ để form nhìn giống kiểu Facebook modal đơn giản */
    body { font-family: Arial, Helvetica, sans-serif; background:#f0f2f5; margin:0; padding:40px 0; display:flex; justify-content:center; }
    .forgot-wrap { width:420px; background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.1); padding:28px; }
    h2 { text-align:center; color:#111; margin:0 0 18px; font-weight:600; }
    .notice { background:#f7f9fb; border-left:4px solid #dbe6ff; padding:10px 12px; margin-bottom:12px; color:#333; border-radius:4px; }
    .form-row { margin-bottom:14px; }
    label { display:block; font-size:14px; color:#333; margin-bottom:6px; }
    input[type="email"] { width:100%; padding:12px 14px; border:1px solid #d8dde6; border-radius:8px; font-size:15px; box-sizing:border-box; }
    button { display:block; width:100%; padding:12px 14px; background:#1877f2; color:#fff; border:none; border-radius:8px; font-weight:700; font-size:16px; cursor:pointer; }
    button:hover { background:#166fe5; }
    .small { margin-top:12px; text-align:center; color:#0666d4; font-size:14px; text-decoration:none; display:block;}
  </style>
</head>
<body>
  <div class="forgot-wrap" role="main">
    <h2>Quên mật khẩu</h2>

    <?php if (!empty($notice)): ?>
      <div class="notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off" novalidate>
      <div class="form-row">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required placeholder="Nhập email của bạn">
      </div>
      <button type="submit">Gửi hướng dẫn</button>
    </form>

    <a class="small" href="login.php">Quay lại đăng nhập</a>
  </div>
</body>
</html>
