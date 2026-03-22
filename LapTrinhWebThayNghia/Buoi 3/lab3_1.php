<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Lab 3_1</title>
</head>

<body>
<?php
	//Tính biểu thức S=1 + 2 + 3 + ... + 100
	// Sử dụng FOR
	$s1=0;
	for($i=1;$i<=100;$i++)
		$s1+=$i;
	$sum=0;
	for ($i = 2; $i <= 100; $i += 2) {
        $sum += $i;
    }
	//Sử dụng while
	$i=1;
	$s2=0;
	while($i<=100)
	{
		$s2+=$i;
		$i++;
	}
	//Sử dụng DO...WHILE
	$sum_n = 0;
    $n = 0;
	$i=1;
	$s3=0;
	do {
        $n++;
        $sum_n += $n;
    } while ($sum_n <= 1000);
	do
	{
		$s3+=$i;
		$i++;
	}while($i<=100);
	echo "Kết quả S = 1 + 2 + 3 + ... + 100 <br/>";
	echo "Tính bằng FOR, S1 = $s1 <br/>";
	echo "Tính bằng WHILE, S2 = $s2 <br/>";
	echo "Tính bằng DO...WHILE, S3 = $s3 <br/>";
	echo "Tổng các số chẵn từ 2 đến 100 (FOR) là: $sum";
	echo "<br>Giá trị n nhỏ nhất để 1 + 2 + ... + n > 1000 là: $n"; // Kết quả là 45
    echo "<br>Tổng S = $sum_n";
 ?>
</body>
</html> 