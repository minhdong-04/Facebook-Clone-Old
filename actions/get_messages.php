<?php

session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!Database::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['messages' => []]);
    exit;
}

$current_user = Database::getCurrentUser();
$friend_id = (int)($_GET['friend_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0);

if ($friend_id <= 0) {
    http_response_code(400);
    echo json_encode(['messages' => []]);
    exit;
}

$messages = Database::GetData("
    SELECT m.*, 
           CASE WHEN m.from_user = ? THEN 1 ELSE 0 END AS is_sent
    FROM messages m
    WHERE (
            (m.from_user = ? AND m.to_user = ?)
         OR (m.from_user = ? AND m.to_user = ?)
          )
      AND m.id > ?
    ORDER BY m.id ASC
", [$current_user['id'], $current_user['id'], $friend_id, $friend_id, $current_user['id'], $last_id]);

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

$me = (int)($current_user['id'] ?? 0);
$myLastRead = (int)(Database::GetOne(
    "SELECT last_read_message_id FROM conversation_reads WHERE user_id = ? AND peer_id = ? LIMIT 1",
    [$me, $friend_id]
) ?? 0);

$peerLastRead = (int)(Database::GetOne(
    "SELECT last_read_message_id FROM conversation_reads WHERE user_id = ? AND peer_id = ? LIMIT 1",
    [$friend_id, $me]
) ?? 0);

echo json_encode([
    'messages' => $messages,
    'my_last_read_message_id' => $myLastRead,
    'peer_last_read_message_id' => $peerLastRead,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);