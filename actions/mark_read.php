<?php

session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!Database::isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401);
    exit(json_encode(['ok' => false]));
}

$current_user = Database::getCurrentUser();
$userId = (int)($current_user['id'] ?? 0);
$friendId = (int)($_POST['friend_id'] ?? 0);
$lastId = (int)($_POST['last_id'] ?? 0);

if ($userId <= 0 || $friendId <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false]));
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

// Validate that last_id (if provided) belongs to this conversation.
if ($lastId > 0) {
    $ok = Database::GetOne(
        "SELECT 1 FROM messages WHERE id = ? AND ((from_user = ? AND to_user = ?) OR (from_user = ? AND to_user = ?)) LIMIT 1",
        [$lastId, $userId, $friendId, $friendId, $userId]
    );
    if (!$ok) {
        http_response_code(400);
        exit(json_encode(['ok' => false]));
    }
} else {
    // If client didn't send last_id, mark up to current max in this conversation.
    $maxId = (int)(Database::GetOne(
        "SELECT MAX(id) FROM messages WHERE (from_user = ? AND to_user = ?) OR (from_user = ? AND to_user = ?)",
        [$userId, $friendId, $friendId, $userId]
    ) ?? 0);
    $lastId = $maxId;
}

Database::NonQuery(
    "INSERT INTO conversation_reads (user_id, peer_id, last_read_message_id) VALUES (?, ?, ?)\n"
    . "ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id))",
    [$userId, $friendId, $lastId]
);

echo json_encode([
    'ok' => true,
    'friend_id' => $friendId,
    'last_read_message_id' => $lastId,
]);
