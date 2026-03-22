<?php

session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!Database::isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401);
    exit(json_encode(['ok' => false]));
}

$current_user = Database::getCurrentUser();
$message = trim((string)($_POST['message'] ?? ''));
$to_user = (int)($_POST['to_user'] ?? 0);

function fb_chat_json(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function fb_chat_ext_from_mime(string $mime): string
{
    $mime = strtolower(trim($mime));
    $map = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
    ];
    return $map[$mime] ?? '';
}

function fb_chat_safe_filename(int $fromUserId, int $toUserId, string $ext): string
{
    $rand = bin2hex(random_bytes(6));
    $ts = date('Ymd_His');
    $ext = preg_replace('/[^a-z0-9]/i', '', $ext);
    if ($ext === '') $ext = 'bin';
    return "chat_{$fromUserId}_{$toUserId}_{$ts}_{$rand}.{$ext}";
}

function fb_chat_save_upload(array $file, int $fromUserId, int $toUserId): ?array
{
    if (empty($file) || !isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $maxBytes = 25 * 1024 * 1024; // 25MB
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        return null;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime = '';
    if ($finfo) {
        $mime = (string)finfo_file($finfo, $tmp);
        finfo_close($finfo);
    }
    $mime = strtolower(trim($mime));
    if ($mime === '') {
        $mime = strtolower(trim((string)($file['type'] ?? '')));
    }

    $isImage = str_starts_with($mime, 'image/');
    $isAudio = str_starts_with($mime, 'audio/');
    if (!$isImage && !$isAudio) {
        return null;
    }

    // whitelist
    $allowed = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/aac'
    ];
    if (!in_array($mime, $allowed, true)) {
        return null;
    }

    $ext = fb_chat_ext_from_mime($mime);
    if ($ext === '') {
        $name = (string)($file['name'] ?? '');
        $extGuess = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $extGuess = preg_replace('/[^a-z0-9]/i', '', $extGuess);
        $ext = $extGuess ?: 'bin';
    }

    $filename = fb_chat_safe_filename($fromUserId, $toUserId, $ext);
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if (!$uploadsDir) {
        // fallback if realpath fails
        $uploadsDir = __DIR__ . '/../uploads';
    }

    if (!is_dir($uploadsDir)) {
        @mkdir($uploadsDir, 0777, true);
    }

    $destPath = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!@move_uploaded_file($tmp, $destPath)) {
        return null;
    }

    // return payload to store in DB
    return [
        'type' => $isImage ? 'image' : 'audio',
        'file' => $filename,
        'mime' => $mime,
    ];
}

if ($to_user > 0) {
    $contentToSave = '';

    // 1) file upload (image/audio)
    $uploadPayload = null;
    if (!empty($_FILES['file'])) {
        $uploadPayload = fb_chat_save_upload($_FILES['file'], (int)$current_user['id'], $to_user);
    } elseif (!empty($_FILES['image'])) {
        $uploadPayload = fb_chat_save_upload($_FILES['image'], (int)$current_user['id'], $to_user);
    }
    if (is_array($uploadPayload)) {
        $contentToSave = fb_chat_json($uploadPayload);
    }

    // 2) text fallback
    if ($contentToSave === '' && $message !== '') {
        $contentToSave = $message;
    }

    if ($contentToSave === '') {
        http_response_code(400);
        exit(json_encode(['ok' => false]));
    }

    $newId = Database::NonQuery(
        "INSERT INTO messages (from_user, to_user, content) VALUES (?, ?, ?)",
        [$current_user['id'], $to_user, $contentToSave]
    );

    if ($newId) {
        $row = Database::GetRow(
            "SELECT id, from_user, to_user, content, is_read, created_at FROM messages WHERE id = ? LIMIT 1",
            [(int)$newId]
        );
        exit(json_encode(['ok' => true, 'message' => $row]));
    }

    http_response_code(500);
    exit(json_encode(['ok' => false]));
}

http_response_code(400);
echo json_encode(['ok' => false]);