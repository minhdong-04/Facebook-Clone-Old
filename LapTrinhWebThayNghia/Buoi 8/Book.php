<?php

class Book extends Db {

    // Phương thức thêm sách mới
    public function addBook($book_id, $book_name, $description, $price, $img, $pub_id, $cat_id) {
        $sql = "INSERT INTO book (book_id, book_name, description, price, img, pub_id, cat_id) 
                VALUES (:book_id, :book_name, :description, :price, :img, :pub_id, :cat_id)";
        $params = [
            ':book_id' => $book_id,
            ':book_name' => $book_name,
            ':description' => $description,
            ':price' => $price,
            ':img' => $img,
            ':pub_id' => $pub_id,
            ':cat_id' => $cat_id
        ];
        return $this->execute($sql, $params);
    }

    // Phương thức lấy danh sách sách
    public function getBooks() {
        $sql = "SELECT * FROM book";
        $stmt = $this->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Phương thức xóa sách
    public function deleteBook($book_id) {
        $sql = "DELETE FROM book WHERE book_id = :book_id";
        $params = [':book_id' => $book_id];
        return $this->execute($sql, $params);
    }
}
