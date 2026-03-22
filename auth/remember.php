<?php
require_once __DIR__ . '/../includes/CookieEncrypt.php';
$cookie = new CookieEncrypt();

// Constants
define('REMEMBER_COOKIE_NAME', 'remember_secure');
define('REMEMBER_DAYS', 30);
define('REMEMBER_LIFETIME', REMEMBER_DAYS * 86400);

function remember_secure_create(int $user_id, PDO $pdo)
{
    global $cookie;

    $selector = bin2hex(random_bytes(8));
    $validator = bin2hex(random_bytes(32));
    $hash = hash("sha256", $validator);

    $stmt = $pdo->prepare("
        INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
    ");
    $stmt->execute([$user_id, $selector, $hash]);

    // Raw token
    $raw = $selector . ":" . $validator;

    // AES-256 encrypted cookie
    $cookie->set(REMEMBER_COOKIE_NAME, $raw, REMEMBER_LIFETIME);
}

function remember_secure_login(PDO $pdo)
{
    global $cookie;

    $raw = $cookie->get(REMEMBER_COOKIE_NAME);
    if (!$raw || !str_contains($raw, ":")) return null;

    list($selector, $validator) = explode(":", $raw);

    $stmt = $pdo->prepare("
        SELECT rt.id AS token_id, rt.user_id,
               rt.validator_hash, rt.expires_at,
               u.id AS uid, u.email, u.name, u.avatar
        FROM remember_tokens rt
        JOIN users u ON u.id = rt.user_id
        WHERE rt.selector = ? AND rt.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$selector]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    if (!hash_equals($row['validator_hash'], hash("sha256", $validator))) {

        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")
            ->execute([$row["user_id"]]);

        return null;
    }

    // Token rotation
    $newValidator = bin2hex(random_bytes(32));
    $newHash = hash("sha256", $newValidator);

    $pdo->prepare("
        UPDATE remember_tokens
        SET validator_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)
        WHERE id = ?
    ")->execute([$newHash, $row['token_id']]);

    $cookie->set(REMEMBER_COOKIE_NAME, $selector . ":" . $newValidator, REMEMBER_LIFETIME);

    // RETURN user info
    return [
        "id"     => (int)$row["uid"],
        "email"  => $row["email"],
        "name"   => $row["name"],
        "avatar" => $row["avatar"]
    ];
}

function remember_secure_clear(int $user_id, PDO $pdo)
{
    global $cookie;

    $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")
        ->execute([$user_id]);

    $cookie->delete(REMEMBER_COOKIE_NAME);
}
