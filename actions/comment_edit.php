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
$content = isset($_POST['content']) ? trim((string)$_POST['content']) : '';

if ($comment_id <= 0 || $content === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Dữ liệu không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = Database::GetRow(
    'SELECT id, user_id, post_id FROM comments WHERE id = ?',
    [$comment_id]
);

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Bình luận không tồn tại.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int)$row['user_id'] !== (int)($_SESSION['user_id'] ?? 0)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bạn không có quyền chỉnh sửa bình luận này.'], JSON_UNESCAPED_UNICODE);
    exit;
}

Database::NonQuery(
    'UPDATE comments SET content = ? WHERE id = ?',
    [$content, $comment_id]
);

// Realtime broadcast
fb_socket_emit('comment:edit', [
    'comment_id' => $comment_id,
    'post_id' => (int)($row['post_id'] ?? 0),
    'content' => $content,
    'actor_user_id' => (int)($_SESSION['user_id'] ?? 0),
]);

echo json_encode([
    'ok' => true,
    'comment_id' => $comment_id,
    'post_id' => (int)($row['post_id'] ?? 0),
    'content' => $content,
], JSON_UNESCAPED_UNICODE);
