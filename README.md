# Facebook Clone (PHP + MySQL + Socket.IO)

Ứng dụng mạng xã hội mô phỏng Facebook, xây dựng bằng PHP thuần (server-rendered), MySQL và Socket.IO cho realtime chat/presence/call signaling.

## 1) Tính năng chính

- Đăng ký, đăng nhập, xác thực email, quên mật khẩu.
- News feed: tạo bài viết, ảnh/video, like/reaction, bình luận.
- Kết bạn: gửi/chấp nhận/từ chối lời mời.
- Messenger: nhắn tin realtime, trạng thái online, read receipt.
- WebRTC signaling: hỗ trợ cuộc gọi audio/video qua Socket.IO.
- Giao diện theo mô hình page + AJAX endpoint.

---

## 2) Kiến trúc thư mục

```text
fb/
├─ index.php                # redirect vào auth/login.php
├─ auth/                    # login/register/verify/forgot/reset
├─ pages/                   # các trang chính: home/profile/chat/friends/...
├─ actions/                 # endpoint AJAX (POST JSON/HTML fragment)
├─ api/                     # endpoint tìm kiếm / tra cứu
├─ includes/                # db, header/footer, language, socket emit, webrtc
├─ assets/                  # css/js/images tĩnh
├─ mysql/                   # schema + script tối ưu index
├─ socket/                  # Node.js Socket.IO server (realtime)
├─ uploads/                 # media upload của user
├─ logs/                    # log runtime
└─ vendor/PHPMailer-master/ # thư viện mail
```

---

## 3) Yêu cầu môi trường

### Bắt buộc
- PHP 8.1+ (khuyến nghị 8.2+), bật extension: `pdo_mysql`, `openssl`, `mbstring`, `curl`.
- MySQL 8+ (hoặc MariaDB tương thích).
- Node.js 18+ (cho realtime socket server).
- Web server: Apache (XAMPP/Laragon) hoặc Nginx + PHP-FPM.

### Khuyến nghị khi dev local
- Windows: XAMPP/Laragon cho PHP + MySQL.
- Chạy Node socket bằng terminal riêng trong thư mục `socket/`.

---

## 4) Cài đặt nhanh (Local)

### Bước 1: Clone source

```bash
git clone git@github.com:minhdong-04/Facebook-Clone-Old.git
cd Facebook-Clone-Old
```

### Bước 2: Tạo database

1. Tạo DB và bảng bằng script:
   - `mysql/database.sql`
2. Nếu cần tối ưu index hoặc nâng cấp schema cũ:
   - `mysql/migrate_indexes.sql`

Ví dụ:

```bash
mysql -u root -p < mysql/database.sql
mysql -u root -p < mysql/migrate_indexes.sql
```

Database mặc định trong code là `facebook_clone`.

### Bước 3: Cấu hình kết nối DB

Sửa `includes/db.php` nếu bạn dùng host/user/pass khác:

- `HOST`
- `USERNAME`
- `PASSWORD`
- `DBNAME`

### Bước 4: Cấu hình SMTP (đăng ký/OTP/quên mật khẩu)

File cấu hình: `includes/mail_config.php`.

Bạn có thể:
- Cập nhật trực tiếp trong file, hoặc
- Thiết lập qua biến môi trường (`FB_SMTP_HOST`, `FB_SMTP_PORT`, `FB_SMTP_USER`, `FB_SMTP_PASS`, ...).

> ⚠️ Bảo mật: không dùng mật khẩu thật trong source code. Nên dùng App Password (Gmail) và thay ngay thông tin SMTP trước khi deploy.

### Bước 5: Cài và chạy Socket server

```bash
cd socket
npm install
npm start
```

Mặc định socket chạy `127.0.0.1:3000`.

### Bước 6: Chạy web PHP

- Trỏ document root tới thư mục dự án (hoặc đặt trong `htdocs`).
- Mở URL app trên trình duyệt.
- `index.php` sẽ tự chuyển sang `auth/login.php`.

---

## 5) Cấu hình realtime (PHP ↔ Socket)

### Node Socket
File: `socket/ecosystem.config.cjs` hoặc biến môi trường runtime:

- `PORT` (mặc định 3000)
- `SOCKET_LISTEN_HOST` (khuyến nghị `127.0.0.1` sau reverse proxy)
- `SOCKET_EMIT_TOKEN` (token bí mật)
- `PHP_BASE_URL` (URL base của PHP app)

### PHP emit sang Node
File: `includes/socket_emit.php` đọc các biến:

- `SOCKET_EMIT_URL` (mặc định `http://127.0.0.1:3000/emit`)
- `SOCKET_EMIT_TOKEN` (phải giống Node)

Nếu token không khớp, event emit từ PHP sang Node sẽ thất bại.

---

## 6) Cấu hình WebRTC (STUN/TURN)

File `includes/webrtc.php` hỗ trợ các biến môi trường:

- `WEBRTC_STUN_URLS`
- `WEBRTC_TURN_URLS`
- `WEBRTC_TURN_SECRET` (TURN REST auth - khuyến nghị)
- hoặc cặp `WEBRTC_TURN_USERNAME` + `WEBRTC_TURN_CREDENTIAL`
- `WEBRTC_TURN_TTL`

Nếu không cấu hình TURN, cuộc gọi có thể hoạt động kém trên mạng NAT nghiêm ngặt.

---

## 7) Deploy production (gợi ý)

Tham khảo chi tiết tại: `deploy/azure-linux-socket.md`.

Kiến trúc khuyến nghị:

- Nginx/Apache phục vụ PHP qua HTTPS.
- Socket server bind nội bộ (`127.0.0.1:3000`).
- Reverse proxy `/socket.io/` về Node.
- Chỉ mở port `22/80/443`, không public trực tiếp `3000`.

---

## 8) Kiểm tra sau cài đặt

Checklist nhanh:

1. Mở trang login thành công.
2. Đăng ký nhận mã xác thực (mail gửi được).
3. Đăng nhập và vào feed.
4. Tạo bài viết + bình luận + reaction.
5. Mở 2 tài khoản để test chat realtime.
6. Kiểm tra socket health:

```bash
curl http://127.0.0.1:3000/healthz
```

Nếu trả về `ok` là socket đã chạy.

---

## 9) Lỗi thường gặp

### Không gửi được email
- Sai SMTP user/pass hoặc chưa dùng App Password.
- Port/secure sai (`587 + tls` hoặc `465 + ssl`).

### Chat không realtime
- Socket server chưa chạy.
- Sai `SOCKET_EMIT_TOKEN` giữa PHP và Node.
- Reverse proxy chưa forward đúng `/socket.io/`.

### Push Git bị chặn file lớn
- GitHub chặn file > 100MB.
- Không commit media nặng vào repo thường; dùng Git LFS hoặc lưu external storage.

---

## 10) Quy ước khi phát triển

- Trang hiển thị đặt trong `pages/`.
- Endpoint AJAX đặt trong `actions/`, ưu tiên POST.
- Dùng `includes/header.php` + `includes/footer.php` cho layout chung.
- Truy cập DB qua `includes/db.php`.
- Realtime logic nằm trong `socket/server.js`.

---

## 11) Ghi chú bảo mật

- Không commit thông tin nhạy cảm (SMTP password, token, khóa API).
- Nên chuyển các secret sang biến môi trường trước khi public/release.
- Giới hạn quyền DB user theo nguyên tắc least privilege.

---

## 12) License

Hiện chưa khai báo license chính thức. Nếu public rộng rãi, nên thêm file `LICENSE`.
