<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Lab 3_4</title>
</head>

<body>
<?php
//Kết hợp hàm và vòng lặp
function kiemtranguyento($x)//Kiểm tra 1 số có nguyên tố hay không
{
	if($x<2)
		return false;
	if($x==2)
		return true;
	//for($i=2;$i<=sqrt($x);$i++)
		//if($x%$i==0)
			//return false;
	$i=2; // Khởi tạo biến lặp
	do
		{
			if($x%$i==0)
				return false;
			$i++; // Tăng biến lặp
		} while($i <= sqrt($x));
	return true;
}

if(kiemtranguyento(17))
	echo  "17 là số nguyên tố";
else
	echo "17 không phải số nguyên tố";
if(kiemtranguyento(15))
	echo "<br> 15 là số nguyên tố";
else
	echo "<br> 15 không phải số nguyên tố";
	
?>
</body>
</html>