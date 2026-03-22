<?php
$pageTitle = 'Kỷ niệm • Facebook';
$extraHead = '<link rel="icon" href="../uploads/fb_logo.jpg">';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
        .container {
            display: flex;
            margin-top: 0;
        }
        .left-sidebar {
            width: 340px;
            background: #fff;
            min-height: calc(100vh - 56px);
            border-right: 1px solid #dddfe2;
            padding: 20px 0;
        }
        .sidebar-title {
            padding: 0 24px;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        .menu-item {
            padding: 10px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 17px;
            cursor: pointer;
            border-radius: 8px;
            margin: 0 12px;
        }
        .menu-item:hover { background: #f0f2f5; }
        .menu-item.active {
            background: #e7f3ff;
            color: #1877f2;
            font-weight: 600;
        }
        .menu-item .icon {
            width: 36px; height: 36px;
            background: #e4e6eb;
            border-radius: 50%;
        }
        .section { margin-top: 24px; }
        .section-title {
            padding: 8px 24px;
            font-size: 15px;
            font-weight: 600;
            color: #606770;
        }

        .main-content {
            flex: 1;
            padding: 80px 100px;
            display: flex;
            justify-content: center;
        }
        .memories-card {
            background: #fff;
            border-radius: 12px;
            width: 500px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .memories-header {
            height: 160px;
            background: linear-gradient(to bottom, #ff8a80, #ff5252);
            position: relative;
        }
        .memories-header img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .memories-body {
            padding: 24px;
            text-align: center;
        }
        .memories-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .memories-text {
            color: #606770;
            font-size: 15px;
            line-height: 20px;
        }

</style>

    <div class="container">
        <!-- Sidebar trái -->
        <div class="left-sidebar">
            <div class="sidebar-title">Kỷ niệm</div>

            <div class="menu-item active">
                <div class="icon" style="background:#1877f2;"></div>
                Trang chủ kỷ niệm
            </div>

            <div class="section">
                <div class="section-title">Cài đặt</div>
                <div class="menu-item">
                    <div class="icon"></div>
                    Thông báo
                </div>
                <div class="menu-item">
                    <div class="icon"></div>
                    Ẩn mọi người
                    <span style="margin-left:auto; color:#606770;">0 Đã ẩn</span>
                </div>
                <div class="menu-item">
                    <div class="icon"></div>
                    Ẩn ngày
                    <span style="margin-left:auto; color:#606770;">0 Đã ẩn</span>
                </div>
            </div>
        </div>

        <!-- Nội dung chính -->
        <div class="main-content">
            <div class="memories-card">
                <div class="memories-header">
                    <!-- Ảnh minh họa kỷ niệm (dùng ảnh thật của Facebook) -->
                    <img src="https://static.xx.fbcdn.net/rsrc.php/v3/y3/r/dk2L9Zf5s0J.png" alt="Memories illustration">
                </div>
                <div class="memories-body">
                    <div class="memories-title">Không có kỷ niệm nào hôm nay</div>
                    <div class="memories-text">
                        Hôm nay không có Kỷ niệm nào để xem hay chia sẻ, nhưng chúng tôi sẽ thông báo<br>
                        cho bạn khi bạn có khoảnh khắc để ôn lại.
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>

</body>
</html>