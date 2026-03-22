<?php
/**
 *
 * * @param string 
 * @return string 
 */
function loai_bo_khoang_trang_du_thua($str) {
    $trimmed_str = trim($str);
    $clean_str = preg_replace('/\s+/', ' ', $trimmed_str);
    return $clean_str;
}

echo "<h3> Hàm loại bỏ khoảng trắng dư thừa</h3>";

$chuoi_goc = "  Đây   là  một  chuỗi   có  khoảng   trắng   dư thừa.  ";
$chuoi_da_xu_ly = loai_bo_khoang_trang_du_thua($chuoi_goc);

echo "Chuỗi gốc:     '" . $chuoi_goc . "' <br>";
echo "Chuỗi đã xử lý: '" . $chuoi_da_xu_ly . "'";
?>