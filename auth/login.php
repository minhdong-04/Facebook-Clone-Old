    <?php
    if (session_status() === PHP_SESSION_NONE) session_start();

    // includes an toàn theo đường dẫn tuyệt đối
    include __DIR__ . '/../includes/language.php';
    require_once __DIR__ . '/../includes/db.php';
    // thêm include remember helpers (file bạn đã lưu theo hướng dẫn trước)
    require_once __DIR__ . '/remember.php';


    $pdo = Database::GetPDO();
    $js_save_and_redirect = false;
    $saved_email = '';
    $saved_name = '';
    $saved_avatar = ''; // sẽ gán avatar ở dưới nếu login thành công

    // --- 1) Nếu chưa login, thử login từ "remember" cookie tự động (rotate token bên trong hàm)
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        try {
            $rememberUser = remember_secure_login($pdo); // trả về user assoc hoặc null
            if (is_array($rememberUser)) {
                // set session giống như khi đăng nhập thành công
                session_regenerate_id(true);
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = (int)$rememberUser['id'];
                $_SESSION['user_name'] = $rememberUser['name'] ?? $rememberUser['email'] ?? '';
                $_SESSION['user_email'] = $rememberUser['email'] ?? '';
                $_SESSION['user_avatar'] = $rememberUser['avatar'] ?? '';

                // presence: online + heartbeat baseline
                try {
                    Database::NonQuery('UPDATE users SET is_online = 1, last_active = NOW() WHERE id = ?', [(int)$rememberUser['id']]);
                } catch (Throwable $e) {
                    // best-effort
                }

                // redirect đến home (đã login từ cookie)
                header("Location: ../pages/home.php");
                exit;
            }
        } catch (Throwable $e) {
            // nếu có lỗi với remember, không làm vỡ app — chỉ log nếu cần
            error_log("remember_login_from_cookie error: " . $e->getMessage());
        }
    }

    // --- 2) Xử lý POST form đăng nhập (user submit)
    if (isset($_POST['login'])) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['pass'] ?? '';

        // Lấy user từ DB (Database::GetRow giả sử dùng prepared stmt)
        $user = Database::GetRow("SELECT id, name, password, avatar FROM users WHERE email = ?", [$email]);

        if ($user && isset($user['password']) && password_verify($password, $user['password'])) {
            // bảo mật: regenerate session id
            session_regenerate_id(true);

            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'] ?? $email;
            // Lưu email/name/avatar vào session để logout có thể dùng (nếu cần)
            $_SESSION['user_email'] = $email;
            $_SESSION['user_avatar'] = $user['avatar'] ?? '';

            // presence: online + heartbeat baseline
            try {
                Database::NonQuery('UPDATE users SET is_online = 1, last_active = NOW() WHERE id = ?', [(int)$user['id']]);
            } catch (Throwable $e) {
                // best-effort
            }

            // Nếu checkbox "Nhớ mật khẩu" được tick (name="savepass"), tạo token và set cookie
            // Lưu ý: remember_create_and_set_cookie sẽ dùng PDO nội bộ nếu không truyền PDO
            if (!empty($_POST['savepass'])) {
                try {
                    // tạo remember token (mặc định 30 ngày)
                    remember_secure_create((int)$user['id'], $pdo);
                } catch (Throwable $e) {
                    // không bắt buộc — log rồi tiếp tục login bình thường
                    error_log("remember_create_and_set_cookie error: " . $e->getMessage());
                }
            }

            session_write_close();

            // Chuẩn bị dữ liệu để emit JS lưu vào localStorage rồi redirect (nếu bạn có logic này)
            $js_save_and_redirect = true;
            $saved_email = $email;
            $saved_name = $user['name'] ?? $email;

            // nếu DB có avatar dùng $user['avatar'] ưu tiên
            $saved_avatar = $user['avatar'] ?? $saved_avatar;

            // không thực hiện header redirect ở server-side nếu bạn cần JS lưu vào localStorage trước.
            // nếu bạn muốn redirect ngay từ server, uncomment dòng dưới:
            // header("Location: ../pages/home.php"); exit;
        } else {
            $error = "Thông tin đăng nhập không chính xác.";
        }
    }

    // Nếu đã đăng nhập (và không phải vừa login để lưu JS), redirect thẳng
    if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && !$js_save_and_redirect) {
    header("Location: ../pages/home.php");
    exit;
}
    ?>

    <!DOCTYPE html>
    <html lang="<?= htmlspecialchars($lang ?? 'vi') ?>">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($text['title'] ?? 'Đăng nhập') ?></title>
        <link rel="icon" href="../uploads/fb_logo.jpg">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            * {
                box-sizing: border-box;
            }

            html,
            body {
                height: 100%;
                margin: 0;
                font-family: 'Helvetica Neue', Arial, sans-serif;
            }

            body {
                background: #f0f2f5;
                display: flex;
                flex-direction: column;
            }

            .main-content {
                flex: 1;
                display: flex;
                justify-content: center;
                align-items: flex-start;
                padding: 180px 120px 140px;
                gap: 5px;
            }

            .login-left {
                max-width: 500px;
                text-align: left;
                margin-top: -130px;
            }

            .fb-logo {
                font-family: "Segoe UI", Arial, sans-serif !important;
                font-size: 55px;
                font-weight: 700;
                color: #1877f2;
                line-height: 1;
                transition: font-size .18s ease, transform .18s ease;
                display: inline-block;
            }

            /* when reduced (>=2 cards) */
            .fb-logo--small {
                font-size: 40px !important;
                transform: none;
                /* tránh scale lạ */
                transform-origin: left top;
                line-height: 1;
                margin-bottom: 25px;
            }

            /* container-level shrunk: toggle to change slogan blocks (keeps styles simple) */
            .login-left.shrunk .slogan.default {
                display: none !important;
            }

            .login-left.shrunk .slogan.cards {
                display: block !important;
            }

            /* Slogan default and cards variants */
            .slogan {
                font-family: SFProDisplay-Regular, Helvetica, Arial, sans-serif;
                font-size: 28px;
                font-weight: 400;
                line-height: 32px;
                width: 500px;
                transition: opacity .18s ease, transform .18s ease;
            }


            .login-left.shrunk .slogan.cards {
                opacity: 1;
                transform: none;
            }

            .login-left .slogan.default {
                opacity: 1;
            }

            .slogan.default {
                display: block;
            }

            .slogan.default .sub {
                display: block;
                font-size: 14px;
                color: #6e7377;
                margin-top: 8px;
                font-weight: 400;
            }

            .slogan.cards {
                font-size: 36px;
                display: none;
            }

            .slogan.cards .sub {
                display: block;
                font-size: 16px;
                color: #6e7377;
                margin-top: 4px;
                font-weight: 400;

            }

            /* ---------- saved accounts (Facebook-like) ---------- */
            .accounts {
                display: flex;
                gap: 14px;
                flex-wrap: wrap;
                margin-top: 18px;
            }

            /* Card base: có 1px border-like (frame), radius cố định.
            Bóng sẽ dùng nhiều layer để có cảm giác "đều bốn phía" và bo góc theo border-radius. */
            .acc-card {
                width: 160px;
                background: #fff;
                border-radius: 8px;
                overflow: visible;
                /* cho phép phần X và tooltip nhảy ra ngoài */
                position: relative;
                cursor: pointer;

                /* khung đứng yên (nhẹ) giống Facebook: 1px 'border' bằng box-shadow, giữ nguyên hình hộp */
                box-shadow: 0 0 0 1px #dddfe2;

                transition: box-shadow .18s ease-out, transform .12s ease-out;

            }

            /* HOVER SHADOW — tạo nhiều lớp shadow để đều 4 phía, có border-radius feel */
            .acc-card:hover {
                transform: translateY(-2px);
                box-shadow:
                    0 0 0 1px #dddfe2,
                    0 6px 18px rgba(0, 0, 0, 0.10),
                    0 10px 28px rgba(0, 0, 0, 0.12),
                    0 2px 6px rgba(0, 0, 0, 0.06);
            }

            .acc-inner {
                display: block;
                overflow: hidden;
                /* giữ avatar bo tròn bên trong nếu cần */
                border-radius: 8px;
                background: transparent;
            }

            .acc-avatar {
                width: 100%;
                height: 160px;
                background: #e9ecef;
                display: block;
                overflow: hidden;
                position: relative;
                border-top-left-radius: 8px;
                border-top-right-radius: 8px;
            }

            .acc-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
                border-radius: 0;
            }

            .acc-name {
                width: 100%;
                box-sizing: border-box;
                padding: 14px 12px;
                border-top: 1px solid #f1f3f5;
                text-align: center;
                font-size: 15px;
                color: #111;
                background: #fff;
                border-bottom-left-radius: 8px;
                border-bottom-right-radius: 8px;
            }

            /* remove button (X) */
            .remove-btn {
                position: absolute;
                left: 4px;
                top: 4px;
                width: 17px;
                height: 17px;

                border-radius: 16px;
                background-color: rgba(0, 0, 0, 0.30);
                border: none;
                color: #ffffff;
                font-size: 10px;
                font-weight: 900;

                display: flex;
                align-items: center;
                justify-content: center;

                opacity: 0.55;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
                transition: all .18s ease-in-out;
                z-index: 10;
                pointer-events: auto;
            }

            /* Khi hover CARD → X to ra và thành tròn trắng */
            .acc-card:hover .remove-btn {
                opacity: 1;
                width: 25px;
                height: 25px;
                font-size: 18px;
                top: -3px;
                left: -3px;

                background: #ffffff;
                color: #d3d6db;

                border-radius: 50%;
                box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
            }

            /* Tooltip (persistent after logout) — bo góc, có mũi tên */
            .acc-tooltip {
                position: absolute;
                min-width: 260px;
                max-width: 360px;
                background: #2f6df6;
                color: #fff;
                padding: 12px 14px;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(31, 44, 56, 0.18);
                z-index: 10000;
                font-size: 14px;
                line-height: 1.35;
            }

            .acc-tooltip .close-x {
                position: absolute;
                right: 8px;
                top: 6px;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: rgba(255, 255, 255, 0.95);
                font-weight: 700;
                font-size: 14px;
                background: transparent;
                border-radius: 50%;
            }

            /* arrow: we'll add a small triangular arrow */
            .acc-tooltip::after {
                content: "";
                position: absolute;
                left: 18px;
                bottom: -8px;
                border-left: 7px solid transparent;
                border-right: 7px solid transparent;
                border-top: 8px solid #2f6df6;
                filter: drop-shadow(0 2px 2px rgba(10, 20, 30, 0.06));
            }

            /* inline hover tooltip (small one) — keep for previous hover behavior */
            .acc-tooltip-inline {
                position: absolute;
                left: 12px;
                top: -78px;
                background: #2f6df6;
                color: #fff;
                padding: 10px 12px;
                border-radius: 6px;
                max-width: 300px;
                font-size: 13px;
                line-height: 1.3;
                box-shadow: 0 6px 18px rgba(10, 20, 30, 0.15);
                z-index: 999;
            }

            .acc-tooltip-inline::after {
                content: "";
                position: absolute;
                left: 16px;
                top: 100%;
                border-left: 7px solid transparent;
                border-right: 7px solid transparent;
                border-top: 7px solid #2f6df6;
            }

            .add-card .acc-avatar {
                width: 100%;
                height: 160px;
                background: #f5f6f7;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .add-card .plus-circle {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                background: #1877f2;
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 28px;
                font-weight: 600;
            }

            /* right column */
            .login-right {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 0 50px;
                position: relative;
                top: -90px;
            }

            .login-box {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, .1), 0 8px 16px rgba(0, 0, 0, .1);
                width: 396px;
                text-align: center;
            }

            .login-box input {
                width: 100%;
                padding: 14px 16px;
                margin-bottom: 12px;
                border: 1px solid #dddfe2;
                border-radius: 6px;
                font-size: 17px;
                color: #1c1e21;
            }

            .login-box input:focus {
                outline: none;
                border-color: #1877f2;
                box-shadow: 0 0 0 2px #e7f3ff;
            }

            .btn-login {
                background: #1877f2;
                color: #fff;
                font-size: 20px;
                font-weight: 600;
                padding: 12px;
                border: none;
                border-radius: 6px;
                width: 100%;
                margin: 8px 0 16px;
                cursor: pointer;
            }

            .btn-login:hover {
                background: #166fe5;
            }

            .forgot {
                color: #0866ff;
                font-size: 14px;
                text-decoration: none;
                display: block;
                margin: 16px 0;
            }

            .divider {
                border-top: 1px solid #dadde1;
                margin: 20px 0;
            }

            .btn-create {
                background: #42b72a;
                color: #fff;
                font-size: 17px;
                font-weight: 600;
                padding: 10px 24px;
                border: none;
                border-radius: 6px;
                cursor: pointer;
            }

            .btn-create:hover {
                background: #3aa324;
            }

            .create-page {
                margin-top: 16px;
                font-size: 14px;
                color: #1c1e21;
                text-align: center;
                max-width: 380px;
                margin-left: auto;
                margin-right: auto;
                line-height: 20px;
            }

            .create-page a {
                color: #1c1e21;
                font-weight: 600;
                text-decoration: none;
                margin-right: 4px;
            }

            .create-page-sub {
                color: #1c1e21;
            }

            /* overlay nền mờ */
            .simple-add-overlay {
                position: fixed;
                inset: 0;
                background: rgba(35, 40, 45, 0.45);
                /* mờ như ảnh */
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 12000;
            }

            /* modal chính */
            .simple-add-modal {
                width: 400px;
                /* kích thước giống ảnh */
                max-width: calc(100% - 40px);
                background: #ffffff;
                border-radius: 3px;
                padding: 18px 22px 20px;
                box-shadow:
                    0 2px 8px rgba(0, 0, 0, 0.08),
                    0 20px 40px rgba(12, 18, 28, 0.25);
                position: relative;
                font-family: "Helvetica Neue", Arial, sans-serif;
            }

            /* header tiêu đề */
            .simple-add-modal h3 {
                margin: 0;
                text-align: center;
                font-size: 20px;
                font-weight: 400;
                color: #111;
                padding-top: 6px;
                padding-bottom: 12px;
            }

            /* nút đóng tròn ở góc (xám tròn, chữ X) */
            .simple-add-close {
                position: absolute;
                right: 12px;
                top: 10px;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: rgba(0, 0, 0, 0.06);
                /* vòng xám nhạt */
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                color: #333;
                cursor: pointer;
                transition: transform .12s ease, background .12s ease;
            }

            .simple-add-close:hover {
                transform: scale(1.03);
                background: rgba(0, 0, 0, 0.08);
            }

            /* form trong modal */
            .simple-add-form {
                padding: 6px 12px 2px;
                margin: 0 -20px;

            }

            .simple-divider {
                display: block;
                height: 0;
                /* không chiếm chiều cao khác */
                border-top: 2px solid #DDDDDD;
                margin: 8px -22px;
                /* margin-top/bottom 16px, kéo ra 2 bên bằng padding modal */
                box-sizing: border-box;
            }

            /* input chung */
            .simple-add-form input[type="text"],
            .simple-add-form input[type="password"],
            .simple-add-form input[type="email"] {
                width: 100%;
                padding: 12px 14px;
                margin: 2px 0 14px;
                border-radius: 8px;
                border: 1px solid #d8dde6;
                font-size: 15px;
                color: #606770;
                background: #fff;
                box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
            }

            /* input focus */
            .simple-add-form input:focus {
                outline: none;
                border-color: #2d7bf6;
                /* viền xanh khi focus */
                box-shadow: 0 0 0 4px rgba(21, 101, 235, 0.06);
            }

            /* checkbox + label */
            /* wrapper */
    /* wrapper */
    /* ===== Facebook-like single checkbox (final) ===== */
    .simple-add-remember {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 8px 0 14px;
    color: #606770;
    font-size: 14px;
    cursor: pointer;
    user-select: none;
    }

    /* native checkbox: ẩn thị giác nhưng vẫn nhận sự kiện
    và nằm CHỒNG LÊN ô hiển thị để chỉ thấy 1 ô duy nhất */
    .simple-add-remember .sa-checkbox {
    position: absolute;
    width: 16px;
    height: 16px;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    margin: 0;
    padding: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 3;
    border: 0;
    background: transparent;
    -webkit-appearance: none;
    appearance: none;
    }

    /* visible custom box */
    .simple-add-remember .cb {
    width: 16px;
    height: 16px;
    min-width: 16px;
    border-radius: 3px;
    background: #fff;
    border: 1px solid #ccd0d5;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
    display: inline-block;
    position: relative;
    transition: background .12s ease, border-color .12s ease, transform .06s ease;
    z-index: 1;
    }

    /* hover visual */
    .simple-add-remember:hover .cb { border-color: #aeb7c2; }

    /* tick (ẩn) */
    .simple-add-remember .cb::after {
    content: "";
    position: absolute;
    left: 4px;
    top: 1px;
    width: 6px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg) scale(0);
    transform-origin: center;
    transition: transform .12s cubic-bezier(.2,.8,.2,1), opacity .12s;
    opacity: 0;
    }

    /* checked: hiện nền xanh + tick */
    .simple-add-remember .sa-checkbox:checked + .cb {
    background: linear-gradient(180deg,#1877f2,#165ecc);
    border-color: rgba(0,0,0,0);
    box-shadow: 0 6px 14px rgba(4,103,229,0.12);
    }
    .simple-add-remember .sa-checkbox:checked + .cb::after {
    transform: rotate(45deg) scale(1);
    opacity: 1;
    }

    /* focus ring (keyboard users) */
    .simple-add-remember .sa-checkbox:focus + .cb {
    box-shadow: 0 0 0 4px rgba(24,119,242,0.10);
    outline: none;
    }

    /* đảm bảo text không bị che bởi input absolute */
    .simple-add-remember .cb-label {
    margin-left: 22px; /* đủ khoảng để text không chồng lên ô */
    line-height: 1;
    color: inherit;
    }




            /* nút Đăng nhập (nổi bật) */
            .simple-add-primary {
                color: #fff;
                font-weight: 700;
                cursor: pointer;
                box-shadow: 0 6px 14px rgba(4, 103, 229, 0.18);
                background-color: #0866ff;
                border: none;
                border-radius: 6px;
                font-family: SFProText-Semibold, Helvetica, Arial, sans-serif;
                font-size: 17px;
                line-height: 40px;
                margin: 0 0 8px;
                padding: 0 16px;
                width: 370px;
            }

            /* link Quên mật khẩu */
            .simple-add-forgot {
                display: block;
                text-align: center;
                margin-top: 12px;
                font-size: 14px;
                color: #0962d4;
                text-decoration: none;
            }

            .simple-add-forgot:hover {
                text-decoration: underline;
            }



            /* chỉnh layout nội dung modal để giống ảnh (khoảng trắng trên/dưới) */
            .simple-add-body {
                padding-top: 2px;
                padding-bottom: 6px;
            }

            /* responsive */
            @media (max-width:560px) {
                .simple-add-modal {
                    width: 96%;
                    padding: 14px;
                }

                .simple-add-close {
                    right: 8px;
                    top: 8px;
                    width: 36px;
                    height: 36px;
                }

                .simple-add-primary {
                    font-size: 16px;
                    padding: 10px;
                }
            }



            @media (max-width:900px) {
                .main-content {
                    flex-direction: column;
                    align-items: center;
                    padding: 40px 20px 20px;
                    gap: 32px;
                }

                .login-left {
                    text-align: center;
                    max-width: 100%;
                }

                .fb-logo {
                    font-size: 48px;
                }

                .login-left p {
                    font-size: 22px;
                    line-height: 28px;
                }

                .fb-logo--small {
                    transform: scale(0.9);
                    font-size: 48px !important;
                }

                .login-right {
                    width: 100%;
                }

                .login-box {
                    width: 100%;
                    max-width: 396px;
                }

                .create-page {
                    margin-top: 14px;
                }

                .slogan.cards {
                    font-size: 28px;
                }

                .slogan.cards .sub {
                    font-size: 13px;
                }
            }
        </style>
    </head>

    <body>

        <div class="main-content">
            <div class="login-left">
                <!-- logo -->
                <h1 class="fb-logo" id="fbLogo">fɑcebook</h1>
                <?php
                $slogan_default = $text['slogan'] ?? 'Facebook giúp bạn kết nối và chia sẻ với mọi người trong cuộc sống của bạn.';
                $slogan_default_sub = $text['slogan_sub'] ?? '';
                $slogan_cards_title = $text['recent_title'] ?? 'Đăng nhập gần đây';
                $slogan_cards_sub = $text['recent_slogan'] ?? 'Nhấp vào ảnh của bạn hoặc thêm tài khoản.';
                ?>
                <p class="slogan default" id="sloganDefault">
                    <?= htmlspecialchars($slogan_default) ?>
                    <?php if ($slogan_default_sub): ?>
                        <span class="sub"><?= htmlspecialchars($slogan_default_sub) ?></span>
                    <?php endif; ?>
                </p>

                <p class="slogan cards" id="sloganCards">
                    <?= htmlspecialchars($slogan_cards_title) ?>
                    <span class="sub"><?= htmlspecialchars($slogan_cards_sub) ?></span>
                </p>

                <!-- modal đăng nhập (ẩn mặc định) -->
                <div id="sa-overlay" class="simple-add-overlay" style="display:none;">
                    <div class="simple-add-modal" role="dialog" aria-modal="true" aria-labelledby="sa-title">
                        <button id="sa-close" class="simple-add-close" title="Đóng">✕</button>
                        <h3 id="sa-title">Đăng nhập Facebook</h3>

                        <div class="simple-add-body">
                            <div id="sa-login-error" class="alert alert-danger py-1 px-2 mb-2" style="display:none;font-size:13px;"></div>
                            <div class="simple-divider"></div>
                            <form class="simple-add-form" onsubmit="return false;">
                                <input id="sa-email" type="text" placeholder="Email hoặc số điện thoại" autocomplete="username">
                                <input id="sa-pass" type="password" placeholder="Mật khẩu" autocomplete="current-password">
                                <label class="simple-add-remember">
                                <input class="sa-checkbox" type="checkbox" name="savepass" value="1">
                                <span class="cb" aria-hidden="true"></span>
                                <div class="cb-label">Nhớ mật khẩu</div>
                                </label>
                                <button class="simple-add-primary" type="submit">Đăng nhập</button>
                            </form>

                            <a href="forgot.php" class="simple-add-forgot">Quên mật khẩu?</a>
                        </div>
                    </div>
                </div>

                <div id="savedAccountWrap" class="accounts" style="margin-top:18px"></div>
            </div>

            <div class="login-right">
                <div class="login-box">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger p-2 mb-3" style="font-size:14px;"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off">
                        <input type="text" name="email" placeholder="<?= htmlspecialchars($text['email_or_phone'] ?? 'Email hoặc số điện thoại') ?>" required>
                        <input type="password" name="pass" placeholder="<?= htmlspecialchars($text['password'] ?? 'Mật khẩu') ?>" required>
                        <button type="submit" name="login" class="btn-login"><?= htmlspecialchars($text['login'] ?? 'Đăng nhập') ?></button>
                    </form>

                    <a href="forgot.php" class="forgot"><?= htmlspecialchars($text['forgot_password'] ?? 'Quên mật khẩu?') ?></a>
                    <div class="divider"></div>
                    <button type="button" class="btn-create" onclick="location.href='register.php'"><?= htmlspecialchars($text['create_new_account'] ?? 'Tạo tài khoản mới') ?></button>
                </div>

                <div class="create-page">
                    <a href="#"><?= htmlspecialchars($text['create_page'] ?? 'Tạo Trang') ?></a>
                    <span class="create-page-sub"><?= htmlspecialchars($text['create_page1'] ?? 'dành cho người nổi tiếng, thương hiệu hoặc doanh nghiệp.') ?></span>
                </div>
            </div>
        </div>

        <script>
            (function() {
                const KEY = 'saved_accounts_v1';
                const LOGOUT_TOOLTIP_KEY = 'show_logout_tooltip_v1';
                const fbLogo = document.getElementById('fbLogo');
                const sloganDefault = document.getElementById('sloganDefault');
                const sloganCards = document.getElementById('sloganCards');
                const loginLeft = document.querySelector('.login-left');

                // default avatar
                const DEFAULT_AVATAR = 'https://scontent.fsgn2-11.fna.fbcdn.net/v/t1.30497-1/453178253_471506465671661_2781666950760530985_n.png?stp=dst-png_s160x160&_nc_cat=1&ccb=1-7&_nc_sid=207b4a&_nc_ohc=_YrXabF6_a4Q7kNvwHChZwQ&_nc_oc=AdlDRJ--0vlW8IqqcwyTpWmsukzJoOAOi1zlFbzf32vPCkEj_D9-B9RX9Xqzfx45vkJJH-Cqint4SLDPns7iCl9j&_nc_zt=24&_nc_ht=scontent.fsgn2-11.fna&oh=00_AfhAoJODDZ_B96ADMufr5faG67W3BLoUDLafJG1bb7-c0g&oe=695279FA';

                function read() {
                    try {
                        return JSON.parse(localStorage.getItem(KEY) || '[]');
                    } catch (e) {
                        return [];
                    }
                }

                function write(v) {
                    localStorage.setItem(KEY, JSON.stringify(v || []));
                }

                function escapeHtml(s) {
                    return String(s || '').replace(/[&"'<>]/g, function(m) {
                        return ({
                            '&': '&amp;',
                            '"': '&quot;',
                            "'": '&#39;',
                            '<': '&lt;',
                            '>': '&gt;'
                        } [m]);
                    });
                }

                // small inline tooltip used on X hover (kept)
                let __inlineTooltip = null;

                function showInlineTooltip(text, anchorElem) {
                    hideInlineTooltip();
                    const t = document.createElement('div');
                    t.className = 'acc-tooltip-inline';
                    t.textContent = text;
                    document.body.appendChild(t);
                    __inlineTooltip = t;
                    // position above anchorElem
                    const ar = anchorElem.getBoundingClientRect();
                    const tr = t.getBoundingClientRect();
                    let left = ar.left + 8;
                    if (left + tr.width > window.innerWidth - 8) left = window.innerWidth - tr.width - 8;
                    if (left < 8) left = 8;
                    let top = ar.top - tr.height - 12;
                    if (top < 8) top = ar.bottom + 12;
                    t.style.left = left + 'px';
                    t.style.top = top + 'px';
                }

                function hideInlineTooltip() {
                    if (__inlineTooltip) {
                        __inlineTooltip.remove();
                        __inlineTooltip = null;
                    }
                }

                // persistent tooltip shown after logout — with close button and persistent state
                let __persistentTooltip = null;

                function showPersistentTooltipOnCard(text, cardElem) {
                    hidePersistentTooltip();

                    if (!cardElem) return;
                    // build tooltip element
                    const t = document.createElement('div');
                    t.className = 'acc-tooltip';
                    t.innerHTML = '<div style="padding-right:28px;">' + escapeHtml(text) + '</div>';
                    // close button
                    const close = document.createElement('div');
                    close.className = 'close-x';
                    close.innerHTML = '✕';
                    t.appendChild(close);

                    document.body.appendChild(t);
                    __persistentTooltip = t;

                    // position above the card (center left area)
                    const ar = cardElem.getBoundingClientRect();
                    const tr = t.getBoundingClientRect();

                    let left = ar.left + 8;
                    if (left + tr.width > window.innerWidth - 8) left = window.innerWidth - tr.width - 8;
                    if (left < 8) left = 8;
                    let top = ar.top - tr.height - 12;
                    if (top < 8) top = ar.bottom + 12;

                    t.style.left = left + 'px';
                    t.style.top = top + 'px';

                    close.addEventListener('click', function(ev) {
                        ev.stopPropagation();
                        hidePersistentTooltip();
                        try {
                            localStorage.setItem(LOGOUT_TOOLTIP_KEY, '0');
                        } catch (e) {}
                    });

                    t.addEventListener('click', function(ev) {
                        ev.stopPropagation();
                    });
                }

                function hidePersistentTooltip() {
                    if (__persistentTooltip) {
                        __persistentTooltip.remove();
                        __persistentTooltip = null;
                    }
                }

                // update header (logo + slogan) based on number of saved cards
                function updateHeaderByCardCount(count) {
                    if (!fbLogo || !sloganDefault || !sloganCards) return;

                    if (count >= 1) {
                        fbLogo.classList.add('fb-logo--small');
                        if (loginLeft) loginLeft.classList.add('shrunk');

                        sloganDefault.style.display = 'none';
                        sloganCards.style.display = 'block';
                    } else {
                        fbLogo.classList.remove('fb-logo--small');
                        if (loginLeft) loginLeft.classList.remove('shrunk');

                        sloganDefault.style.display = 'block';
                        sloganCards.style.display = 'none';
                    }
                }

                // render saved accounts
                function render() {
                    const wrap = document.getElementById('savedAccountWrap');
                    if (!wrap) return;
                    const list = read();
                    wrap.innerHTML = '';

                    list.forEach((acc, idx) => {
                        const card = document.createElement('div');
                        card.className = 'acc-card';

                        const nameElId = 'accName_' + idx;
                        const imgElId = 'accImg_' + idx;

                        const avatarUrl = acc.avatar && acc.avatar.trim() !== '' ? acc.avatar.trim() : DEFAULT_AVATAR;

                        card.innerHTML =
                            '<div class="acc-inner">' +
                            '<div class="acc-avatar">' +
                            '<img id="' + imgElId + '" src="' + escapeHtml(avatarUrl) + '">' +
                            '</div>' +
                            '<div class="acc-name" id="' + nameElId + '">' + escapeHtml(acc.name || acc.email || '') + '</div>' +
                            '</div>';

                        // Sync name/avatar from DB (best-effort). Keeps localStorage up to date.
                        try {
                            if (acc.email) {
                                fetch('../api/user_by_email.php?email=' + encodeURIComponent(acc.email) + '&ajax=1', {
                                    headers: { 'Accept': 'application/json' }
                                })
                                .then(r => r.ok ? r.json() : null)
                                .then(data => {
                                    if (!data || !data.email) return;
                                    const newName = (data.name || '').trim();
                                    const newAvatar = (data.avatar_url || '').trim();

                                    const nameEl = document.getElementById(nameElId);
                                    if (nameEl && newName && nameEl.textContent !== newName) nameEl.textContent = newName;

                                    const imgEl = document.getElementById(imgElId);
                                    if (imgEl && newAvatar) imgEl.src = newAvatar;

                                    // Update storage (only if changed)
                                    const changed = (newName && newName !== (acc.name || '').trim()) || (newAvatar && newAvatar !== (acc.avatar || '').trim());
                                    if (!changed) return;
                                    try {
                                        const KEY = 'saved_accounts_v1';
                                        const cur = JSON.parse(localStorage.getItem(KEY) || '[]') || [];
                                        const next = cur.map(x => {
                                            if (!x || x.email !== acc.email) return x;
                                            return {
                                                ...x,
                                                name: newName || x.name,
                                                avatar: newAvatar || x.avatar
                                            };
                                        });
                                        localStorage.setItem(KEY, JSON.stringify(next));
                                    } catch (e) {}
                                })
                                .catch(() => {});
                            }
                        } catch (e) {}

                        // remove button
                        const btn = document.createElement('button');
                        btn.className = 'remove-btn';
                        btn.textContent = '✕';
                        btn.title = 'Xóa';
                        btn.addEventListener('click', function(ev) {
                            ev.stopPropagation();
                            ev.preventDefault();

                            try {
                                hideInlineTooltip();
                            } catch (e) {}
                            try {
                                hidePersistentTooltip();
                            } catch (e) {}

                            try {
                                localStorage.setItem(LOGOUT_TOOLTIP_KEY, '0');
                            } catch (e) {}

                            remove(acc.email);
                        });

                        btn.addEventListener('mouseenter', function(ev) {
                            const message = "Lần tới bạn đăng nhập, hãy nhấp vào ảnh của mình. Để gỡ tài khoản khỏi trang này, hãy nhấp vào đây.";
                            showInlineTooltip(message, btn);
                        });
                        btn.addEventListener('mouseleave', function() {
                            hideInlineTooltip();
                        });

                        card.appendChild(btn);

                        card.addEventListener('click', function() {
                            select(acc);
                        });

                        card.addEventListener('mouseleave', function() {
                            hideInlineTooltip();
                        });

                        wrap.appendChild(card);
                    });

                    // Only show "Add account" if there is at least one saved account
                    if (list.length > 0) {
                        const add = document.createElement('div');
                        add.className = 'acc-card add-card';
                        add.innerHTML = '<div class="acc-inner"><div class="acc-avatar"><div style="width:48px;height:48px;border-radius:50%;background:#1877f2;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px">+</div></div><div class="acc-name">Thêm tài khoản</div></div>';
                        // do not attach click here; event delegation will handle clicks
                        wrap.appendChild(add);
                    }

                    updateHeaderByCardCount(list.length);

                    try {
                        const showFlag = localStorage.getItem(LOGOUT_TOOLTIP_KEY);
                        if (showFlag === '1') {
                            const firstCard = document.querySelector('#savedAccountWrap .acc-card');
                            if (firstCard) {
                                showPersistentTooltipOnCard("Lần tới bạn đăng nhập, hãy nhấp vào ảnh của mình. Để gỡ tài khoản khỏi trang này, hãy nhấp vào đây.", firstCard);
                            }
                        } else {
                            hidePersistentTooltip();
                        }
                    } catch (e) {
                        // ignore storage errors
                    }
                }

                function remove(email) {
                    const arr = read().filter(a => a.email !== email);
                    write(arr);

                    try {
                        hideInlineTooltip();
                    } catch (e) {}
                    try {
                        hidePersistentTooltip();
                    } catch (e) {}

                    try {
                        localStorage.setItem(LOGOUT_TOOLTIP_KEY, '0');
                    } catch (e) {}

                    (function updateHeaderByCardCountLocal() {
                        try {
                            const wrap = document.getElementById('savedAccountWrap');
                            const fbLogo = document.getElementById('fbLogo');
                            const loginLeft = document.querySelector('.login-left');
                            const sloganDefault = document.querySelector('.slogan.default');
                            const sloganCards = document.querySelector('.slogan.cards');

                            if (!wrap || !loginLeft || !fbLogo) return;

                            const list = JSON.parse(localStorage.getItem('saved_accounts_v1') || '[]');
                            const count = Array.isArray(list) ? list.length : 0;

                            if (count > 1) {
                                fbLogo.classList.add('fb-logo--small');
                                loginLeft.classList.add('shrunk');
                                if (sloganDefault) sloganDefault.style.display = 'none';
                                if (sloganCards) sloganCards.style.display = 'block';
                            } else {
                                fbLogo.classList.remove('fb-logo--small');
                                loginLeft.classList.remove('shrunk');
                                if (sloganDefault) sloganDefault.style.display = 'block';
                                if (sloganCards) sloganCards.style.display = 'none';
                            }
                        } catch (e) {
                            // ignore
                        }
                    })();

                    render();
                }

                function select(acc) {
                    const emailInput = document.querySelector('input[name="email"]');
                    const passInput = document.querySelector('input[name="pass"]');
                    if (emailInput) {
                        emailInput.value = acc.email;
                        if (passInput) passInput.focus();
                    }
                }

                // ----- Modal handlers & delegation (clean replacement) -----
                (function modalInit() {
                    const overlay = document.getElementById('sa-overlay');
                    if (!overlay) return;

                    const closeBtn = document.getElementById('sa-close');
                    const cancelBtn = document.getElementById('sa-cancel');
                    const saveBtn = document.getElementById('sa-save');
                    const emailInput = document.getElementById('sa-email');
                    const passInput = document.getElementById('sa-pass');
                    const nameInput = document.getElementById('sa-name');
                    const avatarInput = document.getElementById('sa-avatar');
                    const preview = document.getElementById('sa-avatar-preview');
                    const loginErr = document.getElementById('sa-login-error');
                    const modalForm = overlay.querySelector('.simple-add-form');
                    const rememberCheckbox = overlay.querySelector('input[name="savepass"]');

                    function safeGet(id) {
                        return document.getElementById(id) || null;
                    }

                    function setLoginError(msg) {
                        if (!loginErr) return;
                        if (!msg) {
                            loginErr.textContent = '';
                            loginErr.style.display = 'none';
                            return;
                        }
                        loginErr.textContent = msg;
                        loginErr.style.display = 'block';
                    }

                    function submitLoginFromModal() {
                        const email = (emailInput && emailInput.value ? emailInput.value : '').trim();
                        const pass = (passInput && passInput.value ? passInput.value : '');
                        const remember = !!(rememberCheckbox && rememberCheckbox.checked);

                        setLoginError('');

                        if (!email) {
                            setLoginError('Vui lòng nhập email hoặc số điện thoại.');
                            if (emailInput) emailInput.focus();
                            return;
                        }
                        if (!pass) {
                            setLoginError('Vui lòng nhập mật khẩu.');
                            if (passInput) passInput.focus();
                            return;
                        }

                        // Reuse the existing login form on the right.
                        const mainForm = document.querySelector('.login-right .login-box form');
                        const mainEmail = document.querySelector('.login-right .login-box input[name="email"]');
                        const mainPass = document.querySelector('.login-right .login-box input[name="pass"]');
                        if (!mainForm || !mainEmail || !mainPass) {
                            setLoginError('Không tìm thấy form đăng nhập.');
                            return;
                        }

                        mainEmail.value = email;
                        mainPass.value = pass;

                        // Ensure "login" flag exists in POST
                        let loginFlag = mainForm.querySelector('input[name="login"]');
                        if (!loginFlag) {
                            loginFlag = document.createElement('input');
                            loginFlag.type = 'hidden';
                            loginFlag.name = 'login';
                            loginFlag.value = '1';
                            mainForm.appendChild(loginFlag);
                        } else {
                            loginFlag.value = '1';
                        }

                        // Mirror remember checkbox into main form
                        let rememberHidden = mainForm.querySelector('input[name="savepass"]');
                        if (remember) {
                            if (!rememberHidden) {
                                rememberHidden = document.createElement('input');
                                rememberHidden.type = 'hidden';
                                rememberHidden.name = 'savepass';
                                rememberHidden.value = '1';
                                mainForm.appendChild(rememberHidden);
                            } else {
                                rememberHidden.value = '1';
                            }
                        } else if (rememberHidden) {
                            rememberHidden.remove();
                        }

                        mainForm.submit();
                    }

                    function openModal() {
                        overlay.style.display = 'flex';
                        overlay.setAttribute('aria-hidden', 'false');
                        document.body.style.overflow = 'hidden';
                        if (emailInput) emailInput.focus();
                    }

                    function closeModal() {
                        overlay.style.display = 'none';
                        overlay.setAttribute('aria-hidden', 'true');
                        document.body.style.overflow = '';
                        setLoginError('');
                        if (emailInput) emailInput.value = '';
                        if (passInput) passInput.value = '';
                        if (nameInput) nameInput.value = '';
                        if (avatarInput) avatarInput.value = '';
                        if (preview) {
                            preview.style.display = 'none';
                            preview.src = '';
                        }
                        const err = safeGet('sa-email-error');
                        if (err) err.textContent = '';
                        if (saveBtn) saveBtn.disabled = true;
                    }

                    const isEmail = s => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s);
                    const isPhone = s => /^[+\d][\d\s\-]{5,}$/.test(s);

                    function validate() {
                        const err = safeGet('sa-email-error');
                        if (!emailInput) return false;
                        const v = emailInput.value.trim();
                        if (!v) {
                            if (err) err.textContent = 'Vui lòng nhập email hoặc số điện thoại.';
                            if (saveBtn) saveBtn.disabled = true;
                            return false;
                        }
                        if (!(isEmail(v) || isPhone(v))) {
                            if (err) err.textContent = 'Không phải email hay số điện thoại hợp lệ.';
                            if (saveBtn) saveBtn.disabled = true;
                            return false;
                        }
                        if (err) err.textContent = '';
                        if (saveBtn) saveBtn.disabled = false;
                        return true;
                    }

                    if (emailInput) emailInput.addEventListener('input', validate);
                    if (avatarInput) avatarInput.addEventListener('input', () => {
                        const url = avatarInput.value.trim();
                        if (!preview) return;
                        if (!url) {
                            preview.style.display = 'none';
                            preview.src = '';
                            return;
                        }
                        preview.src = url;
                        preview.style.display = 'inline-block';
                        preview.onerror = () => preview.style.display = 'none';
                        preview.onload = () => preview.style.display = 'inline-block';
                    });

                    if (closeBtn) closeBtn.addEventListener('click', closeModal);
                    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
                    overlay.addEventListener('click', (e) => {
                        if (e.target === overlay) closeModal();
                    });
                    overlay.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape') closeModal();
                        if (e.key === 'Enter' && saveBtn && !saveBtn.disabled) {
                            e.preventDefault();
                            saveBtn.click();
                        }
                    });

                    if (modalForm) {
                        modalForm.addEventListener('submit', function(ev) {
                            ev.preventDefault();
                            submitLoginFromModal();
                        });
                    }

                    if (saveBtn) {
                        saveBtn.addEventListener('click', () => {
                            if (!validate()) return;
                            const email = emailInput.value.trim();
                            const name = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : email;
                            const avatar = (avatarInput && avatarInput.value.trim()) ? avatarInput.value.trim() : '';

                            let arr = [];
                            try {
                                arr = Array.isArray(read()) ? read() : [];
                            } catch (e) {
                                arr = [];
                            }
                            const exists = arr.some(a => (a.email || '').toLowerCase() === email.toLowerCase());
                            if (exists) {
                                const err = safeGet('sa-email-error');
                                if (err) err.textContent = 'Tài khoản này đã tồn tại.';
                                return;
                            }
                            arr.push({
                                email,
                                name,
                                avatar
                            });
                            try {
                                write(arr);
                            } catch (e) {
                                console.warn('write failed', e);
                            }
                            try {
                                render();
                            } catch (e) {}
                            closeModal();
                        });
                    }

                    // event delegation: handle clicks on dynamically created .add-card
                    const wrap = document.getElementById('savedAccountWrap');
                    if (wrap) {
                        wrap.addEventListener('click', function(ev) {
                            const card = ev.target.closest && ev.target.closest('.add-card');
                            if (card) openModal();
                        });
                    }

                    // attach to any static open button if exists
                    const staticOpen = document.getElementById('openAddBtn');
                    if (staticOpen) staticOpen.addEventListener('click', openModal);

                    // ensure modal hidden initially
                    overlay.style.display = 'none';
                    overlay.setAttribute('aria-hidden', 'true');
                })();

                // expose helper so you can set the logout flag from other pages (e.g. logout page)
                window.triggerLogoutTooltipNextVisit = function() {
                    try {
                        localStorage.setItem(LOGOUT_TOOLTIP_KEY, '1');
                    } catch (e) {}
                };

                window.savedAccounts = {
                    read,
                    write,
                    render,
                    remove,
                    select
                };

                // run render once DOM is ready
                document.addEventListener('DOMContentLoaded', render);
            })();
        </script>

        <?php if ($js_save_and_redirect):
            $e_email = json_encode($saved_email);
            $e_name = json_encode($saved_name);
            $e_avatar = json_encode($saved_avatar);
        ?>
            <script>
                (function() {
                    try {
                        const KEY = 'saved_accounts_v1';
                        const email = <?= $e_email ?>;
                        const name = <?= $e_name ?>;
                        const avatar = <?= $e_avatar ?>;
                        const existing = JSON.parse(localStorage.getItem(KEY) || '[]');
                        if (!existing.some(a => a.email === email)) {
                            existing.push({
                                email: email,
                                name: name,
                                avatar: avatar
                            });
                            localStorage.setItem(KEY, JSON.stringify(existing));
                        }
                    } catch (e) {
                        // ignore
                    }
                    // redirect đến trang home
                    window.location.href = '../pages/home.php';
                })();
            </script>
        <?php endif; ?>

        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </body>

    </html>