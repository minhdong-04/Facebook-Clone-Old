<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tìm kiếm sách</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        table, th, td {
            border: 1px solid black;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>

    <h2>Tìm kiếm sách theo tên</h2>

    <!-- Form nhập tên sách -->
    <form action="" method="POST">
        <label for="book_name">Nhập tên sách:</label>
        <input type="text" id="book_name" name="book_name" required>
        <input type="submit" value="Tìm kiếm">
    </form>

    <?php
    // Kết nối cơ sở dữ liệu
    try {
        $pdh = new PDO("mysql:host=localhost; dbname=bookstore", "root", "");
        $pdh->query("set names 'utf8'");
    } catch (Exception $e) {
        echo "Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage();
        exit;
    }

    // Kiểm tra nếu người dùng đã gửi form
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Lấy tên sách từ form và làm sạch đầu vào
        $book_name = htmlspecialchars($_POST['book_name']); 

        if (empty($book_name)) {
            echo "<p>Vui lòng nhập tên sách để tìm kiếm.</p>";
        } else {
            // Câu lệnh SQL tìm kiếm sách
            $sql = "SELECT * FROM book WHERE book_name LIKE :book_name";
            $stm = $pdh->prepare($sql);
            $stm->bindValue(":book_name", "%" . $book_name . "%"); // Thêm % để tìm kiếm theo tên gần đúng
            $stm->execute();

            // Lấy kết quả và hiển thị
            $rows = $stm->fetchAll(PDO::FETCH_ASSOC);
            
            if ($rows) {
                echo "<h3>Kết quả tìm kiếm:</h3>";
                echo "<table>
                        <tr>
                            <th>Mã sách</th>
                            <th>Tên sách</th>
                            <th>Mô tả</th>
                            <th>Giá</th>
                            <th>Hình ảnh</th>
                        </tr>";

                foreach ($rows as $row) {
                    $imagePath = !empty($row['img']) ? 'images/' . $row['img'] : 'images/default.jpg';
                    echo "<tr>
                            <td>{$row['book_id']}</td>
                            <td>{$row['book_name']}</td>
                            <td>{$row['description']}</td>
                            <td>{$row['price']}</td>
                            <td><img src='$imagePath' alt='{$row['book_name']}' width='100' height='150'></td>

                        </tr>";
                }

                echo "</table>";
            } else {
                echo "<p>Không tìm thấy sách nào với tên: <strong>$book_name</strong></p>";
            }
        }
    }
    ?>

</body>
</html>
