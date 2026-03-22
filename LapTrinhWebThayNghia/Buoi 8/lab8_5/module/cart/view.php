<?php
// Lấy chi tiết giỏ hàng
$list = $cart->getDetail();
?>
<h3>Giỏ hàng của bạn</h3>
<table border="1" cellspacing="0" cellpadding="5">
    <tr>
        <th>Tên sách</th>
        <th>Giá</th>
        <th>Số lượng</th>
        <th>Thành tiền</th>
        <th>Xóa</th>
    </tr>
    <?php 
    $total = 0;
    foreach($list as $item) { 
        $total += $item['subtotal'];
    ?>
    <tr>
        <td><?php echo $item['book_name']; ?></td>
        <td><?php echo number_format($item['price']); ?></td>
        <td><?php echo $item['qty']; ?></td>
        <td><?php echo number_format($item['subtotal']); ?></td>
        <td><a href="index.php?mod=cart&act=delete&id=<?php echo $item['book_id']; ?>">Xóa</a></td>
    </tr>
    <?php } ?>
    <tr>
        <td colspan="3" align="right"><b>Tổng tiền:</b></td>
        <td colspan="2"><b><?php echo number_format($total); ?> đ</b></td>
    </tr>
</table>
<a href="index.php?mod=book&act=list">Tiếp tục mua hàng</a>