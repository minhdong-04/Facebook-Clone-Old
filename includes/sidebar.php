<?php
// includes/sidebar.php
require_once __DIR__ . '/db.php';

if (!Database::isLoggedIn()) {
    return; // Không hiển thị sidebar nếu chưa đăng nhập
}

$user = Database::getCurrentUser();
if (!$user) {
    return; // An toàn
}
?>

<div class="sidebar">
    <ul class="sidebar-menu">
        <!-- Trang cá nhân -->
        <li>
            <a href="../pages/profile.php">
                <img src="../assets/images/<?= $user['avatar'] ?? 'default-avatar.png' ?>" 
                     width="32" class="rounded-full" alt="Avatar">
                <span><?= htmlspecialchars($user['name']) ?></span>
            </a>
        </li>

        <!-- Menu chính -->
        <li><a href="../pages/friends.php">Friends</a></li>
        <li><a href="../pages/group.php">Groups</a></li>
        <li><a href="../pages/marketplace.php">Marketplace</a></li>
        <li><a href="../pages/watch.php">Watch</a></li>
        <li><a href="#">Events</a></li>
        <li><a href="#">Saved</a></li>
        <li><a href="#">Pages</a></li>
        <li><a href="#">Gaming</a></li>

        <hr>

        <!-- Cài đặt -->
        <li><a href="#">Settings & Privacy</a></li>
        <li><a href="../auth/logout.php">Log Out</a></li>
    </ul>
</div>

<style>
    /* === SIDEBAR FACEBOOK THẬT === */
    .sidebar {
        width: 300px;
        padding: 10px 0;
        position: sticky;
        top: 60px; /* Dưới header */
        max-height: calc(100vh - 60px);
        overflow-y: auto;
        font-family: 'Helvetica Neue', Arial, sans-serif;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu li {
        margin: 4px 8px;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        border-radius: 8px;
        color: #1c1e21;
        text-decoration: none;
        font-weight: 500;
        font-size: 15px;
        transition: background 0.2s;
    }

    .sidebar-menu a:hover {
        background: #f0f2f5;
    }

    .sidebar-menu a i {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #65676b;
        margin-right: 12px;
    }

    .sidebar-menu img.rounded-full {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 12px;
    }

    .sidebar-menu hr {
        border: none;
        border-top: 1px solid #ddd;
        margin: 12px 8px;
    }

    /* Responsive: Ẩn sidebar trên mobile */
    @media (max-width: 1000px) {
        .sidebar {
            display: none;
        }
    }

    /* Scrollbar mượt */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }
</style>