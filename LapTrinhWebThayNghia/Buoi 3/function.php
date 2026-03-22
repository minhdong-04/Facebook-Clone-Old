<?php
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

function kiemtrasonguyento_toiuu ($x)
{
    if ($x < 2) return false;
    if ($x == 2) return true;
    if ($x % 2 == 0) return false;

    for($i = 3;$i <= sqrt($x);$i += 2)
        if($x % $i == 0)
            return false;
        return true;
}

function kiemtrasonguyento_GMP($x){
    return gmp_prob_prime($x) >0;
}
?>