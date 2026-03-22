<?php

session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!Database::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['conversations' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$current_user = Database::getCurrentUser();
$userId = (int)($current_user['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['conversations' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

// Ensure table exists (safe for existing installs)
Database::NonQuery(
    "CREATE TABLE IF NOT EXISTS conversation_reads (\n"
    . "  user_id INT UNSIGNED NOT NULL,\n"
    . "  peer_id INT UNSIGNED NOT NULL,\n"
    . "  last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,\n"
    . "  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
    . "  PRIMARY KEY (user_id, peer_id),\n"
    . "  INDEX idx_peer (peer_id),\n"
    . "  INDEX idx_user_last (user_id, last_read_message_id)\n"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

function fb_conv_preview(string $content): string
{
    $trim = trim($content);
    if ($trim === '') return '';

    // Some messages are stored as JSON payload for image/audio uploads.
    if ($trim[0] === '{') {
        $decoded = json_decode($trim, true);
        if (is_array($decoded) && isset($decoded['type'])) {
            $type = (string)$decoded['type'];
            if ($type === 'image') return 'Đã gửi 1 ảnh.';
            if ($type === 'audio') return 'Đã gửi 1 đoạn âm thanh.';
        }
    }

    // Plain text
    $s = preg_replace('/\s+/u', ' ', $trim);
    if (mb_strlen($s, 'UTF-8') > 80) {
        $s = mb_substr($s, 0, 80, 'UTF-8') . '…';
    }
    return $s;
}

function fb_media_kind(string $content): ?string
{
    $trim = trim($content);
    if ($trim === '') return null;
    if ($trim[0] !== '{') return null;
    $decoded = json_decode($trim, true);
    if (!is_array($decoded) || !isset($decoded['type'])) return null;
    $type = (string)$decoded['type'];
    if ($type === 'image' || $type === 'audio') return $type;
    return null;
}

function fb_short_name(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') return '';
    $parts = preg_split('/\s+/u', $name);
    if (!$parts || !is_array($parts)) return $name;
    $last = (string)end($parts);
    return $last !== '' ? $last : $name;
}

function fb_avatar_url(?string $avatar): string
{
    $avatar = trim((string)$avatar);
    if ($avatar === '') return '../assets/images/default-avatar.png';
    if (preg_match('~^https?://~i', $avatar)) return $avatar;
    if (strlen($avatar) > 0 && $avatar[0] === '/') return $avatar;

    $uploadsPath = __DIR__ . '/../uploads/' . $avatar;
    if (is_file($uploadsPath)) return '../uploads/' . rawurlencode($avatar);

    $assetsPath = __DIR__ . '/../assets/images/' . $avatar;
    if (is_file($assetsPath)) return '../assets/images/' . rawurlencode($avatar);

    return '../uploads/' . rawurlencode($avatar);
}

// Get last message per partner (direct messages only; uses legacy messages table)
$rows = Database::GetData(
    "
    SELECT
        u.id AS user_id,
        u.name AS name,
        u.avatar AS avatar,
        m.content AS last_content,
        m.from_user AS last_from_user,
        m.to_user AS last_to_user,
        m.created_at AS last_time,
        m.id AS last_id,
        COALESCE(r.last_read_message_id, 0) AS my_last_read_message_id,
        (
            SELECT COUNT(*)
            FROM messages mm
            WHERE mm.from_user = u.id
              AND mm.to_user = ?
              AND mm.id > COALESCE(r.last_read_message_id, 0)
        ) AS unread_count
    FROM (
        SELECT
            IF(from_user = ?, to_user, from_user) AS other_id,
            MAX(id) AS last_id
        FROM messages
        WHERE from_user = ? OR to_user = ?
        GROUP BY IF(from_user = ?, to_user, from_user)
    ) t
    INNER JOIN messages m ON m.id = t.last_id
    INNER JOIN users u ON u.id = t.other_id
    LEFT JOIN conversation_reads r ON r.user_id = ? AND r.peer_id = t.other_id
    ORDER BY t.last_id DESC
    LIMIT 30
    ",
    [$userId, $userId, $userId, $userId, $userId, $userId]
);

$conversations = [];
foreach ($rows as $r) {
    $peerName = (string)($r['name'] ?? '');
    $lastContent = (string)($r['last_content'] ?? '');
    $lastFrom = (int)($r['last_from_user'] ?? 0);

    $preview = '';
    $kind = fb_media_kind($lastContent);
    if ($kind === 'image') {
        $label = ($lastFrom === $userId) ? 'Bạn' : fb_short_name($peerName);
        $preview = $label . ' đã gửi 1 ảnh.';
    } elseif ($kind === 'audio') {
        $label = ($lastFrom === $userId) ? 'Bạn' : fb_short_name($peerName);
        $preview = $label . ' đã gửi 1 đoạn âm thanh.';
    } else {
        $preview = fb_conv_preview($lastContent);
    }

    $conversations[] = [
        'user_id' => (int)($r['user_id'] ?? 0),
        'name' => $peerName,
        'avatar' => fb_avatar_url((string)($r['avatar'] ?? '')),
        'last_preview' => $preview,
        'last_time' => (string)($r['last_time'] ?? ''),
        'last_id' => (int)($r['last_id'] ?? 0),
        'my_last_read_message_id' => (int)($r['my_last_read_message_id'] ?? 0),
        'unread_count' => (int)($r['unread_count'] ?? 0),
    ];
}

echo json_encode(
    [
        'current_user_id' => $userId,
        'conversations' => $conversations,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
