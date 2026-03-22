<?php
// Tạo mảng sản phẩm
$arr = array();

$r = array("id"=>1, "name"=>"Product1");
$arr[] = $r;

$r = array("id"=>2, "name"=>"Product2");
$arr[] = $r;

$r = array("id"=>3, "name"=>"Product3");
$arr[] = $r;

$r = array("id"=>4, "name"=>"Product4");
$arr[] = $r;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Lab5_4_1 – Nhận dữ liệu qua URL</title>
</head>
<body>
<h3>Danh sách sản phẩm</h3>
<ul>
<?php
// Duyệt mảng và in ra các link <a> gửi id qua URL
foreach($arr as $item){
    // Link tới 2.php, truyền id tương ứng
    echo '<li><a href="2.php?id='.$item["id"].'">'.$item["name"].'</a></li>';
}
?>
</ul>
</body>
</html>
