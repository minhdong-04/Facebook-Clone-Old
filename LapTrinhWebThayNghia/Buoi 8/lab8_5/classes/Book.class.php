<?php
class Book extends Db {
    
    // Lấy sách có phân trang
    public function getBooksPaging($page, $limit) {
        // Vì hàm select() của Db dùng execute(array), việc bind số LIMIT đôi khi gây lỗi.
        // Cách an toàn nhất với class Db này là nối chuỗi trực tiếp cho LIMIT và OFFSET.
        $offset = ($page - 1) * $limit;
        
        // Ép kiểu int để bảo mật, tránh SQL Injection
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $sql = "SELECT * FROM book LIMIT $offset, $limit";
        return $this->select($sql);
    }

    // Đếm tổng số sách
    public function countAll() {
        $sql = "SELECT COUNT(*) as total FROM book";
        $result = $this->select($sql);
        // $result là mảng 2 chiều, lấy phần tử đầu tiên
        return $result[0]['total'];
    }
}
?>