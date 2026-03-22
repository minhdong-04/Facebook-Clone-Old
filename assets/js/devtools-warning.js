(function () {
  let devtoolsOpen = false;
  const threshold = 160;

  setInterval(() => {
    const widthDiff = window.outerWidth - window.innerWidth;
    const heightDiff = window.outerHeight - window.innerHeight;

    if (widthDiff > threshold || heightDiff > threshold) {
      if (!devtoolsOpen) {
        devtoolsOpen = true;

        // Giống Facebook: in cảnh báo trong console
        console.clear();
        console.log(
          "%cDỪNG LẠI!",
          "color:red;font-size:40px;font-weight:bold;"
        );
       console.log(
         '%cĐây là một tính năng của trình duyệt dành cho các nhà phát triển. Nếu ai đó bảo bạn sao chép-dán nội dung nào đó vào đây để bật một tính năng của Facebook hoặc "hack" tài khoản của người khác, thì đó là hành vi lừa đảo và sẽ khiến họ có thể truy cập vào tài khoản Facebook của bạn.',
         "font-size:17px;"
       );

        console.log(
          "%cXem https://www.facebook.com/selfxss để biết thêm thông tin.",
          "font-size:20px;"
        );
      }
    } else {
      devtoolsOpen = false;
    }
  }, 1000);
})();
