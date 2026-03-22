<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>lab 2_5</title>
</head>

<body>
<?php
	require("lab2_5a.php");
    require("lab2_5b.php"); // chạy lần 2 bị ghi đè
    require_once("lab2_5b.php"); // đã chạy 2 lần
	
	if(isset($x))
		echo "Giá trị của x là: $x";
	else
		echo "Biến x không tồn tại";
    echo "<br>==> Kết quả chạy ra giống nhau <br>";
    echo "<br>==> Khi file tồn tại, requice và include chạy kết quả giống nhau. Requice_once và include_once cũng tương tự v sẽ chạy giống nhau ( chỉ chạy file lab2_5b.php 1 lần duy nhất )  <br>";

?>
</body>
</html>