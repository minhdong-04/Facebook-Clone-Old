<?php
/**
 * 
 * @param string 
 * @return int
 */
function tinhtongchusotrongchuoi($str) {
    $tong = 0;
    preg_match_all('/\d/', $str, $matches);
    
    if (!empty($matches[0])) {
        foreach ($matches[0] as $digit) {
            $tong += (int)$digit; 
        }
    }
    return $tong;
}

echo "<h3>Tính tổng các chữ số trong chuỗi:</h3>";
$chuoi = "ngay15thang7nam2015";
$tongchuso = tinhtongchusotrongchuoi($chuoi);
echo "Chuỗi: '{$chuoi}' <br>";
echo "Tổng các chữ số là: {$tongchuso} (1+5+7+2+0+1+5 = 21)";
?>