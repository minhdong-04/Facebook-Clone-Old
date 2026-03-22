<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Database
{
    private static bool $rememberBootstrapped = false;

    private const HOST     = 'localhost';
    private const USERNAME = 'root';
    private const PASSWORD = '';
    private const DBNAME   = 'facebook_clone';
    private const CHARSET  = 'utf8';

    /**
     * Tạo kết nối PDO
     * @return PDO
     */
    private static function Connect(): PDO
    {
        $dsn = "mysql:host=" . self::HOST . ";dbname=" . self::DBNAME . ";charset=" . self::CHARSET;
        try {
            $pdo = new PDO($dsn, self::USERNAME, self::PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (PDOException $e) {
            die("Lỗi kết nối: " . $e->getMessage());
        }
    }
    
    public static function GetPDO(): PDO
    {
        return self::Connect();
    }

    private static function bootstrapRememberLogin(): void
    {
        if (self::$rememberBootstrapped) return;
        self::$rememberBootstrapped = true;

        // If already logged in, nothing to do
        if (!empty($_SESSION['logged_in']) || (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0)) {
            return;
        }

        $rememberFile = __DIR__ . '/../auth/remember.php';
        if (!is_file($rememberFile)) return;

        require_once $rememberFile;

        try {
            $pdo = self::GetPDO();
            if (!function_exists('remember_secure_login')) return;

            $user = remember_secure_login($pdo);
            if (!is_array($user) || empty($user['id'])) return;

            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = (int)$user['id'];

            // Keep compatibility with different parts of the app
            $_SESSION['user_email'] = (string)($user['email'] ?? '');
            $_SESSION['user_name'] = (string)($user['name'] ?? '');
            $_SESSION['user_avatar'] = (string)($user['avatar'] ?? '');

            $_SESSION['email'] = (string)($user['email'] ?? '');
            $_SESSION['name'] = (string)($user['name'] ?? '');
            $_SESSION['avatar'] = (string)($user['avatar'] ?? '');
        } catch (Throwable $e) {
            // best-effort only
            error_log('bootstrapRememberLogin failed: ' . $e->getMessage());
        }
    }

    /*Kiểm tra đăng nhập*/
    public static function isLoggedIn(): bool
    {
        self::bootstrapRememberLogin();
        return (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true)
            || (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0);
    }

    /*lấy user hiện tại*/
    public static function getCurrentUser(): ?array
    {
        self::bootstrapRememberLogin();
        if (!self::isLoggedIn()) {
            return null;
        }

        $query = "SELECT * FROM users WHERE id = ?";
        $params = [$_SESSION['user_id']];

        return self::GetRow($query, $params);
    }

    /*select lấy nhiều dòng*/
    public static function GetData(string $query, array $params = [], array $format = []): array
    {
        $pdo = self::Connect();
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();

            if (empty($data)) return [];

            // Trả về 1 ô
            if (isset($format['cell'])) {
                $row = $format['row'] ?? 0;
                $key = is_numeric($format['cell']) 
                    ? array_keys($data[$row])[$format['cell']] 
                    : $format['cell'];
                return $data[$row][$key] ?? null;
            }

            // Trả về 1 dòng
            if (isset($format['row'])) {
                return $data[$format['row']] ?? null;
            }

            return $data;
        } catch (PDOException $e) {
            die("Query failed: " . $e->getMessage());
        } finally {
            $pdo = null;
        }
    }

    /*select lấy 1 dòng*/
    public static function GetRow(string $query, array $params = []): ?array
    {
        $result = self::GetData($query, $params, ['row' => 0]);
        return is_array($result) ? $result : null;
    }

    public static function GetOne(string $query, array $params = [])
    {
        $pdo = self::Connect();
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetchColumn();
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            error_log("GetOne failed: " . $e->getMessage());
            return null;
        } finally {
            $pdo = null;
        }
    }

    /*các chức năng thêm,sửa,xóa*/
    public static function NonQuery(string $query, array $params = []): ?int
    {
        $pdo = self::Connect();
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount() > 0 ? $pdo->lastInsertId() : null;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            return null;
        } finally {
            $pdo = null;
        }
    }

    /* ============================
       REMEMBER-ME: helpers & methods
       - All methods are static and use self::Connect()
       - Table expected: remember_tokens
         (SQL for migration at bottom of file)
       ============================ */

    /**
     * Private helper: secure random base64-like string
     * REMEMBER-ME: dùng để tạo selector
     */
    private static function random_base64(int $len = 18): string
    {
        if (function_exists('random_bytes')) {
            $bytes = random_bytes($len);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($len);
        } else {
            // fallback (less preferred)
            $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $out = '';
            for ($i = 0; $i < $len; $i++) {
                $out .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $out;
        }

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * REMEMBER-ME:
     * Tạo token (selector + validator), lưu vào bảng remember_tokens
     * và set cookie "remember" có giá trị selector:validator (validator raw).
     *
     * @param int $user_id
     * @param int $days (số ngày cookie còn hiệu lực)
     * @return void
     */
    public static function remember_create_and_set_cookie(int $user_id, int $days = 30): void
    {
        $pdo = self::Connect();
        try {
            // selector ngắn hơn, validator dài (raw) -> validator_hash lưu DB
            $selector = substr(self::random_base64(9), 0, 12);
            $validator = bin2hex(random_bytes(32)); // raw validator, 64 hex chars
            $validator_hash = hash('sha256', $validator);
            $expires = new DateTime("+{$days} days");
            $expires_str = $expires->format('Y-m-d H:i:s');

            $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $selector, $validator_hash, $expires_str]);

            $cookieVal = $selector . ':' . $validator;
            setcookie('remember', $cookieVal, [
                'expires'  => $expires->getTimestamp(),
                'path'     => '/',
                'domain'   => '', // chỉnh nếu cần
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } catch (Exception $e) {
            error_log("remember_create failed: " . $e->getMessage());
            // không die — tránh phá flow đăng nhập
        } finally {
            $pdo = null;
        }
    }

    /**
     * REMEMBER-ME:
     * Thử login từ cookie "remember".
     * Nếu thành công: rotate validator, set cookie mới, trả về user minimal (id,email,name,avatar).
     * Nếu thất bại: trả về null (và xóa cookie khi cần).
     *
     * @return array|null
     */
    public static function remember_login_from_cookie(): ?array
    {
        if (empty($_COOKIE['remember'])) return null;

        $parts = explode(':', $_COOKIE['remember']);
        if (count($parts) !== 2) {
            // invalid format -> clear cookie
            setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            return null;
        }
        list($selector, $validator) = $parts;
        if (!$selector || !$validator) {
            setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            return null;
        }

        $pdo = self::Connect();
        try {
            // select token not expired
            $stmt = $pdo->prepare("
                SELECT rt.user_id, rt.validator_hash, rt.expires_at, u.id AS uid, u.email, u.name, u.avatar
                FROM remember_tokens rt
                JOIN users u ON u.id = rt.user_id
                WHERE rt.selector = ? AND rt.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$selector]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                // invalid selector or expired -> clear cookie
                setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                return null;
            }

            // validate validator
            $hash = hash('sha256', $validator);
            if (!hash_equals($row['validator_hash'], $hash)) {
                // possible theft: remove all tokens for this user and clear cookie
                $del = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                $del->execute([$row['user_id']]);
                setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                return null;
            }

            // OK: rotate validator (prevent replay attacks)
            $newValidator = bin2hex(random_bytes(32));
            $newValidatorHash = hash('sha256', $newValidator);
            $newExpires = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

            $upd = $pdo->prepare("UPDATE remember_tokens SET validator_hash = ?, expires_at = ? WHERE selector = ?");
            $upd->execute([$newValidatorHash, $newExpires, $selector]);

            // set new cookie (same selector, new validator)
            $cookieVal = $selector . ':' . $newValidator;
            setcookie('remember', $cookieVal, [
                'expires'  => (new DateTime('+30 days'))->getTimestamp(),
                'path'     => '/',
                'domain'   => '',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            return [
                'id'     => $row['uid'],
                'email'  => $row['email'],
                'name'   => $row['name'],
                'avatar' => $row['avatar'] ?? ''
            ];
        } catch (Exception $e) {
            error_log("remember_login failed: " . $e->getMessage());
            return null;
        } finally {
            $pdo = null;
        }
    }

    /**
     * REMEMBER-ME:
     * Xóa tất cả token liên quan tới user (khi logout) và clear cookie.
     *
     * @param int $user_id
     * @return void
     */
    public static function remember_clear_for_user(int $user_id): void
    {
        $pdo = self::Connect();
        try {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $stmt->execute([$user_id]);
            setcookie('remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        } catch (Exception $e) {
            error_log("remember_clear failed: " . $e->getMessage());
        } finally {
            $pdo = null;
        }
    }

}


