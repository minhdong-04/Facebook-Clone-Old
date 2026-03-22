<?php
require_once __DIR__ . '/../includes/db.php';

if (!Database::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$currentUser = Database::getCurrentUser();
$currentUserId = (int)($currentUser['id'] ?? 0);

$targetId = (int)($_GET['id'] ?? 0);
if ($currentUserId <= 0 || $targetId <= 0 || $targetId === $currentUserId) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../pages/friends.php'));
    exit;
}

// Check if relationship already exists in either direction
$existing = Database::GetRow(
    "SELECT status FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?) LIMIT 1",
    [$currentUserId, $targetId, $targetId, $currentUserId]
);

if ($existing) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../pages/profile.php'));
    exit;
}

// Create a friend request (pending)
Database::NonQuery(
    "INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')",
    [$currentUserId, $targetId]
);

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../pages/profile.php'));
exit;
