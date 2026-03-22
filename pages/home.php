<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/webrtc.php';

if (!Database::isLoggedIn()) {
  header("Location: /fb/auth/login.php");
  exit;
}

$currentUser = Database::getCurrentUser();
if (!$currentUser) {
  // phòng trường hợp session lỗi
  session_destroy();
  header("Location: /fb/auth/login.php");
  exit;
}

/* ======================
   USER CHAT TARGET
   ví dụ: index.php?to=2
====================== */
$toUserId = isset($_GET['to']) ? (int)$_GET['to'] : 0;

// WebRTC ICE servers (STUN + optional TURN)
$webrtcIceServers = fb_webrtc_ice_servers((int)($currentUser['id'] ?? 0));

function fb_escape(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

$currentUserName = (string)($currentUser['name'] ?? '');
$currentUserNameSafe = fb_escape($currentUserName);
$currentUserAvatar = fb_avatar_url($currentUser['avatar'] ?? null);
$firstName = '';
if ($currentUserName !== '') {
  $parts = preg_split('/\s+/', trim($currentUserName), -1, PREG_SPLIT_NO_EMPTY);
  if ($parts && isset($parts[0])) $firstName = $parts[0];
}
$firstNameSafe = fb_escape($firstName !== '' ? $firstName : $currentUserName);

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

function fb_post_media_url(?string $value): string
{
  $value = trim((string)$value);
  if ($value === '') return '';

  // If stored as JSON array (multiple images), return the first for legacy callers.
  if (strlen($value) > 0 && $value[0] === '[') {
    $decoded = json_decode($value, true);
    if (is_array($decoded) && !empty($decoded)) {
      $first = trim((string)($decoded[0] ?? ''));
      if ($first !== '') {
        return fb_post_media_url($first);
      }
    }
    return '';
  }

  if (preg_match('~^https?://~i', $value)) return $value;
  if (strlen($value) > 0 && $value[0] === '/') return $value;

  $uploadsPath = __DIR__ . '/../uploads/' . $value;
  if (is_file($uploadsPath)) return '../uploads/' . rawurlencode($value);

  return '../uploads/' . rawurlencode($value);
}

function fb_post_media_urls(?string $value): array
{
  $value = trim((string)$value);
  if ($value === '') return [];

  $items = [];
  if (strlen($value) > 0 && $value[0] === '[') {
    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
      foreach ($decoded as $v) {
        $v = trim((string)$v);
        if ($v !== '') $items[] = $v;
      }
    }
  } else {
    $items[] = $value;
  }

  $out = [];
  foreach ($items as $v) {
    $u = fb_post_media_url($v);
    if ($u !== '') $out[] = $u;
  }
  return $out;
}

function fb_time_ago(?string $datetime): string
{
  if (!$datetime) return '';
  $ts = strtotime($datetime);
  if (!$ts) return '';

  $diff = time() - $ts;
  if ($diff < 0) $diff = 0;

  if ($diff < 60) return 'Vừa xong';
  if ($diff < 3600) return floor($diff / 60) . ' phút';
  if ($diff < 86400) return floor($diff / 3600) . ' giờ';
  if ($diff < 86400 * 7) return floor($diff / 86400) . ' ngày';
  return date('d/m/Y', $ts);
}

function fb_reaction_key(?string $reaction): string
{
  $reaction = strtolower(trim((string)$reaction));
  $allowed = ['like', 'love', 'haha', 'wow', 'sad', 'angry', 'care'];
  return in_array($reaction, $allowed, true) ? $reaction : '';
}

function fb_reaction_label(?string $reaction): string
{
  $k = fb_reaction_key($reaction);
  switch ($k) {
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

function fb_reaction_emoji(?string $reaction): string
{
  $k = fb_reaction_key($reaction);
  switch ($k) {
    case 'love': return '❤️';
    case 'care': return '🥰';
    case 'haha': return '😂';
    case 'wow': return '😮';
    case 'sad': return '😢';
    case 'angry': return '😡';
    case 'like':
    default: return '👍';
  }
}

$hasReactionCol = fb_db_likes_has_reaction_column();

if ($hasReactionCol) {
  $feedPosts = Database::GetData(
    "SELECT\n" .
      "  p.id, p.user_id, p.content, p.image, p.created_at,\n" .
      "  u.name AS user_name, u.avatar AS user_avatar,\n" .
      "  (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS like_count,\n" .
      "  (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,\n" .
      "  EXISTS(SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = ?) AS is_liked,\n" .
      "  (SELECT l3.reaction FROM likes l3 WHERE l3.post_id = p.id AND l3.user_id = ? LIMIT 1) AS my_reaction\n" .
      "FROM posts p\n" .
      "JOIN users u ON u.id = p.user_id\n" .
      "ORDER BY p.created_at DESC, p.id DESC\n" .
      "LIMIT 30",
    [(int)($currentUser['id'] ?? 0), (int)($currentUser['id'] ?? 0)]
  );
} else {
  // Backward-compatible: DB chưa có likes.reaction
  $feedPosts = Database::GetData(
    "SELECT\n" .
      "  p.id, p.user_id, p.content, p.image, p.created_at,\n" .
      "  u.name AS user_name, u.avatar AS user_avatar,\n" .
      "  (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS like_count,\n" .
      "  (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,\n" .
      "  EXISTS(SELECT 1 FROM likes l2 WHERE l2.post_id = p.id AND l2.user_id = ?) AS is_liked,\n" .
      "  '' AS my_reaction\n" .
      "FROM posts p\n" .
      "JOIN users u ON u.id = p.user_id\n" .
      "ORDER BY p.created_at DESC, p.id DESC\n" .
      "LIMIT 30",
    [(int)($currentUser['id'] ?? 0)]
  );
}
?>


<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Facebook</title>
  <link rel="icon" href="../uploads/fb_logo.jpg">
</head>
<style>
  /* ================= CƠ BẢN ================= */
  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }


  :root {
    /* Theme-able base palette (light by default) */
    --fb-bg: #f0f2f5;
    --fb-panel: #ffffff;
    --fb-hover: #f2f2f2;
    --fb-border: rgba(0, 0, 0, .08);
    --fb-text: #050505;
    --fb-text-muted: #65676B;
    --fb-card: var(--fb-panel);

    /* Light mode mapping */
    --app-page-bg: #f0f2f5;
    --app-surface-bg: #ffffff;
    --app-text: #050505;
    --app-muted: #65676B;
    --app-hover: #f2f2f2;
    --app-icon-bg: #e4e6eb;
    --app-input-bg: #f0f2f5;
    --app-border: rgba(0, 0, 0, .08);
    --app-card-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
    --app-sprite-filter: var(--app-sprite-filter, none);
    --app-active-pill-bg: #ffffff;
    /* custom hover targets */
    --icon-hover-bg: #f0f2f5;
    --profile-hover-bg: #e4e6eb;

    /* Search (header + messenger) — theme-aware */
    --search-bg: #f0f2f5;
    --search-text: #050505;
    --search-placeholder: #606770;
    --search-border: rgba(0, 0, 0, 0.06);
    /* See-all button (account popover) */
    --seeall-bg: #f0f2f5;
    --seeall-text: #050505;
    --seeall-hover: #e4e6eb;
  }


  body[data-theme="dark"] {
    --fb-bg: #18191A;
    --fb-panel: #242526;
    --fb-hover: #3A3B3C;
    --fb-border: rgba(255, 255, 255, .08);
    --fb-text: #E4E6EB;
    --fb-text-muted: #B0B3B8;

    /* card surface variable (used by modals/cards) */
    --fb-card: var(--fb-panel);

    --app-page-bg: var(--fb-bg);
    --app-surface-bg: var(--fb-panel);
    --app-text: var(--fb-text);
    --app-muted: var(--fb-text-muted);
    --app-hover: var(--fb-hover);
    --app-icon-bg: var(--fb-hover);
    --app-input-bg: var(--fb-hover);
    --app-border: var(--fb-border);
    --app-card-shadow: 0 12px 28px rgba(0, 0, 0, 0.6);
    --app-sprite-filter: invert(1);
    --app-active-pill-bg: var(--fb-panel);

    /* Dark-mode overrides for icon hover and search */
    --icon-hover-bg: #3a3b3c;

    /* Dark theme overrides for search */
    --search-bg: #3a3b3c;
    --search-text: var(--app-text);
    --search-placeholder: var(--app-muted);
    --search-border: rgba(255, 255, 255, 0.06);

    /* See-all dark overrides */
    --seeall-bg: #393b3d;
    --seeall-text: #e4e6eb;
    --seeall-hover: #4a4b4d;
  }


  @media (prefers-color-scheme: dark) {
    body[data-theme="auto"] {
      --fb-bg: #18191A;
      --fb-panel: #242526;
      --fb-hover: #3A3B3C;
      --fb-border: rgba(255, 255, 255, .08);
      --fb-text: #E4E6EB;
      --fb-text-muted: #B0B3B8;

      --app-page-bg: var(--fb-bg);
      --app-surface-bg: var(--fb-panel);
      --app-text: var(--fb-text);
      --app-muted: var(--fb-text-muted);
      --app-hover: var(--fb-hover);
      --app-icon-bg: var(--fb-hover);
      --app-input-bg: var(--fb-hover);
      --app-border: var(--fb-border);
      --app-card-shadow: 0 12px 28px rgba(0, 0, 0, 0.6);
      --app-sprite-filter: invert(1);
      --app-active-pill-bg: var(--fb-panel);
      --fb-card: var(--fb-panel);
    }
  }

  body {
    font-family: system-ui, -apple-system, sans-serif;
    background-color: var(--app-page-bg);
    height: 100vh;
    color: var(--app-text);
  }

  /* Header chính (cố định ở trên) */
  .header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 56px;
    background: var(--app-surface-bg);
    display: flex;
    align-items: center;
    padding: 0 16px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .1);
    z-index: 100;
  }

  /* left area: logo + search */
  .header-left {
    display: flex;
    align-items: center;
    width: 360px;
    gap: 12px;
    /* space between logo and search */
  }

  .account-popover .acct-menu .profile-info .profile-info-inner:hover {
    background: var(--hover-bg);
  }

  .account-popover .acct-menu .profile-info .profile-info-inner:focus-visible {
    outline: 2px solid rgba(24, 119, 242, .45);
    outline-offset: 2px;
  }

  .account-popover .acct-menu .profile-info::after {
    content: "";
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    bottom: 6px;
    width: calc(100% - 24px);
    height: 2px;
    background: #6a6a6a;
    border-radius: 1px;
    pointer-events: none;
    opacity: 0.22;
  }

  .account-popover .acct-menu .avatar-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #ced0d4;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 6px;
    overflow: hidden;
    flex: 0 0 auto;
  }

  .account-popover .acct-menu .avatar-circle>svg {
    display: block;
    width: 100%;
    height: 100%;
  }

  .account-popover .acct-menu .acct-menu-avatar-ring {
    fill: none;
    stroke: rgba(0, 0, 0, .08);
    stroke-width: 1;
  }

  .account-popover .acct-menu .user-name {
    font-size: calc(var(--font-user-name) - 3px);
    font-weight: 600;
    color: var(--text);
    line-height: 1.15;
  }

  .account-popover .acct-menu .see-all-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 5px 16px 8px 16px;
    padding: 10px 12px;
    background: var(--seeall-bg);
    border-radius: 6px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
    font-weight: 600;
    color: var(--seeall-text);
    font-size: calc(var(--font-body) + 3px);
    line-height: 1.2;
    cursor: pointer;
    user-select: none;
    transition: background 0.12s, transform 0.06s;
  }


  .account-popover .acct-menu .see-all-btn:hover,
  .see-all-btn:hover {
    background: var(--seeall-hover) !important;
    border-radius: 8px !important;
    transition: background 0.2s;
  }

  .account-popover .acct-menu .see-all-btn:active {
    transform: translateY(1px);
  }

  .account-popover .acct-menu .see-all-btn:focus-visible {
    outline: 2px solid rgba(24, 119, 242, .45);
    outline-offset: 2px;
  }

  .account-popover .acct-menu .see-all-btn .see-all-ico {
    width: 16px;
    height: 16px;
    flex: 0 0 auto;
    display: inline-block;
    background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yr/r/2qgoWm4SGrp.png");
    background-position: 0px -449px;
    background-size: auto;
    background-repeat: no-repeat;
    filter: var(--sprite-filter);
  }

  .account-popover .acct-menu .see-all-btn .see-all-text {
    min-width: 0;
    max-width: 100%;
  }

  /* MAIN MENU */
  .account-popover .acct-menu .menu-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .account-popover .acct-menu .menu-item {
    display: flex;
    align-items: center !important;
    padding: 6px;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.12s;
    margin-bottom: 0;
    text-decoration: none;
    color: inherit;
    min-height: var(--item-height);
  }


  .account-popover .acct-menu .menu-item:hover,
  .account-popover .acct-menu .sub-menu-item:hover {
    background: var(--hover-bg);
    border-radius: 16px;
    transition: background 0.2s;
  }

  .account-popover .acct-menu .icon-wrapper {
    width: 30px;
    height: 30px;
    background: var(--icon-bg);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 8px 0 4px;
    flex-shrink: 0;
  }

  .account-popover .acct-menu .icon-wrapper svg {
    width: 18px;
    height: 18px;
    fill: var(--text);
    display: block;
  }

  .account-popover .acct-menu .item-label-wrapper {
    display: flex;
    flex-direction: row;
    align-items: center;
    flex: 1;
    min-width: 0;
    gap: 8px;
  }

  .account-popover .acct-menu .item-label {
    font-size: var(--font-item-label);
    font-weight: 600;
    color: var(--text);
    line-height: 1.25;
    padding: 1px 0 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .account-popover .acct-menu .item-ctrl {
    font-size: 12px;
    color: var(--secondary-text);
    margin-top: 4px;
    letter-spacing: 0.4px;
    font-weight: 500;
    display: none;
  }

  .account-popover .acct-menu .chev-right {
    width: 20px;
    height: 20px;
    fill: var(--secondary-text);
    margin-left: 8px;
    flex-shrink: 0;
  }

  .account-popover .acct-menu .menu-item.has-ctrl {
    min-height: calc(var(--item-height) + 10px);
  }

  .account-popover .acct-menu .menu-item.has-ctrl .item-label-wrapper {
    flex-direction: column;
    align-items: flex-start;
    justify-content: center !important;
    padding-top: 0;
    gap: 1px;
  }

  .account-popover .acct-menu .menu-item.has-ctrl .item-ctrl {
    display: block;
    margin-top: 0 !important;
    line-height: 1.1;
    font-size: 11px;
  }

  /* facebook sprite icons */
  .account-popover .acct-menu .fb-sprite {
    background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yk/r/vSXg3cmJhul.png?_nc_eui2=AeExqvLJ-7kaWP5ShPuvq48wjoQEpfhWVnWOhASl-FZWdWFozxKPYbXxXSa_u3UZNSizR4YfsbVAETH8fBcYqjYJ");
    background-repeat: no-repeat;
    background-size: auto;
    width: 20px;
    height: 20px;
    background-position: 0px -285px;
    display: inline-block;
    filter: var(--sprite-filter);
  }

  .account-popover .acct-menu .fb-sprite-privacy-check {
    background-position: 0px -390px;
  }

  /* footer */
  .account-popover .acct-menu .footer-links {
    margin-top: 4px;
    padding: 2px 8px 0;
    font-size: 12px;
    color: var(--secondary-text);
    line-height: 1.35;
    display: block;
    white-space: normal;
  }

  .account-popover .acct-menu .footer-links span {
    cursor: pointer;
    display: inline;
  }

  .account-popover .acct-menu .footer-links span:hover {
    text-decoration: underline;
  }

  .account-popover .acct-menu .footer-links .dot {
    cursor: default;
    margin: 0 4px;
  }

  .account-popover .acct-menu .footer-links .dot:hover {
    text-decoration: none;
  }

  .account-popover .acct-menu .footer-links .footer-more:hover {
    text-decoration: none;
  }

  /* Secondary panel */
  .account-popover .acct-menu .sec-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    padding: 6px 0 12px;
    position: sticky;
    top: 0;
    background: var(--panel-bg);
    z-index: 3;
  }

  /* IMPORTANT: override the header search `.back-btn` */
  .account-popover .acct-menu .back-btn {
    position: static;
    left: auto;
    margin-top: 0;
    opacity: 1;
    pointer-events: auto;
    z-index: auto;
    width: 36px;
    height: 36px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: none;
    background: transparent;
    box-sizing: border-box;
    outline: none;
    box-shadow: none;
    -webkit-tap-highlight-color: transparent;
    transition: none;
  }

  .account-popover .acct-menu .back-btn:focus,
  .account-popover .acct-menu .back-btn:focus-visible,
  .account-popover .acct-menu .back-btn:active {
    outline: none;
    box-shadow: none;
  }

  .account-popover .acct-menu .back-btn svg {
    width: 20px;
    height: 20px;
    fill: var(--text);
  }

  .account-popover .acct-menu .sec-title {
    font-size: var(--font-sec-title);
    font-weight: 800;
    letter-spacing: -0.2px;
    color: var(--text);
    line-height: 1.05;
  }

  .account-popover .acct-menu .sec-view {
    display: none;
  }

  .account-popover .acct-menu .sec-view.active {
    display: block;
  }

  .account-popover .acct-menu .sub-menu-list {
    display: flex;
    flex-direction: column;
    padding-top: 4px;
    gap: 4px;
  }

  .account-popover .acct-menu .sub-menu-item {
    display: flex;
    align-items: center;
    padding: 6px;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    min-height: var(--item-height);
  }

  .account-popover .acct-menu .sub-menu-item:hover {
    background: var(--hover-bg);
  }

  .account-popover .acct-menu .sub-menu-item .icon-wrapper {
    margin-right: 8px;
  }

  .account-popover .acct-menu .sub-menu-item .item-label {
    font-size: var(--font-submenu-label);
    font-weight: 700;
    line-height: 1.3;
    padding: 1px 0;
  }

  .account-popover .acct-menu .sub-menu-item .chev-right {
    margin-left: auto;
  }

  /* Accessibility view */
  .account-popover .acct-menu .setting-block {
    display: flex;
    align-items: flex-start !important;
    margin-bottom: 10px;
  }

  .account-popover .acct-menu .setting-icon {
    width: 32px;
    height: 32px;
    background: var(--icon-bg);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    margin-top: 2px;
    flex-shrink: 0;
  }

  .account-popover .acct-menu .setting-icon svg {
    width: 18px;
    height: 18px;
    fill: var(--text);
    display: block;
  }

  .account-popover .acct-menu .setting-content {
    flex: 1;
    display: flex;
    flex-direction: column;
  }

  .account-popover .acct-menu .setting-name {
    font-size: var(--font-setting-name);
    font-weight: 600;
    color: var(--text);
    margin-bottom: 2px;
    margin-top: 0;
  }

  .account-popover .acct-menu .setting-desc {
    font-size: var(--font-setting-desc);
    color: var(--secondary-text);
    margin-bottom: 8px;
    line-height: 1.25;
    display: -webkit-box;
    line-clamp: 2;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  /* Chỉ riêng view “Màn hình và trợ năng”: hiện đầy đủ mô tả */
  .account-popover .acct-menu .sec-view[data-view="accessibility"] .setting-desc {
    display: block;
    overflow: visible;
    line-clamp: unset;
    -webkit-line-clamp: unset;
    -webkit-box-orient: unset;
  }

  .account-popover .acct-menu .sec-view[data-view="accessibility"] .setting-name {
    font-size: 18px;
  }

  .account-popover .acct-menu .sec-view[data-view="accessibility"] .setting-desc {
    font-size: 18px;
  }

  .account-popover .acct-menu .sec-view[data-view="accessibility"] .radio-label {
    font-size: 18px;
  }

  .account-popover .acct-menu .radio-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 8px;
    border-radius: 8px;
    cursor: pointer;
  }

  .account-popover .acct-menu .radio-row:hover {
    background-color: var(--hover-bg);
  }

  .account-popover .acct-menu .radio-label {
    font-size: var(--font-radio-label);
    font-weight: 600;
    color: var(--text);
  }

  .account-popover .acct-menu .radio-circle {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid var(--secondary-text);
    position: relative;
    background: transparent;
    box-sizing: border-box;
    transition: border-color .14s ease, transform .12s ease;
  }

  .account-popover .acct-menu .radio-row.selected .radio-circle {
    border-color: var(--text);
  }

  .account-popover .acct-menu .radio-row:active .radio-circle {
    transform: scale(0.96);
  }

  .account-popover .acct-menu .radio-circle::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--text);
    transform: translate(-50%, -50%) scale(0.6);
    opacity: 0;
    transition: opacity .14s ease, transform .14s ease;
  }

  .account-popover .acct-menu .radio-row.selected .radio-circle::after {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
  }

  .account-popover .acct-menu .simple-item {
    display: flex;
    align-items: center;
    padding: 10px 8px;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 2px;
  }

  .account-popover .acct-menu .simple-item:hover {
    background-color: var(--hover-bg);
  }

  .account-popover .acct-menu .simple-text {
    flex: 1;
    font-size: var(--font-simple-text);
    font-weight: 600;
    margin-left: 0;
    color: var(--text);
  }

  /* scrollbars */
  .account-popover .acct-menu .menu-panel::-webkit-scrollbar {
    width: 10px;
  }

  .account-popover .acct-menu .menu-panel::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.08);
    border-radius: 10px;
  }

  .account-popover .acct-menu .menu-panel::-webkit-scrollbar-track {
    background: transparent;
  }

  .back-btn {
    position: absolute;
    left: 5px;
    width: 40px;
    height: 40px;
    margin-top: -40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--app-muted);
    opacity: 0;
    pointer-events: none;
    transition: .2s;
    z-index: 10;
    background: transparent;
    border: none;
  }

  /* Nếu muốn "kéo dài" mũi tên ngang: ví dụ .header-left.is-searching .back-btn svg -> transform: scaleX(1.35) */
  .back-btn svg {
    transition: transform .22s ease;
    transform-origin: 50% 50%;
    transform: scaleX(1);
  }

  .back-btn svg,
  .back-btn svg * {
    fill: currentColor;
  }

  .back-btn:hover {
    background: var(--hover-bg);
  }

  /* ================= SEARCH BOX ================= */
  .search-wrapper {
    height: 40px;
    width: 240px;
    /* match screenshot width */
    background: var(--search-bg);
    border-radius: 20px;
    position: relative;
    display: flex;
    align-items: center;
    transition: width .2s, background .2s;
    box-shadow: none;
    border: 1px solid var(--search-border);
    padding-right: 8px;
  }

  .search-wrapper::before {
    content: "";
    position: absolute;
    top: -8px;
    /* extend further left so the focus-card reaches the header's left edge */
    left: -220px;
    width: calc(100% + 232px);
    height: var(--search-result-height, 130px);

    background: var(--app-surface-bg);
    border-radius: 12px;
    box-shadow: var(--app-card-shadow);

    opacity: 0;
    pointer-events: none;
    transition: opacity .15s ease;
    z-index: 0;
  }

  /* khi focus (giữ logic cũ) */
  .search-wrapper.focused::before {
    opacity: 1;
  }

  /* giữ mọi thứ nổi trên card */
  .search-wrapper>* {
    position: relative;
    z-index: 2;
  }


  /* ================= SEARCH RESULT ================= */
  #searchResult {
    /* position the empty-state panel absolutely under the pill */
    position: absolute;
    top: calc(100% + 8px);
    /* nudge left to align dropdown more under the back-arrow */
    left: calc(50% - 30px);
    transform: translateX(-50%);
    width: calc(100% + 56px);
    padding: 0;
    z-index: 2;
    pointer-events: none;
    min-height: 0;
    /* allow script to set explicit height */
    max-height: 360px;
    /* clamp to reasonable max and allow scrolling */
    overflow-y: auto;
    /* enable scroll when content exceeds max */
    -webkit-overflow-scrolling: touch;
    background: transparent;
  }

  #searchResult .empty {
    padding: 10px 12px;
    font-size: 15px;
    color: #b0b3b8;
    background: transparent;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-4px);
    transition: opacity .12s ease, transform .12s ease;
    pointer-events: auto;
  }

  /* show empty message only when the search pill is focused */
  .search-wrapper.focused #searchResult .empty {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }

  /* hide when there's input text */
  .search-wrapper.has-text #searchResult .empty {
    opacity: 0;
    visibility: hidden;
  }

  /* enable interactions when search is open or has text */
  .search-wrapper.focused #searchResult,
  .search-wrapper.has-text #searchResult {
    pointer-events: auto;
  }

  /* stronger hover for result rows (high specificity to override other rules) */
  .search-wrapper.focused #searchResult .user-item:hover,
  .search-wrapper.has-text #searchResult .user-item:hover {
    background: rgba(255, 255, 255, 0.04);
    color: #ffffff;
    transition: background .12s ease, color .12s ease;
  }

  /* make sure avatars / labels stay visible on hover */
  .search-wrapper.focused #searchResult .user-item:hover img,
  .search-wrapper.has-text #searchResult .user-item:hover img {
    filter: none;
  }

  /* extend hover area to the left using a background pseudo-element */
  .search-wrapper.focused #searchResult .user-item,
  .search-wrapper.has-text #searchResult .user-item {
    position: relative;
    z-index: 1;
    border-radius: 10px;
    /* allow full-row rounding on hover */
    overflow: hidden;
    /* clip pseudo-element and children */
  }

  .search-wrapper.focused #searchResult .user-item::before,
  .search-wrapper.has-text #searchResult .user-item::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    /* extended to the card left edge */
    right: 0;
    background: transparent;
    border-radius: inherit;
    /* match row rounding */
    transition: background .12s ease, opacity .12s ease;
    z-index: 0;
    opacity: 0;
  }

  .search-wrapper.focused #searchResult .user-item:hover::before,
  .search-wrapper.has-text #searchResult .user-item:hover::before {
    background: rgba(255, 255, 255, 0.04);
    opacity: 1;
  }

  /* keep content above the pseudo-element */
  .search-wrapper.focused #searchResult .user-item>*,
  .search-wrapper.has-text #searchResult .user-item>* {
    position: relative;
    z-index: 1;
  }

  /* kính lúp bên trái */
  .search-icon {
    position: absolute;
    left: 12px;
    color: var(--search-placeholder);
    transition: opacity .1s, transform .2s;
    z-index: 1;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .search-icon svg {
    width: 22px;
    height: 22px;
    margin-top: 5px;
  }

  /*
          FAKE PLACEHOLDER
          - Đây là phần hiển thị chữ khi user "soạn" (để có hiệu ứng trượt từ phải -> trái).
          - Muốn thay đổi độ mượt/nhanh: chỉnh `transition: transform ...` ở đây.
        */
  .fake-placeholder {
    position: absolute;
    left: 200px;
    /* a bit further right so text centers like screenshot */
    font-size: 15px;
    color: var(--search-placeholder);
    pointer-events: none;
    transition: transform .42s cubic-bezier(.08, .52, .52, 1), opacity .18s ease;
    z-index: 2;
    white-space: nowrap;
    user-select: none;
  }

  /* place back arrow to the left OUTSIDE the pill; hidden by default */
  .search-wrapper .back-btn {
    position: absolute;
    left: -48px;
    right: auto;
    top: calc(50% + 40px);
    transform: translateY(-50%) translateX(-10px);
    color: var(--search-text);
    z-index: 99999;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    background: transparent;
    border: none;
    border-radius: 0;
    width: auto;
    height: auto;
    transition: transform .18s ease, opacity .12s ease;
    opacity: 0;
    pointer-events: none;
    box-shadow: none;
  }

  .search-wrapper .back-btn svg {
    width: 18px;
    height: 18px;
    fill: var(--search-text) !important;
    color: var(--search-text) !important;
  }

  /* restore default magnifier/input offsets; back arrow slides in on focus */
  .search-icon {
    left: 12px;
  }

  .search-input {
    padding-left: 56px;
  }

  .fake-placeholder {
    left: 38px;
  }

  /* show and slide the back arrow when search is active */
  .search-wrapper.focused .back-btn,
  .is-searching .search-wrapper .back-btn {
    /* show and keep positioned to the left of the pill when active */
    opacity: 1;
    pointer-events: auto;
    left: -48px;
    top: calc(50% + 40px);
    transform: translateY(-50%) translateX(0);
    color: var(--search-text);
    z-index: 120;
  }

  /* remove temporary forced-in-pill placement: keep left-outside placement */


  /* default: slightly off to the left and hidden */
  .search-wrapper .back-btn {
    transform: translateY(-50%) translateX(-10px);
  }

  /* input thật (luôn chứa value) */
  .search-input {
    width: 100%;
    height: 100%;
    border: none;
    background: transparent;
    outline: none;
    font-size: 15px;
    color: var(--search-text);
    caret-color: var(--search-text);
    padding-left: 56px;
    /* leave room for icon and some gap */
    padding-right: 16px;
    z-index: 4;
    transition: padding-left .28s cubic-bezier(.08, .52, .52, 1);
  }

  .search-input::placeholder {
    opacity: 0;
  }



  /* ẩn placeholder native */

  /* Khi đang mở chế độ tìm kiếm: ẩn logo, show back-btn */
  .is-searching .fb-logo {
    opacity: 0;
    transform: translateX(-20px);
    pointer-events: none;
  }

  .is-searching .back-btn {
    opacity: 1;
    pointer-events: auto;
  }

  /* Khi wrapper focus: ẩn icon kính lúp và biến fake-placeholder thành khung input giả */
  .search-wrapper.focused .search-icon {
    opacity: 0;
    transform: translateX(14px);
  }

  .search-wrapper.focused .fake-placeholder {
    /* expand the fake placeholder to match the full input area */
    left: 0;
    right: 0;
    height: 100%;
    padding-left: 12px;
    /* reduced so text sits nearer the left when focused */
    padding-right: 16px;
    background: var(--search-bg);
    color: var(--search-text) !important;
    border-radius: 20px;
    /* match .search-wrapper */
    border: 1px solid var(--search-border);
    box-shadow: none;
    z-index: 3;
    display: flex;
    align-items: center;
    font-size: 15px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transform: translateX(0);
    opacity: 1;
    visibility: visible;
    transition: transform .28s cubic-bezier(.08, .52, .52, 1), opacity .18s ease;
  }

  /* Nếu đã nhập chữ -> ẩn CHỮ trong fake-placeholder nhưng giữ khung (border/background) */
  .search-wrapper.has-text .fake-placeholder {
    /* keep the frame visible so input still shows as a framed field */
    opacity: 1;
    visibility: visible;
    /* hide only the text content */
    color: transparent !important;
    pointer-events: none;
  }



  /* ================= CENTER NAV (icons) ================= */
  .center-nav-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    z-index: 1001;
  }

  .center-nav {
    display: flex;
    gap: 10px;
    align-items: center;
    height: 56px;
    padding: 0 8px;
    transform: translateX(-30px);
  }

  /* ====== FB-like wider pill nav item ====== */
  /* vùng click ngoài giữ kích thước vừa phải, nhưng cho phép .icon-bg mở rộng */
  .cnav-item {
    display: flex;
    align-items: center;
    justify-content: center;
    width: auto;
    /* cho phép .icon-bg quyết định chiều ngang (not fixed tiny) */
    min-width: 64px;
    /* vùng click tối thiểu */
    height: 56px;
    padding: 0 6px;
    /* chút padding để pill không dính nhau */
    border-radius: 12px;
    position: relative;
    cursor: pointer;
    color: var(--app-muted);
    user-select: none;
    outline: none;
    transition: background .18s ease, transform .12s ease;
  }

  /* pill chứa icon: rộng, bo góc, căn giữa icon - giống ảnh mẫu */
  .cnav-item .icon-bg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    /* CHỈNH: thay đổi để pill rộng (ví dụ 100px) */
    height: 48px;
    /* chiều cao pill như ảnh */
    padding: 0 14px;
    /* khoảng cách bên trái/phải để icon nằm giữa pill */
    border-radius: 10px;
    /* bo góc pill */
    background: transparent;
    /* mặc định trong suốt */
    transition: background .18s ease, transform .12s ease, box-shadow .18s ease;
    flex-shrink: 0;
    box-sizing: border-box;
  }

  /* icon bên trong giữ kích thước */
  .cnav-item svg {
    width: 23px;
    height: 23px;
    display: block;
    color: inherit;
    fill: currentColor;
  }

  /* hover: đổi nền pill nhẹ (như FB) */

  .cnav-item:not(.active):hover .icon-bg,
  .cnav-item:not(.active):focus-visible .icon-bg {
    background: var(--icon-hover-bg);
    border-radius: 8px;
    transition: background 0.2s;
  }

  .cnav-item.active .icon-bg {
    box-shadow: 0 6px 14px rgba(8, 102, 255, 0.06);
    transform: translateY(0);
  }


  /* ACTIVE → nền trắng, icon xanh */
  .cnav-item.active {
    color: var(--fb-blue);
  }

  /* ICON-BG của tab đang active */
  .cnav-item.active .icon-bg {
    background: var(--app-active-pill-bg) !important;
    border-radius: 8px !important;
    /* keep rounded corners for active pill */
    border: none !important;
    transform: none !important;
    box-shadow: none !important;
  }

  /* Khi hover tab active → KHÔNG thay đổi màu, KHÔNG bo góc */
  .cnav-item.active:hover .icon-bg,
  .cnav-item.active:focus-within .icon-bg {
    background: var(--app-active-pill-bg) !important;
    border-radius: 8px !important;
    /* keep rounded corners when active + hover/focus */
    border: none !important;
    transform: none !important;
  }



  /* underline (nếu cần) giữ nguyên vị trí dưới cùng */
  .cnav-item .underline {
    position: absolute;
    bottom: 0px;
    left: 12px;
    right: 12px;
    height: 3px;
    border-radius: 3px;
    transform-origin: center;
    transform: scaleX(0);
    background: transparent;
    transition: transform .36s cubic-bezier(.08, .52, .52, 1), background .28s ease;
  }

  .cnav-item.active .underline {
    transform: scaleX(1);
    background: var(--fb-blue);
  }

  /* responsive: giảm kích thước pill trên màn bé */
  @media (max-width: 900px) {
    .cnav-item {
      min-width: 54px;
      height: 48px;
      padding: 0 4px;
    }

    .cnav-item .icon-bg {
      min-width: 72px;
      height: 34px;
      padding: 0 10px;
      border-radius: 8px;
    }

    .cnav-item svg {
      width: 18px;
      height: 18px;
    }
  }


  .tooltip {
    position: absolute;
    top: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%) translateY(6px);
    background: #111;
    color: #fff;
    padding: 8px 10px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1;
    opacity: 0;
    pointer-events: none;
    white-space: nowrap;
    transition: opacity .22s cubic-bezier(.2, .9, .3, 1), transform .28s cubic-bezier(.2, .9, .3, 1);
    z-index: 4000;
    box-shadow: 0 8px 20px rgba(0, 0, 0, .16);
    will-change: transform, opacity;
  }

  .tooltip.tooltip-visible {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
    pointer-events: auto;
  }


  @media (max-width:900px) {
    .center-nav {
      gap: 8px;
    }

    .cnav-item {
      width: 48px;
      height: 48px;
    }

    .cnav-item .underline {
      left: 10px;
      right: 10px;
      bottom: 6px;
      height: 2px;
    }
  }

  .header-right {
    width: 300px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }

  .header-right .icon-btn.is-active {
    background: #393b3d;
    color: var(--fb-blue);
  }

  .header-right .icon-btn.is-active:hover {
    background: var(--app-hover);
  }

  .header-right .icon-btn {
    width: 40px;
    height: 40px;
    border-radius: 999px;
    border: none;
    background: var(--app-icon-bg);
    color: var(--app-text);
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    user-select: none;
    transition: background .12s ease, transform .12s ease;
  }

  .header-right .icon-btn:hover {
    background: var(--icon-hover-bg);
  }

  .header-right .icon-btn:active {
    transform: scale(0.98);
  }

  .header-right .icon-btn:focus {
    outline: none;
  }

  .header-right .icon-btn:focus-visible {
    outline: 2px solid rgba(8, 102, 255, .55);
    outline-offset: 2px;
  }

  .header-right .icon-btn svg {
    width: 20px;
    height: 20px;
    display: block;
    fill: currentColor;
  }

  /* ================= Account (profile) icon ================= */
  .header-right .icon-btn.account-btn {
    background: transparent;
  }

  .header-right .icon-btn.account-btn:hover {
    background: transparent;
  }

  .header-right .icon-btn.account-btn .account-avatar {
    width: 40px;
    height: 40px;
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .header-right .icon-btn.account-btn .account-avatar>svg {
    width: 40px;
    height: 40px;
    display: block;
  }

  .header-right .icon-btn.account-btn .account-avatar-ring {
    fill: none;
    stroke: rgba(0, 0, 0, .08);
    stroke-width: 1;
  }

  .header-right .icon-btn.account-btn .account-caret {
    position: absolute;
    bottom: 6px;
    right: 6px;
    transform: translate(50%, 50%);
  }

  .header-right .icon-btn.account-btn .account-caret-bg {
    width: 11px;
    height: 11px;
    border-radius: 999px;
    background: var(--app-icon-bg);
    color: var(--app-text);
    box-shadow: 0 0 0 2px var(--app-surface-bg);
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .header-right .icon-btn.account-btn .account-caret-bg svg {
    width: 11px;
    height: 11px;
  }

  /* ================= Account popover ================= */
  .account-popover {
    /* Theme + sizing tokens used by the account menu CSS */
    --panel-bg: var(--app-surface-bg);
    --text: var(--app-text);
    --secondary-text: var(--app-muted);
    --hover-bg: var(--app-hover);
    --icon-bg: var(--app-icon-bg);
    --sprite-filter: var(--app-sprite-filter);

    --item-height: 44px;
    --panel-padding: 10px;

    --font-user-name: 16px;
    --font-body: 13px;
    --font-item-label: 15px;
    --font-sec-title: 20px;
    --font-submenu-label: 15px;
    --font-setting-name: 14px;
    --font-setting-desc: 13px;
    --font-radio-label: 14px;
    --font-simple-text: 15px;

    --acct-x: 0px;
    --acct-y: 0px;
    --acct-offset-y: -4px;
    --acct-offset-x: 2px;
    --acct-left-pad: 8px;
    --acct-right-pad: 4px;
    position: fixed;
    top: 0;
    left: 0;
    width: 370px;
    height: 460px;
    max-height: calc(100vh - 72px);
    background: var(--panel-bg);
    border-radius: 15px;
    box-shadow: var(--app-card-shadow);
    border: 1px solid var(--app-border);
    overflow: hidden;
    z-index: 2500;
    display: none;
    transform: translate(var(--acct-x), var(--acct-y)) translate(-100%, 0);
  }

  /* Account menu internal layout (was missing → caused broken popover) */
  .account-popover .acct-menu {
    width: 100%;
    height: 100%;
    background: var(--panel-bg);
    color: var(--text);
  }

  .account-popover .acct-menu .fb-menu-container {
    width: 100%;
    height: 100%;
  }

  .account-popover .acct-menu .menu-slider {
    width: 100%;
    height: 100%;
    overflow: hidden;
  }

  .account-popover .acct-menu .menu-track {
    display: flex;
    width: 100%;
    height: 100%;
    transition: transform 0.18s ease;
    will-change: transform;
  }

  .account-popover .acct-menu .menu-panel {
    flex: 0 0 100%;
    height: 100%;
    overflow-y: auto;
    overflow-x: hidden;
    padding: var(--panel-padding);
  }

  .account-popover .acct-menu .menu-slider.slide-active .menu-track {
    transform: translateX(-100%);
  }

  .account-popover .acct-menu .profile-card {
    background: var(--panel-bg);
    border: 1px solid var(--app-border);
    border-radius: 12px;
    padding: 6px 0 8px;
    margin-bottom: 10px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.28);
  }

  .account-popover .acct-menu .profile-info {
    position: relative;
    padding: 6px 12px 8px;
  }

  .account-popover .acct-menu .profile-info .profile-info-inner {
    display: flex;
    align-items: center;
    padding: 8px;
    border-radius: 10px;
    cursor: pointer;
    user-select: none;
  }

  .account-popover.open {
    display: block;
  }

  .account-popover-frame {
    width: 100%;
    height: 100%;
    border: 0;
    display: block;
    background: transparent;
  }

  :root {
    --fb-blue: #0866ff;
  }

  /* ================= Messenger popover ================= */
  .messenger-popover {
    /* CHỈNH VỊ TRÍ POPUP:
         - --mp-offset-y: khoảng cách so với đáy header (có thể âm để “xích lên”)
         - --mp-right-pad: căn mép phải popup theo padding của header
      */
    --mp-offset-y: -6px;
    --mp-right-pad: 28px;
    position: fixed;
    width: 360px;
    height: 650px;
    background: var(--app-surface-bg);
    border-radius: 12px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, .2);
    border: 1px solid var(--app-border);
    overflow: hidden;
    z-index: 2000;
    display: none;
  }

  /* ================= Compose (Tin nhắn mới) ================= */
  .mp-compose {
    position: fixed;
    right: 80px;
    bottom: 1px;
    width: 290px;
    height: 400px;
    background: var(--app-surface-bg);
    color: var(--app-text);
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, .2);
    border: 1px solid var(--app-border);
    overflow: hidden;
    display: none;
    flex-direction: column;
    z-index: 3000;
  }

  .mp-compose.open {
    display: flex;
  }

  .mp-compose-header {
    height: 56px;
    padding: 0 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .mp-compose-title {
    font-size: 14px;
    font-weight: 500;
    letter-spacing: -0.1px;
  }

  .mp-compose-close {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: none;
    background: transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--app-muted);
  }

  .mp-compose-close:hover {
    background: var(--app-hover);
  }

  .mp-compose-close svg {
    width: 20px;
    height: 20px;
    display: block;
    fill: currentColor;
  }

  .mp-compose-to {
    padding: 8px 12px;
    display: flex;
    align-items: center;
    gap: 2px;
  }

  .mp-compose-to-label {
    font-size: 15px;
    color: var(--app-muted);
    flex-shrink: 0;
    margin-bottom: 20px;
    position: relative;
    top: 8px;
  }

  .mp-compose-to-input {
    border: none;
    outline: none;
    font-size: 15px;
    flex: 1;
    padding: 6px 0;
    min-width: 0;
    background: transparent;
    color: var(--app-text);
    caret-color: var(--app-text);
  }

  .mp-compose-to-input::placeholder {
    color: var(--app-muted);
  }

  .mp-compose-divider {
    height: 1px;
    background: var(--app-border);
  }

  .mp-compose-list {
    padding: 8px 0;
    overflow: auto;
    flex: 1;
  }

  .mp-compose-item {
    width: 100%;
    border: none;
    background: transparent;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    text-align: left;
  }

  .mp-compose-item:hover {
    background: var(--app-hover);
  }

  .mp-compose-avatar {
    width: 40px;
    height: 40px;
    border-radius: 999px;
    flex-shrink: 0;
    background: var(--app-icon-bg);
    position: relative;
    overflow: hidden;
  }

  .mp-compose-avatar.meta-ai {
    background: transparent;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .mp-compose-avatar.meta-ai::before {
    content: '';
    display: none;
  }

  .mp-compose-avatar.meta-ai .mp-metaai-ring {
    width: 36px;
    height: 36px;
    display: block;
  }

  .mp-compose-avatar.meta-ai .mp-metaai-ring-overlay {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    border: 1px solid var(--app-border);
    pointer-events: none;
  }

  .mp-compose-avatar.user::before {
    content: '';
    display: none;
  }

  .mp-compose-avatar.user::after {
    content: '';
    display: none;
  }

  .mp-compose-avatar.user {
    background: transparent;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .mp-compose-avatar.user .mp-user-avatar-img {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    object-fit: cover;
    display: block;
  }

  .mp-compose-avatar.user .mp-user-avatar-overlay {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    border: 1px solid var(--app-border);
    pointer-events: none;
  }

  .mp-compose-name {
    font-size: 16px;
    font-weight: 500;
    color: var(--app-text);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
  }

  .mp-compose-verified {
    width: 12px;
    height: 12px;
    border-radius: 0;
    background: transparent;
    color: var(--fb-blue);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .mp-compose-verified svg {
    width: 12px;
    height: 12px;
    fill: currentColor;
    display: block;
  }

  .messenger-popover.open {
    display: flex;
    flex-direction: column;
  }

  .mp-header {
    height: 56px;
    padding: 0 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .mp-title {
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -0.3px;
  }

  .mp-actions {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  .mp-icon {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: none;
    background: transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: var(--app-muted);
  }

  .mp-icon:hover {
    background: var(--icon-hover-bg);
  }

  .mp-icon.is-open {
    background: var(--app-hover);
  }

  .mp-icon svg {
    width: 20px;
    height: 20px;
    fill: currentColor;
    display: block;
  }

  /* ================= Messenger options (Chat settings) ================= */
  .mp-options-menu {
    position: fixed;
    display: none;
    width: 340px;
    background: var(--app-surface-bg);
    border-radius: 12px;
    box-shadow: 0 12px 28px rgba(0, 0, 0, .2);
    border: 1px solid var(--app-border);
    z-index: 2000;
    padding: 0;
    overflow: visible;

    /* FB-like anchor behavior (right edge anchored to button)
         You can tweak these without JS overriding them. */
    --mp-options-shift-x: -2px;
    --mp-options-caret-left: 0px;
  }

  .mp-options-scroll {
    max-height: 550px;
    overflow: auto;
    padding: 8px 0;
    border-radius: 12px;
    background: var(--app-surface-bg);
  }

  .mp-options-menu::before {
    content: '';
    position: absolute;
    top: -8px;
    /* khoảng cách với menu */
    left: var(--mp-options-caret-left);

    width: 0;
    height: 0;

    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-bottom: 8px solid var(--app-surface-bg);

    transform: translateX(-50%);
  }

  .mp-options-menu.open {
    display: block;
  }

  .mp-opts-head {
    padding: 8px 12px 6px;
  }

  .mp-opts-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--app-text);
    margin-bottom: 2px;
  }

  .mp-opts-sub {
    font-size: 13px;
    color: var(--app-muted);
    line-height: 1.25;
  }

  .mp-opts-divider {
    /* Divider (đường kẻ ngang) dùng để chia nhóm giống Facebook.
         Cách dùng:
         - Chèn vào HTML: <div class="mp-opts-divider" role="separator"></div>
         - Muốn sát hơn (như ngay dưới header hoặc dưới 1 item): thêm class `is-tight`.
         Tùy chỉnh:
         - Độ đậm/nhạt: đổi `background`
         - Khoảng cách: đổi `margin`
      */
    height: 1.25px;
    background: var(--app-border);
    margin: 8px 16px;
  }

  .mp-opts-divider.is-tight {
    margin: 6px 16px;
  }

  .mp-opts-row {
    width: 100%;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2px;
    padding: 3px 12px;
    text-align: left;
    color: var(--app-text);
  }

  .mp-opts-row:hover {
    background: var(--hover-bg);
  }

  .mp-opts-row:focus {
    outline: none;
  }

  .mp-opts-row:focus-visible {
    box-shadow: 0 0 0 2px rgba(8, 102, 255, .85) inset;
    border-radius: 10px;
    background: var(--app-surface-bg);
  }

  .mp-opts-left {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    flex: 1;
  }

  .mp-opts-ico {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--app-muted);
    flex-shrink: 0;
  }

  /* Icon sprite (giống Facebook) cho một vài item trong menu.
       Dùng khi bạn muốn icon đúng y hệt sprite PNG của FB thay vì SVG.
       - Base class: `.mp-ico-sprite`
       - Từng icon cụ thể: thêm class kiểu `.mp-ico-call-sound` để set background-position.
    */
  .mp-ico-sprite {
    width: 20px;
    height: 20px;
    background-image: url('https://static.xx.fbcdn.net/rsrc.php/v4/yz/r/wrC8dZBs6J6.png');
    background-position: 0 0;
    background-size: auto;
    background-repeat: no-repeat;
    display: inline-block;
    filter: var(--app-sprite-filter);
  }

  .mp-ico-call-sound {
    background-position: 0 -168px;
  }

  .mp-ico-message-sound {
    background-image: url('https://static.xx.fbcdn.net/rsrc.php/v4/yv/r/qIiumBTWeGK.png');
    background-position: 0 -180px;
  }

  .mp-ico-new-message-pop {
    /* Icon “Tin nhắn mới bật lên” theo sprite bạn gửi */
    background-position: 0 0;
  }

  .mp-ico-active-status {
    /* Icon “Trạng thái hoạt động” theo sprite bạn gửi */
    background-position: 0 -21px;
  }

  .mp-ico-requests {
    /* Icon “Tin nhắn đang chờ” theo sprite bạn gửi */
    background-image: url('https://static.xx.fbcdn.net/rsrc.php/v4/y0/r/4tVh2_pWBuE.png');
    background-position: 0 -201px;
  }

  .mp-ico-archived {
    /* Icon “Đoạn chat đã lưu trữ” theo sprite bạn gửi */
    background-image: url('https://static.xx.fbcdn.net/rsrc.php/v4/yq/r/ZC1GVF1R9Xm.png');
    background-position: 0 -264px;
  }

  .mp-ico-delivery {
    /* Icon “Cài đặt gửi tin nhắn” theo sprite bạn gửi */
    background-position: 0 -84px;
  }

  .mp-ico-restricted {
    /* Icon “Tài khoản đã hạn chế” theo sprite bạn gửi */
    background-position: 0 -63px;
  }

  .mp-ico-blocking {
    /* Icon “Cài đặt chặn” theo sprite bạn gửi */
    background-position: 0 -189px;
  }

  .mp-opts-ico svg {
    width: 20px;
    height: 20px;
    fill: currentColor;
    display: block;
  }

  .mp-opts-text {
    min-width: 0;
  }

  .mp-opts-label {
    font-size: 15px;
    font-weight: 400;
    line-height: 1.15;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .mp-opts-desc {
    font-size: 13px;
    color: var(--app-muted);
    margin-top: 3px;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .mp-opts-right {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .mp-switch {
    width: 46px;
    height: 26px;
    border-radius: 999px;
    background: var(--app-icon-bg);
    position: relative;
    transition: background-color .16s ease;
  }

  .mp-switch::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: var(--app-surface-bg);
    box-shadow: 0 2px 6px rgba(0, 0, 0, .2);
    transition: transform .16s ease;
  }

  .mp-opts-row[role="switch"][aria-checked="true"] .mp-switch {
    background: var(--fb-blue);
  }

  .mp-opts-row[role="switch"][aria-checked="true"] .mp-switch::after {
    transform: translateX(20px);
  }

  .mp-chevron svg {
    width: 18px;
    height: 18px;
    fill: currentColor;
    display: block;
  }

  .mp-chevron {
    color: var(--app-muted);
  }

  .mp-search {
    padding: 0 12px 10px;
  }

  .mp-searchbox {
    height: 40px;
    border-radius: 999px;
    background: var(--search-bg);
    /* messenger search follows theme */
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 12px;
    color: var(--search-placeholder);
  }

  .mp-searchbox svg {
    width: 18px;
    height: 18px;
    fill: var(--search-placeholder);
    flex-shrink: 0;
  }

  .mp-searchbox input {
    border: none;
    outline: none;
    background: transparent;
    width: 100%;
    font-size: 15px;
    color: var(--search-text);
    /* typed text follows theme */
  }

  .mp-searchbox input::placeholder {
    color: var(--search-placeholder);
  }

  .mp-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 2px 12px 10px;
    position: relative;
  }

  .mp-tab {
    border: none;
    background: transparent;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 15px;
    font-weight: 600;
    color: var(--app-text);
    transition: background-color .16s ease, color .16s ease;
  }

  .mp-tab:not(.active):hover {
    background: var(--app-hover);
  }

  .mp-tab:focus {
    outline: none;
  }

  .mp-tab:focus-visible {
    outline: 2px solid rgba(8, 102, 255, .55);
    outline-offset: 2px;
  }

  .mp-tab.active {
    background: var(--app-hover);
    color: var(--fb-blue);
  }

  .mp-tab.mp-more-btn {
    padding: 8px 10px;
    font-size: 18px;
    line-height: 1;
    width: 40px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .mp-tab.mp-more-btn.is-open {
    background: var(--app-hover);
  }

  .mp-more-menu {
    position: absolute;
    display: none;
    min-width: 150px;
    background: var(--app-surface-bg);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
    border: 1px solid var(--app-border);
    padding: 6px;
    z-index: 10;
  }

  .mp-more-menu.open {
    display: block;
  }

  .mp-more-item {
    width: 100%;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    padding: 10px 12px;
    border-radius: 10px;
    text-align: left;
    color: var(--app-text);
  }

  .mp-more-item:hover {
    background: var(--app-hover);
  }

  .mp-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 24px;
    text-align: center;
    color: var(--app-muted);
  }

  .mp-empty h4 {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 6px;
    color: var(--app-muted);
  }

  .mp-empty p {
    font-size: 14px;
    color: var(--app-muted);
  }

  .mp-footer {
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-top: 1px solid var(--app-border);
  }

  .mp-footer a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: var(--fb-blue);
    font-weight: 700;
    text-decoration: none;
    font-size: 16px;
  }

  .mp-footer a svg {
    width: 16px;
    height: 16px;
    fill: currentColor;
    flex-shrink: 0;
  }

  .mp-footer a:hover {
    text-decoration: underline;
  }

  /* Search result: user item and friend CTA centered over avatar */
  .user-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    position: relative;
    justify-content: flex-start;
  }

  .user-item img {
    width: 36px;
    height: 36px;
    object-fit: cover;
    border-radius: 6px;
    display: block;
  }

  .user-left {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: 0 1 auto;
    min-width: 0;
  }

  .recent-label {
    display: inline-block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 420px;
  }

  /* recent-item remove button */
  .recent-remove {
    background: transparent;
    border: none;
    color: var(--search-placeholder, #b0b3b8);
    font-size: 22px;
    width: 28px;
    height: 28px;
    padding: 0;
    margin-left: auto;
    border-radius: 50%;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .recent-remove:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #ffffff;
  }

  /* header shown above recent results */
  .results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px 8px 12px;
    color: var(--app-muted);
    font-weight: 700;
    gap: 8px;
  }

  .results-header .recent-title {
    font-size: 14px;
  }

  .results-header .recent-edit {
    background: transparent;
    border: 0;
    color: #2d88ff;
    font-size: 13px;
    cursor: pointer;
    padding: 6px 8px;
    border-radius: 6px;
  }

  .results-header .recent-edit:active {
    opacity: .85
  }

  .results-header .recent-edit:hover {
    background: rgba(255, 255, 255, 0.08);
  }


  /* friend-cta removed */


    /* =====================
      Merged from <head> <style> (feed/layout/modal/footer)
      ===================== */

  * {
    box-sizing: border-box
  }

  body {
    margin: 0;
    background: var(--fb-bg);
    color: var(--fb-text);
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto;
  }

  /* ===== LAYOUT ===== */
  .fb-layout {
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr) 280px;
    min-height: 100vh;
    padding-top: 56px;
    /* push content below fixed header */
  }

  /* ===== LEFT SIDEBAR ===== */
  .fb-sidebar-left {
    padding: 12px 8px;
  }

  .fb-menu {
    list-style: none;
    padding: 0;
    margin: 0;
  }

  .fb-menu-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    cursor: pointer;
  }

  .fb-menu-item:hover {
    background: var(--fb-hover);
  }

  /* Make anchor inside menu item fill the row and align contents */
  .fb-menu-item>.fb-menu-link {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    color: inherit;
    text-decoration: none;
  }

  .fb-friends-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .fb-friends-icon i {
    width: 36px;
    height: 36px;
    display: inline-block;
    background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yh/r/FlnJwE1zAUa.png");
    background-repeat: no-repeat;
    background-size: auto;
    background-position: 0px -777px;
    /* ICON BẠN BÈ */
  }

  .fb-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .fb-icon i {
    width: 36px;
    height: 36px;
    display: inline-block;
    background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yh/r/FlnJwE1zAUa.png");
    background-repeat: no-repeat;
    background-size: auto;
  }

  /* ICON KỶ NIỆM */
  .fb-icon-memories i {
    background-position: 0px -1184px;
  }

  /* ===== ICON ĐÃ LƯU (SAVED) ===== */
  .fb-icon-saved i {
    background-position: 0px -481px;
  }

  /* ===== ICON NHÓM (GROUPS) ===== */
  .fb-icon-groups i {
    background-position: 0px -185px;
  }

  /* ===== ICON VIDEO ===== */
  .fb-icon-video i {
    background-position: 0px -1628px;

  }

  /* ICON MARKETPLACE */
  .fb-icon-marketplace i {
    background-position: 0px -925px;
  }

  /* ===== ICON BẢNG FEED ===== */
  .fb-icon-feed i {
    background-position: 0px -37px;
    /* FEED ICON */
  }

  /* ===== ICON SỰ KIỆN (EVENTS) ===== */
  .fb-icon-events i {
    background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yh/r/FlnJwE1zAUa.png");
    background-repeat: no-repeat;
    background-size: auto;
    background-position: 0px -629px;
    /* ICON SỰ KIỆN CHUẨN */
    width: 36px;
    height: 36px;
    display: inline-block;
  }

  /* ===== ICON TRÌNH QUẢN LÝ QUẢNG CÁO ===== */
  .fb-icon-ads i {
    background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yh/r/FlnJwE1zAUa.png");
    background-repeat: no-repeat;
    background-size: auto;
    background-position: 0px -370px;
    /* ADS MANAGER */
    width: 36px;
    height: 36px;
    display: inline-block;
  }

  /* ===== XEM THÊM ===== */
  .fb-see-more {
    padding: 0 8px;
    cursor: pointer;
  }

  .fb-see-more-inner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.15s ease;
  }

  .fb-see-more-inner:hover {
    background: rgba(0, 0, 0, 0.05);
  }

  .fb-see-more-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #e4e6eb;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .fb-see-more-icon svg {
    fill: #050505;
  }

  .fb-see-more-text {
    font-size: 15px;
    font-weight: 500;
    color: #050505;
  }

  /* MENU ẨN */
  .more-menu {
    display: none;
  }

  /* HIỆN KHI XEM THÊM */
  .show-more .more-menu {
    display: block;
  }


  /* DARK MODE */
  @media (prefers-color-scheme: dark) {
    .fb-see-more-inner:hover {
      background: rgba(255, 255, 255, 0.1);
    }

    .fb-see-more-icon {
      background: #3a3b3c;
    }

    .fb-see-more-icon svg,
    .fb-see-more-text {
      fill: #e4e6eb;
      color: #e4e6eb;
    }
  }



  .fb-menu-icon img {
    width: 36px;
    height: 36px;
    display: block;
    border-radius: 50%;
  }

  .fb-menu-text {
    font-size: 15px;
    font-weight: 500;
  }

  .fb-sidebar-footer {
    margin-top: 16px;
    padding: 0 12px;
    font-size: 12px;
    color: var(--fb-muted);
    line-height: 1.4;
  }

  /* ===== CENTER ===== */
  .fb-content {
    padding: 16px;
    display: flex;
    justify-content: center;
    align-items: flex-start;
  }

  .fb-feed {
    width: 100%;
    max-width: 680px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  /* ===== STATUS BAR ===== */
  .fb-status-bar {
    width: 100%;
    background: var(--app-surface-bg);
    border: 1px solid var(--app-border);
    border-radius: 16px;
    padding: 6px 8px;
  }

  .fb-status-inner {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .fb-status-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
  }

  .fb-status-input {
    flex: 1;
    height: 36px;
    border: none;
    border-radius: 999px;
    background: var(--app-input-bg);
    padding: 0 14px;
    font-size: 14px;
    color: var(--app-text);
    outline: none;
  }

  .fb-status-input::placeholder {
    color: var(--app-muted);
  }

  /* ===== CREATE POST MODAL ===== */
  .post-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 16px;
  }

  .post-modal-backdrop.is-open {
    display: flex;
  }

  .post-modal {
    width: 520px;
    max-width: 100%;
    background: var(--fb-card);
    color: var(--fb-text);
    border-radius: 12px;
    box-shadow: var(--app-card-shadow, 0 12px 28px rgba(0,0,0,0.2));
    overflow: hidden;
  }

  .post-modal-header {
    position: relative;
    padding: 14px 16px;
    border-bottom: 1px solid var(--fb-border, rgba(0,0,0,0.08));
    text-align: center;
    font-weight: 700;
    font-size: 20px;
  }

  .post-modal-close {
    position: absolute;
    right: 12px;
    top: 10px;
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 999px;
    background: var(--icon-hover-bg, rgba(255,255,255,0.08));
    color: var(--fb-text);
    cursor: pointer;
    font-size: 20px;
    line-height: 36px;
  }

  .post-modal-close:hover {
    background: rgba(255, 255, 255, 0.12);
  }

  .post-modal-body {
    padding: 14px 16px 16px;
  }

  .post-modal-user {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
  }

  .post-modal-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: block;
    object-fit: cover;
    background: rgba(255, 255, 255, 0.08);
  }

  .post-modal-user-name {
    font-weight: 700;
    font-size: 15px;
    line-height: 1.2;
  }

  .post-modal-audience {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 28px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    background: var(--app-icon-bg);
    color: var(--app-text);
  }

  .post-modal-audience .audience-icon {
    width: 12px;
    height: 12px;
    display: block;
    filter: var(--app-sprite-filter);
    opacity: 0.95;
  }

  .post-modal-audience .audience-text {
    display: inline-block;
    line-height: 1;
  }

  .post-modal-audience .audience-caret {
    width: 12px;
    height: 12px;
    display: inline-block;
    background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yF/r/NTPe0D2TtJp.png");
    background-position: -17px -958px;
    background-repeat: no-repeat;
    background-size: auto;
    filter: var(--app-sprite-filter);
    opacity: 0.95;
  }

  .post-modal-audience:hover {
    background: var(--icon-hover-bg);
  }

  .post-modal-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .post-modal-textwrap {
    position: relative;
    border-radius: 12px;
  }

  .post-modal-textwrap[data-bg="none"] {
    background: transparent;
  }

  .post-modal-textwrap[data-bg="bg1"] {
    background: linear-gradient(135deg, #0866FF, #1D85FC);
  }

  .post-modal-textwrap[data-bg="bg2"] {
    background: linear-gradient(135deg, #FB3C44, #FA61BA);
  }

  .post-modal-textwrap[data-bg="bg3"] {
    background: linear-gradient(135deg, #00A400, #31A24C);
  }

  .post-modal-textwrap[data-bg="bg4"] {
    background: linear-gradient(135deg, #7D74FF, #14B898);
  }

  .post-modal-textwrap[data-bg="bg5"] {
    background: linear-gradient(135deg, #F9CF00, #F5C33B);
  }

  .post-modal-textwrap:not([data-bg="none"]) .post-modal-textarea {
    color: #fff;
    text-align: center;
    font-weight: 700;
    padding: 36px 14px 56px;
    min-height: 200px;
  }

  .post-modal-textwrap:not([data-bg="none"]) .post-modal-textarea::placeholder {
    color: rgba(255, 255, 255, 0.85);
  }

  .post-modal-bg-btn {
    position: absolute;
    left: 8px;
    bottom: 8px;
    width: 40px;
    height: 40px;
    border: 0;
    border-radius: 10px;
    background: var(--app-icon-bg);
    color: var(--app-text);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    user-select: none;
  }

  .post-modal-bg-btn:hover {
    background: var(--icon-hover-bg);
  }

  .post-modal-bg-btn.is-disabled,
  .post-modal-bg-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
  }

  .post-modal-bg-btn:disabled {
    pointer-events: none;
  }

  .post-modal-bg-btn .fb-tooltip {
    bottom: 125%;
  }

  .post-modal-bg-btn:hover .fb-tooltip {
    opacity: 1;
  }

  .post-modal-bg-aa {
    font-weight: 800;
    font-size: 16px;
    line-height: 1;
  }

  .post-modal-bg-picker {
    position: absolute;
    left: 56px;
    bottom: 8px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px;
    border-radius: 999px;
    background: var(--app-icon-bg);
    border: 1px solid rgba(255, 255, 255, 0.12);
    z-index: 2;
    opacity: 0;
    transform: translateX(-10px);
    pointer-events: none;
    transition: opacity 180ms var(--fds-animation-enter-exit-in, cubic-bezier(.14, 1, .34, 1)),
      transform 220ms var(--fds-animation-enter-exit-in, cubic-bezier(.14, 1, .34, 1));
  }

  .post-modal-bg-picker.is-open {
    opacity: 1;
    transform: translateX(0);
    pointer-events: auto;
  }

  @media (prefers-reduced-motion: reduce) {
    .post-modal-bg-picker {
      transition: none;
      transform: none;
    }
  }

  .post-modal-bg-swatch {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: 0;
    cursor: pointer;
    padding: 0;
  }

  .post-modal-bg-swatch.is-none {
    background: transparent;
    border: 2px solid rgba(228, 230, 235, 0.45);
  }

  .post-modal-bg-swatch[data-bg="bg1"] { background: linear-gradient(135deg, #0866FF, #1D85FC); }
  .post-modal-bg-swatch[data-bg="bg2"] { background: linear-gradient(135deg, #FB3C44, #FA61BA); }
  .post-modal-bg-swatch[data-bg="bg3"] { background: linear-gradient(135deg, #00A400, #31A24C); }
  .post-modal-bg-swatch[data-bg="bg4"] { background: linear-gradient(135deg, #7D74FF, #14B898); }
  .post-modal-bg-swatch[data-bg="bg5"] { background: linear-gradient(135deg, #F9CF00, #F5C33B); }

  .post-modal-textarea {
    width: 100%;
    min-height: 170px;
    border: 0;
    outline: none;
    resize: none;
    background: transparent;
    color: var(--fb-text);
    font-size: 26px;
    line-height: 1.25;
    padding: 6px 2px;
    font-weight: 600;
    letter-spacing: -0.2px;
  }

  .post-modal-textarea::placeholder {
    color: var(--fb-text-muted);
    opacity: 0.95;
  }

  .post-modal-file {
    display: none;
  }

  .post-modal-media-preview {
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 8px;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px;
  }

  .post-modal-media-preview[hidden] {
    display: none !important;
  }

  .post-modal-media-item {
    position: relative;
    border-radius: 10px;
    overflow: hidden;
    background: var(--app-icon-bg);
    border: 1px solid var(--app-border);
    aspect-ratio: 1 / 1;
  }

  .post-modal-media-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .post-modal-media-remove {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 28px;
    height: 28px;
    border: 0;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    cursor: pointer;
    font-size: 18px;
    line-height: 28px;
  }

  .post-modal-photo-btn {
    cursor: pointer;
  }

  .post-modal-addto {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
  }

  .post-modal-addto-label {
    font-weight: 700;
    font-size: 14px;
    color: var(--fb-text);
  }

  .post-modal-addto-actions {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .post-modal-icon-btn {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    color: rgba(228, 230, 235, 0.9);
    cursor: pointer;
    font-size: 16px;
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    outline: none;
  }

  .post-modal-icon-inner {
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  /* icon MORE (sprite giống inspect) */
.post-modal-icon-more-img {
  background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/y3/r/meA5_bcHZ_F.png");
  background-position: 0px -75px;
  background-size: auto;
  background-repeat: no-repeat;
  width: 24px;
  height: 24px;
  filter: var(--app-sprite-filter);
}

  

  /* tooltip */
.fb-tooltip {
  position: absolute;
  bottom: 120%;
  left: 50%;
  transform: translateX(-50%);

  padding: 6px 10px;
  border-radius: 8px;

  font-size: 12px;
  font-weight: 500;
  white-space: nowrap;

  background: #e4e6eb;
  color: #050505;

  opacity: 0;
  pointer-events: none;
  transition: opacity .15s ease;
}

/* dark mode tooltip */
body.dark .fb-tooltip,
.__fb-dark-mode .fb-tooltip {
  background: #e4e6eb;
  color: #050505;
}

/* show on hover */
.post-modal-icon-btn:hover .fb-tooltip {
  opacity: 1;
}

  .post-modal-icon-img {
  width: 24px;
  height: 24px;
  display: block;
}

/* hover (use the same theme token as the rest of the UI) */
.post-modal-icon-btn:hover {
  background: var(--icon-hover-bg);
}

.post-modal-icon-btn:active {
  transform: scale(0.97);
}

  .post-modal-submit {
    height: 40px;
    border: 0;
    border-radius: 10px;
    font-weight: 700;
    font-size: 15px;
    background: rgba(255, 255, 255, 0.14);
    color: rgba(228, 230, 235, 0.65);
    cursor: not-allowed;
  }

  .post-modal-submit.is-enabled {
    background: #0866ff;
    color: #fff;
    cursor: pointer;
  }

  .fb-status-actions {
    display: flex;
    gap: 6px;
  }

  .fb-status-icon {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    cursor: pointer;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;
    /* 👈 vuông bo góc như Facebook */
    padding: 0;
  }

  .fb-status-icon img {
    width: 24px;
    height: 24px;
    display: block;
  }

  .fb-status-icon:hover {
    background: var(--icon-hover-bg);
  }

  /* ===== CREATE STORY ===== */
  .fb-story-create {
    width: 100%;
    background: var(--app-surface-bg);
    border: 1px solid var(--app-border);
    border-radius: 16px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: background 0.15s ease, box-shadow 0.15s ease, transform 0.05s ease;
  }

  .fb-story-create:hover {
    background: var(--app-hover);
    box-shadow: 0 1px 2px rgba(0, 0, 0, .2);
  }

  .fb-story-create:active {
    transform: scale(0.995);
  }

  .fb-story-create * {
    pointer-events: none;
  }

  /* story create icon (circle with plus) */
  .fb-story-create-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #232f36;
    /* darker circle to match screenshot */
    color: var(--accent, #2d88ff);
    /* plus uses accent color */
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 36px;
  }

  .fb-story-create-icon svg {
    width: 18px;
    height: 18px;
    display: block
  }

  .fb-story-create-icon svg path {
    fill: currentColor
  }

  /* ===== POSTS FEED ===== */
  .fb-post-card {
    width: 100%;
    background: var(--app-surface-bg);
    border: 1px solid var(--app-border);
    border-radius: 16px;
    overflow: hidden;
    padding: 12px 0 0;
  }

  .fb-post-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 14px 10px;
  }

  .fb-post-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
  }

  .fb-post-meta {
    flex: 1;
    min-width: 0;
  }

  .fb-post-name {
    font-weight: 700;
    font-size: 14px;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

 .fb-post-time {
  display: flex;
  align-items: center;
  gap: 4px;

  font-size: 12px;
  color: var(--app-muted);
}

/* tương đương x78zum5 + x6s0dn4 + xl56j7k */
.fb-audience-wrap,
.fb-audience-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}

/* icon */
.fb-audience-icon {
  width: 12px;
  height: 12px;
  display: block;
  filter: var(--app-sprite-filter);
}

/* div rỗng giống FB (x165d6jo) */
.fb-audience-spacer {
  width: 0;
  height: 0;
}

/* theme-aware: icon filter is handled via --app-sprite-filter */

  .fb-post-more {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    color: var(--app-text);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 1;
  }

  .fb-post-more:hover {
    background: var(--app-hover);
  }

  /* ===== POST MENU (three-dots) ===== */
  .fb-post-menu {
    position: fixed;
    width: 360px;
    max-width: calc(100vw - 24px);
    background: var(--app-surface-bg);
    border-radius: 12px;
    box-shadow: var(--app-card-shadow);
    z-index: 12000;
    padding: 8px;
    display: none;
  }

  .fb-post-menu.is-open {
    display: block;
  }

  .fb-post-menu-item {
    width: 100%;
    border: 0;
    background: transparent;
    color: var(--app-text);
    text-align: left;
    padding: 10px 10px;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    font-size: 15px;
    line-height: 1.2;
  }

  .fb-post-menu-item:hover {
    background: var(--app-hover);
  }

  .fb-post-menu-ico {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: var(--app-hover);
    font-size: 16px;
    line-height: 1;
  }

  .fb-post-menu-text {
    flex: 1;
    min-width: 0;
  }

  .fb-post-menu-title {
    font-weight: 700;
    font-size: 15px;
    color: var(--app-text);
  }

  .fb-post-menu-sub {
    margin-top: 2px;
    font-size: 12.5px;
    color: var(--app-muted);
    line-height: 1.2;
  }

  .fb-post-menu-sep {
    height: 1px;
    background: var(--app-border);
    margin: 8px 4px;
  }

  .fb-post-content {
    padding: 0 14px 12px;
    font-size: 15px;
    line-height: 1.35;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .fb-post-image {
    width: 100%;
    display: block;
    max-height: 680px;
    object-fit: cover;
    background: var(--app-page-bg);
  }

  .fb-post-media-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 2px;
    background: var(--app-border);
    overflow: hidden;
  }

  .fb-post-media-grid .fb-post-image {
    max-height: 420px;
  }

  .fb-post-actions {
    display: flex;
    gap: 6px;
    padding: 6px 10px 10px;
    border-top: 1px solid var(--app-border);
  }

  .fb-post-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 14px 8px;
    color: var(--app-muted);
    font-size: 13px;
    line-height: 1.2;
  }

  .fb-post-stats .fb-post-stat-link {
    color: inherit;
    text-decoration: none;
    cursor: pointer;
    user-select: none;
  }

  .fb-post-stats .fb-post-stat-link:hover {
    text-decoration: underline;
  }

  .fb-post-action-btn.is-liked {
    color: #0866ff;
  }

  .fb-post-comments {
    padding: 0 14px 12px;
    border-top: 1px solid var(--app-border);
    display: none;
  }

  .fb-post-comments.is-open {
    display: block;
  }

  .fb-comment-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px 0;
  }

  .fb-comment-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
  }

  .fb-comment-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    flex: 0 0 auto;
  }

  .fb-comment-bubble {
    background: var(--app-input-bg);
    border-radius: 14px;
    padding: 8px 10px;
    flex: 1;
    min-width: 0;
  }

  .fb-comment-name {
    font-weight: 700;
    font-size: 13px;
    line-height: 1.15;
    margin-bottom: 2px;
  }

  .fb-comment-text {
    font-size: 13.5px;
    line-height: 1.3;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .fb-comment-form {
    display: flex;
    gap: 10px;
    align-items: center;
    padding-top: 6px;
  }

  .fb-comment-input {
    flex: 1;
    height: 36px;
    border: 0;
    border-radius: 999px;
    background: var(--app-input-bg);
    color: var(--app-text);
    padding: 0 12px;
    outline: none;
    font-size: 13.5px;
  }

  .fb-comment-submit {
    height: 36px;
    border: 0;
    border-radius: 999px;
    padding: 0 12px;
    background: var(--app-hover);
    color: var(--app-text);
    cursor: pointer;
    font-weight: 700;
  }

  .fb-comment-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  /* ===== Comment modal (giống Facebook) ===== */
  .fb-comment-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 16px;
  }

  .fb-comment-modal-backdrop.is-open {
    display: flex;
  }

  .fb-comment-modal {
    width: min(760px, calc(100vw - 24px));
    max-height: min(88vh, 860px);
    background: var(--app-surface-bg);
    color: var(--app-text);
    border-radius: 12px;
    box-shadow: var(--app-card-shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .fb-comment-modal-header {
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    border-bottom: 1px solid var(--app-border);
    padding: 0 56px;
    font-weight: 800;
    font-size: 18px;
  }

  .fb-comment-modal-close {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 999px;
    background: var(--app-hover);
    color: var(--app-text);
    cursor: pointer;
    font-size: 22px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .fb-comment-modal-body {
    flex: 1;
    overflow: auto;
  }

  .fb-comment-modal-post {
    padding: 10px 0 0;
  }

  .fb-comment-modal-divider {
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
    margin: 8px 0;
  }

  .fb-comment-modal-comments {
    padding: 0 14px 12px;
  }

  .fb-comment-empty {
    padding: 34px 0 44px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: var(--fb-muted);
    text-align: center;
  }

  .fb-comment-empty[hidden] {
    display: none;
  }

  .fb-comment-empty-icon {
    width: 96px;
    height: 96px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.08);
    display: grid;
    place-items: center;
    margin: 8px 0 6px;
    color: rgba(255, 255, 255, 0.75);
  }

  .fb-comment-empty-title {
    font-weight: 800;
    font-size: 18px;
    color: var(--fb-text);
  }

  .fb-comment-empty-sub {
    font-size: 14px;
    color: var(--fb-muted);
  }

  /* chỉ hiển thị giống FB trong modal, không tương tác */
  .fb-comment-modal-post .fb-post-actions {
    pointer-events: auto;
  }

  /* ===== Comment actions (like FB) ===== */
  .fb-comment-item {
    position: relative;
  }

  .fb-comment-bubble-head {
    display: flex;
    align-items: flex-start;
    gap: 8px;
  }

  .fb-comment-bubble-head .fb-comment-name {
    margin-bottom: 0;
    flex: 1;
    min-width: 0;
  }

  .fb-comment-more {
    width: 28px;
    height: 28px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--fb-muted);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .fb-comment-more:hover {
    background: var(--fb-hover);
    color: var(--fb-text);
  }

  .fb-comment-meta {
    display: flex;
    gap: 12px;
    align-items: center;
    padding-left: 42px;
    margin-top: 2px;
    font-size: 12.5px;
    color: var(--fb-muted);
    user-select: none;
  }

  .fb-comment-action {
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
    font-weight: 700;
    padding: 0;
  }

  .fb-comment-action:hover {
    text-decoration: underline;
  }

  .fb-comment-action.is-liked {
    color: #0866ff;
  }

  .fb-comment-item.is-reply {
    margin-left: 42px;
  }

  .fb-comment-item.is-reply .fb-comment-meta {
    padding-left: 0;
  }

  .fb-comment-menu {
    position: absolute;
    right: 0;
    top: 0;
    transform: translateY(32px);
    width: 240px;
    background: var(--fb-card);
    border-radius: 12px;
    box-shadow: var(--app-card-shadow);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 6px;
    display: none;
    z-index: 10000;
  }

  .fb-comment-menu.is-open {
    display: block;
  }

  .fb-comment-menu-btn {
    width: 100%;
    border: 0;
    background: transparent;
    color: var(--fb-text);
    text-align: left;
    padding: 10px 10px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 700;
  }

  .fb-comment-menu-btn:hover {
    background: var(--fb-hover);
  }

  .fb-comment-edit-wrap {
    margin-top: 6px;
  }

  .fb-comment-edit-input {
    width: 100%;
    min-height: 64px;
    border: 0;
    outline: none;
    border-radius: 10px;
    background: var(--fb-input);
    color: var(--fb-text);
    padding: 10px;
    resize: vertical;
    font-size: 13.5px;
    line-height: 1.3;
  }

  .fb-comment-edit-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 6px;
  }

  .fb-comment-edit-actions button {
    height: 32px;
    border: 0;
    border-radius: 999px;
    padding: 0 12px;
    cursor: pointer;
    font-weight: 800;
    background: rgba(255, 255, 255, 0.10);
    color: var(--fb-text);
  }

  .fb-replying {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.06);
    margin: 10px 0 8px;
    color: var(--fb-muted);
    font-size: 13px;
  }

  .fb-replying strong {
    color: var(--fb-text);
  }

  .fb-replying-cancel {
    border: 0;
    background: transparent;
    color: var(--fb-text);
    cursor: pointer;
    font-weight: 900;
    width: 28px;
    height: 28px;
    border-radius: 999px;
  }

  .fb-replying-cancel:hover {
    background: var(--fb-hover);
  }

  .fb-post-action-btn {
    flex: 1;
    height: 36px;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--app-muted, var(--fb-muted));
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .fb-post-action-ico {
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: inherit;
    flex: 0 0 20px;
  }

  .fb-post-action-ico svg {
    width: 20px;
    height: 20px;
    display: block;
  }

  /* Post action icons as FB sprites (theme-aware via --app-sprite-filter) */
  .fb-post-action-ico .fb-post-sprite {
    width: 20px;
    height: 20px;
    display: inline-block;
    background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yF/r/NTPe0D2TtJp.png");
    background-repeat: no-repeat;
    background-size: auto;
    filter: var(--app-sprite-filter);
  }

  .fb-post-action-ico .fb-post-sprite-comment {
    background-position: 0px -762px;
  }

  .fb-post-action-ico .fb-post-sprite-share {
    background-position: 0px -846px;
  }

  .fb-post-action-text {
    line-height: 1;
    font-size: 14px;
  }

  .fb-post-action-btn:hover {
    background: var(--app-hover, var(--fb-hover));
    color: var(--app-text, var(--fb-text));
  }



  /* ===== REACTIONS (LIKE BAR + PICKER) ===== */
  .fb-like-bar {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .fb-like-btn {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .fb-like-emoji {
    font-size: 16px;
    line-height: 1;
    transform: translateY(-0.5px);
  }

  .fb-reaction-picker {
    position: absolute;
    left: 50%;
    bottom: 36px;
    transform: translateX(-50%) translateY(8px) scale(0.98);
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border-radius: 999px;
    background: var(--fb-card);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: var(--app-card-shadow);
    opacity: 0;
    pointer-events: none;
    transition: opacity .12s ease, transform .12s ease;
    z-index: 20;
  }

  .fb-like-bar:hover .fb-reaction-picker,
  .fb-like-bar:focus-within .fb-reaction-picker {
    opacity: 1;
    pointer-events: auto;
    transform: translateX(-50%) translateY(0) scale(1);
    animation: fbPickerIn 140ms ease-out;
  }

  @keyframes fbPickerIn {
    from {
      opacity: 0;
      transform: translateX(-50%) translateY(10px) scale(0.96);
    }
    to {
      opacity: 1;
      transform: translateX(-50%) translateY(0) scale(1);
    }
  }

  .fb-reaction-item {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    cursor: pointer;
    font-size: 20px;
    line-height: 1;
    display: grid;
    place-items: center;
    transform: translateY(0) scale(1);
    transition: transform 90ms ease, background 90ms ease;
  }

  .fb-reaction-item:hover {
    background: var(--fb-hover);
    transform: translateY(-2px) scale(1.12);
  }

  /* Staggered icon pop on open (Facebook-like) */
  .fb-like-bar:hover .fb-reaction-item,
  .fb-like-bar:focus-within .fb-reaction-item {
    animation: fbReactionPop 240ms ease-out both;
  }

  .fb-like-bar:hover .fb-reaction-item:nth-child(1),
  .fb-like-bar:focus-within .fb-reaction-item:nth-child(1) { animation-delay: 0ms; }
  .fb-like-bar:hover .fb-reaction-item:nth-child(2),
  .fb-like-bar:focus-within .fb-reaction-item:nth-child(2) { animation-delay: 20ms; }
  .fb-like-bar:hover .fb-reaction-item:nth-child(3),
  .fb-like-bar:focus-within .fb-reaction-item:nth-child(3) { animation-delay: 40ms; }
  .fb-like-bar:hover .fb-reaction-item:nth-child(4),
  .fb-like-bar:focus-within .fb-reaction-item:nth-child(4) { animation-delay: 60ms; }
  .fb-like-bar:hover .fb-reaction-item:nth-child(5),
  .fb-like-bar:focus-within .fb-reaction-item:nth-child(5) { animation-delay: 80ms; }
  .fb-like-bar:hover .fb-reaction-item:nth-child(6),
  .fb-like-bar:focus-within .fb-reaction-item:nth-child(6) { animation-delay: 100ms; }
  .fb-like-bar:hover .fb-reaction-item:nth-child(7),
  .fb-like-bar:focus-within .fb-reaction-item:nth-child(7) { animation-delay: 120ms; }

  @keyframes fbReactionPop {
    0% {
      transform: translateY(8px) scale(0.85);
      opacity: 0;
    }
    60% {
      transform: translateY(-2px) scale(1.12);
      opacity: 1;
    }
    100% {
      transform: translateY(0) scale(1);
      opacity: 1;
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .fb-like-bar:hover .fb-reaction-picker,
    .fb-like-bar:focus-within .fb-reaction-picker {
      animation: none;
    }
    .fb-like-bar:hover .fb-reaction-item,
    .fb-like-bar:focus-within .fb-reaction-item {
      animation: none;
    }
    .fb-reaction-item {
      transition: none;
    }
  }

  /* ===== REACTIONS MODAL ===== */
  .fb-reactions-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
  }

  .fb-reactions-overlay.is-open {
    display: flex;
  }

  .fb-reactions-modal {
    width: min(560px, calc(100vw - 24px));
    max-height: min(640px, calc(100vh - 24px));
    background: var(--fb-card);
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: var(--app-card-shadow);
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .fb-reactions-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    font-weight: 800;
  }

  .fb-reactions-close {
    width: 36px;
    height: 36px;
    border: 0;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: var(--fb-text);
    cursor: pointer;
    font-size: 20px;
    line-height: 1;
  }

  .fb-reactions-body {
    padding: 10px 14px;
    overflow: auto;
  }

  .fb-reactions-summary {
    color: var(--fb-muted);
    font-size: 13px;
    margin-bottom: 10px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }

  .fb-reaction-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.06);
  }

  .fb-reactions-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .fb-reactions-item {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .fb-reactions-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    flex: 0 0 auto;
  }

  .fb-reactions-name {
    font-weight: 800;
  }

  .fb-reactions-right {
    margin-left: auto;
    font-size: 18px;
    line-height: 1;
  }

  .fb-empty-feed {
    width: 100%;
    background: var(--fb-card);
    border-radius: 16px;
    padding: 14px;
    text-align: center;
    color: var(--fb-muted);
  }

  /* ===== RIGHT SIDEBAR ===== */
  .fb-sidebar-right {
    padding: 16px 12px;
  }

  .fb-right-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 8px;
  }

  .fb-right-divider {
    height: 1px;
    background: #3e4042;
    margin: 12px 0;
  }

  .fb-right-birthday-link {
    display: block;
    padding: 6px 8px;
    border-radius: 8px;
    color: inherit;
    text-decoration: none;
    cursor: pointer;
  }

  .fb-right-birthday-link:hover {
    background: var(--fb-hover);
  }

  .fb-right-birthday-link:focus-visible {
    outline: 2px solid #0866ff;
    outline-offset: 2px;
  }

  /* ===== CONTACT ===== */
  .fb-contact-item {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    border-radius: 8px;
    cursor: pointer;
  }

  /* Active contact (like Facebook highlight) */
  .fb-contact-item.is-active {
    background: rgba(255, 255, 255, 0.06);
    outline: 2px solid #0866ff;
    outline-offset: 0;
  }

  .fb-contact-item:hover {
    background: var(--fb-hover);
  }

  .fb-contact-avatar-wrap {
    position: relative;
    width: 36px;
    height: 36px;
    flex: 0 0 auto;
  }

  .fb-contact-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
  }

  .fb-contact-dot {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #31A24C;
    border: 2px solid var(--fb-card);
    display: none;
  }

  .fb-contact-item.is-online .fb-contact-dot {
    display: block;
  }

  .fb-contact-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
  }

  .fb-contact-last {
    font-size: 12px;
    font-weight: 600;
    color: #31A24C;
    line-height: 1.1;
  }

  /* ===== CHAT POPUP (Messenger-like) ===== */
  .fb-chat-pop {
    position: fixed;
    /* overlay near the contacts column like Facebook */
    right: 16px;
    bottom: 16px;
    width: 340px;
    max-width: calc(100vw - 32px);
    height: 460px;
    max-height: calc(100vh - 32px);
    background: var(--fb-card);
    color: var(--fb-text);
    border-radius: 12px;
    box-shadow: var(--app-card-shadow);
    border: 1px solid rgba(255, 255, 255, 0.08);
    overflow: hidden;
    display: none;
    flex-direction: column;
    z-index: 12000;
  }

  /* keep on-screen on small widths */
  @media (max-width: 420px) {
    .fb-chat-pop {
      width: calc(100vw - 32px);
    }
  }

  .fb-chat-pop.is-open {
    display: flex;
  }

  .fb-chat-pop.is-min {
    height: 54px;
  }

  .fb-chat-pop.is-min .fb-chat-body,
  .fb-chat-pop.is-min .fb-chat-foot {
    display: none;
  }

  .fb-chat-head {
    height: 54px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  }

  .fb-chat-head-avatar-wrap {
    position: relative;
    width: 34px;
    height: 34px;
    flex: 0 0 auto;
  }

  .fb-chat-head-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
  }

  .fb-chat-head-dot {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #31A24C;
    border: 2px solid var(--fb-card);
    display: none;
  }

  .fb-chat-head-dot.is-online {
    display: block;
  }

  .fb-chat-head-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
  }

  .fb-chat-head-name-row {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
  }

  .fb-chat-head-name {
    font-weight: 800;
    font-size: 14px;
    line-height: 1.15;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .fb-chat-head-caret {
    opacity: 0.75;
    font-size: 14px;
    line-height: 1;
    transform: translateY(-1px);
    user-select: none;
  }

  .fb-chat-head-sub {
    font-size: 12px;
    color: var(--fb-muted);
    line-height: 1.1;
  }

  .fb-chat-head-actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .fb-chat-head-link {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
  }

  .fb-chat-head-link:hover {
    text-decoration: none;
  }

  .fb-chat-head-settings {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    color: var(--fb-text);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .fb-chat-head-settings:hover {
    background: var(--fb-hover);
  }

  .fb-chat-settings-menu {
    position: fixed;
    top: 0;
    left: 0;
    width: 310px;
    max-height: 385px;
    overflow-y: auto;
    overflow-x: hidden;
    background: var(--fb-card);
    color: var(--fb-text);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: var(--app-card-shadow);
    z-index: 20000;
    padding: 8px 0;

    /* Hide scrollbar until hover (Messenger-like) */
    scrollbar-width: thin;                 /* Firefox */
    scrollbar-color: transparent transparent;
  }

  .fb-chat-settings-menu:hover {
    scrollbar-color: rgba(255, 255, 255, 0.25) transparent;
  }

  .fb-chat-settings-menu::-webkit-scrollbar {
    width: 10px;
  }

  .fb-chat-settings-menu::-webkit-scrollbar-track {
    background: transparent;
  }

  .fb-chat-settings-menu::-webkit-scrollbar-thumb {
    background-color: transparent;
    border-radius: 999px;
    border: 3px solid transparent;
    background-clip: content-box;
  }

  .fb-chat-settings-menu:hover::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.25);
  }

  .fb-chat-settings-menu::before {
    content: '';
    position: absolute;
    top: -6px;
    left: var(--fb-chat-menu-arrow-left, 260px);
    width: 12px;
    height: 12px;
    background: var(--fb-card);
    transform: rotate(45deg);
    border-left: 1px solid rgba(255, 255, 255, 0.08);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
  }

  .fb-chat-settings-menu[hidden] {
    display: none;
  }

  .fb-chat-menu-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: transparent;
    border: 0;
    color: inherit;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    text-align: left;
  }

  .fb-chat-menu-item:hover {
    background: var(--fb-hover);
  }

  .fb-chat-menu-item:disabled {
    opacity: 0.55;
    cursor: not-allowed;
  }

  .fb-chat-menu-ico {
    width: 20px;
    height: 20px;
    color: var(--fb-muted);
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }

  .fb-chat-menu-ico.is-accent {
    color: var(--fb-blue);
  }

  .fb-chat-menu-right {
    margin-left: auto;
    font-size: 12px;
    color: var(--fb-muted);
    font-weight: 800;
  }

  .fb-chat-menu-chevron {
    margin-left: 6px;
    width: 16px;
    height: 16px;
    color: var(--fb-muted);
    flex: 0 0 auto;
  }

  .fb-chat-menu-sep {
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
    margin: 8px 0;
  }

  .fb-chat-ico-btn {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    color: var(--fb-text);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 1;
  }

  .fb-chat-ico-btn.is-accent {
    color: var(--fb-blue);
  }

  .fb-chat-ico-btn:hover {
    background: var(--fb-hover);
  }

  .fb-chat-body {
    flex: 1;
    overflow: auto;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: rgba(255, 255, 255, 0.02);

    /* Scrollbar: subtle (avoid bright white) */
    scrollbar-width: thin; /* Firefox */
    scrollbar-color: rgba(255, 255, 255, 0.16) transparent;
  }

  .fb-chat-body::-webkit-scrollbar {
    width: 10px;
  }

  .fb-chat-body::-webkit-scrollbar-track {
    background: transparent;
  }

  .fb-chat-body::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.16);
    border-radius: 999px;
    border: 3px solid transparent;
    background-clip: content-box;
  }

  .fb-chat-body:hover {
    scrollbar-color: rgba(255, 255, 255, 0.24) transparent;
  }

  .fb-chat-body:hover::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.24);
  }

  .fb-chat-call-layer {
    position: absolute;
    left: 0;
    right: 0;
    top: 54px;
    bottom: 0;
    background: var(--fb-card);
    z-index: 15000;
    display: flex;
    flex-direction: column;
  }

  .fb-chat-call-layer[hidden] {
    display: none;
  }

  .fb-chat-call-remote {
    flex: 1;
    width: 100%;
    background: rgba(255, 255, 255, 0.02);
    object-fit: cover;
  }

  .fb-chat-call-local {
    position: absolute;
    right: 10px;
    bottom: 10px;
    width: 110px;
    height: 150px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.02);
    object-fit: cover;
    border: 1px solid rgba(255, 255, 255, 0.08);
  }

  .fb-call-screen {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    gap: 6px;
    padding: 18px 12px 70px;
    pointer-events: none;
  }

  .fb-call-screen.is-video {
    justify-content: flex-start;
    padding-top: 18px;
  }

  .fb-call-avatar {
    width: 92px;
    height: 92px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.02);
  }

  .fb-call-name {
    font-weight: 900;
    font-size: 22px;
    line-height: 1.2;
    color: var(--fb-text);
    padding: 0 10px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
  }

  .fb-call-status {
    font-size: 13px;
    color: var(--fb-muted);
    line-height: 1.2;
  }

  .fb-call-controls {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    pointer-events: auto;
  }

  .fb-call-btn {
    width: 44px;
    height: 44px;
    border-radius: 999px;
    border: 0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--fb-hover);
    color: var(--fb-text);
  }

  .fb-call-btn:hover {
    filter: brightness(1.05);
  }

  .fb-call-btn.is-primary {
    background: var(--fb-blue);
    color: #fff;
  }

  .fb-call-btn.is-on {
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.18) inset;
  }

  .fb-call-btn[hidden] {
    display: none;
  }

  .fb-chat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 20px 16px;
    color: var(--fb-muted);
    gap: 10px;
  }

  .fb-chat-empty-avatar-wrap {
    position: relative;
    width: 92px;
    height: 92px;
  }

  .fb-chat-empty-avatar {
    width: 92px;
    height: 92px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
  }

  .fb-chat-empty-dot {
    position: absolute;
    right: 8px;
    bottom: 8px;
    width: 14px;
    height: 14px;
    border-radius: 999px;
    background: #31A24C;
    border: 3px solid var(--fb-card);
    display: none;
  }

  .fb-chat-empty-dot.is-online {
    display: block;
  }

  .fb-chat-empty-name {
    font-weight: 900;
    font-size: 22px;
    line-height: 1.2;
    color: var(--fb-text);
  }

  .fb-chat-empty-lock {
    font-size: 13.5px;
    line-height: 1.4;
  }

  .fb-chat-empty-lock a {
    color: #0866ff;
    text-decoration: none;
    font-weight: 700;
  }

  .fb-chat-empty-lock a:hover {
    text-decoration: underline;
  }

  .fb-chat-msg {
    max-width: 78%;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .fb-chat-msg.is-me {
    align-self: flex-end;
    align-items: flex-end;
  }

  .fb-chat-msg.is-them {
    align-self: flex-start;
    align-items: flex-start;
  }

  .fb-chat-bubble {
    padding: 8px 12px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.35;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .fb-chat-msg.is-me .fb-chat-bubble {
    background: #0866ff;
    color: #fff;
    border-bottom-right-radius: 6px;
  }

  .fb-chat-msg.is-them .fb-chat-bubble {
    background: var(--fb-input);
    color: var(--fb-text);
    border-bottom-left-radius: 6px;
  }

  .fb-chat-time-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px 0;
    color: var(--fb-muted);
    font-size: 12px;
    line-height: 1;
    user-select: none;
  }

  .fb-chat-foot {
    padding: 6px 8px 10px;
    border-top: 0;
    display: flex;
    align-items: flex-end;
    gap: 6px;
  }

  /* Facebook-like: collapse 4 tools into a + button when composing multi-line */
  #fbChatToolPlus {
    display: none;
  }

  .fb-chat-foot.is-tools-collapsed #fbChatToolPlus {
    display: inline-flex;
    background: var(--fb-input);
  }

  .fb-chat-foot.is-tools-collapsed #fbChatToolMic,
  .fb-chat-foot.is-tools-collapsed #fbChatToolPhoto,
  .fb-chat-foot.is-tools-collapsed #fbChatToolSticker,
  .fb-chat-foot.is-tools-collapsed #fbChatToolGif {
    display: none;
  }

  .fb-chat-tool {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    color: var(--fb-blue);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    line-height: 1;
    flex: 0 0 auto;
  }

  .fb-chat-tool svg {
    width: 20px;
    height: 20px;
    display: block;
  }

  .fb-chat-tool.is-recording {
    background: var(--fb-blue);
    color: #fff;
  }

  .fb-chat-tool:hover {
    background: var(--fb-hover);
  }

  #fbChatToolGif {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .4px;
  }

  .fb-chat-input-wrap {
    flex: 1;
    min-width: 0;
    min-height: 36px;
    height: auto;
    border-radius: 18px;
    background: var(--fb-input);
    display: flex;
    align-items: flex-end;
    padding: 6px 8px 6px 12px;
    gap: 6px;
  }

  .fb-chat-input {
    flex: 1;
    min-width: 0;
    min-height: 18px;
    max-height: 120px;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: var(--fb-text);
    padding: 4px 0;
    outline: none;
    font-size: 14px;
    line-height: 1.25;
    white-space: pre-wrap;
    word-break: break-word;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge legacy */
  }

  .fb-chat-input::-webkit-scrollbar {
    width: 0;
    height: 0;
  }

  .fb-chat-input[contenteditable="true"]:empty::before {
    content: attr(data-placeholder);
    color: var(--fb-muted);
  }

  .fb-chat-input-ico {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    color: var(--fb-blue);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .fb-chat-input-ico:hover {
    background: var(--fb-hover);
  }

  .fb-chat-input-ico svg {
    width: 20px;
    height: 20px;
    display: block;
  }

  .fb-chat-like,
  .fb-chat-send {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    color: var(--fb-blue);
    cursor: pointer;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }

  .fb-chat-like svg,
  .fb-chat-send svg {
    width: 20px;
    height: 20px;
    display: block;
  }

  .fb-chat-like:hover,
  .fb-chat-send:hover {
    background: var(--fb-hover);
  }

  .fb-chat-send[hidden] {
    display: none;
  }

  .fb-chat-like[hidden] {
    display: none;
  }

  .fb-chat-send:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  /* ===== NAME + VERIFIED ===== */
  .fb-contact-name {
    font-size: 14px;
    font-weight: 500;
    display: inline-flex;
    align-items: baseline;
    gap: 6px;
    line-height: 1.2;
  }

  .fb-verified {
    display: inline-flex;
    align-items: center;
  }

  .fb-verified svg {
    width: 12px;
    height: 12px;
    margin-top: -1px;
    /* FIX CHUẨN FACEBOOK */
    color: #1877f2;
  }

  .fb-footer {
    margin-top: 16px;
    padding: 0 12px;
    font-size: 12px;
    color: var(--fb-muted);
  }

  .fb-footer-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
  }

  .fb-footer-list li {
    display: flex;
    align-items: center;
  }

  .fb-footer-list a,
  .fb-footer-more button {
    color: var(--fb-muted);
    text-decoration: none;
    background: none;
    border: none;
    padding: 0;
    font-size: 12px;
    cursor: pointer;
  }

  .fb-footer-list a:hover,
  .fb-footer-more button:hover {
    text-decoration: underline;
  }

  .fb-footer-list span {
    margin: 0 4px;
    pointer-events: none;
  }

  /* ICON “LỰA CHỌN QUẢNG CÁO” */
  .fb-ad-icon {
    width: 12px;
    height: 12px;
    display: inline-block;
    margin-left: 4px;

    background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/ys/r/WiDJ-s7tLiG.png");
    background-repeat: no-repeat;
    background-position: -17px -867px;

    /* Facebook-style color */
    filter: invert(62%) sepia(6%) saturate(320%) hue-rotate(174deg) brightness(92%) contrast(88%);
  }

  .fb-more-btn {
    cursor: pointer;
  }

  .fb-more-btn:hover {
    text-decoration: underline;
  }

  /* POPUP */
  .fb-more-popup {
    position: fixed;
    bottom: 70px;
    left: 24px;

    width: 220px;
    background: #242526;
    border-radius: 12px;
    padding: 8px;

    display: none;
    flex-direction: column;
    gap: 4px;

    box-shadow: 0 12px 28px rgba(0, 0, 0, .6);
    z-index: 9999;
  }

  /* ITEM */
  .fb-more-item {
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 14px;
    color: #e4e6eb;
    cursor: pointer;
  }

  .fb-more-item:hover {
    background: #3a3b3c;
  }

  /* =====================
       Loading overlay styles (merged)
       ===================== */
  #fbLoadingOverlay {
    position: fixed;
    inset: 0;
    z-index: 5000;

    display: flex;
    /* LUÔN flex */
    align-items: center;
    justify-content: center;

    background: none;
    /* Không làm mờ hoặc tối nền */
    opacity: 0;
    pointer-events: none;
    transition: opacity .25s ease;
  }

  #fbLoadingOverlay.show {
    opacity: 1;
    pointer-events: auto;
  }

  .fb-loading-center {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--app-surface-bg, #242526);
    border-radius: 50%;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.18);
    position: absolute;
    left: 50%;
    top: 10vh;
    transform: translate(-50%, -40px);
    opacity: 0;
    transition: transform 0.35s cubic-bezier(.4, 1.6, .4, 1), opacity 0.25s;
  }

  #fbLoadingOverlay.show .fb-loading-center {
    transform: translate(-50%, 0);
    opacity: 1;
    transition: transform 0.35s cubic-bezier(.4, 1.6, .4, 1), opacity 0.25s;
  }

  #fbLoadingOverlay:not(.show) .fb-loading-center {
    transform: translate(-50%, -40px);
    opacity: 0;
    transition: transform 0.35s cubic-bezier(.4, 0, .2, 1), opacity 0.25s;
  }

  .fb-spinner {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    border: 4px solid #242526;
    border-top: 4px solid #0866ff;
    animation: fbspin 1s linear infinite;
    box-sizing: border-box;
    background: none;
  }

  @keyframes fbspin {
    0% {
      transform: rotate(0deg);
    }

    100% {
      transform: rotate(360deg);
    }
  }
</style>

<body>

  <div class="fb-layout">

    <!-- LEFT -->
    <aside class="fb-sidebar-left">
      <ul class="fb-menu">
        <li class="fb-menu-item">
          <span class="fb-menu-icon">
            <img src="<?= fb_escape($currentUserAvatar) ?>" alt="<?= $currentUserNameSafe ?>">
          </span>
          <span class="fb-menu-text"><?= $currentUserNameSafe ?></span>
        </li>

        <li class="fb-menu-item">
          <a href="https://www.meta.ai/?utm_source=facebook_bookmarks&fbclid=IwY2xjawOwWt9leHRuA2FlbQIxMABicmlkETFNMGFzSVMyeUk5Z2FNbnlNc3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHofxUTQQUKZ86CwKVH5vuwflefI_8UV9u3zklq0PO76CBymn4vuQVuNTszws_aem_7SzLCyb9SNowu1RO8S9DbA" class="fb-menu-link" target="_blank" rel="noopener noreferrer">
            <span class="fb-menu-icon">
              <img src="https://www.facebook.com/images/web_messenger/gen-ai-ring-2_36-4x.png" alt="Meta AI">
            </span>
            <span class="fb-menu-text">Meta AI</span>
          </a>
        </li>

        <li class="fb-menu-item">
          <span class="fb-menu-icon">
            <div class="fb-friends-icon"><i></i></div>
          </span>
          <span class="fb-menu-text">Bạn bè</span>
        </li>
        <li class="fb-menu-item">
          <span class="fb-menu-icon">
            <div class="fb-icon fb-icon-memories">
              <i></i>
            </div>
          </span>
          <span class="fb-menu-text">Kỷ niệm</span>
        </li>
        <li class="fb-menu-item">
          <span class="fb-menu-icon">
            <div class="fb-icon fb-icon-saved">
              <i></i>
            </div>
          </span>
          <span class="fb-menu-text">Đã lưu</span>
        </li>
        <li class="fb-menu-item">
          <span class="fb-menu-icon">
            <div class="fb-icon fb-icon-groups">
              <i></i>
            </div>
          </span>
     
          <span class="fb-menu-text">Nhóm</span>
        </li>

        <li class="fb-menu-item">
          <span class="fb-menu-icon">
            <div class="fb-icon fb-icon-video">
              <i></i>
            </div>
          </span>
          <span class="fb-menu-text">Video</span>
        </li>
        <li class="fb-menu-item">
          <span class="fb-menu-icon fb-icon fb-icon-marketplace">
            <i></i>
          </span>
          <span class="fb-menu-text">Marketplace</span>
        </li>
        <li class="fb-menu-item">
          <span class="fb-menu-icon">
            <img
              src="https://static.xx.fbcdn.net/rsrc.php/v4/yb/r/eECk3ceTaHJ.png"
              alt="Bảng feed"
              width="36"
              height="36"
              draggable="false">
          </span>
          <span class="fb-menu-text">Bảng feed</span>
        </li>

        <li class="fb-menu-item">
          <span class="fb-menu-icon">
            <div class="fb-icon fb-icon-events">
              <i></i>
            </div>
          </span>
          <span class="fb-menu-text">Sự kiện</span>
        </li>
        <li class="fb-menu-item">
          <span class="fb-menu-icon">
            <div class="fb-icon fb-icon-ads">
              <i></i>
            </div>
          </span>
          <span class="fb-menu-text">Trình quản lý quảng cáo</span>
        </li>
        <!-- XEM THÊM -->
        <div class="fb-see-more" role="button" tabindex="0">
          <div class="fb-see-more-inner">
            <span class="fb-see-more-icon">
              <svg viewBox="0 0 16 16" width="20" height="20" aria-hidden="true">
                <path d="M2.293 5.293a1 1 0 0 1 1.414 0L8 9.586l4.293-4.293a1 1 0 1 1 1.414 1.414l-5 5a1 1 0 0 1-1.414 0l-5-5a1 1 0 0 1 0-1.414z" />
              </svg>
            </span>
            <span class="fb-see-more-text">Xem thêm</span>
          </div>
        </div>
      </ul>

      <div class="fb-footer">
        <ul class="fb-footer-list">
          <li><a href="#">Quyền riêng tư</a><span>·</span></li>
          <li><a href="#">Điều khoản</a><span>·</span></li>
          <li><a href="#">Quảng cáo</a><span>·</span></li>
          <li>
            <a href="#">Lựa chọn quảng cáo</a>
            <span class="fb-ad-icon"></span>
            <span>·</span>
          </li>
          <li><a href="#">Cookie</a><span>·</span></li>
          <li class="fb-footer-more">
            <button id="fbFooterMore">Xem thêm</button>
          </li>
        </ul>
      </div>

      <!-- POPUP -->
      <div class="fb-more-popup" id="fbMorePopup">
        <div class="fb-more-item">Giới thiệu</div>
        <div class="fb-more-item">Nghề nghiệp</div>
        <div class="fb-more-item">Nhà phát triển</div>
        <div class="fb-more-item">Trợ giúp</div>
      </div>
    </aside>

    <!-- CENTER -->
    <main class="fb-content">
      <div class="fb-feed">
        <div class="fb-status-bar">
          <div class="fb-status-inner">
            <img class="fb-status-avatar" src="<?= fb_escape($currentUserAvatar) ?>" alt="<?= $currentUserNameSafe ?>">
            <input class="fb-status-input" id="fbStatusInput" readonly aria-haspopup="dialog" placeholder="<?= $firstNameSafe ?> ơi, bạn đang nghĩ gì thế?">
            <div class="fb-status-actions">
              <button type="button" class="fb-status-icon">
                <img src="https://static.xx.fbcdn.net/rsrc.php/v4/yr/r/c0dWho49-X3.png"
                  alt="Video"
                  width="24"
                  height="24">
              </button>
              <button type="button" class="fb-status-icon">
                <img
                  src="https://static.xx.fbcdn.net/rsrc.php/v4/y7/r/Ivw7nhRtXyo.png"
                  alt="Photo"
                  width="24"
                  height="24">
              </button>
              <button type="button" class="fb-status-icon">
                <img
                  src="https://static.xx.fbcdn.net/rsrc.php/v4/yd/r/Y4mYLVOhTwq.png"
                  alt="Feeling"
                  width="24"
                  height="24">
              </button>
            </div>
          </div>
        </div>

        <div class="fb-story-create">
          <div class="fb-story-create-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
              <path fill="currentColor" d="M11 11V6a1 1 0 1 1 2 0v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5z"></path>
            </svg>
          </div>
          <div>
            <div style="font-weight:600">Tạo tin</div>
            <div style="font-size:12.5px;color:var(--app-muted)">
              Bạn có thể chia sẻ ảnh hoặc viết gì đó.
            </div>
          </div>
        </div>

        <?php if (!empty($feedPosts)) : ?>
          <?php foreach ($feedPosts as $post) :
            $postUserNameSafe = fb_escape((string)($post['user_name'] ?? ''));
            $postUserAvatar = fb_avatar_url($post['user_avatar'] ?? null);
            $postCreatedAt = (string)($post['created_at'] ?? '');
            $postTime = fb_time_ago($postCreatedAt ?: null);
            $postContent = (string)($post['content'] ?? '');
            $postImageUrls = fb_post_media_urls($post['image'] ?? null);
            $postId = (int)($post['id'] ?? 0);
            $postOwnerId = (int)($post['user_id'] ?? 0);
            $likeCount = (int)($post['like_count'] ?? 0);
            $commentCount = (int)($post['comment_count'] ?? 0);
            $myReaction = fb_reaction_key($post['my_reaction'] ?? '');
            $isLiked = $myReaction !== '' || !empty($post['is_liked']);
            $myReactionLabel = fb_reaction_label($myReaction);
            $myReactionEmoji = fb_reaction_emoji($myReaction);
          ?>
            <article class="fb-post-card" id="post-<?= $postId ?>" data-post-id="<?= $postId ?>" data-owner-id="<?= $postOwnerId ?>" data-my-reaction="<?= fb_escape($myReaction) ?>" data-created-at="<?= fb_escape($postCreatedAt) ?>">
              <div class="fb-post-header">
                <img class="fb-post-avatar" src="<?= fb_escape($postUserAvatar) ?>" alt="<?= $postUserNameSafe ?>">
                <div class="fb-post-meta">
                  <div class="fb-post-name"><?= $postUserNameSafe ?></div>
                  <div class="fb-post-time">
                    <span class="js-time-ago" data-time="<?= fb_escape($postCreatedAt) ?>"><?= fb_escape($postTime) ?></span> ·

                    <div class="fb-audience-wrap">
                      <div class="fb-audience-icon-wrap" aria-hidden="false">
                        <img
                          class="fb-audience-icon"
                          src="https://static.xx.fbcdn.net/rsrc.php/v4/y5/r/qop9rFQ_Ys1.png"
                          alt="Công khai"
                          width="12"
                          height="12">
                      </div>
                      <div class="fb-audience-spacer"></div>
                    </div>
                  </div>
                </div>
                <button type="button" class="fb-post-more js-post-more" aria-label="Tùy chọn">…</button>
              </div>

              <?php if (trim($postContent) !== '') : ?>
                <div class="fb-post-content"><?= nl2br(fb_escape($postContent), false) ?></div>
              <?php endif; ?>

              <?php if (!empty($postImageUrls)) : ?>
                <?php if (count($postImageUrls) === 1) : ?>
                  <img class="fb-post-image" src="<?= fb_escape($postImageUrls[0]) ?>" alt="Ảnh bài viết">
                <?php else : ?>
                  <div class="fb-post-media-grid" data-count="<?= (int)count($postImageUrls) ?>">
                    <?php foreach ($postImageUrls as $u) : ?>
                      <img class="fb-post-image" src="<?= fb_escape($u) ?>" alt="Ảnh bài viết">
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              <?php endif; ?>

              <div class="fb-post-stats" aria-label="Thống kê">
                <span class="fb-post-stat-link js-like-count" data-value="<?= $likeCount ?>"><?= $likeCount ?> lượt thích</span>
                <span class="fb-post-stat-link js-comment-count" data-value="<?= $commentCount ?>"><?= $commentCount ?> bình luận</span>
              </div>

              <div class="fb-post-actions" role="group" aria-label="Tương tác">
                <div class="fb-like-bar" data-post-id="<?= $postId ?>">
                  <button type="button" class="fb-post-action-btn fb-like-btn js-like-btn<?= $isLiked ? ' is-liked' : '' ?>" aria-pressed="<?= $isLiked ? 'true' : 'false' ?>" data-reaction="<?= fb_escape($myReaction) ?>">
                    <span class="fb-like-emoji" aria-hidden="true"><?= fb_escape($myReactionEmoji) ?></span>
                    <span class="fb-like-text"><?= fb_escape($myReactionLabel) ?></span>
                  </button>

                  <div class="fb-reaction-picker" role="menu" aria-label="Chọn cảm xúc">
                    <button type="button" class="fb-reaction-item" data-reaction="like" aria-label="Thích">👍</button>
                    <button type="button" class="fb-reaction-item" data-reaction="love" aria-label="Yêu thích">❤️</button>
                    <button type="button" class="fb-reaction-item" data-reaction="haha" aria-label="Haha">😂</button>
                    <button type="button" class="fb-reaction-item" data-reaction="wow" aria-label="Wow">😮</button>
                    <button type="button" class="fb-reaction-item" data-reaction="care" aria-label="Thương thương">🥰</button>
                    <button type="button" class="fb-reaction-item" data-reaction="sad" aria-label="Buồn">😢</button>
                    <button type="button" class="fb-reaction-item" data-reaction="angry" aria-label="Phẫn nộ">😡</button>
                  </div>
                </div>
               <button type="button" class="fb-post-action-btn js-comment-btn" aria-label="Bình luận">
  <span class="fb-post-action-ico" aria-hidden="true">
    <i class="fb-post-sprite fb-post-sprite-comment"></i>
  </span>
  <span class="fb-post-action-text">Bình luận</span>
</button>
               <button type="button" class="fb-post-action-btn js-share-btn" aria-label="Chia sẻ">
  <span class="fb-post-action-ico" aria-hidden="true">
    <i class="fb-post-sprite fb-post-sprite-share"></i>
  </span>
  <span class="fb-post-action-text">Chia sẻ</span>
</button>
              </div>

              <div class="fb-post-comments" aria-label="Bình luận">
                <div class="fb-comment-list"></div>
                <form class="fb-comment-form" autocomplete="off">
                  <input class="fb-comment-input" name="content" type="text" placeholder="Viết bình luận...">
                  <button class="fb-comment-submit" type="submit" disabled>Gửi</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else : ?>
          <div class="fb-empty-feed">Chưa có bài viết nào.</div>
        <?php endif; ?>
      </div>
    </main>

    <!-- RIGHT -->
    <aside class="fb-sidebar-right">
      <div class="fb-right-title">Sinh nhật</div>
      <a class="fb-right-birthday-link" href="../pages/marketplace.php" aria-label="Mở Marketplace">
        🎁 Bài tập các tuần của <b>Thầy Nghĩa</b>.
      </a>

      <div class="fb-right-divider"></div>

      <div class="fb-right-title">Người liên hệ</div>

      <div class="fb-contact-item">
        <span class="fb-menu-icon">
          <img src="https://www.facebook.com/images/web_messenger/gen-ai-ring-2_36-4x.png" alt="Meta AI">
        </span>

        <span class="fb-contact-name">
          Meta AI
          <span class="fb-verified">
            <!-- VERIFIED BADGE FACEBOOK REAL -->
            <svg viewBox="0 0 12 13" fill="currentColor" aria-hidden="true">
              <g fill-rule="evenodd" transform="translate(-98 -917)">
                <path d="m106.853 922.354-3.5 3.5a.499.499 0 0 1-.706 0l-1.5-1.5a.5.5 0 1 1 .706-.708l1.147 1.147 3.147-3.147a.5.5 0 1 1 .706.708m3.078 2.295-.589-1.149.588-1.15a.633.633 0 0 0-.219-.82l-1.085-.7-.065-1.287a.627.627 0 0 0-.6-.603l-1.29-.066-.703-1.087a.636.636 0 0 0-.82-.217l-1.148.588-1.15-.588a.631.631 0 0 0-.82.22l-.701 1.085-1.289.065a.626.626 0 0 0-.6.6l-.066 1.29-1.088.702a.634.634 0 0 0-.216.82l.588 1.149-.588 1.15a.632.632 0 0 0 .219.819l1.085.701.065 1.286c.014.33.274.59.6.604l1.29.065.703 1.088c.177.27.53.362.82.216l1.148-.588 1.15.589a.629.629 0 0 0 .82-.22l.701-1.085 1.286-.064a.627.627 0 0 0 .604-.601l.065-1.29 1.088-.703a.633.633 0 0 0 .216-.819"></path>
              </g>
            </svg>
          </span>
        </span>
      </div>

      <div id="fbContactsList" aria-label="Danh sách người liên hệ"></div>
    </aside>
  </div>

  <!-- Chat popup (Messenger-like) -->
  <div class="fb-chat-pop" id="fbChatPop" aria-hidden="true">
    <div class="fb-chat-head">
      <div class="fb-chat-head-avatar-wrap">
        <img class="fb-chat-head-avatar" id="fbChatAvatar" alt="" src="">
        <span class="fb-chat-head-dot" id="fbChatDot" aria-hidden="true"></span>
      </div>
      <div class="fb-chat-head-meta">
        <div class="fb-chat-head-name-row">
          <a class="fb-chat-head-link" id="fbChatProfileLink" href="javascript:void(0)" role="link" tabindex="0" aria-label="Trang cá nhân">
            <div class="fb-chat-head-name" id="fbChatName"></div>
          </a>
          <button type="button" class="fb-chat-head-settings" id="fbChatSettings" aria-label="Cài đặt đoạn chat">
            <svg aria-hidden="true" width="10" height="10" viewBox="0 0 18 10" fill="currentColor">
              <path d="M1 2.414A1 1 0 0 1 2.414 1L8.293 6.88a1 1 0 0 0 1.414 0L15.586 1A1 1 0 0 1 17 2.414L9.707 9.707a1 1 0 0 1-1.414 0L1 2.414z" fill-rule="evenodd"/>
            </svg>
          </button>
        </div>
        <div class="fb-chat-head-sub" id="fbChatSub"></div>
      </div>
      <div class="fb-chat-head-actions">
        <button type="button" class="fb-chat-ico-btn is-accent" id="fbChatCall" aria-label="Bắt đầu gọi thoại">
          <svg aria-hidden="true" width="16" height="16" viewBox="0 0 12 13" fill="currentColor">
            <g stroke="none" stroke-width="1" fill-rule="evenodd">
              <path d="M109.492 925.682a1.154 1.154 0 0 0-.443-.81 10.642 10.642 0 0 0-1.158-.776l-.211-.125c-.487-.29-.872-.514-1.257-.511a3.618 3.618 0 0 0-.693.084c-.304.07-.6.302-.88.69a3.365 3.365 0 0 0-.297.494l.449.22-.507-.202-.13-.074a8.53 8.53 0 0 1-3.04-3.043l-.071-.124.019-.057v-.001c.168-.083.334-.183.492-.297.162-.117.552-.432.681-.842.063-.2.075-.407.086-.59l.007-.116c.029-.389-.197-.764-.482-1.237l-.153-.256c-.322-.55-.6-.933-.775-1.158a1.155 1.155 0 0 0-.811-.443c-.36-.031-1.066.01-1.748.608-1.018.896-1.326 2.25-.845 3.714a11.734 11.734 0 0 0 2.834 4.612 11.732 11.732 0 0 0 4.61 2.833c.455.149.897.222 1.32.222.94 0 1.777-.364 2.395-1.067.599-.681.639-1.387.608-1.748" transform="translate(-450 -1073) translate(352.5 157)"></path>
            </g>
          </svg>
        </button>
        <button type="button" class="fb-chat-ico-btn is-accent" id="fbChatVideo" aria-label="Bắt đầu gọi video">
          <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
            <path d="M15 8a3 3 0 0 1 3 3v2.3l3.2-2.1A1 1 0 0 1 23 12v6a1 1 0 0 1-1.8.6L18 16.7V17a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V11a3 3 0 0 1 3-3h10z"/>
          </svg>
        </button>
        <button type="button" class="fb-chat-ico-btn" id="fbChatMin" aria-label="Thu nhỏ">
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M5 12.75h14a.75.75 0 0 0 0-1.5H5a.75.75 0 0 0 0 1.5z"/>
          </svg>
        </button>
        <button type="button" class="fb-chat-ico-btn" id="fbChatClose" aria-label="Đóng">
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M18.3 5.71a1 1 0 0 0-1.41 0L12 10.59 7.11 5.7A1 1 0 0 0 5.7 7.11L10.59 12l-4.9 4.89a1 1 0 1 0 1.42 1.42L12 13.41l4.89 4.9a1 1 0 0 0 1.42-1.42L13.41 12l4.9-4.89a1 1 0 0 0-.01-1.4z"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="fb-chat-settings-menu" id="fbChatSettingsMenu" role="menu" aria-label="Cài đặt tab Chat" hidden>
      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="e2ee_info">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 1.667a4.167 4.167 0 0 0-4.167 4.166v1.667H5a1 1 0 0 0-1 1V17a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V8.5a1 1 0 0 0-1-1h-.833V5.833A4.167 4.167 0 0 0 10 1.667Zm2.5 5.833V5.833a2.5 2.5 0 1 0-5 0V7.5h5Z"/></svg>
        </span>
        Được mã hóa đầu cuối
      </button>

      <div class="fb-chat-menu-sep" role="separator"></div>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="open_messenger">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 1.5c-4.694 0-8.5 3.434-8.5 7.667 0 2.175 1.012 4.13 2.638 5.47.18.15.284.372.29.607l.06 1.74a.75.75 0 0 0 1.055.654l1.94-.844a.75.75 0 0 1 .5-.037c.65.18 1.34.277 2.017.277 4.694 0 8.5-3.434 8.5-7.667C18.5 4.934 14.694 1.5 10 1.5Zm.999 10.06-2.55-1.96-2.9 3.77c-.24.312.152.69.46.482l3.06-2.02a.5.5 0 0 1 .55 0l2.55 1.96 2.9-3.77c.24-.312-.152-.69-.46-.482l-3.06 2.02a.5.5 0 0 1-.55 0Z"/></svg>
        </span>
        Mở trong Messenger
      </button>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="view_profile">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 1.667A8.333 8.333 0 1 0 18.333 10 8.343 8.343 0 0 0 10 1.667Zm0 4.166a2.917 2.917 0 1 1 0 5.834 2.917 2.917 0 0 1 0-5.834Zm0 11a6.64 6.64 0 0 1-5.046-2.308 5.833 5.833 0 0 1 10.092 0A6.64 6.64 0 0 1 10 16.833Z"/></svg>
        </span>
        Xem trang cá nhân
      </button>

      <div class="fb-chat-menu-sep" role="separator"></div>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="theme">
        <span class="fb-chat-menu-ico is-accent" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 1.667A8.333 8.333 0 1 0 18.333 10 8.343 8.343 0 0 0 10 1.667Zm0 3.333a5 5 0 0 1 0 10 5 5 0 0 1 0-10Zm0 2.167a2.833 2.833 0 1 0 0 5.666 2.833 2.833 0 0 0 0-5.666Z"/></svg>
        </span>
        Đổi chủ đề
      </button>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="emoji_prefs">
        <span class="fb-chat-menu-ico is-accent" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 1.667A8.333 8.333 0 1 0 18.333 10 8.343 8.343 0 0 0 10 1.667Zm-2.5 6.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM6.8 12.2a.75.75 0 0 1 1.06 0A3.03 3.03 0 0 0 10 13.083c.8 0 1.56-.31 2.14-.883a.75.75 0 1 1 1.06 1.06A4.52 4.52 0 0 1 10 14.583a4.52 4.52 0 0 1-3.2-1.323.75.75 0 0 1 0-1.06Z"/></svg>
        </span>
        Biểu tượng cảm xúc
      </button>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="nickname">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M14.343 2.936a1.5 1.5 0 0 1 2.121 2.121l-1.06 1.061-2.12-2.121 1.059-1.061ZM12.224 5.057 4.5 12.782V15.5h2.718l7.725-7.725-2.719-2.718Z"/></svg>
        </span>
        Biệt danh
      </button>

      <div class="fb-chat-menu-sep" role="separator"></div>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="create_group">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M7.5 10.833a2.917 2.917 0 1 1 0-5.833 2.917 2.917 0 0 1 0 5.833Zm6.25-1.666a2.5 2.5 0 1 0-1.667-4.356 4.47 4.47 0 0 1 0 4.356A2.49 2.49 0 0 0 13.75 9.167ZM2.917 16.667c.083-2.727 2.36-4.834 5.083-4.834h-1c2.723 0 5 2.107 5.083 4.834h-9.166Zm10.25 0c-.055-1.425-.71-2.718-1.75-3.606.46-.151.955-.227 1.467-.227 2.29 0 4.159 1.69 4.233 3.833h-3.95Z"/></svg>
        </span>
        Tạo nhóm
      </button>

      <div class="fb-chat-menu-sep" role="separator"></div>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="toggle_mute">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10.8 3.7a.75.75 0 0 1 .783.083l2.834 2.217H17a1 1 0 0 1 1 1v5.999a1 1 0 0 1-1 1h-2.583l-2.834 2.217A.75.75 0 0 1 10.8 16.5H9a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1h1.8ZM4 7.5a.75.75 0 0 1 .75-.75H6.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 7.5Zm0 5a.75.75 0 0 1 .75-.75H6.5a.75.75 0 0 1 0 1.5H4.75A.75.75 0 0 1 4 12.5Z"/></svg>
        </span>
        Tắt thông báo
        <span class="fb-chat-menu-right" id="fbChatMenuMuteState">Tắt</span>
      </button>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="block">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 1.667A8.333 8.333 0 1 0 18.333 10 8.343 8.343 0 0 0 10 1.667Zm0 1.5a6.8 6.8 0 0 1 4.66 1.84L5.007 14.66A6.833 6.833 0 0 1 10 3.167Zm0 13.666a6.8 6.8 0 0 1-4.66-1.84L14.993 5.34A6.833 6.833 0 0 1 10 16.833Z"/></svg>
        </span>
        Chặn
      </button>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="restrict">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 2.5a5 5 0 0 0-5 5V9H4a1 1 0 0 0-1 1v6.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10a1 1 0 0 0-1-1h-1V7.5a5 5 0 0 0-5-5Zm3.5 6.5V7.5a3.5 3.5 0 1 0-7 0V9h7Z"/></svg>
        </span>
        Hạn chế
      </button>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="verify_e2ee">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 1.667 3.333 4.5v5.167c0 4.125 2.844 7.895 6.667 8.666 3.823-.771 6.667-4.541 6.667-8.666V4.5L10 1.667Zm2.9 7.066-3.45 3.45a.75.75 0 0 1-1.06 0l-1.617-1.616a.75.75 0 0 1 1.06-1.06l1.087 1.086 2.92-2.92a.75.75 0 0 1 1.06 1.06Z"/></svg>
        </span>
        Xác minh mã hóa đầu cuối
      </button>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="toggle_read_receipts">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 4c4.667 0 8.333 6 8.333 6S14.667 16 10 16 1.667 10 1.667 10 5.333 4 10 4Zm0 2a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm0 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z"/></svg>
        </span>
        Thông báo đã đọc
        <span class="fb-chat-menu-right" id="fbChatMenuReadState">Bật</span>
        <span class="fb-chat-menu-chevron" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor"><path d="M7.5 4.167a.75.75 0 0 1 1.06 0l5 5a.75.75 0 0 1 0 1.06l-5 5a.75.75 0 1 1-1.06-1.06L12.97 10 7.5 4.53a.75.75 0 0 1 0-1.06Z"/></svg>
        </span>
      </button>

      <div class="fb-chat-menu-sep" role="separator"></div>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="archive">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M3 4.5A1.5 1.5 0 0 1 4.5 3h11A1.5 1.5 0 0 1 17 4.5v1A1.5 1.5 0 0 1 15.5 7H4.5A1.5 1.5 0 0 1 3 5.5v-1ZM4.5 8h11v8.5A1.5 1.5 0 0 1 14 18H6A1.5 1.5 0 0 1 4.5 16.5V8Zm4 2.25a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5h-3Z"/></svg>
        </span>
        Lưu trữ đoạn chat
      </button>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="delete_thread">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M7.5 2.5h5a1 1 0 0 1 1 1V5H16a.75.75 0 0 1 0 1.5h-.75l-.95 10.45A2 2 0 0 1 12.307 18H7.693a2 2 0 0 1-1.993-1.05L4.75 6.5H4a.75.75 0 0 1 0-1.5h2.5V3.5a1 1 0 0 1 1-1ZM8 5h4V4h-4v1Zm1 3.25a.75.75 0 0 0-1.5 0v7a.75.75 0 0 0 1.5 0v-7Zm3.5 0a.75.75 0 0 0-1.5 0v7a.75.75 0 0 0 1.5 0v-7Z"/></svg>
        </span>
        Xóa đoạn chat
      </button>

      <button type="button" class="fb-chat-menu-item" role="menuitem" data-action="report">
        <span class="fb-chat-menu-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor"><path d="M10 2 1.667 16.5A1.5 1.5 0 0 0 3 18.75h14a1.5 1.5 0 0 0 1.333-2.25L10 2Zm.75 5.5a.75.75 0 0 0-1.5 0l.35 5.15a.4.4 0 0 0 .8 0L10.75 7.5ZM10 14.25a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z"/></svg>
        </span>
        Báo cáo
      </button>
    </div>

    <div class="fb-chat-call-layer" id="fbChatCallLayer" hidden aria-label="Cuộc gọi">
      <video class="fb-chat-call-remote" id="fbChatRemoteVideo" autoplay playsinline></video>
      <video class="fb-chat-call-local" id="fbChatLocalVideo" autoplay muted playsinline></video>
      <audio id="fbChatRemoteAudio" autoplay></audio>

      <div class="fb-call-screen" id="fbCallScreen" aria-live="polite">
        <img class="fb-call-avatar" id="fbCallAvatar" alt="" src="">
        <div class="fb-call-name" id="fbCallName"></div>
        <div class="fb-call-status" id="fbCallStatus">Đang gọi…</div>
      </div>

      <div class="fb-call-controls" aria-label="Điều khiển cuộc gọi">
        <button type="button" class="fb-call-btn is-primary" id="fbCallAccept" aria-label="Nghe" hidden>
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
        </button>
        <button type="button" class="fb-call-btn" id="fbCallDecline" aria-label="Từ chối" hidden>
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.3 5.71a1 1 0 0 0-1.41 0L12 10.59 7.11 5.7A1 1 0 0 0 5.7 7.11L10.59 12l-4.9 4.89a1 1 0 1 0 1.42 1.42L12 13.41l4.89 4.9a1 1 0 0 0 1.42-1.42L13.41 12l4.9-4.89a1 1 0 0 0-.01-1.4z"/></svg>
        </button>

        <button type="button" class="fb-call-btn" id="fbCallMute" aria-label="Tắt mic" hidden>
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/><path d="M19 11a1 1 0 1 0-2 0 5 5 0 0 1-10 0 1 1 0 1 0-2 0 7 7 0 0 0 6 6.92V21a1 1 0 1 0 2 0v-3.08A7 7 0 0 0 19 11z"/></svg>
        </button>
        <button type="button" class="fb-call-btn" id="fbCallCam" aria-label="Tắt camera" hidden>
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M15 8a3 3 0 0 1 3 3v2.3l3.2-2.1A1 1 0 0 1 23 12v6a1 1 0 0 1-1.8.6L18 16.7V17a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V11a3 3 0 0 1 3-3h10z"/></svg>
        </button>
        <button type="button" class="fb-call-btn" id="fbCallHangup" aria-label="Kết thúc cuộc gọi" hidden>
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M21 15.46l-5.27-2.11a1 1 0 0 0-1.02.22l-2.2 2.2a16.88 16.88 0 0 1-7.31-7.31l2.2-2.2a1 1 0 0 0 .22-1.02L8.54 3H3a1 1 0 0 0-1 1c0 9.39 7.61 17 17 17a1 1 0 0 0 1-1v-4.54z"/></svg>
        </button>
      </div>

      <audio id="fbCallTone" preload="auto" loop></audio>
    </div>

    <div class="fb-chat-body" id="fbChatBody" aria-label="Tin nhắn"></div>
    <form class="fb-chat-foot" id="fbChatForm" autocomplete="off">
      <input id="fbChatFileInput" type="file" accept="image/*" multiple hidden>
      <button type="button" class="fb-chat-tool" id="fbChatToolPlus" aria-label="Thêm">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M11 11V6a1 1 0 1 1 2 0v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5z"></path>
        </svg>
      </button>
      <button type="button" class="fb-chat-tool" id="fbChatToolMic" aria-label="Gửi clip âm thanh">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/>
          <path d="M19 11a1 1 0 1 0-2 0 5 5 0 0 1-10 0 1 1 0 1 0-2 0 7 7 0 0 0 6 6.92V21a1 1 0 1 0 2 0v-3.08A7 7 0 0 0 19 11z"/>
        </svg>
      </button>
      <button type="button" class="fb-chat-tool" id="fbChatToolPhoto" aria-label="Gửi ảnh">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M16.5 6.5 9 14a3 3 0 0 0 4.24 4.24L20 11.5a5 5 0 0 0-7.07-7.07L6.5 10.86a7 7 0 1 0 9.9 9.9l1.1-1.1a1 1 0 1 0-1.42-1.42l-1.1 1.1a5 5 0 1 1-7.07-7.07l6.43-6.43a3 3 0 0 1 4.24 4.24l-6.76 6.76a1 1 0 0 1-1.41-1.41l7.5-7.5a1 1 0 1 0-1.41-1.41z"/>
        </svg>
      </button>

      <button type="button" class="fb-chat-tool" id="fbChatToolSticker" aria-label="Chọn nhãn dán">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M21 13.5V12a9 9 0 1 0-9 9h1.5A7.5 7.5 0 0 0 21 13.5z" opacity=".35"/>
          <path d="M14 21.9a.9.9 0 0 1-.9-.9V18a4 4 0 0 1 4-4h3a.9.9 0 0 1 .9.9c0 3.86-3.14 7-7 7z"/>
          <path d="M9 11.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5zm6 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5z"/>
        </svg>
      </button>
      <button type="button" class="fb-chat-tool" id="fbChatToolGif" aria-label="Chọn file GIF">GIF</button>
      <div class="fb-chat-input-wrap">
        <div
          class="fb-chat-input"
          id="fbChatInput"
          contenteditable="true"
          role="textbox"
          aria-label="Tin nhắn"
          data-placeholder="Aa"
          spellcheck="true"></div>

        <button type="button" class="fb-chat-input-ico" id="fbChatToolEmoji" aria-label="Chọn biểu tượng cảm xúc">
          <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2zm-3 9a1.5 1.5 0 1 1 1.5-1.5A1.502 1.502 0 0 1 9 11zm6 0a1.5 1.5 0 1 1 1.5-1.5A1.502 1.502 0 0 1 15 11zm-3 8a6.013 6.013 0 0 1-4.243-1.757 1 1 0 1 1 1.414-1.414A4.014 4.014 0 0 0 12 17a4.014 4.014 0 0 0 2.829-1.171 1 1 0 0 1 1.414 1.414A6.013 6.013 0 0 1 12 19z"/>
          </svg>
        </button>
      </div>
      <button type="button" class="fb-chat-like" id="fbChatLike" aria-label="Gửi lượt thích">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M2 21h4V9H2v12zm20-11a2 2 0 0 0-2-2h-6.31l.95-4.57.03-.32a1 1 0 0 0-.29-.7L13.17 2 7.59 7.59A2 2 0 0 0 7 9v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-1.68l1.88-8.32A2 2 0 0 0 22 10z"/>
        </svg>
      </button>
      <button class="fb-chat-send" id="fbChatSend" type="submit" disabled hidden aria-label="Gửi">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M2 21 23 12 2 3v7l15 2-15 2v7z"/>
        </svg>
      </button>
    </form>
  </div>

  <!-- Socket status (debug) -->
  <div id="fbSocketStatus" style="display:none!important">socket: -</div>

  <!-- Create Post Modal (uses actions/post_create.php) -->
  <div class="post-modal-backdrop" id="postModalBackdrop" aria-hidden="true">
    <div class="post-modal" role="dialog" aria-modal="true" aria-label="Tạo bài viết">
      <div class="post-modal-header">
        Tạo bài viết
        <button type="button" class="post-modal-close" id="postModalClose" aria-label="Đóng">×</button>
      </div>
      <div class="post-modal-body">
        <div class="post-modal-user">
          <img class="post-modal-avatar" src="<?= fb_escape($currentUserAvatar) ?>" alt="<?= $currentUserNameSafe ?>">
          <div>
            <div class="post-modal-user-name"><?= $currentUserNameSafe ?></div>
            <div class="post-modal-audience" aria-disabled="true">
              <img
                class="audience-icon"
                src="https://static.xx.fbcdn.net/rsrc.php/v4/y5/r/qop9rFQ_Ys1.png"
                alt="Công khai">
              <span class="audience-text">Công khai</span>
              <i class="audience-caret"></i>
            </div>
          </div>
        </div>

        <form class="post-modal-form" id="postModalForm" action="../actions/post_create.php" method="post" enctype="multipart/form-data">
          <div class="post-modal-textwrap" id="postModalTextWrap" data-bg="none">
            <textarea class="post-modal-textarea" id="postModalContent" name="content" placeholder="<?= $firstNameSafe ?> ơi, bạn đang nghĩ gì thế?"></textarea>

            <button type="button" class="post-modal-bg-btn" id="postModalBgBtn" aria-label="Hiển thị các tùy chọn phông nền">
              <span class="post-modal-bg-aa" aria-hidden="true">Aa</span>
              <span class="fb-tooltip">Hiển thị các tùy chọn phông nền</span>
            </button>

            <div class="post-modal-bg-picker" id="postModalBgPicker" hidden aria-label="Tùy chọn phông nền">
              <button type="button" class="post-modal-bg-swatch is-none" data-bg="none" aria-label="Không dùng phông nền"></button>
              <button type="button" class="post-modal-bg-swatch" data-bg="bg1" aria-label="Phông nền 1"></button>
              <button type="button" class="post-modal-bg-swatch" data-bg="bg2" aria-label="Phông nền 2"></button>
              <button type="button" class="post-modal-bg-swatch" data-bg="bg3" aria-label="Phông nền 3"></button>
              <button type="button" class="post-modal-bg-swatch" data-bg="bg4" aria-label="Phông nền 4"></button>
              <button type="button" class="post-modal-bg-swatch" data-bg="bg5" aria-label="Phông nền 5"></button>
            </div>
          </div>

          <input id="postModalMediaInput" class="post-modal-file" type="file" name="image[]" accept="image/*,image/heif,image/heic" multiple>
          <div class="post-modal-media-preview" id="postModalMediaPreview" hidden aria-label="Xem trước ảnh"></div>

          <div class="post-modal-addto" aria-hidden="true">
            <div class="post-modal-addto-label">Thêm vào bài viết của bạn</div>
            <div class="post-modal-addto-actions">
              <button type="button" class="post-modal-icon-btn post-modal-photo-btn" id="postModalPickMedia" aria-label="Ảnh/video">
                <img class="post-modal-icon-img" src="https://static.xx.fbcdn.net/rsrc.php/v4/y7/r/Ivw7nhRtXyo.png" alt="" width="24" height="24">
              </button>
              <button
  type="button"
  class="post-modal-icon-btn post-modal-icon-tag"
  aria-label="Gắn thẻ người khác"
>
  <img
    class="post-modal-icon-img"
    src="https://static.xx.fbcdn.net/rsrc.php/v4/yq/r/b37mHA1PjfK.png"
    alt=""
  >
  <span class="fb-tooltip">Gắn thẻ người khác</span>
</button>
              <button
  type="button"
  class="post-modal-icon-btn post-modal-icon-feeling"
  aria-label="Cảm xúc/hoạt động"
>
  <div class="post-modal-icon-inner">
    <img
      src="https://static.xx.fbcdn.net/rsrc.php/v4/yd/r/Y4mYLVOhTwq.png"
      alt=""
      width="24"
      height="24"
    >
  </div>
  <div class="post-modal-icon-overlay"></div>
</button>
              <button
  type="button"
  class="post-modal-icon-btn post-modal-icon-location"
  aria-label="Check in"
>
  <div class="post-modal-icon-inner">
    <img
      src="https://static.xx.fbcdn.net/rsrc.php/v4/y1/r/8zlaieBcZ72.png"
      alt=""
      width="24"
      height="24"
    >
  </div>
  <div class="post-modal-icon-overlay"></div>

  <span class="fb-tooltip">Check in</span>
</button>
              <button
  type="button"
  class="post-modal-icon-btn post-modal-icon-gif"
  aria-label="File GIF"
>
  <div class="post-modal-icon-inner">
    <img
      src="https://static.xx.fbcdn.net/rsrc.php/v4/yT/r/q7MiRkL7MLC.png"
      alt=""
      width="24"
      height="24"
    >
  </div>

  <div class="post-modal-icon-overlay"></div>

  <span class="fb-tooltip">File GIF</span>
</button>

              <button
  type="button"
  class="post-modal-icon-btn post-modal-icon-more"
  aria-label="Xem thêm"
>
  <div class="post-modal-icon-inner post-modal-icon-more-img"></div>

  <div class="post-modal-icon-overlay"></div>

  <span class="fb-tooltip">Xem thêm</span>
</button>
            </div>
          </div>

          <button type="submit" class="post-modal-submit" id="postModalSubmit" disabled>Đăng</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Comment Modal (mở khi click "Bình luận" trong bài viết) -->
  <div class="fb-comment-modal-backdrop" id="fbCommentModalBackdrop" aria-hidden="true">
    <div class="fb-comment-modal" role="dialog" aria-modal="true" aria-label="Bình luận">
      <div class="fb-comment-modal-header">
        <div id="fbCommentModalTitle">Bài viết</div>
        <button type="button" class="fb-comment-modal-close" id="fbCommentModalClose" aria-label="Đóng">×</button>
      </div>

      <div class="fb-comment-modal-body">
        <div class="fb-comment-modal-post" id="fbCommentModalPost"></div>
        <div class="fb-comment-modal-divider" aria-hidden="true"></div>

        <div class="fb-comment-modal-comments">
          <div class="fb-comment-list" id="fbCommentModalList"></div>

          <div class="fb-comment-empty" id="fbCommentModalEmpty" hidden>
            <div class="fb-comment-empty-icon" aria-hidden="true">
              <svg viewBox="0 0 64 64" width="46" height="46" aria-hidden="true" focusable="false">
                <path fill="currentColor" d="M14 8h26l10 10v38a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V12a4 4 0 0 1 4-4z" opacity=".35"></path>
                <path fill="currentColor" d="M40 8v10a4 4 0 0 0 4 4h10" opacity=".55"></path>
                <path fill="currentColor" d="M18 30h28a2 2 0 0 1 0 4H18a2 2 0 0 1 0-4zm0 10h22a2 2 0 0 1 0 4H18a2 2 0 0 1 0-4z" opacity=".8"></path>
              </svg>
            </div>
            <div class="fb-comment-empty-title">Chưa có bình luận nào</div>
            <div class="fb-comment-empty-sub">Hãy là người đầu tiên bình luận.</div>
          </div>

          <form class="fb-comment-form" id="fbCommentModalForm" autocomplete="off">
            <input type="hidden" name="post_id" id="fbCommentModalPostId" value="">
            <input type="hidden" name="parent_id" id="fbCommentModalParentId" value="">

            <div class="fb-replying" id="fbCommentModalReplying" hidden>
              <div>Đang trả lời <strong id="fbCommentModalReplyingName"></strong></div>
              <button type="button" class="fb-replying-cancel" id="fbCommentModalReplyingCancel" aria-label="Hủy trả lời">×</button>
            </div>

            <input class="fb-comment-input" name="content" type="text" placeholder="Viết bình luận...">
            <button class="fb-comment-submit" type="submit" disabled>Gửi</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>

  <!-- Facebook-style Loading Overlay -->
  <div id="fbLoadingOverlay">
    <div class="fb-loading-center">
      <div class="fb-spinner"></div>
    </div>
  </div>



  <header class="header">
    <div class="header-left" id="headerBox">
      <div class="logo-area">
        <div class="fb-logo" title="Facebook" id="fbLogo" tabindex="0" role="button" aria-label="Facebook">
          <svg viewBox="0 0 36 36" width="40" height="40" aria-hidden="true" focusable="false">
            <path d="M20.181 35.87C29.094 34.791 36 27.202 36 18c0-9.941-8.059-18-18-18S0 8.059 0 18c0 8.442 5.811 15.526 13.652 17.471L14 34h5.5l.681 1.87Z" fill="#0866FF"></path>
            <path d="M13.651 35.471v-11.97H9.936V18h3.715v-2.37c0-6.127 2.772-8.964 8.784-8.964 1.138 0 3.103.223 3.91.446v4.983c-.425-.043-1.167-.065-2.081-.065-2.952 0-4.09 1.116-4.09 4.025V18h5.883l-1.008 5.5h-4.867v12.37a18.183 18.183 0 0 1-6.53-.399Z" fill="#fff"></path>
          </svg>
        </div>

        <!-- back button moved into the search wrapper (kept visually inside the pill) -->
      </div>

      <!-- ------------------ Search ------------------ -->
      <div class="search-wrapper" id="searchWrapper">
        <div class="search-icon" aria-hidden="true">
          <!-- Cleaned magnifier SVG that fits the icon box and won't be clipped -->
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMid meet">
            <path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z" />
          </svg>
        </div>


        <!-- fake placeholder (dùng để animate chữ trượt sang trái khi focus)
                     CHÚ Ý: phần hiệu ứng phải->trái nằm ở CSS:
                     .search-wrapper.focused .fake-placeholder { transform: translateX(-26px); }
                     Và tốc độ mượt/nhanh chỉnh ở .fake-placeholder { transition: transform .42s ... } -->
        <span class="fake-placeholder" id="fakePlaceholder">Tìm kiếm trên Facebook</span>

        <!-- back button placed inside the pill so the arrow is always inside the search frame -->
        <button class="back-btn" id="backBtn" aria-label="Quay lại" title="Quay lại" tabindex="0">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true" class="x14rh7hd x1lliihq x1tzjh5l x1k90msu x2h7rmj x1qfuztq" style="--x-color:var(--secondary-icon)">
            <g fill-rule="evenodd" transform="translate(-446 -350)">
              <g fill-rule="nonzero">
                <path d="M100.249 201.999a1 1 0 0 0-1.415-1.415l-5.208 5.209a1 1 0 0 0 0 1.414l5.208 5.209A1 1 0 0 0 100.25 211l-4.501-4.501 4.5-4.501z" transform="translate(355 153.5)"></path>
                <path d="M107.666 205.5H94.855a1 1 0 1 0 0 2h12.813a1 1 0 1 0 0-2z" transform="translate(355 153.5)"></path>
              </g>
            </g>
          </svg>
        </button>

        <!-- input thật: luôn giữ value (nơi user nhập) -->
        <input type="text" class="search-input" id="searchInput" placeholder="Tìm kiếm trên Facebook" autocomplete="off">

        <!-- KHUNG TRẮNG -->
        <div id="searchResult">
          <div class="empty">Không có tìm kiếm nào gần đây</div>
        </div>
      </div>
    </div>

    <!-- center nav (icons) -->
    <div class="center-nav-wrap" aria-hidden="false">
      <nav class="center-nav" role="navigation" aria-label="Main">
        <!-- Home -->
        <a class="cnav-item active" href="../pages/home.php" title="Trang chủ" data-key="home" aria-current="page">
          <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24">
              <path fill="currentColor" d="M9.464 1.286C10.294.803 11.092.5 12 .5c.908 0 1.707.303 2.537.786.795.462 1.7 1.142 2.815 1.977l2.232 1.675c1.391 1.042 2.359 1.766 2.888 2.826.53 1.059.53 2.268.528 4.006v4.3c0 1.355 0 2.471-.119 3.355-.124.928-.396 1.747-1.052 2.403-.657.657-1.476.928-2.404 1.053-.884.119-2 .119-3.354.119H7.93c-1.354 0-2.471 0-3.355-.119-.928-.125-1.747-.396-2.403-1.053-.656-.656-.928-1.475-1.053-2.403C1 18.541 1 17.425 1 16.07v-4.3c0-1.738-.002-2.947.528-4.006.53-1.06 1.497-1.784 2.888-2.826L6.65 3.263c1.114-.835 2.02-1.515 2.815-1.977zM10.5 13A1.5 1.5 0 0 0 9 14.5V21h6v-6.5a1.5 1.5 0 0 0-1.5-1.5h-3z" />
            </svg></div>
          <div class="underline"></div>
          <div class="tooltip" role="status">Trang chủ</div>
        </a>

        <!-- Friends -->
        <a class="cnav-item" href="../pages/friends.php" title="Bạn bè" data-key="friends">
          <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24">
              <path fill="currentColor" d="M12.496 5a4 4 0 1 1 8 0 4 4 0 0 1-8 0zm4-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-9 2.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm-2 4a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM5.5 15a5 5 0 0 0-5 5 3 3 0 0 0 3 3h8.006a3 3 0 0 0 3-3 5 5 0 0 0-5-5H5.5zm-3 5a3 3 0 0 1 3-3h4.006a3 3 0 0 1 3 3 1 1 0 0 1-1 1H3.5a1 1 0 0 1-1-1zm12-9.5a5.04 5.04 0 0 0-.37.014 1 1 0 0 0 .146 1.994c.074-.005.149-.008.224-.008h4.006a3 3 0 0 1 3 3 1 1 0 0 1-1 1h-3.398a1 1 0 1 0 0 2h3.398a3 3 0 0 0 3-3 5 5 0 0 0-5-5H14.5z" />
            </svg></div>
          <div class="underline"></div>
          <div class="tooltip">Bạn bè</div>
        </a>

        <!-- Watch -->
        <a class="cnav-item" href="../pages/watch.php" title="Watch" data-key="watch">
          <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24">
              <path d="M10.996 12.132A1 1 0 0 0 9.5 13v4a1 1 0 0 0 1.496.868l3.5-2a1 1 0 0 0 0-1.736l-3.5-2z"></path>
              <path d="M12.075 1h-.15C9.632 1 7.81 1 6.38 1.192c-1.472.198-2.674.616-3.623 1.565-.949.95-1.367 2.15-1.565 3.623C1 7.81 1 9.632 1 11.925v.15c0 2.293 0 4.116.192 5.545.198 1.472.616 2.674 1.565 3.623.95.949 2.15 1.367 3.623 1.565C7.81 23 9.632 23 11.925 23h.15c2.293 0 4.116 0 5.545-.192 1.472-.198 2.674-.616 3.623-1.565.949-.95 1.367-2.15 1.565-3.623.192-1.43.192-3.252.192-5.545v-.15c0-2.293 0-4.116-.192-5.545-.198-1.472-.616-2.674-1.565-3.623-.95-.949-2.15-1.367-3.623-1.565C16.19 1 14.368 1 12.075 1zM4.172 4.172c.515-.516 1.224-.83 2.475-.998l.183-.023L8.113 7H3.132c.013-.121.027-.239.042-.353.168-1.25.482-1.96.998-2.475zM10.22 7 8.895 3.023C9.778 3 10.801 3 12 3c.642 0 1.234 0 1.78.004L15.114 7H10.22zm6.253 2h4.507c.02.86.02 1.848.02 3 0 2.385-.002 4.074-.174 5.353-.168 1.25-.482 1.96-.998 2.475-.515.516-1.224.83-2.475.998-1.28.172-2.968.174-5.353.174s-4.074-.002-5.353-.174c-1.25-.168-1.96-.482-2.475-.998-.516-.515-.83-1.224-.998-2.475C3.002 16.073 3 14.385 3 12c0-1.152 0-2.14.02-3h13.454zm.747-2-1.316-3.949c.537.026 1.016.065 1.448.123 1.25.168 1.96.482 2.475.998.516.515.83 1.224.998 2.475.015.114.03.232.042.353H17.22z" />
            </svg></div>
          <div class="underline"></div>
          <div class="tooltip">Thước phim</div>
        </a>

        <!-- Marketplace -->
        <a class="cnav-item" href="../pages/marketplace.php" title="Marketplace" data-key="market">
          <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
              <path d="M1.588 3.227A3.125 3.125 0 0 1 4.58 1h14.84c1.38 0 2.597.905 2.993 2.227l.816 2.719a6.47 6.47 0 0 1 .272 1.854A5.183 5.183 0 0 1 22 11.455v4.615c0 1.355 0 2.471-.119 3.355-.125.928-.396 1.747-1.053 2.403-.656.657-1.475.928-2.403 1.053-.884.12-2 .119-3.354.119H8.929c-1.354 0-2.47 0-3.354-.119-.928-.125-1.747-.396-2.403-1.053-.657-.656-.929-1.475-1.053-2.403-.12-.884-.119-2-.119-3.354V11.5l.001-.045A5.184 5.184 0 0 1 .5 7.8c0-.628.092-1.252.272-1.854l.816-2.719zM10 21h4v-3.5a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5V21zm6-.002c.918-.005 1.608-.025 2.159-.099.706-.095 1.033-.262 1.255-.485.223-.222.39-.55.485-1.255.099-.735.101-1.716.101-3.159v-3.284a5.195 5.195 0 0 1-1.7.284 5.18 5.18 0 0 1-3.15-1.062A5.18 5.18 0 0 1 12 13a5.18 5.18 0 0 1-3.15-1.062A5.18 5.18 0 0 1 5.7 13a5.2 5.2 0 0 1-1.7-.284V16c0 1.442.002 2.424.1 3.159.096.706.263 1.033.486 1.255.222.223.55.39 1.255.485.551.074 1.24.094 2.159.1V17.5a2.5 2.5 0 0 1 2.5-2.5h3a2.5 2.5 0 0 1 2.5 2.5v3.498zM4.581 3c-.497 0-.935.326-1.078.802l-.815 2.72A4.45 4.45 0 0 0 2.5 7.8a3.2 3.2 0 0 0 5.6 2.117 1 1 0 0 1 1.5 0A3.19 3.19 0 0 0 12 11a3.19 3.19 0 0 0 2.4-1.083 1 1 0 0 1 1.5 0A3.2 3.2 0 0 0 21.5 7.8c0-.434-.063-.865-.188-1.28l-.816-2.72A1.125 1.125 0 0 0 19.42 3H4.58z" />
            </svg></div>
          <div class="underline"></div>
          <div class="tooltip">Marketplace</div>
        </a>

        <!-- Groups -->
        <a class="cnav-item" href="../pages/group.php" title="Nhóm" data-key="groups">
          <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
              <path d="M.5 12c0 6.351 5.149 11.5 11.5 11.5S23.5 18.351 23.5 12 18.351.5 12 .5.5 5.649.5 12zm2 0c0-.682.072-1.348.209-1.99a2 2 0 0 1 0 3.98A9.539 9.539 0 0 1 2.5 12zm.84-3.912A9.502 9.502 0 0 1 12 2.5a9.502 9.502 0 0 1 8.66 5.588 4.001 4.001 0 0 0 0 7.824 9.514 9.514 0 0 1-1.755 2.613A5.002 5.002 0 0 0 14 14.5h-4a5.002 5.002 0 0 0-4.905 4.025 9.515 9.515 0 0 1-1.755-2.613 4.001 4.001 0 0 0 0-7.824zM12 5a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm-2 4a2 2 0 1 0 4 0 2 2 0 0 0-4 0zm11.291 1.01a9.538 9.538 0 0 1 0 3.98 2 2 0 0 1 0-3.98zM16.99 20.087A9.455 9.455 0 0 1 12 21.5c-1.83 0-3.54-.517-4.99-1.414a1.004 1.004 0 0 1-.01-.148V19.5a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v.438a1 1 0 0 1-.01.148z"></path>
            </svg></div>
          <div class="underline"></div>
          <div class="tooltip">Nhóm</div>
        </a>
      </nav>
    </div>

    <!-- right icons -->
    <div class="header-right">
      <button class="icon-btn" type="button" aria-label="Menu">
        <!-- menu (grid) -->
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M18.5 1A1.5 1.5 0 0 0 17 2.5v3A1.5 1.5 0 0 0 18.5 7h3A1.5 1.5 0 0 0 23 5.5v-3A1.5 1.5 0 0 0 21.5 1h-3zm0 8a1.5 1.5 0 0 0-1.5 1.5v3a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5v-3A1.5 1.5 0 0 0 21.5 9h-3zm-16 8A1.5 1.5 0 0 0 1 18.5v3A1.5 1.5 0 0 0 2.5 23h3A1.5 1.5 0 0 0 7 21.5v-3A1.5 1.5 0 0 0 5.5 17h-3zm8 0A1.5 1.5 0 0 0 9 18.5v3a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5v-3a1.5 1.5 0 0 0-1.5-1.5h-3zm8 0a1.5 1.5 0 0 0-1.5 1.5v3a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5v-3a1.5 1.5 0 0 0-1.5-1.5h-3zm-16-8A1.5 1.5 0 0 0 1 10.5v3A1.5 1.5 0 0 0 2.5 15h3A1.5 1.5 0 0 0 7 13.5v-3A1.5 1.5 0 0 0 5.5 9h-3zm0-8A1.5 1.5 0 0 0 1 2.5v3A1.5 1.5 0 0 0 2.5 7h3A1.5 1.5 0 0 0 7 5.5v-3A1.5 1.5 0 0 0 5.5 1h-3zm8 0A1.5 1.5 0 0 0 9 2.5v3A1.5 1.5 0 0 0 10.5 7h3A1.5 1.5 0 0 0 15 5.5v-3A1.5 1.5 0 0 0 13.5 1h-3zm0 8A1.5 1.5 0 0 0 9 10.5v3a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5v-3A1.5 1.5 0 0 0 13.5 9h-3z"></path>
        </svg>
        <div class="tooltip">Menu</div>
      </button>

      <button class="icon-btn" id="messengerBtn" type="button" aria-label="Messenger" aria-expanded="false" aria-controls="messengerPopover">
        <!-- messenger (FB DOM) -->
        <svg viewBox="0 0 12 12" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false">
          <g stroke="none" stroke-width="1" fill-rule="evenodd">
            <path d="m106.868 921.248-1.892 2.925a.32.32 0 0 1-.443.094l-1.753-1.134a.2.2 0 0 0-.222.003l-1.976 1.363c-.288.199-.64-.143-.45-.437l1.892-2.925a.32.32 0 0 1 .443-.095l1.753 1.134a.2.2 0 0 0 .222-.003l1.976-1.363c.288-.198.64.144.45.438m-3.368-4.251c-3.323 0-5.83 2.432-5.83 5.658 0 1.642.652 3.128 1.834 4.186a.331.331 0 0 1 .111.234l.03 1.01a.583.583 0 0 0 .82.519l1.13-.5a.32.32 0 0 1 .22-.015c.541.148 1.108.223 1.685.223 3.323 0 5.83-2.432 5.83-5.657 0-3.226-2.507-5.658-5.83-5.658" transform="translate(-450 -1073.5) translate(352.5 156.845)"></path>
          </g>
        </svg>
        <div class="tooltip">Messenger</div>
      </button>

      <button class="icon-btn" type="button" aria-label="Thông báo">
        <!-- notifications (bell) -->
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M3 9.5a9 9 0 1 1 18 0v2.927c0 1.69.475 3.345 1.37 4.778a1.5 1.5 0 0 1-1.272 2.295h-4.625a4.5 4.5 0 0 1-8.946 0H2.902a1.5 1.5 0 0 1-1.272-2.295A9.01 9.01 0 0 0 3 12.43V9.5zm6.55 10a2.5 2.5 0 0 0 4.9 0h-4.9z"></path>
        </svg>
        <div class="tooltip">Thông báo</div>
      </button>

      <button class="icon-btn account-btn" type="button" aria-label="Trang cá nhân của bạn">
        <span class="account-avatar" aria-hidden="true">
          <svg aria-label="Trang cá nhân của bạn" role="img" viewBox="0 0 40 40" width="40" height="40" focusable="false">
            <mask id="mpAcctMask">
              <circle cx="20" cy="20" r="20" fill="#fff"></circle>
              <circle cx="34" cy="34" r="8" fill="#000"></circle>
            </mask>
            <g mask="url(#mpAcctMask)">
              <image x="0" y="0" width="100%" height="100%" preserveAspectRatio="xMidYMid slice"
                href="<?= fb_escape($currentUserAvatar) ?>"
                xlink:href="<?= fb_escape($currentUserAvatar) ?>"></image>
              <circle class="account-avatar-ring" cx="20" cy="20" r="20"></circle>
            </g>
          </svg>

          <span class="account-caret" aria-hidden="true">
            <span class="account-caret-bg" aria-hidden="true">
              <svg viewBox="0 0 16 16" width="12" height="12" fill="currentColor" aria-hidden="true" focusable="false">
                <g fill-rule="evenodd" transform="translate(-448 -544)">
                  <path fill-rule="nonzero" d="M452.707 549.293a1 1 0 0 0-1.414 1.414l4 4a1 1 0 0 0 1.414 0l4-4a1 1 0 0 0-1.414-1.414L456 552.586l-3.293-3.293z"></path>
                </g>
              </svg>
            </span>
          </span>
        </span>
        <div class="tooltip">Tài khoản</div>
      </button>
    </div>
  </header>

  <!-- Account popover -->
  <section class="account-popover" id="accountPopover" role="dialog" aria-label="Tài khoản" aria-hidden="true">
    <div class="acct-menu" id="acctMenuRoot">
      <div class="fb-menu-container">
        <div class="menu-slider" id="acctMenuSlider">
          <div class="menu-track">

            <!-- PANEL 1: MAIN MENU -->
            <div class="menu-panel" aria-hidden="false">
              <div class="profile-card">
                <div class="profile-info">
                  <div class="profile-info-inner" role="button" tabindex="0" aria-label="Trang cá nhân của bạn">
                    <div class="avatar-circle" aria-hidden="true">
                      <svg aria-hidden="true" role="none" viewBox="0 0 36 36" focusable="false">
                        <mask id="acctMenuAvatarMask">
                          <circle cx="18" cy="18" r="18" fill="#fff"></circle>
                        </mask>
                        <g mask="url(#acctMenuAvatarMask)">
                          <image x="0" y="0" width="100%" height="100%" preserveAspectRatio="xMidYMid slice"
                            href="<?= fb_escape($currentUserAvatar) ?>"
                            xlink:href="<?= fb_escape($currentUserAvatar) ?>"></image>
                          <circle class="acct-menu-avatar-ring" cx="18" cy="18" r="18"></circle>
                        </g>
                      </svg>
                    </div>
                    <div class="user-name"><?= $currentUserNameSafe ?></div>
                  </div>
                </div>

                <div class="see-all-btn" role="button" tabindex="0" aria-label="Xem tất cả trang cá nhân">
                  <span class="see-all-ico" aria-hidden="true"></span>
                  <span class="see-all-text">Xem tất cả trang cá nhân</span>
                </div>
              </div>

              <div class="menu-list">
                <a href="#" class="menu-item" id="acctBtnSettingsPrivacy">
                  <div class="icon-wrapper" aria-hidden="true">
                    <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                      <path d="M7.5 10a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0z"></path>
                      <path d="M17.773 8.983a1.748 1.748 0 0 0 0 2.034v-.001l.949 1.328c.302.423.31.988.023 1.42l-1.387 2.081a1.25 1.25 0 0 1-1.195.547l-1.235-.154a1.75 1.75 0 0 0-1.856 1.122l-.498 1.328a1.25 1.25 0 0 1-1.17.811H8.597a1.25 1.25 0 0 1-1.17-.811l-.476-1.269a1.75 1.75 0 0 0-1.91-1.115l-1.165.182a1.248 1.248 0 0 1-1.246-.561L1.238 13.75a1.25 1.25 0 0 1 .036-1.4l.934-1.307a1.75 1.75 0 0 0-.018-2.059l-.904-1.22a1.249 1.249 0 0 1-.06-1.399l1.398-2.272a1.25 1.25 0 0 1 1.258-.58l1.16.181A1.75 1.75 0 0 0 6.95 2.579l.476-1.269a1.25 1.25 0 0 1 1.17-.811h2.807c.52 0 .987.323 1.17.811l.498 1.328a1.752 1.752 0 0 0 1.856 1.122l1.235-.154a1.25 1.25 0 0 1 1.195.547l1.387 2.081a1.25 1.25 0 0 1-.023 1.42l-.95 1.329zM10 6a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path>
                    </svg>
                  </div>
                  <div class="item-label-wrapper">
                    <div class="item-label">Cài đặt và quyền riêng tư</div>
                  </div>
                  <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                  </svg>
                </a>

                <a href="#" class="menu-item" id="acctBtnHelpSupport">
                  <div class="icon-wrapper" aria-hidden="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z" />
                    </svg>
                  </div>
                  <div class="item-label-wrapper">
                    <div class="item-label">Trợ giúp và hỗ trợ</div>
                  </div>
                  <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                  </svg>
                </a>

                <a href="#" class="menu-item" id="acctBtnDisplayAccessibility">
                  <div class="icon-wrapper" aria-hidden="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z" />
                    </svg>
                  </div>
                  <div class="item-label-wrapper">
                    <div class="item-label">Màn hình và trợ năng</div>
                  </div>
                  <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                  </svg>
                </a>

                <a href="#" class="menu-item has-ctrl">
                  <div class="icon-wrapper" aria-hidden="true">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z" />
                    </svg>
                  </div>
                  <div class="item-label-wrapper">
                    <div class="item-label">Đóng góp ý kiến</div>
                    <div class="item-ctrl">CTRL B</div>
                  </div>
                </a>

                <a href="../auth/logout.php" class="menu-item" id="acctBtnLogout">
                  <div class="icon-wrapper" aria-hidden="true">
                    <i class="fb-sprite" aria-hidden="true" style="display:inline-block;"></i>
                  </div>
                  <div class="item-label-wrapper">
                    <div class="item-label">Đăng xuất</div>
                  </div>
                </a>
              </div>

              <div class="footer-links">
                <span>Quyền riêng tư</span> <span class="dot">·</span>
                <span>Điều khoản</span> <span class="dot">·</span>
                <span>Quảng cáo</span> <span class="dot">·</span>
                <span>Lựa chọn quảng cáo</span> <span class="dot">·</span>
                <span>Cookie</span> <span class="dot">·</span>
                <span class="footer-more">Xem thêm</span>
              </div>
            </div>

            <!-- PANEL 2: SECONDARY -->
            <div class="menu-panel" aria-hidden="true">
              <div class="sec-header">
                <button class="back-btn" id="acctBtnBack" type="button" title="Quay lại" aria-label="Quay lại">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
                  </svg>
                </button>
                <div class="sec-title" id="acctSecTitle">Màn hình và trợ năng</div>
              </div>

              <div id="acctSecViews">
                <div class="sec-view" data-view="settings">
                  <div class="sub-menu-list">
                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                          <path d="M7.5 10a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0z"></path>
                          <path d="M17.773 8.983a1.748 1.748 0 0 0 0 2.034v-.001l.949 1.328c.302.423.31.988.023 1.42l-1.387 2.081a1.25 1.25 0 0 1-1.195.547l-1.235-.154a1.75 1.75 0 0 0-1.856 1.122l-.498 1.328a1.25 1.25 0 0 1-1.17.811H8.597a1.25 1.25 0 0 1-1.17-.811l-.476-1.269a1.75 1.75 0 0 0-1.91-1.115l-1.165.182a1.248 1.248 0 0 1-1.246-.561L1.238 13.75a1.25 1.25 0 0 1 .036-1.4l.934-1.307a1.75 1.75 0 0 0-.018-2.059l-.904-1.22a1.249 1.249 0 0 1-.06-1.399l1.398-2.272a1.25 1.25 0 0 1 1.258-.58l1.16.181A1.75 1.75 0 0 0 6.95 2.579l.476-1.269a1.25 1.25 0 0 1 1.17-.811h2.807c.52 0 .987.323 1.17.811l.498 1.328a1.752 1.752 0 0 0 1.856 1.122l1.235-.154a1.25 1.25 0 0 1 1.195.547l1.387 2.081a1.25 1.25 0 0 1-.023 1.42l-.95 1.329zM10 6a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path>
                        </svg>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Cài đặt</div>
                      </div>
                    </a>

                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                          <path d="M12 .5C5.649.5.5 5.649.5 12S5.649 23.5 12 23.5 23.5 18.351 23.5 12 18.351.5 12 .5zM2.983 9H7.16c-.105.958-.16 1.965-.16 3s.055 2.042.16 3H2.983a9.49 9.49 0 0 1-.483-3c0-1.048.17-2.057.483-3zm.938-2a9.53 9.53 0 0 1 4.826-3.928c-.186.358-.356.743-.51 1.147A17.163 17.163 0 0 0 7.462 7H3.92zm5.251 2h5.656c.111.942.172 1.949.172 3s-.06 2.058-.172 3H9.172A25.628 25.628 0 0 1 9 12c0-1.051.06-2.058.172-3zm5.324-2H9.504c.167-.766.37-1.461.602-2.069.337-.883.713-1.53 1.08-1.937.367-.407.644-.494.814-.494.17 0 .447.087.814.494.367.408.743 1.054 1.08 1.937.231.608.435 1.303.602 2.069zm2.344 2h4.177a9.49 9.49 0 0 1 .483 3 9.49 9.49 0 0 1-.483 3H16.84c.105-.958.16-1.965.16-3s-.055-2.042-.16-3zm3.24-2h-3.542a17.154 17.154 0 0 0-.775-2.78 11.02 11.02 0 0 0-.51-1.148A9.53 9.53 0 0 1 20.08 7zM8.746 20.928A9.53 9.53 0 0 1 3.92 17h3.54a17.15 17.15 0 0 0 .776 2.78c.154.405.324.79.51 1.148zm1.36-1.86A14.592 14.592 0 0 1 9.503 17h4.992c-.167.766-.37 1.461-.602 2.069-.337.883-.713 1.53-1.08 1.937-.367.407-.644.494-.814.494-.17 0-.447-.087-.814-.494-.367-.408-.743-1.054-1.08-1.937zm5.656.713c.313-.822.575-1.76.775-2.781h3.541a9.53 9.53 0 0 1-4.826 3.928c.186-.358.356-.743.51-1.147z"></path>
                        </svg>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Ngôn ngữ</div>
                      </div>
                      <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                      </svg>
                    </a>

                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <i class="fb-sprite fb-sprite-privacy-check" aria-hidden="true"></i>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Kiểm tra quyền riêng tư</div>
                      </div>
                    </a>

                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                          <path d="M10 .375a4 4 0 0 0-4 4v3.04a8.83 8.83 0 0 0-.593.058c-.764.103-1.426.325-1.955.854-.529.529-.751 1.19-.854 1.955-.098.73-.098 1.656-.098 2.79v.107c0 1.133 0 2.058.098 2.79.103.763.325 1.425.854 1.954.529.529 1.19.751 1.955.854.73.098 1.656.098 2.79.098h3.607c1.133 0 2.058 0 2.79-.098.763-.103 1.425-.325 1.954-.854.529-.529.751-1.19.854-1.955.098-.73.098-1.656.098-2.79v-.107c0-1.133 0-2.058-.098-2.79-.103-.763-.325-1.425-.854-1.954-.529-.529-1.19-.751-1.955-.854A8.83 8.83 0 0 0 14 7.416V4.375a4 4 0 0 0-4-4zm-2.5 4a2.5 2.5 0 0 1 5 0v3h-5v-3z"></path>
                        </svg>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Trung tâm quyền riêng tư</div>
                      </div>
                    </a>

                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                          <path d="M3.5 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM7.75 3a1 1 0 0 0 0 2h9.5a1 1 0 1 0 0-2h-9.5zM5 10a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm2.75-1a1 1 0 0 0 0 2h9.5a1 1 0 1 0 0-2h-9.5zM5 16a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm2.75-1a1 1 0 1 0 0 2h9.5a1 1 0 1 0 0-2h-9.5z"></path>
                        </svg>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Nhật ký hoạt động</div>
                      </div>
                    </a>

                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                          <path d="M3.854 7.25H2.75a.75.75 0 0 1 0-1.5h1.104a2.751 2.751 0 1 1 0 1.5zm9.646 3.5a2.75 2.75 0 0 1 2.646 2h1.104a.75.75 0 0 1 0 1.5h-1.104a2.751 2.751 0 1 1-2.646-3.5zM9.25 13.5a.75.75 0 0 0-.75-.75H2.75a.75.75 0 0 0 0 1.5H8.5a.75.75 0 0 0 .75-.75zm2.25-7.75a.75.75 0 0 0 0 1.5h5.75a.75.75 0 0 0 0-1.5H11.5z"></path>
                        </svg>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Tùy chọn nội dung</div>
                      </div>
                    </a>
                  </div>
                </div>

                <div class="sec-view" data-view="help">
                  <div class="sub-menu-list">
                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M12 2a10 10 0 1 0 10 10A10.01 10.01 0 0 0 12 2zm1 16h-2v-2h2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z" />
                        </svg>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Trung tâm trợ giúp</div>
                      </div>
                    </a>

                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true">
                          <path d="M6.25 1a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5zM4.583 8.5A4.083 4.083 0 0 0 .5 12.583 2.417 2.417 0 0 0 2.917 15H6.25a.75.75 0 0 0 .75-.75v-5a.75.75 0 0 0-.75-.75H4.583zm11.947 4.53a.75.75 0 1 0-1.06-1.06L13 14.44l-.97-.97a.75.75 0 1 0-1.06 1.06l1.028 1.029c.554.553 1.45.553 2.004 0l2.528-2.529z" />
                          <path d="M8.5 10.75a2.25 2.25 0 0 1 2.25-2.25h6A2.25 2.25 0 0 1 19 10.75v6A2.25 2.25 0 0 1 16.75 19h-6a2.25 2.25 0 0 1-2.25-2.25v-6zm2.25-.75a.75.75 0 0 0-.75.75v6c0 .414.336.75.75.75h6a.75.75 0 0 0 .75-.75v-6a.75.75 0 0 0-.75-.75h-6z" />
                        </svg>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Trạng thái tài khoản</div>
                      </div>
                    </a>

                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                          <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z" />
                        </svg>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Hộp thư hỗ trợ</div>
                      </div>
                    </a>

                    <a href="#" class="sub-menu-item" aria-disabled="true">
                      <div class="icon-wrapper" aria-hidden="true">
                        <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true">
                          <path d="M12.805 2.5h-5.61c-1.367 0-2.47 0-3.337.117-.9.12-1.658.38-2.26.981-.602.602-.86 1.36-.981 2.26C.5 6.725.5 7.828.5 9.195v1.61c0 1.367 0 2.47.117 3.337.12.9.38 1.658.981 2.26.602.602 1.36.86 2.26.982.867.116 1.97.116 3.337.116h5.61c1.367 0 2.47 0 3.337-.116.9-.122 1.658-.38 2.26-.982.602-.602.86-1.36.982-2.26.116-.867.116-1.97.116-3.337v-1.61c0-1.367 0-2.47-.116-3.337-.122-.9-.38-1.658-.982-2.26-.602-.602-1.36-.86-2.26-.981-.867-.117-1.97-.117-3.337-.117zM10 5.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5.5zm0 9a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                        </svg>
                      </div>
                      <div class="item-label-wrapper">
                        <div class="item-label">Báo cáo sự cố</div>
                      </div>
                    </a>
                  </div>
                </div>

                <div class="sec-view" data-view="accessibility">
                  <div class="setting-block">
                    <div class="setting-icon">
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z" />
                      </svg>
                    </div>
                    <div class="setting-content">
                      <div class="setting-name">Chế độ tối</div>
                      <div class="setting-desc">Điều chỉnh giao diện của Facebook để giảm độ chói và cho đôi mắt được nghỉ ngơi.</div>

                      <div class="radio-row" data-group="darkmode" data-value="off">
                        <span class="radio-label">Tắt</span>
                        <div class="radio-circle"></div>
                      </div>
                      <div class="radio-row" data-group="darkmode" data-value="on">
                        <span class="radio-label">Bật</span>
                        <div class="radio-circle"></div>
                      </div>
                      <div class="radio-row" data-group="darkmode" data-value="auto">
                        <span class="radio-label">Tự động</span>
                        <div class="radio-circle"></div>
                      </div>

                      <div class="setting-desc" style="font-size:12px; margin-top:8px;">Chúng tôi sẽ tự động điều chỉnh màn hình theo cài đặt hệ thống trên thiết bị của bạn (khi chọn Tự động).</div>
                    </div>
                  </div>

                  <div class="setting-block">
                    <div class="setting-icon">
                      <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="m10.002 8.61.532 1.884H9.468l.534-1.884z"></path>
                        <path d="M18.25 7.5a.75.75 0 0 1-.75-.75V3.56l-2.404 2.405a6.5 6.5 0 0 1-9.131 9.131L3.56 17.5h2.69a.75.75 0 0 1 0 1.5h-3.5A1.75 1.75 0 0 1 1 17.25v-3.5a.75.75 0 0 1 1.5 0v2.69l2.404-2.405a6.5 6.5 0 0 1 9.131-9.131L16.44 2.5h-3.19a.75.75 0 0 1 0-1.5h4c.966 0 1.75.784 1.75 1.75v4a.75.75 0 0 1-.75.75zm-9.042-.67-1.56 5.5a.625.625 0 0 0 1.203.34l.263-.926h1.773l.262.926a.625.625 0 1 0 1.203-.34l-1.553-5.5a.625.625 0 0 0-.602-.455H9.81a.625.625 0 0 0-.601.455z"></path>
                      </svg>
                    </div>
                    <div class="setting-content">
                      <div class="setting-name">Chế độ Thu gọn</div>
                      <div class="setting-desc">Giảm kích thước phông chữ để có thêm nội dung vừa với màn hình.</div>

                      <div class="radio-row" data-group="compact" data-value="off">
                        <span class="radio-label">Tắt</span>
                        <div class="radio-circle"></div>
                      </div>
                      <div class="radio-row" data-group="compact" data-value="on">
                        <span class="radio-label">Bật</span>
                        <div class="radio-circle"></div>
                      </div>
                    </div>
                  </div>

                  <div class="simple-item">
                    <div class="setting-icon">
                      <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20 5H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-9 3h2v2h-2V8zm0 3h2v2h-2v-2zM8 8h2v2H8V8zm0 3h2v2H8v-2zm-1 2H5v-2h2v2zm0-3H5V8h2v2zm9 7H8v-2h8v2zm0-4h-2v-2h2v2zm0-3h-2V8h2v2zm3 3h-2v-2h2v2zm0-3h-2V8h2v2z" />
                      </svg>
                    </div>
                    <div class="simple-text">Bàn phím</div>
                    <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                    </svg>
                  </div>

                  <div class="simple-item">
                    <div class="setting-icon">
                      <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M10 .5a9.5 9.5 0 1 0 0 19 9.5 9.5 0 0 0 0-19zM10 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zM5.98 7.286l.005.002.017.005a8.811 8.811 0 0 0 .345.103c.238.067.575.157.97.248.8.183 1.8.356 2.683.356.883 0 1.882-.173 2.682-.356a19.414 19.414 0 0 0 1.316-.351l.017-.005.004-.002a.75.75 0 0 1 .462 1.428h-.003l-.007.003-.022.007a9.877 9.877 0 0 1-.386.115c-.258.073-.621.17-1.047.267-.403.092-.872.187-1.366.26v1.013l1.322 4.667a.75.75 0 0 1-1.424.469L10 11.415l-1.548 4.1a.75.75 0 0 1-1.424-.47L8.35 10.38V9.366a18.111 18.111 0 0 1-1.366-.26 20.89 20.89 0 0 1-1.433-.382l-.022-.007-.007-.002-.003-.001a.75.75 0 0 1 .462-1.428z" />
                      </svg>
                    </div>
                    <div class="simple-text">Cài đặt trợ năng</div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Messenger popover -->
  <section class="messenger-popover" id="messengerPopover" role="dialog" aria-label="Messenger" aria-hidden="true">
    <div class="mp-header">
      <div class="mp-title">Đoạn chat</div>
      <div class="mp-actions" aria-hidden="true">
        <button class="mp-icon" id="mpOptionsBtn" type="button" title="Tùy chọn" aria-haspopup="menu" aria-expanded="false" aria-controls="mpOptionsMenu">
          <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false">
            <path d="M2.25 10a1.75 1.75 0 1 1 3.5 0 1.75 1.75 0 0 1-3.5 0zM10 8.25a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5zm6 0a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5z"></path>
          </svg>
        </button>
        <button class="mp-icon" type="button" title="Mở rộng">
          <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false">
            <path d="M18.25 7.75a1 1 0 1 1-2 0V5.164l-3.293 3.293a1 1 0 1 1-1.414-1.414l3.293-3.293H12.25a1 1 0 1 1 0-2h4a2 2 0 0 1 2 2v4zm-14.5 4.5a1 1 0 1 0-2 0v4a2 2 0 0 0 2 2h4a1 1 0 1 0 0-2H5.164l3.293-3.293a1 1 0 1 0-1.414-1.414L3.75 14.836V12.25zm13.5-1a1 1 0 0 0-1 1v2.586l-3.293-3.293a1 1 0 0 0-1.414 1.414l3.293 3.293H12.25a1 1 0 1 0 0 2h4a2 2 0 0 0 2-2v-4a1 1 0 0 0-1-1zm-14.5-2.5a1 1 0 0 0 1-1V5.164l3.293 3.293a1 1 0 0 0 1.414-1.414L5.164 3.75H7.75a1 1 0 0 0 0-2h-4a2 2 0 0 0-2 2v4a1 1 0 0 0 1 1z"></path>
          </svg>
        </button>
        <button class="mp-icon" id="mpComposeBtn" type="button" title="Soạn tin">
          <svg viewBox="0 0 24 24">
            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm18-11.5a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.13 1.13 3.75 3.75L21 5.75z"></path>
          </svg>
        </button>
      </div>
    </div>

    <!-- Options menu (Chat settings) -->
    <div class="mp-options-menu" id="mpOptionsMenu" role="menu" aria-label="Cài đặt đoạn chat" aria-hidden="true">
      <div class="mp-opts-head">
        <div class="mp-opts-title">Cài đặt đoạn chat</div>
        <div class="mp-opts-sub">Tùy chỉnh trải nghiệm trên Messenger.</div>
      </div>

      <!-- ĐƯỜNG KẺ (giống Facebook): đặt 1 divider ngay dưới phần mô tả ở header -->
      <div class="mp-opts-divider is-tight" role="separator"></div>

      <button class="mp-opts-row" type="button" role="switch" aria-checked="true" data-pref="call_sounds">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <i class="mp-ico-sprite mp-ico-call-sound" aria-hidden="true"></i>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label">Âm thanh cuộc gọi đến</div>
          </span>
        </span>
        <span class="mp-opts-right" aria-hidden="true"><span class="mp-switch"></span></span>
      </button>

      <button class="mp-opts-row" type="button" role="switch" aria-checked="true" data-pref="message_sounds">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <i class="mp-ico-sprite mp-ico-message-sound" aria-hidden="true"></i>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label">Âm thanh tin nhắn</div>
          </span>
        </span>
        <span class="mp-opts-right" aria-hidden="true"><span class="mp-switch"></span></span>
      </button>

      <button class="mp-opts-row" type="button" role="switch" aria-checked="true" data-pref="new_message_pop">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <i class="mp-ico-sprite mp-ico-new-message-pop" aria-hidden="true"></i>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label">Tin nhắn mới bật lên</div>
            <div class="mp-opts-desc">Tự động mở tin nhắn mới.</div>
          </span>
        </span>
        <span class="mp-opts-right" aria-hidden="true"><span class="mp-switch"></span></span>
      </button>

      <div class="mp-opts-divider" role="separator"></div>

      <button class="mp-opts-row" type="button" data-action="privacy">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
              <g fill-rule="evenodd" transform="translate(-446 -398)">
                <g>
                  <path d="M103 201.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0" transform="translate(355 204)"></path>
                  <path d="m101 201.5 1.843 4.423a.416.416 0 0 1-.385.577h-2.916a.416.416 0 0 1-.385-.577L101 201.5z" transform="translate(355 204)"></path>
                  <path fill-rule="nonzero" d="M107.312 208.579a6.456 6.456 0 0 0 1.688-4.347v-7.118a1.57 1.57 0 0 0-1.196-1.523l-.588-.142c-2.347-.558-4.602-.949-6.216-.949-1.749 0-4.252.46-6.804 1.091A1.57 1.57 0 0 0 93 197.114v7.118c0 1.606.601 3.153 1.688 4.347 1.759 1.933 3.637 3.602 5.521 4.706a1.568 1.568 0 0 0 1.49.05l.092-.05c1.884-1.104 3.764-2.774 5.521-4.706zm-6.28 3.412a.069.069 0 0 1-.064 0c-1.73-1.014-3.505-2.59-5.17-4.422a4.956 4.956 0 0 1-1.298-3.337v-7.118c0-.03.022-.058.057-.067C96.991 196.445 99.413 196 101 196c1.587 0 4.007.444 6.443 1.047.035.009.057.037.057.067v7.118a4.957 4.957 0 0 1-1.298 3.337c-1.588 1.747-3.279 3.264-4.933 4.28l-.237.142z" transform="translate(355 204)"></path>
                </g>
              </g>
            </svg>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label">Quyền riêng tư và an toàn</div>
          </span>
        </span>
        <span class="mp-opts-right mp-chevron" aria-hidden="true">
          <svg viewBox="0 0 20 20">
            <path d="M7.25 4.5a1 1 0 0 0-.707 1.707L10.336 10l-3.793 3.793a1 1 0 0 0 1.414 1.414l4.5-4.5a1 1 0 0 0 0-1.414l-4.5-4.5a.997.997 0 0 0-.707-.293z"></path>
          </svg>
        </span>
      </button>

      <!-- ĐƯỜNG KẺ (giống Facebook): đặt divider ngay dưới “Quyền riêng tư và an toàn” -->
      <div class="mp-opts-divider is-tight" role="separator"></div>

      <button class="mp-opts-row" type="button" data-action="active_status" id="mpActiveStatusRow">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <i class="mp-ico-sprite mp-ico-active-status" aria-hidden="true"></i>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label" id="mpActiveStatusLabel">Trạng thái hoạt động: ĐANG BẬT</div>
          </span>
        </span>
      </button>

      <button class="mp-opts-row" type="button" data-action="requests">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <i class="mp-ico-sprite mp-ico-requests" aria-hidden="true"></i>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label">Tin nhắn đang chờ</div>
          </span>
        </span>
      </button>

      <button class="mp-opts-row" type="button" data-action="archived">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <i class="mp-ico-sprite mp-ico-archived" aria-hidden="true"></i>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label">Đoạn chat đã lưu trữ</div>
          </span>
        </span>
      </button>

      <button class="mp-opts-row" type="button" data-action="delivery">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <i class="mp-ico-sprite mp-ico-delivery" aria-hidden="true"></i>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label">Cài đặt gửi tin nhắn</div>
          </span>
        </span>
      </button>

      <div class="mp-opts-divider" role="separator"></div>

      <button class="mp-opts-row" type="button" data-action="restricted">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <i class="mp-ico-sprite mp-ico-restricted" aria-hidden="true"></i>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label">Tài khoản đã hạn chế</div>
          </span>
        </span>
      </button>

      <button class="mp-opts-row" type="button" data-action="blocking">
        <span class="mp-opts-left">
          <span class="mp-opts-ico" aria-hidden="true">
            <i class="mp-ico-sprite mp-ico-blocking" aria-hidden="true"></i>
          </span>
          <span class="mp-opts-text">
            <div class="mp-opts-label">Cài đặt chặn</div>
          </span>
        </span>
      </button>
    </div>

    <div class="mp-search">
      <div class="mp-searchbox">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"></path>
        </svg>
        <input type="text" aria-label="Tìm kiếm trên Messenger" placeholder="Tìm kiếm trên Messenger" />
      </div>
    </div>

    <div class="mp-tabs" role="tablist" aria-label="Bộ lọc">
      <button class="mp-tab active" type="button" role="tab" aria-selected="true" data-tab="all">Tất cả</button>
      <button class="mp-tab" type="button" role="tab" aria-selected="false" data-tab="unread">Chưa đọc</button>
      <button class="mp-tab" type="button" role="tab" aria-selected="false" data-tab="group">Nhóm</button>
      <button class="mp-tab mp-more-btn" id="mpMoreBtn" type="button" aria-label="Thêm" aria-haspopup="menu" aria-expanded="false">…</button>
      <div class="mp-more-menu" id="mpMoreMenu" role="menu" aria-label="Thêm bộ lọc" aria-hidden="true">
        <button class="mp-more-item" type="button" role="menuitem" data-more="community">Cộng đồng</button>
      </div>
    </div>

    <div class="mp-content">
      <div class="mp-empty">
        <h4>Không có đoạn chat nào</h4>
        <p>Đoạn chat mới sẽ hiển thị ở đây.</p>
      </div>
    </div>

    <div class="mp-footer">
      <a href="#" aria-label="Xem tất cả trong Messenger">
        <span>Xem tất cả trong Messenger</span>
      </a>
    </div>
  </section>

  <!-- Compose panel (standalone like Facebook): Tin nhắn mới -->
  <section class="mp-compose" id="mpCompose" aria-label="Tin nhắn mới" aria-hidden="true">
    <div class="mp-compose-header">
      <div class="mp-compose-title">Tin nhắn mới</div>
      <button class="mp-compose-close" id="mpComposeClose" type="button" aria-label="Đóng">
        <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L8.94 10l-4.72 4.72a.75.75 0 1 0 1.06 1.06L10 11.06l4.72 4.72a.75.75 0 1 0 1.06-1.06L11.06 10l4.72-4.72a.75.75 0 1 0-1.06-1.06L10 8.94 5.28 4.22z"></path>
        </svg>
      </button>
    </div>
    <div class="mp-compose-to">
      <div class="mp-compose-to-label">Đến:</div>
      <input class="mp-compose-to-input" id="mpComposeTo" type="text" autocomplete="off" />
    </div>
    <div class="mp-compose-divider" role="separator"></div>
    <div class="mp-compose-list" role="list">
      <button class="mp-compose-item" type="button" role="listitem">
        <span class="mp-compose-avatar meta-ai" aria-hidden="true">
          <img class="mp-metaai-ring" alt="Meta AI" referrerpolicy="origin-when-cross-origin" src="https://www.facebook.com/images/web_messenger/gen-ai-ring-2_36-4x.png" />
          <span class="mp-metaai-ring-overlay" aria-hidden="true"></span>
        </span>
        <span class="mp-compose-name">Meta AI
          <span class="mp-compose-verified" aria-hidden="true" title="Tài khoản đã xác minh">
            <svg viewBox="0 0 12 13" width="12" height="12" fill="currentColor" aria-hidden="true" focusable="false">
              <title>Tài khoản đã xác minh</title>
              <g fill-rule="evenodd" transform="translate(-98 -917)">
                <path d="m106.853 922.354-3.5 3.5a.499.499 0 0 1-.706 0l-1.5-1.5a.5.5 0 1 1 .706-.708l1.147 1.147 3.147-3.147a.5.5 0 1 1 .706.708m3.078 2.295-.589-1.149.588-1.15a.633.633 0 0 0-.219-.82l-1.085-.7-.065-1.287a.627.627 0 0 0-.6-.603l-1.29-.066-.703-1.087a.636.636 0 0 0-.82-.217l-1.148.588-1.15-.588a.631.631 0 0 0-.82.22l-.701 1.085-1.289.065a.626.626 0 0 0-.6.6l-.066 1.29-1.088.702a.634.634 0 0 0-.216.82l.588 1.149-.588 1.15a.632.632 0 0 0 .219.819l1.085.701.065 1.286c.014.33.274.59.6.604l1.29.065.703 1.088c.177.27.53.362.82.216l1.148-.588 1.15.589a.629.629 0 0 0 .82-.22l.701-1.085 1.286-.064a.627.627 0 0 0 .604-.601l.065-1.29 1.088-.703a.633.633 0 0 0 .216-.819"></path>
              </g>
            </svg>
          </span>
        </span>
      </button>
      <button class="mp-compose-item" type="button" role="listitem">
        <span class="mp-compose-avatar user" aria-hidden="true">
          <img class="mp-user-avatar-img" alt="<?= $currentUserNameSafe ?>" referrerpolicy="origin-when-cross-origin" src="<?= fb_escape($currentUserAvatar) ?>" />
          <span class="mp-user-avatar-overlay" aria-hidden="true"></span>
        </span>
        <span class="mp-compose-name"><?= $currentUserNameSafe ?></span>
      </button>
    </div>
  </section>

  <script>
    /* ===================== JS - Comment tiếng Việt chi tiết ===================== */

    const fbSeeMoreBtn = document.querySelector('.fb-see-more');
    if (fbSeeMoreBtn) {
      fbSeeMoreBtn.onclick = function() {
        document.body.classList.toggle('show-more');
      };
    }

    // ===== Create Post Modal =====
    (function() {
      const backdrop = document.getElementById('postModalBackdrop');
      const closeBtn = document.getElementById('postModalClose');
      const statusInput = document.getElementById('fbStatusInput');
      const statusBar = document.querySelector('.fb-status-bar');
      const contentEl = document.getElementById('postModalContent');
      const submitBtn = document.getElementById('postModalSubmit');
      const formEl = document.getElementById('postModalForm');
      const mediaInput = document.getElementById('postModalMediaInput');
      const pickMediaBtn = document.getElementById('postModalPickMedia');
      const previewEl = document.getElementById('postModalMediaPreview');
      const textWrapEl = document.getElementById('postModalTextWrap');
      const bgBtn = document.getElementById('postModalBgBtn');
      const bgPickerEl = document.getElementById('postModalBgPicker');

      if (!backdrop || !closeBtn || !statusBar || !contentEl || !submitBtn || !formEl) return;

      function setOpen(open) {
        if (open) {
          backdrop.classList.add('is-open');
          backdrop.setAttribute('aria-hidden', 'false');
          try {
            document.body.style.overflow = 'hidden';
          } catch (e) {}
          setTimeout(() => {
            try {
              contentEl.focus();
            } catch (e) {}
          }, 0);
        } else {
          backdrop.classList.remove('is-open');
          backdrop.setAttribute('aria-hidden', 'true');
          try {
            document.body.style.overflow = '';
          } catch (e) {}
          try {
            if (statusInput) statusInput.blur();
          } catch (e) {}
        }
      }

      function updateSubmit() {
        const hasText = (contentEl.value || '').trim().length > 0;
        const hasMedia = !!(mediaInput && mediaInput.files && mediaInput.files.length);
        const enabled = hasText || hasMedia;
        submitBtn.disabled = !enabled;
        submitBtn.classList.toggle('is-enabled', enabled);
      }

      function setBg(bg) {
        if (!textWrapEl) return;
        const v = String(bg || 'none');
        textWrapEl.setAttribute('data-bg', v);
      }

      function openBgPicker() {
        if (!bgPickerEl || !bgBtn || bgBtn.disabled) return;
        bgPickerEl.hidden = false;
        bgPickerEl.classList.remove('is-open');
        requestAnimationFrame(() => {
          bgPickerEl.classList.add('is-open');
        });
      }

      function closeBgPicker() {
        if (!bgPickerEl) return;
        bgPickerEl.classList.remove('is-open');
        // allow transition to play before hiding
        setTimeout(() => {
          if (!bgPickerEl.classList.contains('is-open')) {
            bgPickerEl.hidden = true;
          }
        }, 240);
      }

      function clearBg() {
        setBg('none');
        closeBgPicker();
      }

      function syncBgAvailability() {
        const hasMedia = !!(mediaInput && mediaInput.files && mediaInput.files.length);
        if (bgBtn) {
          bgBtn.disabled = hasMedia;
          bgBtn.classList.toggle('is-disabled', hasMedia);
        }
        if (hasMedia) clearBg();
      }

      function setFiles(files) {
        if (!mediaInput) return;
        try {
          const dt = new DataTransfer();
          (files || []).forEach((f) => {
            try { dt.items.add(f); } catch (_e) {}
          });
          mediaInput.files = dt.files;
        } catch (_e) {
          // best-effort; if not supported, fall back to clearing
          mediaInput.value = '';
        }
      }

      function renderPreview() {
        if (!mediaInput || !previewEl) return;
        const files = Array.from(mediaInput.files || []);
        if (!files.length) {
          previewEl.innerHTML = '';
          previewEl.hidden = true;
          return;
        }
        previewEl.hidden = false;
        previewEl.innerHTML = files.map((f, idx) => {
          const url = URL.createObjectURL(f);
          return `
            <div class="post-modal-media-item" data-idx="${idx}">
              <img src="${url}" alt="Ảnh đã chọn">
              <button type="button" class="post-modal-media-remove" data-remove="${idx}" aria-label="Bỏ ảnh">×</button>
            </div>
          `;
        }).join('');
      }

      // Open when clicking input or any status actions
      if (statusInput) {
        statusInput.addEventListener('click', () => setOpen(true));
        statusInput.addEventListener('focus', () => setOpen(true));
      }

      statusBar.addEventListener('click', (e) => {
        const t = e.target;
        if (!t) return;
        if (t.closest && (t.closest('.fb-status-input') || t.closest('.fb-status-actions') || t.closest('.fb-status-icon'))) {
          setOpen(true);
        }
      });

      // Clicking the Photo icon should open picker like Facebook
      const statusPhotoBtn = document.querySelector('.fb-status-actions .fb-status-icon:nth-child(2)');
      if (statusPhotoBtn) {
        statusPhotoBtn.addEventListener('click', (e) => {
          e.preventDefault();
          setOpen(true);
          setTimeout(() => {
            try {
              if (pickMediaBtn) pickMediaBtn.click();
            } catch (_e) {}
          }, 0);
        });
      }

      if (pickMediaBtn && mediaInput) {
        pickMediaBtn.addEventListener('click', () => {
          try { mediaInput.click(); } catch (_e) {}
        });
      }

      if (mediaInput) {
        mediaInput.addEventListener('change', () => {
          renderPreview();
          syncBgAvailability();
          updateSubmit();
        });
      }

      if (bgBtn && bgPickerEl) {
        bgBtn.addEventListener('click', () => {
          if (bgBtn.disabled) return;
          if (bgPickerEl.hidden || !bgPickerEl.classList.contains('is-open')) {
            openBgPicker();
          } else {
            closeBgPicker();
          }
        });
      }

      if (bgPickerEl && textWrapEl) {
        bgPickerEl.addEventListener('click', (e) => {
          const sw = e.target && e.target.closest && e.target.closest('[data-bg]');
          if (!sw) return;
          const bg = sw.getAttribute('data-bg') || 'none';
          setBg(bg);
          closeBgPicker();
          try { contentEl.focus(); } catch (_e) {}
        });
      }

      if (previewEl) {
        previewEl.addEventListener('click', (e) => {
          const btn = e.target && e.target.closest && e.target.closest('[data-remove]');
          if (!btn || !mediaInput) return;
          const idx = Number(btn.getAttribute('data-remove'));
          if (!Number.isFinite(idx)) return;
          const files = Array.from(mediaInput.files || []);
          const next = files.filter((_f, i) => i !== idx);
          setFiles(next);
          renderPreview();
          updateSubmit();
        });
      }

      closeBtn.addEventListener('click', () => setOpen(false));
      backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) setOpen(false);
      });

      document.addEventListener('click', (e) => {
        if (!bgPickerEl || bgPickerEl.hidden || !bgPickerEl.classList.contains('is-open')) return;
        const t = e.target;
        if (t && (t.closest('#postModalBgPicker') || t.closest('#postModalBgBtn'))) return;
        closeBgPicker();
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && backdrop.classList.contains('is-open')) setOpen(false);
      });

      contentEl.addEventListener('input', updateSubmit);
      updateSubmit();
      syncBgAvailability();

      // Submit via AJAX (avoid full page reload)
      formEl.addEventListener('submit', async (e) => {
        e.preventDefault();
        const content = (contentEl.value || '').trim();
        const hasMedia = !!(mediaInput && mediaInput.files && mediaInput.files.length);
        if (!content && !hasMedia) return;

        submitBtn.disabled = true;
        submitBtn.classList.remove('is-enabled');

        try {
          const fd = new FormData(formEl);
          fd.append('ajax', '1');

          const resp = await fetch(formEl.action, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: fd
          });

          const data = await resp.json().catch(() => null);
          if (data && data.ok) {
            // Close + reset; realtime insert comes from socket event (and response is a fallback)
            contentEl.value = '';
            if (mediaInput) mediaInput.value = '';
            if (previewEl) {
              previewEl.innerHTML = '';
              previewEl.hidden = true;
            }
            clearBg();
            syncBgAvailability();
            updateSubmit();
            setOpen(false);

            try {
              if (data.post && window.fbFeedInsertPost) {
                window.fbFeedInsertPost(data.post);
              }
            } catch (_e) {}
          } else {
            // restore submit state
            updateSubmit();
          }
        } catch (_err) {
          updateSubmit();
        }
      });
    })();

    const btn = document.getElementById("fbFooterMore");
    const popup = document.getElementById("fbMorePopup");

    if (btn && popup) {
      btn.onclick = (e) => {
        e.stopPropagation();
        popup.style.display =
          popup.style.display === "flex" ? "none" : "flex";
      };

      document.addEventListener("click", () => {
        popup.style.display = "none";
      });
    }

    /* ===== PHP SESSION → JS ===== */
    const CURRENT_USER_ID = <?= (int)$currentUser['id'] ?>;

    // user đang chat, ví dụ lấy từ URL ?to=2
    const TO_USER_ID = <?= (int)$toUserId ?>;

    // ===== SOCKET =====
    (function() {
      try {
        // If header already created a shared socket, reuse it.
        let socket = (window.fbSocket && typeof window.fbSocket.emit === 'function') ? window.fbSocket : null;

        // Socket.IO:
        // - Local dev (http://localhost): connect to http://localhost:3000
        // - Cloudflare Tunnel / HTTPS: connect to same-origin and route /socket.io -> Node (WebSocket)
        const host = (window.location && window.location.hostname) ? window.location.hostname : 'localhost';
        const proto = (window.location && window.location.protocol) ? window.location.protocol : 'http:';
        const isLocalHost = host === 'localhost' || host === '127.0.0.1';

        // Cloudflare Tunnel pattern:
        // - app.<domain>   -> XAMPP/Apache (:80)
        // - socket.<domain> -> Node Socket.IO (:3000)
        // If we detect app.* host, connect Socket.IO to socket.* explicitly.
        const socketHost = (!isLocalHost && host.toLowerCase().startsWith('app.'))
          ? `socket.${host.slice(4)}`
          : '';

        // Connection strategy:
        // - If using Cloudflare Tunnel pattern (app.* -> socket.*), connect to socketHost.
        // - If HTTPS (reverse-proxy likely), connect same-origin and route /socket.io -> Node.
        // - Otherwise (plain HTTP, including LAN/IP), connect directly to :3000.
        const useSameOrigin = (proto === 'https:') && !socketHost;

        const socketOpts = {
          path: '/socket.io',
          transports: ['websocket', 'polling'],
          withCredentials: true
        };

        if (!socket) {
          const socketUrl = socketHost ? `${proto}//${socketHost}` : '';
          socket = socketUrl
            ? io(socketUrl, socketOpts)
            : (useSameOrigin
              ? io(undefined, socketOpts)
              : io(`http://${host}:3000`, socketOpts));

          // expose for feed realtime
          window.fbSocket = socket;
        }

        const socketStatusEl = document.getElementById('fbSocketStatus');
        function setSocketStatus(text, show) {
          // User requested: run hidden (no on-page socket banner).
          // Keep the element in DOM (in case of future debugging), but never display it.
          if (!socketStatusEl) return;
          socketStatusEl.textContent = text;
          socketStatusEl.style.display = 'none';
        }

        // show status when disconnected/errors (helps debug)
        setSocketStatus('socket: connecting…', true);
        socket.on('connect', () => setSocketStatus('socket: connected', false));
        socket.on('disconnect', (reason) => setSocketStatus('socket: disconnected', true));
        socket.on('connect_error', (err) => {
          console.warn('socket connect_error', err);
          const msg = (err && err.message) ? String(err.message) : '';
          const hint = useSameOrigin
            ? ' (HTTPS: ensure /socket.io is proxied to :3000)'
            : ' (HTTP: ensure Node is running on :3000)';
          setSocketStatus(`socket: connect_error${hint}${msg ? ' - ' + msg : ''}`, true);
        });

        // join room theo userId thật
        socket.emit("join", CURRENT_USER_ID);

        // join shared feed room
        socket.emit("joinFeed");

        // ===== CHAT POPUP STATE =====
        const chatPop = document.getElementById('fbChatPop');
        const chatAvatarEl = document.getElementById('fbChatAvatar');
        const chatDotEl = document.getElementById('fbChatDot');
        const chatNameEl = document.getElementById('fbChatName');
        const chatSubEl = document.getElementById('fbChatSub');
        const chatBodyEl = document.getElementById('fbChatBody');
        const chatForm = document.getElementById('fbChatForm');
        const chatInput = document.getElementById('fbChatInput');
        const chatSendBtn = document.getElementById('fbChatSend');
        const chatLikeBtn = document.getElementById('fbChatLike');
        const chatCloseBtn = document.getElementById('fbChatClose');
        const chatMinBtn = document.getElementById('fbChatMin');
        const chatSoundBtn = document.getElementById('fbChatSound');
        const chatProfileLink = document.getElementById('fbChatProfileLink');
        const chatCallBtn = document.getElementById('fbChatCall');
        const chatVideoBtn = document.getElementById('fbChatVideo');
        const chatSettingsBtn = document.getElementById('fbChatSettings');
        const chatSettingsMenu = document.getElementById('fbChatSettingsMenu');
        const chatMenuMuteState = document.getElementById('fbChatMenuMuteState');
        const chatMenuReadState = document.getElementById('fbChatMenuReadState');
        const chatCallLayer = document.getElementById('fbChatCallLayer');
        const chatRemoteVideo = document.getElementById('fbChatRemoteVideo');
        const chatLocalVideo = document.getElementById('fbChatLocalVideo');
        const chatRemoteAudio = document.getElementById('fbChatRemoteAudio');
        const callScreen = document.getElementById('fbCallScreen');
        const callAvatarEl = document.getElementById('fbCallAvatar');
        const callNameEl = document.getElementById('fbCallName');
        const callStatusEl = document.getElementById('fbCallStatus');
        const callAcceptBtn = document.getElementById('fbCallAccept');
        const callDeclineBtn = document.getElementById('fbCallDecline');
        const callMuteBtn = document.getElementById('fbCallMute');
        const callCamBtn = document.getElementById('fbCallCam');
        const callHangupBtn = document.getElementById('fbCallHangup');
        const callToneEl = document.getElementById('fbCallTone');
        const chatPlusBtn = document.getElementById('fbChatToolPlus');
        const chatMicBtn = document.getElementById('fbChatToolMic');
        const chatPhotoBtn = document.getElementById('fbChatToolPhoto');
        const chatFileInput = document.getElementById('fbChatFileInput');
        const contactsRoot = document.getElementById('fbContactsList');

        let activeChatUserId = 0;
        let activeChatUserName = '';
        let activeChatUserAvatar = '';
        let lastMessageId = 0;
        const lastIdByUser = new Map();
        let activeContactEl = null;
        let chatSoundEnabled = true;
        const chatMutedThreads = new Set(); // userId muted in this browser
        let chatReadReceiptsEnabled = true; // local toggle (UI only)

        // ===== CALL (WebRTC) =====
        let callPc = null;
        let callLocalStream = null;
        let callPeerUserId = 0;
        let callKind = ''; // 'audio' | 'video'
        let callInProgress = false;
        let callState = 'idle';
        let pendingIncoming = null; // { from, kind, sdp }
        let callUiName = '';
        let callUiAvatar = '';
        let callMicEnabled = true;
        let callCamEnabled = true;

        const RTC_CFG = {
          iceServers: <?= json_encode($webrtcIceServers, JSON_UNESCAPED_SLASHES) ?>
        };
        // load audio (use project's sound file)
        let chatAudio = null;
        try {
          chatAudio = new Audio('../sound/messenger_pc_web.mp3');
          chatAudio.preload = 'auto';
        } catch (_e) { chatAudio = null; }

        function getCallToneSrc(kind) {
          return (kind === 'video') ? '../sound/messenger_video_call.mp3' : '../sound/messenger_video_call.mp3';
        }

        async function playCallTone(kind) {
          if (!callToneEl) return;
          try {
            callToneEl.src = getCallToneSrc(kind);
            callToneEl.currentTime = 0;
            await callToneEl.play();
          } catch (_e) {
            // autoplay might be blocked; will play after user gesture (accept/decline)
          }
        }

        function stopCallTone() {
          if (!callToneEl) return;
          try {
            callToneEl.pause();
            callToneEl.currentTime = 0;
          } catch (_e) {}
        }

        function setCallUiInfo(name, avatar) {
          callUiName = String(name || '');
          callUiAvatar = String(avatar || '');
          if (callNameEl) callNameEl.textContent = callUiName || '';
          if (callAvatarEl) {
            callAvatarEl.src = callUiAvatar || '';
            callAvatarEl.alt = callUiName || '';
          }
        }

        function setCallStatus(text) {
          if (callStatusEl) callStatusEl.textContent = String(text || '');
        }

        function setCallControls({ showAccept, showDecline, showMute, showCam, showHangup }) {
          if (callAcceptBtn) callAcceptBtn.hidden = !showAccept;
          if (callDeclineBtn) callDeclineBtn.hidden = !showDecline;
          if (callMuteBtn) callMuteBtn.hidden = !showMute;
          if (callCamBtn) callCamBtn.hidden = !showCam;
          if (callHangupBtn) callHangupBtn.hidden = !showHangup;
        }

        function setCallState(next) {
          callState = String(next || 'idle');

          const isVideo = (callKind === 'video');
          if (callScreen && callScreen.classList) {
            callScreen.classList.toggle('is-video', !!isVideo);
          }

          if (callState === 'idle') {
            stopCallTone();
            setCallControls({ showAccept: false, showDecline: false, showMute: false, showCam: false, showHangup: false });
            setCallUiVisible(false);
            return;
          }

          // always show overlay when not idle
          setCallUiVisible(true, callKind);

          if (callState === 'incoming_ringing') {
            setCallStatus('Đang gọi…');
            setCallControls({ showAccept: true, showDecline: true, showMute: false, showCam: false, showHangup: false });
            playCallTone(callKind);
            return;
          }

          if (callState === 'outgoing_calling') {
            setCallStatus('Đang gọi…');
            setCallControls({ showAccept: false, showDecline: false, showMute: false, showCam: false, showHangup: true });
            playCallTone(callKind);
            return;
          }

          if (callState === 'outgoing_ringing') {
            setCallStatus('Đang đổ chuông…');
            setCallControls({ showAccept: false, showDecline: false, showMute: false, showCam: false, showHangup: true });
            // keep tone
            return;
          }

          if (callState === 'connecting') {
            stopCallTone();
            setCallStatus('Đang kết nối…');
            setCallControls({ showAccept: false, showDecline: false, showMute: true, showCam: isVideo, showHangup: true });
            return;
          }

          if (callState === 'in_call') {
            stopCallTone();
            setCallStatus('Đang gọi');
            setCallControls({ showAccept: false, showDecline: false, showMute: true, showCam: isVideo, showHangup: true });
            return;
          }

          if (callState === 'ended') {
            stopCallTone();
            setCallControls({ showAccept: false, showDecline: false, showMute: false, showCam: false, showHangup: false });
            setCallStatus('Cuộc gọi đã kết thúc');
            setTimeout(() => {
              if (callState === 'ended') {
                cleanupCall();
                setCallState('idle');
              }
            }, 900);
          }
        }

        function escapeHtml(s) {
          return String(s || '').replace(/[&"'<>]/g, (m) => ({
            '&': '&amp;',
            '"': '&quot;',
            "'": '&#39;',
            '<': '&lt;',
            '>': '&gt;'
          }[m]));
        }

        function setChatOpen(open) {
          if (!chatPop) return;
          chatPop.classList.toggle('is-open', !!open);
          chatPop.setAttribute('aria-hidden', open ? 'false' : 'true');

          // close settings menu if chat is closed
          if (!open) setChatSettingsMenuOpen(false);
        }

        function setChatMinimized(min) {
          if (!chatPop) return;
          chatPop.classList.toggle('is-min', !!min);

          // close settings menu on minimize
          if (min) setChatSettingsMenuOpen(false);
        }

        function setChatSettingsMenuOpen(open) {
          if (!chatSettingsMenu || !chatSettingsBtn) return;
          chatSettingsMenu.hidden = !open;
          chatSettingsBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
          if (open) {
            updateChatSettingsMenuUi();
            positionChatSettingsMenu();
          }
        }

        function positionChatSettingsMenu() {
          if (!chatSettingsMenu || !chatSettingsBtn) return;
          try {
            const btnRect = chatSettingsBtn.getBoundingClientRect();
            const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
            const vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);

            const pad = 10;
            const menuWidth = 310;

            // Place below the caret button
            let top = Math.round(btnRect.bottom + 10);
            // If near bottom, clamp within viewport (keep a little margin)
            const maxTop = Math.max(pad, vh - 385 - pad);
            top = Math.min(top, maxTop);
            top = Math.max(pad, top);

            // Messenger-like: menu opens to the left of the button (arrow near top-right)
            const desiredArrowFromRight = 26; // px
            const desiredArrowX = Math.round(btnRect.left + (btnRect.width / 2));
            let left = desiredArrowX - (menuWidth - desiredArrowFromRight);
            left = Math.max(pad, Math.min(left, vw - menuWidth - pad));

            // Arrow should point to the button center
            const arrowLeft = Math.max(16, Math.min(menuWidth - 28, desiredArrowX - left - 6));
            chatSettingsMenu.style.setProperty('--fb-chat-menu-arrow-left', `${arrowLeft}px`);

            chatSettingsMenu.style.top = `${top}px`;
            chatSettingsMenu.style.left = `${left}px`;
            chatSettingsMenu.style.width = `${menuWidth}px`;
          } catch (_e) {}
        }

        function updateChatSettingsMenuUi() {
          const hasThread = !!activeChatUserId;

          if (chatMenuMuteState) {
            const isMuted = hasThread ? chatMutedThreads.has(activeChatUserId) : false;
            chatMenuMuteState.textContent = isMuted ? 'Bật' : 'Tắt';
          }
          if (chatMenuReadState) {
            chatMenuReadState.textContent = chatReadReceiptsEnabled ? 'Bật' : 'Tắt';
          }

          try {
            if (!chatSettingsMenu) return;
            const items = chatSettingsMenu.querySelectorAll('.fb-chat-menu-item[data-action]');
            (items || []).forEach((btn) => {
              const action = String(btn.getAttribute('data-action') || '');
              // Disable thread-specific actions when no active thread
              const needsThread = ['view_profile', 'toggle_mute', 'block', 'restrict', 'verify_e2ee', 'toggle_read_receipts', 'archive', 'delete_thread'].includes(action);
              btn.disabled = needsThread && !hasThread;
            });
          } catch (_e) {}
        }

        function clearCurrentChatUiOnly() {
          if (!chatBodyEl || !activeChatUserId) return;
          // UI-only clear (does not delete from DB)
          chatBodyEl.innerHTML = renderChatEmptyState(activeChatUserName, activeChatUserAvatar, !!(chatDotEl && chatDotEl.classList.contains('is-online')));
          try { lastIdByUser.set(activeChatUserId, 0); } catch (_e) {}
          try { lastChatTimeByUser.delete(activeChatUserId); } catch (_e) {}
        }

        function setCallUiVisible(visible, kind) {
          if (!chatCallLayer) return;
          chatCallLayer.hidden = !visible;
          const k = kind || callKind;
          if (chatRemoteVideo) chatRemoteVideo.hidden = !(visible && k === 'video');
          if (chatLocalVideo) chatLocalVideo.hidden = !(visible && k === 'video');
          if (chatRemoteAudio) chatRemoteAudio.hidden = !(visible && k === 'audio');
        }

        function stopStream(stream) {
          try {
            if (!stream) return;
            stream.getTracks().forEach((t) => {
              try { t.stop(); } catch (_e) {}
            });
          } catch (_e) {}
        }

        function cleanupCall() {
          callInProgress = false;
          stopCallTone();
          try {
            if (callPc) callPc.ontrack = null;
          } catch (_e) {}
          try {
            if (callPc) callPc.onicecandidate = null;
          } catch (_e) {}
          try {
            if (callPc) callPc.close();
          } catch (_e) {}
          callPc = null;

          stopStream(callLocalStream);
          callLocalStream = null;

          callPeerUserId = 0;
          callKind = '';
          pendingIncoming = null;
          callMicEnabled = true;
          callCamEnabled = true;
          if (callMuteBtn && callMuteBtn.classList) callMuteBtn.classList.remove('is-on');
          if (callCamBtn && callCamBtn.classList) callCamBtn.classList.remove('is-on');

          try {
            if (chatRemoteVideo) chatRemoteVideo.srcObject = null;
            if (chatLocalVideo) chatLocalVideo.srcObject = null;
            if (chatRemoteAudio) chatRemoteAudio.srcObject = null;
          } catch (_e) {}

          setCallUiVisible(false);
        }

        function endCall(sendSignal) {
          // IMPORTANT: capture peer/kind BEFORE cleanupCall() resets them.
          // Also handle the race where user hangs up very quickly before ensureCallPc() sets callPeerUserId.
          const stateBefore = callState;
          const peer = callPeerUserId || ((stateBefore === 'outgoing_calling' || stateBefore === 'outgoing_ringing' || stateBefore === 'connecting' || stateBefore === 'in_call') ? (activeChatUserId || 0) : 0);
          const kindBefore = callKind;
          cleanupCall();
          setCallState('idle');
          if (sendSignal && peer) {
            try {
              // If caller cancels before connected, emit cancel.
              if (stateBefore === 'outgoing_calling' || stateBefore === 'outgoing_ringing') {
                socket.emit('webrtc:cancel', { from_user: CURRENT_USER_ID, to_user: peer, kind: kindBefore || undefined });
              } else {
                socket.emit('webrtc:hangup', { from_user: CURRENT_USER_ID, to_user: peer });
              }
            } catch (_e) {}
          }
        }

        async function ensureCallPc(peerId, kind) {
          if (!peerId) throw new Error('no peer');
          if (callPc) return callPc;

          callPc = new RTCPeerConnection(RTC_CFG);
          callPeerUserId = peerId;
          callKind = kind;
          callInProgress = true;

          callPc.onicecandidate = (evt) => {
            try {
              if (!evt || !evt.candidate) return;
              // debug log
              try { console.debug('webrtc:ice ->', evt.candidate); } catch (_e) {}
              socket.emit('webrtc:ice', {
                from_user: CURRENT_USER_ID,
                to_user: peerId,
                candidate: evt.candidate
              });
            } catch (_e) {}
          };

          callPc.ontrack = (evt) => {
            try {
              const stream = evt.streams && evt.streams[0];
              if (!stream) return;
              if (kind === 'video') {
                if (chatRemoteVideo) chatRemoteVideo.srcObject = stream;
                // ensure autoplay: try to play when remote stream attached
                try { if (chatRemoteVideo && typeof chatRemoteVideo.play === 'function') chatRemoteVideo.play().catch(()=>{}); } catch (_e) {}
              } else {
                if (chatRemoteAudio) chatRemoteAudio.srcObject = stream;
                try { if (chatRemoteAudio && typeof chatRemoteAudio.play === 'function') chatRemoteAudio.play().catch(()=>{}); } catch (_e) {}
              }

              // once we receive remote media, treat as in-call
              if (callState === 'connecting') setCallState('in_call');
            } catch (_e) {}
          };

          // local media
          const constraints = (kind === 'video') ? { audio: true, video: true } : { audio: true, video: false };
          callLocalStream = await navigator.mediaDevices.getUserMedia(constraints);
          callLocalStream.getTracks().forEach((track) => {
            try { callPc.addTrack(track, callLocalStream); } catch (_e) {}
          });

          // Show overlay, but let the caller/callee state machine decide status text + controls.
          setCallUiVisible(true, kind);
          try {
            if (kind === 'video') {
              if (chatLocalVideo) chatLocalVideo.srcObject = callLocalStream;
            }
          } catch (_e) {}

          return callPc;
        }

        async function startCall(kind) {
          if (!activeChatUserId) return;
          if (!socket || !socket.connected) {
            alert('Realtime chưa kết nối (Socket). Vui lòng tải lại trang hoặc kiểm tra server socket.');
            return;
          }
          if (!window.RTCPeerConnection || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Trình duyệt không hỗ trợ cuộc gọi WebRTC.');
            return;
          }

          // toggle: if already calling this user, hang up
          if (callPeerUserId && callPeerUserId === activeChatUserId) {
            endCall(true);
            return;
          }

          // end any existing call
          if (callPeerUserId) endCall(true);

          try {
            // Set these immediately to avoid the "hang up too fast" race.
            callPeerUserId = activeChatUserId;
            callKind = kind;
            setCallUiInfo(activeChatUserName, activeChatUserAvatar);
            setCallState('outgoing_calling');
            const pc = await ensureCallPc(activeChatUserId, kind);
            const offer = await pc.createOffer({
              offerToReceiveAudio: true,
              offerToReceiveVideo: kind === 'video'
            });
            await pc.setLocalDescription(offer);
            socket.emit('webrtc:offer', {
              from_user: CURRENT_USER_ID,
              to_user: activeChatUserId,
              kind,
              sdp: pc.localDescription
            });
          } catch (e) {
            console.warn('startCall failed', e);
            alert('Không thể bắt đầu cuộc gọi.');
            cleanupCall();
            setCallState('idle');
          }
        }

        function toggleMic() {
          callMicEnabled = !callMicEnabled;
          try {
            if (callLocalStream) {
              callLocalStream.getAudioTracks().forEach((t) => { t.enabled = callMicEnabled; });
            }
          } catch (_e) {}
          if (callMuteBtn && callMuteBtn.classList) callMuteBtn.classList.toggle('is-on', !callMicEnabled);
        }

        function toggleCam() {
          callCamEnabled = !callCamEnabled;
          try {
            if (callLocalStream) {
              callLocalStream.getVideoTracks().forEach((t) => { t.enabled = callCamEnabled; });
            }
          } catch (_e) {}
          if (callCamBtn && callCamBtn.classList) callCamBtn.classList.toggle('is-on', !callCamEnabled);
        }

        async function acceptIncomingCall() {
          if (!pendingIncoming) return;
          const from = Number(pendingIncoming.from) || 0;
          const kind = (pendingIncoming.kind === 'video') ? 'video' : 'audio';
          const sdp = pendingIncoming.sdp;
          if (!from || !sdp) return;

          try {
            stopCallTone();
            callKind = kind;
            setCallState('connecting');
            const pc = await ensureCallPc(from, kind);
            await pc.setRemoteDescription(sdp);
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            try { socket.emit('webrtc:accepted', { from_user: CURRENT_USER_ID, to_user: from, kind }); } catch (_e) {}
            socket.emit('webrtc:answer', {
              from_user: CURRENT_USER_ID,
              to_user: from,
              kind,
              sdp: pc.localDescription
            });
            pendingIncoming = null;
          } catch (e) {
            console.warn('acceptIncomingCall failed', e);
            endCall(false);
          }
        }

        function declineIncomingCall() {
          if (!pendingIncoming) return;
          const from = Number(pendingIncoming.from) || 0;
          pendingIncoming = null;
          stopCallTone();
          try {
            if (from) socket.emit('webrtc:reject', { from_user: CURRENT_USER_ID, to_user: from });
          } catch (_e) {}
          cleanupCall();
          setCallState('idle');
        }

        function findContactUser(otherUserId) {
          let item = null;
          try {
            item = contactsRoot && contactsRoot.querySelector
              ? contactsRoot.querySelector(`.fb-contact-item[data-user-id="${otherUserId}"]`)
              : null;
          } catch (_e) { item = null; }

          return {
            id: otherUserId,
            name: item ? String(item.dataset && item.dataset.userName || '') : `User ${otherUserId}`,
            avatar: item ? String(item.dataset && item.dataset.userAvatar || '') : '../assets/images/default-avatar.png',
            online: item ? (String(item.dataset && item.dataset.userOnline || '') === '1') : false,
            last_seen: item ? String(item.dataset && item.dataset.userLastSeen || '') : '',
            __el: item
          };
        }

        // Chat settings dropdown
        if (chatSettingsBtn) {
          chatSettingsBtn.setAttribute('aria-haspopup', 'menu');
          chatSettingsBtn.setAttribute('aria-expanded', 'false');

          chatSettingsBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const open = !!(chatSettingsMenu && !chatSettingsMenu.hidden);
            setChatSettingsMenuOpen(!open);
          });
        }

        if (chatSettingsMenu) {
          chatSettingsMenu.addEventListener('click', (e) => {
            const btn = e.target && e.target.closest ? e.target.closest('.fb-chat-menu-item[data-action]') : null;
            if (!btn || !chatSettingsMenu.contains(btn)) return;
            const action = String(btn.getAttribute('data-action') || '');

            // Keep menu open for toggles? Messenger usually keeps it open; but simplest: close after click.
            function closeMenuSoon() {
              setTimeout(() => setChatSettingsMenuOpen(false), 0);
            }

            if (action === 'view_profile') {
              if (chatProfileLink && chatProfileLink.href && !chatProfileLink.href.includes('javascript:void')) {
                window.location.href = chatProfileLink.href;
              }
              closeMenuSoon();
              return;
            }

            if (action === 'toggle_mute') {
              if (activeChatUserId) {
                if (chatMutedThreads.has(activeChatUserId)) chatMutedThreads.delete(activeChatUserId);
                else chatMutedThreads.add(activeChatUserId);
              }
              updateChatSettingsMenuUi();
              closeMenuSoon();
              return;
            }

            if (action === 'toggle_read_receipts') {
              chatReadReceiptsEnabled = !chatReadReceiptsEnabled;
              updateChatSettingsMenuUi();
              closeMenuSoon();
              return;
            }

            if (action === 'delete_thread') {
              if (!activeChatUserId) return;
              const ok = confirm('Xóa đoạn chat này? (Sẽ xóa toàn bộ tin nhắn giữa 2 bạn trong database)');
              if (!ok) {
                closeMenuSoon();
                return;
              }

              (async () => {
                try {
                  const resp = await fetch('../actions/delete_conversation.php', {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                      'Accept': 'application/json',
                      'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `friend_id=${encodeURIComponent(activeChatUserId)}`
                  });

                  const data = await resp.json().catch(() => null);
                  if (resp.ok && data && data.ok) {
                    clearCurrentChatUiOnly();
                  } else {
                    alert('Không thể xóa đoạn chat.');
                  }
                } catch (_e) {
                  alert('Không thể xóa đoạn chat.');
                }
              })();

              closeMenuSoon();
              return;
            }

            if (action === 'e2ee_info') {
              try {
                window.open('https://www.facebook.com/help/messenger-app/1084673321594605/', '_blank', 'noopener');
              } catch (_e) {
                alert('Tin nhắn và cuộc gọi được bảo mật bằng tính năng mã hóa đầu cuối.');
              }
              closeMenuSoon();
              return;
            }

            if (action === 'open_messenger') {
              try {
                window.open('https://www.messenger.com/', '_blank', 'noopener');
              } catch (_e) {
                alert('Mở trong Messenger');
              }
              closeMenuSoon();
              return;
            }

            // Remaining actions: placeholder (UI exists as requested)
            alert('Chức năng này đang phát triển.');
            closeMenuSoon();
          });
        }

        // Close menu on outside click + ESC
        document.addEventListener('click', (e) => {
          if (!chatSettingsMenu || !chatSettingsBtn) return;
          if (chatSettingsMenu.hidden) return;
          const t = e.target;
          if (t === chatSettingsBtn || (chatSettingsBtn.contains && chatSettingsBtn.contains(t))) return;
          if (chatSettingsMenu.contains && chatSettingsMenu.contains(t)) return;
          setChatSettingsMenuOpen(false);
        });

        document.addEventListener('keydown', (e) => {
          if (e.key !== 'Escape') return;
          if (!chatSettingsMenu || chatSettingsMenu.hidden) return;
          setChatSettingsMenuOpen(false);
        });

        window.addEventListener('resize', () => {
          if (!chatSettingsMenu || chatSettingsMenu.hidden) return;
          positionChatSettingsMenu();
        });

        function setActiveContactEl(el) {
          try {
            if (contactsRoot) {
              const all = contactsRoot.querySelectorAll('.fb-contact-item.is-active');
              (all || []).forEach((x) => x.classList.remove('is-active'));
            }
          } catch (_e) {}
          activeContactEl = el || null;
          if (activeContactEl && activeContactEl.classList) activeContactEl.classList.add('is-active');
        }

        function renderChatEmptyState(name, avatar, online) {
          const safeName = escapeHtml(name);
          const safeAvatar = escapeHtml(avatar);
          return `
            <div class="fb-chat-empty" aria-label="Bắt đầu trò chuyện">
              <div class="fb-chat-empty-avatar-wrap">
                <img class="fb-chat-empty-avatar" alt="${safeName}" src="${safeAvatar}">
                <span class="fb-chat-empty-dot ${online ? 'is-online' : ''}" aria-hidden="true"></span>
              </div>
              <div class="fb-chat-empty-name">${safeName}</div>
              <div class="fb-chat-empty-lock">🔒 Tin nhắn và cuộc gọi được bảo mật bằng<br>tính năng mã hóa đầu cuối. <a href="javascript:void(0)" aria-label="Tìm hiểu thêm">Tìm hiểu thêm</a></div>
            </div>
          `;
        }

        function normalizeChatInputEmpty() {
          if (!chatInput) return;
          // Some browsers leave a <br> when contenteditable is cleared
          const html = String(chatInput.innerHTML || '').trim();
          if (html === '<br>' || html === '<div><br></div>') {
            chatInput.innerHTML = '';
          }
        }

        function getChatInputText() {
          if (!chatInput) return '';
          normalizeChatInputEmpty();
          return String(chatInput.textContent || '').replace(/\u00A0/g, ' ').trim();
        }

        function clearChatInput() {
          if (!chatInput) return;
          chatInput.innerHTML = '';
          updateChatComposerLayout();
        }

        function updateChatComposerLayout() {
          if (!chatForm || !chatInput) return;

          try {
            normalizeChatInputEmpty();
            const hasText = !!String(chatInput.textContent || '').replace(/\u00A0/g, ' ').trim();
            const multiLine = (chatInput.scrollHeight > (chatInput.clientHeight + 6)) || String(chatInput.textContent || '').includes('\n');
            // Facebook-like: collapse tools when composing multi-line (or long text)
            chatForm.classList.toggle('is-tools-collapsed', multiLine && (hasText || String(chatInput.innerHTML || '').trim() !== ''));
          } catch (_e) {
            // ignore
          }
        }

        function syncChatComposer() {
          const hasText = !!getChatInputText();
          if (chatSendBtn) {
            chatSendBtn.disabled = !hasText;
            chatSendBtn.hidden = !hasText;
          }
          if (chatLikeBtn) {
            chatLikeBtn.hidden = hasText;
          }

          updateChatComposerLayout();
        }

        function scrollChatToBottom() {
          try {
            if (!chatBodyEl) return;
            chatBodyEl.scrollTop = chatBodyEl.scrollHeight;
          } catch (_e) {}
        }

        // Time divider: show a timestamp marker when the conversation is idle for a while
        const CHAT_TIME_DIVIDER_GAP_MS = 30 * 60 * 1000; // 30 minutes
        const lastChatTimeByUser = new Map(); // userId -> last message time (ms)

        function parseChatDateTime(msg) {
          if (!msg) return null;
          const v = msg.created_at || msg.time || msg.createdAt || msg.timestamp || '';
          if (!v) return null;

          if (typeof v === 'number' && Number.isFinite(v)) {
            const d = new Date(v);
            return Number.isNaN(d.getTime()) ? null : d;
          }

          const s = String(v).trim();
          if (!s) return null;

          // MySQL DATETIME: YYYY-MM-DD HH:MM:SS
          const m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
          if (m) {
            const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5]), Number(m[6] || '0'));
            return Number.isNaN(d.getTime()) ? null : d;
          }

          const d = new Date(s);
          return Number.isNaN(d.getTime()) ? null : d;
        }

        function formatChatDividerTime(d) {
          try {
            return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
          } catch (_e) {
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');
            return `${hh}:${mm}`;
          }
        }

        function isDifferentDay(a, b) {
          return a.getFullYear() !== b.getFullYear() || a.getMonth() !== b.getMonth() || a.getDate() !== b.getDate();
        }

        function maybeInsertChatTimeDivider(threadUserId, msgTime) {
          if (!chatBodyEl || !threadUserId || !msgTime) return;

          const ms = msgTime.getTime();
          if (!Number.isFinite(ms)) return;

          const lastMs = Number(lastChatTimeByUser.get(threadUserId)) || 0;
          if (!lastMs) return;

          const lastDate = new Date(lastMs);
          const gap = ms - lastMs;
          if (gap >= CHAT_TIME_DIVIDER_GAP_MS || isDifferentDay(lastDate, msgTime)) {
            const div = document.createElement('div');
            div.className = 'fb-chat-time-divider';
            div.textContent = formatChatDividerTime(msgTime);
            chatBodyEl.appendChild(div);
          }
        }

        function appendChatMessage(msg) {
          if (!chatBodyEl || !msg) return;
          const from = Number(msg.from_user) || 0;
          const to = Number(msg.to_user) || 0;
          const rawContent = String(msg.content || msg.message || '').trim();
          if (!rawContent) return;

          const threadUserId = (from === CURRENT_USER_ID) ? to : from;

          const msgId = Number(msg.id) || 0;
          if (msgId) {
            const existed = chatBodyEl.querySelector(`[data-msg-id="${msgId}"]`);
            if (existed) return;
          }

          // Only show messages for the active thread
          if (!activeChatUserId) return;
          const isForThread = (from === CURRENT_USER_ID && to === activeChatUserId) || (from === activeChatUserId && to === CURRENT_USER_ID);
          if (!isForThread) return;

          const msgTime = parseChatDateTime(msg) || new Date();
          maybeInsertChatTimeDivider(threadUserId, msgTime);

          const wrap = document.createElement('div');
          wrap.className = 'fb-chat-msg ' + (from === CURRENT_USER_ID ? 'is-me' : 'is-them');
          if (msgId) wrap.dataset.msgId = String(msgId);

          const bubble = document.createElement('div');
          bubble.className = 'fb-chat-bubble';

          // Support media JSON payloads: {"type":"image"|"audio","file":"..."}
          let parsed = null;
          if (rawContent.startsWith('{') && rawContent.endsWith('}')) {
            try { parsed = JSON.parse(rawContent); } catch (_e) { parsed = null; }
          }

          if (parsed && parsed.type === 'image' && parsed.file) {
            const img = document.createElement('img');
            img.src = '../uploads/' + encodeURIComponent(String(parsed.file));
            img.alt = 'Ảnh';
            img.style.maxWidth = '220px';
            img.style.borderRadius = '14px';
            img.style.display = 'block';
            bubble.style.padding = '4px';
            bubble.style.background = 'transparent';
            bubble.appendChild(img);
          } else if (parsed && parsed.type === 'audio' && parsed.file) {
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.src = '../uploads/' + encodeURIComponent(String(parsed.file));
            audio.style.maxWidth = '240px';
            bubble.style.padding = '6px 8px';
            bubble.appendChild(audio);
          } else {
            bubble.textContent = rawContent;
          }

          wrap.appendChild(bubble);

          const empty = chatBodyEl.querySelector('.fb-chat-empty');
          if (empty) empty.remove();

          chatBodyEl.appendChild(wrap);

          try {
            lastChatTimeByUser.set(threadUserId, msgTime.getTime());
          } catch (_e) {}

          const id = Number(msg.id) || 0;
          if (id && id > lastMessageId) lastMessageId = id;
          scrollChatToBottom();
        }

        async function loadChatHistory(userId) {
          if (!userId || !chatBodyEl) return;
          const last = Number(lastIdByUser.get(userId)) || 0;
          lastMessageId = last;

          try {
            const resp = await fetch(`../actions/get_messages.php?friend_id=${encodeURIComponent(userId)}&last_id=${encodeURIComponent(lastMessageId)}`, {
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json().catch(() => null);
            const list = (data && data.messages) || [];
            if (Array.isArray(list) && list.length) {
              list.forEach((m) => appendChatMessage(m));
              lastIdByUser.set(userId, lastMessageId);
            } else {
              // keep empty state if there are no bubbles
              const hasBubble = !!chatBodyEl.querySelector('.fb-chat-msg');
              const hasEmpty = !!chatBodyEl.querySelector('.fb-chat-empty');
              if (!hasBubble && !hasEmpty) {
                chatBodyEl.innerHTML = renderChatEmptyState(activeChatUserName, activeChatUserAvatar, !!(chatDotEl && chatDotEl.classList.contains('is-online')));
              }
            }
          } catch (_e) {}
        }

        function setChatHeader(name, avatar, online, lastSeen) {
          if (chatNameEl) chatNameEl.textContent = String(name || '');
          if (chatAvatarEl) {
            chatAvatarEl.src = String(avatar || '');
            chatAvatarEl.alt = String(name || '');
          }
          if (chatProfileLink) {
            const uid = Number(activeChatUserId) || 0;
            chatProfileLink.href = uid ? `../pages/profile.php?id=${encodeURIComponent(uid)}` : 'javascript:void(0)';
            chatProfileLink.setAttribute('aria-label', name ? `Trang cá nhân ${String(name)}` : 'Trang cá nhân');
          }
          if (chatDotEl) {
            chatDotEl.classList.toggle('is-online', !!online);
          }
          if (chatSubEl) {
            if (online) chatSubEl.textContent = 'Đang hoạt động';
            else chatSubEl.textContent = lastSeen ? `Hoạt động ${lastSeen} trước` : '';
          }
        }

        // Sound toggle helper
        function updateSoundButton() {
          if (!chatSoundBtn) return;
          chatSoundBtn.textContent = chatSoundEnabled ? '🔈' : '🔇';
          chatSoundBtn.setAttribute('aria-label', chatSoundEnabled ? 'Tắt âm' : 'Bật âm');
        }
        if (chatSoundBtn) {
          chatSoundBtn.addEventListener('click', (e) => {
            e.preventDefault();
            chatSoundEnabled = !chatSoundEnabled;
            updateSoundButton();
          });
        }

        function openChatForUser(user) {
          const userId = Number(user && user.id) || 0;
          if (!userId) return;
          activeChatUserId = userId;
          activeChatUserName = String(user && user.name || '');
          activeChatUserAvatar = String(user && user.avatar || '');
          lastMessageId = Number(lastIdByUser.get(userId)) || 0;

          setActiveContactEl(user && user.__el);

          if (chatBodyEl) {
            chatBodyEl.innerHTML = renderChatEmptyState(activeChatUserName, activeChatUserAvatar, !!user.online);
          }
          setChatHeader(activeChatUserName, activeChatUserAvatar, !!user.online, String(user.last_seen || ''));
          setChatOpen(true);
          setChatMinimized(false);
          setChatSettingsMenuOpen(false);
          updateChatSettingsMenuUi();
          loadChatHistory(userId);

          try {
            if (chatInput) chatInput.focus();
          } catch (_e) {}

          syncChatComposer();
        }

        // Expose for shared header/popover to open Home's full chat popup.
        // Accepts either a user object ({id,name,avatar,...}) or a numeric user id.
        window.fbOpenChat = function(userOrId) {
          try {
            if (userOrId && typeof userOrId === 'object') {
              openChatForUser(userOrId);
              return;
            }

            const userId = Number(userOrId) || 0;
            if (!userId) return;

            // Prefer the contact list lookup if available.
            if (typeof findContactUser === 'function') {
              const found = findContactUser(userId);
              if (found) {
                openChatForUser(found);
                return;
              }
            }

            openChatForUser({ id: userId, name: '', avatar: '', online: false });
          } catch (_e) {}
        };

        function closeChat() {
          setChatOpen(false);
          setChatMinimized(false);
          setChatSettingsMenuOpen(false);
          // End call when closing the popup (simple behavior)
          if (callPeerUserId) endCall(true);
          activeChatUserId = 0;
          activeChatUserName = '';
          activeChatUserAvatar = '';
          setActiveContactEl(null);
        }

        if (chatCloseBtn) {
          chatCloseBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeChat();
          });
        }

        if (chatMinBtn) {
          chatMinBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (!chatPop) return;
            chatPop.classList.toggle('is-min');
            // close settings menu on minimize
            setChatSettingsMenuOpen(false);
          });
        }

        if (chatCallBtn) {
          chatCallBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!activeChatUserId) return;
            await startCall('audio');
          });
        }

        if (chatVideoBtn) {
          chatVideoBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!activeChatUserId) return;
            await startCall('video');
          });
        }

        if (callAcceptBtn) {
          callAcceptBtn.addEventListener('click', (e) => {
            e.preventDefault();
            acceptIncomingCall();
          });
        }

        if (callDeclineBtn) {
          callDeclineBtn.addEventListener('click', (e) => {
            e.preventDefault();
            declineIncomingCall();
          });
        }

        if (callHangupBtn) {
          callHangupBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (callPeerUserId) endCall(true);
          });
        }

        if (callMuteBtn) {
          callMuteBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleMic();
          });
        }

        if (callCamBtn) {
          callCamBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleCam();
          });
        }

        if (chatInput) {
          chatInput.addEventListener('input', () => {
            syncChatComposer();
          });
          chatInput.addEventListener('keydown', (e) => {
            // Messenger-like: Enter to send, Shift+Enter for new line
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              if (chatForm && chatSendBtn && !chatSendBtn.hidden && !chatSendBtn.disabled) {
                try {
                  if (chatForm.requestSubmit) chatForm.requestSubmit(chatSendBtn);
                  else chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
                } catch (_e2) {}
              }
            }
          });
        }

        // The + button is UI-only for now (tools are still available when input is one-line)
        if (chatPlusBtn) {
          chatPlusBtn.addEventListener('click', (e) => {
            e.preventDefault();
          });
        }

        if (chatLikeBtn) {
          chatLikeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (!activeChatUserId) return;
            try {
              socket.emit('sendMessage', {
                from_user: CURRENT_USER_ID,
                to_user: activeChatUserId,
                message: '👍',
                cookie: document.cookie
              });
            } catch (_e) {}
          });
        }

        async function persistAndRelayContent(contentString, savedRow) {
          // Relay realtime through socket (do not persist on Node)
          try {
            socket.emit('sendMessage', {
              no_persist: true,
              id: savedRow && savedRow.id ? savedRow.id : undefined,
              created_at: savedRow && savedRow.created_at ? savedRow.created_at : undefined,
              from_user: CURRENT_USER_ID,
              to_user: activeChatUserId,
              message: contentString,
              content: contentString
            });
          } catch (_e) {}

          // Ensure sender view updates even if socket is down
          if (!window.fbSocket || !window.fbSocket.connected) {
            if (savedRow) appendChatMessage(savedRow);
            else await loadChatHistory(activeChatUserId);
          }
        }

        async function sendFileMessage(file) {
          if (!file || !activeChatUserId) return;

          let saved = null;
          try {
            const fd = new FormData();
            fd.append('to_user', String(activeChatUserId));
            fd.append('file', file);

            const resp = await fetch('../actions/send_message.php', {
              method: 'POST',
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
              body: fd
            });
            const data = await resp.json().catch(() => null);
            if (data && data.ok && data.message) saved = data.message;
          } catch (_e) {
            saved = null;
          }

          // play send sound (if enabled)
          try {
            if (chatSoundEnabled && chatAudio) {
              chatAudio.currentTime = 0;
              chatAudio.play().catch(()=>{});
            }
          } catch (_e) {}

          if (saved) {
            await persistAndRelayContent(String(saved.content || ''), saved);
          } else {
            // fallback to refresh
            await loadChatHistory(activeChatUserId);
          }
        }

        // Photo button -> pick image -> auto send
        if (chatPhotoBtn && chatFileInput) {
          chatPhotoBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (!activeChatUserId) return;
            chatFileInput.value = '';
            chatFileInput.click();
          });
          chatFileInput.addEventListener('change', async (e) => {
            const files = (chatFileInput.files && Array.from(chatFileInput.files)) || [];
            if (!files.length) return;
            // send sequentially
            for (const f of files) {
              await sendFileMessage(f);
            }
            chatFileInput.value = '';
          });
        }

        // Voice recording (mic)
        let mediaRecorder = null;
        let recordChunks = [];
        let recordStream = null;
        let isRecording = false;

        function setMicUi(recording) {
          if (!chatMicBtn) return;
          chatMicBtn.classList.toggle('is-recording', !!recording);
          chatMicBtn.setAttribute('aria-label', recording ? 'Dừng ghi âm' : 'Gửi clip âm thanh');
        }

        async function stopRecordingAndSend() {
          try {
            if (mediaRecorder && isRecording) {
              mediaRecorder.stop();
            }
          } catch (_e) {}
        }

        if (chatMicBtn) {
          chatMicBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!activeChatUserId) return;

            // toggle
            if (isRecording) {
              await stopRecordingAndSend();
              return;
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
              alert('Trình duyệt không hỗ trợ ghi âm.');
              return;
            }

            try {
              recordStream = await navigator.mediaDevices.getUserMedia({ audio: true });
              const preferredTypes = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/ogg'
              ];
              let mimeType = '';
              for (const t of preferredTypes) {
                if (window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t)) {
                  mimeType = t;
                  break;
                }
              }
              mediaRecorder = mimeType ? new MediaRecorder(recordStream, { mimeType }) : new MediaRecorder(recordStream);
              recordChunks = [];
              isRecording = true;
              setMicUi(true);

              mediaRecorder.ondataavailable = (evt) => {
                if (evt.data && evt.data.size > 0) recordChunks.push(evt.data);
              };

              mediaRecorder.onstop = async () => {
                const chunks = recordChunks;
                recordChunks = [];
                isRecording = false;
                setMicUi(false);

                try {
                  if (recordStream) {
                    recordStream.getTracks().forEach((t) => t.stop());
                  }
                } catch (_e2) {}
                recordStream = null;

                if (!chunks.length) return;
                const blob = new Blob(chunks, { type: (mediaRecorder && mediaRecorder.mimeType) ? mediaRecorder.mimeType : 'audio/webm' });
                const ext = (blob.type || '').includes('ogg') ? 'ogg' : 'webm';
                const file = new File([blob], `voice.${ext}`, { type: blob.type || 'audio/webm' });
                await sendFileMessage(file);
              };

              mediaRecorder.start();
            } catch (err) {
              console.warn('record failed', err);
              alert('Không thể ghi âm (cần cấp quyền micro).');
              try {
                if (recordStream) recordStream.getTracks().forEach((t) => t.stop());
              } catch (_e3) {}
              recordStream = null;
              isRecording = false;
              setMicUi(false);
            }
          });
        }

        if (chatForm) {
          chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = getChatInputText();
            if (!msg || !activeChatUserId) return;

            // Always persist via browser POST so PHP session cookies are included
            let saved = null;
            try {
              const resp = await fetch('../actions/send_message.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                  'Accept': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest'
                },
                body: `message=${encodeURIComponent(msg)}&to_user=${encodeURIComponent(activeChatUserId)}`
              });
              const data = await resp.json().catch(() => null);
              if (data && data.ok && data.message) {
                saved = data.message;
              }
            } catch (_e) {
              saved = null;
            }

            clearChatInput();
            syncChatComposer();

            // play send sound (if enabled)
            try {
              if (chatSoundEnabled && chatAudio) {
                chatAudio.currentTime = 0;
                chatAudio.play().catch(()=>{});
              }
            } catch (_e) {}

            // Relay realtime through socket (do not persist on Node)
            await persistAndRelayContent(msg, saved);
          });
        }

        // Click contact -> open chat popup
        if (contactsRoot) {
          contactsRoot.addEventListener('click', (e) => {
            const item = e.target && e.target.closest && e.target.closest('.fb-contact-item');
            if (!item || !contactsRoot.contains(item)) return;
            const uid = Number(item.dataset && item.dataset.userId) || 0;
            if (!uid) return;

            openChatForUser({
              id: uid,
              name: String(item.dataset && item.dataset.userName || ''),
              avatar: String(item.dataset && item.dataset.userAvatar || ''),
              online: String(item.dataset && item.dataset.userOnline || '') === '1',
              last_seen: String(item.dataset && item.dataset.userLastSeen || ''),
              __el: item
            });
          });
        }

        // Expose optional helper for other scripts
        window.sendMessage = function(message) {
          const msg = String(message || '').trim();
          const to = activeChatUserId || TO_USER_ID;
          if (!msg || !to) return;
          socket.emit('sendMessage', {
            from_user: CURRENT_USER_ID,
            to_user: to,
            message: msg,
            cookie: document.cookie
          });
        };

        // Realtime receive
        let mpRealtimeConvTimer = null;
        function mpScheduleConvRefresh() {
          try {
            if (!messengerPopover || !messengerPopover.classList || !messengerPopover.classList.contains('open')) return;
          } catch (_e) { return; }
          if (mpRealtimeConvTimer) return;
          mpRealtimeConvTimer = setTimeout(() => {
            mpRealtimeConvTimer = null;
            try {
              if (typeof loadMpConversations === 'function') loadMpConversations();
            } catch (_e) {}
          }, 250);
        }

        socket.on('newMessage', (data) => {
          // data: {from_user,to_user,message,time}
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            const otherUserId = (from === CURRENT_USER_ID) ? to : from;

            // If popup is closed, auto-open chat for incoming sender (Messenger-like)
            if (!activeChatUserId && otherUserId && otherUserId !== CURRENT_USER_ID) {
              let item = null;
              try {
                item = contactsRoot && contactsRoot.querySelector
                  ? contactsRoot.querySelector(`.fb-contact-item[data-user-id="${otherUserId}"]`)
                  : null;
              } catch (_e) { item = null; }

              const user = {
                id: otherUserId,
                name: item ? String(item.dataset && item.dataset.userName || '') : `User ${otherUserId}`,
                avatar: item ? String(item.dataset && item.dataset.userAvatar || '') : '../assets/images/default-avatar.png',
                online: item ? (String(item.dataset && item.dataset.userOnline || '') === '1') : false,
                last_seen: item ? String(item.dataset && item.dataset.userLastSeen || '') : '',
                __el: item
              };
              openChatForUser(user);
            }
          } catch (_e) {}

          // Update Messenger popover list instantly (realtime), if it's open.
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            const otherUserId = (from === CURRENT_USER_ID) ? to : from;
            const preview = mpPreviewFromSocketPayload(data);

            // For cache: keep a parsable timestamp value
            const lastTimeValue = (data && data.created_at) ? data.created_at : (() => {
              const t = Number(data && data.time);
              if (Number.isFinite(t) && t > 0) {
                try { return new Date(t).toISOString(); } catch (_e) { return ''; }
              }
              return '';
            })();

            // Unread only increases for incoming messages when you're not viewing that thread.
            const unreadDelta = (from && from !== CURRENT_USER_ID && activeChatUserId !== otherUserId) ? 1 : 0;

            // Always update local cache so opening the popover later still shows the latest thread.
            mpUpsertCacheFromRealtime(otherUserId, preview, lastTimeValue, unreadDelta);

            // If popover is open, update DOM immediately.
            if (messengerPopover && messengerPopover.classList && messengerPopover.classList.contains('open')) {
              const timeLabel = mpTimeLabelFromSocketPayload(data);
              mpEnsureRowForUser(otherUserId, preview, timeLabel, unreadDelta, mpFindContactBasic);
            }
          } catch (_e) {}

          // Keep Messenger popover list in sync while open.
          try { mpScheduleConvRefresh(); } catch (_e) {}

          appendChatMessage(data);

          // play receive sound if message is from other user
          try {
            const from = Number(data && data.from_user) || 0;
            const muted = from ? chatMutedThreads.has(from) : false;
            if (from && from !== CURRENT_USER_ID && !muted && chatSoundEnabled && chatAudio) {
              chatAudio.currentTime = 0;
              chatAudio.play().catch(()=>{});
            }
          } catch (_e) {}

          // If popup is open for this thread, keep lastId map in sync via fetch
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            const otherUserId = (from === CURRENT_USER_ID) ? to : from;
            if (activeChatUserId && otherUserId && activeChatUserId === otherUserId) {
              // best-effort: pull DB ids so last_id advances
              loadChatHistory(activeChatUserId);
            }
          } catch (_e) {}
        });

        // ===== WEBRTC SIGNALING HANDLERS =====
        socket.on('webrtc:offer', async (data) => {
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            const kind = (data && data.kind) === 'video' ? 'video' : 'audio';
            const sdp = data && data.sdp;
            if (!from || to !== CURRENT_USER_ID || !sdp) return;

            // Busy if already on call or has pending incoming/outgoing
            if (callState !== 'idle' && callPeerUserId && callPeerUserId !== from) {
              try { socket.emit('webrtc:busy', { from_user: CURRENT_USER_ID, to_user: from, kind }); } catch (_e) {}
              return;
            }

            // Force-open chat popup (so incoming call UI is visible even if user didn't open chat)
            try { setChatOpen(true); } catch (_e) {}
            try { setChatMinimized(false); } catch (_e) {}

            // Open chat thread with caller
            if (!activeChatUserId || activeChatUserId !== from) {
              openChatForUser(findContactUser(from));
            }

            // If we're already dealing with a call, mark busy
            if (callState !== 'idle' && callPeerUserId && callPeerUserId !== from) {
              try { socket.emit('webrtc:busy', { from_user: CURRENT_USER_ID, to_user: from, kind }); } catch (_e) {}
              return;
            }

            // Prepare incoming call UI
            const u = findContactUser(from);
            callKind = kind;
            setCallUiInfo(u.name || activeChatUserName, u.avatar || activeChatUserAvatar);
            callPeerUserId = from;
            pendingIncoming = { from, kind, sdp };
            setCallState('incoming_ringing');

            // Inform caller that we're ringing
            try { socket.emit('webrtc:ringing', { from_user: CURRENT_USER_ID, to_user: from, kind }); } catch (_e) {}
          } catch (e) {
            console.warn('offer handler failed', e);
            cleanupCall();
            setCallState('idle');
          }
        });

        socket.on('webrtc:answer', async (data) => {
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            const sdp = data && data.sdp;
            if (!from || to !== CURRENT_USER_ID || !sdp) return;
            if (!callPc || callPeerUserId !== from) return;
            await callPc.setRemoteDescription(sdp);
            setCallState('in_call');
          } catch (e) {
            console.warn('answer handler failed', e);
          }
        });

        socket.on('webrtc:ice', async (data) => {
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            const candidate = data && data.candidate;
            if (!from || to !== CURRENT_USER_ID || !candidate) return;
            if (!callPc || callPeerUserId !== from) return;
            await callPc.addIceCandidate(candidate);
          } catch (_e) {}
        });

        socket.on('webrtc:hangup', (data) => {
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            if (!from || to !== CURRENT_USER_ID) return;
            if (callPeerUserId && callPeerUserId === from) {
              stopCallTone();
              cleanupCall();
              setCallState('ended');
            }
          } catch (_e) {}
        });

        socket.on('webrtc:reject', (data) => {
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            if (!from || to !== CURRENT_USER_ID) return;
            if (callPeerUserId && callPeerUserId === from) {
              stopCallTone();
              cleanupCall();
              setCallStatus('Đối phương từ chối');
              setCallState('ended');
            }
          } catch (_e) {}
        });

        socket.on('webrtc:ringing', (data) => {
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            if (!from || to !== CURRENT_USER_ID) return;
            if (callPeerUserId && callPeerUserId === from) {
              if (callState === 'outgoing_calling') setCallState('outgoing_ringing');
            }
          } catch (_e) {}
        });

        socket.on('webrtc:accepted', (data) => {
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            if (!from || to !== CURRENT_USER_ID) return;
            if (callPeerUserId && callPeerUserId === from) {
              setCallState('connecting');
            }
          } catch (_e) {}
        });

        socket.on('webrtc:busy', (data) => {
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            if (!from || to !== CURRENT_USER_ID) return;
            if (callPeerUserId && callPeerUserId === from) {
              stopCallTone();
              setCallStatus('Người dùng đang bận');
              cleanupCall();
              setCallState('ended');
            }
          } catch (_e) {}
        });

        socket.on('webrtc:cancel', (data) => {
          try {
            const from = Number(data && data.from_user) || 0;
            const to = Number(data && data.to_user) || 0;
            if (!from || to !== CURRENT_USER_ID) return;
            if (callPeerUserId && callPeerUserId === from) {
              stopCallTone();
              setCallStatus('Cuộc gọi đã bị hủy');
              cleanupCall();
              setCallState('ended');
            }
          } catch (_e) {}
        });

        // init composer state
        syncChatComposer();
      } catch (e) {
        console.warn('Socket.IO client failed', e);
      }
    })();

    // ===== REALTIME FEED (LIKE/COMMENT/DELETE/CREATE) =====
    (function() {
      const feedEl = document.querySelector('.fb-feed');
      if (!feedEl) return;

      const socket = window.fbSocket;

      const qs = (obj) => new URLSearchParams(obj).toString();
      const escapeHtml = (str) => String(str || '').replace(/[&<>"]/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;'
      } [c]));
      function parseDbDateTime(v) {
        const s = String(v || '').trim();
        if (!s) return null;
        // MySQL DATETIME: YYYY-MM-DD HH:MM:SS
        const iso = s.includes('T') ? s : s.replace(' ', 'T');
        const d = new Date(iso);
        return Number.isNaN(d.getTime()) ? null : d;
      }
      function formatTimeAgoVN(date) {
        const ms = Math.max(0, Date.now() - date.getTime());
        const sec = Math.floor(ms / 1000);
        if (sec < 60) return 'vừa xong';
        const min = Math.floor(sec / 60);
        if (min < 60) return `${min} phút`;
        const hr = Math.floor(min / 60);
        if (hr < 24) return `${hr} giờ`;
        const day = Math.floor(hr / 24);
        if (day < 7) return `${day} ngày`;
        const week = Math.floor(day / 7);
        if (week < 4) return `${week} tuần`;
        const month = Math.floor(day / 30);
        if (month < 12) return `${month} tháng`;
        const year = Math.floor(day / 365);
        return `${year} năm`;
      }
      function updateTimeAgoIn(root) {
        const scope = root || document;
        const els = scope.querySelectorAll ? scope.querySelectorAll('.js-time-ago') : [];
        (els || []).forEach((el) => {
          const t = el && el.dataset ? el.dataset.time : '';
          const d = parseDbDateTime(t);
          if (!d) return;
          el.textContent = formatTimeAgoVN(d);
        });
      }

      const resolveAvatar = (avatar) => {
        const v = String(avatar || '').trim();
        if (!v) return '../assets/images/default-avatar.png';
        if (/^https?:\/\//i.test(v)) return v;
        if (v.startsWith('/')) return v;
        return '../uploads/' + encodeURIComponent(v);
      };

      const resolveImage = (image) => {
        const v = String(image || '').trim();
        if (!v) return '';
        // legacy: if server stores JSON array, treat as first item here
        if (v.startsWith('[')) {
          try {
            const arr = JSON.parse(v);
            if (Array.isArray(arr) && arr.length) {
              return resolveImage(String(arr[0] || ''));
            }
          } catch (_e) {}
          return '';
        }
        if (/^https?:\/\//i.test(v)) return v;
        if (v.startsWith('/')) return v;
        return '../uploads/' + encodeURIComponent(v);
      };

      const resolveImages = (imageField) => {
        const v = String(imageField || '').trim();
        if (!v) return [];
        if (v.startsWith('[')) {
          try {
            const arr = JSON.parse(v);
            if (Array.isArray(arr)) {
              return arr
                .map((x) => resolveImage(String(x || '')))
                .filter(Boolean);
            }
          } catch (_e) {}
          return [];
        }
        const one = resolveImage(v);
        return one ? [one] : [];
      };

      function findPostEl(postId) {
        return feedEl.querySelector(`.fb-post-card[data-post-id="${postId}"]`);
      }

      function setCounts(postEl, likeCount, commentCount) {
        if (!postEl) return;
        if (typeof likeCount === 'number') {
          const likeEl = postEl.querySelector('.js-like-count');
          if (likeEl) {
            likeEl.dataset.value = String(likeCount);
            likeEl.textContent = `${likeCount} lượt thích`;
          }
        }
        if (typeof commentCount === 'number') {
          const cEl = postEl.querySelector('.js-comment-count');
          if (cEl) {
            cEl.dataset.value = String(commentCount);
            cEl.textContent = `${commentCount} bình luận`;
          }
        }
      }

      function setLiked(postEl, isLiked) {
        const btn = postEl && postEl.querySelector('.js-like-btn');
        if (!btn) return;
        btn.classList.toggle('is-liked', !!isLiked);
        btn.setAttribute('aria-pressed', isLiked ? 'true' : 'false');
      }

      const REACTIONS = {
        like: { label: 'Thích', emoji: '👍' },
        love: { label: 'Yêu thích', emoji: '❤️' },
        haha: { label: 'Haha', emoji: '😂' },
        wow: { label: 'Wow', emoji: '😮' },
        care: { label: 'Thương thương', emoji: '🥰' },
        sad: { label: 'Buồn', emoji: '😢' },
        angry: { label: 'Phẫn nộ', emoji: '😡' },
      };

      function normalizeReaction(key) {
        key = String(key || '').trim().toLowerCase();
        return REACTIONS[key] ? key : '';
      }

      function setReactionState(postEl, reactionKey) {
        if (!postEl) return;
        const btn = postEl.querySelector('.js-like-btn');
        if (!btn) return;

        const k = normalizeReaction(reactionKey);
        const reacted = !!k;
        const meta = REACTIONS[k || 'like'];

        postEl.dataset.myReaction = k;
        btn.dataset.reaction = k;
        btn.classList.toggle('is-liked', reacted);
        btn.setAttribute('aria-pressed', reacted ? 'true' : 'false');

        const emojiEl = btn.querySelector('.fb-like-emoji');
        const textEl = btn.querySelector('.fb-like-text');
        if (emojiEl) emojiEl.textContent = meta.emoji;
        if (textEl) textEl.textContent = reacted ? meta.label : 'Thích';
      }

      async function sendReaction(postId, reactionKey) {
        const resp = await fetch('../actions/like.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: qs({ post_id: postId, reaction: reactionKey, ajax: 1 })
        });
        return await resp.json();
      }

      // ===== Reactions modal =====
      const reactionsOverlay = document.createElement('div');
      reactionsOverlay.className = 'fb-reactions-overlay';
      reactionsOverlay.innerHTML = `
        <div class="fb-reactions-modal" role="dialog" aria-modal="true" aria-label="Cảm xúc">
          <div class="fb-reactions-modal-header">
            <div class="fb-reactions-title">Cảm xúc</div>
            <button type="button" class="fb-reactions-close" aria-label="Đóng">×</button>
          </div>
          <div class="fb-reactions-body">
            <div class="fb-reactions-summary"></div>
            <div class="fb-reactions-list"></div>
          </div>
        </div>
      `;
      document.body.appendChild(reactionsOverlay);

      let reactionsModalPostId = 0;

      function closeReactionsModal() {
        reactionsOverlay.classList.remove('is-open');
        reactionsModalPostId = 0;
        const list = reactionsOverlay.querySelector('.fb-reactions-list');
        const sum = reactionsOverlay.querySelector('.fb-reactions-summary');
        if (list) list.innerHTML = '';
        if (sum) sum.innerHTML = '';
      }

      reactionsOverlay.addEventListener('click', (e) => {
        if (e.target === reactionsOverlay) closeReactionsModal();
      });
      const closeBtn = reactionsOverlay.querySelector('.fb-reactions-close');
      if (closeBtn) closeBtn.addEventListener('click', closeReactionsModal);
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && reactionsOverlay.classList.contains('is-open')) closeReactionsModal();
      });

      function renderReactionsSummary(summary) {
        const sum = reactionsOverlay.querySelector('.fb-reactions-summary');
        if (!sum) return;
        const keys = Object.keys(summary || {});
        if (!keys.length) {
          sum.innerHTML = '';
          return;
        }
        sum.innerHTML = keys.map((k) => {
          const meta = REACTIONS[k] || REACTIONS.like;
          const cnt = Number(summary[k]) || 0;
          return `<span class="fb-reaction-chip"><span aria-hidden="true">${meta.emoji}</span><span>${cnt}</span></span>`;
        }).join('');
      }

      function renderReactionsList(items) {
        const list = reactionsOverlay.querySelector('.fb-reactions-list');
        if (!list) return;
        list.innerHTML = '';
        (items || []).forEach((it) => {
          const name = String(it.name || '');
          const avatar = resolveAvatar(it.avatar || '');
          const rk = normalizeReaction(it.reaction) || 'like';
          const meta = REACTIONS[rk] || REACTIONS.like;
          const row = document.createElement('div');
          row.className = 'fb-reactions-item';
          row.innerHTML = `
            <img class="fb-reactions-avatar" src="${escapeHtml(avatar)}" alt="${escapeHtml(name)}">
            <div class="fb-reactions-name">${escapeHtml(name)}</div>
            <div class="fb-reactions-right" aria-hidden="true">${meta.emoji}</div>
          `;
          list.appendChild(row);
        });
      }

      async function openReactionsModal(postId) {
        reactionsModalPostId = postId;
        reactionsOverlay.classList.add('is-open');
        try {
          const resp = await fetch(`../actions/get_reactions.php?post_id=${encodeURIComponent(postId)}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const data = await resp.json().catch(() => null);
          if (data && data.ok) {
            renderReactionsSummary(data.summary || {});
            renderReactionsList(data.items || []);
          }
        } catch (_e) {}
      }

      // ===== Comment modal =====
      const commentBackdrop = document.getElementById('fbCommentModalBackdrop');
      const commentCloseBtn = document.getElementById('fbCommentModalClose');
      const commentTitleEl = document.getElementById('fbCommentModalTitle');
      const commentPostWrap = document.getElementById('fbCommentModalPost');
      const commentListEl = document.getElementById('fbCommentModalList');
      const commentEmptyEl = document.getElementById('fbCommentModalEmpty');
      const commentForm = document.getElementById('fbCommentModalForm');
      const commentPostIdInput = document.getElementById('fbCommentModalPostId');
      const commentParentIdInput = document.getElementById('fbCommentModalParentId');
      const commentReplyingEl = document.getElementById('fbCommentModalReplying');
      const commentReplyingNameEl = document.getElementById('fbCommentModalReplyingName');
      const commentReplyingCancelBtn = document.getElementById('fbCommentModalReplyingCancel');

      let commentModalPostId = 0;

      function setCommentModalOpen(open) {
        if (!commentBackdrop) return;
        if (open) {
          commentBackdrop.classList.add('is-open');
          commentBackdrop.setAttribute('aria-hidden', 'false');
          try { document.body.style.overflow = 'hidden'; } catch (_e) {}
        } else {
          commentBackdrop.classList.remove('is-open');
          commentBackdrop.setAttribute('aria-hidden', 'true');
          try { document.body.style.overflow = ''; } catch (_e) {}
        }
      }

      function closeCommentModal() {
        commentModalPostId = 0;
        if (commentPostIdInput) commentPostIdInput.value = '';
        if (commentParentIdInput) commentParentIdInput.value = '';
        if (commentTitleEl) commentTitleEl.textContent = 'Bài viết';
        if (commentPostWrap) commentPostWrap.innerHTML = '';
        if (commentListEl) commentListEl.innerHTML = '';
        if (commentEmptyEl) commentEmptyEl.hidden = true;
        if (commentReplyingEl) commentReplyingEl.hidden = true;
        if (commentForm) {
          const ip = commentForm.querySelector('.fb-comment-input');
          const btn = commentForm.querySelector('.fb-comment-submit');
          if (ip) ip.value = '';
          if (ip) ip.placeholder = 'Viết bình luận...';
          if (btn) btn.disabled = true;
        }
        setCommentModalOpen(false);
      }
      function setReplyTarget(parentId, userName) {
        const pid = Number(parentId) || 0;
        if (commentParentIdInput) commentParentIdInput.value = pid ? String(pid) : '';
        if (commentReplyingEl) commentReplyingEl.hidden = !pid;
        if (commentReplyingNameEl) commentReplyingNameEl.textContent = pid ? String(userName || '') : '';
        if (commentForm) {
          const ip = commentForm.querySelector('.fb-comment-input');
          if (ip) ip.placeholder = pid ? `Trả lời ${String(userName || '')}...` : 'Viết bình luận...';
        }
      }

      if (commentBackdrop) {
        commentBackdrop.addEventListener('click', (e) => {
          if (e.target === commentBackdrop) closeCommentModal();
        });
      }
      if (commentCloseBtn) commentCloseBtn.addEventListener('click', closeCommentModal);
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && commentBackdrop && commentBackdrop.classList.contains('is-open')) {
          closeCommentModal();
        }
      });
      if (commentReplyingCancelBtn) {
        commentReplyingCancelBtn.addEventListener('click', () => setReplyTarget(0, ''));
      }

      function closeAllCommentMenus(exceptItem) {
        if (!commentListEl) return;
        commentListEl.querySelectorAll('.fb-comment-menu.is-open').forEach((m) => {
          const item = m.closest('.fb-comment-item');
          if (exceptItem && item === exceptItem) return;
          m.classList.remove('is-open');
        });
      }

      // click outside comment list closes menus
      document.addEventListener('click', (e) => {
        if (!commentBackdrop || !commentBackdrop.classList.contains('is-open')) return;
        if (!commentListEl) return;
        if (commentListEl.contains(e.target)) return;
        closeAllCommentMenus();
      });

      function buildCommentEl(comment) {
        const commentId = Number(comment && comment.id) || 0;
        const parentId = Number(comment && comment.parent_id) || 0;
        const userId = Number(comment && comment.user_id) || 0;
        const userName = String(comment && comment.user_name || '');
        const avatar = resolveAvatar(comment && comment.user_avatar || '');
        const content = String(comment && comment.content || '');
        const createdAt = String(comment && comment.created_at || '');
        const likeCount = Number(comment && comment.like_count) || 0;
        const likedByMe = !!(comment && comment.liked_by_me);

        const item = document.createElement('div');
        item.className = 'fb-comment-item' + (parentId ? ' is-reply' : '');
        if (commentId) item.dataset.commentId = String(commentId);
        if (parentId) item.dataset.parentId = String(parentId);
        item.dataset.userId = String(userId);
        item.dataset.userName = userName;

        const img = document.createElement('img');
        img.className = 'fb-comment-avatar';
        img.alt = escapeHtml(userName);
        img.src = avatar;

        const bubble = document.createElement('div');
        bubble.className = 'fb-comment-bubble';

        const head = document.createElement('div');
        head.className = 'fb-comment-bubble-head';

        const name = document.createElement('div');
        name.className = 'fb-comment-name';
        name.textContent = userName;
        head.appendChild(name);

        if (typeof CURRENT_USER_ID !== 'undefined' && userId === CURRENT_USER_ID) {
          const more = document.createElement('button');
          more.type = 'button';
          more.className = 'fb-comment-more js-comment-more';
          more.setAttribute('aria-label', 'Tùy chọn');
          more.textContent = '…';
          head.appendChild(more);

          const menu = document.createElement('div');
          menu.className = 'fb-comment-menu';
          menu.innerHTML = `
            <button type="button" class="fb-comment-menu-btn js-comment-edit">Chỉnh sửa</button>
            <button type="button" class="fb-comment-menu-btn js-comment-delete">Xóa</button>
          `;
          bubble.appendChild(menu);
        }

        const text = document.createElement('div');
        text.className = 'fb-comment-text';
        text.textContent = content;

        bubble.appendChild(head);
        bubble.appendChild(text);

        const meta = document.createElement('div');
        meta.className = 'fb-comment-meta';
        meta.innerHTML = `
          <span class="fb-comment-time js-time-ago" data-time="${escapeHtml(createdAt)}"></span>
          <button type="button" class="fb-comment-action js-comment-like ${likedByMe ? 'is-liked' : ''}">Thích</button>
          <button type="button" class="fb-comment-action js-comment-reply">Trả lời</button>
          <span class="fb-comment-like-count js-comment-like-count" data-value="${likeCount}" ${likeCount > 0 ? '' : 'hidden'}>${likeCount} thích</span>
        `;

        item.appendChild(img);
        item.appendChild(bubble);
        item.appendChild(meta);

        updateTimeAgoIn(item);
        return item;
      }

      function appendCommentToList(listEl, comment) {
        if (!listEl || !comment) return;
        const commentId = Number(comment.id) || 0;
        if (commentId && listEl.querySelector(`[data-comment-id="${commentId}"]`)) return;

        const item = buildCommentEl(comment);

        const parentId = Number(comment.parent_id) || 0;
        if (parentId) {
          const parentEl = listEl.querySelector(`[data-comment-id="${parentId}"]`);
          if (parentEl) {
            const siblings = Array.from(listEl.querySelectorAll(`[data-parent-id="${parentId}"]`));
            const last = siblings.length ? siblings[siblings.length - 1] : null;
            (last || parentEl).insertAdjacentElement('afterend', item);
          } else {
            listEl.appendChild(item);
          }
        } else {
          listEl.appendChild(item);
        }

        if (commentEmptyEl && listEl === commentListEl) {
          commentEmptyEl.hidden = true;
        }
      }

      async function openCommentModalForPost(postEl) {
        if (!commentBackdrop || !postEl) return;
        const postId = Number(postEl.dataset && postEl.dataset.postId) || 0;
        if (!postId) return;

        commentModalPostId = postId;
        if (commentPostIdInput) commentPostIdInput.value = String(postId);

        const nameEl = postEl.querySelector('.fb-post-name');
        const titleName = nameEl ? String(nameEl.textContent || '').trim() : '';
        if (commentTitleEl) {
          commentTitleEl.textContent = titleName ? `Bài viết của ${titleName}` : 'Bài viết';
        }

        if (commentPostWrap) {
          commentPostWrap.innerHTML = '';
          commentPostWrap.dataset.postId = String(postId);
          commentPostWrap.dataset.myReaction = String(postEl.dataset && postEl.dataset.myReaction || '');
          const header = postEl.querySelector('.fb-post-header');
          const content = postEl.querySelector('.fb-post-content');
          const media = postEl.querySelector('.fb-post-image, .fb-post-media-grid');
          const stats = postEl.querySelector('.fb-post-stats');
          const actions = postEl.querySelector('.fb-post-actions');
          if (header) commentPostWrap.appendChild(header.cloneNode(true));
          if (content) commentPostWrap.appendChild(content.cloneNode(true));
          if (media) commentPostWrap.appendChild(media.cloneNode(true));
          if (stats) commentPostWrap.appendChild(stats.cloneNode(true));
          if (actions) commentPostWrap.appendChild(actions.cloneNode(true));
        }

        if (commentListEl) commentListEl.innerHTML = '';
        if (commentEmptyEl) commentEmptyEl.hidden = true;

        setCommentModalOpen(true);
        setReplyTarget(0, '');

        try {
          const resp = await fetch(`../actions/get_comments.php?post_id=${encodeURIComponent(postId)}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const data = await resp.json().catch(() => null);
          if (data && data.ok && commentListEl) {
            const items = Array.isArray(data.items) ? data.items : [];
            const parents = items.filter((c) => !(Number(c.parent_id) || 0));
            const byParent = new Map();
            items.forEach((c) => {
              const pid = Number(c.parent_id) || 0;
              if (!pid) return;
              if (!byParent.has(pid)) byParent.set(pid, []);
              byParent.get(pid).push(c);
            });

            parents.forEach((p) => {
              appendCommentToList(commentListEl, p);
              const replies = byParent.get(Number(p.id) || 0) || [];
              replies.forEach((r) => appendCommentToList(commentListEl, r));
            });

            // Orphan replies (if parent not in the list)
            items.filter((c) => (Number(c.parent_id) || 0) && !items.some((p) => (Number(p.id) || 0) === (Number(c.parent_id) || 0)))
              .forEach((c) => appendCommentToList(commentListEl, c));

            if (commentEmptyEl) commentEmptyEl.hidden = items.length > 0;
            updateTimeAgoIn(commentPostWrap);
          } else {
            if (commentEmptyEl) commentEmptyEl.hidden = false;
          }
        } catch (_e) {
          if (commentEmptyEl) commentEmptyEl.hidden = false;
        }

        try {
          const ip = commentForm && commentForm.querySelector('.fb-comment-input');
          if (ip) ip.focus();
        } catch (_e) {}
      }

      function appendComment(postEl, comment) {
        if (!postEl || !comment) return;
        const list = postEl.querySelector('.fb-comment-list');
        if (!list) return;

        const item = document.createElement('div');
        item.className = 'fb-comment-item';
        const cid = Number(comment.id) || 0;
        const pid = Number(comment.parent_id) || 0;
        if (cid) item.dataset.commentId = String(cid);
        if (pid) item.dataset.parentId = String(pid);

        const img = document.createElement('img');
        img.className = 'fb-comment-avatar';
        img.alt = escapeHtml(comment.user_name || '');
        img.src = resolveAvatar(comment.user_avatar || '');

        const bubble = document.createElement('div');
        bubble.className = 'fb-comment-bubble';

        const name = document.createElement('div');
        name.className = 'fb-comment-name';
        name.textContent = String(comment.user_name || '');

        const text = document.createElement('div');
        text.className = 'fb-comment-text';
        text.textContent = String(comment.content || '');

        bubble.appendChild(name);
        bubble.appendChild(text);

        item.appendChild(img);
        item.appendChild(bubble);
        // basic reply indent if this is a reply
        if (pid) item.classList.add('is-reply');
        list.appendChild(item);

        // nếu modal đang mở đúng bài, append vào modal luôn
        if (commentListEl && commentBackdrop && commentBackdrop.classList.contains('is-open') && commentModalPostId === (Number(postEl.dataset && postEl.dataset.postId) || 0)) {
          appendCommentToList(commentListEl, comment);
        }
      }

      function renderPostCard(post) {
        const postId = Number(post && post.id) || 0;
        if (!postId) return null;

        const ownerId = Number(post.user_id) || 0;
        const userName = String(post.user_name || '');
        const avatar = resolveAvatar(post.user_avatar || '');
        const content = String(post.content || '');
        const imgUrls = resolveImages(post.image || '');
        const likeCount = Number(post.like_count) || 0;
        const commentCount = Number(post.comment_count) || 0;

        const mediaHtml = (function() {
          if (!imgUrls.length) return '';
          if (imgUrls.length === 1) {
            return `<img class="fb-post-image" src="${escapeHtml(imgUrls[0])}" alt="Ảnh bài viết">`;
          }
          return `<div class="fb-post-media-grid" data-count="${imgUrls.length}">${imgUrls
            .map((u) => `<img class="fb-post-image" src="${escapeHtml(u)}" alt="Ảnh bài viết">`)
            .join('')}</div>`;
        })();

        const createdAt = String(post.created_at || '');

        const article = document.createElement('article');
        article.className = 'fb-post-card';
        article.id = `post-${postId}`;
        article.dataset.postId = String(postId);
        article.dataset.ownerId = String(ownerId);
        article.dataset.createdAt = createdAt;

        // Build with safe text nodes
        article.innerHTML = `
          <div class="fb-post-header">
            <img class="fb-post-avatar" src="${escapeHtml(avatar)}" alt="${escapeHtml(userName)}">
            <div class="fb-post-meta">
              <div class="fb-post-name">${escapeHtml(userName)}</div>
               <div class="fb-post-time">
                  <span class="js-time-ago" data-time="${escapeHtml(createdAt)}">${escapeHtml(createdAt ? '' : 'vừa xong')}</span> ·

                    <div class="fb-audience-wrap">
                      <div class="fb-audience-icon-wrap" aria-hidden="false">
                        <img
                          class="fb-audience-icon"
                          src="https://static.xx.fbcdn.net/rsrc.php/v4/y5/r/qop9rFQ_Ys1.png"
                          alt="Công khai"
                          width="12"
                          height="12">
                      </div>
                      <div class="fb-audience-spacer"></div>
                    </div>
                  </div>
            </div>
            <button type="button" class="fb-post-more js-post-more" aria-label="Tùy chọn">…</button>
          </div>
          ${content.trim() ? `<div class="fb-post-content"></div>` : ''}
          ${mediaHtml}
          <div class="fb-post-stats" aria-label="Thống kê">
            <span class="fb-post-stat-link js-like-count" data-value="${likeCount}">${likeCount} lượt thích</span>
            <span class="fb-post-stat-link js-comment-count" data-value="${commentCount}">${commentCount} bình luận</span>
          </div>
          <div class="fb-post-actions" role="group" aria-label="Tương tác">
            <div class="fb-like-bar" data-post-id="${postId}">
              <button type="button" class="fb-post-action-btn fb-like-btn js-like-btn" aria-pressed="false" data-reaction="">
                <span class="fb-like-emoji" aria-hidden="true">👍</span>
                <span class="fb-like-text">Thích</span>
              </button>
              <div class="fb-reaction-picker" role="menu" aria-label="Chọn cảm xúc">
                <button type="button" class="fb-reaction-item" data-reaction="like" aria-label="Thích">👍</button>
                <button type="button" class="fb-reaction-item" data-reaction="love" aria-label="Yêu thích">❤️</button>
                <button type="button" class="fb-reaction-item" data-reaction="haha" aria-label="Haha">😂</button>
                <button type="button" class="fb-reaction-item" data-reaction="wow" aria-label="Wow">😮</button>
                <button type="button" class="fb-reaction-item" data-reaction="care" aria-label="Thương thương">🥰</button>
                <button type="button" class="fb-reaction-item" data-reaction="sad" aria-label="Buồn">😢</button>
                <button type="button" class="fb-reaction-item" data-reaction="angry" aria-label="Phẫn nộ">😡</button>
              </div>
            </div>
            <button type="button" class="fb-post-action-btn js-comment-btn" aria-label="Bình luận">
  <span class="fb-post-action-ico" aria-hidden="true">
    <i class="fb-post-sprite fb-post-sprite-comment"></i>
  </span>
  <span class="fb-post-action-text">Bình luận</span>
</button>
            <button type="button" class="fb-post-action-btn js-share-btn" aria-label="Chia sẻ">
  <span class="fb-post-action-ico" aria-hidden="true">
    <i class="fb-post-sprite fb-post-sprite-share"></i>
  </span>
  <span class="fb-post-action-text">Chia sẻ</span>
</button>
          </div>
          <div class="fb-post-comments" aria-label="Bình luận">
            <div class="fb-comment-list"></div>
            <form class="fb-comment-form" autocomplete="off">
              <input class="fb-comment-input" name="content" type="text" placeholder="Viết bình luận...">
              <button class="fb-comment-submit" type="submit" disabled>Gửi</button>
            </form>
          </div>
        `;

        if (content.trim()) {
          const contentEl = article.querySelector('.fb-post-content');
          if (contentEl) contentEl.textContent = content;
        }

        // Fill time-ago for this new card
        updateTimeAgoIn(article);

        return article;
      }

      // Allow other scripts (modal submit) to insert without reload
      window.fbFeedInsertPost = function(post) {
        if (!post || !post.id) return;
        if (findPostEl(post.id)) return;
        const el = renderPostCard(post);
        if (!el) return;
        const anchor = feedEl.querySelector('.fb-story-create');
        if (anchor && anchor.parentNode) {
          anchor.insertAdjacentElement('afterend', el);
        } else {
          feedEl.insertAdjacentElement('afterbegin', el);
        }
      };

      // Enable/disable comment submit button
      feedEl.addEventListener('input', (e) => {
        const input = e.target && e.target.closest && e.target.closest('.fb-comment-input');
        if (!input) return;
        const form = input.closest('.fb-comment-form');
        const btn = form && form.querySelector('.fb-comment-submit');
        if (!btn) return;
        btn.disabled = !(input.value || '').trim();
      });

      // Enable/disable submit button (modal)
      if (commentForm) {
        commentForm.addEventListener('input', (e) => {
          const input = e.target && e.target.closest && e.target.closest('.fb-comment-input');
          if (!input) return;
          const btn = commentForm.querySelector('.fb-comment-submit');
          if (!btn) return;
          btn.disabled = !(input.value || '').trim();
        });
      }

      // Click delegation
      feedEl.addEventListener('click', async (e) => {
        const reactionItem = e.target.closest && e.target.closest('.fb-reaction-item');
        if (reactionItem) {
          const postEl = reactionItem.closest('.fb-post-card');
          const postId = Number(postEl && postEl.dataset.postId) || 0;
          if (!postId) return;
          const reactionKey = normalizeReaction(reactionItem.dataset.reaction);
          const likeBtn = postEl.querySelector('.js-like-btn');
          if (likeBtn) likeBtn.disabled = true;
          try {
            const data = await sendReaction(postId, reactionKey || 'like');
            if (data && data.ok) {
              setCounts(postEl, Number(data.like_count) || 0, undefined);
              setReactionState(postEl, data.reaction || '');
            }
          } catch (_err) {} finally {
            if (likeBtn) likeBtn.disabled = false;
          }
          return;
        }

        const likeCountLink = e.target.closest && e.target.closest('.js-like-count');
        if (likeCountLink) {
          const postEl = likeCountLink.closest('.fb-post-card');
          const postId = Number(postEl && postEl.dataset.postId) || 0;
          if (!postId) return;
          openReactionsModal(postId);
          return;
        }

        const likeBtn = e.target.closest && e.target.closest('.js-like-btn');
        if (likeBtn) {
          const postEl = likeBtn.closest('.fb-post-card');
          const postId = Number(postEl && postEl.dataset.postId) || 0;
          if (!postId) return;
          likeBtn.disabled = true;
          try {
            const currentReaction = normalizeReaction(postEl && postEl.dataset.myReaction);
            // Click main button: if not reacted => like; else remove current reaction
            const wanted = currentReaction ? currentReaction : 'like';
            const data = await sendReaction(postId, wanted);
            if (data && data.ok) {
              setCounts(postEl, Number(data.like_count) || 0, undefined);
              setReactionState(postEl, data.reaction || '');
            }
          } catch (_err) {} finally {
            likeBtn.disabled = false;
          }
          return;
        }

        const commentBtn = e.target.closest && e.target.closest('.js-comment-btn');
        if (commentBtn) {
          const postEl = commentBtn.closest('.fb-post-card');
          if (!postEl) return;
          openCommentModalForPost(postEl);
          return;
        }

        const commentCountLink = e.target.closest && e.target.closest('.js-comment-count');
        if (commentCountLink) {
          const postEl = commentCountLink.closest('.fb-post-card');
          if (!postEl) return;
          openCommentModalForPost(postEl);
          return;
        }

        const moreBtn = e.target.closest && e.target.closest('.js-post-more');
        if (moreBtn) {
          const postEl = moreBtn.closest('.fb-post-card');
          const postId = Number(postEl && postEl.dataset.postId) || 0;
          if (!postId) return;
          try {
            window.fbPostMenuOpen(moreBtn, postEl);
          } catch (_e) {}
          return;
        }

        const shareBtn = e.target.closest && e.target.closest('.js-share-btn');
        if (shareBtn) {
          const postEl = shareBtn.closest('.fb-post-card');
          const postId = Number(postEl && postEl.dataset.postId) || 0;
          if (!postId) return;
          try {
            await sharePost(postId);
          } catch (_e) {}
          return;
        }
      });

      async function sharePost(postId) {
        const base = String(window.location.href || '').split('#')[0];
        const url = `${base}#post-${postId}`;
        if (navigator.share) {
          try {
            await navigator.share({ url, title: 'Bài viết' });
            return;
          } catch (_e) {}
        }
        try {
          await navigator.clipboard.writeText(url);
          alert('Đã sao chép liên kết');
        } catch (_e) {
          prompt('Sao chép liên kết:', url);
        }
      }

      // Click delegation for modal post (Like/Reaction, reactions list, Share)
      if (commentBackdrop) {
        commentBackdrop.addEventListener('click', async (e) => {
          if (!commentBackdrop.classList.contains('is-open')) return;
          if (!commentPostWrap || !commentPostWrap.contains(e.target)) return;

          const reactionItem = e.target.closest && e.target.closest('.fb-reaction-item');
          if (reactionItem) {
            const postId = commentModalPostId || (Number(commentPostWrap.dataset && commentPostWrap.dataset.postId) || 0);
            if (!postId) return;
            const feedPostEl = findPostEl(postId);
            const reactionKey = normalizeReaction(reactionItem.dataset.reaction);
            const modalLikeBtn = commentPostWrap.querySelector('.js-like-btn');
            const feedLikeBtn = feedPostEl && feedPostEl.querySelector('.js-like-btn');
            if (modalLikeBtn) modalLikeBtn.disabled = true;
            if (feedLikeBtn) feedLikeBtn.disabled = true;
            try {
              const data = await sendReaction(postId, reactionKey || 'like');
              if (data && data.ok) {
                if (feedPostEl) {
                  setCounts(feedPostEl, Number(data.like_count) || 0, undefined);
                  setReactionState(feedPostEl, data.reaction || '');
                }
                setCounts(commentPostWrap, Number(data.like_count) || 0, undefined);
                setReactionState(commentPostWrap, data.reaction || '');
              }
            } catch (_err) {} finally {
              if (modalLikeBtn) modalLikeBtn.disabled = false;
              if (feedLikeBtn) feedLikeBtn.disabled = false;
            }
            return;
          }

          const likeCountLink = e.target.closest && e.target.closest('.js-like-count');
          if (likeCountLink) {
            const postId = commentModalPostId || (Number(commentPostWrap.dataset && commentPostWrap.dataset.postId) || 0);
            if (!postId) return;
            openReactionsModal(postId);
            return;
          }

          const likeBtn = e.target.closest && e.target.closest('.js-like-btn');
          if (likeBtn) {
            const postId = commentModalPostId || (Number(commentPostWrap.dataset && commentPostWrap.dataset.postId) || 0);
            if (!postId) return;
            const feedPostEl = findPostEl(postId);
            const feedLikeBtn = feedPostEl && feedPostEl.querySelector('.js-like-btn');

            likeBtn.disabled = true;
            if (feedLikeBtn) feedLikeBtn.disabled = true;
            try {
              const currentReaction = normalizeReaction(commentPostWrap.dataset && commentPostWrap.dataset.myReaction);
              const wanted = currentReaction ? currentReaction : 'like';
              const data = await sendReaction(postId, wanted);
              if (data && data.ok) {
                if (feedPostEl) {
                  setCounts(feedPostEl, Number(data.like_count) || 0, undefined);
                  setReactionState(feedPostEl, data.reaction || '');
                }
                setCounts(commentPostWrap, Number(data.like_count) || 0, undefined);
                setReactionState(commentPostWrap, data.reaction || '');
              }
            } catch (_err) {} finally {
              likeBtn.disabled = false;
              if (feedLikeBtn) feedLikeBtn.disabled = false;
            }
            return;
          }

          const commentBtn = e.target.closest && e.target.closest('.js-comment-btn');
          if (commentBtn) {
            try {
              const ip = commentForm && commentForm.querySelector('.fb-comment-input');
              if (ip) ip.focus();
            } catch (_e) {}
            return;
          }

          const shareBtn = e.target.closest && e.target.closest('.js-share-btn');
          if (shareBtn) {
            const postId = commentModalPostId || (Number(commentPostWrap.dataset && commentPostWrap.dataset.postId) || 0);
            if (!postId) return;
            try {
              await sharePost(postId);
            } catch (_e) {}
            return;
          }
        });
      }

      // Comment submit delegation
      feedEl.addEventListener('submit', async (e) => {
        const form = e.target && e.target.closest && e.target.closest('.fb-comment-form');
        if (!form) return;
        e.preventDefault();

        const postEl = form.closest('.fb-post-card');
        const postId = Number(postEl && postEl.dataset.postId) || 0;
        const input = form.querySelector('.fb-comment-input');
        const btn = form.querySelector('.fb-comment-submit');
        const content = (input && input.value || '').trim();
        if (!postId || !content) return;

        if (btn) btn.disabled = true;
        try {
          const resp = await fetch('../actions/comment.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: qs({
              post_id: postId,
              content,
              ajax: 1
            })
          });
          const data = await resp.json();
          if (data && data.ok) {
            setCounts(postEl, undefined, Number(data.comment_count) || 0);
            if (data.comment) {
              appendComment(postEl, data.comment);
            }
            if (input) {
              input.value = '';
              input.dispatchEvent(new Event('input', {
                bubbles: true
              }));
            }
          }
        } catch (_err) {} finally {
          if (btn) btn.disabled = false;
        }
      });

      // Comment submit (modal)
      if (commentForm) {
        commentForm.addEventListener('submit', async (e) => {
          e.preventDefault();

          const postId = Number(commentPostIdInput && commentPostIdInput.value) || commentModalPostId || 0;
          const parentId = Number(commentParentIdInput && commentParentIdInput.value) || 0;
          const input = commentForm.querySelector('.fb-comment-input');
          const btn = commentForm.querySelector('.fb-comment-submit');
          const content = (input && input.value || '').trim();
          if (!postId || !content) return;

          if (btn) btn.disabled = true;
          try {
            const resp = await fetch('../actions/comment.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: qs({ post_id: postId, parent_id: parentId, content, ajax: 1 })
            });

            const data = await resp.json().catch(() => null);
            if (data && data.ok) {
              const postEl = findPostEl(postId);
              if (postEl) {
                setCounts(postEl, undefined, Number(data.comment_count) || 0);
                if (data.comment) appendComment(postEl, data.comment);
              }
              if (data.comment && commentListEl && commentModalPostId === postId) {
                appendCommentToList(commentListEl, data.comment);
              }
              if (input) {
                input.value = '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
              }

              // reset reply mode after submit
              setReplyTarget(0, '');
            }
          } catch (_err) {} finally {
            // input event sẽ tự set disabled lại
            if (btn) btn.disabled = false;
          }
        });
      }

      // Comment actions (modal): like/reply/more menu/edit/delete
      if (commentBackdrop) {
        commentBackdrop.addEventListener('click', async (e) => {
          if (!commentBackdrop.classList.contains('is-open')) return;
          if (!commentListEl || !commentListEl.contains(e.target)) return;

          const item = e.target.closest && e.target.closest('.fb-comment-item');
          if (!item) return;
          const commentId = Number(item.dataset && item.dataset.commentId) || 0;
          const postId = commentModalPostId || (Number(commentPostWrap && commentPostWrap.dataset && commentPostWrap.dataset.postId) || 0);
          if (!commentId) return;

          const moreBtn = e.target.closest && e.target.closest('.js-comment-more');
          if (moreBtn) {
            const menu = item.querySelector('.fb-comment-menu');
            if (!menu) return;
            const willOpen = !menu.classList.contains('is-open');
            closeAllCommentMenus(item);
            menu.classList.toggle('is-open', willOpen);
            return;
          }

          const likeBtn = e.target.closest && e.target.closest('.js-comment-like');
          if (likeBtn) {
            likeBtn.disabled = true;
            try {
              const resp = await fetch('../actions/comment_like.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                  'Accept': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest'
                },
                body: qs({ comment_id: commentId })
              });
              const data = await resp.json().catch(() => null);
              if (data && data.ok) {
                likeBtn.classList.toggle('is-liked', !!data.liked);
                const lc = Number(data.like_count);
                const countEl = item.querySelector('.js-comment-like-count');
                if (countEl && !Number.isNaN(lc)) {
                  countEl.dataset.value = String(lc);
                  countEl.textContent = `${lc} thích`;
                  countEl.hidden = !(lc > 0);
                }
              }
            } catch (_err) {} finally {
              likeBtn.disabled = false;
            }
            return;
          }

          const replyBtn = e.target.closest && e.target.closest('.js-comment-reply');
          if (replyBtn) {
            const userName = String(item.dataset && item.dataset.userName || '');
            setReplyTarget(commentId, userName);
            try {
              const ip = commentForm && commentForm.querySelector('.fb-comment-input');
              if (ip) ip.focus();
            } catch (_e) {}
            return;
          }

          const editBtn = e.target.closest && e.target.closest('.js-comment-edit');
          if (editBtn) {
            closeAllCommentMenus();
            const bubble = item.querySelector('.fb-comment-bubble');
            const textEl = item.querySelector('.fb-comment-text');
            if (!bubble || !textEl) return;
            if (item.querySelector('.fb-comment-edit-wrap')) return;

            const wrap = document.createElement('div');
            wrap.className = 'fb-comment-edit-wrap';
            wrap.innerHTML = `
              <textarea class="fb-comment-edit-input"></textarea>
              <div class="fb-comment-edit-actions">
                <button type="button" class="js-comment-edit-cancel">Hủy</button>
                <button type="button" class="js-comment-edit-save">Lưu</button>
              </div>
            `;
            const ta = wrap.querySelector('textarea');
            if (ta) ta.value = String(textEl.textContent || '').trim();
            textEl.style.display = 'none';
            bubble.appendChild(wrap);
            if (ta) ta.focus();
            return;
          }

          const cancelEditBtn = e.target.closest && e.target.closest('.js-comment-edit-cancel');
          if (cancelEditBtn) {
            const wrap = item.querySelector('.fb-comment-edit-wrap');
            const textEl = item.querySelector('.fb-comment-text');
            if (wrap) wrap.remove();
            if (textEl) textEl.style.display = '';
            return;
          }

          const saveEditBtn = e.target.closest && e.target.closest('.js-comment-edit-save');
          if (saveEditBtn) {
            const wrap = item.querySelector('.fb-comment-edit-wrap');
            const ta = wrap && wrap.querySelector('textarea');
            const nextContent = (ta && ta.value || '').trim();
            if (!nextContent) return;
            saveEditBtn.disabled = true;
            try {
              const resp = await fetch('../actions/comment_edit.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                  'Accept': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest'
                },
                body: qs({ comment_id: commentId, content: nextContent })
              });
              const data = await resp.json().catch(() => null);
              if (data && data.ok) {
                const textEl = item.querySelector('.fb-comment-text');
                if (textEl) {
                  textEl.textContent = String(data.content || nextContent);
                  textEl.style.display = '';
                }
                if (wrap) wrap.remove();
              }
            } catch (_err) {} finally {
              saveEditBtn.disabled = false;
            }
            return;
          }

          const deleteBtn = e.target.closest && e.target.closest('.js-comment-delete');
          if (deleteBtn) {
            closeAllCommentMenus();
            if (!confirm('Xóa bình luận này?')) return;
            deleteBtn.disabled = true;
            try {
              const resp = await fetch('../actions/comment_delete.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                  'Accept': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest'
                },
                body: qs({ comment_id: commentId })
              });
              const data = await resp.json().catch(() => null);
              if (data && data.ok) {
                // Remove this comment and any nested replies in modal list
                const removeIds = new Set([commentId]);
                let changed = true;
                while (changed) {
                  changed = false;
                  commentListEl.querySelectorAll('.fb-comment-item').forEach((el) => {
                    const pid = Number(el.dataset && el.dataset.parentId) || 0;
                    const cid = Number(el.dataset && el.dataset.commentId) || 0;
                    if (pid && removeIds.has(pid) && cid && !removeIds.has(cid)) {
                      removeIds.add(cid);
                      changed = true;
                    }
                  });
                }
                removeIds.forEach((id) => {
                  const el = commentListEl.querySelector(`[data-comment-id="${id}"]`);
                  if (el) el.remove();
                });

                if (postId) {
                  const postEl = findPostEl(postId);
                  if (postEl) setCounts(postEl, undefined, Number(data.comment_count) || 0);
                  if (commentPostWrap) setCounts(commentPostWrap, undefined, Number(data.comment_count) || 0);
                }
              }
            } catch (_err) {} finally {
              deleteBtn.disabled = false;
            }
            return;
          }
        });
      }

      // Live update time-ago labels
      updateTimeAgoIn(document);
      setInterval(() => updateTimeAgoIn(document), 30000);

      if (socket && socket.on) {
        socket.on('post:create', (data) => {
          const post = data && data.post;
          if (!post || !post.id) return;
          if (findPostEl(post.id)) return;
          const el = renderPostCard(post);
          if (!el) return;
          const anchor = feedEl.querySelector('.fb-story-create');
          if (anchor && anchor.parentNode) {
            anchor.insertAdjacentElement('afterend', el);
          } else {
            feedEl.insertAdjacentElement('afterbegin', el);
          }
        });

        socket.on('post:delete', (data) => {
          const postId = Number(data && data.post_id) || 0;
          const el = postId ? findPostEl(postId) : null;
          if (el) el.remove();
        });

        socket.on('post:reaction', (data) => {
          const postId = Number(data && data.post_id) || 0;
          const likeCount = Number(data && data.like_count);
          const actorId = Number(data && data.actor_user_id) || 0;
          const actorReaction = (data && data.actor_reaction) || '';
          const el = postId ? findPostEl(postId) : null;
          if (!el) return;
          if (!Number.isNaN(likeCount)) {
            setCounts(el, likeCount, undefined);
          }
          if (actorId === CURRENT_USER_ID) {
            setReactionState(el, actorReaction);
          }

          if (commentPostWrap && commentBackdrop && commentBackdrop.classList.contains('is-open') && commentModalPostId === postId) {
            if (!Number.isNaN(likeCount)) {
              setCounts(commentPostWrap, likeCount, undefined);
            }
            if (actorId === CURRENT_USER_ID) {
              setReactionState(commentPostWrap, actorReaction);
            }
          }
          if (reactionsOverlay.classList.contains('is-open') && reactionsModalPostId === postId) {
            // refresh list while modal open
            openReactionsModal(postId);
          }
        });

        socket.on('post:comment', (data) => {
          const postId = Number(data && data.post_id) || 0;
          const commentCount = Number(data && data.comment_count);
          const el = postId ? findPostEl(postId) : null;
          if (!el) return;
          if (!Number.isNaN(commentCount)) {
            setCounts(el, undefined, commentCount);
          }

          if (commentPostWrap && commentBackdrop && commentBackdrop.classList.contains('is-open') && commentModalPostId === postId) {
            if (!Number.isNaN(commentCount)) {
              setCounts(commentPostWrap, undefined, commentCount);
            }
          }
          if (data && data.comment) {
            const commentsWrap = el.querySelector('.fb-post-comments');
            if (commentsWrap && commentsWrap.classList.contains('is-open')) {
              appendComment(el, data.comment);
            }

            if (commentListEl && commentBackdrop && commentBackdrop.classList.contains('is-open') && commentModalPostId === postId) {
              appendCommentToList(commentListEl, data.comment);
            }
          }
        });

        socket.on('comment:edit', (data) => {
          const commentId = Number(data && data.comment_id) || 0;
          const content = String(data && data.content || '');
          const likeCount = Number(data && data.like_count);

          // Count is global -> update for everyone
          if (!Number.isNaN(likeCount)) {
            document.querySelectorAll(`[data-comment-id="${commentId}"] .js-comment-like-count`).forEach((el) => {
              el.dataset.value = String(likeCount);
              el.textContent = `${likeCount} thích`;
              el.hidden = !(likeCount > 0);
            });
          }

          // Like state is per-user -> only sync for the same logged-in user across tabs
          const actorId = Number(data && data.actor_user_id) || 0;
          if (actorId && typeof CURRENT_USER_ID !== 'undefined' && actorId === CURRENT_USER_ID) {
            const liked = !!(data && data.liked);
            document.querySelectorAll(`[data-comment-id="${commentId}"] .js-comment-like`).forEach((btn) => {
              btn.classList.toggle('is-liked', liked);
            });
          }
          const postId = Number(data && data.post_id) || 0;
          const commentCount = Number(data && data.comment_count);
          const deleted = Array.isArray(data && data.deleted_ids) ? data.deleted_ids : [];
          const ids = deleted.length ? deleted.map((x) => Number(x) || 0).filter(Boolean) : [Number(data && data.comment_id) || 0].filter(Boolean);
          if (!ids.length) return;

          ids.forEach((id) => {
            document.querySelectorAll(`[data-comment-id="${id}"]`).forEach((el) => el.remove());
          });

          if (postId && !Number.isNaN(commentCount)) {
            const postEl = findPostEl(postId);
            if (postEl) setCounts(postEl, undefined, commentCount);
            if (commentPostWrap && commentBackdrop && commentBackdrop.classList.contains('is-open') && commentModalPostId === postId) {
              setCounts(commentPostWrap, undefined, commentCount);
            }
          }

          // if current reply target was deleted, reset
          const currentParent = Number(commentParentIdInput && commentParentIdInput.value) || 0;
          if (currentParent && ids.includes(currentParent)) {
            setReplyTarget(0, '');
          }
        });

        socket.on('comment:like', (data) => {
          const commentId = Number(data && data.comment_id) || 0;
          if (!commentId) return;
          const actorId = Number(data && data.actor_user_id) || 0;
          // Like state is per-user; only sync for the same logged-in user across tabs
          if (actorId && typeof CURRENT_USER_ID !== 'undefined' && actorId !== CURRENT_USER_ID) return;
          const liked = !!(data && data.liked);
          document.querySelectorAll(`[data-comment-id="${commentId}"] .js-comment-like`).forEach((btn) => {
            btn.classList.toggle('is-liked', liked);
          });
        });
      }

      // ===== CONTACTS (right sidebar) =====
      const contactsRoot = document.getElementById('fbContactsList');

      async function presencePing() {
        try {
          await fetch('../actions/presence_ping.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
        } catch (_e) {}
      }

      function renderContacts(items) {
        if (!contactsRoot) return;
        contactsRoot.innerHTML = '';
        (Array.isArray(items) ? items : []).forEach((u) => {
          const id = Number(u && u.id) || 0;
          const name = String(u && u.name || '').trim();
          const avatar = String(u && u.avatar || '');
          const online = !!(u && u.online);
          const lastSeen = String(u && u.last_seen || '').trim();
          if (!id || !name) return;

          const row = document.createElement('div');
          row.className = 'fb-contact-item' + (online ? ' is-online' : '');
          row.dataset.userId = String(id);
          row.dataset.userName = name;
          row.dataset.userAvatar = avatar;
          row.dataset.userOnline = online ? '1' : '0';
          row.dataset.userLastSeen = lastSeen;

          const avWrap = document.createElement('span');
          avWrap.className = 'fb-contact-avatar-wrap';
          const img = document.createElement('img');
          img.className = 'fb-contact-avatar';
          img.alt = escapeHtml(name);
          img.src = avatar;
          const dot = document.createElement('span');
          dot.className = 'fb-contact-dot';
          avWrap.appendChild(img);
          avWrap.appendChild(dot);

          const meta = document.createElement('span');
          meta.className = 'fb-contact-meta';
          const nameEl = document.createElement('span');
          nameEl.className = 'fb-contact-name';
          nameEl.textContent = name;
          meta.appendChild(nameEl);

          if (!online && lastSeen) {
            const seenEl = document.createElement('span');
            seenEl.className = 'fb-contact-last';
            seenEl.textContent = lastSeen;
            meta.appendChild(seenEl);
          }

          row.appendChild(avWrap);
          row.appendChild(meta);
          contactsRoot.appendChild(row);
        });
      }

      async function refreshContacts() {
        if (!contactsRoot) return;
        try {
          const resp = await fetch('../actions/get_contacts.php?limit=40', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const data = await resp.json().catch(() => null);
          if (data && data.ok) {
            renderContacts(data.items || []);
          }
        } catch (_e) {}
      }

      // initial + polling
      presencePing();
      refreshContacts();
      setInterval(presencePing, 30000);
      setInterval(refreshContacts, 12000);
    })();

    // ===== POST MENU (three dots) =====
    (function() {
      const menu = document.createElement('div');
      menu.id = 'fbPostMenu';
      menu.className = 'fb-post-menu';
      menu.setAttribute('role', 'menu');
      document.body.appendChild(menu);

      let currentPostEl = null;
      let currentAnchorEl = null;

      function closeMenu() {
        menu.classList.remove('is-open');
        menu.innerHTML = '';
        currentPostEl = null;
        currentAnchorEl = null;
      }

      function positionMenu(anchorEl) {
        const r = anchorEl.getBoundingClientRect();
        const mW = 360;
        const pad = 10;

        // default align right edge with button
        let left = r.right - mW;
        let top = r.bottom + 8;

        const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        const vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);

        const maxLeft = vw - menu.offsetWidth - pad;
        const minLeft = pad;
        left = Math.min(Math.max(left, minLeft), maxLeft);

        // flip upward if overflow
        const estimatedHeight = Math.min(menu.scrollHeight || 420, 520);
        if (top + estimatedHeight > vh - pad) {
          top = r.top - estimatedHeight - 8;
        }
        top = Math.max(pad, Math.min(top, vh - pad - 80));

        menu.style.left = `${Math.round(left)}px`;
        menu.style.top = `${Math.round(top)}px`;
      }

      function itemHtml(icon, title, sub, action) {
        const subHtml = sub ? `<div class="fb-post-menu-sub">${sub}</div>` : '';
        return `
          <button type="button" class="fb-post-menu-item" role="menuitem" data-action="${action}">
            <span class="fb-post-menu-ico" aria-hidden="true">${icon}</span>
            <span class="fb-post-menu-text">
              <div class="fb-post-menu-title">${title}</div>
              ${subHtml}
            </span>
          </button>
        `;
      }

      window.fbPostMenuOpen = function(anchorEl, postEl) {
        if (!anchorEl || !postEl) return;

        // toggle
        if (menu.classList.contains('is-open') && currentPostEl === postEl) {
          closeMenu();
          return;
        }

        currentPostEl = postEl;
        currentAnchorEl = anchorEl;

        const postId = Number(postEl.dataset.postId) || 0;
        const ownerId = Number(postEl.dataset.ownerId) || 0;
        const isOwner = ownerId === CURRENT_USER_ID;

        // Build menu like screenshot (labels only; most are placeholders)
        let html = '';
        html += itemHtml('🔖', 'Lưu bài viết', 'Thêm vào danh sách mục đã lưu.', 'save');
        html += '<div class="fb-post-menu-sep" role="separator"></div>';
        html += itemHtml('💬', 'Ai có thể bình luận về bài viết của bạn?', '', 'who_comment');
        if (isOwner) {
          html += itemHtml('✏️', 'Chỉnh sửa bài viết', '', 'edit');
          html += itemHtml('⚙️', 'Chỉnh sửa đối tượng', '', 'audience');
        }
        html += itemHtml('🔕', 'Tắt thông báo về bài viết này', '', 'mute');
        html += itemHtml('🌐', 'Tắt bản dịch', '', 'translation');
        html += itemHtml('📅', 'Chỉnh sửa ngày', '', 'date');
        html += itemHtml('&lt;/&gt;', 'Nhúng', '', 'embed');
        if (isOwner) {
          html += '<div class="fb-post-menu-sep" role="separator"></div>';
          html += itemHtml('🗄️', 'Chuyển vào kho lưu trữ', '', 'archive');
          html += itemHtml('🗑️', 'Chuyển vào thùng rác', 'Các mục trong thùng rác sẽ bị xóa sau 30 ngày.', 'trash');
        }

        menu.innerHTML = html;
        menu.classList.add('is-open');

        // must measure after display
        positionMenu(anchorEl);
      };

      // Click actions
      menu.addEventListener('click', async (e) => {
        const btn = e.target.closest && e.target.closest('.fb-post-menu-item');
        if (!btn) return;

        const action = btn.dataset.action;
        const postEl = currentPostEl;
        const postId = Number(postEl && postEl.dataset.postId) || 0;
        const ownerId = Number(postEl && postEl.dataset.ownerId) || 0;

        if (!postEl || !postId) {
          closeMenu();
          return;
        }

        // Only implement delete right now (others are UI-only)
        if (action === 'trash') {
          if (ownerId !== CURRENT_USER_ID) {
            closeMenu();
            return;
          }
          btn.disabled = true;
          try {
            const resp = await fetch('../actions/post_delete.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: new URLSearchParams({
                id: String(postId),
                ajax: '1'
              }).toString()
            });
            const data = await resp.json().catch(() => null);
            if (data && data.ok) {
              postEl.remove();
            }
          } catch (_err) {} finally {
            btn.disabled = false;
            closeMenu();
          }
          return;
        }

        // placeholder for other actions
        closeMenu();
      });

      // Close on outside click
      document.addEventListener('click', (e) => {
        if (!menu.classList.contains('is-open')) return;
        const t = e.target;
        if (t === menu || (menu.contains(t))) return;
        if (currentAnchorEl && (t === currentAnchorEl || currentAnchorEl.contains(t))) return;
        closeMenu();
      });

      // Close on Escape
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && menu.classList.contains('is-open')) {
          closeMenu();
        }
      });

      // Reposition on resize/scroll (best-effort)
      window.addEventListener('resize', () => {
        if (!menu.classList.contains('is-open') || !currentAnchorEl) return;
        positionMenu(currentAnchorEl);
      }, {
        passive: true
      });
      window.addEventListener('scroll', () => {
        if (!menu.classList.contains('is-open') || !currentAnchorEl) return;
        positionMenu(currentAnchorEl);
      }, {
        passive: true,
        capture: true
      });
    })();

    // Tham chiếu các element cần dùng
    const header = document.getElementById('headerBox'); // chứa logo + search
    // mark script load for debugging (helps detect cached/old JS)
    try {
      sessionStorage.setItem('mPhpScriptLoadedAt', JSON.stringify({
        ts: Date.now()
      }));
      console.log('m.php script load marker written');
    } catch (e) {
      console.warn('mPhpScriptLoadedAt write failed', e);
    }
    // If page loaded with ?clearRecents=1 then remove recentSearches (safe, explicit)
    try {
      const params = new URLSearchParams(window.location.search || '');
      if (params.get('clearRecents') === '1') {
        try {
          sessionStorage.removeItem('recentSearches');
          sessionStorage.removeItem('recentLastCalled');
          // also clear localStorage fallback if present
          try {
            localStorage.removeItem('recentSearches');
          } catch (_e) {}
          console.log('recentSearches cleared via ?clearRecents=1');
        } catch (e) {
          console.warn('clearRecents failed', e);
        }
        try {
          if (window.updateRecentDebug) window.updateRecentDebug();
        } catch (_e) {}
      }
    } catch (e) {}
    const wrapper = document.getElementById('searchWrapper'); // wrapper của search (fake + input)
    const input = document.getElementById('searchInput'); // input thật
    const backBtn = document.getElementById('backBtn'); // nút back (mũi tên)
    const fake = document.getElementById('fakePlaceholder'); // fake placeholder hiển thị chữ

    // ---------- Cấu hình padding (để chữ input khi hiện thực tế bắt đầu đúng chỗ) ----------
    const POS_DEFAULT = '38px'; // padding-left mặc định (né kính lúp)
    const POS_FOCUSED = '12px'; // padding-left khi focus (để chữ sát lề bên trái)

    /* Hàm mở chế độ tìm kiếm:
       - thêm class .is-searching để ẩn logo và hiện back-btn (CSS xử lý)
       - thêm .focused để kích hoạt animation fake-placeholder trượt trái
       - set padding-left cho input để con trỏ (sau blur) và text căn đúng chỗ
    */
    function openSearch() {
      header.classList.add('is-searching'); // hiển thị trạng thái tìm kiếm
      wrapper.classList.add('focused'); // trigger CSS animation (kính lúp ẩn, fake trượt)
      input.style.paddingLeft = POS_FOCUSED;
      // show results container when opening search (if previously hidden)
      try {
        const rb = document.getElementById('searchResult');
        if (rb) rb.style.display = '';
      } catch (err) {}
      // if no input, show recent searches
      try {
        if (!input.value || input.value.trim().length < 2) renderRecentSearches();
      } catch (e) {}
      // CSS controls back button visibility; no inline override needed
    }

    /* Hàm đóng chế độ tìm kiếm:
       - remove các class, blur input (bỏ focus), reset padding nếu muốn
       - giữ value nếu người dùng đã gõ (không tự xóa trừ khi bấm back)
    */
    function closeSearch() {
      header.classList.remove('is-searching');
      wrapper.classList.remove('focused');
      input.style.paddingLeft = POS_DEFAULT;
      // blur input để con trỏ mất focus (nếu đang gõ)
      try {
        input.blur();
      } catch (err) {}
      // nếu input rỗng -> remove class has-text
      if (!input.value) wrapper.classList.remove('has-text');
      // clear and hide results when closing search so UI doesn't appear to 'keep' results
      try {
        const rb = document.getElementById('searchResult');
        if (rb) {
          rb.innerHTML = '';
          rb.style.display = 'none';
        }
      } catch (err) {}
      // CSS controls back button visibility; no inline override needed
    }

    // Khi input nhận focus -> mở search
    input.addEventListener('focus', (e) => {
      openSearch();
      // đảm bảo con trỏ bắt đầu ở đầu (tránh highlight toàn bộ text khi click nhiều lần)
      try {
        input.setSelectionRange(0, 0);
      } catch (err) {}
    });

    // Khi click vào vùng .search-wrapper thì focus input (giúp hiển thị back-btn khi click bất kỳ chỗ trong pill)
    wrapper.addEventListener && wrapper.addEventListener('click', (e) => {
      // nếu click trúng backBtn thì không override (backBtn có handler riêng)
      if (e.target && (e.target.closest && e.target.closest('#backBtn'))) return;
      try {
        input.focus();
      } catch (err) {}
    });

    // Khi user gõ -> cập nhật class has-text để ẩn fake placeholder
    input.addEventListener('input', (e) => {
      if (e.target.value && e.target.value.trim() !== '') wrapper.classList.add('has-text');
      else wrapper.classList.remove('has-text');
    });

    // Nút back: xóa chữ và đóng search
    backBtn.addEventListener('click', (e) => {
      e.stopPropagation(); // ngăn event click lan thêm
      input.value = '';
      wrapper.classList.remove('has-text');
      closeSearch();
    });

    /* === QUAN TRỌNG: Click mọi vị trí đều đóng thanh tìm kiếm (dù đang nhập) ===
       - Thay vì kiểm tra header.contains(e.target) để bỏ qua clicks trong header,
         ta chỉ giữ open nếu user click TRONG searchWrapper (ví dụ click vào dropdown hoặc input).
       - Nếu click ở bất kỳ chỗ nào KHÔNG thuộc searchWrapper => đóng search.
       - Lưu ý sử dụng capture phase (tham số `true`) để bắt event sớm, đảm bảo đóng
         ngay cả khi input vẫn đang focus hoặc đang có selection.
    */
    document.addEventListener('click', (e) => {
      // chỉ quan tâm khi đang mở trạng thái tìm kiếm
      if (!header.classList.contains('is-searching')) return;

      // nếu click vào nút xóa gần đây -> bỏ qua (để handler riêng xử lý)
      try {
        const path = (e.composedPath && e.composedPath()) || e.path || (function() {
          const p = [];
          let el = e.target;
          while (el) {
            if (el instanceof Element) p.push(el);
            el = el.parentElement;
          }
          return p;
        })();
        if (path && path.length) {
          for (let i = 0; i < path.length; i++) {
            const el = path[i];
            if (el && el.classList && el.classList.contains && el.classList.contains('recent-remove')) return;
          }
        }
      } catch (err) {}

      // nếu click nằm trong khu vực searchWrapper -> KHÔNG đóng (người click vẫn muốn tương tác với search)
      if (wrapper && wrapper.contains(e.target)) {
        return;
      }

      // tất cả click khác (mọi vị trí trên trang) -> đóng search
      closeSearch();
    }, true); // <-- dùng capture để bắt click sớm (đặc biệt khi input đang focus)

    /* --- TÙY CHỌN: Nếu bạn muốn mọi click (kể cả click vào chính ô tìm kiếm) cũng đóng:
         Thay handler phía trên bằng:
         document.addEventListener('click', (e) => {
           if (!header.classList.contains('is-searching')) return;
           closeSearch();
         }, true);
       Nhưng thông thường sẽ giữ lại behavior không đóng khi người dùng click trong ô search. */

    /* ---------- Các xử lý nhỏ khác (keyboard / accessibility) ---------- */

    // Escape để đóng
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' || e.key === 'Esc') {
        if (header.classList.contains('is-searching')) closeSearch();
      }
    });

    // Khi click vào fakePlaceholder (nếu bạn muốn click vào chữ giả cũng focus input)
    fake.addEventListener ? fake.addEventListener('click', () => {
      input.focus();
    }) : null;

    // Khi trang load: nếu input có value (ví dụ lưu trạng thái), hiển thị class tương ứng
    document.addEventListener('DOMContentLoaded', () => {
      if (input.value && input.value.trim() !== '') wrapper.classList.add('has-text');
    });

    /* ================= Tooltip + center nav (giữ nguyên) ================= */
    const cnavItems = Array.from(document.querySelectorAll('.cnav-item'));
    cnavItems.forEach((item, idx) => {
      item.addEventListener('click', () => {
        // Always set active on click (including Home). Loader logic for Home is handled separately.
        cnavItems.forEach(i => {
          i.classList.remove('active');
          i.setAttribute('aria-pressed', 'false');
        });
        item.classList.add('active');
        item.setAttribute('aria-pressed', 'true');
      });
      item.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ' || e.code === 'Space') {
          e.preventDefault();
          item.click();
        } else if (e.key === 'ArrowRight') {
          e.preventDefault();
          const next = (idx + 1) % cnavItems.length;
          cnavItems[next].focus();
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault();
          const prev = (idx - 1 + cnavItems.length) % cnavItems.length;
          cnavItems[prev].focus();
        }
      });
    });

    const TOOLTIP_SHOW_DELAY = 500,
      TOOLTIP_HIDE_DELAY = 120;

    function wireTooltips(elements) {
      elements.forEach((el) => {
        let showTimer = null;
        let hideTimer = null;
        const tip = el.querySelector('.tooltip');
        if (!tip) return;

        el.addEventListener('mouseenter', () => {
          if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
          }
          showTimer = setTimeout(() => {
            tip.classList.add('tooltip-visible');
          }, TOOLTIP_SHOW_DELAY);
        });

        el.addEventListener('mouseleave', () => {
          if (showTimer) {
            clearTimeout(showTimer);
            showTimer = null;
          }
          hideTimer = setTimeout(() => {
            tip.classList.remove('tooltip-visible');
          }, TOOLTIP_HIDE_DELAY);
        });

        el.addEventListener('focus', () => {
          tip.classList.add('tooltip-visible');
        }, true);

        el.addEventListener('blur', () => {
          tip.classList.remove('tooltip-visible');
        }, true);
      });
    }

    wireTooltips(Array.from(document.querySelectorAll('.cnav-item')));
    wireTooltips(Array.from(document.querySelectorAll('.header-right .icon-btn')));

    // Home controls Messenger popover inline; prevent shared topnav.js from initializing it too.
    try { window.__FB_HOME_MESSENGER__ = true; } catch (_e) {}

    /* ================= Messenger popover ================= */
    const messengerBtn = document.getElementById('messengerBtn');
    const messengerPopover = document.getElementById('messengerPopover');
    const fixedHeader = document.querySelector('.header');

    const mpConvList = document.getElementById('mpConvList');
    const mpEmpty = document.getElementById('mpEmpty');

    /* ================= Account popover ================= */
    const accountBtn = document.querySelector('.header-right .account-btn');
    const accountPopover = document.getElementById('accountPopover');

    function isAccountOpen() {
      return !!(accountPopover && accountPopover.classList.contains('open'));
    }

    function positionAccountPopover() {
      if (!accountBtn || !accountPopover) return;
      const rect = accountBtn.getBoundingClientRect();
      const headerRect = fixedHeader ? fixedHeader.getBoundingClientRect() : null;

      const popStyles = window.getComputedStyle(accountPopover);
      const offsetY = Number.parseFloat(popStyles.getPropertyValue('--acct-offset-y')) || 0;
      const offsetX = Number.parseFloat(popStyles.getPropertyValue('--acct-offset-x')) || 0;
      const leftPad = Number.parseFloat(popStyles.getPropertyValue('--acct-left-pad')) || 0;
      const rightPad = Number.parseFloat(popStyles.getPropertyValue('--acct-right-pad')) || 0;
      const popW = accountPopover.offsetWidth || 420;

      // FB-like: anchor X/Y then translate(-100%, 0) to align the popover's right edge to X.
      let x = rect.right + offsetX;
      let y = (headerRect ? headerRect.bottom : rect.bottom) + offsetY;

      const minX = popW + leftPad;
      const maxX = Math.max(minX, window.innerWidth - rightPad);
      if (x < minX) x = minX;
      if (x > maxX) x = maxX;
      if (y < 0) y = 0;

      accountPopover.style.setProperty('--acct-x', `${x}px`);
      accountPopover.style.setProperty('--acct-y', `${y}px`);
    }

    function openAccountPopover() {
      if (!accountBtn || !accountPopover) return;
      if (messengerPopover && messengerPopover.classList.contains('open')) closeMessenger();
      if (isComposeOpen()) closeCompose();
      closeMoreMenu();
      closeOptionsMenu();

      accountPopover.classList.add('open');
      accountPopover.setAttribute('aria-hidden', 'false');
      resetAccountMenu();
      acctSyncPopoverHeight();
      positionAccountPopover();
    }

    function closeAccountPopover() {
      if (!accountBtn || !accountPopover) return;
      accountPopover.classList.remove('open');
      accountPopover.setAttribute('aria-hidden', 'true');
    }

    function toggleAccountPopover() {
      if (isAccountOpen()) closeAccountPopover();
      else openAccountPopover();
    }

    // Auto expand popover height for secondary views (clamped to max-height)
    const ACCT_BASE_HEIGHT = (() => {
      if (!accountPopover) return 460;
      const h = Number.parseFloat(window.getComputedStyle(accountPopover).height);
      return Number.isFinite(h) && h > 0 ? h : 460;
    })();

    function acctSyncPopoverHeight() {
      if (!accountPopover || !acctMenuSlider) return;

      const styles = window.getComputedStyle(accountPopover);
      const maxH = Number.parseFloat(styles.maxHeight);
      const maxHeight = Number.isFinite(maxH) && maxH > 0 ? maxH : Math.max(300, window.innerHeight - 72);

      const isSecondary = acctMenuSlider.classList.contains('slide-active');
      if (!isSecondary) {
        accountPopover.style.height = `${Math.min(ACCT_BASE_HEIGHT, maxHeight)}px`;
        return;
      }

      const secondPanel = acctMenuSlider.querySelector('.menu-track > .menu-panel:nth-child(2)');
      if (!secondPanel) return;

      const secHeader = secondPanel.querySelector('.sec-header');
      const activeView = secondPanel.querySelector('#acctSecViews .sec-view.active');

      const panelPaddingY = 12; // menu-panel padding: 6px top + 6px bottom
      const headerH = secHeader ? secHeader.offsetHeight : 0;
      const viewH = activeView ? activeView.scrollHeight : 0;
      const desired = Math.max(ACCT_BASE_HEIGHT, headerH + panelPaddingY + (viewH || secondPanel.scrollHeight) + 24);
      const target = Math.max(300, Math.min(desired, maxHeight));

      accountPopover.style.height = `${target}px`;
    }

    /* ================= Account menu (inlined) ================= */
    const acctMenuRoot = accountPopover ? accountPopover.querySelector('#acctMenuRoot') : null;
    const acctMenuSlider = accountPopover ? accountPopover.querySelector('#acctMenuSlider') : null;
    const acctBtnSettingsPrivacy = accountPopover ? accountPopover.querySelector('#acctBtnSettingsPrivacy') : null;
    const acctBtnHelpSupport = accountPopover ? accountPopover.querySelector('#acctBtnHelpSupport') : null;
    const acctBtnDisplayAccessibility = accountPopover ? accountPopover.querySelector('#acctBtnDisplayAccessibility') : null;
    const acctBtnBack = accountPopover ? accountPopover.querySelector('#acctBtnBack') : null;
    const acctSecTitle = accountPopover ? accountPopover.querySelector('#acctSecTitle') : null;
    const acctSecViews = accountPopover ? accountPopover.querySelector('#acctSecViews') : null;

    const ACCT_STORAGE_THEME = 'fbmenu-theme';
    const ACCT_STORAGE_COMPACT = 'fbmenu-compact';

    function acctSetView(viewName) {
      if (!acctSecViews || !acctSecTitle) return;
      const views = Array.from(acctSecViews.querySelectorAll('.sec-view'));
      for (const view of views) view.classList.toggle('active', view.dataset.view === viewName);

      try {
        acctSecViews.scrollTop = 0;
      } catch (err) {}

      if (viewName === 'settings') acctSecTitle.textContent = 'Cài đặt và quyền riêng tư';
      else if (viewName === 'help') acctSecTitle.textContent = 'Trợ giúp và hỗ trợ';
      else acctSecTitle.textContent = 'Màn hình và trợ năng';
    }

    function acctOpenSecondary(viewName) {
      if (!acctMenuSlider) return;
      acctSetView(viewName);
      acctMenuSlider.classList.add('slide-active');
      requestAnimationFrame(() => {
        try {
          const secondPanel = acctMenuSlider.querySelector('.menu-track > .menu-panel:nth-child(2)');
          if (secondPanel) secondPanel.scrollTop = 0;
          if (acctSecViews) acctSecViews.scrollTop = 0;
        } catch (err) {}

        acctSyncPopoverHeight();
        positionAccountPopover();
      });
    }

    function acctCloseSecondary() {
      if (acctMenuSlider) acctMenuSlider.classList.remove('slide-active');
      acctSyncPopoverHeight();
      positionAccountPopover();
    }

    function acctApplyTheme(mode) {
      const root = document.documentElement;
      const body = document.body;
      if (!root && !body) return;

      // off = light, on = dark, auto = follow OS
      const theme = (mode === 'on') ? 'dark' : (mode === 'auto' ? 'auto' : 'light');
      try { if (root) root.setAttribute('data-theme', theme); } catch (_e) {}
      try { if (body) body.setAttribute('data-theme', theme); } catch (_e) {}
    }

    function acctApplyCompact(mode) {
      if (!acctMenuRoot) return;
      if (mode === 'on') {
        acctMenuRoot.style.setProperty('--item-height', '40px');
        acctMenuRoot.style.setProperty('--panel-padding', '6px');
        acctMenuRoot.style.setProperty('--font-item-label', '14px');
        return;
      }
      acctMenuRoot.style.removeProperty('--item-height');
      acctMenuRoot.style.removeProperty('--panel-padding');
      acctMenuRoot.style.removeProperty('--font-item-label');
    }

    function acctSelectRadio(rowEl) {
      if (!rowEl || !rowEl.dataset || !accountPopover) return;
      const group = rowEl.dataset.group;
      const value = rowEl.dataset.value;
      if (!group || !value) return;

      const rows = Array.from(accountPopover.querySelectorAll(`.radio-row[data-group="${group}"]`));
      for (const r of rows) r.classList.toggle('selected', r === rowEl);

      if (group === 'darkmode') {
        try {
          localStorage.setItem(ACCT_STORAGE_THEME, value);
        } catch (err) {}
        acctApplyTheme(value);
      }
      if (group === 'compact') {
        try {
          localStorage.setItem(ACCT_STORAGE_COMPACT, value);
        } catch (err) {}
        acctApplyCompact(value);
      }
    }

    function initAccountMenu() {
      if (!accountPopover) return;

      const ownProfileHref = (() => {
        try {
          const uid = (typeof CURRENT_USER_ID !== 'undefined') ? (Number(CURRENT_USER_ID) || 0) : 0;
          const base = './profile.php';
          return uid > 0 ? `${base}?id=${encodeURIComponent(String(uid))}` : base;
        } catch (_e) {
          return './profile.php';
        }
      })();

      if (acctBtnSettingsPrivacy) acctBtnSettingsPrivacy.addEventListener('click', (e) => {
        e.preventDefault();
        acctOpenSecondary('settings');
      });
      if (acctBtnHelpSupport) acctBtnHelpSupport.addEventListener('click', (e) => {
        e.preventDefault();
        acctOpenSecondary('help');
      });
      if (acctBtnDisplayAccessibility) acctBtnDisplayAccessibility.addEventListener('click', (e) => {
        e.preventDefault();
        acctOpenSecondary('accessibility');
      });
      if (acctBtnBack) acctBtnBack.addEventListener('click', () => acctCloseSecondary());

      accountPopover.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) return;

        const profileHit = target.closest('.profile-info-inner, .see-all-btn');
        if (profileHit) {
          e.preventDefault();
          e.stopPropagation();
          window.location.href = ownProfileHref;
          return;
        }

        const row = target.closest('.radio-row');
        if (!row) return;
        acctSelectRadio(row);
      });

      accountPopover.addEventListener('keydown', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) return;
        if (e.key !== 'Enter' && e.key !== ' ' && e.code !== 'Space') return;

        const profileHit = target.closest('.profile-info-inner, .see-all-btn');
        if (!profileHit) return;

        e.preventDefault();
        e.stopPropagation();
        window.location.href = ownProfileHref;
      });

      let savedTheme = 'off';
      let savedCompact = 'off';
      try {
        savedTheme = localStorage.getItem(ACCT_STORAGE_THEME) || 'off';
        savedCompact = localStorage.getItem(ACCT_STORAGE_COMPACT) || 'off';
      } catch (err) {}

      acctApplyTheme(savedTheme);
      acctApplyCompact(savedCompact);

      const initThemeRow = accountPopover.querySelector(`.radio-row[data-group="darkmode"][data-value="${savedTheme}"]`);
      const initCompactRow = accountPopover.querySelector(`.radio-row[data-group="compact"][data-value="${savedCompact}"]`);
      if (initThemeRow) acctSelectRadio(initThemeRow);
      if (initCompactRow) acctSelectRadio(initCompactRow);

      acctSetView('accessibility');
    }

    function resetAccountMenu() {
      acctCloseSecondary();
      acctSetView('accessibility');
      acctSyncPopoverHeight();
    }

    initAccountMenu();

    const mpEmptyTitle = messengerPopover ? messengerPopover.querySelector('.mp-empty h4') : null;
    const mpEmptyDesc = messengerPopover ? messengerPopover.querySelector('.mp-empty p') : null;
    const mpTabsWrap = messengerPopover ? messengerPopover.querySelector('.mp-tabs') : null;
    const mpTabButtons = mpTabsWrap ? Array.from(mpTabsWrap.querySelectorAll('.mp-tab[data-tab]')) : [];

    let mpCurrentTab = 'all';
    let mpConvCache = [];
    let mpConvAbort = null;
    const mpBtnByUserId = new Map();
    let mpLastLoadError = '';

    function mpEscapeHtml(s) {
      return String(s || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
    }

    function mpSetEmptyState(title, desc) {
      if (mpEmptyTitle) mpEmptyTitle.textContent = String(title || '');
      if (mpEmptyDesc) mpEmptyDesc.textContent = String(desc || '');
    }

    function mpParseDateTime(v) {
      const s = String(v || '').trim();
      if (!s) return null;
      // MySQL DATETIME: YYYY-MM-DD HH:MM:SS
      const m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
      if (m) {
        const y = Number(m[1]);
        const mo = Number(m[2]) - 1;
        const d = Number(m[3]);
        const h = Number(m[4]);
        const mi = Number(m[5]);
        const se = Number(m[6] || 0);
        const dt = new Date(y, mo, d, h, mi, se);
        return Number.isNaN(dt.getTime()) ? null : dt;
      }
      const dt = new Date(s);
      return Number.isNaN(dt.getTime()) ? null : dt;
    }

    function mpFormatTime(v) {
      const dt = mpParseDateTime(v);
      if (!dt) return '';
      try {
        const nowMs = Date.now();
        const ms = nowMs - dt.getTime();
        if (!Number.isFinite(ms)) return '';

        const sec = Math.floor(ms / 1000);
        if (sec < 0) return '';
        if (sec < 60) return 'Vừa xong';

        const min = Math.floor(sec / 60);
        if (min < 60) return `${min} phút`;

        const hour = Math.floor(min / 60);
        if (hour < 24) return `${hour} giờ`;

        const day = Math.floor(hour / 24);
        if (day < 7) return `${day} ngày`;

        const week = Math.floor(day / 7);
        if (week < 5) return `${week} tuần`;

        const month = Math.floor(day / 30);
        if (month < 12) return `${month} tháng`;

        const year = Math.floor(day / 365);
        return `${Math.max(1, year)} năm`;
      } catch (_e) {
        return '';
      }
    }

    function mpFilterConversations(list) {
      const items = Array.isArray(list) ? list : [];
      if (mpCurrentTab === 'unread') return items.filter((c) => (Number(c && c.unread_count) || 0) > 0);
      if (mpCurrentTab === 'group') return [];
      return items;
    }

    function mpRebuildConvIndex() {
      mpBtnByUserId.clear();
      if (!mpConvList) return;
      const btns = Array.from(mpConvList.querySelectorAll('.mp-conv-item'));
      for (const b of btns) {
        if (!(b instanceof HTMLElement)) continue;
        const id = Number(b.getAttribute('data-user-id')) || 0;
        if (id > 0) mpBtnByUserId.set(id, b);
      }
    }

    function mpPreviewFromSocketPayload(data) {
      const raw = String((data && (data.message || data.content)) || '').trim();
      if (!raw) return '';
      if (raw[0] === '{') {
        try {
          const obj = JSON.parse(raw);
          if (obj && typeof obj === 'object' && obj.type) {
            if (obj.type === 'image') return 'Đã gửi 1 ảnh.';
            if (obj.type === 'audio') return 'Đã gửi 1 đoạn âm thanh.';
          }
        } catch (_e) {}
      }
      return raw.length > 80 ? `${raw.slice(0, 80)}…` : raw;
    }

    function mpTimeLabelFromSocketPayload(data) {
      const createdAt = data && data.created_at ? data.created_at : '';
      if (createdAt) return mpFormatTime(createdAt);
      const t = Number(data && data.time);
      if (Number.isFinite(t) && t > 0) {
        try {
          return mpFormatTime(new Date(t).toISOString());
        } catch (_e) {
          return '';
        }
      }
      return 'Vừa xong';
    }

    function mpEnsureRowForUser(otherUserId, preview, timeLabel, unreadDelta, fromContactsFallback) {
      if (!mpConvList || !mpEmpty) return;
      const uid = Number(otherUserId) || 0;
      if (uid <= 0) return;

      let btn = mpBtnByUserId.get(uid) || null;

      if (!btn) {
        let name = '';
        let avatar = '';
        if (fromContactsFallback && typeof fromContactsFallback === 'function') {
          try {
            const u = fromContactsFallback(uid);
            if (u) {
              name = String(u.name || '');
              avatar = String(u.avatar || '');
            }
          } catch (_e) {}
        }

        const safeName = mpEscapeHtml(name || `User ${uid}`);
        const safeAvatar = mpEscapeHtml(avatar || '../assets/images/default-avatar.png');
        btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mp-conv-item';
        btn.setAttribute('role', 'listitem');
        btn.setAttribute('data-user-id', String(uid));
        btn.setAttribute('data-name', safeName);
        btn.setAttribute('data-avatar', safeAvatar);
        btn.setAttribute('data-unread', '0');

        btn.innerHTML = `
          <span class="mp-conv-avatar" aria-hidden="true">
            ${safeAvatar ? `<img src="${safeAvatar}" alt="" />` : `<span aria-hidden="true"></span>`}
          </span>
          <span class="mp-conv-text">
            <div class="mp-conv-name">${safeName}</div>
            <div class="mp-conv-preview"></div>
          </span>
          <span class="mp-conv-badge" hidden aria-label="Chưa đọc">0</span>
        `;

        mpConvList.insertBefore(btn, mpConvList.firstChild);
        mpBtnByUserId.set(uid, btn);
      }

      // Update preview/time
      const previewEl = btn.querySelector('.mp-conv-preview');
      if (previewEl) {
        const p = String(preview || '').trim();
        const t = String(timeLabel || '').trim();
        previewEl.textContent = t ? `${p}${p ? ' · ' : ''}${t}` : p;
      }

      // Update unread
      const currentUnread = Number(btn.getAttribute('data-unread')) || 0;
      const nextUnread = Math.max(0, currentUnread + (Number(unreadDelta) || 0));
      btn.setAttribute('data-unread', String(nextUnread));
      const badge = btn.querySelector('.mp-conv-badge');
      if (badge) {
        badge.textContent = nextUnread > 99 ? '99+' : String(nextUnread);
        badge.toggleAttribute('hidden', !(nextUnread > 0));
      }

      // Move to top
      try {
        if (mpConvList.firstElementChild !== btn) {
          mpConvList.insertBefore(btn, mpConvList.firstElementChild);
        }
      } catch (_e) {}

      // Ensure list visible
      mpEmpty.style.display = 'none';
      mpConvList.classList.add('has-items');
    }

    function renderMpConversations(list) {
      if (!mpConvList || !mpEmpty) return;
      const items = mpFilterConversations(list);

      if (!items.length) {
        mpConvList.classList.remove('has-items');
        mpConvList.innerHTML = '';
        mpEmpty.style.display = '';

        if (mpLastLoadError) {
          mpSetEmptyState('Không thể tải đoạn chat', mpLastLoadError);
        }
        return;
      }

      mpEmpty.style.display = 'none';
      mpConvList.classList.add('has-items');
      mpConvList.innerHTML = items
        .map((c) => {
          const id = Number(c && c.user_id) || 0;
          const name = mpEscapeHtml(c && c.name ? c.name : '');
          const preview = mpEscapeHtml(c && c.last_preview ? c.last_preview : '');
          const time = mpEscapeHtml(mpFormatTime(c && c.last_time ? c.last_time : ''));
          const avatar = mpEscapeHtml(c && c.avatar ? c.avatar : '');
          const unread = Number(c && c.unread_count) || 0;
          return `
            <button class="mp-conv-item" type="button" role="listitem" data-user-id="${id}" data-name="${name}" data-avatar="${avatar}" data-unread="${unread}">
              <span class="mp-conv-avatar" aria-hidden="true">
                ${avatar ? `<img src="${avatar}" alt="" />` : `<span aria-hidden="true"></span>`}
              </span>
              <span class="mp-conv-text">
                <div class="mp-conv-name">${name}</div>
                <div class="mp-conv-preview">${preview}${time ? ` · ${time}` : ''}</div>
              </span>
              <span class="mp-conv-badge" ${unread > 0 ? '' : 'hidden'} aria-label="Chưa đọc">${unread > 99 ? '99+' : unread}</span>
            </button>
          `;
        })
        .join('');

      mpRebuildConvIndex();
    }

    function mpFindContactBasic(uid) {
      let item = null;
      try {
        item = contactsRoot && contactsRoot.querySelector
          ? contactsRoot.querySelector(`.fb-contact-item[data-user-id="${uid}"]`)
          : null;
      } catch (_e) { item = null; }

      return item ? {
        name: String(item.dataset && item.dataset.userName || ''),
        avatar: String(item.dataset && item.dataset.userAvatar || ''),
      } : null;
    }

    function mpUpsertCacheFromRealtime(uid, preview, lastTimeValue, unreadDelta) {
      const id = Number(uid) || 0;
      if (id <= 0) return;

      const contact = mpFindContactBasic(id);
      const nowIso = (() => {
        try {
          return new Date().toISOString();
        } catch (_e) {
          return '';
        }
      })();

      const nextLastTime = String(lastTimeValue || nowIso);
      const nextPreview = String(preview || '').trim();

      let existing = null;
      for (const c of mpConvCache) {
        if (Number(c && c.user_id) === id) { existing = c; break; }
      }

      if (!existing) {
        mpConvCache.unshift({
          user_id: id,
          name: contact ? contact.name : `User ${id}`,
          avatar: contact ? contact.avatar : '../assets/images/default-avatar.png',
          last_preview: nextPreview,
          last_time: nextLastTime,
          unread_count: Math.max(0, Number(unreadDelta) || 0),
        });
      } else {
        existing.name = existing.name || (contact ? contact.name : existing.name);
        existing.avatar = existing.avatar || (contact ? contact.avatar : existing.avatar);
        existing.last_preview = nextPreview || existing.last_preview;
        existing.last_time = nextLastTime || existing.last_time;
        existing.unread_count = Math.max(0, (Number(existing.unread_count) || 0) + (Number(unreadDelta) || 0));

        // move to top
        mpConvCache = [existing].concat(mpConvCache.filter((x) => x !== existing));
      }
    }

    function loadMpConversations() {
      if (!mpConvList || !mpEmpty) return;
      if (mpConvAbort) {
        try { mpConvAbort.abort(); } catch (_e) {}
      }
      mpConvAbort = new AbortController();

      mpLastLoadError = '';
      mpSetEmptyState(MP_EMPTY_TEXT[mpCurrentTab] ? MP_EMPTY_TEXT[mpCurrentTab].title : MP_EMPTY_TEXT.all.title,
        MP_EMPTY_TEXT[mpCurrentTab] ? MP_EMPTY_TEXT[mpCurrentTab].desc : MP_EMPTY_TEXT.all.desc);

      const url = new URL('../actions/get_conversations.php', window.location.href);
      fetch(url.toString(), {
        signal: mpConvAbort.signal,
        credentials: 'same-origin',
      })
        .then(async (r) => {
          if (!r.ok) {
            let msg = '';
            try { msg = await r.text(); } catch (_e) { msg = ''; }
            throw new Error(`HTTP ${r.status}${msg ? `: ${msg}` : ''}`);
          }
          return r.json();
        })
        .then((data) => {
          mpConvCache = (data && data.conversations) || [];
          renderMpConversations(mpConvCache);
        })
        .catch((err) => {
          if (err && err.name === 'AbortError') return;
          mpConvCache = [];
          mpLastLoadError = (err && err.message) ? String(err.message) : 'Vui lòng thử lại.';
          renderMpConversations([]);
        });
    }

    const mpMoreBtn = document.getElementById('mpMoreBtn');
    const mpMoreMenu = document.getElementById('mpMoreMenu');

    const mpOptionsBtn = document.getElementById('mpOptionsBtn');
    const mpOptionsMenu = document.getElementById('mpOptionsMenu');
    const mpActiveStatusLabel = document.getElementById('mpActiveStatusLabel');
    const mpActiveStatusRow = document.getElementById('mpActiveStatusRow');

    const mpComposeBtn = document.getElementById('mpComposeBtn');
    const mpCompose = document.getElementById('mpCompose');
    const mpComposeClose = document.getElementById('mpComposeClose');
    const mpComposeTo = document.getElementById('mpComposeTo');

    function isComposeOpen() {
      return !!(mpCompose && mpCompose.classList.contains('open'));
    }

    function openCompose() {
      if (!mpCompose) return;
      if (messengerPopover && messengerPopover.classList.contains('open')) closeMessenger();
      closeMoreMenu();
      closeOptionsMenu();
      mpCompose.classList.add('open');
      mpCompose.setAttribute('aria-hidden', 'false');
      requestAnimationFrame(() => {
        try {
          mpComposeTo && mpComposeTo.focus();
        } catch (err) {}
      });
    }

    function closeCompose() {
      if (!mpCompose) return;
      mpCompose.classList.remove('open');
      mpCompose.setAttribute('aria-hidden', 'true');
    }

    function ensureOptionsMenuMounted() {
      if (!mpOptionsMenu) return;

      // Render outside the Messenger popover (like Facebook)
      if (mpOptionsMenu.parentElement !== document.body) {
        document.body.appendChild(mpOptionsMenu);
      }

      // Keep caret visible while content scrolls
      const first = mpOptionsMenu.firstElementChild;
      if (!first || !first.classList.contains('mp-options-scroll')) {
        const wrap = document.createElement('div');
        wrap.className = 'mp-options-scroll';
        while (mpOptionsMenu.firstChild) wrap.appendChild(mpOptionsMenu.firstChild);
        mpOptionsMenu.appendChild(wrap);
      }
    }

    ensureOptionsMenuMounted();

    const MP_PREF_KEYS = {
      call_sounds: 'mp_pref_call_sounds',
      message_sounds: 'mp_pref_message_sounds',
      new_message_pop: 'mp_pref_new_message_pop',
      active_status: 'mp_pref_active_status'
    };

    function readBoolPref(key, defaultValue) {
      try {
        const raw = window.localStorage.getItem(key);
        if (raw === null) return defaultValue;
        return raw === '1';
      } catch (err) {
        return defaultValue;
      }
    }

    function writeBoolPref(key, value) {
      try {
        window.localStorage.setItem(key, value ? '1' : '0');
      } catch (err) {}
    }

    function setSwitchRowState(row, isOn) {
      if (!row) return;
      row.setAttribute('aria-checked', isOn ? 'true' : 'false');
    }

    function syncOptionsUIFromPrefs() {
      if (!mpOptionsMenu) return;
      const rows = Array.from(mpOptionsMenu.querySelectorAll('.mp-opts-row[role="switch"][data-pref]'));
      rows.forEach((row) => {
        const pref = row.getAttribute('data-pref');
        const key = MP_PREF_KEYS[pref];
        if (!key) return;
        const val = readBoolPref(key, true);
        setSwitchRowState(row, val);
      });

      const activeOn = readBoolPref(MP_PREF_KEYS.active_status, true);
      if (mpActiveStatusLabel) {
        mpActiveStatusLabel.textContent = `Trạng thái hoạt động: ${activeOn ? 'ĐANG BẬT' : 'ĐANG TẮT'}`;
      }
    }

    function isOptionsMenuOpen() {
      return !!(mpOptionsMenu && mpOptionsMenu.classList.contains('open'));
    }

    function positionOptionsMenu() {
      if (!mpOptionsBtn || !mpOptionsMenu) return;
      ensureOptionsMenuMounted();

      const btnRect = mpOptionsBtn.getBoundingClientRect();
      const menuStyles = window.getComputedStyle(mpOptionsMenu);
      const shiftX = Number.parseFloat(menuStyles.getPropertyValue('--mp-options-shift-x')) || 0;

      const menuW = mpOptionsMenu.offsetWidth || 340;
      const menuH = mpOptionsMenu.offsetHeight || 460;

      // FB-like: menu opens below the button, aligned to the left (right edge near the button)
      let top = btnRect.bottom + 8;
      let left = (btnRect.right - menuW) + shiftX;

      const minLeft = 8;
      const maxLeft = Math.max(minLeft, window.innerWidth - menuW - 8);
      if (left < minLeft) left = minLeft;
      if (left > maxLeft) left = maxLeft;

      const minTop = 8;
      const maxTop = Math.max(minTop, window.innerHeight - menuH - 8);
      if (top < minTop) top = minTop;
      if (top > maxTop) top = maxTop;

      mpOptionsMenu.style.top = `${top}px`;
      mpOptionsMenu.style.left = `${left}px`;

      // Arrow/caret should point to the button center
      const btnCenterX = btnRect.left + (btnRect.width / 2);
      let caretLeft = btnCenterX - left;
      const caretPad = 16;
      if (caretLeft < caretPad) caretLeft = caretPad;
      if (caretLeft > (menuW - caretPad)) caretLeft = menuW - caretPad;
      mpOptionsMenu.style.setProperty('--mp-options-caret-left', `${caretLeft}px`);
    }

    function openOptionsMenu() {
      if (!mpOptionsBtn || !mpOptionsMenu) return;
      ensureOptionsMenuMounted();
      mpOptionsMenu.classList.add('open');
      mpOptionsMenu.setAttribute('aria-hidden', 'false');
      mpOptionsBtn.setAttribute('aria-expanded', 'true');
      mpOptionsBtn.classList.add('is-open');
      syncOptionsUIFromPrefs();
      requestAnimationFrame(() => positionOptionsMenu());
    }

    function closeOptionsMenu() {
      if (!mpOptionsBtn || !mpOptionsMenu) return;
      mpOptionsMenu.classList.remove('open');
      mpOptionsMenu.setAttribute('aria-hidden', 'true');
      mpOptionsBtn.setAttribute('aria-expanded', 'false');
      mpOptionsBtn.classList.remove('is-open');
    }

    function toggleOptionsMenu() {
      if (isOptionsMenuOpen()) closeOptionsMenu();
      else openOptionsMenu();
    }

    const MP_EMPTY_TEXT = {
      all: {
        title: 'Không có đoạn chat nào',
        desc: 'Đoạn chat mới sẽ hiển thị ở đây.'
      },
      unread: {
        title: 'Không có đoạn chat nào chưa đọc',
        desc: 'Đoạn chat chưa đọc sẽ hiển thị ở đây.'
      },
      group: {
        title: 'Không có nhóm chat nào',
        desc: 'Nhóm chat mới sẽ hiển thị ở đây.'
      }
    };

    function setMessengerTab(tabKey) {
      if (!mpTabButtons.length) return;
      const normalized = MP_EMPTY_TEXT[tabKey] ? tabKey : 'all';
      mpCurrentTab = normalized;

      mpTabButtons.forEach((btn) => {
        const isActive = btn.getAttribute('data-tab') === normalized;
        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      if (mpEmptyTitle && mpEmptyDesc) {
        mpEmptyTitle.textContent = MP_EMPTY_TEXT[normalized].title;
        mpEmptyDesc.textContent = MP_EMPTY_TEXT[normalized].desc;
      }

      // Re-render conversation list based on current filter.
      renderMpConversations(mpConvCache);
    }

    function isMoreMenuOpen() {
      return !!(mpMoreMenu && mpMoreMenu.classList.contains('open'));
    }

    function positionMoreMenu() {
      if (!mpMoreBtn || !mpMoreMenu || !mpTabsWrap) return;
      const btnRect = mpMoreBtn.getBoundingClientRect();
      const tabsRect = mpTabsWrap.getBoundingClientRect();
      const menuW = mpMoreMenu.offsetWidth || 160;

      // NOTE: mpMoreMenu is absolutely positioned inside `.mp-tabs` (which is `position: relative`)
      const desiredTop = (btnRect.bottom - tabsRect.top) + 8;

      // Center the menu under the “…” button
      const btnCenterX = (btnRect.left - tabsRect.left) + (btnRect.width / 2);
      let left = btnCenterX - (menuW / 2);

      const minLeft = 8;
      const maxLeft = Math.max(minLeft, tabsRect.width - menuW - 8);
      if (left < minLeft) left = minLeft;
      if (left > maxLeft) left = maxLeft;

      mpMoreMenu.style.top = `${desiredTop}px`;
      mpMoreMenu.style.left = `${left}px`;
    }

    function openMoreMenu() {
      if (!mpMoreBtn || !mpMoreMenu) return;
      mpMoreMenu.classList.add('open');
      mpMoreMenu.setAttribute('aria-hidden', 'false');
      mpMoreBtn.setAttribute('aria-expanded', 'true');
      mpMoreBtn.classList.add('is-open');
      positionMoreMenu();
    }

    function closeMoreMenu() {
      if (!mpMoreBtn || !mpMoreMenu) return;
      mpMoreMenu.classList.remove('open');
      mpMoreMenu.setAttribute('aria-hidden', 'true');
      mpMoreBtn.setAttribute('aria-expanded', 'false');
      mpMoreBtn.classList.remove('is-open');
    }

    function toggleMoreMenu() {
      if (isMoreMenuOpen()) closeMoreMenu();
      else openMoreMenu();
    }

    function positionMessengerPopover() {
      if (!messengerBtn || !messengerPopover) return;
      const rect = messengerBtn.getBoundingClientRect();
      const headerRect = fixedHeader ? fixedHeader.getBoundingClientRect() : null;

      const popStyles = window.getComputedStyle(messengerPopover);
      const offsetY = Number.parseFloat(popStyles.getPropertyValue('--mp-offset-y')) || 0;
      const rightPad = Number.parseFloat(popStyles.getPropertyValue('--mp-right-pad')) || 0;

      const top = (headerRect ? headerRect.bottom : rect.bottom) + offsetY;

      const popW = messengerPopover.offsetWidth || 360;
      const targetRight = window.innerWidth - rightPad;
      let left = targetRight - popW;

      // Clamp so it stays on-screen (and still looks like FB spacing)
      const minLeft = 8;
      const maxLeft = Math.max(minLeft, window.innerWidth - popW - 8);
      if (left < minLeft) left = minLeft;
      if (left > maxLeft) left = maxLeft;

      messengerPopover.style.top = `${top}px`;
      messengerPopover.style.left = `${left}px`;

      // Prevent top from going off-screen (still allows moving up)
      const currentTop = Number.parseFloat(messengerPopover.style.top) || 0;
      if (currentTop < 0) messengerPopover.style.top = `0px`;
    }

    function openMessenger() {
      if (!messengerBtn || !messengerPopover) return;
      messengerBtn.classList.add('is-active');
      messengerBtn.setAttribute('aria-expanded', 'true');
      messengerPopover.classList.add('open');
      messengerPopover.setAttribute('aria-hidden', 'false');
      positionMessengerPopover();

      // default state
      setMessengerTab('all');
      closeMoreMenu();
      closeOptionsMenu();

      // Load chats whenever opened.
      try {
        loadMpConversations();
      } catch (_e) {}
    }

    function closeMessenger() {
      if (!messengerBtn || !messengerPopover) return;
      messengerBtn.classList.remove('is-active');
      messengerBtn.setAttribute('aria-expanded', 'false');
      messengerPopover.classList.remove('open');
      messengerPopover.setAttribute('aria-hidden', 'true');

      closeMoreMenu();
      closeOptionsMenu();
    }

    function toggleMessenger() {
      if (!messengerBtn || !messengerPopover) return;
      const isOpen = messengerPopover.classList.contains('open');
      if (isOpen) closeMessenger();
      else openMessenger();
    }

    if (messengerBtn) {
      messengerBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleMessenger();
      });
    }

    if (accountBtn) {
      accountBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleAccountPopover();
      });
    }

    // Tabs switching
    mpTabButtons.forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        setMessengerTab(btn.getAttribute('data-tab'));
        closeMoreMenu();
        closeOptionsMenu();
      });
    });

    // Click a conversation -> open chat popup
    if (mpConvList) {
      mpConvList.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) return;
        const btn = target.closest('.mp-conv-item');
        if (!btn || !mpConvList.contains(btn)) return;

        const userId = Number(btn.getAttribute('data-user-id')) || 0;
        const name = String(btn.getAttribute('data-name') || '');
        const avatar = String(btn.getAttribute('data-avatar') || '');
        if (!userId) return;

        try {
          if (typeof window.fbOpenChat === 'function') {
            window.fbOpenChat({ id: userId, name, avatar, online: false });
          }
        } catch (_e) {}

        // Close popover after choosing a conversation
        try { closeMessenger(); } catch (_e) {}
      });
    }

    // “…” menu
    if (mpMoreBtn) {
      mpMoreBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        closeOptionsMenu();
        toggleMoreMenu();
      });
    }

    // Options button
    if (mpOptionsBtn) {
      mpOptionsBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        closeMoreMenu();
        toggleOptionsMenu();
      });
    }

    // Compose button
    if (mpComposeBtn) {
      mpComposeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        openCompose();
      });
    }

    if (mpComposeClose) {
      mpComposeClose.addEventListener('click', (e) => {
        e.stopPropagation();
        closeCompose();
      });
    }

    // Options menu interactions
    if (mpOptionsMenu) {
      mpOptionsMenu.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) return;

        const switchRow = target.closest('.mp-opts-row[role="switch"][data-pref]');
        if (switchRow) {
          const pref = switchRow.getAttribute('data-pref');
          const key = MP_PREF_KEYS[pref];
          if (!key) return;
          const current = switchRow.getAttribute('aria-checked') === 'true';
          const next = !current;
          writeBoolPref(key, next);
          setSwitchRowState(switchRow, next);
          return;
        }
      });
    }

    if (mpActiveStatusRow) {
      mpActiveStatusRow.addEventListener('click', (e) => {
        e.stopPropagation();
        const current = readBoolPref(MP_PREF_KEYS.active_status, true);
        const next = !current;
        writeBoolPref(MP_PREF_KEYS.active_status, next);
        syncOptionsUIFromPrefs();
      });
    }

    if (mpMoreMenu) {
      mpMoreMenu.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) return;
        const item = target.closest('[data-more]');
        if (!item) return;
        e.stopPropagation();
        // For now the menu only mirrors the screenshot (shows “Cộng đồng”)
        closeMoreMenu();
      });
    }

    document.addEventListener('click', (e) => {
      if (!messengerPopover || !messengerBtn) return;
      if (!messengerPopover.classList.contains('open')) return;

      if (isOptionsMenuOpen() && mpOptionsMenu && mpOptionsBtn) {
        if (!mpOptionsMenu.contains(e.target) && !mpOptionsBtn.contains(e.target)) closeOptionsMenu();
      }

      if (isMoreMenuOpen() && mpMoreMenu && mpMoreBtn) {
        if (!mpMoreMenu.contains(e.target) && !mpMoreBtn.contains(e.target)) closeMoreMenu();
      }

      if (messengerPopover.contains(e.target) || messengerBtn.contains(e.target)) return;
      closeMessenger();
    }, true);

    document.addEventListener('click', (e) => {
      if (!accountPopover || !accountBtn) return;
      if (!accountPopover.classList.contains('open')) return;
      if (accountPopover.contains(e.target) || accountBtn.contains(e.target)) return;
      closeAccountPopover();
    }, true);

    window.addEventListener('resize', () => {
      if (messengerPopover && messengerPopover.classList.contains('open')) {
        positionMessengerPopover();
        if (isMoreMenuOpen()) positionMoreMenu();
        if (isOptionsMenuOpen()) positionOptionsMenu();
      }

      if (accountPopover && accountPopover.classList.contains('open')) {
        acctSyncPopoverHeight();
        positionAccountPopover();
      }
    });

    window.addEventListener('scroll', () => {
      if (isOptionsMenuOpen()) positionOptionsMenu();
    }, true);

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' || e.key === 'Esc') {
        if (isComposeOpen()) {
          closeCompose();
          return;
        }
        if (isAccountOpen()) {
          if (acctMenuSlider && acctMenuSlider.classList.contains('slide-active')) {
            acctCloseSecondary();
            return;
          }
          closeAccountPopover();
          return;
        }
        if (isOptionsMenuOpen()) {
          closeOptionsMenu();
          return;
        }
        if (isMoreMenuOpen()) {
          closeMoreMenu();
          return;
        }
        if (messengerPopover && messengerPopover.classList.contains('open')) closeMessenger();
      }
    });

    // Facebook-style loading overlay: show ONLY after 3 consecutive Home clicks
    document.addEventListener('DOMContentLoaded', () => {
      const homeTab = document.querySelector('.cnav-item[data-key="home"]');
      const overlay = document.getElementById('fbLoadingOverlay');
      const spinner = overlay ? overlay.querySelector('.fb-loading-center') : null;

      if (!homeTab || !overlay || !spinner) return;

      let count = 0;
      let lastClick = 0;
      let loading = false;

      homeTab.addEventListener('click', (e) => {
        // Always prevent the anchor's default navigation so clicks only trigger UI effects.
        try { e.preventDefault(); } catch (err) {}
        if (loading) return;

        const now = Date.now();
        if (now - lastClick > 2000) count = 0;
        lastClick = now;

        count++;

        if (count === 3) {
          // Show spinner briefly on 3 consecutive clicks (no reload).
          overlay.classList.add('show');
          setTimeout(() => {
            overlay.classList.remove('show');
          }, 1000);
          return;
        }

        if (count === 5) {
          // Prevent default navigation and show a slightly longer spinner (no reload).
          try { e.preventDefault(); } catch (err) {}
          count = 0;
          overlay.classList.add('show');
          setTimeout(() => {
            overlay.classList.remove('show');
          }, 1200);
          return;
        }
      });
    });

    const ip = document.getElementById("searchInput");
    const resultBox = document.getElementById("searchResult");

    // Observe changes inside the result box and recompute height automatically.
    (function attachResultObservers() {
      if (!resultBox) return;
      // debounce helper
      let t;
      const debouncedAdjust = () => {
        clearTimeout(t);
        t = setTimeout(adjustSearchResultHeight, 60);
      };

      // MutationObserver for DOM changes
      try {
        const mo = new MutationObserver(mutations => debouncedAdjust());
        mo.observe(resultBox, {
          childList: true,
          subtree: true,
          attributes: true
        });
      } catch (e) {
        /* ignore */
      }

      // Recalculate on image load inside results (avatars may change height)
      resultBox.addEventListener('load', function(e) {
        if (e.target && e.target.tagName === 'IMG') debouncedAdjust();
      }, true);

      // Also adjust on window resize
      window.addEventListener('resize', debouncedAdjust);
    })();

    ip.addEventListener("input", function() {
      const keyword = this.value.trim();

      // show recent searches when input is empty or very short
      if (keyword.length < 2) {
        renderRecentSearches();
        return;
      }

      const q = keyword; // capture for later

      fetch(`../api/search_user.php?username=${encodeURIComponent(q)}&ajax=1`)
        .then(res => {
          if (!res.ok) throw new Error('network');
          return res.json();
        })
        .then(users => {
          try {
            console.log('search_user response', users);
            users.forEach(u => console.log('user avatar:', u.name, u.avatar));
          } catch (e) {}
          renderUsers(users);
          // Do not auto-save the typed query here — only save when user explicitly
          // clicks a result so we can preserve avatar images. saveRecentSearch(q) removed.
        })
        .catch(err => {
          console.error('Search error', err);
          resultBox.innerHTML = '<div>Lỗi tìm kiếm</div>';
        });
    });

    // click delegation for recent items and live results
    resultBox.addEventListener('click', function(e) {
      // remove recent item when clicking the x button
      const rem = e.target.closest('.recent-remove');
      if (rem) {
        const key = rem.dataset.key;
        if (!key) return;
        try {
          let arr = JSON.parse(sessionStorage.getItem('recentSearches') || '[]') || [];
          // arr may contain objects or strings
          arr = arr.filter(x => {
            try {
              if (x && x.name) return x.name !== key;
            } catch (_) {}
            return String(x) !== key;
          });
          sessionStorage.setItem('recentSearches', JSON.stringify(arr));
        } catch (err) {
          console.warn('remove recent', err)
        }
        renderRecentSearches();
        return;
      }

      // if clicked a recent-item (from session) -> populate input
      const recent = e.target.closest('.recent-item');
      if (recent) {
        const val = recent.dataset.key || '';
        const avatar = recent.dataset.avatar || '';
        if (!val) return;
        ip.value = val;
        // trigger input event to perform search
        ip.dispatchEvent(new Event('input', {
          bubbles: true
        }));
        ip.focus();
        return;
      }

      // if clicked a live result row (.user-item) that is NOT a .recent-item,
      // save its name + avatar to recent searches and update the recent list
      const userRow = e.target.closest('.user-item');
      if (userRow) {
        if (userRow.classList.contains('recent-item')) return;
        try {
          const imgEl = userRow.querySelector('img');
          // robust name extraction: prefer data-name, then span text, then row text
          let name = '';
          try {
            if (userRow.dataset && userRow.dataset.name) name = String(userRow.dataset.name).trim();
          } catch (e) {}
          if (!name) {
            try {
              const nameEl = userRow.querySelector('.user-left span, span');
              if (nameEl) name = (nameEl.textContent || '').trim();
            } catch (e) {}
          }
          if (!name) {
            try {
              name = (userRow.textContent || '').replace(/\s+/g, ' ').trim();
            } catch (e) {}
          }

          // avatar extraction
          let avatar = '';
          try {
            avatar = (userRow.getAttribute && userRow.getAttribute('data-avatar')) || (userRow.dataset && userRow.dataset.avatar) || '';
          } catch (e) {}
          if (!avatar && imgEl) try {
            avatar = imgEl.getAttribute('src') || '';
          } catch (e) {}
          avatar = (avatar || '').trim();

          try {
            console.log('click:userRow values ->', {
              name,
              avatar
            });
          } catch (e) {}

          if (name) {
            try {
              console.log('calling upsertRecentSearch (resultBox handler)', {
                name,
                avatar
              });
            } catch (e) {}
            upsertRecentSearch({
              name: name,
              avatar: avatar
            });
            try {
              console.log('after save session (post-upsert):', sessionStorage.getItem('recentSearches'));
            } catch (e) {}
            try {
              renderRecentSearches();
            } catch (e) {}
            try {
              ip.value = name;
              ip.dispatchEvent(new Event('input', {
                bubbles: true
              }));
              ip.focus();
            } catch (e) {}
          } else {
            try {
              console.warn('click ignored: could not determine name for userRow', userRow);
            } catch (e) {}
          }
        } catch (err) {
          console.warn('handle user-item click', err);
        }
        return;
      }
    });

    // store recent searches in sessionStorage (per-browser session)
    // accepts either a string (name) or an object { name, avatar }
    function saveRecentSearch(q) {
      try {
        const key = 'recentSearches';
        let arr = JSON.parse(sessionStorage.getItem(key) || '[]') || [];
        const item = (typeof q === 'string') ? {
          name: q,
          avatar: ''
        } : (q && q.name ? {
          name: String(q.name),
          avatar: String(q.avatar || '')
        } : null);
        if (!item) return;
        // normalize avatar
        try {
          item.avatar = (item.avatar || '').trim();
          if (item.avatar === 'null' || item.avatar === 'undefined') item.avatar = '';
        } catch (e) {}
        // If the incoming item has no avatar but an existing recent entry for the
        // same name has an avatar, preserve that avatar (avoid overwriting).
        try {
          const existing = arr.find(x => x && x.name && String(x.name).toLowerCase() === String(item.name).toLowerCase());
          if (existing && existing.avatar && !item.avatar) {
            item.avatar = existing.avatar;
          }
        } catch (e) {}
        // remove duplicates by case-insensitive name
        arr = arr.filter(x => {
          try {
            if (x && x.name) return x.name.toLowerCase() !== item.name.toLowerCase();
          } catch (_) {}
          return String(x).toLowerCase() !== item.name.toLowerCase();
        });
        arr.unshift(item);
        if (arr.length > 10) arr.length = 10;
        sessionStorage.setItem(key, JSON.stringify(arr));
        try {
          console.log('saveRecentSearch saved:', JSON.parse(sessionStorage.getItem(key) || '[]'));
        } catch (e) {}
      } catch (err) {
        console.warn('saveRecentSearch', err)
      }
    }

    // Upsert helper: ensure we merge avatar values correctly and always store
    function upsertRecentSearch(item) {
      try {
        try {
          console.log('upsertRecentSearch called (entry)', item);
        } catch (e) {}
        try {
          sessionStorage.setItem('recentLastCalled', JSON.stringify({
            ts: Date.now(),
            name: item && item.name ? String(item.name) : '',
            avatar: item && item.avatar ? String(item.avatar) : ''
          }));
        } catch (e) {
          console.warn('recentLastCalled write failed', e);
        }
        if (!item || !item.name) return;
        const key = 'recentSearches';
        let arr = JSON.parse(sessionStorage.getItem(key) || '[]') || [];
        try {
          console.log('upsertRecentSearch in:', item, 'existingRaw:', arr);
        } catch (e) {}
        // normalize existing entries to objects {name,avatar}
        try {
          arr = (Array.isArray(arr) ? arr : []).map(x => {
            if (!x) return {
              name: '',
              avatar: ''
            };
            if (typeof x === 'string') return {
              name: String(x),
              avatar: ''
            };
            if (x && x.name) return {
              name: String(x.name),
              avatar: String(x.avatar || '')
            };
            return {
              name: String(x || ''),
              avatar: ''
            };
          }).filter(x => x && x.name);
        } catch (e) {
          /* ignore normalization errors */
        }

        const nameLower = String(item.name).toLowerCase();
        // normalize avatar on incoming item
        try {
          item.avatar = (item.avatar || '').trim();
          if (item.avatar === 'null' || item.avatar === 'undefined') item.avatar = '';
        } catch (e) {}
        // find existing (case-insensitive)
        const idx = arr.findIndex(x => x && x.name && String(x.name).toLowerCase() === nameLower);
        if (idx >= 0) {
          // merge: prefer incoming avatar if present, otherwise keep existing
          const existing = arr[idx];
          const avatar = (item.avatar && item.avatar.length) ? item.avatar : (existing.avatar || '');
          const merged = {
            name: existing.name,
            avatar: avatar
          };
          // remove old and unshift merged
          arr.splice(idx, 1);
          arr.unshift(merged);
        } else {
          arr.unshift({
            name: String(item.name),
            avatar: item.avatar || ''
          });
        }
        if (arr.length > 10) arr.length = 10;
        // attempt to save and then verify the write succeeded (helps debug storage failures)
        try {
          sessionStorage.setItem(key, JSON.stringify(arr));
          // quick verification read (log a short preview)
          try {
            const raw2 = sessionStorage.getItem(key);
            console.log('upsertRecentSearch saved (verify):', raw2 ? raw2.substring(0, 200) : raw2);
          } catch (e2) {
            console.warn('upsert verify read failed', e2);
          }
        } catch (e) {
          console.error('upsertRecentSearch: sessionStorage.setItem failed', e);
          // fallback: try localStorage so user still sees recents in same browser
          try {
            localStorage.setItem(key, JSON.stringify(arr));
            console.warn('upsertRecentSearch: saved to localStorage fallback');
          } catch (e3) {
            console.warn('upsertRecentSearch: localStorage fallback failed', e3);
          }
        }
        try {
          if (window.updateRecentDebug) window.updateRecentDebug();
        } catch (e) {}
        try {
          if (window.updateRecentDebug) window.updateRecentDebug();
        } catch (e) {}
      } catch (e) {
        console.warn('upsertRecentSearch', e);
      }
    }

    // Adjust the results dropdown height to match rendered content.
    // Uses the actual content height (prefer .inner-results if present) and
    // switches to inner scrolling when the content exceeds `maxHeight`.
    function adjustSearchResultHeight() {
      try {
        const rb = resultBox;
        if (!rb) return;
        const inner = rb.querySelector('.inner-results') || rb;

        // Measure desired full content height
        const desired = (inner.scrollHeight && inner.scrollHeight > 0) ? inner.scrollHeight : (rb.scrollHeight || rb.offsetHeight || 0);
        const maxHeight = 360; // maximum visible card height before inner scrolling

        // If content fits, size the card to show everything; otherwise cap and enable inner scroll
        if (desired <= maxHeight) {
          rb.style.display = 'block';
          rb.style.boxSizing = 'border-box';
          rb.style.height = desired + 'px';
          rb.style.maxHeight = '';
          rb.style.overflow = 'hidden';
          if (inner !== rb) {
            inner.style.height = 'auto';
            inner.style.overflow = 'hidden';
          }
        } else {
          rb.style.display = 'block';
          rb.style.boxSizing = 'border-box';
          rb.style.height = maxHeight + 'px';
          rb.style.maxHeight = maxHeight + 'px';
          rb.style.overflow = 'hidden';
          if (inner !== rb) {
            inner.style.height = '100%';
            inner.style.overflowY = 'auto';
          } else {
            rb.style.overflowY = 'auto';
          }
        }

        // expand the background pseudo-element via CSS variable on wrapper
        try {
          const extra = 80; // allow space above/below pill for visual padding
          wrapper.style.setProperty('--search-result-height', (Math.min(desired, maxHeight) + extra) + 'px');
        } catch (e) {}
      } catch (err) {
        console.warn('adjustSearchResultHeight', err);
      }
    }

    function renderRecentSearches() {
      try {
        const key = 'recentSearches';
        const arr = JSON.parse(sessionStorage.getItem(key) || '[]') || [];
        if (!arr || arr.length === 0) {
          resultBox.innerHTML = '<div class="empty">Không có tìm kiếm nào gần đây</div>';
          adjustSearchResultHeight();
          return;
        }
        let html = '';
        arr.forEach(s => {
          // s may be object {name, avatar} or string
          const name = s && s.name ? String(s.name) : String(s || '');
          let avatar = s && s.avatar ? String(s.avatar) : '';
          if (avatar === 'null' || avatar === 'undefined') avatar = '';
          const safe = name.replace(/</g, '&lt;').replace(/>/g, '&gt;');
          const img = avatar ? (
            `<span class="avatar-wrap" style="display:inline-flex;align-items:center;">
                 <img src="${avatar}" width="36" style="border-radius:50%;margin-right:8px;flex:0 0 auto" crossorigin="anonymous" referrerpolicy="no-referrer" onerror="this.style.display='none';var f=this.parentNode.querySelector('.avatar-fallback'); if(f) f.style.display='inline-block';">
                 <svg class="avatar-fallback" viewBox="0 0 24 24" width="20" height="20" style="opacity:.6;margin-right:8px;display:none"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>
               </span>`
          ) : `<svg viewBox="0 0 24 24" width="20" height="20" style="opacity:.6;margin-right:8px"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>`;
          html += `<div class="user-item recent-item" data-key="${safe}" data-avatar="${avatar}">
                <div class="user-left">
                  ${img}
                  <span class="recent-label">${safe}</span>
                </div>
                <button class="recent-remove" data-key="${safe}" aria-label="Xóa">×</button>
              </div>`;
        });
        const header = `<div class="results-header"><span class="recent-title">Mới đây</span><button class="recent-edit" type="button">Chỉnh sửa</button></div>`;
        resultBox.innerHTML = `<div class="inner-results">${header}<div class="results-list">${html}</div></div>`;
        adjustSearchResultHeight();
      } catch (err) {
        console.warn('renderRecentSearches', err);
        resultBox.innerHTML = '';
      }
    }

    function renderUsers(users) {
      if (!users || users.length === 0) {
        resultBox.innerHTML = `<div class="empty"><div style="display:flex;align-items:center;gap:8px;padding:10px 12px;color:var(--app-muted)"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false" style="opacity:.7"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg><span>Không tìm thấy</span></div></div>`;
        adjustSearchResultHeight();
        return;
      }

      let html = '';
      users.forEach(u => {
        let av = u && u.avatar ? String(u.avatar) : '';
        if (av === 'null' || av === 'undefined') av = '';
        const nm = u && u.name ? String(u.name) : '';
        const safe = nm.replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const left = av ? (
          `<span class="avatar-wrap" style="display:inline-flex;align-items:center;">
               <img src="${av}" width="36" style="border-radius:50%;margin-right:8px;flex:0 0 auto" crossorigin="anonymous" referrerpolicy="no-referrer" onerror="this.style.display='none';var f=this.parentNode.querySelector('.avatar-fallback'); if(f) f.style.display='inline-block';">
               <svg class="avatar-fallback" viewBox="0 0 24 24" width="20" height="20" style="opacity:.6;margin-right:8px;display:none"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>
             </span>`
        ) : `<svg viewBox="0 0 24 24" width="20" height="20" style="opacity:.6;margin-right:8px"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>`;
        html += `<div class="user-item" data-name="${safe}" data-avatar="${av}"><div class="user-left">${left}<span>${safe}</span></div></div>`;
      });

      resultBox.innerHTML = html;
      adjustSearchResultHeight();
    }

    // Robust capture: ensure clicks on live result rows always save {name,avatar}
    document.addEventListener('click', function(e) {
      try {
        // only care if resultBox exists and click is inside it
        const rb = document.getElementById('searchResult');
        if (!rb) return;
        if (!rb.contains(e.target)) return;

        const row = e.target.closest && e.target.closest('.user-item');
        if (!row) return;
        // if it's already a recent-item, ignore (recent handler handles it)
        if (row.classList.contains('recent-item')) return;

        // extract name and avatar (robustly)
        const imgEl = row.querySelector('img');
        let name = '';
        try {
          if (row.dataset && row.dataset.name) name = String(row.dataset.name).trim();
        } catch (e) {}
        if (!name) {
          try {
            const nameEl = row.querySelector('.user-left span, span');
            if (nameEl) name = (nameEl.textContent || '').trim();
          } catch (e) {}
        }
        if (!name) {
          try {
            name = (row.textContent || '').replace(/\s+/g, ' ').trim();
          } catch (e) {}
        }

        let avatar = '';
        try {
          avatar = (row.getAttribute && row.getAttribute('data-avatar')) || (row.dataset && row.dataset.avatar) || '';
        } catch (e) {}
        if (!avatar && imgEl) try {
          avatar = imgEl.getAttribute('src') || '';
        } catch (e) {}
        avatar = (avatar || '').trim();

        if (name) {
          try {
            console.log('capture-save click ->', {
              name,
              avatar
            });
          } catch (e) {}
          try {
            console.log('calling upsertRecentSearch (capture handler)', {
              name,
              avatar
            });
          } catch (e) {}
          upsertRecentSearch({
            name: name,
            avatar: avatar
          });
          try {
            renderRecentSearches();
          } catch (e) {}
        } else {
          try {
            console.warn('capture-save ignored: no name detected', row);
          } catch (e) {}
        }
      } catch (err) {
        try {
          console.warn('capture-save error', err);
        } catch (e) {}
      }
    }, true);
  </script>

  <!-- debug panel removed -->



  <script src="/fb/assets/js/devtools-warning.js"></script>

</body>

</html>