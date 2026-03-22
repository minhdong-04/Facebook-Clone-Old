<?php
function getTitles($url, $pattern){
    $opts = ["http" => ["header" => "User-Agent: Mozilla/5.0\r\n"]];
    $context = stream_context_create($opts);
    $content = file_get_contents($url, false, $context);

    preg_match_all($pattern, $content, $arr);
    return $arr[1]; 
}

$url = "https://zingnews.vn/xa-hoi.html";
$pattern = '/<h3 class="article-title"><a[^>]*>(.*?)<\/a><\/h3>/is';
$titles = getTitles($url, $pattern);

echo "<h2>Tiêu đề tin mục Xã hội – Zing News</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>STT</th><th>Tiêu đề</th></tr>";
$i=1;
foreach($titles as $t){
    echo "<tr><td>".$i++."</td><td>".$t."</td></tr>";
}
echo "</table>";
?>
