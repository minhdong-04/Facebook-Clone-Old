<?php
session_start(); // Khởi động session để quản lý admin login

// Ví dụ kiểm tra đăng nhập admin bằng session
if(!isset($_SESSION['admin'])){
    $_SESSION['admin'] = "AdminUser"; // minh họa
}

// Ví dụ đặt cookie admin
setcookie("admin", $_SESSION['admin'], time()+3600); // lưu 1 giờ

define("ROOT", dirname(__FILE__)); // Thư mục gốc admin
include ROOT."/include/function.php"; // hàm dùng chung
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div id="container">
    <div id="header">Header Admin</div>
    <div id="body">
        <div id="left">
            <a href="index.php">Home</a><br>
            <a href="index.php?mod=product">Quản lý sản phẩm</a><br>
            <a href="index.php?mod=category">Quản lý loại sản phẩm</a><br>
            <a href="index.php?mod=order">Quản lý đơn hàng</a><br>
            <a href="index.php?mod=user">Quản lý thành viên</a><br>
            <hr>
            <a href="../index.php">Trang Front-end</a>
        </div>
        <div id="right">
            <?php
            // Thông tin admin
            echo "Admin: ".$_SESSION['admin']."<br>";
            echo "Cookie admin: ".(isset($_COOKIE['admin'])?$_COOKIE['admin']:'Chưa có')."<br>";
            echo "File hiện tại: ".$_SERVER['PHP_SELF']."<br><hr>";
            ?>
            
            <?php
            // Include module dựa trên tham số GET
            $mod = isset($_GET['mod']) ? $_GET['mod'] : 'home';
            $path = ROOT."/module/".$mod."/index.php";

            if(file_exists($path)){
                include $path;
            } else {
                echo "<p>Module không tồn tại!</p>";
            }
            ?>
        </div>
    </div>
    <div id="footer">Footer Admin</div>
</div>
</body>
</html>
