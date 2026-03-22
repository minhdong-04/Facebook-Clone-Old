<?php
session_start();
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/db.php';

// === Biến trạng thái ===
$error = '';
$success = '';
$token_valid = false;

// Lấy tham số từ URL
$publicToken = (string)($_GET['token'] ?? '');
$resetRow = null;
$resetUserId = null;

// 1. Kiểm tra Token có hợp lệ không (Logic chạy ngay khi vào trang)
if (!empty($publicToken) && strpos($publicToken, ':') !== false) {
    [$selector, $validator_raw] = explode(':', $publicToken, 2);
    $selector = trim((string)$selector);
    $validator_raw = trim((string)$validator_raw);

    if ($selector !== '' && $validator_raw !== '') {
        $resetRow = Database::GetRow(
            "SELECT pr.id AS reset_id, pr.user_id, pr.validator_hash, pr.expires_at, u.email
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.selector = ? AND pr.expires_at > NOW()
             LIMIT 1",
            [$selector]
        );

        if ($resetRow) {
            $input_hash = hash('sha256', $validator_raw);
            if (hash_equals((string)$resetRow['validator_hash'], (string)$input_hash)) {
                $token_valid = true;
                $resetUserId = (int)$resetRow['user_id'];
            } else {
                // token sai -> log để debug, sau đó xóa token của user để an toàn
                error_log("[reset] validator mismatch for selector=" . $selector . " user_id=" . (int)$resetRow['user_id'] . " token=" . $publicToken);
                try {
                    Database::NonQuery("DELETE FROM password_resets WHERE user_id = ?", [(int)$resetRow['user_id']]);
                } catch (Throwable $e) {
                    error_log("[reset] failed to delete password_resets: " . $e->getMessage());
                }
                $error = "Liên kết không hợp lệ.";
            }
        } else {
            // không tìm thấy row -> log selector + token để debug
            error_log("[reset] no reset row for selector=" . $selector . " token=" . $publicToken);
            $error = "Liên kết không hợp lệ hoặc đã hết hạn.";
        }
    } else {
        $error = "Thiếu thông tin xác thực.";
    }
} else {
    $error = "Thiếu thông tin xác thực.";
}

// 2. Xử lý khi người dùng Submit mật khẩu mới (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid && $resetUserId) {
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';

    if (strlen($pass1) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự.";
    } elseif ($pass1 !== $pass2) {
        $error = "Hai mật khẩu không khớp nhau.";
    } else {
        // Mọi thứ ok -> Tiến hành đổi mật khẩu
        try {
            // Hash mật khẩu mới (dùng thuật toán mặc định hiện đại của PHP: Bcrypt/Argon2)
            $new_hash = password_hash($pass1, PASSWORD_DEFAULT);

            // Cập nhật mật khẩu trong bảng users
            Database::NonQuery("UPDATE users SET password = ? WHERE id = ?", [$new_hash, (int)$resetUserId]);

            // QUAN TRỌNG: Xóa token sau khi dùng xong để không thể dùng lại link này
            Database::NonQuery("DELETE FROM password_resets WHERE user_id = ?", [(int)$resetUserId]);

            $success = "Mật khẩu đã được thay đổi thành công!";
            $token_valid = false; // Ẩn form đi để hiện thông báo thành công
        } catch (Throwable $e) {
            error_log("Reset password failed: " . $e->getMessage());
            $error = "Có lỗi xảy ra, vui lòng thử lại sau.";
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Đặt lại mật khẩu - Facebook</title>
  <style>
    /* CSS tương tự trang quên mật khẩu */
    body { font-family: Arial, Helvetica, sans-serif; background:#f0f2f5; margin:0; padding:40px 0; display:flex; justify-content:center; }
    .reset-wrap { width:420px; background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(0,0,0,0.1); padding:28px; }
    h2 { text-align:center; color:#111; margin:0 0 18px; font-weight:600; }
    .msg { padding:12px; margin-bottom:15px; border-radius:4px; font-size:14px; }
    .error { background:#ffebe8; color:#c62828; border:1px solid #ffcdd2; }
    .success { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; }
    
    .form-row { margin-bottom:14px; }
    label { display:block; font-size:14px; color:#333; margin-bottom:6px; font-weight:bold; }
    input { width:100%; padding:12px 14px; border:1px solid #d8dde6; border-radius:8px; font-size:15px; box-sizing:border-box; }
    button { display:block; width:100%; padding:12px 14px; background:#1877f2; color:#fff; border:none; border-radius:8px; font-weight:700; font-size:16px; cursor:pointer; margin-top:10px;}
    button:hover { background:#166fe5; }
    .links { margin-top:15px; text-align:center; }
    .links a { color:#1877f2; text-decoration:none; font-size:14px; }
    .links a:hover { text-decoration:underline; }
  </style>
</head>
<body>

<div class="reset-wrap">
    <h2>Đặt lại mật khẩu</h2>

    <?php if (!empty($error)): ?>
        <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php if (!$token_valid && empty($success)): ?>
            <div class="links">
                <a href="forgot.php">Yêu cầu liên kết mới</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="msg success"><?= htmlspecialchars($success) ?></div>
        <div class="links">
            <button onclick="window.location.href='login.php'">Đăng nhập ngay</button>
        </div>
    <?php endif; ?>

    <?php if ($token_valid): ?>
        <form method="post" autocomplete="off">
            <div class="form-row">
                <label for="pass1">Mật khẩu mới</label>
                <input type="password" id="pass1" name="pass1" required placeholder="Nhập mật khẩu mới" minlength="6">
            </div>
            
            <div class="form-row">
                <label for="pass2">Nhập lại mật khẩu</label>
                <input type="password" id="pass2" name="pass2" required placeholder="Xác nhận mật khẩu">
            </div>

            <button type="submit">Lưu mật khẩu</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>