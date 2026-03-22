<?php
// actions/update_cover.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/socket_emit.php';

function fb_is_ajax_request(): bool
{
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        return true;
    }
    return true;
}

function fb_json_fail(string $message, int $status = 400): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Database::isLoggedIn()) {
    fb_json_fail('Chưa đăng nhập', 401);
}

$user = Database::getCurrentUser();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    fb_json_fail('User không hợp lệ', 401);
}

if (!isset($_FILES['cover'])) {
    fb_json_fail('Thiếu file', 400);
}

$f = $_FILES['cover'];
$err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err === UPLOAD_ERR_NO_FILE) {
    fb_json_fail('Chưa chọn ảnh', 400);
}
if ($err !== UPLOAD_ERR_OK) {
    fb_json_fail('Lỗi upload ảnh', 400);
}

$maxSize = 8 * 1024 * 1024; // 8MB
$size = (int)($f['size'] ?? 0);
if ($size <= 0 || $size > $maxSize) {
    fb_json_fail('Ảnh quá lớn (tối đa 8MB)', 400);
}

$tmp = (string)($f['tmp_name'] ?? '');
if ($tmp === '' || !is_uploaded_file($tmp)) {
    fb_json_fail('Upload không hợp lệ', 400);
}

$info = @getimagesize($tmp);
if (!$info || empty($info['mime'])) {
    fb_json_fail('File không phải ảnh hợp lệ', 400);
}

$mime = strtolower((string)$info['mime']);
$ext = match ($mime) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    default => ''
};

if ($ext === '') {
    fb_json_fail('Định dạng ảnh không hỗ trợ (jpg/png/gif/webp)', 400);
}

$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0777, true);
}

$token = bin2hex(random_bytes(6));
$filename = 'cover_u' . $userId . '_' . date('Ymd_His') . '_' . $token . '.' . $ext;
$dest = $uploadsDir . '/' . $filename;

if (!@move_uploaded_file($tmp, $dest)) {
    fb_json_fail('Không thể lưu ảnh', 500);
}

// Save to DB
Database::NonQuery('UPDATE users SET cover = ? WHERE id = ?', [$filename, $userId]);

$coverUrl = '../uploads/' . rawurlencode($filename);

// Realtime: notify other tabs/devices for the same user room
try {
    fb_socket_emit('profile:cover_updated', [
        'user_id' => $userId,
        'cover' => $filename,
        'coverUrl' => $coverUrl,
        'time' => time(),
    ], 'user_' . $userId);
} catch (Throwable $_e) {
    // best effort
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'cover' => $filename,
    'coverUrl' => $coverUrl,
], JSON_UNESCAPED_UNICODE);
exit;
