<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>lab 2_5</title>
</head>

<body>
<?php
	include("lab2_5a.php");
    include("lab2_5b.php"); // chạy lần 2 bị ghi đè
    include_once("lab2_5b.php"); // đã chạy 2 lần
	
	if(isset($x))
		echo "Giá trị của x là: $x";
	else
		echo "Biến x không tồn tại";
    echo "<br>==> Chạy ra kết quả là x=20 khác so với 4_6.php và giống với kết quả của 4_5.php <br>";
    echo "<br>==> Lệnh include sẽ được chạy đè lên nếu được gọi tiếp, còn include_once thì chỉ gọi được 1 lần và k đè lần2"
?>
</body>
</html>