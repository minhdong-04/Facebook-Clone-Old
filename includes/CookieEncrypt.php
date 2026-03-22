<?php

class CookieEncrypt
{
    private $key = "my-secret-key-32bytes!!!!123456"; // phải 32 ký tự
    private $except = [];

    public function set($name, $value, $expire = 3600, $path = "/")
    {
        if (in_array($name, $this->except)) {
            setcookie($name, $value, time() + $expire, $path);
            return;
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, "AES-256-CBC", $this->key, 0, $iv);
        $cookieValue = base64_encode($iv . $encrypted);

        setcookie($name, $cookieValue, time() + $expire, "/", "", false, true);
    }

    public function get($name)
    {
        if (!isset($_COOKIE[$name])) return null;

        if (in_array($name, $this->except)) return $_COOKIE[$name];

        $data = base64_decode($_COOKIE[$name]);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt($encrypted, "AES-256-CBC", $this->key, 0, $iv);
    }

    public function delete($name)
    {
        setcookie($name, "", time() - 3600, "/");
    }

    public function except($name)
    {
        $this->except[] = $name;
    }
}
