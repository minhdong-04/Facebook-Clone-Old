<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Kiểm tra session register_data
if (!isset($_SESSION['register_data']) || empty($_SESSION['register_data']['email'])) {
    echo json_encode(['success' => false, 'message' => 'No pending registration.']);
    exit;
}

$email = trim($_SESSION['register_data']['email']);

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Throttle: không cho gửi quá nhiều lần (ví dụ 60s)
$cooldown = 60; // giây
$now = time();
$last = (int)($_SESSION['last_resend_time'] ?? 0);
$elapsed = $now - $last;
if ($elapsed < $cooldown) {
    $remaining = $cooldown - $elapsed;
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng chờ trước khi gửi lại.',
        'retry_after' => $remaining
    ]);
    exit;
}

// Giới hạn số lần gửi trong 1 giờ (ví dụ 5 lần/giờ) để tránh lạm dụng
$hour_limit = 5;
$_SESSION['resend_history'] = $_SESSION['resend_history'] ?? [];
// loại bỏ các bản ghi > 1 giờ
$_SESSION['resend_history'] = array_filter($_SESSION['resend_history'], function($t) use ($now) {
    return ($now - $t) <= 3600;
});
if (count($_SESSION['resend_history']) >= $hour_limit) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn đã gửi mã quá nhiều lần. Vui lòng thử lại sau.',
    ]);
    exit;
}

// Tạo mã 6 chữ số
try {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
} catch (Exception $e) {
    // fallback
    $code = str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Lưu vào session (không trả mã cho client)
$_SESSION['verify_code'] = $code;
$_SESSION['verify_expires'] = $now + 300; // 5 phút
$_SESSION['last_resend_time'] = $now;
$_SESSION['resend_history'][] = $now;

// Tùy chỉnh nội dung email (text + html)
$subject = "Mã xác thực của bạn";
$plain = "Mã xác nhận của bạn là: $code\nMã có hiệu lực 5 phút.";
$html = "<p>Mã xác nhận của bạn là: <strong>$code</strong></p><p>Mã có hiệu lực 5 phút.</p>";

// Gửi mail - dùng mail() nếu chưa cài PHPMailer
$from = 'no-reply@yourdomain.com';
$headers = "From: Meta <{$from}>\r\n";
$headers .= "Reply-To: {$from}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

// Trên một số hệ thống bạn có thể thêm parameter '-f' để đặt Return-Path
$mail_ok = false;
try {
    // mail() trả true nếu envelope accepted — nhiều host dev không gửi thực
    $mail_ok = @mail($email, $subject, $html, $headers, "-f{$from}");
} catch (Throwable $ex) {
    error_log("resend_verify mail error: " . $ex->getMessage());
    $mail_ok = false;
}

// Nếu mail() thất bại — ghi log để debug và trả success=false (client sẽ hiển thị thông báo)
if ($mail_ok) {
    echo json_encode(['success' => true, 'message' => 'Mã đã được gửi lại.']);
    exit;
} else {
    // Lưu log để kiểm tra (file or error_log)
    $logLine = sprintf("[%s] resend_verify failed to send to %s, code=%s\n", date('c'), $email, $code);
    // Ghi file log (đảm bảo webserver có quyền ghi)
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/verify_resend.log', $logLine, FILE_APPEND | LOCK_EX);

    // Trả success = true vẫn có thể chấp nhận (thử nghiệm), hoặc trả false nếu bắt buộc phải gửi mail.
    // Mình trả false và thông báo admin kiểm tra; bạn có thể đổi thành true cho môi trường dev.
    echo json_encode([
        'success' => false,
        'message' => 'Không thể gửi email. Kiểm tra cấu hình gửi email trên server hoặc sử dụng SMTP.',
        'debug' => null
    ]);
    exit;
}
