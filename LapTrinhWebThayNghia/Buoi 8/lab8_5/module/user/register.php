<?php
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $u = $_POST['username'];
    $p = $_POST['password'];
    $e = $_POST['email'];
    $f = $_POST['fullname'];
    
    // Gọi hàm register từ User class
    // Lưu ý: Hàm này trả về số dòng insert được (lớn hơn 0 là thành công)
    $res = $userObj->register($u, $p, $e, $f);
    
    if ($res) {
        echo "<script>alert('Đăng ký thành công! Vui lòng đăng nhập.'); window.location='index.php?mod=user&act=login';</script>";
    } else {
        $msg = "Đăng ký thất bại hoặc tên đăng nhập đã tồn tại!";
    }
}
?>

<h3>Đăng ký thành viên mới</h3>
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
            <td>Email:</td>
            <td><input type="email" name="email" required></td>
        </tr>
        <tr>
            <td>Fullname:</td>
            <td><input type="text" name="fullname" required></td>
        </tr>
        <tr>
            <td></td>
            <td><input type="submit" value="Đăng ký"></td>
        </tr>
    </table>
</form>