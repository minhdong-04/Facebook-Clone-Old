<?php
declare(strict_types=1);

// includes/password_reset.php
// Helpers for password reset (selector:validator).
// This file expects Database::GetPDO() to exist and return a PDO instance.
// If you prefer Reflection, tell me and I can provide that variant.

// config: token lifetime in minutes
if (!defined('PR_TOKEN_MINUTES')) define('PR_TOKEN_MINUTES', 60);

if (!function_exists('pr_random_base64')) {
    function pr_random_base64(int $len = 18): string {
        return rtrim(strtr(base64_encode(random_bytes($len)), '+/', '-_'), '=');
    }
}

/**
 * Get PDO helper — uses Database::GetPDO() which should call the private Connect internally.
 * Throws RuntimeException if not available.
 *
 * @return PDO
 */
function pr_get_pdo(): PDO {
    if (!class_exists('Database')) {
        throw new RuntimeException('Database class not found. Make sure includes/db.php is loaded.');
    }

    // Preferred entrypoint in this codebase
    if (method_exists('Database', 'GetPDO')) {
        $pdo = Database::GetPDO();
        if ($pdo instanceof PDO) return $pdo;
        throw new RuntimeException('Database::GetPDO() did not return a PDO instance.');
    }

    throw new RuntimeException('Cannot obtain PDO. Add Database::GetPDO() to includes/db.php.');
}

/**
 * Create a reset token, insert to DB and send email (mail() used as placeholder).
 * Returns public token (selector:validator) — not necessary to return but handy for testing.
 *
 * @param int $user_id
 * @param string $user_email
 * @param int|null $minutes
 * @return string
 */
function pr_create_and_send_token(int $user_id, string $user_email, ?int $minutes = null): string {
    $minutes = $minutes ?? PR_TOKEN_MINUTES;
    $pdo = pr_get_pdo();

    $selector = substr(pr_random_base64(9), 0, 12);
    $validator_raw = bin2hex(random_bytes(32));
    $validator_hash = hash('sha256', $validator_raw);
    $expires_at = (new DateTime("+{$minutes} minutes"))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $selector, $validator_hash, $expires_at]);

    $publicToken = $selector . ':' . $validator_raw;

    // build reset url (adjust base URL if needed)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $resetUrl = $scheme . '://' . $host . '/reset_password.php?token=' . rawurlencode($publicToken);

    $subject = "Đặt lại mật khẩu";
    $message = "Bạn hoặc ai đó đã yêu cầu đặt lại mật khẩu.\n\nBấm vào đây để đặt lại (hết hạn trong {$minutes} phút):\n{$resetUrl}\n\nNếu không phải bạn, hãy bỏ qua email này.";
    @mail($user_email, $subject, $message, "From: no-reply@" . ($host));

    return $publicToken;
}

/**
 * Verify public token. Return array with reset_id, user_id, user info on success; null on fail.
 *
 * @param string $publicToken
 * @return array|null
 */
function pr_verify_token(string $publicToken): ?array {
    if (strpos($publicToken, ':') === false) return null;
    list($selector, $validator_raw) = explode(':', $publicToken, 2);
    if (!$selector || !$validator_raw) return null;

    $pdo = pr_get_pdo();
    $stmt = $pdo->prepare("SELECT pr.id AS reset_id, pr.user_id, pr.validator_hash, pr.expires_at, u.id AS uid, u.email, u.name
                           FROM password_resets pr
                           JOIN users u ON u.id = pr.user_id
                           WHERE pr.selector = ? AND pr.expires_at > NOW()
                           LIMIT 1");
    $stmt->execute([$selector]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $hash = hash('sha256', $validator_raw);
    if (!hash_equals((string)$row['validator_hash'], $hash)) {
        // invalid validator -> delete tokens for this user as precaution
        try {
            $del = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $del->execute([$row['user_id']]);
        } catch (Throwable $e) {}
        return null;
    }

    return [
        'reset_id' => (int)$row['reset_id'],
        'user_id'  => (int)$row['user_id'],
        'user'     => [
            'id' => (int)$row['uid'],
            'email' => $row['email'],
            'name' => $row['name']
        ]
    ];
}

/**
 * Consume (delete) token by reset_id
 */
function pr_consume_token(int $reset_id): void {
    $pdo = pr_get_pdo();
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE id = ?");
    $stmt->execute([$reset_id]);
}
