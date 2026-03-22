<?php
/**
 * 
 * @param int
 * @param int 
 */
function xuat_n_so_nguyento($n, $start_number = 2) {
    if (!function_exists('kiemtranguyento')) {
        function kiemtranguyento($x) {
            if ($x < 2) return false;
            if ($x == 2) return true;
            for ($i = 2; $i <= sqrt($x); $i++) {
                if ($x % $i == 0) return false;
            }
            return true;
        }
    }

    $count = 0;
    $number = $start_number;
    $prime_numbers = [];
    
    while ($count < $n) {
        if (kiemtranguyento($number)) {
            $prime_numbers[] = $number;
            $count++;
        }
        $number++;
    }
    
    echo "<h3>{$n} số nguyên tố đầu tiên là:</h3>";
    echo implode(", ", $prime_numbers);
}

xuat_n_so_nguyento(10);
?>