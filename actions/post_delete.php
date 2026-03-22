<?php
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

// Kiểm tra đăng nhập
if (!Database::isLoggedIn()) {
    if (fb_is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Bạn cần đăng nhập để xóa bài viết.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    exit("Bạn cần đăng nhập để xóa bài viết.");
}

// Lấy post_id từ query string, ép kiểu int để an toàn
$post_id = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($post_id <= 0) {
    if (fb_is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header("Location: ../pages/home.php");
    exit;
}

// Lấy thông tin bài viết
$post = Database::GetRow(
    "SELECT user_id, image FROM posts WHERE id = ?",
    [$post_id]
);

// Kiểm tra bài viết có tồn tại và thuộc user hiện tại
$deleted = false;
if ($post && (int)$post['user_id'] === (int)$_SESSION['user_id']) {

    // Xóa file ảnh nếu có
    if (!empty($post['image'])) {
        $raw = (string)$post['image'];
        $rawTrim = trim($raw);
        $files = [];

        if ($rawTrim !== '' && strlen($rawTrim) > 0 && $rawTrim[0] === '[') {
            $decoded = json_decode($rawTrim, true);
            if (is_array($decoded)) {
                foreach ($decoded as $v) {
                    $v = trim((string)$v);
                    if ($v !== '') $files[] = $v;
                }
            }
        } else {
            $files[] = $rawTrim;
        }

        foreach ($files as $fn) {
            $filePath = __DIR__ . '/../uploads/' . $fn;
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }

    // Xóa bài viết khỏi database
    Database::NonQuery("DELETE FROM posts WHERE id = ?", [$post_id]);
    $deleted = true;
}

if ($deleted) {
    fb_socket_emit('post:delete', [
        'post_id' => $post_id,
    ]);
}

if (fb_is_ajax_request()) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$deleted) {
        http_response_code(403);
    }
    echo json_encode([
        'ok' => $deleted,
        'post_id' => $post_id,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Chuyển hướng về trang home
header("Location: ../pages/home.php");
exit;
