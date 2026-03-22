<?php
function postIndex($index, $value="")
{
	if (!isset($_POST[$index]))	return $value;
	return trim($_POST[$index]);
}

$username 	= postIndex("username");
$password1	= postIndex("password1");
$password2	= postIndex("password2");
$name		= postIndex("name");
$sm 		= postIndex("submit");
$thongtin   = postIndex("thongtin");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Lab6_1</title>
<style>
fieldset{width:50%; margin:100px auto;}
.info{width:600px; color:#006; background:#6FC; margin:0 auto}
</style>
</head>

<body>
<fieldset>
<legend style="margin:0 auto">Thông tin đăng ký</legend>
<form action=" " method="post" enctype="multipart/form-data">
<table  align="center">
    <tr><td>Tên đăng nhập:</td><td><input type="text" name="username" 
    					value="<?php echo $username;?>"></td></tr>
    <tr><td>Mật khẩu:</td><td><input type="password" name="password1" /></td></tr>
     <tr><td>Nhập lại mật khẩu:</td><td><input type="password" name="password2" /></td></tr>
    <tr><td>Họ Tên:</td><td><input type="text" name="name" value="<?php echo $name;?>" /></td></tr>
	<tr><td>Thông tin:</td>
    <td><textarea name="thongtin" rows="5" cols="40"><?php echo $thongtin;?></textarea></td></tr>
    <tr><td colspan="2" align="center"><input type="submit" value="submit" name="submit"></td></tr>
</table>
</form>
</fieldset>
<?php

if ($sm !="")
{
	$err= "";
	if (strlen($username)<6 ) 		$err .=" Username ít nhất phải 6 ký tự!<br>";
	if ($password1!= $password2) 	$err .="Mật khẩu và mật khẩu nhập lại không khớp. <br>";
	if(strlen($password1)<8) 		$err .="Mật khẩu phải ít nhất 8 ký tự.<br>";
	if(str_word_count($name)<2) 	$err .="Họ tên phải chứa ít nhất 2 từ ";

	$step1 = strip_tags($thongtin);       // Bỏ HTML
    $step2 = addslashes($step1);          // Thêm \ trước '
    $step3 = nl2br($step2);               // \n thành <br>
    $final = stripslashes($step3);		  // Bỏ \ khi xuất
	?>
    <div class="info">
    	<?php 
			if ($err !="") echo $err;
			else
			  {
            echo "Username: $username <br>";
            $password_md5 = md5($password1);  // Mã hóa bằng MD5 (128bit) 32 ký tự
            $password_sha1 = sha1($password_md5); // Mã hóa kết quả MD5 bằng SHA1 (160bit) 40 ký tự
            $password_sha256 = hash('sha256', $password1); //sha256 64 ký tự HEX
            $password_sha3_256 = hash('sha3-256', $password1);

            echo "Mật khẩu đã mã hóa MD5: " . $password_md5 . "<br>";
            echo "Mật khẩu đã mã hóa SHA1: " . $password_sha1 . "<br>";
            echo "<hr>";
            echo "Mật khẩu đã mã hóa sha256: " . $password_sha256 . "<br>";
            echo "Mật khẩu đã mã hóa sha3-256: " . $password_sha3_256 . "<br>";
            echo "<hr>";
            echo "Họ tên: " . ucwords($name) . "<br>";
            echo "Thông tin đã xử lý: $thongtin <br>";
			}
		?>
    </div>
    <?php

}
?>
</body>
</html>
