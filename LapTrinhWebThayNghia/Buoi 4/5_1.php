<?php
// Khởi tạo mảng rỗng hoặc lấy mảng từ session nếu có
session_start();

// Kiểm tra nếu mảng đã tồn tại trong session, nếu không tạo mảng mới
if (!isset($_SESSION['array'])) {
    $_SESSION['array'] = array();
}

// Xử lý khi người dùng nhấn nút "Thêm"
if (isset($_POST['submit'])) {
    // Lấy giá trị index và value từ form
    $index = $_POST['index'];
    $value = $_POST['value'];

    // Thêm phần tử vào mảng với key là index
    $_SESSION['array'][$index] = $value;
}

// Xử lý khi người dùng nhấn nút "Hủy"
if (isset($_POST['reset'])) {
    // Xóa mảng
    unset($_SESSION['array']);
}

// Xử lý khi người dùng nhấn nút "Xóa" cho phần tử
if (isset($_POST['delete_index'])) {
    $index_to_delete = $_POST['delete_index'];  // Lấy index của phần tử cần xóa
    unset($_SESSION['array'][$index_to_delete]);  // Xóa phần tử khỏi mảng
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>5.1 showArray</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
</head>

<body class="container mt-3">
    <h4>Nhập Index và Value vào Mảng</h4>
    <h6><em>Có sử dụng Bootstrap định dạng form và table</em></h6>

    <!-- Form nhập Index và Value -->
    <form method="post" action="" class="w-auto">
        <span class="input-group">
            <label class="input-group-text" for="index">Index:</label>
            <input class="form-control w-auto" type="text" id="index" size="5" name="index" required>
            <label class="input-group-text" for="value">Value:</label>
            <input class="form-control w-auto" type="text" id="value" size="5" name="value" required>
            <span><input class="btn btn-primary mx-2" type="submit" name="submit" value="Thêm"></span><br>
        </span>
    </form>

    <!-- Tạo form riêng để Hủy không yêu cầu nhập liệu -->
    <form action="" method="post" class="mt-3 mb-3">
        <input class="btn btn-danger" type="submit" name="reset" value="Hủy"><br>
    </form>

    <!-- Hiển thị bảng mảng -->
    <table class='table table-striped table-bordered table-hover' border=1>
        <tr class='table-dark'>
            <th>Index</th>
            <th>Value</th>
            <th>Action</th>
        </tr>
        <?php
        // Kiểm tra nếu mảng không rỗng
        if (!empty($_SESSION['array'])) {
            // Duyệt qua mảng và in ra bảng
            foreach ($_SESSION['array'] as $index => $value) {
                echo "<tr><td>{$index}</td><td>{$value}</td>";
                // Thêm nút Xóa vào cột Action
                echo "<td>
                        <form method='post' style='display:inline;'>
                            <input type='hidden' name='delete_index' value='{$index}'>
                            <input class='btn btn-danger btn-sm' type='submit' value='Xóa'>
                        </form>
                      </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='3' class='text-center'>Mảng rỗng!</td></tr>";
        }
        ?>
    </table>
</body>

</html>
