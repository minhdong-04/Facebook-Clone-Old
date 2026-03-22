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
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
if ($limit < 1) $limit = 1;
if ($limit > 100) $limit = 100;

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

function fb_last_seen_text(?string $last_active): string
{
    if (!$last_active) return '';
    $ts = strtotime($last_active);
    if (!$ts) return '';

    $diff = time() - $ts;
    if ($diff < 0) $diff = 0;

    if ($diff < 60) return 'vừa xong';
    if ($diff < 3600) {
        $m = (int)floor($diff / 60);
        if ($m < 1) $m = 1;
        return $m . ' phút';
    }
    if ($diff < 86400) {
        $h = (int)floor($diff / 3600);
        if ($h < 1) $h = 1;
        return $h . ' giờ';
    }
    return '';
}

try {
    $rows = Database::GetData(
        'SELECT id, name, avatar, is_online, last_active
         FROM users
         WHERE id <> ?
         ORDER BY is_online DESC, last_active DESC
         LIMIT ' . (int)$limit,
        [$user_id]
    );

    $now = time();
    $items = [];

    foreach ($rows as $r) {
        $uid = (int)($r['id'] ?? 0);
        if ($uid <= 0) continue;

        $isOnlineFlag = (int)($r['is_online'] ?? 0) === 1;
        $lastActive = (string)($r['last_active'] ?? '');

        $ts = $lastActive ? strtotime($lastActive) : 0;
        $recent = false;
        if ($ts) {
            $diff = $now - $ts;
            if ($diff < 0) $diff = 0;
            // consider "online" only if explicitly online AND has recent activity
            $recent = $diff <= 120;
        }

        $online = $isOnlineFlag && $recent;
        $seenText = $online ? '' : fb_last_seen_text($lastActive);

        $items[] = [
            'id' => $uid,
            'name' => (string)($r['name'] ?? ''),
            'avatar' => fb_avatar_url($r['avatar'] ?? ''),
            'online' => $online,
            'last_seen' => $seenText,
        ];
    }

    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Không thể tải danh sách liên hệ.'], JSON_UNESCAPED_UNICODE);
}
