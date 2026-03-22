<?php
// actions/like.php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/socket_emit.php';

function fb_db_likes_has_reaction_column(): bool
{
    try {
        $v = Database::GetOne(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND COLUMN_NAME = 'reaction' LIMIT 1"
        );
        return $v !== null;
    } catch (Throwable $e) {
        return false;
    }
}

function fb_normalize_reaction(?string $reaction): string
{
    $reaction = strtolower(trim((string)$reaction));
    // default behaviour when old code calls like.php without reaction
    if ($reaction === '') return 'like';

    // allow common aliases
    if ($reaction === 'care' || $reaction === 'hug') return 'care';

    $allowed = ['like', 'love', 'haha', 'wow', 'sad', 'angry', 'care'];
    return in_array($reaction, $allowed, true) ? $reaction : 'like';
}

function fb_reaction_label(string $reaction): string
{
    switch ($reaction) {
        case 'love': return 'Yêu thích';
        case 'care': return 'Thương thương';
        case 'haha': return 'Haha';
        case 'wow': return 'Wow';
        case 'sad': return 'Buồn';
        case 'angry': return 'Phẫn nộ';
        case 'like':
        default: return 'Thích';
    }
}

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

if (!Database::isLoggedIn()) {
    header("Location: ../pages/home.php");
    exit;
}

$user = Database::getCurrentUser();
$user_id = $user['id'];
$post_id = (int)($_POST['post_id'] ?? ($_GET['post_id'] ?? 0));

// reaction can be supplied by reaction picker; if omitted, keep old behaviour
$requested_reaction = fb_normalize_reaction($_POST['reaction'] ?? ($_GET['reaction'] ?? ''));

$hasReactionCol = fb_db_likes_has_reaction_column();
if (!$hasReactionCol) {
    // Legacy mode: DB chưa có cột reaction -> chỉ hỗ trợ like/unlike.
    $is_liked = Database::GetRow(
        "SELECT 1 FROM likes WHERE user_id = ? AND post_id = ?",
        [$user_id, $post_id]
    );

    if ($is_liked) {
        Database::NonQuery(
            "DELETE FROM likes WHERE user_id = ? AND post_id = ?",
            [$user_id, $post_id]
        );
    } else {
        Database::NonQuery(
            "INSERT INTO likes (user_id, post_id) VALUES (?, ?)",
            [$user_id, $post_id]
        );

        $post = Database::GetRow(
            "SELECT user_id FROM posts WHERE id = ?",
            [$post_id]
        );

        if ($post && $post['user_id'] != $user_id) {
            Database::NonQuery("
                INSERT INTO notifications (user_id, sender_id, type, post_id, message, created_at)
                VALUES (?, ?, 'like', ?, ?, NOW())
            ", [
                $post['user_id'],
                $user_id,
                $post_id,
                $user['name'] . ' đã thích bài viết của bạn'
            ]);
        }
    }

    $like_count = (int)Database::GetOne(
        "SELECT COUNT(*) FROM likes WHERE post_id = ?",
        [$post_id]
    );
    $is_liked_now = (bool)Database::GetRow(
        "SELECT 1 FROM likes WHERE user_id = ? AND post_id = ?",
        [$user_id, $post_id]
    );

    $payload = [
        'post_id' => $post_id,
        'like_count' => $like_count,
        'reaction_counts' => ['like' => $like_count],
        'actor_user_id' => (int)$user_id,
        'actor_reaction' => $is_liked_now ? 'like' : null,
        'actor_liked' => (bool)$is_liked_now,
    ];

    fb_socket_emit('post:reaction', $payload);
    fb_socket_emit('post:like', $payload);

    if (fb_is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'post_id' => $post_id,
            'like_count' => $like_count,
            'is_liked' => (bool)$is_liked_now,
            'reaction' => $is_liked_now ? 'like' : null,
            'reaction_label' => $is_liked_now ? 'Thích' : 'Thích',
            'reaction_counts' => ['like' => $like_count],
            'removed' => (bool)!$is_liked_now,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '../pages/home.php';
    header("Location: $referer");
    exit;
}

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

// === KIỂM TRA ĐÃ REACT CHƯA ===
$existing = Database::GetRow(
    "SELECT reaction FROM likes WHERE user_id = ? AND post_id = ? LIMIT 1",
    [$user_id, $post_id]
);

$did_remove = false;
$final_reaction = null;

if ($existing) {
    $current_reaction = (string)($existing['reaction'] ?? 'like');
    // If user clicks the same reaction again => remove
    if ($current_reaction === $requested_reaction) {
        Database::NonQuery(
            "DELETE FROM likes WHERE user_id = ? AND post_id = ?",
            [$user_id, $post_id]
        );
        $did_remove = true;
        $final_reaction = null;
    } else {
        Database::NonQuery(
            "UPDATE likes SET reaction = ?, created_at = NOW() WHERE user_id = ? AND post_id = ?",
            [$requested_reaction, $user_id, $post_id]
        );
        $final_reaction = $requested_reaction;
    }
} else {
    // Insert new reaction
    Database::NonQuery(
        "INSERT INTO likes (user_id, post_id, reaction) VALUES (?, ?, ?)",
        [$user_id, $post_id, $requested_reaction]
    );
    $final_reaction = $requested_reaction;

    // === LẤY THÔNG TIN BÀI VIẾT ĐỂ GỬI THÔNG BÁO (giữ type=like để tương thích) ===
    $post = Database::GetRow(
        "SELECT user_id FROM posts WHERE id = ?",
        [$post_id]
    );

    if ($post && $post['user_id'] != $user_id) {
        Database::NonQuery("
            INSERT INTO notifications (user_id, sender_id, type, post_id, message, created_at)
            VALUES (?, ?, 'like', ?, ?, NOW())
        ", [
            $post['user_id'],
            $user_id,
            $post_id,
            $user['name'] . ' đã bày tỏ cảm xúc về bài viết của bạn'
        ]);
    }
}

$like_count = (int)Database::GetOne(
    "SELECT COUNT(*) FROM likes WHERE post_id = ?",
    [$post_id]
);

$my_reaction = Database::GetOne(
    "SELECT reaction FROM likes WHERE user_id = ? AND post_id = ? LIMIT 1",
    [$user_id, $post_id]
);

$is_liked_now = $my_reaction !== null;

// summary counts by reaction
$rows = Database::GetData(
    "SELECT reaction, COUNT(*) AS cnt FROM likes WHERE post_id = ? GROUP BY reaction",
    [$post_id]
);
$reaction_counts = [];
if (is_array($rows)) {
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $k = (string)($r['reaction'] ?? '');
        if ($k === '') continue;
        $reaction_counts[$k] = (int)($r['cnt'] ?? 0);
    }
}

// Realtime broadcast to feed room
$payload = [
    'post_id' => $post_id,
    'like_count' => $like_count,
    'reaction_counts' => $reaction_counts,
    'actor_user_id' => (int)$user_id,
    'actor_reaction' => $my_reaction,
    'actor_liked' => (bool)$is_liked_now,
];

// New event name
fb_socket_emit('post:reaction', $payload);
// Backward compatible event name for existing clients
fb_socket_emit('post:like', $payload);

if (fb_is_ajax_request()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'post_id' => $post_id,
        'like_count' => $like_count,
        'is_liked' => (bool)$is_liked_now,
        'reaction' => $my_reaction,
        'reaction_label' => $my_reaction ? fb_reaction_label((string)$my_reaction) : 'Thích',
        'reaction_counts' => $reaction_counts,
        'removed' => (bool)$did_remove,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// === CHUYỂN HƯỚNG VỀ TRANG CHỨA BÀI VIẾT ===
$referer = $_SERVER['HTTP_REFERER'] ?? '../pages/home.php';
header("Location: $referer");
exit;