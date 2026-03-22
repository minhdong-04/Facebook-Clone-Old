<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>5_3.php</title>
</head>
<body>
<?php
    if(isset($_POST["submit"])) {
        $a = $_POST["a"];
        $b = $_POST["b"];
        $c = $_POST["c"];

        if($a == 0) {
            echo "Đây không phải phương trình bậc 2<br>";
        } else {
            $delta = $b*$b - 4*$a*$c;
            echo "Delta = $delta<br>";
            if($delta < 0) {
                echo "Phương trình vô nghiệm<br>";
            } elseif($delta == 0) {
                $x = -$b / (2*$a);
                echo "Phương trình có nghiệm kép: x = $x<br>";
            } else {
                $x1 = (-$b + sqrt($delta)) / (2*$a);
                $x2 = (-$b - sqrt($delta)) / (2*$a);
                echo "Phương trình có 2 nghiệm phân biệt:<br>";
                echo "x1 = $x1<br>";
                echo "x2 = $x2<br>";
            }
        }
    }
?>
<form method="post">
    <table>
        <tr>
            <th>Nhập a</th>
            <th><input type="text" name="a"></th>
        </tr>
        <tr>
            <th>Nhập b</th>
            <th><input type="text" name="b"></th>
        </tr>
        <tr>
            <th>Nhập c</th>
            <th><input type="text" name="c"></th>
        </tr>
    </table>
    <input type="submit" name="submit" value="Giải phương trình">
</form>
</body>
</html>
