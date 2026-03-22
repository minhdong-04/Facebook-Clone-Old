<?php
function showArray($arr)
{
	foreach($arr as $k=>$v)
	{
		echo "<br> $k - $v ";	
	}	
}

$a = array(6, 2, 7, 8, 5); 
$b = array("a"=>4, "b"=>2, "c"=>3, "d"=>8);

// Lấy một phần tử ngẫu nhiên từ mảng $a
$n = array_rand($a);
echo "Phần tử ngẫu nhiên: key=$n , giá trị=".$a[$n];

// Lấy 3 phần tử ngẫu nhiên từ mảng $a
$c = array_rand($a, 3);
echo "<br> Danh sách 3 phần tử ngẫu nhiên được lấy ra:";
foreach($c as $k)
{
	echo "(key=$k - value={$a[$k]})";
}

// Lấy 3 phần tử ngẫu nhiên từ mảng $b
$m = 3;
$c = array_rand($b, $m);
echo "<br> Danh sách $m phần tử ngẫu nhiên được lấy ra từ b:";
foreach($c as $k)
{
	echo "(key=$k - value={$b[$k]})";
}

?><hr />

<?php
// Sắp xếp mảng $a theo chiều tăng dần và hiển thị
$a1 = $a; 
sort($a1);
echo "Mảng a sau khi sắp xếp theo chiều tăng dần:";
showArray($a1);

// Sắp xếp mảng $b theo chiều tăng dần (loại bỏ key) và hiển thị
$b1 = $b;
sort($b1);
echo "<br>Mảng b sau khi sắp xếp theo chiều tăng dần (loại bỏ key):";
showArray($b1);

// Sắp xếp mảng $b theo chiều tăng dần nhưng giữ lại key và hiển thị
$b2 = $b;
asort($b2);
echo "<br>Mảng b sau khi sắp xếp theo chiều tăng dần (giữ lại key):";
showArray($b2);

// Sắp xếp mảng $a theo chiều giảm dần và hiển thị
$a1 = $a; 
rsort($a1);  // Sắp xếp theo chiều giảm dần
echo "<br>Mảng a sau khi sắp xếp theo chiều giảm dần:";
showArray($a1);

// Sắp xếp mảng $b theo chiều giảm dần (loại bỏ key) và hiển thị
$b1 = $b;
rsort($b1);  // Sắp xếp theo chiều giảm dần
echo "<br>Mảng b sau khi sắp xếp theo chiều giảm dần (loại bỏ key):";
showArray($b1);

// Sắp xếp mảng $b theo chiều giảm dần nhưng giữ lại key và hiển thị
$b2 = $b;
arsort($b2);  // Sắp xếp theo chiều giảm dần nhưng giữ lại key
echo "<br>Mảng b sau khi sắp xếp theo chiều giảm dần (giữ lại key):";
showArray($b2);

echo "<hr> Tính tổng ";
$sum_a = array_sum($a);
$sum_b = array_sum($b);
echo "<br> Tổng các giá trị trong mảng a = $sum_a , mảng b = $sum_b ";
?>
