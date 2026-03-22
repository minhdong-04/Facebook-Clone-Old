<?php
// Shared Messenger popover markup (ported from pages/home.php)
// Expects: $currentUserNameSafe, $currentUserAvatar, fb_escape()
?>

<!-- Messenger popover -->
<section class="messenger-popover" id="messengerPopover" role="dialog" aria-label="Messenger" aria-hidden="true">
  <div class="mp-header">
    <div class="mp-title">Đoạn chat</div>
    <div class="mp-actions" aria-hidden="true">
      <button class="mp-icon" id="mpOptionsBtn" type="button" title="Tùy chọn" aria-haspopup="menu" aria-expanded="false" aria-controls="mpOptionsMenu">
        <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false">
          <path d="M2.25 10a1.75 1.75 0 1 1 3.5 0 1.75 1.75 0 0 1-3.5 0zM10 8.25a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5zm6 0a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5z"></path>
        </svg>
      </button>
      <button class="mp-icon" type="button" title="Mở rộng">
        <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false">
          <path d="M18.25 7.75a1 1 0 1 1-2 0V5.164l-3.293 3.293a1 1 0 1 1-1.414-1.414l3.293-3.293H12.25a1 1 0 1 1 0-2h4a2 2 0 0 1 2 2v4zm-14.5 4.5a1 1 0 1 0-2 0v4a2 2 0 0 0 2 2h4a1 1 0 1 0 0-2H5.164l3.293-3.293a1 1 0 1 0-1.414-1.414L3.75 14.836V12.25zm13.5-1a1 1 0 0 0-1 1v2.586l-3.293-3.293a1 1 0 0 0-1.414 1.414l3.293 3.293H12.25a1 1 0 1 0 0 2h4a2 2 0 0 0 2-2v-4a1 1 0 0 0-1-1zm-14.5-2.5a1 1 0 0 0 1-1V5.164l3.293 3.293a1 1 0 0 0 1.414-1.414L5.164 3.75H7.75a1 1 0 0 0 0-2h-4a2 2 0 0 0-2 2v4a1 1 0 0 0 1 1z"></path>
        </svg>
      </button>
      <button class="mp-icon" id="mpComposeBtn" type="button" title="Soạn tin">
        <svg viewBox="0 0 24 24">
          <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm18-11.5a1 1 0 0 0 0-1.41l-1.34-1.34a1 1 0 0 0-1.41 0l-1.13 1.13 3.75 3.75L21 5.75z"></path>
        </svg>
      </button>
    </div>
  </div>

  <!-- Options menu (Chat settings) -->
  <div class="mp-options-menu" id="mpOptionsMenu" role="menu" aria-label="Cài đặt đoạn chat" aria-hidden="true">
    <div class="mp-opts-head">
      <div class="mp-opts-title">Cài đặt đoạn chat</div>
      <div class="mp-opts-sub">Tùy chỉnh trải nghiệm trên Messenger.</div>
    </div>

    <div class="mp-opts-divider is-tight" role="separator"></div>

    <button class="mp-opts-row" type="button" role="switch" aria-checked="true" data-pref="call_sounds">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true"><i class="mp-ico-sprite mp-ico-call-sound" aria-hidden="true"></i></span>
        <span class="mp-opts-text"><div class="mp-opts-label">Âm thanh cuộc gọi đến</div></span>
      </span>
      <span class="mp-opts-right" aria-hidden="true"><span class="mp-switch"></span></span>
    </button>

    <button class="mp-opts-row" type="button" role="switch" aria-checked="true" data-pref="message_sounds">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true"><i class="mp-ico-sprite mp-ico-message-sound" aria-hidden="true"></i></span>
        <span class="mp-opts-text"><div class="mp-opts-label">Âm thanh tin nhắn</div></span>
      </span>
      <span class="mp-opts-right" aria-hidden="true"><span class="mp-switch"></span></span>
    </button>

    <button class="mp-opts-row" type="button" role="switch" aria-checked="true" data-pref="new_message_pop">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true"><i class="mp-ico-sprite mp-ico-new-message-pop" aria-hidden="true"></i></span>
        <span class="mp-opts-text">
          <div class="mp-opts-label">Tin nhắn mới bật lên</div>
          <div class="mp-opts-desc">Tự động mở tin nhắn mới.</div>
        </span>
      </span>
      <span class="mp-opts-right" aria-hidden="true"><span class="mp-switch"></span></span>
    </button>

    <div class="mp-opts-divider" role="separator"></div>

    <button class="mp-opts-row" type="button" data-action="privacy">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true">
          <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
            <g fill-rule="evenodd" transform="translate(-446 -398)">
              <g>
                <path d="M103 201.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0" transform="translate(355 204)"></path>
                <path d="m101 201.5 1.843 4.423a.416.416 0 0 1-.385.577h-2.916a.416.416 0 0 1-.385-.577L101 201.5z" transform="translate(355 204)"></path>
                <path fill-rule="nonzero" d="M107.312 208.579a6.456 6.456 0 0 0 1.688-4.347v-7.118a1.57 1.57 0 0 0-1.196-1.523l-.588-.142c-2.347-.558-4.602-.949-6.216-.949-1.749 0-4.252.46-6.804 1.091A1.57 1.57 0 0 0 93 197.114v7.118c0 1.606.601 3.153 1.688 4.347 1.759 1.933 3.637 3.602 5.521 4.706a1.568 1.568 0 0 0 1.49.05l.092-.05c1.884-1.104 3.764-2.774 5.521-4.706zm-6.28 3.412a.069.069 0 0 1-.064 0c-1.73-1.014-3.505-2.59-5.17-4.422a4.956 4.956 0 0 1-1.298-3.337v-7.118c0-.03.022-.058.057-.067C96.991 196.445 99.413 196 101 196c1.587 0 4.007.444 6.443 1.047.035.009.057.037.057.067v7.118a4.957 4.957 0 0 1-1.298 3.337c-1.588 1.747-3.279 3.264-4.933 4.28l-.237.142z" transform="translate(355 204)"></path>
              </g>
            </g>
          </svg>
        </span>
        <span class="mp-opts-text"><div class="mp-opts-label">Quyền riêng tư và an toàn</div></span>
      </span>
      <span class="mp-opts-right mp-chevron" aria-hidden="true">
        <svg viewBox="0 0 20 20"><path d="M7.25 4.5a1 1 0 0 0-.707 1.707L10.336 10l-3.793 3.793a1 1 0 0 0 1.414 1.414l4.5-4.5a1 1 0 0 0 0-1.414l-4.5-4.5a.997.997 0 0 0-.707-.293z"></path></svg>
      </span>
    </button>

    <div class="mp-opts-divider is-tight" role="separator"></div>

    <button class="mp-opts-row" type="button" data-action="active_status" id="mpActiveStatusRow">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true"><i class="mp-ico-sprite mp-ico-active-status" aria-hidden="true"></i></span>
        <span class="mp-opts-text"><div class="mp-opts-label" id="mpActiveStatusLabel">Trạng thái hoạt động: ĐANG BẬT</div></span>
      </span>
    </button>

    <button class="mp-opts-row" type="button" data-action="requests">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true"><i class="mp-ico-sprite mp-ico-requests" aria-hidden="true"></i></span>
        <span class="mp-opts-text"><div class="mp-opts-label">Tin nhắn đang chờ</div></span>
      </span>
    </button>

    <button class="mp-opts-row" type="button" data-action="archived">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true"><i class="mp-ico-sprite mp-ico-archived" aria-hidden="true"></i></span>
        <span class="mp-opts-text"><div class="mp-opts-label">Đoạn chat đã lưu trữ</div></span>
      </span>
    </button>

    <button class="mp-opts-row" type="button" data-action="delivery">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true"><i class="mp-ico-sprite mp-ico-delivery" aria-hidden="true"></i></span>
        <span class="mp-opts-text"><div class="mp-opts-label">Cài đặt gửi tin nhắn</div></span>
      </span>
    </button>

    <div class="mp-opts-divider" role="separator"></div>

    <button class="mp-opts-row" type="button" data-action="restricted">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true"><i class="mp-ico-sprite mp-ico-restricted" aria-hidden="true"></i></span>
        <span class="mp-opts-text"><div class="mp-opts-label">Tài khoản đã hạn chế</div></span>
      </span>
    </button>

    <button class="mp-opts-row" type="button" data-action="blocking">
      <span class="mp-opts-left">
        <span class="mp-opts-ico" aria-hidden="true"><i class="mp-ico-sprite mp-ico-blocking" aria-hidden="true"></i></span>
        <span class="mp-opts-text"><div class="mp-opts-label">Cài đặt chặn</div></span>
      </span>
    </button>
  </div>

  <div class="mp-search">
    <div class="mp-searchbox">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"></path></svg>
      <input type="text" aria-label="Tìm kiếm trên Messenger" placeholder="Tìm kiếm trên Messenger" />
    </div>
  </div>

  <div class="mp-tabs" role="tablist" aria-label="Bộ lọc">
    <button class="mp-tab active" type="button" role="tab" aria-selected="true" data-tab="all">Tất cả</button>
    <button class="mp-tab" type="button" role="tab" aria-selected="false" data-tab="unread">Chưa đọc</button>
    <button class="mp-tab" type="button" role="tab" aria-selected="false" data-tab="group">Nhóm</button>
    <button class="mp-tab mp-more-btn" id="mpMoreBtn" type="button" aria-label="Thêm" aria-haspopup="menu" aria-expanded="false">…</button>
    <div class="mp-more-menu" id="mpMoreMenu" role="menu" aria-label="Thêm bộ lọc" aria-hidden="true">
      <button class="mp-more-item" type="button" role="menuitem" data-more="community">Cộng đồng</button>
    </div>
  </div>

  <div class="mp-content">
    <div class="mp-conv-list" id="mpConvList" role="list" aria-label="Danh sách đoạn chat"></div>
    <div class="mp-empty" id="mpEmpty">
      <h4>Không có đoạn chat nào</h4>
      <p>Đoạn chat mới sẽ hiển thị ở đây.</p>
    </div>
  </div>

  <div class="mp-footer">
    <a href="#" aria-label="Xem tất cả trong Messenger"><span>Xem tất cả trong Messenger</span></a>
  </div>
</section>

<!-- Mini chat window (opens from Messenger popover) -->
<section class="mp-mini" id="mpMini" aria-label="Đoạn chat" aria-hidden="true">
  <div class="mp-mini-header">
    <div class="mp-mini-peer">
      <span class="mp-mini-avatar" aria-hidden="true">
        <img id="mpMiniAvatar" alt="" />
      </span>
      <div class="mp-mini-peertext">
        <div class="mp-mini-name-row">
          <div class="mp-mini-name" id="mpMiniName"></div>
          <button class="mp-mini-btn mp-mini-caret" type="button" aria-label="Tùy chọn">
            <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M5.293 7.293a1 1 0 0 0 0 1.414l4 4a1 1 0 0 0 1.414 0l4-4a1 1 0 1 0-1.414-1.414L10 10.586 6.707 7.293a1 1 0 0 0-1.414 0z"></path></svg>
          </button>
        </div>
        <div class="mp-mini-sub" id="mpMiniSub">Đang hoạt động</div>
      </div>
    </div>
    <div class="mp-mini-actions">
      <button class="mp-mini-btn" type="button" aria-label="Gọi thoại">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.62 10.79a15.05 15.05 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24c1.12.37 2.33.57 3.58.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.07 21 3 13.93 3 5a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.46.57 3.58a1 1 0 0 1-.24 1.01l-2.2 2.2z"></path></svg>
      </button>
      <button class="mp-mini-btn" type="button" aria-label="Gọi video">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M17 10.5V7a2 2 0 0 0-2-2H5A2 2 0 0 0 3 7v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3.5l4 4v-11l-4 4z"></path></svg>
      </button>
      <button class="mp-mini-btn" id="mpMiniClose" type="button" aria-label="Đóng">
        <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L8.94 10l-4.72 4.72a.75.75 0 1 0 1.06 1.06L10 11.06l4.72 4.72a.75.75 0 1 0 1.06-1.06L11.06 10l4.72-4.72a.75.75 0 1 0-1.06-1.06L10 8.94 5.28 4.22z"></path></svg>
      </button>
    </div>
  </div>
  <div class="mp-mini-messages" id="mpMiniMessages" role="log" aria-live="polite"></div>
  <form class="fb-chat-foot" id="mpMiniForm" autocomplete="off">
    <input id="mpMiniFileInput" type="file" accept="image/*" multiple hidden>

    <button type="button" class="fb-chat-tool" id="mpMiniToolPlus" aria-label="Thêm">
      <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M11 11V6a1 1 0 1 1 2 0v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5z"></path>
      </svg>
    </button>

    <button type="button" class="fb-chat-tool" id="mpMiniToolMic" aria-label="Gửi clip âm thanh">
      <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3z"/>
        <path d="M19 11a1 1 0 1 0-2 0 5 5 0 0 1-10 0 1 1 0 1 0-2 0 7 7 0 0 0 6 6.92V21a1 1 0 1 0 2 0v-3.08A7 7 0 0 0 19 11z"/>
      </svg>
    </button>

    <button type="button" class="fb-chat-tool" id="mpMiniToolPhoto" aria-label="Gửi ảnh">
      <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M16.5 6.5 9 14a3 3 0 0 0 4.24 4.24L20 11.5a5 5 0 0 0-7.07-7.07L6.5 10.86a7 7 0 1 0 9.9 9.9l1.1-1.1a1 1 0 1 0-1.42-1.42l-1.1 1.1a5 5 0 1 1-7.07-7.07l6.43-6.43a3 3 0 0 1 4.24 4.24l-6.76 6.76a1 1 0 0 1-1.41-1.41l7.5-7.5a1 1 0 1 0-1.41-1.41z"/>
      </svg>
    </button>

    <button type="button" class="fb-chat-tool" id="mpMiniToolSticker" aria-label="Chọn nhãn dán">
      <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M21 13.5V12a9 9 0 1 0-9 9h1.5A7.5 7.5 0 0 0 21 13.5z" opacity=".35"/>
        <path d="M14 21.9a.9.9 0 0 1-.9-.9V18a4 4 0 0 1 4-4h3a.9.9 0 0 1 .9.9c0 3.86-3.14 7-7 7z"/>
        <path d="M9 11.25a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5zm6 0a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5z"/>
      </svg>
    </button>

    <button type="button" class="fb-chat-tool" id="mpMiniToolGif" aria-label="Chọn file GIF">GIF</button>

    <div class="fb-chat-input-wrap">
      <div
        class="fb-chat-input"
        id="mpMiniInput"
        contenteditable="true"
        role="textbox"
        aria-label="Tin nhắn"
        data-placeholder="Aa"
        spellcheck="true"></div>

      <button type="button" class="fb-chat-input-ico" id="mpMiniToolEmoji" aria-label="Chọn biểu tượng cảm xúc">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2zm-3 9a1.5 1.5 0 1 1 1.5-1.5A1.502 1.502 0 0 1 9 11zm6 0a1.5 1.5 0 1 1 1.5-1.5A1.502 1.502 0 0 1 15 11zm-3 8a6.013 6.013 0 0 1-4.243-1.757 1 1 0 1 1 1.414-1.414A4.014 4.014 0 0 0 12 17a4.014 4.014 0 0 0 2.829-1.171 1 1 0 0 1 1.414 1.414A6.013 6.013 0 0 1 12 19z"/>
        </svg>
      </button>
    </div>

    <!-- Mini emoji picker (simple) -->
    <div class="fb-emoji-pop" id="mpMiniEmojiPop" aria-hidden="true" role="dialog" aria-label="Biểu tượng cảm xúc">
      <div class="fb-emoji-grid" role="list">
        <?php
        $mpMiniEmojis = ['😀','😁','😂','🤣','😊','😍','😘','😎','🥳','😇','🙂','😉','😜','🤔','😴','😭','😡','👍','👎','❤️','🔥','🎉','👏','🙏'];
        foreach ($mpMiniEmojis as $e) {
          $safe = fb_escape($e);
          echo '<button class="fb-emoji-btn" type="button" role="listitem" data-emoji="' . $safe . '" aria-label="' . $safe . '">' . $safe . '</button>';
        }
        ?>
      </div>
    </div>

    <button type="button" class="fb-chat-like" id="mpMiniLike" aria-label="Gửi lượt thích">
      <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M2 21h4V9H2v12zm20-11a2 2 0 0 0-2-2h-6.31l.95-4.57.03-.32a1 1 0 0 0-.29-.7L13.17 2 7.59 7.59A2 2 0 0 0 7 9v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-1.68l1.88-8.32A2 2 0 0 0 22 10z"/>
      </svg>
    </button>

    <button class="fb-chat-send" id="mpMiniSend" type="submit" disabled hidden aria-label="Gửi">
      <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M2 21 23 12 2 3v7l15 2-15 2v7z"/>
      </svg>
    </button>
  </form>
</section>

<!-- Compose panel (standalone like Facebook): Tin nhắn mới -->
<section class="mp-compose" id="mpCompose" aria-label="Tin nhắn mới" aria-hidden="true">
  <div class="mp-compose-header">
    <div class="mp-compose-title">Tin nhắn mới</div>
    <button class="mp-compose-close" id="mpComposeClose" type="button" aria-label="Đóng">
      <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L8.94 10l-4.72 4.72a.75.75 0 1 0 1.06 1.06L10 11.06l4.72 4.72a.75.75 0 1 0 1.06-1.06L11.06 10l4.72-4.72a.75.75 0 1 0-1.06-1.06L10 8.94 5.28 4.22z"></path></svg>
    </button>
  </div>
  <div class="mp-compose-to">
    <div class="mp-compose-to-label">Đến:</div>
    <input class="mp-compose-to-input" id="mpComposeTo" type="text" autocomplete="off" />
  </div>
  <div class="mp-compose-divider" role="separator"></div>
  <div class="mp-compose-list" role="list">
    <button class="mp-compose-item" type="button" role="listitem">
      <span class="mp-compose-avatar meta-ai" aria-hidden="true">
        <img class="mp-metaai-ring" alt="Meta AI" referrerpolicy="origin-when-cross-origin" src="https://www.facebook.com/images/web_messenger/gen-ai-ring-2_36-4x.png" />
        <span class="mp-metaai-ring-overlay" aria-hidden="true"></span>
      </span>
      <span class="mp-compose-name">Meta AI
        <span class="mp-compose-verified" aria-hidden="true" title="Tài khoản đã xác minh">
          <svg viewBox="0 0 12 13" width="12" height="12" fill="currentColor" aria-hidden="true" focusable="false">
            <title>Tài khoản đã xác minh</title>
            <g fill-rule="evenodd" transform="translate(-98 -917)">
              <path d="m106.853 922.354-3.5 3.5a.499.499 0 0 1-.706 0l-1.5-1.5a.5.5 0 1 1 .706-.708l1.147 1.147 3.147-3.147a.5.5 0 1 1 .706.708m3.078 2.295-.589-1.149.588-1.15a.633.633 0 0 0-.219-.82l-1.085-.7-.065-1.287a.627.627 0 0 0-.6-.603l-1.29-.066-.703-1.087a.636.636 0 0 0-.82-.217l-1.148.588-1.15-.588a.631.631 0 0 0-.82.22l-.701 1.085-1.289.065a.626.626 0 0 0-.6.6l-.066 1.29-1.088.702a.634.634 0 0 0-.216.82l.588 1.149-.588 1.15a.632.632 0 0 0 .219.819l1.085.701.065 1.286c.014.33.274.59.6.604l1.29.065.703 1.088c.177.27.53.362.82.216l1.148-.588 1.15.589a.629.629 0 0 0 .82-.22l.701-1.085 1.286-.064a.627.627 0 0 0 .604-.601l.065-1.29 1.088-.703a.633.633 0 0 0 .216-.819"></path>
            </g>
          </svg>
        </span>
      </span>
    </button>

    <button class="mp-compose-item" type="button" role="listitem">
      <span class="mp-compose-avatar user" aria-hidden="true">
        <img class="mp-user-avatar-img" alt="<?= $currentUserNameSafe ?>" referrerpolicy="origin-when-cross-origin" src="<?= fb_escape($currentUserAvatar) ?>" />
        <span class="mp-user-avatar-overlay" aria-hidden="true"></span>
      </span>
      <span class="mp-compose-name"><?= $currentUserNameSafe ?></span>
    </button>
  </div>
</section>
