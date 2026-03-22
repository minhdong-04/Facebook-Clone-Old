<!DOCTYPE html>
<html>

<head>
    <title>5.3 Trắc nghiệm</title>
</head>

<body>
    
    

    <?php
    // Hàm để in câu hỏi ngẫu nhiên
    function generateQuiz($questions, $m)
    {
        // Kiểm tra nếu số câu hỏi cần lấy lớn hơn số câu hỏi có trong mảng
        if ($m > count($questions)) {
            echo "Số câu hỏi cần lấy không thể lớn hơn số câu hỏi trong mảng.";
            return;
        }

        // Lấy m câu hỏi ngẫu nhiên từ mảng
        $randomKeys = array_rand($questions, $m);

        // Nếu chỉ có một câu hỏi được chọn
        if ($m == 1) {
            $randomKeys = array($randomKeys);  // Chuyển thành mảng để dễ duyệt
        }

        // In danh sách câu hỏi ngẫu nhiên
        echo "<form method='post'>";  // Tạo form để gửi câu trả lời
        foreach ($randomKeys as $key) {
            // In câu hỏi
            echo "<h2>" . $questions[$key]['question'] . "</h2>";

            // In các lựa chọn trả lời
            foreach ($questions[$key]['options'] as $index => $option) {
                echo "<input type='radio' name='question_" . $key . "' value='" . $index . "'> " . $option . "</input><br>";
            }
        }
        echo "<br><input type='submit' value='Gửi câu trả lời'></form>";
    }

    // Mảng câu hỏi trắc nghiệm
    $questions = array(
        array(
            'question' => "Câu 1: 1 + 1 = ?",
            'options' => array("1", "2", "22", "4")
        ),
        array(
            'question' => "Câu 2: 1 + 3 = ?",
            'options' => array("1", "44", "3", "4")
        ),
        array(
            'question' => "Câu 4: 1 * 1 = ?",
            'options' => array("1", "22", "133", "234")
        ),
        array(
            'question' => "Câu 5: 1 + 3 = ?",
            'options' => array("1", "49", "3", "4")
        )
    );

    // Số câu hỏi cần lấy ngẫu nhiên từ mảng
    $m = 4; // Thay đổi số lượng câu hỏi lấy ngẫu nhiên theo yêu cầu

    // Gọi hàm generateQuiz để in ra đề thi ngẫu nhiên
    generateQuiz($questions, $m);
    ?>
</body>

</html>
