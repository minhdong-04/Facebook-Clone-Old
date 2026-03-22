<?php

try{
$pdh = new PDO("mysql:host=localhost; dbname=bookstore"  , "root"  , ""  );
$pdh->query("  set names 'utf8'"  );
}
catch(Exception $e){
		echo $e->getMessage(); exit;
}

$cat_id = isset($_GET["cat_id"])?$_GET["cat_id"]:"";
$sql ="delete from category where cat_id = :cat_id ";
$arr = array(":cat_id"=>$cat_id);

$stm = $pdh->prepare($sql);
$stm->execute($arr);
$n = $stm->rowCount();
if ($n>0) $thongbao="Da xoa $n loai sach! ";
else $thongbao="Loi xoa!";
?>
<script language="javascript">
alert("<?php echo $thongbao;?>");
window.location = "lab8_3.php";
</script>