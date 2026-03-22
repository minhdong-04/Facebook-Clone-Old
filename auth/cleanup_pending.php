<?php
// cleanup_pending.php
// Cách dùng (CLI): php cleanup_pending.php [--dry-run]
// Cách dùng (web): GET /cleanup_pending.php?key=YOUR_SECRET_KEY&dry=1

// BẢO MẬT: đặt khóa bí mật qua biến môi trường CLEANUP_KEY hoặc sửa $SECRET_KEY bên dưới
$SECRET_KEY = getenv('CLEANUP_KEY') ?: ''; // đặt trong biến môi trường server để sử dụng cho web

// Cho phép chạy từ CLI mà không cần key, nhưng khuyến nghị đặt key cho truy cập web
$isCli = php_sapi_name() === 'cli';

// Logger đơn giản
function _log($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    // đảm bảo thư mục logs tồn tại
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/cleanup_pending.log', $line, FILE_APPEND | LOCK_EX);
}

// Tạo file khoá đơn giản để tránh chạy đồng thời (concurrent runs)
$lockFile = __DIR__ . '/tmp/cleanup_pending.lock';
if (!file_exists(dirname($lockFile))) @mkdir(dirname($lockFile), 0755, true);
$fpLock = fopen($lockFile, 'c');
if (!$fpLock) {
    _log("ERROR: Couldn't open lock file $lockFile");
    if ($isCli) { echo "ERROR: Couldn't open lock file\n"; exit(1); }
    http_response_code(500); echo "Lock error"; exit;
}
if (!flock($fpLock, LOCK_EX | LOCK_NB)) {
    _log("INFO: Another cleanup is already running. Exiting.");
    if ($isCli) { echo "Another cleanup is already running. Exiting.\n"; exit(0); }
    http_response_code(200); echo "Another cleanup is already running."; exit;
}

// Phân tích tham số
$dryRun = false;
if ($isCli) {
    foreach ($argv as $a) {
        if ($a === '--dry-run' || $a === '-n') $dryRun = true;
    }
} else {
    // Web request
    $key = $_GET['key'] ?? '';
    if (!$SECRET_KEY) {
        // nếu SECRET_KEY không được đặt, yêu cầu tham số key phải không rỗng (và ghi log cảnh báo)
        if (!$key) {
            http_response_code(403);
            echo "Forbidden: missing key";
            flock($fpLock, LOCK_UN);
            fclose($fpLock);
            exit;
        }
    } else {
        if ($key !== $SECRET_KEY) {
            http_response_code(403);
            echo "Forbidden: invalid key";
            flock($fpLock, LOCK_UN);
            fclose($fpLock);
            exit;
        }
    }
    if (isset($_GET['dry']) && ($_GET['dry'] === '1' || $_GET['dry'] === 'true')) $dryRun = true;
}

// Include bootstrap kết nối DB của dự án
require_once __DIR__ . '/../includes/db.php';

// Sử dụng helper Database (dự án có Database::NonQuery, Database::GetOne, ...)
try {
    // LƯU Ý: cột pending_registrations.expires_at là INT (timestamp UNIX, theo giây)
    // Sẽ so sánh với UNIX_TIMESTAMP() của MySQL để tránh lệch giờ giữa PHP và MySQL.

    // Nếu dry-run: chỉ đếm số bản ghi sẽ bị xóa
    if ($dryRun) {
        $cnt = (int) Database::GetOne("SELECT COUNT(*) FROM pending_registrations WHERE expires_at <= UNIX_TIMESTAMP()");
        $msg = "DRY RUN: $cnt pending registration(s) would be deleted (expires_at <= UNIX_TIMESTAMP()).";
        _log($msg);
        if ($isCli) {
            echo $msg . PHP_EOL;
            // release lock
            flock($fpLock, LOCK_UN); fclose($fpLock);
            exit(0);
        } else {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'dry_run' => true, 'would_delete' => $cnt]);
            flock($fpLock, LOCK_UN); fclose($fpLock);
            exit;
        }
    }

    // Đếm trước khi xóa
    $before = (int) Database::GetOne("SELECT COUNT(*) FROM pending_registrations WHERE expires_at <= UNIX_TIMESTAMP()");

    // Thực hiện xóa
    Database::NonQuery("DELETE FROM pending_registrations WHERE expires_at <= UNIX_TIMESTAMP()");

    // Đếm sau khi xóa
    $after = (int) Database::GetOne("SELECT COUNT(*) FROM pending_registrations WHERE expires_at <= UNIX_TIMESTAMP()");

    $deleted = max(0, $before - $after);

    $msg = "Cleanup completed. Deleted: {$deleted}. Remaining expired (expires_at <= UNIX_TIMESTAMP()): {$after}.";
    _log($msg);

    if ($isCli) {
        echo $msg . PHP_EOL;
        flock($fpLock, LOCK_UN); fclose($fpLock);
        exit(0);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'deleted' => $deleted, 'remaining_expired' => $after]);
        flock($fpLock, LOCK_UN); fclose($fpLock);
        exit;
    }

} catch (Exception $ex) {
    _log("ERROR: Exception during cleanup: " . $ex->getMessage());
    if ($isCli) {
        echo "ERROR: " . $ex->getMessage() . PHP_EOL;
        flock($fpLock, LOCK_UN); fclose($fpLock);
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
        flock($fpLock, LOCK_UN); fclose($fpLock);
        exit;
    }
}
