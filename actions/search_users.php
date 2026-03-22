<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$keyword = trim((string)($_GET['username'] ?? ''));
if (strlen($keyword) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Use Database helper to get PDO
    $pdo = Database::GetPDO();

    // Make search tolerant of spaces: split words and join with % so "minh dong" -> "%minh%dong%"
    $parts = preg_split('/\s+/', $keyword, -1, PREG_SPLIT_NO_EMPTY);
    $pattern = '%' . implode('%', $parts) . '%';

    $sql = "SELECT id, name, avatar FROM users WHERE name LIKE :keyword LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':keyword' => $pattern]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($users);
} catch (Exception $e) {
    http_response_code(500);
    // for debugging you can uncomment the next line in development
    // error_log('search_users error: ' . $e->getMessage());
    echo json_encode([]);
}
