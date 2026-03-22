<?php

class Db {
    protected $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host=localhost; dbname=bookstore", "root", "");
            $this->pdo->query("SET NAMES 'utf8'");
        } catch (Exception $e) {
            echo "Lỗi kết nối: " . $e->getMessage();
            exit;
        }
    }

    // Phương thức thực thi câu lệnh SELECT
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // Phương thức thực thi câu lệnh INSERT, UPDATE, DELETE
    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
