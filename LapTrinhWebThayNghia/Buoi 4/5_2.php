<?php
// Khởi tạo mảng 2 chiều
$arr = array();

// Thêm sản phẩm với STT, id và name
$r = array("STT" => 1, "id" => "sp1", "name" => "Sản phẩm 1");
$arr[] = $r;

$r = array("STT" => 2, "id" => "sp2", "name" => "Sản phẩm 2");
$arr[] = $r;

$r = array("STT" => 3, "id" => "sp3", "name" => "Sản phẩm 3");
$arr[] = $r;
?>

<!DOCTYPE html>
<html>

<head>
    <title>5.2 IN mảng 2 chiều với STT</title>
</head>

<body>
    <h3>Danh sách sản phẩm:</h3>

    <!-- Hiển thị bảng mảng -->
    <table border="1" cellspacing="0" cellpadding="5">
        <tr>
            <th>STT</th>
            <th>Mã Sản Phẩm</th>
            <th>Tên Sản Phẩm</th>
        </tr>
        <?php
        // Duyệt qua mảng và in các sản phẩm vào bảng HTML
        foreach ($arr as $row) {
            echo "<tr>";
            echo "<td>" . $row["STT"] . "</td>";
            echo "<td>" . $row["id"] . "</td>";
            echo "<td>" . $row["name"] . "</td>";
            echo "</tr>";
        }
        ?>
    </table>

   

   
    
</body>

</html>
