<?php
// Tạo context với User-Agent để giả lập trình duyệt
$opts = [
    "http" => [
        "header" => "User-Agent: Mozilla/5.0\r\n"
    ]
];
$context = stream_context_create($opts);

// Lấy nội dung trang thể thao VnExpress
$content = file_get_contents("https://vnexpress.net/the-thao", false, $context);

// Regex tìm các thẻ tiêu đề tin tức
$pattern = '/<h3 class="title-news">.*?<\/h3>/is';
preg_match_all($pattern, $content, $arr);

// Hiển thị kết quả
echo "<h2>Danh sách tiêu đề tin tức</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>STT</th><th>Tiêu đề</th></tr>";

$i = 1;
foreach($arr[0] as $item){
    // Lọc lấy text bên trong thẻ <a>
    if(preg_match('/<a[^>]*>(.*?)<\/a>/is', $item, $m)){
        echo "<tr><td>".$i++."</td><td>".$m[1]."</td></tr>";
    }
}
echo "</table>";
?>
