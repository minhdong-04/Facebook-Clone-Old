<?php
class News extends Db {
    public function getAll() {
        return $this->select("SELECT * FROM news ORDER BY id DESC");
    }

    public function getDetail($id) {
        $sql = "SELECT * FROM news WHERE id = ?";
        $result = $this->select($sql, array($id));
        if (count($result) > 0) return $result[0];
        return null;
    }
}
?>