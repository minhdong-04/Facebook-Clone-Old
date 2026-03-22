<?php
session_start();
include "config/config.php";

// Tự động load class
spl_autoload_register(function($className) {
    include "classes/" . $className . ".class.php";
});

$mod = isset($_GET['mod']) ? $_GET['mod'] : 'home';
$act = isset($_GET['act']) ? $_GET['act'] : 'index';

// --- PHẦN HEADER MENU ---
echo "<h1>Website Bán Sách</h1>";
echo "<a href='index.php?mod=book&act=list'>Danh sách Sách</a> | ";
echo "<a href='index.php?mod=cart&act=view'>Giỏ hàng</a> | ";

// Kiểm tra nếu đã đăng nhập thì hiện tên + nút thoát
if (isset($_SESSION['user'])) {
    echo "Xin chào, <b>" . $_SESSION['user']['fullname'] . "</b> | ";
    echo "<a href='index.php?mod=user&act=logout'>Đăng xuất</a>";
} else {
    // Chưa đăng nhập thì hiện nút Đăng nhập / Đăng ký
    echo "<a href='index.php?mod=user&act=login'>Đăng nhập</a> | ";
    echo "<a href='index.php?mod=user&act=register'>Đăng ký</a>";
}
echo "<hr>";

// --- PHẦN ĐIỀU HƯỚNG ---
switch ($mod) {
    case 'book':
        if ($act == 'list') include "module/book/list.php";
        break;
        
    case 'cart':
        $cart = new Cart();
        if ($act == 'add') {
            $cart->add($_GET['id']);
            header("Location: index.php?mod=cart&act=view");
        } 
        elseif ($act == 'view') include "module/cart/view.php"; // Bạn cần tạo file này để xem giỏ
        break;
        
    case 'user':
        $userObj = new User(); // Khởi tạo đối tượng User
        if ($act == 'login') include "module/user/login.php";
        if ($act == 'register') include "module/user/register.php";
        if ($act == 'logout') {
            unset($_SESSION['user']);
            header("Location: index.php?mod=user&act=login");
        }
        break;
        
    default:
        echo "Trang chủ - Chào mừng!";
        break;
}
?>