<?php
require_once __DIR__ . '/../includes/db.php';

if (!Database::isLoggedIn()) {
  header('Location: ../auth/login.php');
  exit;
}

$currentUser = Database::getCurrentUser();
if (!$currentUser) {
  session_destroy();
  header('Location: ../auth/login.php');
  exit;
}

function fb_escape(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fb_urlencode_path(string $path): string
{
  $path = str_replace('\\', '/', $path);
  $parts = array_values(array_filter(explode('/', $path), static fn($p) => $p !== ''));
  $encoded = array_map('rawurlencode', $parts);
  return implode('/', $encoded);
}

$buoi = (int)($_GET['b'] ?? 0);
$allowedBuoi = [2, 3, 4, 5, 6, 7, 8];
if (!in_array($buoi, $allowedBuoi, true)) {
  http_response_code(404);
  echo 'Buổi không hợp lệ.';
  exit;
}

$baseFolderName = 'Buoi ' . $buoi;
$baseFs = realpath(__DIR__ . '/../LapTrinhWebThayNghia/' . $baseFolderName);
if ($baseFs === false || !is_dir($baseFs)) {
  http_response_code(404);
  echo 'Không tìm thấy thư mục Buổi.';
  exit;
}

$path = (string)($_GET['path'] ?? '');
$path = str_replace('\\', '/', $path);
$path = ltrim($path, '/');

// Basic traversal protections
if ($path !== '' && (str_contains($path, '..') || str_contains($path, ':') || str_contains($path, "\0"))) {
  http_response_code(400);
  echo 'Đường dẫn không hợp lệ.';
  exit;
}

$targetFs = $baseFs;
if ($path !== '') {
  $candidate = realpath($baseFs . DIRECTORY_SEPARATOR . $path);
  if ($candidate !== false && is_dir($candidate)) {
    $targetFs = $candidate;
  }
}

// Ensure target stays within base
$baseFsNorm = rtrim(str_replace('\\', '/', $baseFs), '/');
$targetFsNorm = rtrim(str_replace('\\', '/', $targetFs), '/');
if (stripos($targetFsNorm, $baseFsNorm) !== 0) {
  http_response_code(400);
  echo 'Đường dẫn không hợp lệ.';
  exit;
}

// Build the relative path from base
$relativePath = '';
if (strlen($targetFsNorm) > strlen($baseFsNorm)) {
  $relativePath = ltrim(substr($targetFsNorm, strlen($baseFsNorm)), '/');
}

$entries = @scandir($targetFs);
if (!is_array($entries)) $entries = [];

$items = [];
foreach ($entries as $name) {
  if ($name === '.' || $name === '..') continue;
  $full = $targetFs . DIRECTORY_SEPARATOR . $name;
  $items[] = [
    'name' => $name,
    'isDir' => is_dir($full),
  ];
}

usort($items, static function ($a, $b) {
  if ($a['isDir'] !== $b['isDir']) return $a['isDir'] ? -1 : 1;
  return strnatcasecmp($a['name'], $b['name']);
});

$baseUrl = '../LapTrinhWebThayNghia/' . fb_urlencode_path($baseFolderName);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Buổi <?= (int)$buoi ?> | Danh sách file</title>
  <link rel="stylesheet" href="../assets/css/theme.css">
  <script>
    (function () {
      try {
        const KEY = 'fbmenu-theme';
        const root = document.documentElement;
        if (!root) return;
        const apply = () => {
          let v = 'off';
          try { v = localStorage.getItem(KEY) || 'off'; } catch (_e) { v = 'off'; }
          const theme = (v === 'on') ? 'dark' : (v === 'auto' ? 'auto' : 'light');
          root.setAttribute('data-theme', theme);
          try { document.body && document.body.setAttribute('data-theme', theme); } catch (_e) {}
        };
        apply();
        window.addEventListener('storage', (e) => { if (e && e.key === KEY) apply(); });
        const mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
        if (mq) {
          const onChange = () => {
            try { if ((localStorage.getItem(KEY) || 'off') === 'auto') apply(); } catch (_e) {}
          };
          try { mq.addEventListener ? mq.addEventListener('change', onChange) : mq.addListener(onChange); } catch (_e) {}
        }
      } catch (_e) {}
    })();
  </script>
  <style>
    :root {
      --fb-blue: #0866ff;
      --app-page-bg: #f0f2f5;
      --app-surface-bg: #ffffff;
      --app-text: #050505;
      --app-muted: #65676B;
      --app-hover: #f2f2f2;
      --app-border: rgba(0, 0, 0, 0.08);
      --app-card-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
    }

    body {
      margin: 0;
      font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
      background: var(--app-page-bg);
      color: var(--app-text);
    }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 10;
      background: var(--app-surface-bg);
      border-bottom: 1px solid var(--app-border);
      box-shadow: 0 1px 2px rgba(0, 0, 0, .08);
    }

    .topbar-inner {
      max-width: 1050px;
      margin: 0 auto;
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .back {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 999px;
      border: 1px solid var(--app-border);
      background: var(--app-surface-bg);
      color: var(--app-text);
      text-decoration: none;
    }

    .back:hover { background: var(--app-hover); }

    .title {
      font-size: 16px;
      font-weight: 700;
      line-height: 1.2;
    }

    .subtitle {
      font-size: 13px;
      color: var(--app-muted);
    }

    .wrap {
      max-width: 1050px;
      margin: 0 auto;
      padding: 16px;
    }

    .crumbs {
      font-size: 13px;
      color: var(--app-muted);
      margin-bottom: 12px;
    }

    .crumbs a {
      color: var(--fb-blue);
      text-decoration: none;
      font-weight: 600;
    }

    .card {
      background: var(--app-surface-bg);
      border: 1px solid var(--app-border);
      border-radius: 12px;
      box-shadow: var(--app-card-shadow);
      overflow: hidden;
    }

    .row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 14px;
      border-top: 1px solid var(--app-border);
      text-decoration: none;
      color: inherit;
    }

    .row:first-child { border-top: none; }
    .row:hover { background: var(--app-hover); }

    .name {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
    }

    .pill {
      font-size: 12px;
      font-weight: 700;
      padding: 4px 8px;
      border-radius: 999px;
      border: 1px solid var(--app-border);
      background: var(--app-surface-bg);
      color: var(--app-muted);
      flex: 0 0 auto;
    }

    .filename {
      font-size: 14px;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .hint {
      font-size: 12px;
      color: var(--app-muted);
      flex: 0 0 auto;
    }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="topbar-inner">
      <a class="back" href="marketplace.php" aria-label="Quay lại Marketplace">←</a>
      <div>
        <div class="title">Buổi <?= (int)$buoi ?> · Danh sách file</div>
        <div class="subtitle">LapTrinhWebThayNghia / <?= fb_escape($baseFolderName) ?></div>
      </div>
    </div>
  </div>

  <div class="wrap">
    <div class="crumbs">
      <a href="marketplace.php">Marketplace</a>
      <span> / </span>
      <a href="buoi_files.php?b=<?= (int)$buoi ?>">Buổi <?= (int)$buoi ?></a>
      <?php
        $crumbAccum = '';
        if ($relativePath !== '') {
          $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $relativePath)), static fn($p) => $p !== ''));
          foreach ($parts as $p) {
            $crumbAccum = ($crumbAccum === '') ? $p : ($crumbAccum . '/' . $p);
            $href = 'buoi_files.php?b=' . (int)$buoi . '&path=' . rawurlencode($crumbAccum);
            echo '<span> / </span><a href="' . fb_escape($href) . '">' . fb_escape($p) . '</a>';
          }
        }
      ?>
    </div>

    <div class="card">
      <?php if ($relativePath !== ''): ?>
        <?php
          $parent = dirname($relativePath);
          if ($parent === '.' || $parent === DIRECTORY_SEPARATOR) $parent = '';
          $upHref = 'buoi_files.php?b=' . (int)$buoi . ($parent !== '' ? ('&path=' . rawurlencode($parent)) : '');
        ?>
        <a class="row" href="<?= fb_escape($upHref) ?>">
          <div class="name">
            <span class="pill">DIR</span>
            <span class="filename">..</span>
          </div>
          <span class="hint">Lên thư mục</span>
        </a>
      <?php endif; ?>

      <?php foreach ($items as $it): ?>
        <?php
          $name = (string)$it['name'];
          $isDir = (bool)$it['isDir'];
          $subPath = $relativePath === '' ? $name : ($relativePath . '/' . $name);

          if ($isDir) {
            $href = 'buoi_files.php?b=' . (int)$buoi . '&path=' . rawurlencode($subPath);
          } else {
            $href = $baseUrl . '/' . fb_urlencode_path($subPath);
          }
        ?>
        <a class="row" href="<?= fb_escape($href) ?>" <?= $isDir ? '' : 'target="_blank" rel="noopener"' ?>>
          <div class="name">
            <span class="pill"><?= $isDir ? 'DIR' : 'FILE' ?></span>
            <span class="filename"><?= fb_escape($name) ?></span>
          </div>
          <span class="hint"><?= $isDir ? 'Mở' : 'Xem' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
