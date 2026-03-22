<?php
// ==================== HÀM HIỂN THỊ MẢNG DẠNG BẢNG HTML ====================
function showArray($arr)
{
    echo "<table border='1' cellpadding='5'>";      // Bắt đầu bảng HTML
    echo "<tr><th>STT</th><th>Key</th><th>Value</th></tr>"; // Tiêu đề cột

    $i = 0; // Biến đếm STT
    foreach($arr as $key => $value)
    {
        $i++; // Tăng STT
        echo "<tr>";              // Bắt đầu dòng
        echo "<td>$i</td>";       // Cột STT
        echo "<td>$key</td>";     // Cột key
        echo "<td>$value</td>";   // Cột value
        echo "</tr>";             // Kết thúc dòng
    }

    echo "</table>";               // Kết thúc bảng
}

// ==================== TẠO MẢNG ====================
$arr = array(10, 20, 30, 40, 50); // Mảng số

// ==================== GỌI HÀM HIỂN THỊ ====================
echo "<h3>Mảng một chiều hiển thị dạng bảng HTML:</h3>";
showArray($arr);
?>
