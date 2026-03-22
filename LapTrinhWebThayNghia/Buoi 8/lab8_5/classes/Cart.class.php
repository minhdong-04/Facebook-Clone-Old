<?php
class Cart extends Db {
    public function __construct() {
        parent::__construct();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    public function add($id, $qty = 1) {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id] += $qty;
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
    }

    public function remove($id) {
        if (isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
        }
    }

    public function getDetail() {
        // Nếu giỏ hàng trống thì trả về mảng rỗng ngay
        if (empty($_SESSION['cart'])) return [];
        
        // 1. Lấy tất cả key (là book_id) từ session
        $ids = array_keys($_SESSION['cart']);
        
        // 2. [SỬA LỖI TẠI ĐÂY] Thêm dấu nháy đơn cho từng ID
        // Biến đổi array từ [td01, td02] thành ['td01', 'td02']
        $ids = array_map(function($item) {
            return "'$item'"; 
        }, $ids);
        
        // 3. Nối lại thành chuỗi: 'td01','td02'
        $str_ids = implode(',', $ids);
        
        // Câu lệnh SQL lúc này sẽ đúng cú pháp: SELECT * FROM book WHERE book_id IN ('td01')
        $sql = "SELECT * FROM book WHERE book_id IN ($str_ids)";
        $books = $this->select($sql);

        // 4. Tính toán số lượng và thành tiền
        foreach ($books as &$book) {
            if (isset($_SESSION['cart'][$book['book_id']])) {
                $book['qty'] = $_SESSION['cart'][$book['book_id']];
                $book['subtotal'] = $book['price'] * $book['qty'];
            }
        }
        return $books;
    }
}
?>