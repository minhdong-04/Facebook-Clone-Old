<?php
class User extends Db {
    
    // Đăng ký thành viên
    public function register($username, $password, $email, $fullname) {
        $sql = "INSERT INTO users (username, password, email, fullname) VALUES (?, ?, ?, ?)";
        // Mật khẩu mã hóa MD5
        $arr = array($username, md5($password), $email, $fullname);
        return $this->insert($sql, $arr);
    }

    // Đăng nhập
    public function login($username, $password) {
        $sql = "SELECT * FROM users WHERE username = ? AND password = ?";
        $arr = array($username, md5($password));
        $result = $this->select($sql, $arr);
        
        // Nếu mảng không rỗng tức là tìm thấy user
        if (count($result) > 0) {
            return $result[0]; // Trả về dòng user đầu tiên tìm thấy
        }
        return false;
    }

    // Đổi tên thành updateUser để tránh trùng với hàm update của Db
    public function updateUser($username, $email, $fullname) {
        $sql = "UPDATE users SET email = ?, fullname = ? WHERE username = ?";
        $arr = array($email, $fullname, $username);
        return $this->update($sql, $arr);
    }
}
?>