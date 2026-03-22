<?php
if(!defined("ROOT")){
    echo "Bạn không có quyền truy cập!";
    exit;
}

// Ví dụ danh sách sản phẩm
$products = array(
    array("id"=>1,"name"=>"Sách Toán"),
    array("id"=>2,"name"=>"Sách Tin Học"),
    array("id"=>3,"name"=>"Sách Văn Hóa")
);

echo "<h3>Quản lý sản phẩm</h3>";
echo "<ul>";
foreach($products as $p){
    echo "<li>ID: {$p['id']} - Name: {$p['name']}</li>";
}
echo "</ul>";
