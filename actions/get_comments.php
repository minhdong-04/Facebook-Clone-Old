<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!Database::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Bạn cần đăng nhập.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit < 1) $limit = 1;
if ($limit > 100) $limit = 100;

if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'post_id không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Detect comment_likes table (graceful fallback)
$has_comment_likes = false;
try {
    $has_comment_likes = !!Database::GetOne("SHOW TABLES LIKE 'comment_likes'");
} catch (Throwable $e) {
    $has_comment_likes = false;
}

if ($has_comment_likes) {
    $items = Database::GetData(
        "SELECT c.id, c.user_id, c.post_id, c.parent_id, c.content, c.created_at,
                u.name AS user_name, u.avatar AS user_avatar,
                COALESCE(clc.cnt, 0) AS like_count,
                CASE WHEN cl_me.user_id IS NULL THEN 0 ELSE 1 END AS liked_by_me
         FROM comments c
         JOIN users u ON u.id = c.user_id
         LEFT JOIN (SELECT comment_id, COUNT(*) AS cnt FROM comment_likes GROUP BY comment_id) clc
                ON clc.comment_id = c.id
         LEFT JOIN comment_likes cl_me
                ON cl_me.comment_id = c.id AND cl_me.user_id = ?
         WHERE c.post_id = ?
         ORDER BY c.id ASC
         LIMIT {$limit}",
        [$user_id, $post_id]
    );
} else {
    $items = Database::GetData(
        "SELECT c.id, c.user_id, c.post_id, c.parent_id, c.content, c.created_at, u.name AS user_name, u.avatar AS user_avatar,
                0 AS like_count, 0 AS liked_by_me
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ?
         ORDER BY c.id ASC
         LIMIT {$limit}",
        [$post_id]
    );
}

echo json_encode([
    'ok' => true,
    'post_id' => $post_id,
    'items' => array_map(function ($row) {
        return [
            'id' => (int)($row['id'] ?? 0),
            'post_id' => (int)($row['post_id'] ?? 0),
            'user_id' => (int)($row['user_id'] ?? 0),
            'parent_id' => (int)($row['parent_id'] ?? 0),
            'user_name' => (string)($row['user_name'] ?? ''),
            'user_avatar' => (string)($row['user_avatar'] ?? ''),
            'content' => (string)($row['content'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'like_count' => (int)($row['like_count'] ?? 0),
            'liked_by_me' => (bool)((int)($row['liked_by_me'] ?? 0)),
        ];
    }, is_array($items) ? $items : [])
], JSON_UNESCAPED_UNICODE);
