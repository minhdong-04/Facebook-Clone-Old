<?php
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/remember.php';

$regEmail  = $_SESSION['register_email']  ?? null;
$regName   = $_SESSION['register_name']   ?? null;
$regAvatar = $_SESSION['register_avatar'] ?? '';
$userId    = $_SESSION['user_id'] ?? null;

// presence: mark offline best-effort
if ($userId) {
    try {
        Database::NonQuery('UPDATE users SET is_online = 0, last_active = NOW() WHERE id = ?', [(int)$userId]);
    } catch (Throwable $e) {
        // ignore
    }
}

// Xóa token "remember" và cookie nếu có userId
if ($userId) {
    try {
        $pdo = Database::GetPDO();
        remember_secure_clear((int)$userId, $pdo);
    } catch (Throwable $e) {
        // bỏ qua nếu không xóa được, vẫn tiếp tục logout
    }
}

// hủy session trước nhưng CHƯA redirect
session_destroy();
session_write_close();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Đang đăng xuất...</title>
</head>
<body>
<script>
// Nếu có tài khoản đăng ký gần đây → thêm vào localStorage
(function() {
    const email  = <?= json_encode($regEmail) ?>;
    const name   = <?= json_encode($regName) ?>;
    const avatar = <?= json_encode($regAvatar) ?>;

    if (email) {
        const KEY = "saved_accounts_v1";
        let list = [];

        try {
            list = JSON.parse(localStorage.getItem(KEY) || "[]");
        } catch(e) {}

        if (!list.some(acc => acc.email === email)) {
            list.push({
                email: email,
                name:  name || email,
                avatar: avatar || ""
            });
            localStorage.setItem(KEY, JSON.stringify(list));
        }
    }

    // Redirect về trang login
    window.location.href = "login.php";
})();
</script>
</body>
</html>
