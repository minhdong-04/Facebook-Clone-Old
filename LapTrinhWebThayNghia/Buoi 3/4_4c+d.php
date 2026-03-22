<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Lab 4. - Final</title>
<style>
    #banco{border:solid; padding:15px; background:#E8E8E8} 
     #banco .cellBlack{width:50px; height:50px; background:black; float:left; }
    #banco .cellWhite{width:50px; height:50px; background:white; float:left}
    .clear{clear:both}
     #banco_new {
        border: solid; 
        padding: 15px; 
         background: #E8E8E8;
        width: <?php echo 8 * 50 + 30;?>px; 
    }
     #banco_new .cellBlack{width:50px; height:50px; background:black; float:left; }
    #banco_new .cellWhite{width:50px; height:50px; background:white; float:left}
</style>
</head>

<body>
<?php
include_once 'function.php'; 

echo "<h1>VẬN DỤNG 4.4 - GỌI HÀM TỪ function.php</h1>";

echo "<h2>1. Bảng Cửu Chương (4.4a - Xuất Trực Tiếp)</h2>";
BCC(7, "gold", "pink", "white"); 

echo "<h2>2. Bảng Cửu Chương (4.4b - Return Chuỗi)</h2>";
echo BCC_return(7, "skyblue", "lightyellow", "lightgreen");

echo "<br><h2>3. Bàn Cờ Vua (4.4b - Return Chuỗi)</h2>";
echo BanCo_return(8);
?>
</body>
</html>