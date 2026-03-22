<?php
session_start();

function postIndex($index, $value=""){
    return isset($_POST[$index]) ? $_POST[$index] : $value;
}

$submit   = postIndex("submit");
$username = postIndex("username");
$password = postIndex("password");
$password2= postIndex("password2");
$gender   = postIndex("gender");
$hobbies  = postIndex("hobbies", array());
$province = postIndex("province");
$arrImg   = array("image/png","image/jpeg","image/bmp","image/gif");

$err = "";
if($submit){
    if($username=="") $err .= "Phải nhập tên đăng nhập<br>";
    if($password=="") $err .= "Phải nhập mật khẩu<br>";
    if($password2=="") $err .= "Phải nhập lại mật khẩu<br>";
    if($password!=$password2) $err .= "Mật khẩu nhập lại không trùng<br>";
    if($gender=="") $err .= "Phải chọn giới tính<br>";
    if($province=="") $err .= "Phải chọn tỉnh<br>";

    if(isset($_FILES['avatar']) && $_FILES['avatar']['error']!=4){
        $errFile = $_FILES["avatar"]["error"];
        $type    = $_FILES["avatar"]["type"];
        $temp    = $_FILES["avatar"]["tmp_name"];
        $name    = $_FILES["avatar"]["name"];
        if($errFile>0) $err .= "Lỗi file hình<br>";
        elseif(!in_array($type, $arrImg)) $err .= "File phải là hình ảnh (.jpg, .png, .bmp, .gif)<br>";
        else{
            if(!move_uploaded_file($temp, "uploads/".$name)) $err .= "Không thể lưu file<br>";
        }
    }

    if($err==""){
        $_SESSION['username'] = $username;
        setcookie("username", $username, time()+3600);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Lab5_4_3 – Đăng ký thành viên (JS Validation)</title>
<style>
fieldset{width:50%; margin:20px auto;}
.error{color:red;}
</style>
<script>
// Hàm kiểm tra form bằng JS
function validateForm(){
    let f = document.forms["regForm"];
    let err = "";

    if(f.username.value.trim()=="") err += "Phải nhập tên đăng nhập\n";
    if(f.password.value=="") err += "Phải nhập mật khẩu\n";
    if(f.password2.value=="") err += "Phải nhập lại mật khẩu\n";
    if(f.password.value!=f.password2.value) err += "Mật khẩu nhập lại không trùng\n";

    let genderChecked = false;
    for(let g of f.gender){
        if(g.checked) genderChecked = true;
    }
    if(!genderChecked) err += "Phải chọn giới tính\n";

    if(f.province.value=="") err += "Phải chọn tỉnh\n";

    if(err!=""){
        alert("Có lỗi:\n"+err);
        return false; // Ngăn submit
    }
    return true; // Cho phép submit
}
</script>
</head>
<body>

<h2>Form đăng ký thành viên</h2>

<?php
if($submit && $err!=""){
    echo "<div class='error'><b>Lỗi:</b><br>$err</div>";
}
if($submit && $err==""){
    echo "<h3>Thông tin vừa nhập:</h3>";
    echo "Tên đăng nhập: <b>$username</b><br>";
    echo "Giới tính: <b>".($gender==1 ? "Nam" : "Nữ")."</b><br>";
    echo "Sở thích: <b>".implode(", ", $hobbies)."</b><br>";
    echo "Tỉnh: <b>$province</b><br>";
    if(isset($name)) echo "Hình ảnh: <br><img src='uploads/$name' width='150'><br>";
    echo "<p>Session và Cookie đã lưu thông tin tên đăng nhập.</p>";
}
?>

<form name="regForm" action="lab5_4_3_js.php" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">
<fieldset>
<legend>Thông tin đăng ký</legend>
Tên đăng nhập (*): <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>"><br><br>
Mật khẩu (*): <input type="password" name="password"><br><br>
Nhập lại mật khẩu (*): <input type="password" name="password2"><br><br>
Giới tính (*):
<input type="radio" name="gender" value="1" <?php if($gender==1) echo "checked"; ?>> Nam
<input type="radio" name="gender" value="0" <?php if($gender==0) echo "checked"; ?>> Nữ<br><br>
Sở thích:
<input type="checkbox" name="hobbies[]" value="Thể thao" <?php if(in_array("Thể thao",$hobbies)) echo "checked"; ?>> Thể thao
<input type="checkbox" name="hobbies[]" value="Du lịch" <?php if(in_array("Du lịch",$hobbies)) echo "checked"; ?>> Du lịch
<input type="checkbox" name="hobbies[]" value="Game" <?php if(in_array("Game",$hobbies)) echo "checked"; ?>> Game<br><br>
Hình ảnh: <input type="file" name="avatar"><br><br>
Tỉnh (*):
<select name="province">
    <option value="">--Chọn tỉnh--</option>
    <option value="Hà Nội" <?php if($province=="Hà Nội") echo "selected"; ?>>Hà Nội</option>
    <option value="TP.HCM" <?php if($province=="TP.HCM") echo "selected"; ?>>TP.HCM</option>
    <option value="Đà Nẵng" <?php if($province=="Đà Nẵng") echo "selected"; ?>>Đà Nẵng</option>
</select><br><br>
<input type="submit" name="submit" value="Đăng ký">
<input type="reset" value="Reset">
</fieldset>
</form>

</body>
</html>
