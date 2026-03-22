<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

// Nếu user đã đăng nhập (đã xác thực xong) thì chuyển thẳng về Home
if (Database::isLoggedIn()) {
    header('Location: ../pages/home.php');
    exit;
}

/* --- cấu hình ngôn ngữ --- */
$allowed_langs = ['vi', 'en', 'zh', 'kr', 'jp', 'fr', 'th', 'es', 'pt', 'de', 'it', 'ru', 'ja', 'ko', 'ar', 'nl', 'sv', 'pl', 'tr', 'id', 'hi'];

/* nếu user click ?lang=xx -> lưu vào session trước khi xuất HTML */
if (isset($_GET['lang'])) {
    $langParam = $_GET['lang'];
    if (in_array($langParam, $allowed_langs, true)) {
        $_SESSION['lang'] = $langParam;
    }
    // redirect để xóa param lang khỏi URL
    $current = $_SERVER['REQUEST_URI'];
    $parts = parse_url($current);
    $path = $parts['path'] ?? '/';
    parse_str($parts['query'] ?? '', $q);
    unset($q['lang']);
    $qs = http_build_query($q);
    $newUrl = $path . ($qs ? '?' . $qs : '');
    header('Location: ' . $newUrl);
    exit;
}

$lang = $_SESSION['lang'] ?? 'vi';

/* --- Mảng chuỗi (đã mở rộng các key cần thiết) --- */
$text = [
    "vi" => [
        "title" => "Nhập mã từ email của bạn",
        "desc" => "Để đảm bảo đây chính là email của bạn, hãy nhập mã mà chúng tôi đã gửi qua email đến",
        "continue" => "Tiếp tục",
        "update" => "Cập nhật thông tin liên hệ",
        "resend" => "Gửi lại email",
        "resent_msg" => "Mã đã được gửi lại.",
        "modal_title" => "Thêm địa chỉ email hoặc số di động",
        "modal_cancel" => "Hủy",
        "modal_add" => "Thêm",
        "logout_title" => "Đăng xuất khỏi Facebook?",
        "logout_desc" => "Bạn có chắc chắn muốn đăng xuất khỏi Facebook trước khi xác nhận email không? Việc xác nhận email trên tài khoản đảm bảo rằng bạn sẽ có thể đăng nhập lại.",
        "logout_cancel" => "Xác nhận tài khoản",
        "logout_confirm" => "Đăng xuất",
        "footer_about" => "Giới thiệu",
        "footer_privacy" => "Chính sách quyền riêng tư",
        "footer_cookie" => "Cookie",
        "footer_adchoices" => "Lựa chọn quảng cáo",
        "footer_terms" => "Điều khoản",
        "footer_help" => "Trợ giúp",
        "language_modal_title" => "Chọn ngôn ngữ của bạn",
        "language_search_placeholder" => "Tìm ngôn ngữ...",
        "close_btn_text" => "Đóng",
        "input_placeholder" => "Email hoặc số di động mới",
    ],
    "en" => [
        "title" => "Enter the code from your email",
        "desc" => "To confirm this is your email, enter the code we sent to",
        "continue" => "Continue",
        "update" => "Update contact information",
        "resend" => "Resend email",
        "resent_msg" => "A new code has been sent.",
        "modal_title" => "Add email address or mobile number",
        "modal_cancel" => "Cancel",
        "modal_add" => "Add",
        "logout_title" => "Log out of Facebook?",
        "logout_desc" => "Are you sure you want to log out of Facebook before confirming your email? Confirming your email ensures you can sign back in.",
        "logout_cancel" => "Confirm account",
        "logout_confirm" => "Log out",
        "footer_about" => "About",
        "footer_privacy" => "Privacy Policy",
        "footer_cookie" => "Cookie",
        "footer_adchoices" => "Ad Choices",
        "footer_terms" => "Terms",
        "footer_help" => "Help",
        "language_modal_title" => "Choose your language",
        "language_search_placeholder" => "Search languages...",
        "close_btn_text" => "Close",
    ],
    "zh" => [
        "title" => "輸入您電子郵件中的驗證碼",
        "desc" => "請輸入我們寄到您電子郵件的驗證碼",
        "continue" => "繼續",
        "update" => "更新聯絡資訊",
        "resend" => "重新寄送電子郵件",
        "resent_msg" => "驗證碼已重新寄送。",
        "modal_title" => "新增電子郵件或手機號碼",
        "modal_cancel" => "取消",
        "modal_add" => "新增",
        "logout_title" => "要從 Facebook 登出嗎？",
        "logout_desc" => "您確定要在確認電子郵件之前登出 Facebook 嗎？確認電子郵件可確保您能再次登入。",
        "logout_cancel" => "確認帳號",
        "logout_confirm" => "登出",
        "footer_about" => "關於",
        "footer_privacy" => "隱私政策",
        "footer_cookie" => "Cookie",
        "footer_adchoices" => "廣告選擇",
        "footer_terms" => "條款",
        "footer_help" => "說明",
        "language_modal_title" => "選擇語言",
        "language_search_placeholder" => "搜尋語言...",
        "close_btn_text" => "關閉",
    ],
    "kr" => [
        "title" => "이메일로 받은 코드를 입력하세요",
        "desc" => "이메일로 보낸 코드를 입력하세요",
        "continue" => "계속",
        "update" => "연락처 정보 업데이트",
        "resend" => "이메일 다시 보내기",
        "resent_msg" => "코드가 다시 전송되었습니다.",
        "modal_title" => "이메일 또는 휴대폰 번호 추가",
        "modal_cancel" => "취소",
        "modal_add" => "추가",
        "logout_title" => "Facebook에서 로그아웃하시겠습니까?",
        "logout_desc" => "이메일을 확인하기 전에 Facebook에서 로그아웃하시겠습니까? 이메일 확인은 다시 로그인할 수 있도록 도와줍니다.",
        "logout_cancel" => "계정 확인",
        "logout_confirm" => "로그아웃",
        "footer_about" => "소개",
        "footer_privacy" => "개인정보처리방침",
        "footer_cookie" => "쿠키",
        "footer_adchoices" => "광고 설정",
        "footer_terms" => "약관",
        "footer_help" => "도움말",
        "language_modal_title" => "언어 선택",
        "language_search_placeholder" => "언어 검색...",
        "close_btn_text" => "닫기",
    ],
    "jp" => [ /* Japanese (jp) kept for compatibility */
        "title" => "メールに送信されたコードを入力してください",
        "desc" => "メールに送信したコードを入力してください",
        "continue" => "続行",
        "update" => "連絡先情報を更新",
        "resend" => "メールを再送する",
        "resent_msg" => "コードを再送しました。",
        "modal_title" => "メールアドレスまたは携帯番号を追加",
        "modal_cancel" => "キャンセル",
        "modal_add" => "追加",
        "logout_title" => "Facebook からログアウトしますか？",
        "logout_desc" => "メールを確認する前に Facebook からログアウトしますか？メール確認は再ログインできるようにします。",
        "logout_cancel" => "アカウントを確認",
        "logout_confirm" => "ログアウト",
        "footer_about" => "概要",
        "footer_privacy" => "プライバシーポリシー",
        "footer_cookie" => "Cookie",
        "footer_adchoices" => "広告の選択",
        "footer_terms" => "利用規約",
        "footer_help" => "ヘルプ",
        "language_modal_title" => "言語を選択",
        "language_search_placeholder" => "言語を検索...",
        "close_btn_text" => "閉じる",
    ],
    /* For brevity: other languages reuse similar keys — you can expand/adjust later */
    "fr" => [
        "title" => "Entrez le code envoyé à votre e-mail",
        "desc" => "Veuillez saisir le code que nous avons envoyé à votre e-mail",
        "continue" => "Continuer",
        "update" => "Mettre à jour les informations de contact",
        "resend" => "Renvoyer l'e-mail",
        "resent_msg" => "Un nouveau code a été envoyé.",
        "modal_title" => "Ajouter un e-mail ou un numéro de téléphone",
        "modal_cancel" => "Annuler",
        "modal_add" => "Ajouter",
        "logout_title" => "Se déconnecter de Facebook ?",
        "logout_desc" => "Êtes-vous sûr de vouloir vous déconnecter avant de confirmer votre e-mail ? La confirmation permet de vous reconnecter.",
        "logout_cancel" => "Confirmer le compte",
        "logout_confirm" => "Se déconnecter",
        "footer_about" => "À propos",
        "footer_privacy" => "Politique de confidentialité",
        "footer_cookie" => "Cookie",
        "footer_adchoices" => "Choix des annonces",
        "footer_terms" => "Conditions",
        "footer_help" => "Aide",
        "language_modal_title" => "Choisissez votre langue",
        "language_search_placeholder" => "Rechercher une langue...",
        "close_btn_text" => "Fermer",
    ],
    "th" => [
        "title" => "กรอกรหัสจากอีเมลของคุณ",
        "desc" => "โปรดกรอกรหัสที่เราส่งไปยังอีเมลของคุณ",
        "continue" => "ดำเนินการต่อ",
        "update" => "อัปเดตข้อมูลติดต่อ",
        "resend" => "ส่งอีเมลอีกครั้ง",
        "resent_msg" => "เราได้ส่งรหัสใหม่แล้ว",
        "modal_title" => "เพิ่มอีเมลหรือหมายเลขโทรศัพท์",
        "modal_cancel" => "ยกเลิก",
        "modal_add" => "เพิ่ม",
        "logout_title" => "ต้องการออกจาก Facebook หรือไม่?",
        "logout_desc" => "คุณแน่ใจไหมว่าต้องการออกจาก Facebook ก่อนยืนยันอีเมล การยืนยันอีเมลจะช่วยให้คุณลงชื่อเข้าใช้ได้อีกครั้ง",
        "logout_cancel" => "ยืนยันบัญชี",
        "logout_confirm" => "ออกจากระบบ",
        "footer_about" => "เกี่ยวกับ",
        "footer_privacy" => "นโยบายความเป็นส่วนตัว",
        "footer_cookie" => "คุกกี้",
        "footer_adchoices" => "ตัวเลือกโฆษณา",
        "footer_terms" => "ข้อกำหนด",
        "footer_help" => "ช่วยเหลือ",
        "language_modal_title" => "เลือกภาษา",
        "language_search_placeholder" => "ค้นหาภาษา...",
        "close_btn_text" => "ปิด",

    ],

];

/* đảm bảo tồn tại $t (fallback về vi) */
$t = $text[$lang] ?? $text['vi'];

/* helper build url giữ param khác */
function build_lang_url(string $langCode): string
{
    $current = $_SERVER['REQUEST_URI'];
    $parts = parse_url($current);
    $path = $parts['path'] ?? '/';
    parse_str($parts['query'] ?? '', $q);
    $q['lang'] = $langCode;
    return $path . '?' . http_build_query($q);
}

/* --- kiểm tra register email --- */
if (!isset($_SESSION['register_data']) || empty($_SESSION['register_data']['email'])) {
    header("Location: register.php");
    exit;
}
$err = $_SESSION['verify_error'] ?? null;
unset($_SESSION['verify_error']);
$email = htmlspecialchars($_SESSION['register_data']['email']);
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($t['title']) ?></title>
    <link rel="stylesheet" href="../assets/css/verify.css">
</head>

<body>
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-inner">
            <div class="logo-circle" aria-hidden="true">
                <span class="f-icon">f</span>
            </div>

            <div class="account-area">
                <button id="accountBtn" class="account-btn" aria-expanded="false" aria-label="<?= htmlspecialchars($t['logout_confirm'] ?? 'Tài khoản') ?>">
                    <span class="chev">▾</span>
                </button>

                <div id="accountDropdown" class="account-dropdown" role="menu" aria-hidden="true">
                    <a id="logoutLink" href="logout.php"><?= htmlspecialchars($t['logout_confirm'] ?? 'Đăng xuất') ?></a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($err): ?>
        <div class="error-banner" role="alert"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <div class="wrap">
        <div class="card" role="region" aria-labelledby="verify-title">
            <div class="card-body">
                <div class="tt">
                    <h2 id="verify-title" class="title"><?= htmlspecialchars($t['title']) ?></h2>
                </div>

                <p class="desc">
                    <?= htmlspecialchars($t['desc']) ?> <strong><?= $email ?></strong>.
                </p>

                <div class="center-row" aria-hidden="false">
                    <div class="left">
                        <form id="verifyForm" method="POST" action="complete_register.php" autocomplete="off" novalidate>
                            <div id="inputBox" class="input-box" aria-live="polite">
                                <div class="input-prefix">FB-</div>
                                <input id="code" name="code" class="code-input" type="text" inputmode="numeric" maxlength="6" placeholder="" />
                            </div>

                            <div class="row-actions">
                                <button type="button" id="resendBtn" class="link" aria-live="polite"><?= htmlspecialchars($t['resend']) ?></button>
                                <span id="resendMsg" class="muted small"></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <div>
                    <button class="btn ghost" id="updateContactBtn" type="button"><?= htmlspecialchars($t['update']) ?></button>
                </div>
                <div>
                    <button class="btn primary" id="continueBtn" disabled><?= htmlspecialchars($t['continue']) ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: cập nhật email/phone -->
    <div id="contactModal" class="modal" aria-hidden="true">
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <div class="modal-header">
                <h3 id="modalTitle"><?= htmlspecialchars($t['modal_title'] ?? '') ?></h3>
                <button class="modal-close" id="modalClose" aria-label="<?= htmlspecialchars($t['close_btn_text'] ?? 'Đóng') ?>">&times;</button>
            </div>
            <form id="contactForm" class="modal-body" method="POST" action="../actions/update_contact.php">
                <div class="floating-wrap">
                    <input type="text" name="new_contact" id="newContact" placeholder="<?= htmlspecialchars($t['input_placeholder'] ?? 'Email hoặc số di động mới') ?>">
                </div>
            </form>
            <div class="modal-footer">
                <button class="btn ghost" id="modalCancel" type="button"><?= htmlspecialchars($t['modal_cancel'] ?? 'Hủy') ?></button>
                <button class="btn primary" id="modalAdd" type="button"><?= htmlspecialchars($t['modal_add'] ?? 'Thêm') ?></button>
            </div>
        </div>
    </div>

    <!-- Logout Confirm Modal -->
    <div id="logoutConfirmOverlay" class="logout-modal-overlay" aria-hidden="true" role="dialog" aria-labelledby="logoutConfirmTitle">
        <div class="logout-modal" role="document" aria-modal="true">
            <button id="logoutModalClose" class="modal-close-round" aria-label="<?= htmlspecialchars($t['close_btn_text'] ?? 'Đóng') ?>">✕</button>

            <div class="modal-inner">
                <h3 id="logoutConfirmTitle" class="modal-title"><?= htmlspecialchars($t['logout_title'] ?? '') ?></h3>
                <p class="modal-desc">
                    <?= htmlspecialchars($t['logout_desc'] ?? '') ?>
                </p>
            </div>

            <div class="modal-footer">
                <button id="logoutCancelBtn" class="btn-linkish" type="button"><?= htmlspecialchars($t['logout_cancel'] ?? '') ?></button>
                <button id="logoutConfirmBtn" class="btn-primary-confirm" type="button"><?= htmlspecialchars($t['logout_confirm'] ?? '') ?></button>
            </div>
        </div>
    </div>

    <footer id="pageFooter" class="fb-footer">
        <div class="footer-row links">
            <a href="#"><?= htmlspecialchars($t['footer_about'] ?? 'Giới thiệu') ?></a>
            <a href="#"><?= htmlspecialchars($t['footer_privacy'] ?? 'Chính sách quyền riêng tư') ?></a>
            <a href="#"><?= htmlspecialchars($t['footer_cookie'] ?? 'Cookie') ?></a>
            <a href="#"><?= htmlspecialchars($t['footer_adchoices'] ?? 'Lựa chọn quảng cáo') ?>
                <span class="ad-choices-icon-tooltip">
                    <i class="img sp_GPvE0syHYuh sx_7d98b4"></i>
                </span></a>
            <a href="#"><?= htmlspecialchars($t['footer_terms'] ?? 'Điều khoản') ?></a>
            <a href="#"><?= htmlspecialchars($t['footer_help'] ?? 'Trợ giúp') ?></a>
        </div>

        <div class="footer-meta">
            <span>Meta © <?= date('Y') ?></span>
        </div>

        <div class="footer-row langs">
            <span class="lang">
                <a href="<?= htmlspecialchars(build_lang_url('vi')) ?>" class="language-item py-2 text-dark <?= $lang === 'vi' ? 'fw-bold text-primary' : '' ?>">Tiếng Việt <?= $lang === 'vi' ? '✓' : '' ?></a>
            </span>

            <!-- Các link chuyển ngôn ngữ: luôn dùng build_lang_url để giữ param khác -->
            <a href="<?= htmlspecialchars(build_lang_url('en')) ?>" class="footer-link">English</a>
            <a href="<?= htmlspecialchars(build_lang_url('zh')) ?>" class="footer-link">中文(台灣)</a>
            <a href="<?= htmlspecialchars(build_lang_url('kr')) ?>" class="footer-link">한국어</a>
            <a href="<?= htmlspecialchars(build_lang_url('jp')) ?>" class="footer-link">日本語</a>
            <a href="<?= htmlspecialchars(build_lang_url('fr')) ?>" class="footer-link">Français</a>
            <a href="<?= htmlspecialchars(build_lang_url('th')) ?>" class="footer-link">ภาษาไทย</a>
            <a href="<?= htmlspecialchars(build_lang_url('es')) ?>" class="footer-link">Español</a>
            <a href="<?= htmlspecialchars(build_lang_url('pt')) ?>" class="footer-link">Português</a>
            <a href="<?= htmlspecialchars(build_lang_url('de')) ?>" class="footer-link">Deutsch</a>
            <a href="<?= htmlspecialchars(build_lang_url('it')) ?>" class="footer-link">Italiano</a>
            <button type="button" class="more-btn" data-bs-toggle="modal" data-bs-target="#languageModal" aria-controls="languageModal" aria-haspopup="dialog">+</button>
        </div>

        <div class="modal fade" id="languageModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-0">
                        <h5 class="modal-title fw-bold fs-5"><?= htmlspecialchars($t['language_modal_title'] ?? 'Chọn ngôn ngữ của bạn') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlspecialchars($t['close_btn_text'] ?? 'Đóng') ?>"></button>
                    </div>

                    <div class="modal-body pt-0">
                        <div class="px-3 mb-3">
                            <input type="text" class="form-control form-control-sm" placeholder="<?= htmlspecialchars($t['language_search_placeholder'] ?? 'Tìm ngôn ngữ...') ?>" id="languageSearch">
                        </div>

                        <div class="row g-3" id="languageList">
                            <div class="col-12">
                                <strong class="small text-muted">Ngôn ngữ được đề xuất</strong>
                            </div>
                            <div class="d-flex flex-wrap gap-3 mb-2 small">
                                <?php
                                $popular = ['vi' => 'Tiếng Việt', 'en' => 'English (UK)', 'zh' => '中文(台灣)', 'kr' => '한국어', 'jp' => '日本語', 'es' => 'Español', 'pt' => 'Português'];
                                foreach ($popular as $code => $label): ?>
                                    <a href="<?= htmlspecialchars(build_lang_url($code)) ?>" class="language-item py-2 text-dark <?= $lang === $code ? 'fw-bold text-primary' : '' ?>">
                                        <?= htmlspecialchars($label) ?> <?= $lang === $code ? '✓' : '' ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <div class="col-12 mt-3">
                                <hr class="my-2">
                            </div>
                            <div class="col-12"><strong class="small text-muted">Tất cả ngôn ngữ</strong></div>

                            <div class="d-flex flex-wrap gap-3 mb-2 small" id="languageListInline">
                                <?php
                                // hiển thị tất cả (dùng $allowed_langs)
                                $names = [
                                    'vi' => 'Tiếng Việt',
                                    'en' => 'English',
                                    'zh' => '中文',
                                    'kr' => '한국어',
                                    'jp' => '日本語',
                                    'fr' => 'Français',
                                    'th' => 'ภาษาไทย',
                                    'es' => 'Español',
                                    'pt' => 'Português',
                                    'de' => 'Deutsch',
                                    'it' => 'Italiano',
                                    'ru' => 'Русский',
                                    'ko' => '한국어',
                                    'ar' => 'العربية',
                                    'nl' => 'Nederlands',
                                    'sv' => 'Svenska',
                                    'pl' => 'Polski',
                                    'tr' => 'Türkçe',
                                    'id' => 'Bahasa Indonesia',
                                    'hi' => 'हिन्दी'
                                ];
                                foreach ($allowed_langs as $code) {
                                    $label = $names[$code] ?? $code;
                                    echo '<a href="' . htmlspecialchars(build_lang_url($code)) . '" class="language-item py-1 text-dark">' . htmlspecialchars($label) . '</a>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 justify-content-center">
                        <button type="button" class="btn btn-primary px-5" data-bs-dismiss="modal"><?= htmlspecialchars($t['close_btn_text'] ?? 'Đóng') ?></button>
                    </div>
                </div>
            </div>
        </div>

    </footer>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/verify.js"></script>
</body>

</html>