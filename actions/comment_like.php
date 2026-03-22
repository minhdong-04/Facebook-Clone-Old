<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/socket_emit.php';

header('Content-Type: application/json; charset=utf-8');

if (!Database::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Bạn cần đăng nhập.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
if ($comment_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'comment_id không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Graceful fallback if table does not exist
try {
    $exists = Database::GetOne("SHOW TABLES LIKE 'comment_likes'");
    if (!$exists) {
        echo json_encode([
            'ok' => false,
            'message' => 'Thiếu bảng comment_likes. Hãy chạy lại database.sql (migration).'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'message' => 'Không thể kiểm tra comment_likes.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = Database::GetRow('SELECT id, post_id FROM comments WHERE id = ?', [$comment_id]);
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Bình luận không tồn tại.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

$liked = false;
try {
    $has = (int)Database::GetOne('SELECT COUNT(*) FROM comment_likes WHERE user_id = ? AND comment_id = ?', [$user_id, $comment_id]);
    if ($has > 0) {
        Database::NonQuery('DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?', [$user_id, $comment_id]);
        $liked = false;
    } else {
        Database::NonQuery('INSERT INTO comment_likes (user_id, comment_id) VALUES (?, ?)', [$user_id, $comment_id]);
        $liked = true;
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Không thể thả thích bình luận.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$like_count = 0;
try {
    $like_count = (int)Database::GetOne('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?', [$comment_id]);
} catch (Throwable $e) {
    $like_count = 0;
}

echo json_encode([
    'ok' => true,
    'comment_id' => $comment_id,
    'post_id' => (int)($row['post_id'] ?? 0),
    'liked' => $liked,
    'like_count' => $like_count,
], JSON_UNESCAPED_UNICODE);

// Realtime sync for the same user across tabs
try {
    fb_socket_emit('comment:like', [
        'comment_id' => $comment_id,
        'post_id' => (int)($row['post_id'] ?? 0),
        'liked' => $liked,
        'like_count' => $like_count,
        'actor_user_id' => $user_id
    ]);
} catch (Throwable $e) {
    // best-effort; do not break response
}
