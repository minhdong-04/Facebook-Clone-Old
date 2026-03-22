<?php
$opts = ["http" => ["header" => "User-Agent: Mozilla/5.0\r\n"]];
$context = stream_context_create($opts);
$content = file_get_contents("https://vnexpress.net", false, $context);

$pattern_link = '/https?:\/\/[^\s"]+/i';
preg_match_all($pattern_link, $content, $links);
echo "<h3>Danh sách link:</h3><pre>";
print_r($links[0]);
echo "</pre>";


$pattern_email = '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i';
preg_match_all($pattern_email, $content, $emails);
echo "<h3>Danh sách email:</h3><pre>";
print_r($emails[0]);
echo "</pre>";

$pattern_phone = '/\b\d{9,11}\b/';
preg_match_all($pattern_phone, $content, $phones);
echo "<h3>Danh sách số điện thoại:</h3><pre>";
print_r($phones[0]);
echo "</pre>";

$pattern_img = '/[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|gif|bmp)/i';
preg_match_all($pattern_img, $content, $imgs);
echo "<h3>Danh sách hình ảnh hợp lệ:</h3><pre>";
print_r($imgs[0]);
echo "</pre>";
?>
