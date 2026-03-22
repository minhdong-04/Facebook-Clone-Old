<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>5_1.php</title>
</head>

<body>
<?php
    if(isset($_POST["submit"])) {
        $a = isset($_POST["a"]) && $_POST['a'] !== '' ? $_POST['a']+$_POST['a']:0;
        $b = isset($_POST["b"]) && $_POST['b'] !== '' ? $_POST['b']+$_POST['b']:0;

        if(empty($a) && empty($b)) {
            echo "Cả a và b đều chưa được nhập! <br>";
        } else  {
            if(empty($a)){
                echo "Bạn chưa nhập số a!<br>";
            }else{
                if(is_float($a)) {
                    echo "a là số thực <br>";
                }else{
                    echo 'a là số nguyên <br>';
                }
            }
            if(empty($b)) {
                echo"Bạn chưa nhập số b! <br>";
            }
            iF(!empty($a)&&!empty($b)){
                if($b == 0){
                    echo "Không thể chia cho 0!<br>";
                }else{
                    echo'Phần nguyên a/b = '.floor($a/$b)."<br>";

                    $phandu = $a = (floor($a / $b)*$b);
                    echo 'Phần dư a%b = ' . $phandu. "<br>";
                }
            }
        }
    }
    //$a=10;$b=3;
    //echo "Phần nguyên của $a/$b là: ".floor($a/$b);
    //echo "<br>";
    //echo "Phần dư của $a%$b là: ".$a%$b;
?>
    <form method="post">
        <table>
            <tr>
                <th>Số a</th>
                <th><input type="text" onkeypress=" return validateKkey (event)" ></th>
            </tr>
            <tr>
                <th>Số b</th>
                <th><input type="text" onkeypress=" return validateKkey (event)" ></th>
            </tr>
            </tr>
        </table>
        <input type="sumbit"name="submit" value="submit">
    </form>
</body>
</html>