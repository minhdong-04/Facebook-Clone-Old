<?php
// Bootstrap đăng nhập tự động bằng cookie "remember"
// Đảm bảo đúng đường dẫn và khởi tạo PDO trước khi gọi helper.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../auth/remember.php';

if (!empty($_SESSION["user_id"])) return;

$pdo = Database::GetPDO();

// Try auto login via secure remember cookie
$user = remember_secure_login($pdo);

if ($user) {
    $_SESSION["logged_in"] = true;
    $_SESSION["user_id"] = $user["id"];
    $_SESSION["email"]  = $user["email"];
    $_SESSION["name"]   = $user["name"];
    $_SESSION["avatar"] = $user["avatar"];
}
