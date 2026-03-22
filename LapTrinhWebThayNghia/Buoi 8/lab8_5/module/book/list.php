<?php
$bookObj = new Book();

// 1. Cấu hình phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 5; // Số sách mỗi trang

// 2. Lấy dữ liệu
$listBooks = $bookObj->getBooksPaging($page, $limit);
$totalBooks = $bookObj->countAll();
$totalPages = ceil($totalBooks / $limit);

// 3. Hiển thị danh sách
echo "<h3>Danh sách Sách (Trang $page/$totalPages)</h3>";
echo "<ul>";
foreach ($listBooks as $b) {
    echo "<li>";
    echo "<b>" . $b['book_name'] . "</b> - Giá: " . number_format($b['price']) . " đ ";
    echo "[<a href='index.php?mod=cart&act=add&id=" . $b['book_id'] . "'>Mua ngay</a>]";
    echo "</li>";
}
echo "</ul>";

// 4. Hiển thị thanh phân trang
echo "<div style='margin-top:20px;'>Trang: ";
for ($i = 1; $i <= $totalPages; $i++) {
    // Tô đậm trang hiện tại
    if ($i == $page) {
        echo "<b>[$i]</b> ";
    } else {
        echo "<a href='index.php?mod=book&act=list&page=$i'>$i</a> ";
    }
}
echo "</div>";
?>