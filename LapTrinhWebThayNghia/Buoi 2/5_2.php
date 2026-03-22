<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>5_2.php</title>
</head>
<body>
<?php
    if(isset($_POST["submit"])) {
        if(isset($_POST["a"]) && $_POST["a"] !== '') {
            $a = $_POST["a"];
            if(filter_var($a, FILTER_VALIDATE_INT) !== false) {
                echo "a = $a là số nguyên<br>";
            } elseif(filter_var($a, FILTER_VALIDATE_FLOAT) !== false) {
                echo "a = $a là số thực<br>";
            } else {
                echo "Giá trị nhập vào không phải số<br>";
            }
        } else {
            echo "Bạn chưa nhập số a!<br>";
        }
    }
?>
<form method="post">
    <table>
        <tr>
            <th>Nhập số a</th>
            <th><input type="text" name="a"></th>
        </tr>
    </table>
    <input type="submit" name="submit" value="Kiểm tra">
</form>
</body>
</html>
