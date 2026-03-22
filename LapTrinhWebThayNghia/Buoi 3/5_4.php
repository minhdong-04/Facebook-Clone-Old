<?php
/**
 * 
 * @param int 
 * @param int
 */
function ve_hinh_chu_nhat_rong($d, $r) {
    $output = "<h3Hình chữ nhật rỗng d={$d}, r={$r}:</h3>";
    
    for ($i = 1; $i <= $r; $i++) {
        for ($j = 1; $j <= $d; $j++) {
            if ($i == 1 || $i == $r || $j == 1 || $j == $d) {
                $output .= "*";
            } else {
                $output .= "&nbsp;&nbsp;"; 
            }
        }
        $output .= "<br>";
    }
    echo $output;
}

ve_hinh_chu_nhat_rong(6, 4);
?>