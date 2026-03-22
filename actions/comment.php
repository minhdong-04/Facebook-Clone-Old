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
        echo json_encode(['ok' => false, 'message' => 'Bạn cần đăng nhập để bình luận.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    exit("Bạn cần đăng nhập để bình luận.");
}

// Lấy dữ liệu từ form
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

// Kiểm tra nội dung và post_id hợp lệ
if ($content && $post_id > 0) {
    $parent_to_use = null;
    if ($parent_id > 0) {
        $parentRow = Database::GetRow(
            'SELECT id, post_id FROM comments WHERE id = ?',
            [$parent_id]
        );
        if ($parentRow && (int)($parentRow['post_id'] ?? 0) === $post_id) {
            $parent_to_use = (int)($parentRow['id'] ?? 0);
        }
    }

    if ($parent_to_use) {
        $comment_id = Database::NonQuery(
            "INSERT INTO comments (user_id, post_id, parent_id, content) VALUES (?, ?, ?, ?)",
            [$_SESSION['user_id'], $post_id, $parent_to_use, $content]
        );
    } else {
        $comment_id = Database::NonQuery(
            "INSERT INTO comments (user_id, post_id, content) VALUES (?, ?, ?)",
            [$_SESSION['user_id'], $post_id, $content]
        );
    }

    $comment_count = (int)Database::GetOne(
        "SELECT COUNT(*) FROM comments WHERE post_id = ?",
        [$post_id]
    );

    $user = Database::getCurrentUser();
    $comment_row = null;
    if ($comment_id) {
        $comment_row = Database::GetRow(
            "SELECT id, user_id, post_id, parent_id, content, created_at FROM comments WHERE id = ?",
            [$comment_id]
        );
    }

    // Realtime broadcast
    fb_socket_emit('post:comment', [
        'post_id' => $post_id,
        'comment_count' => $comment_count,
        'comment' => $comment_row ? [
            'id' => (int)$comment_row['id'],
            'post_id' => (int)$comment_row['post_id'],
            'user_id' => (int)$comment_row['user_id'],
            'parent_id' => (int)($comment_row['parent_id'] ?? 0),
            'user_name' => (string)($user['name'] ?? ''),
            'user_avatar' => (string)($user['avatar'] ?? ''),
            'content' => (string)($comment_row['content'] ?? ''),
            'created_at' => (string)($comment_row['created_at'] ?? ''),
            'like_count' => 0,
            'liked_by_me' => false,
        ] : null,
    ]);

    if (fb_is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'post_id' => $post_id,
            'comment_count' => $comment_count,
            'comment' => $comment_row ? [
                'id' => (int)$comment_row['id'],
                'post_id' => (int)$comment_row['post_id'],
                'user_id' => (int)$comment_row['user_id'],
                'parent_id' => (int)($comment_row['parent_id'] ?? 0),
                'user_name' => (string)($user['name'] ?? ''),
                'user_avatar' => (string)($user['avatar'] ?? ''),
                'content' => (string)($comment_row['content'] ?? ''),
                'created_at' => (string)($comment_row['created_at'] ?? ''),
                'like_count' => 0,
                'liked_by_me' => false,
            ] : null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Chuyển hướng về trang home
header("Location: ../pages/home.php");
exit;
