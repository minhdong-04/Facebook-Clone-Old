<?php
// pages/chat.php
require '../includes/db.php';
require '../includes/header.php';

if (!Database::isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

$current_user = Database::getCurrentUser();
$friend_id = (int)($_GET['user'] ?? 0);

if ($friend_id <= 0) {
    die("Không tìm thấy người dùng.");
}

// LẤY THÔNG TIN BẠN CHAT
$friend = Database::GetRow(
    "SELECT id, name, avatar FROM users WHERE id = ?",
    [$friend_id]
);

if (!$friend) {
    die("Người dùng không tồn tại.");
}

// ĐÁNH DẤU TIN NHẮN ĐÃ ĐỌC
Database::NonQuery(
    "UPDATE messages SET is_read = 1 WHERE to_user = ? AND from_user = ? AND is_read = 0",
    [$current_user['id'], $friend_id]
);
?>

<div class="chat-container" style="max-width:800px; margin:50px auto; background:white; border-radius:8px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
    <!-- Header -->
    <div class="chat-header" style="background:#1877f2; color:white; padding:12px 15px; font-weight:600; display:flex; align-items:center;">
        <a href="../pages/profile.php?user=<?= $friend_id ?>" style="color:white; margin-right:10px;">
            Back
        </a>
        <img src="../assets/images/<?= $friend['avatar'] ?? 'default-avatar.png' ?>" width="36" style="border-radius:50%; margin-right:10px;">
        <div>
            <div style="font-size:15px;"><?= htmlspecialchars($friend['name']) ?></div>
            <div style="font-size:12px; opacity:0.9;">Đang hoạt động</div>
        </div>
    </div>

    <!-- Tin nhắn -->
    <div id="messages" style="height:500px; overflow-y:auto; padding:15px; background:#f0f2f5; display:flex; flex-direction:column;"></div>

    <!-- Gửi tin nhắn -->
    <form id="send-form" style="display:flex; border-top:1px solid #eee; padding:10px; background:white;">
        <input type="text" id="message" placeholder="Aa" autocomplete="off"
               style="flex:1; padding:10px 16px; border-radius:24px; border:1px solid #ddd; font-size:15px; outline:none;">
        <button type="submit" style="margin-left:8px; background:#1877f2; color:white; border:none; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
            Send
        </button>
    </form>
</div>

<script>
const currentUserId = <?= $current_user['id'] ?>;
const friendId = <?= $friend_id ?>;
const messagesContainer = document.getElementById('messages');
let lastMessageId = 0;

function scrollToBottom() {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function loadMessages() {
    fetch(`../actions/get_messages.php?friend_id=${friendId}&last_id=${lastMessageId}`)
        .then(r => r.json())
        .then(data => {
            const list = data.messages || [];
            if (list.length > 0) {
                list.forEach(msg => {
                    const div = document.createElement('div');
                    div.className = msg.from_user == currentUserId ? 'message sent' : 'message received';
                    div.innerHTML = `
                        <div class="bubble">${escapeHtml(msg.content)}</div>
                        <div class="time">${formatTime(msg.created_at || msg.sent_at)}</div>
                    `;
                    messagesContainer.appendChild(div);
                    if (msg.id > lastMessageId) lastMessageId = msg.id;
                });
                scrollToBottom();
            }
        });
}

function escapeHtml(s) {
    return String(s || '').replace(/[&"'<>]/g, m => ({
        '&': '&amp;', '"': '&quot;', "'": '&#39;', '<': '&lt;', '>': '&gt;'
    }[m]));
}

function formatTime(time) {
    const date = new Date(time);
    const now = new Date();
    const diff = now - date;
    if (diff < 60000) return 'Vừa xong';
    if (diff < 3600000) return Math.floor(diff / 60000) + ' phút';
    if (diff < 86400000) return Math.floor(diff / 3600000) + ' giờ';
    return date.toLocaleDateString('vi-VN');
}

// Gửi tin nhắn
document.getElementById('send-form').onsubmit = function(e) {
    e.preventDefault();
    const input = document.getElementById('message');
    const msg = input.value.trim();
    if (!msg) return;

    fetch('../actions/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `message=${encodeURIComponent(msg)}&to_user=${friendId}`
    }).then(() => {
        input.value = '';
        loadMessages();
    });
};

// Tự động load tin nhắn
setInterval(loadMessages, 1000);
loadMessages();
</script>

<style>
.chat-container { font-family: 'Helvetica Neue', Arial, sans-serif; }
#messages { gap: 8px; }
.message { max-width: 70%; margin: 4px 0; display: flex; flex-direction: column; }
.message.sent { align-self: flex-end; align-items: flex-end; }
.message.received { align-self: flex-start; align-items: flex-start; }
.bubble {
    padding: 8px 14px;
    border-radius: 18px;
    font-size: 15px;
    line-height: 1.4;
    word-wrap: break-word;
}
.message.sent .bubble { background: #1877f2; color: white; border-bottom-right-radius: 4px; }
.message.received .bubble { background: #e4e6eb; color: #1c1e21; border-bottom-left-radius: 4px; }
.time { font-size: 11px; color: #65676b; margin-top: 2px; }
</style>