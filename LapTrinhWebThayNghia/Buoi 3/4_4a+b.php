<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Lab 3_5</title>
<style>
	#banco{border:solid; padding:15px; background:#E8E8E8}
	#banco .cellBlack{width:50px; height:50px; background:black; float:left; }
	#banco .cellWhite{width:50px; height:50px; background:white; float:left}
	.clear{clear:both}
    #banco_new {
        border: solid; 
        padding: 15px; 
        background: #E8E8E8;
        width: <?php echo 8 * 50 + 30;?>px; /* Điều chỉnh width cho phù hợp */
    }
</style>
</head>

<body>
<?php
/*
bảng cửu chương $n, màu nền $color
- Input: $n là một số nguyên dương (1->10)
		 $color: Tên màu nền.Mặc định là green
- Output: Bảng cửu chương, được xuât trong hàm
*/
function BCC($n, $colorHead, $color1="darksalmon", $color2="lightcoral")
{
	?>
	<table border="1" style="border-collapse: collapse; text-align: center;">
	<tr bgcolor="<?php echo $colorHead;?>"><td colspan="3">Bảng cửu chương <?php echo $n;?></td></tr>
	<?php
		for($i=1; $i<=10; $i++)
		{
            $row_color = ($i % 2 != 0) ? $color1 : $color2;
			?>
			<tr bgcolor="<?php echo $row_color;?>">
                <td><?php echo $n;?></td>
				<td><?php echo $i;?></td>
				<td><?php echo $n*$i;?></td>
			</tr>
			<?php
		}
		?>
		</table>
	<?php	
}
function BCC_return($n, $colorHead, $color1="darksalmon", $color2="lightcoral")
{
    $output = '<table border="1" style="border-collapse: collapse; text-align: center;">';
    $output .= '<tr bgcolor="' . $colorHead . '"><td colspan="3">Bảng cửu chương ' . $n . '</td></tr>';
    
    for($i=1; $i<=10; $i++)
    {
        $row_color = ($i % 2 != 0) ? $color1 : $color2;
        $output .= '<tr bgcolor="' . $row_color . '">';
        $output .= '<td>' . $n . '</td>';
        $output .= '<td>' . $i . '</td>';
        $output .= '<td>' . ($n*$i) . '</td>';
        $output .= '</tr>';
    }
    
    $output .= '</table>';
    return $output;
}
/*
Hàm in ra bàn cờ vua với màu các ô thay đổi và được định nghĩa trong css: cellBlack, cellWhite
- Input: $size: kích thước bàn cờ: là 1 số nguyên dương (mặc định là 8)
- Output: bàn cờ HTML 

*/
function BanCo_return($size =8)
{
    $output = "<div id='banco_new'>"; 
    for($i=1; $i<= $size; $i++)
     {
        for($j=1; $j<= $size; $j++)
        {
            $classCss = (($i+$j) %2)==0?"cellWhite":"cellBlack";
            $output .= "<div class='$classCss'> $i - $j</div>";
        }
        $output .= "<div class='clear'></div>"; 
    }
    $output .= "</div>";
    return $output;
}

echo "<h2>1. Bảng Cửu Chương (Xuất Trực Tiếp - BCC)</h2>";
BCC(7, "gold", "pink", "white"); 

echo "<h2>2. Bảng Cửu Chương (Return Chuỗi - BCC_return)</h2>";
echo BCC_return(7, "skyblue", "lightyellow", "lightgreen");

echo "<br><h2>3. Bàn Cờ Vua (Return Chuỗi - BanCo_return)</h2>";
echo BanCo_return(8);
?>
</body>
</html>