<?php
$a = array(1, -3, 5); // mảng có 3 phần tử
$b = array("a" => 2, "b" => 4, "c" => -6); // mảng có 3 phần tử, các chỉ mục là chuỗi
?>

<!-- Nội dung giá trị mảng a: -->
Nội dung giá trị mảng a:
<?php
$countPositiveA = 0;  // Biến để đếm số phần tử dương trong mảng $a
foreach ($a as $value) {
    echo $value . " ";
    if ($value > 0) {
        $countPositiveA++; // Nếu giá trị là dương, tăng biến đếm
    }
}
?>
<br>
Số phần tử dương trong mảng a là: <?php echo $countPositiveA; ?> <br>

<!-- Nội dung mảng a (key-value): -->
Nội dung mảng a (key-value):
<?php  
foreach ($a as $key => $value) {
    echo "($key - $value) ";  
}
?>
<br /> 

<!-- Nội dung mảng b (key - value): -->
Nội dung mảng b (key - value):
<?php
foreach ($b as $k => $v) {
    echo "($k - $v) ";  
}
?>
<br />

<!-- Hiển thị nội dung mảng b ra dạng bảng: -->
Hiển thị nội dung mảng b ra dạng bảng:
<table border="1">
    <tr><td>STT</td><td>Key</td><td>Value</td></tr>
    <?php
    $i = 0;
    foreach ($b as $k => $v) {  
        $i++;
        echo "<tr><td>$i</td>";
        echo "<td>$k</td>";
        echo "<td>$v</td></tr>";
    }
    ?>
</table>

<!-- Tạo mảng mới lưu các phần tử dương trong mảng b: -->
<?php
$c = array();  // Mảng mới để lưu các phần tử dương của mảng b
foreach ($b as $k => $v) {
    if ($v > 0) {
        $c[$k] = $v;  // Thêm phần tử vào mảng $c nếu nó là dương
    }
}
?>

<!-- Hiển thị mảng c -->
Mảng c (các phần tử dương của mảng b):
<?php
print_r($c);
?>
