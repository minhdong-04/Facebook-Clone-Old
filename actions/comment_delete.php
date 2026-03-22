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

$row = Database::GetRow(
    'SELECT id, user_id, post_id FROM comments WHERE id = ?',
    [$comment_id]
);

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Bình luận không tồn tại.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$post_id = (int)($row['post_id'] ?? 0);

if ((int)$row['user_id'] !== (int)($_SESSION['user_id'] ?? 0)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Bạn không có quyền xóa bình luận này.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// collect descendants (best-effort) before delete so clients can remove nested replies
$deleted_ids = [$comment_id];
try {
    $queue = [$comment_id];
    $seen = [$comment_id => true];
    $max = 200;
    while (!empty($queue) && count($deleted_ids) < $max) {
        $current = array_shift($queue);
        $children = Database::GetData('SELECT id FROM comments WHERE parent_id = ?', [$current]);
        if (is_array($children)) {
            foreach ($children as $c) {
                $cid = (int)($c['id'] ?? 0);
                if ($cid > 0 && empty($seen[$cid])) {
                    $seen[$cid] = true;
                    $deleted_ids[] = $cid;
                    $queue[] = $cid;
                    if (count($deleted_ids) >= $max) break;
                }
            }
        }
    }
} catch (Throwable $e) {
    // ignore
}

Database::NonQuery('DELETE FROM comments WHERE id = ?', [$comment_id]);

$comment_count = (int)Database::GetOne('SELECT COUNT(*) FROM comments WHERE post_id = ?', [$post_id]);

// Realtime broadcast
fb_socket_emit('comment:delete', [
    'post_id' => $post_id,
    'comment_id' => $comment_id,
    'deleted_ids' => array_values(array_unique(array_map('intval', $deleted_ids))),
    'comment_count' => $comment_count,
    'actor_user_id' => (int)($_SESSION['user_id'] ?? 0),
]);

echo json_encode([
    'ok' => true,
    'comment_id' => $comment_id,
    'post_id' => $post_id,
    'comment_count' => $comment_count,
], JSON_UNESCAPED_UNICODE);
