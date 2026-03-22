<?php
// Nhận dữ liệu từ GET
$id = isset($_GET['id']) ? $_GET['id'] : 'Chưa có id';

// Ngoài ra, $_REQUEST cũng có thể nhận dữ liệu GET hoặc POST
//$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 'Chưa có id';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Lab5_4_1 – Hiển thị ID</title>
</head>
<body>
<h3>Giá trị id nhận được từ URL:</h3>
<p>ID = <?php echo htmlspecialchars($id); ?></p>
<a href="1.php">Quay lại danh sách</a>
</body>
</html>
