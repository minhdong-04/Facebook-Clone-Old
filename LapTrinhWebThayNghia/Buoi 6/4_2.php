<?php
function postIndex($index, $value="")
{
    if (!isset($_POST[$index])) return $value;
    return trim($_POST[$index]);
}
function checkUserName($string)
{
    return preg_match("/^[a-zA-Z0-9._-]*$/",$string);
}
function checkEmail($string)
{
    return preg_match("/^[a-zA-Z0-9._-]+@[a-zA-Z0-9-]+\.[a-zA-Z.]{2,10}$/", $string);
}
function checkPassword($pw)
{
    return preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/", $pw);
}
function checkPhone($phone)
{
    return preg_match("/^[0-9]+$/", $phone);
}
function checkDateFormat($date)
{
    return preg_match("/^(0?[1-9]|[12][0-9]|3[01])[\/-](0?[1-9]|1[012])[\/-]\d{4}$/", $date);
}

$sm = postIndex("submit");
$username = postIndex("username");
$password = postIndex("password");
$email    = postIndex("email");
$date     = postIndex("date");
$phone    = postIndex("phone");
?>
<html>
<head>
<meta charset="utf-8" />
<title>Lab6_3</title>
<style>
fieldset{width:50%; margin:100px auto;}
.info{
    width:600px;
    color:#006;
    background:#6FC;
    margin:0 auto;
    padding: 10px;
}
#frm1 input{width:300px}
</style>
</head>

<body>
<fieldset>
<legend style="margin:0 auto">Đăng ký thông tin </legend>
<form action="" method="post" enctype="multipart/form-data" id='frm1'>
<table align="center">
    <tr>
      <td>UserName</td>
      <td><input type="text" name="username" value="<?php echo $username;?>"/>*</td></tr>

    <tr>
      <td>Mật khẩu</td>
      <td><input type="password" name="password" value="<?php echo $password;?>" />*</td></tr>

    <tr>
      <td>Email</td>
      <td><input type="text" name="email" value="<?php echo $email;?>" />*</td></tr>

    <tr>
      <td>Ngày sinh</td>
      <td><input type="text" name="date" value="<?php echo $date;?>" placeholder="dd/mm/yyyy" />*</td></tr>

    <tr>
      <td>Điện thoại</td>
      <td><input type="text" name="phone" value="<?php echo $phone;?>" /></td></tr>
      
    <tr><td colspan="2" align="center"><input type="submit" value="submit" name="submit"></td></tr>
</table>
</form>
</fieldset>

<?php
if ($sm !="")
{
?>
<div class="info">Xuất thành công<br />
    <?php 
    
    if (!checkUserName($username)) 
        echo "Username: Chỉ được dùng a-z, A-Z, 0-9, ., _ và - <br>";

    if (!checkPassword($password))
        echo "Mật khẩu phải ≥ 8 ký tự, gồm chữ hoa, chữ thường và số!<br>";

    if (!checkEmail($email))
        echo "Email sai định dạng!<br>";

    if (!checkDateFormat($date))
        echo "Ngày sinh phải dạng dd/mm/yyyy hoặc dd-mm-yyyy<br>";

    if ($phone != "" && !checkPhone($phone))
        echo "Điện thoại chỉ được chứa số!<br>";
    ?>
</div>
<?php } ?>

</body>
</html>
