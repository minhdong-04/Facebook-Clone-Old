<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Quản lý sách</title>
<style>
#container{width:800px; margin:0 auto;}
</style>
</head>

<body>
<div id="container">

<h3>Thêm sách mới</h3>
<form action="lab8_4.php" method="post" enctype="multipart/form-data">
    <table>
        <tr><td>Mã sách:</td><td><input type="text" name="book_id" /></td></tr>
        <tr><td>Tên sách:</td><td><input type="text" name="book_name" /></td></tr>
        <tr><td>Mô tả:</td><td><textarea name="description"></textarea></td></tr>
        <tr><td>Giá:</td><td><input type="text" name="price" /></td></tr>
        <tr><td>Hình ảnh:</td><td><input type="file" name="img" /></td></tr>
        <tr><td>Nhà xuất bản:</td>
            <td>
                <select name="pub_id">
                    <?php
                    // Lấy danh sách nhà xuất bản từ cơ sở dữ liệu
                    try {
                        $pdh = new PDO("mysql:host=localhost; dbname=bookstore", "root", "");
                        $pdh->query("SET NAMES 'utf8'");
                    } catch (Exception $e) {
                        echo $e->getMessage();
                        exit;
                    }

                    $stm = $pdh->prepare("SELECT * FROM publisher");
                    $stm->execute();
                    $publishers = $stm->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($publishers as $publisher) {
                        echo "<option value='{$publisher['pub_id']}'>{$publisher['pub_name']}</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr><td>Danh mục sách:</td>
            <td>
                <select name="cat_id">
                    <?php
                    // Lấy danh sách thể loại sách từ cơ sở dữ liệu
                    $stm = $pdh->prepare("SELECT * FROM category");
                    $stm->execute();
                    $categories = $stm->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($categories as $category) {
                        echo "<option value='{$category['cat_id']}'>{$category['cat_name']}</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <tr><td colspan="2"> <input type="submit" name="sm" value="Thêm sách" /></td></tr>
    </table>
</form>

<?php
if (isset($_POST["sm"])) {
    // Lấy dữ liệu từ form
    $book_id = $_POST["book_id"];
    $book_name = $_POST["book_name"];
    $description = $_POST["description"];
    $price = $_POST["price"];
    $pub_id = $_POST["pub_id"];
    $cat_id = $_POST["cat_id"];

    // Xử lý file ảnh
    if (isset($_FILES['img']) && $_FILES['img']['error'] == 0) {
        $img = $_FILES['img']['name'];
        move_uploaded_file($_FILES['img']['tmp_name'], 'images/' . $img);
    } else {
        $img = ''; // Nếu không có ảnh, để trống
    }

    // Thêm sách vào cơ sở dữ liệu
    $sql = "INSERT INTO book (book_id, book_name, description, price, img, pub_id, cat_id) 
            VALUES (:book_id, :book_name, :description, :price, :img, :pub_id, :cat_id)";
    $arr = array(
        ":book_id" => $book_id,
        ":book_name" => $book_name,
        ":description" => $description,
        ":price" => $price,
        ":img" => $img,
        ":pub_id" => $pub_id,
        ":cat_id" => $cat_id
    );
    $stm = $pdh->prepare($sql);
    $stm->execute($arr);
    $n = $stm->rowCount();
    if ($n > 0) {
        echo "Đã thêm $n sách mới.";
    } else {
        echo "Lỗi thêm sách.";
    }
}

// Hiển thị danh sách sách
$stm = $pdh->prepare("SELECT * FROM book");
$stm->execute();
$books = $stm->fetchAll(PDO::FETCH_ASSOC);
?>

<h3>Danh sách sách</h3>
<table border="1">
    <tr>
        <th>Mã sách</th>
        <th>Tên sách</th>
        <th>Mô tả</th>
        <th>Giá</th>
        <th>Hình ảnh</th>
        <th>Nhà xuất bản</th>
        <th>Danh mục</th>
        <th>Thao tác</th>
    </tr>

<?php
foreach ($books as $book) {
    $imagePath = !empty($book['img']) ? 'images/' . $book['img'] : 'images/default.jpg'; // Đường dẫn hình ảnh
    echo "<tr>
            <td>{$book['book_id']}</td>
            <td>{$book['book_name']}</td>
            <td>{$book['description']}</td>
            <td>{$book['price']}</td>
            <td><img src='$imagePath' alt='{$book['book_name']}' width='100' height='150'></td>
            <td>{$book['pub_id']}</td>
            <td>{$book['cat_id']}</td>
            <td><a href='lab8_4.php?book_id={$book['book_id']}'>Xóa</a></td>
          </tr>";
}
?>

</table>

<?php
// Xử lý xóa sách
if (isset($_GET['book_id'])) {
    $book_id = $_GET['book_id'];
    $sql = "DELETE FROM book WHERE book_id = :book_id";
    $stm = $pdh->prepare($sql);
    $stm->execute(array(":book_id" => $book_id));
    $n = $stm->rowCount();
    if ($n > 0) {
        echo "Đã xóa $n sách.";
        header("Location: lab8_4.php"); // Tải lại trang sau khi xóa
    } else {
        echo "Lỗi xóa sách.";
    }
}
?>

</div>
</body>
</html>
