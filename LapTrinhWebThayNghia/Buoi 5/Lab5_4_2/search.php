<?php
// Nhận dữ liệu từ GET
$proname = isset($_GET['proname']) ? $_GET['proname'] : '';
$searchType = isset($_GET['searchType']) ? $_GET['searchType'] : 'gan_dung'; // Gần đúng mặc định
$category = isset($_GET['category']) ? $_GET['category'] : 'tatca';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Lab5_4_2 – Tìm kiếm sản phẩm</title>
</head>
<body>
<h3>Form tìm kiếm sản phẩm</h3>

<form action="search.php" method="get">
    <label>Tên sản phẩm:</label>
    <input type="text" name="proname" value="<?php echo htmlspecialchars($proname); ?>"><br><br>
    
    <label>Cách tìm:</label>
    <input type="radio" name="searchType" value="gan_dung" <?php if($searchType=='gan_dung') echo 'checked'; ?>> Gần đúng
    <input type="radio" name="searchType" value="chinh_xac" <?php if($searchType=='chinh_xac') echo 'checked'; ?>> Chính xác<br><br>
    
    <label>Loại sản phẩm:</label>
    <select name="category">
        <option value="tatca" <?php if($category=='tatca') echo 'selected'; ?>>Tất cả</option>
        <option value="loai1" <?php if($category=='loai1') echo 'selected'; ?>>Loại 1</option>
        <option value="loai2" <?php if($category=='loai2') echo 'selected'; ?>>Loại 2</option>
        <option value="loai3" <?php if($category=='loai3') echo 'selected'; ?>>Loại 3</option>
    </select><br><br>
    
    <input type="submit" value="Tìm kiếm">
</form>

<hr>
<h3>Kết quả nhập:</h3>
<p>Tên sản phẩm: <b><?php echo htmlspecialchars($proname); ?></b></p>
<p>Cách tìm: <b><?php echo ($searchType=='gan_dung') ? 'Gần đúng' : 'Chính xác'; ?></b></p>
<?php
if($category != 'tatca'){
    echo "<p>Loại sản phẩm: <b>$category</b></p>";
}

// Ví dụ hiển thị thông tin server
echo "<hr><p>Thông tin server (ví dụ $_SERVER):<br>";
echo "Phương thức gửi form: " . $_SERVER['REQUEST_METHOD'] . "<br>";
echo "File hiện tại: " . $_SERVER['PHP_SELF'] . "</p>";
?>
</body>
</html>
