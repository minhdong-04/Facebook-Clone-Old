<?php
// actions/post_create.php
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
    return !empty($_POST['ajax']);
}

function fb_fail(string $message, int $status = 400): void
{
    if (fb_is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    die($message);
}

function fb_normalize_uploaded_files(array $fileField): array
{
    $out = [];

    if (!isset($fileField['name'])) return $out;

    // single
    if (!is_array($fileField['name'])) {
        if (($fileField['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $out;
        $out[] = $fileField;
        return $out;
    }

    $count = count($fileField['name']);
    for ($i = 0; $i < $count; $i++) {
        $out[] = [
            'name' => $fileField['name'][$i] ?? '',
            'type' => $fileField['type'][$i] ?? '',
            'tmp_name' => $fileField['tmp_name'][$i] ?? '',
            'error' => $fileField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileField['size'][$i] ?? 0,
        ];
    }

    return $out;
}

if (!Database::isLoggedIn()) {
    if (fb_is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header("Location: ../pages/home.php");
    exit;
}

$user = Database::getCurrentUser();
$user_id = $user['id'];
$content = trim($_POST['content'] ?? '');
$image = '';

// === KIỂM TRA NỘI DUNG HOẶC ẢNH ===
$uploaded = [];
if (isset($_FILES['image'])) {
    $uploaded = fb_normalize_uploaded_files($_FILES['image']);
}

$hasAnyUpload = false;
foreach ($uploaded as $f) {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $hasAnyUpload = true;
        break;
    }
}

if ($content === '' && !$hasAnyUpload) {
    if (fb_is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Nội dung trống'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header("Location: ../pages/home.php");
    exit;
}

// === UPLOAD ẢNH (AN TOÀN) - hỗ trợ nhiều ảnh ===
if ($hasAnyUpload) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];
    $maxFiles = 10;
    $maxSize = 5 * 1024 * 1024; // 5MB

    $saved = [];
    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0777, true);
    }

    $seen = 0;
    foreach ($uploaded as $f) {
        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err !== UPLOAD_ERR_OK) {
            fb_fail('Lỗi upload ảnh', 400);
        }

        $seen++;
        if ($seen > $maxFiles) {
            fb_fail('Bạn chỉ được chọn tối đa ' . $maxFiles . ' ảnh', 400);
        }

        $name = (string)($f['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            fb_fail('Chỉ cho phép ảnh: JPG, PNG, GIF, WEBP, HEIC/HEIF', 400);
        }

        $size = (int)($f['size'] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            fb_fail('Ảnh quá lớn (tối đa 5MB/ảnh)', 400);
        }

        try {
            $token = bin2hex(random_bytes(6));
        } catch (Throwable $_e) {
            $token = (string)rand(100000, 999999);
        }
        $filename = 'post_' . time() . '_' . $token . '.' . $ext;
        $targetPath = $uploadsDir . '/' . $filename;

        if (!move_uploaded_file((string)($f['tmp_name'] ?? ''), $targetPath)) {
            fb_fail('Lỗi upload ảnh', 500);
        }
        $saved[] = $filename;
    }

    if (count($saved) === 1) {
        $image = $saved[0];
    } elseif (count($saved) > 1) {
        $encoded = json_encode($saved, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || strlen($encoded) > 255) {
            // rollback files to avoid orphan uploads
            foreach ($saved as $fn) {
                $p = $uploadsDir . '/' . $fn;
                if (is_file($p)) @unlink($p);
            }
            fb_fail('Bạn chọn quá nhiều ảnh, vui lòng chọn ít hơn', 400);
        }
        $image = $encoded;
    }
}

// === TẠO BÀI VIẾT ===
$post_id = Database::NonQuery(
    "INSERT INTO posts (user_id, content, image) VALUES (?, ?, ?)",
    [$user_id, $content, $image]
);

if (!$post_id) {
    fb_fail('Lỗi tạo bài viết', 500);
}

// Load created post for realtime payload
$post_row = Database::GetRow(
    "SELECT p.id, p.user_id, p.content, p.image, p.created_at, u.name AS user_name, u.avatar AS user_avatar\n" .
      "FROM posts p JOIN users u ON u.id = p.user_id\n" .
      "WHERE p.id = ?",
    [$post_id]
);

$payload_post = null;
if ($post_row) {
    $payload_post = [
        'id' => (int)$post_row['id'],
        'user_id' => (int)$post_row['user_id'],
        'user_name' => (string)($post_row['user_name'] ?? ''),
        'user_avatar' => (string)($post_row['user_avatar'] ?? ''),
        'content' => (string)($post_row['content'] ?? ''),
        'image' => (string)($post_row['image'] ?? ''),
        'created_at' => (string)($post_row['created_at'] ?? ''),
        'like_count' => 0,
        'comment_count' => 0,
    ];
}

if ($payload_post) {
    fb_socket_emit('post:create', [
        'post' => $payload_post,
    ]);
}

// === GỬI THÔNG BÁO CHO BẠN BÈ ===
$friends = Database::GetData("
    SELECT friend_id FROM friends 
    WHERE user_id = ? AND status = 'accepted'
    UNION
    SELECT user_id FROM friends 
    WHERE friend_id = ? AND status = 'accepted'
", [$user_id, $user_id]);

foreach ($friends as $friend) {
    $friend_id = $friend['friend_id'] ?? $friend['user_id'];
    Database::NonQuery("
        INSERT INTO notifications (user_id, sender_id, type, post_id, message) 
        VALUES (?, ?, 'post', ?, ?)
    ", [$friend_id, $user_id, $post_id, $user['name'] . ' đã đăng bài mới']);
}

if (fb_is_ajax_request()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'post' => $payload_post,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// === CHUYỂN HƯỚNG ===
header("Location: ../pages/home.php");
exit;