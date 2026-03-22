<?php
require '../includes/db.php';
$pageTitle = 'Facebook';
$extraHead = '<link rel="icon" href="../uploads/fb_logo.jpg">';

require '../includes/header.php';

if (!Database::isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

$user = $current_user;
$user_id = (int)($user['id'] ?? 0);

function fb_cover_url(?string $cover): string
{
    $cover = trim((string)$cover);
    if ($cover === '') return '../assets/images/default-cover.jpg';
    if (preg_match('~^https?://~i', $cover)) return $cover;
    if (strlen($cover) > 0 && $cover[0] === '/') return $cover;

    $uploadsPath = __DIR__ . '/../uploads/' . $cover;
    if (is_file($uploadsPath)) return '../uploads/' . rawurlencode($cover);
    $assetsPath = __DIR__ . '/../assets/images/' . $cover;
    if (is_file($assetsPath)) return '../assets/images/' . rawurlencode($cover);
    return '../uploads/' . rawurlencode($cover);
}

// === LẤY BÀI VIẾT CỦA USER (NHIỀU DÒNG) ===
$posts = Database::GetData("
    SELECT 
        p.id, p.content, p.image, p.created_at,
        u.name, u.avatar,
        COUNT(l.user_id) AS like_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN likes l ON l.post_id = p.id
    WHERE p.user_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
", [$user_id]);

// === LẤY SỐ BẠN BÈ ===
$friend_count = Database::GetRow("
    SELECT COUNT(*) AS total 
    FROM friends 
    WHERE user_id = ? OR friend_id = ?
", [$user_id, $user_id])['total'] ?? 0;

// === GỢI Ý KẾT BẠN (5 NGƯỜI NGẪU NHIÊN) ===
$suggestions = Database::GetData("
    SELECT id, name, avatar 
    FROM users 
    WHERE id != ?
    AND id NOT IN (
        SELECT friend_id FROM friends WHERE user_id = ?
        UNION
        SELECT user_id FROM friends WHERE friend_id = ?
    )
    ORDER BY RAND()
    LIMIT 5
", [$user_id, $user_id, $user_id]);

$hasCustomCover = false;
$coverValue = trim((string)($user['cover'] ?? ''));
if ($coverValue !== '' && $coverValue !== 'default-cover.jpg') {
    $hasCustomCover = true;
}
?>

<style>
    /* Base typography behavior (similar to FB's system-fonts rules, but using our own structure) */
    .profile-page {
        -webkit-font-smoothing: antialiased;
        overscroll-behavior-y: none;
        font-size: 15px;
    }

    .profile-page :where(div, span, a, h1, h2, h3, h4, h5, h6, p, button, input, label, select, td, textarea) {
        font-family: inherit;
    }

    .profile-page {
        flex: 1;
        min-width: 0;
    }

    .profile-shell {
        width: 100%;
        max-width: 900px;
        margin: 0 auto;
        padding: 0 10px;
    }

    .profile-top {
        background: var(--app-surface-bg);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        margin-bottom: 12px;
    }

    .profile-cover {
        height: 310px;
        background: var(--app-icon-bg);
        position: relative;
    }

    .cover-edit {
        position: absolute;
        right: 12px;
        bottom: 12px;
        z-index: 3;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .cover-edit .btn {
        background: rgba(255, 255, 255, 0.88);
        border-color: var(--app-border, rgba(0,0,0,0.10));
        color: #050505;
        backdrop-filter: blur(4px);
    }

    :where(html, body)[data-theme="dark"] .cover-edit .btn {
        background: rgba(36, 37, 38, 0.88);
        border-color: var(--app-border, rgba(255,255,255,0.12));
        color: var(--app-text);
    }

    .cover-menu {
        position: absolute;
        right: 12px;
        bottom: 52px;
        z-index: 4;
        min-width: 240px;
        background: var(--app-surface-bg);
        border: 1px solid var(--app-border, rgba(0,0,0,0.10));
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.14);
        padding: 6px;
        display: none;
    }

    .cover-menu[data-open="1"] {
        display: block;
    }

    .cover-menu button {
        width: 100%;
        border: none;
        background: transparent;
        text-align: left;
        padding: 10px 10px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--app-text);
        font-weight: 800;
        font-size: 14px;
    }

    .cover-menu button:hover {
        background: var(--app-hover);
    }

    .cover-menu button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .cover-menu i {
        width: 18px;
        text-align: center;
        color: var(--app-muted);
        flex: 0 0 auto;
    }

    .profile-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .profile-header {
        position: relative;
        padding: 14px 16px 12px;
    }

    .profile-avatar {
        position: absolute;
        left: 16px;
        top: -84px;
        width: 168px;
        height: 168px;
        border-radius: 999px;
        border: 4px solid var(--app-surface-bg);
        background: var(--app-surface-bg);
        overflow: hidden;
    }

    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .profile-title-row {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        padding-left: 184px;
        min-height: 84px;
    }

    .profile-name {
        font-size: 28px;
        font-weight: 800;
        margin: 0;
        color: var(--app-text);
        line-height: 1.15;
    }

    .profile-subtitle {
        margin-top: 6px;
        color: var(--app-muted);
        font-size: 15px;
    }

    .profile-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        padding-bottom: 2px;
    }

    .btn {
        appearance: none;
        border: 1px solid var(--app-border, rgba(0,0,0,0.08));
        background: var(--app-icon-bg);
        color: var(--app-text);
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 14px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .btn.primary {
        background: var(--fb-blue);
        border-color: transparent;
        color: #fff;
    }

    .btn:hover { filter: brightness(0.98); }

    .profile-tabs {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 0 10px;
        border-top: 1px solid var(--app-border, rgba(0,0,0,0.08));
        overflow-x: auto;
    }

    .profile-tab {
        color: var(--app-muted);
        text-decoration: none;
        padding: 12px 14px;
        border-radius: 8px;
        font-weight: 700;
        white-space: nowrap;
    }

    .profile-tab:hover { background: var(--app-hover); }

    .profile-tab.active {
        color: var(--fb-blue);
        position: relative;
    }

    .profile-tab.active::after {
        content: "";
        position: absolute;
        left: 10px;
        right: 10px;
        bottom: 0;
        height: 3px;
        background: var(--fb-blue);
        border-radius: 999px;
    }

    .profile-body {
        display: grid;
        grid-template-columns: 360px minmax(0, 1fr);
        gap: 12px;
        align-items: start;
    }

    .card {
        background: var(--app-surface-bg);
        border-radius: 10px;
        padding: 14px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    }

    .muted {
        color: var(--app-muted);
        font-size: 14px;
    }

    .center-muted {
        text-align: center;
        color: var(--app-muted);
    }

    .profile-aside .card:not(:last-child) {
        margin-bottom: 12px;
    }

    .card-title {
        margin: 0 0 12px;
        font-size: 16px;
        font-weight: 800;
        color: var(--app-text);
    }

    .mini-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .mini-user {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .mini-user img {
        width: 40px;
        height: 40px;
        border-radius: 999px;
        object-fit: cover;
        background: var(--app-icon-bg);
        flex: 0 0 auto;
    }

    .mini-user .meta {
        min-width: 0;
        flex: 1;
    }

    .mini-user .name {
        font-weight: 800;
        font-size: 14px;
        color: var(--app-text);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mini-user .sub {
        font-size: 12px;
        color: var(--app-muted);
        margin-top: 2px;
    }

    .posts-list .post {
        box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    }

    .post-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }

    .post-header img {
        width: 40px;
        height: 40px;
        border-radius: 999px;
        object-fit: cover;
        background: var(--app-icon-bg);
    }

    .post-time {
        font-size: 13px;
        color: var(--app-muted);
        margin-top: 2px;
    }

    .post-images {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px;
        margin: 10px 0;
    }

    .post-images img {
        width: 100%;
        max-width: 100%;
        border-radius: 8px;
        display: block;
    }

    .post-footer {
        display: flex;
        justify-content: space-between;
        color: var(--app-muted);
        font-size: 14px;
        padding-top: 10px;
        border-top: 1px solid var(--app-border, rgba(0,0,0,0.08));
        margin-top: 10px;
    }

    .post-footer a {
        color: inherit;
        text-decoration: none;
        font-weight: 700;
    }

    .post-footer a:hover { text-decoration: underline; }

    @media (max-width: 1100px) {
        .profile-body { grid-template-columns: 1fr; }
        .profile-shell { max-width: 680px; }
    }

    @media (max-width: 600px) {
        .profile-cover { height: 220px; }
        .profile-avatar {
            width: 132px;
            height: 132px;
            top: -66px;
        }
        .profile-title-row {
            padding-left: 0;
            padding-top: 74px;
            align-items: flex-start;
            flex-direction: column;
        }
        .profile-name { font-size: 24px; }
    }
</style>

<div class="facebook-layout">

    <main class="profile-page" role="main">
        <div class="profile-shell">
            <section class="profile-top">
                <div class="profile-cover">
                    <img id="profileCoverImg" src="<?= fb_cover_url($user['cover'] ?? null) ?>" alt="">

                    <div class="cover-edit">
                        <button id="coverEditBtn" class="btn" type="button" aria-haspopup="menu" aria-expanded="false">
                            <?= $hasCustomCover ? 'Chỉnh sửa ảnh bìa' : 'Thêm ảnh bìa' ?>
                        </button>
                    </div>

                    <div id="coverMenu" class="cover-menu" role="menu" aria-label="Menu ảnh bìa">
                        <button id="coverChooseBtn" type="button" role="menuitem">
                            <i class="fa-regular fa-image" aria-hidden="true"></i>
                            Chọn ảnh bìa
                        </button>
                        <button id="coverUploadBtn" type="button" role="menuitem">
                            <i class="fa-solid fa-upload" aria-hidden="true"></i>
                            Tải ảnh lên
                        </button>
                        <input id="coverFileInput" type="file" accept="image/*" style="display:none" />
                    </div>
                </div>

                <div class="profile-header">
                    <div class="profile-avatar">
                        <img src="<?= fb_avatar_url($user['avatar'] ?? null) ?>" alt="Avatar">
                    </div>

                    <div class="profile-title-row">
                        <div>
                            <h1 class="profile-name"><?= fb_escape((string)($user['name'] ?? '')) ?></h1>
                            <div class="profile-subtitle"><?= number_format((int)$friend_count) ?> bạn bè</div>
                        </div>

                        <div class="profile-actions">
                            <a class="btn" href="#">Chỉnh sửa trang cá nhân</a>
                        </div>
                    </div>
                </div>

                <nav class="profile-tabs" aria-label="Profile tabs">
                    <a class="profile-tab active" href="#" aria-current="page">Bài viết</a>
                    <a class="profile-tab" href="#">Giới thiệu</a>
                    <a class="profile-tab" href="#">Bạn bè</a>
                    <a class="profile-tab" href="#">Ảnh</a>
                    <a class="profile-tab" href="#">Video</a>
                </nav>
            </section>

            <section class="profile-body">
                <aside class="profile-aside">
                    <div class="card">
                        <h3 class="card-title">Giới thiệu</h3>
                        <div class="muted">
                            Chưa có thông tin giới thiệu.
                        </div>
                    </div>

                    <div class="card">
                        <h3 class="card-title">Gợi ý kết bạn</h3>
                        <?php if (empty($suggestions)): ?>
                            <div class="muted">Không có gợi ý.</div>
                        <?php else: ?>
                            <div class="mini-list">
                                <?php foreach ($suggestions as $sug): ?>
                                    <div class="mini-user">
                                        <img src="<?= fb_avatar_url($sug['avatar'] ?? null) ?>" alt="">
                                        <div class="meta">
                                            <div class="name"><?= fb_escape((string)($sug['name'] ?? '')) ?></div>
                                            <div class="sub">1 bạn chung</div>
                                        </div>
                                        <a class="btn" href="../actions/add_friend.php?id=<?= (int)$sug['id'] ?>">Thêm bạn</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>

                <div>
                    <div class="posts-list">
                        <?php if (empty($posts)): ?>
                            <div class="card center-muted">
                                Chưa có bài viết nào.
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <div class="post">
                                    <div class="post-header">
                                        <img src="<?= fb_avatar_url($post['avatar'] ?? null) ?>" alt="">
                                        <div>
                                            <strong><?= fb_escape((string)($post['name'] ?? '')) ?></strong>
                                            <div class="post-time"><?= date('H:i d/m/Y', strtotime((string)$post['created_at'])) ?></div>
                                        </div>
                                    </div>

                                    <p style="margin: 10px 0;">
                                        <?= nl2br(fb_escape((string)($post['content'] ?? ''))) ?>
                                    </p>

                                    <?php if (!empty($post['image'])): ?>
                                        <?php
                                            $rawImg = (string)$post['image'];
                                            $rawTrim = trim($rawImg);
                                            $imgs = [];
                                            if ($rawTrim !== '' && $rawTrim[0] === '[') {
                                                $decoded = json_decode($rawTrim, true);
                                                if (is_array($decoded)) {
                                                    foreach ($decoded as $v) {
                                                        $v = trim((string)$v);
                                                        if ($v !== '') $imgs[] = $v;
                                                    }
                                                }
                                            } else {
                                                if ($rawTrim !== '') $imgs[] = $rawTrim;
                                            }
                                        ?>

                                        <?php if (!empty($imgs)): ?>
                                            <div class="post-images">
                                                <?php foreach ($imgs as $img): ?>
                                                    <img src="../uploads/<?= rawurlencode((string)$img) ?>" alt="">
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <div class="post-footer">
                                        <a href="../actions/like.php?post_id=<?= (int)$post['id'] ?>">Like <?= (int)$post['like_count'] ?></a>
                                        <a href="#">Comment</a>
                                        <a href="#">Share</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>

<script>
    (function () {
        try {
            const CURRENT_USER_ID = <?= (int)$user_id ?>;
            const coverImg = document.getElementById('profileCoverImg');
            const editBtn = document.getElementById('coverEditBtn');
            const menu = document.getElementById('coverMenu');
            const chooseBtn = document.getElementById('coverChooseBtn');
            const uploadBtn = document.getElementById('coverUploadBtn');
            const fileInput = document.getElementById('coverFileInput');

            if (!coverImg || !editBtn || !menu || !chooseBtn || !uploadBtn || !fileInput) return;

            // Hide browser broken-image placeholder icon
            coverImg.addEventListener('error', () => {
                try { coverImg.style.display = 'none'; } catch (_e) {}
            });
            coverImg.addEventListener('load', () => {
                try { coverImg.style.display = 'block'; } catch (_e) {}
            });

            const openMenu = () => {
                menu.setAttribute('data-open', '1');
                editBtn.setAttribute('aria-expanded', 'true');
            };
            const closeMenu = () => {
                menu.removeAttribute('data-open');
                editBtn.setAttribute('aria-expanded', 'false');
            };
            const toggleMenu = () => {
                const isOpen = menu.getAttribute('data-open') === '1';
                if (isOpen) closeMenu();
                else openMenu();
            };

            editBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleMenu();
            });

            menu.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            document.addEventListener('click', () => closeMenu());
            document.addEventListener('keydown', (e) => {
                if (e && e.key === 'Escape') closeMenu();
            });

            function pickFile() {
                try {
                    fileInput.value = '';
                    fileInput.click();
                } catch (_e) {}
            }

            chooseBtn.addEventListener('click', (e) => {
                e.preventDefault();
                pickFile();
            });

            uploadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                pickFile();
            });

            function setButtonsBusy(busy) {
                editBtn.disabled = !!busy;
                chooseBtn.disabled = !!busy;
                uploadBtn.disabled = !!busy;
                editBtn.textContent = busy ? 'Đang tải…' : 'Chỉnh sửa ảnh bìa';
            }

            async function uploadCover(file) {
                if (!file) return;
                if (!file.type || !String(file.type).toLowerCase().startsWith('image/')) {
                    alert('Vui lòng chọn file ảnh');
                    return;
                }
                if (file.size > 8 * 1024 * 1024) {
                    alert('Ảnh quá lớn (tối đa 8MB)');
                    return;
                }

                setButtonsBusy(true);

                try {
                    const fd = new FormData();
                    fd.append('cover', file);

                    const res = await fetch('../actions/update_cover.php', {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await res.json().catch(() => null);
                    if (!res.ok || !data || !data.ok || !data.coverUrl) {
                        const msg = data && data.message ? String(data.message) : 'Upload thất bại';
                        alert(msg);
                        return;
                    }

                    const url = String(data.coverUrl);
                    const sep = url.includes('?') ? '&' : '?';
                    coverImg.style.display = 'block';
                    coverImg.src = url + sep + 'v=' + Date.now();
                    editBtn.textContent = 'Chỉnh sửa ảnh bìa';
                    closeMenu();
                } catch (err) {
                    console.warn(err);
                    alert('Upload thất bại');
                } finally {
                    setButtonsBusy(false);
                }
            }

            fileInput.addEventListener('change', () => {
                const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
                uploadCover(file);
            });

            // ===== Realtime (socket) =====
            (function initSocketCoverSync() {
                try {
                    if (typeof io !== 'function') return;

                    // Reuse a global socket if already created (home page sets window.fbSocket)
                    let socket = (window.fbSocket && typeof window.fbSocket.on === 'function') ? window.fbSocket : null;
                    if (!socket) {
                        const host = (window.location && window.location.hostname) ? window.location.hostname : 'localhost';
                        const proto = (window.location && window.location.protocol) ? window.location.protocol : 'http:';
                        const isLocalHost = host === 'localhost' || host === '127.0.0.1';
                        const socketHost = (!isLocalHost && host.toLowerCase().startsWith('app.'))
                            ? `socket.${host.slice(4)}`
                            : '';
                        const useSameOrigin = (proto === 'https:') && !socketHost;
                        const socketOpts = { path: '/socket.io', transports: ['websocket', 'polling'], withCredentials: true };
                        const socketUrl = socketHost ? `${proto}//${socketHost}` : '';
                        socket = socketUrl
                            ? io(socketUrl, socketOpts)
                            : (useSameOrigin ? io(undefined, socketOpts) : io(`http://${host}:3000`, socketOpts));
                        window.fbSocket = socket;
                    }

                    // join per-user room (server listens for this)
                    socket.emit('join', CURRENT_USER_ID);

                    socket.on('profile:cover_updated', (payload) => {
                        try {
                            if (!payload) return;
                            const uid = Number(payload.user_id || 0);
                            if (uid !== CURRENT_USER_ID) return;
                            const url = payload.coverUrl ? String(payload.coverUrl) : '';
                            if (!url) return;
                            const sep = url.includes('?') ? '&' : '?';
                            coverImg.style.display = 'block';
                            coverImg.src = url + sep + 'v=' + Date.now();
                            editBtn.textContent = 'Chỉnh sửa ảnh bìa';
                        } catch (_e) {}
                    });
                } catch (_e) {}
            })();
        } catch (_e) {}
    })();
</script>
