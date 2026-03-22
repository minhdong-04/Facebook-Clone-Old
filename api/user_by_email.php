<?php
// Lightweight lookup used by auth/login.php saved-account cards

if (
  isset($_GET['email']) && (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_GET['ajax'])
  )
) {
  header('Content-Type: application/json; charset=utf-8');

  $email = trim((string)($_GET['email'] ?? ''));
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(null);
    exit;
  }

  require_once __DIR__ . '/../includes/db.php';

  $user = Database::GetRow('SELECT id, email, name, avatar FROM users WHERE email = ? LIMIT 1', [$email]);
  if (!$user) {
    echo json_encode(null);
    exit;
  }

  $avatar = trim((string)($user['avatar'] ?? ''));
  $avatarUrl = '/fb/assets/images/default-avatar.png';
  if ($avatar !== '') {
    if (preg_match('~^https?://~i', $avatar)) {
      $avatarUrl = $avatar;
    } elseif ($avatar[0] === '/') {
      $avatarUrl = $avatar;
    } else {
      $uploadsPath = __DIR__ . '/../uploads/' . $avatar;
      if (is_file($uploadsPath)) {
        $avatarUrl = '/fb/uploads/' . rawurlencode($avatar);
      } else {
        $assetsPath = __DIR__ . '/../assets/images/' . $avatar;
        if (is_file($assetsPath)) {
          $avatarUrl = '/fb/assets/images/' . rawurlencode($avatar);
        } else {
          $avatarUrl = '/fb/uploads/' . rawurlencode($avatar);
        }
      }
    }
  }

  echo json_encode([
    'id' => (int)($user['id'] ?? 0),
    'email' => (string)($user['email'] ?? $email),
    'name' => (string)($user['name'] ?? ''),
    'avatar_url' => $avatarUrl,
  ]);
  exit;
}

http_response_code(400);
echo 'Bad Request';
