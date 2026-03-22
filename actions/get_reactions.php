<?php
// actions/get_reactions.php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!Database::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$post_id = (int)($_GET['post_id'] ?? ($_POST['post_id'] ?? 0));
if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_post_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Detect whether likes.reaction exists
$hasReactionCol = false;
try {
    $v = Database::GetOne(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'reaction' LIMIT 1"
    );
    $hasReactionCol = $v !== null;
} catch (Throwable $e) {
    $hasReactionCol = false;
}

$total = (int)Database::GetOne("SELECT COUNT(*) FROM likes WHERE post_id = ?", [$post_id]);

if ($hasReactionCol) {
    // Summary counts by reaction
    $rows = Database::GetData(
        "SELECT reaction, COUNT(*) AS cnt FROM likes WHERE post_id = ? GROUP BY reaction",
        [$post_id]
    );
    $summary = [];
    if (is_array($rows)) {
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $k = (string)($r['reaction'] ?? '');
            if ($k === '') continue;
            $summary[$k] = (int)($r['cnt'] ?? 0);
        }
    }

    // User list (newest first)
    $items = Database::GetData(
        "SELECT l.user_id, u.name, u.avatar, l.reaction, l.created_at
         FROM likes l
         JOIN users u ON u.id = l.user_id
         WHERE l.post_id = ?
         ORDER BY l.created_at DESC",
        [$post_id]
    );
} else {
    // Backward-compatible: DB chưa có likes.reaction
    $summary = ['like' => $total];
    $items = Database::GetData(
        "SELECT l.user_id, u.name, u.avatar, 'like' AS reaction, l.created_at
         FROM likes l
         JOIN users u ON u.id = l.user_id
         WHERE l.post_id = ?
         ORDER BY l.created_at DESC",
        [$post_id]
    );
}

// Normalize output
$out = [];
if (is_array($items)) {
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $out[] = [
            'user_id' => (int)($it['user_id'] ?? 0),
            'name' => (string)($it['name'] ?? ''),
            'avatar' => (string)($it['avatar'] ?? ''),
            'reaction' => (string)($it['reaction'] ?? 'like'),
            'created_at' => (string)($it['created_at'] ?? ''),
        ];
    }
}

echo json_encode([
    'ok' => true,
    'post_id' => $post_id,
    'total' => $total,
    'summary' => $summary,
    'items' => $out,
], JSON_UNESCAPED_UNICODE);
