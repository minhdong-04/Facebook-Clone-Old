<?php
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $u = $_POST['username'];
    $p = $_POST['password'];
    
    // Gọi hàm login từ User class
    $result = $userObj->login($u, $p);
    
    if ($result) {
        // Lưu thông tin vào session
        $_SESSION['user'] = $result; 
        echo "<script>alert('Đăng nhập thành công!'); window.location='index.php';</script>";
    } else {
        $msg = "Sai tên đăng nhập hoặc mật khẩu!";
    }
}
?>

<h3>Đăng nhập hệ thống</h3>
<span style="color:red"><?php echo $msg; ?></span>
<form action="" method="post">
    <table border="0">
        <tr>
            <td>Username:</td>
            <td><input type="text" name="username" required></td>
        </tr>
        <tr>
            <td>Password:</td>
            <td><input type="password" name="password" required></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="Đăng nhập"></td>
        </tr>
    </table>
</form>