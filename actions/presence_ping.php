<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!Database::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Bạn cần đăng nhập.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'user_id không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    Database::NonQuery('UPDATE users SET is_online = 1, last_active = NOW() WHERE id = ?', [$user_id]);
} catch (Throwable $e) {
    // best-effort
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
