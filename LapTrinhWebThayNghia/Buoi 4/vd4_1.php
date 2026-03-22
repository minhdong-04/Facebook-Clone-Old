<?php
$a = array();// mảng rỗng
$b = array(1, 3, 5); // mảng có 3 phần tử
/*
$b[0] = 1;
$b[1] = 3;
$b[2] = 5;
*/
$c = array("a"=>2, "b"=>4, "c"=>6); // mảng có 3 phần tử. Các index của mảng là chuỗi
/*
$c['a'] = 2;
$c['b'] = 4;
$c['c'] = 6;
*/

$na = Count($a);
$nb = Count($b);
$nc = Count($c);
echo "Mảng a có $na phần tử <br> ";
echo "Mảng b có $nb phần tử <br> ";
echo "Mảng c có $nc phần tử <br> ";
print_r($a);
var_dump($b);
print_r($c);
$a[] = 3;
$b[] = 7;
$c['d'] = 8;
echo "<hr> Sau khi thêm phần tử, nội dung các mảng  là :";
print_r($a);
print_r($b);
print_r($c);

$x = 1;
unset($a[$x]);
unset($b[$x]);
unset($c['a']);
echo "<hr> Sau khi xóa phần tử, nội dung các mảng  là :";
print_r($a);
print_r($b);
print_r($c);

$value = 2;
$key = 'b';
if (isset($c[$key])) {
    // Nếu phần tử tồn tại, thay đổi giá trị của nó
    $c[$key] += $value;
} else {
    // Nếu phần tử không tồn tại, thêm phần tử mới
    $c[$key] = $value;
}
echo "<hr> Kết quả mảng c sau khi thay đổi là:";
print_r($c);

// Kiểm tra và thay đổi phần tử trong mảng
$searchValue = 4; // Giá trị cần tìm
$searchKey = 'b'; // Khoá cần kiểm tra

// Kiểm tra xem phần tử có tồn tại trong mảng hay không
if (in_array($searchValue, $b)) {
    echo "<hr> Phần tử $searchValue tồn tại trong mảng b.<br>";
    // Nếu tồn tại, xóa phần tử
    $keyToRemove = array_search($searchValue, $b); // Tìm khóa của phần tử cần xóa
    unset($b[$keyToRemove]);
    echo "Mảng b sau khi xóa phần tử $searchValue: ";
    print_r($b);
} else {
    echo "<hr> Phần tử $searchValue không tồn tại trong mảng b.<br>";
}

// Kiểm tra và thay đổi dữ liệu trong mảng $c
if (array_key_exists($searchKey, $c)) {
    // Nếu khoá tồn tại, thay đổi giá trị của nó
    $c[$searchKey] = 10; // Thay đổi giá trị của phần tử có khoá 'b' thành 10
    echo "<hr> Sau khi thay đổi giá trị của khoá '$searchKey', mảng c là: ";
    print_r($c);
} else {
    // Nếu khoá không tồn tại, thêm khoá và giá trị mới vào mảng
    $c[$searchKey] = 5;
    echo "<hr> Khoá '$searchKey' không tồn tại, đã thêm phần tử mới. Mảng c là: ";
    print_r($c);
}

?>
