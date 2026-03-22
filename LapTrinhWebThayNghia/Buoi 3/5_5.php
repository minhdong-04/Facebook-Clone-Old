<?php
/**
 * @param string
 * @return int
 */
function tinh_tong_cac_so_trong_chuoi($str) {
    $tong = 0;
    preg_match_all('/\d+/', $str, $matches);
    
    if (!empty($matches[0])) {
        foreach ($matches[0] as $number_str) {
            $tong += (int)$number_str; 
        }
    }
    return $tong;
}

echo "<h3> Tính tổng các số trong chuỗi:</h3>";
$chuoi = "ngay15thang7nam2015";
$tong_cac_so = tinh_tong_cac_so_trong_chuoi($chuoi);
echo "Chuỗi: '{$chuoi}' <br>";
echo "Tổng các số là: {$tong_cac_so} (15+7+2015 = 2037)";
?>