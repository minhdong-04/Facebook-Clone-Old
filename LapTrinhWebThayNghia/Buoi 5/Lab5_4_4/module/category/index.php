<?php
if(!defined("ROOT")){
    echo "Bạn không có quyền truy cập!";
    exit;
}

// Ví dụ danh sách loại sản phẩm
$categories = array("Toán Học","Tin Học","Văn Hóa");
echo "<h3>Quản lý loại sản phẩm</h3>";
echo "<ul>";
foreach($categories as $c){
    echo "<li>$c</li>";
}
echo "</ul>";
