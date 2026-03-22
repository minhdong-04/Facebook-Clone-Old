<?php
/**
 * 
 * @param string 
 * @return bool 
 */
function kiem_tra_chuoi_doi_xung($str) {
    $clean_str = strtolower(str_replace(' ', '', $str));
    
    $reversed_str = strrev($clean_str);
        return $clean_str === $reversed_str;
}

echo "<h3>Kiểm tra chuỗi đối xứng:</h3>";
$chuoi1 = "abcba";
$chuoi2 = "abcdba";
$chuoi3 = "Madam I'm Adam";

echo "Chuỗi '{$chuoi1}' là: " . (kiem_tra_chuoi_doi_xung($chuoi1) ? "Đối xứng" : "Không đối xứng") . "<br>";
echo "Chuỗi '{$chuoi2}' là: " . (kiem_tra_chuoi_doi_xung($chuoi2) ? "Đối xứng" : "Không đối xứng") . "<br>";
echo "Chuỗi '{$chuoi3}' là: " . (kiem_tra_chuoi_doi_xung($chuoi3) ? "Đối xứng" : "Không đối xứng") . "<br>"; // Palindrome, bỏ qua ký tự
?>