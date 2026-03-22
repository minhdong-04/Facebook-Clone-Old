<?php

session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!Database::isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401);
    exit(json_encode(['ok' => false]));
}

$current_user = Database::getCurrentUser();
if (!$current_user) {
    http_response_code(401);
    exit(json_encode(['ok' => false]));
}

$friend_id = (int)($_POST['friend_id'] ?? 0);
if ($friend_id <= 0) {
    // allow alternate param name
    $friend_id = (int)($_POST['user_id'] ?? 0);
}

if ($friend_id <= 0 || $friend_id === (int)$current_user['id']) {
    http_response_code(400);
    exit(json_encode(['ok' => false]));
}

try {
    $pdo = Database::GetPDO();
    $stmt = $pdo->prepare(
        'DELETE FROM messages
         WHERE (from_user = ? AND to_user = ?)
            OR (from_user = ? AND to_user = ?)' 
    );
    $stmt->execute([(int)$current_user['id'], $friend_id, $friend_id, (int)$current_user['id']]);

    $deleted = (int)$stmt->rowCount();
    echo json_encode(['ok' => true, 'deleted' => $deleted], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
}
