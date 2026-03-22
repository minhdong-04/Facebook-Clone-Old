<?php
session_start();
include __DIR__ . '/../includes/db.php'; // chỉnh path nếu cần

// Nếu không có dữ liệu đăng ký thì trả về trang đăng ký
if (!isset($_SESSION['register_data']) || empty($_SESSION['register_data']['email'])) {
    header('Location: register.php');
    exit;
}

// Chỉ xử lý POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verify.php');
    exit;
}

$email = $_SESSION['register_data']['email'];

// Lấy mã người dùng nhập — chỉ lấy chữ số
$code_input = trim($_POST['code'] ?? '');
$code_input = preg_replace('/\D/', '', $code_input);

// Lấy pending từ DB theo email (mới nhất)
$pending = Database::GetRow("SELECT * FROM pending_registrations WHERE email = ? ORDER BY id DESC LIMIT 1", [$email]);

if (!$pending) {
    // không tìm thấy pending — quay về đăng ký
    unset($_SESSION['register_data'], $_SESSION['verify_code'], $_SESSION['verify_expires'], $_SESSION['last_resend_time']);
    $_SESSION['verify_error'] = 'Không tìm thấy đăng ký tạm. Vui lòng đăng ký lại.';
    header('Location: register.php');
    exit;
}

// Lấy code và expires từ row pending
$expected_code = (string)($pending['code'] ?? '');
$expires_at = $pending['expires_at'] ?? null;

// chuẩn hóa expires -> timestamp
if (is_numeric($expires_at)) {
    $expires_ts = (int)$expires_at;
} else {
    $expires_ts = $expires_at ? strtotime($expires_at) : 0;
}

// kiểm tra expired
if (!$expected_code || $expires_ts === 0 || time() > $expires_ts) {
    // xóa pending expired để dọn dẹp
    Database::NonQuery("DELETE FROM pending_registrations WHERE id = ?", [$pending['id']]);
    unset($_SESSION['register_data'], $_SESSION['verify_code'], $_SESSION['verify_expires'], $_SESSION['last_resend_time']);
    $_SESSION['verify_error'] = 'Mã xác thực đã hết hạn. Vui lòng gửi lại.';
    header('Location: verify.php');
    exit;
}

// so sánh mã (dùng hash_equals cho an toàn nếu muốn)
if (!hash_equals($expected_code, (string)$code_input)) {
    $_SESSION['verify_error'] = 'Mã xác thực không đúng.';
    header('Location: verify.php');
    exit;
}

// kiểm tra email đã tồn tại trong users
$exists = Database::GetRow("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
if ($exists) {
    // xóa pending và redirect login
    Database::NonQuery("DELETE FROM pending_registrations WHERE id = ?", [$pending['id']]);
    unset($_SESSION['register_data'], $_SESSION['verify_code'], $_SESSION['verify_expires'], $_SESSION['last_resend_time']);
    $_SESSION['notice'] = 'Email đã được sử dụng. Vui lòng đăng nhập.';
    header('Location: login.php');
    exit;
}

// giờ lấy dữ liệu cần chèn từ pending row
$name = $pending['name'] ?? '';
$password_hash = $pending['password_hash'] ?? null; // cột bạn lưu ở pending
$birthday = $pending['birthday'] ?? null;
$gender = $pending['gender'] ?? null;
$pronoun = $pending['pronoun'] ?? null;
$pronoun_text = $pending['pronoun_text'] ?? null;

// bảo đảm có password_hash
if (empty($password_hash)) {
    error_log('complete_register: missing password_hash in pending for email ' . $email);
    $_SESSION['verify_error'] = 'Có lỗi khi tạo tài khoản. Vui lòng thử lại.';
    header('Location: verify.php');
    exit;
}

// chèn vào users (trường password ở users là 'password' theo schema bạn cung cấp)
try {
    $sql = "INSERT INTO users (name, email, password, birthday, gender, pronoun, pronoun_text, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    $res = Database::NonQuery($sql, [
        $name,
        $email,
        $password_hash,
        $birthday,
        $gender,
        $pronoun,
        $pronoun_text
    ]);

    // Database::NonQuery có thể trả id hoặc true; kiểm tra theo implement của bạn
    if ($res === null) {
        // lỗi chèn
        error_log('complete_register: insert returned null for email ' . $email);
        $_SESSION['verify_error'] = 'Có lỗi xảy ra khi tạo tài khoản. Vui lòng thử lại sau.';
        header('Location: verify.php');
        exit;
    }

    // nếu thành công: xóa pending
    Database::NonQuery("DELETE FROM pending_registrations WHERE id = ?", [$pending['id']]);

    // dọn session tạm
    unset($_SESSION['register_data'], $_SESSION['verify_code'], $_SESSION['verify_expires'], $_SESSION['last_resend_time']);

    // đăng nhập tự động: lấy user vừa tạo
    $new = Database::GetRow("SELECT id, name, avatar FROM users WHERE email = ? LIMIT 1", [$email]);
    if ($new && !empty($new['id'])) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = (int)$new['id'];
        $_SESSION['user_name'] = $new['name'] ?? $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_avatar'] = $new['avatar'] ?? '';
    }
    // thông báo thành công
    $_SESSION['registered'] = 'Đăng ký thành công. Bạn có thể đăng nhập.';
    session_write_close();
    header('Location: ../pages/home.php');
    exit;

} catch (Exception $ex) {
    error_log('complete_register exception: ' . $ex->getMessage());
    $_SESSION['verify_error'] = 'Có lỗi xảy ra khi tạo tài khoản. Vui lòng thử lại sau.';
    header('Location: verify.php');
    exit;
}
