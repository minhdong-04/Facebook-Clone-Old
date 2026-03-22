/* Shared top navigation behaviors (search + recents + account popover) */
(function () {
  function initSearch() {
    const headerBox = document.getElementById('headerBox');
    const wrapper = document.getElementById('searchWrapper');
    const input = document.getElementById('searchInput');
    const backBtn = document.getElementById('backBtn');
    const fake = document.getElementById('fakePlaceholder');
    const resultBox = document.getElementById('searchResult');

    if (!headerBox || !wrapper || !input || !backBtn || !fake || !resultBox) return;

    const STORAGE_KEY = 'recentSearches';
    const MIN_QUERY_LEN = 2;

    function readRecents() {
    const parse = (raw) => {
      try {
        const arr = JSON.parse(raw || '[]');
        if (!Array.isArray(arr)) return [];
        return arr
          .map((x) => {
            if (!x) return null;
            if (typeof x === 'string') return { name: x, avatar: '' };
            if (typeof x === 'object' && x.name) return { name: String(x.name), avatar: String(x.avatar || '') };
            return null;
          })
          .filter((x) => x && x.name && String(x.name).trim() !== '');
      } catch {
        return [];
      }
    };

    let arr = parse(sessionStorage.getItem(STORAGE_KEY));
    if (arr.length) return arr;
    try {
      arr = parse(localStorage.getItem(STORAGE_KEY));
    } catch {
      arr = [];
    }
    return arr;
  }

    function writeRecents(arr) {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
      return;
    } catch {
      // fall through
    }
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
    } catch {
      // ignore
    }
  }

    function upsertRecent(item) {
    if (!item || !item.name) return;
    const name = String(item.name).trim();
    if (!name) return;
    const avatar = String(item.avatar || '').trim();

    let arr = readRecents();
    const nameLower = name.toLowerCase();
    const idx = arr.findIndex((x) => String(x.name || '').toLowerCase() === nameLower);

    if (idx >= 0) {
      const existing = arr[idx];
      const merged = {
        name: existing.name,
        avatar: avatar || existing.avatar || '',
      };
      arr.splice(idx, 1);
      arr.unshift(merged);
    } else {
      arr.unshift({ name, avatar });
    }

    if (arr.length > 10) arr.length = 10;
    writeRecents(arr);
  }

    function escapeHtml(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#39;');
  }

    function renderEmpty(text) {
    resultBox.innerHTML = `<div class="empty">${escapeHtml(text)}</div>`;
  }

    function renderRecent() {
    const recents = readRecents();
    if (!recents.length) {
      renderEmpty('Không có tìm kiếm nào gần đây');
      return;
    }

    const rows = recents
      .map((x) => {
        const name = escapeHtml(x.name);
        const avatar = escapeHtml(x.avatar || '');
        const img = avatar
          ? `<img class="user-avatar" src="${avatar}" alt="">`
          : `<div class="user-avatar ph" aria-hidden="true"></div>`;
        return `
          <div class="user-item recent-item" data-key="${name}" data-avatar="${avatar}" role="button" tabindex="0">
            <div class="user-left">${img}<span>${name}</span></div>
            <button class="recent-remove" data-key="${name}" aria-label="Xóa" type="button">×</button>
          </div>
        `;
      })
      .join('');

    resultBox.innerHTML = rows;
    resultBox.style.display = '';
  }

    function renderUsers(users) {
    if (!Array.isArray(users) || users.length === 0) {
      renderEmpty('Không tìm thấy kết quả');
      return;
    }

    const rows = users
      .map((u) => {
        const name = escapeHtml(u && u.name ? u.name : '');
        const avatar = escapeHtml(u && u.avatar ? u.avatar : '');
        const img = avatar
          ? `<img class="user-avatar" src="${avatar}" alt="">`
          : `<div class="user-avatar ph" aria-hidden="true"></div>`;
        return `
          <div class="user-item" data-name="${name}" data-avatar="${avatar}" role="button" tabindex="0">
            <div class="user-left">${img}<span>${name}</span></div>
          </div>
        `;
      })
      .join('');

    resultBox.innerHTML = rows;
    resultBox.style.display = '';
  }

    let currentFetchAbort = null;

    function fetchUsers(q) {
    if (currentFetchAbort) currentFetchAbort.abort();
    currentFetchAbort = new AbortController();

    const url = new URL('../api/search_user.php', window.location.href);
    url.searchParams.set('username', q);
    url.searchParams.set('ajax', '1');

    fetch(url.toString(), {
      signal: currentFetchAbort.signal,
      credentials: 'same-origin',
    })
      .then((r) => {
        if (!r.ok) throw new Error('network');
        return r.json();
      })
      .then((users) => {
        renderUsers(users);
      })
      .catch((err) => {
        if (err && err.name === 'AbortError') return;
        renderEmpty('Lỗi tìm kiếm');
      });
  }

    function openSearch() {
    headerBox.classList.add('is-searching');
    wrapper.classList.add('focused');
    resultBox.style.display = '';

    const q = input.value.trim();
    if (q.length < MIN_QUERY_LEN) {
      renderRecent();
    }
  }

    function closeSearch() {
    headerBox.classList.remove('is-searching');
    wrapper.classList.remove('focused');

    try {
      input.blur();
    } catch {
      // ignore
    }

    if (!input.value) wrapper.classList.remove('has-text');
    resultBox.innerHTML = '';
    resultBox.style.display = 'none';
  }

    input.addEventListener('focus', () => {
    openSearch();
    try {
      input.setSelectionRange(0, 0);
    } catch {
      // ignore
    }
  });

    wrapper.addEventListener('click', (e) => {
    if (e.target && e.target.closest && e.target.closest('#backBtn')) return;
    try {
      input.focus();
    } catch {
      // ignore
    }
  });

    input.addEventListener('input', () => {
    const q = input.value.trim();
    if (q) wrapper.classList.add('has-text');
    else wrapper.classList.remove('has-text');

    if (q.length < MIN_QUERY_LEN) {
      renderRecent();
      return;
    }

    fetchUsers(q);
  });

    backBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    input.value = '';
    wrapper.classList.remove('has-text');
    closeSearch();
  });

    document.addEventListener(
    'click',
    (e) => {
      if (!headerBox.classList.contains('is-searching')) return;

      // allow clicks inside search wrapper
      if (wrapper.contains(e.target)) return;

      // allow removing recents without closing
      try {
        const rem = e.target && e.target.closest && e.target.closest('.recent-remove');
        if (rem) return;
      } catch {
        // ignore
      }

      closeSearch();
    },
    true
  );

    document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key === 'Esc') {
      if (headerBox.classList.contains('is-searching')) closeSearch();
    }
  });

  fake.addEventListener('click', () => {
    input.focus();
  });

    resultBox.addEventListener('click', (e) => {
    const rem = e.target.closest('.recent-remove');
    if (rem) {
      const key = String(rem.dataset.key || '').trim();
      if (!key) return;
      const arr = readRecents().filter((x) => String(x.name || '').toLowerCase() !== key.toLowerCase());
      writeRecents(arr);
      renderRecent();
      return;
    }

    const recent = e.target.closest('.recent-item');
    if (recent) {
      const val = String(recent.dataset.key || '').trim();
      if (!val) return;
      input.value = val;
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.focus();
      return;
    }

    const userRow = e.target.closest('.user-item');
    if (userRow) {
      if (userRow.classList.contains('recent-item')) return;
      const name = String(userRow.dataset.name || '').trim();
      const avatar = String(userRow.dataset.avatar || '').trim();
      if (name) {
        upsertRecent({ name, avatar });
        input.value = name;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.focus();
      }
    }
  });

    resultBox.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const row = e.target && e.target.closest && e.target.closest('.user-item');
    if (!row) return;
    e.preventDefault();
    row.click();
  });

  // initial state
  if (input.value && input.value.trim() !== '') wrapper.classList.add('has-text');
  resultBox.style.display = 'none';
  }

  function initAccountPopover() {
    const accountBtn = document.getElementById('accountBtn') || document.querySelector('.header-right .account-btn');
    const accountPopover = document.getElementById('accountPopover');
    const fixedHeader = document.querySelector('.header');

    if (!accountBtn || !accountPopover) return;

    // Home-style account menu (optional; only activates if the markup exists)
    const acctMenuSlider = accountPopover.querySelector('#acctMenuSlider');
    const acctBtnSettingsPrivacy = accountPopover.querySelector('#acctBtnSettingsPrivacy');
    const acctBtnHelpSupport = accountPopover.querySelector('#acctBtnHelpSupport');
    const acctBtnDisplayAccessibility = accountPopover.querySelector('#acctBtnDisplayAccessibility');
    const acctBtnBack = accountPopover.querySelector('#acctBtnBack');
    const acctSecTitle = accountPopover.querySelector('#acctSecTitle');
    const acctSecViews = accountPopover.querySelector('#acctSecViews');

    const ACCT_STORAGE_THEME = 'fbmenu-theme';
    const ACCT_STORAGE_COMPACT = 'fbmenu-compact';

    function acctSetView(viewName) {
      if (!acctSecViews || !acctSecTitle) return;
      const views = Array.from(acctSecViews.querySelectorAll('.sec-view'));
      for (const view of views) view.classList.toggle('active', view.dataset.view === viewName);

      try {
        acctSecViews.scrollTop = 0;
      } catch {
        // ignore
      }

      if (viewName === 'settings') acctSecTitle.textContent = 'Cài đặt và quyền riêng tư';
      else if (viewName === 'help') acctSecTitle.textContent = 'Trợ giúp và hỗ trợ';
      else acctSecTitle.textContent = 'Màn hình và trợ năng';
    }

    // Auto-expand popover height for secondary views (clamped to max-height)
    const ACCT_BASE_HEIGHT = (() => {
      const h = Number.parseFloat(window.getComputedStyle(accountPopover).height);
      return Number.isFinite(h) && h > 0 ? h : 460;
    })();

    function acctSyncPopoverHeight() {
      if (!acctMenuSlider) return;
      const styles = window.getComputedStyle(accountPopover);
      const maxHeightCss = (styles.maxHeight || '').trim();
      const maxHeight = (() => {
        // Computed max-height can be `calc(100vh - 72px)`; parseFloat would incorrectly return 100.
        if (maxHeightCss.endsWith('px')) {
          const px = Number.parseFloat(maxHeightCss);
          if (Number.isFinite(px) && px > 0) return px;
        }
        return Math.max(300, window.innerHeight - 72);
      })();

      const isSecondary = acctMenuSlider.classList.contains('slide-active');
      if (!isSecondary) {
        // Let CSS control the base height; we only auto-expand for secondary panels.
        accountPopover.style.removeProperty('height');
        return;
      }

      const secondPanel = acctMenuSlider.querySelector('.menu-track > .menu-panel:nth-child(2)');
      if (!secondPanel) return;

      const secHeader = secondPanel.querySelector('.sec-header');
      const activeView = secondPanel.querySelector('#acctSecViews .sec-view.active');

      const panelStyles = window.getComputedStyle(secondPanel);
      const panelPaddingY = (() => {
        const pt = Number.parseFloat(panelStyles.paddingTop);
        const pb = Number.parseFloat(panelStyles.paddingBottom);
        const top = Number.isFinite(pt) ? pt : 0;
        const bottom = Number.isFinite(pb) ? pb : 0;
        return top + bottom;
      })();
      const headerH = secHeader ? secHeader.offsetHeight : 0;
      const viewH = activeView ? activeView.scrollHeight : 0;
      const desired = Math.max(ACCT_BASE_HEIGHT, headerH + panelPaddingY + (viewH || secondPanel.scrollHeight) + 24);
      const target = Math.max(300, Math.min(desired, maxHeight));
      accountPopover.style.height = `${target}px`;
    }

    function acctOpenSecondary(viewName) {
      if (!acctMenuSlider) return;
      acctSetView(viewName);
      acctMenuSlider.classList.add('slide-active');
      requestAnimationFrame(() => {
        try {
          const secondPanel = acctMenuSlider.querySelector('.menu-track > .menu-panel:nth-child(2)');
          if (secondPanel) secondPanel.scrollTop = 0;
          if (acctSecViews) acctSecViews.scrollTop = 0;
        } catch {
          // ignore
        }
        acctSyncPopoverHeight();
        position();
      });
    }

    function acctCloseSecondary() {
      if (acctMenuSlider) acctMenuSlider.classList.remove('slide-active');
      acctSyncPopoverHeight();
      position();
    }

    function acctApplyTheme(mode) {
      const pageRoot = document.body;
      const docRoot = document.documentElement;
      if (!pageRoot && !docRoot) return;
      const set = (value) => {
        if (docRoot) docRoot.setAttribute('data-theme', value);
        if (pageRoot) pageRoot.setAttribute('data-theme', value);
      };
      if (mode === 'on') {
        set('dark');
        return;
      }
      if (mode === 'off') {
        set('light');
        return;
      }
      set('auto');
    }

    function acctApplyCompact(mode) {
      const acctMenuRoot = accountPopover.querySelector('#acctMenuRoot');
      if (!acctMenuRoot) return;
      if (mode === 'on') {
        acctMenuRoot.style.setProperty('--item-height', '40px');
        acctMenuRoot.style.setProperty('--panel-padding', '6px');
        acctMenuRoot.style.setProperty('--font-item-label', '14px');
        return;
      }
      acctMenuRoot.style.removeProperty('--item-height');
      acctMenuRoot.style.removeProperty('--panel-padding');
      acctMenuRoot.style.removeProperty('--font-item-label');
    }

    function acctSelectRadio(rowEl) {
      if (!rowEl || !rowEl.dataset) return;
      const group = rowEl.dataset.group;
      const value = rowEl.dataset.value;
      if (!group || !value) return;

      const rows = Array.from(accountPopover.querySelectorAll(`.radio-row[data-group="${group}"]`));
      for (const r of rows) r.classList.toggle('selected', r === rowEl);

      if (group === 'darkmode') {
        try {
          localStorage.setItem(ACCT_STORAGE_THEME, value);
        } catch {
          // ignore
        }
        acctApplyTheme(value);
      }
      if (group === 'compact') {
        try {
          localStorage.setItem(ACCT_STORAGE_COMPACT, value);
        } catch {
          // ignore
        }
        acctApplyCompact(value);
      }
    }

    function initAccountMenu() {
      if (!acctMenuSlider) return;

      if (acctBtnSettingsPrivacy) {
        acctBtnSettingsPrivacy.addEventListener('click', (e) => {
          e.preventDefault();
          acctOpenSecondary('settings');
        });
      }
      if (acctBtnHelpSupport) {
        acctBtnHelpSupport.addEventListener('click', (e) => {
          e.preventDefault();
          acctOpenSecondary('help');
        });
      }
      if (acctBtnDisplayAccessibility) {
        acctBtnDisplayAccessibility.addEventListener('click', (e) => {
          e.preventDefault();
          acctOpenSecondary('accessibility');
        });
      }
      if (acctBtnBack) acctBtnBack.addEventListener('click', () => acctCloseSecondary());

      accountPopover.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) return;
        const row = target.closest('.radio-row');
        if (!row) return;
        acctSelectRadio(row);
      });

      let savedTheme = 'off';
      let savedCompact = 'off';
      try {
        savedTheme = localStorage.getItem(ACCT_STORAGE_THEME) || 'off';
        savedCompact = localStorage.getItem(ACCT_STORAGE_COMPACT) || 'off';
      } catch {
        // ignore
      }

      acctApplyTheme(savedTheme);
      acctApplyCompact(savedCompact);

      const initThemeRow = accountPopover.querySelector(`.radio-row[data-group="darkmode"][data-value="${savedTheme}"]`);
      const initCompactRow = accountPopover.querySelector(`.radio-row[data-group="compact"][data-value="${savedCompact}"]`);
      if (initThemeRow) acctSelectRadio(initThemeRow);
      if (initCompactRow) acctSelectRadio(initCompactRow);

      acctSetView('accessibility');
    }

    function resetAccountMenu() {
      acctCloseSecondary();
      acctSetView('accessibility');
      acctSyncPopoverHeight();
    }

    // Profile/"see all" rows in Home markup are div[role=button]
    function wireProfileNav() {
      const profileHref = '../pages/profile.php';
      const profileInfo = accountPopover.querySelector('.profile-info-inner');
      const seeAll = accountPopover.querySelector('.see-all-btn');

      const go = () => {
        window.location.href = profileHref;
      };

      if (profileInfo) {
        profileInfo.addEventListener('click', go);
        profileInfo.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            go();
          }
        });
      }
      if (seeAll) {
        seeAll.addEventListener('click', go);
        seeAll.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            go();
          }
        });
      }
    }

    initAccountMenu();
    wireProfileNav();

    function isOpen() {
      return accountPopover.classList.contains('open');
    }

    function position() {
      const rect = accountBtn.getBoundingClientRect();
      const headerRect = fixedHeader ? fixedHeader.getBoundingClientRect() : null;
      const styles = window.getComputedStyle(accountPopover);

      // Match Home's behavior: anchor by the button's right edge and clamp horizontally.
      // Use clientWidth when available (more stable with scrollbars/zoom).
      const vw = document.documentElement && document.documentElement.clientWidth ? document.documentElement.clientWidth : window.innerWidth;

      const offsetY = Number.parseFloat(styles.getPropertyValue('--acct-offset-y')) || 0;
      const offsetX = Number.parseFloat(styles.getPropertyValue('--acct-offset-x')) || 0;
      const leftPad = Number.parseFloat(styles.getPropertyValue('--acct-left-pad')) || 0;
      const rightPad = Number.parseFloat(styles.getPropertyValue('--acct-right-pad')) || 0;

      const popW = accountPopover.offsetWidth || Number.parseFloat(styles.width) || 370;

      let x = rect.right + offsetX;
      let y = (headerRect ? headerRect.bottom : rect.bottom) + offsetY;

      const minX = popW + leftPad;
      const maxX = Math.max(minX, vw - rightPad);
      if (x < minX) x = minX;
      if (x > maxX) x = maxX;
      if (y < 0) y = 0;

      accountPopover.style.setProperty('--acct-x', `${x}px`);
      accountPopover.style.setProperty('--acct-y', `${y}px`);
    }

    function open() {
      accountPopover.classList.add('open');
      accountPopover.setAttribute('aria-hidden', 'false');
      try {
        accountBtn.setAttribute('aria-expanded', 'true');
      } catch {
        // ignore
      }
      try {
        // Only meaningful for the Home-style menu
        resetAccountMenu();
        acctSyncPopoverHeight();
      } catch {
        // ignore
      }
      position();
    }

    function close() {
      accountPopover.classList.remove('open');
      accountPopover.setAttribute('aria-hidden', 'true');
      try {
        accountBtn.setAttribute('aria-expanded', 'false');
      } catch {
        // ignore
      }
    }

    function toggle() {
      if (isOpen()) close();
      else open();
    }

    accountBtn.addEventListener('click', (e) => {
      // If this is an <a>, prevent navigation (for pages that still have link markup)
      e.preventDefault();
      e.stopPropagation();
      toggle();
    });

    window.addEventListener('resize', () => {
      if (isOpen()) {
        try {
          acctSyncPopoverHeight();
        } catch {
          // ignore
        }
        position();
      }
    });

    document.addEventListener(
      'click',
      (e) => {
        if (!isOpen()) return;
        if (accountPopover.contains(e.target) || accountBtn.contains(e.target)) return;
        close();
      },
      true
    );

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' || e.key === 'Esc') {
        if (isOpen()) close();
      }
    });
  }

  function initMessengerPopover() {
    // Home page manages Messenger popover inline; avoid double-binding events/rendering.
    if (window.__FB_HOME_MESSENGER__ === true) return;

    const messengerBtn = document.getElementById('messengerBtn');
    const messengerPopover = document.getElementById('messengerPopover');

    if (!messengerBtn || !messengerPopover) return;

    const fixedHeader = document.querySelector('.header');
    const mpOptionsBtn = document.getElementById('mpOptionsBtn');
    const mpOptionsMenu = document.getElementById('mpOptionsMenu');
    const mpMoreBtn = document.getElementById('mpMoreBtn');
    const mpMoreMenu = document.getElementById('mpMoreMenu');
    const mpComposeBtn = document.getElementById('mpComposeBtn');
    const mpCompose = document.getElementById('mpCompose');
    const mpComposeClose = document.getElementById('mpComposeClose');
    const mpComposeTo = document.getElementById('mpComposeTo');

    const mpConvList = document.getElementById('mpConvList');
    const mpEmpty = document.getElementById('mpEmpty');

    const mpMini = document.getElementById('mpMini');
    const mpMiniAvatar = document.getElementById('mpMiniAvatar');
    const mpMiniName = document.getElementById('mpMiniName');
    const mpMiniClose = document.getElementById('mpMiniClose');
    const mpMiniMessages = document.getElementById('mpMiniMessages');
    const mpMiniForm = document.getElementById('mpMiniForm');
    const mpMiniInput = document.getElementById('mpMiniInput');
    const mpMiniSendBtn = document.getElementById('mpMiniSend');
    const mpMiniLikeBtn = document.getElementById('mpMiniLike');
    const mpMiniToolPlus = document.getElementById('mpMiniToolPlus');
    const mpMiniToolMic = document.getElementById('mpMiniToolMic');
    const mpMiniToolPhoto = document.getElementById('mpMiniToolPhoto');
    const mpMiniToolSticker = document.getElementById('mpMiniToolSticker');
    const mpMiniToolGif = document.getElementById('mpMiniToolGif');
    const mpMiniToolEmoji = document.getElementById('mpMiniToolEmoji');
    const mpMiniEmojiPop = document.getElementById('mpMiniEmojiPop');
    const mpMiniFileInput = document.getElementById('mpMiniFileInput');

    const miniDefaultFileAccept = mpMiniFileInput ? (mpMiniFileInput.getAttribute('accept') || 'image/*') : 'image/*';

    let currentUserId = 0;
    let currentPeer = null; // { user_id, name, avatar }
    let lastMessageId = 0;
    let miniPollTimer = null;
    let miniAbort = null;
    let convAbort = null;

    // Socket.IO (realtime)
    let socket = null;
    let socketJoined = false;
    let convRefreshTimer = null;
    const convBtnByUserId = new Map();

    // Voice recording (mic) - match Home behavior
    let miniMediaRecorder = null;
    let miniRecordChunks = [];
    let miniRecordStream = null;
    let miniIsRecording = false;

    // Avoid clipping: keep the options menu at body-level like Home.
    try {
      if (mpOptionsMenu && mpOptionsMenu.parentElement !== document.body) {
        document.body.appendChild(mpOptionsMenu);
      }
    } catch {
      // ignore
    }

    function isOpen() {
      return messengerPopover.classList.contains('open');
    }

    function escapeHtml(s) {
      return String(s || '').replace(/[&"'<>]/g, (m) => ({
        '&': '&amp;',
        '"': '&quot;',
        "'": '&#39;',
        '<': '&lt;',
        '>': '&gt;',
      }[m]));
    }

    function formatTime(time) {
      const d = new Date(time);
      if (Number.isNaN(d.getTime())) return '';
      const now = new Date();
      const diff = now - d;
      if (diff < 60000) return 'Vừa xong';
      if (diff < 3600000) return `${Math.floor(diff / 60000)} phút`;
      if (diff < 86400000) return `${Math.floor(diff / 3600000)} giờ`;
      return d.toLocaleDateString('vi-VN');
    }

    function parseContent(content) {
      const raw = String(content || '').trim();
      if (!raw) return { html: '' };

      if (raw[0] === '{') {
        try {
          const obj = JSON.parse(raw);
          if (obj && typeof obj === 'object' && obj.type && obj.file) {
            const file = String(obj.file);
            const url = `../uploads/${encodeURIComponent(file)}`;
            if (obj.type === 'image') return { html: `<img src="${url}" alt="" />` };
            if (obj.type === 'audio') return { html: `<audio controls src="${url}"></audio>` };
          }
        } catch {
          // ignore
        }
      }

      return { html: escapeHtml(raw) };
    }

    function tryInitSocket() {
      if (socket) return socket;
      // Reuse existing socket if Home already initialized it
      if (window.fbSocket) {
        socket = window.fbSocket;
        return socket;
      }

      // Socket.IO client library must be loaded
      if (typeof window.io !== 'function') return null;

      try {
        const host = (window.location && window.location.hostname) ? window.location.hostname : 'localhost';
        const proto = (window.location && window.location.protocol) ? window.location.protocol : 'http:';
        const isLocalHost = host === 'localhost' || host === '127.0.0.1';

        const socketHost = (!isLocalHost && host.toLowerCase().startsWith('app.'))
          ? `socket.${host.slice(4)}`
          : '';

        // Connection strategy:
        // - If using Cloudflare Tunnel pattern (app.* -> socket.*), connect to socketHost.
        // - If HTTPS (reverse-proxy likely), connect same-origin and route /socket.io -> Node.
        // - Otherwise (plain HTTP, including LAN/IP), connect directly to :3000.
        const useSameOrigin = (proto === 'https:') && !socketHost;

        const socketOpts = {
          path: '/socket.io',
          transports: ['websocket', 'polling'],
          withCredentials: true,
        };

        const socketUrl = socketHost ? `${proto}//${socketHost}` : '';
        socket = socketUrl
          ? window.io(socketUrl, socketOpts)
          : (useSameOrigin
            ? window.io(undefined, socketOpts)
            : window.io(`http://${host}:3000`, socketOpts));

        window.fbSocket = socket;
        return socket;
      } catch (_e) {
        socket = null;
        return null;
      }
    }

    function ensureSocketJoined() {
      const s = tryInitSocket();
      if (!s) return;
      if (!currentUserId) {
        const fromWindow = Number(window.CURRENT_USER_ID) || 0;
        if (fromWindow > 0) currentUserId = fromWindow;
      }
      if (!currentUserId || socketJoined) return;
      try {
        s.emit('join', currentUserId);
        socketJoined = true;
      } catch {
        // ignore
      }
    }

    function convPreviewFromContentString(contentString) {
      const raw = String(contentString || '').trim();
      if (!raw) return '';
      if (raw[0] === '{') {
        try {
          const obj = JSON.parse(raw);
          if (obj && typeof obj === 'object' && obj.type) {
            if (obj.type === 'image') return 'Đã gửi ảnh';
            if (obj.type === 'audio') return 'Đã gửi âm thanh';
          }
        } catch {
          // ignore
        }
      }
      return raw.length > 60 ? `${raw.slice(0, 60)}…` : raw;
    }

    function scheduleConvRefresh() {
      if (!mpConvList) return;
      if (convRefreshTimer) clearTimeout(convRefreshTimer);
      convRefreshTimer = setTimeout(() => {
        convRefreshTimer = null;
        try {
          loadConversations();
        } catch {
          // ignore
        }
      }, 250);
    }

    function getConvBadge(btn) {
      if (!btn) return null;
      const el = btn.querySelector('.mp-conv-badge');
      return (el instanceof HTMLElement) ? el : null;
    }

    function setConvUnread(btn, count) {
      const n = Number(count) || 0;
      if (btn && btn instanceof HTMLElement) btn.dataset.unread = String(n);
      const badge = getConvBadge(btn);
      if (!badge) return;
      badge.textContent = n > 99 ? '99+' : String(n);
      badge.toggleAttribute('hidden', !(n > 0));
    }

    function getConvUnread(btn) {
      if (!btn) return 0;
      return Number(btn.dataset.unread) || 0;
    }

    function rebuildConversationIndex() {
      convBtnByUserId.clear();
      if (!mpConvList) return;
      const btns = Array.from(mpConvList.querySelectorAll('.mp-conv-item'));
      for (const b of btns) {
        if (!(b instanceof HTMLElement)) continue;
        const id = Number(b.dataset.userId) || 0;
        if (id > 0) convBtnByUserId.set(id, b);
      }
    }

    function updateConversationRowFromRealtime(otherUserId, previewText, when) {
      if (!mpConvList || !mpEmpty) return;
      const uid = Number(otherUserId) || 0;
      if (uid <= 0) return;

      let btn = convBtnByUserId.get(uid) || null;
      if (!btn) {
        // If list hasn't been loaded yet or this thread isn't in it, fall back to refresh.
        scheduleConvRefresh();
        return;
      }

      const previewTextEl = btn.querySelector('.mp-conv-preview-text');
      const sepEl = btn.querySelector('.mp-conv-sep');
      const timeEl = btn.querySelector('.mp-conv-time');

      const nextPreview = String(previewText || '');
      const nextTime = String(formatTime(when || Date.now()) || '');

      if (previewTextEl) previewTextEl.textContent = nextPreview;

      if (timeEl instanceof HTMLElement) {
        timeEl.textContent = nextTime;
        timeEl.toggleAttribute('hidden', !nextTime);
        if (nextTime) {
          timeEl.setAttribute('aria-label', `${nextTime} trước`);
          timeEl.setAttribute('title', `${nextTime} trước`);
        } else {
          timeEl.removeAttribute('aria-label');
          timeEl.removeAttribute('title');
        }
      }
      if (sepEl instanceof HTMLElement) {
        sepEl.toggleAttribute('hidden', !(nextPreview && nextTime));
      }

      // Move to top
      try {
        if (mpConvList.firstElementChild !== btn) {
          mpConvList.insertBefore(btn, mpConvList.firstElementChild);
        }
      } catch {
        // ignore
      }

      mpEmpty.style.display = 'none';
      mpConvList.classList.add('has-items');
    }

    function closeMoreMenu() {
      if (!mpMoreBtn || !mpMoreMenu) return;
      mpMoreMenu.classList.remove('open');
      mpMoreMenu.setAttribute('aria-hidden', 'true');
      mpMoreBtn.classList.remove('is-open');
      mpMoreBtn.setAttribute('aria-expanded', 'false');
    }

    function closeOptionsMenu() {
      if (!mpOptionsBtn || !mpOptionsMenu) return;
      mpOptionsMenu.classList.remove('open');
      mpOptionsMenu.setAttribute('aria-hidden', 'true');
      mpOptionsBtn.classList.remove('is-open');
      mpOptionsBtn.setAttribute('aria-expanded', 'false');
    }

    function openCompose() {
      if (!mpCompose) return;
      mpCompose.classList.add('open');
      mpCompose.setAttribute('aria-hidden', 'false');
      requestAnimationFrame(() => {
        try {
          if (mpComposeTo) mpComposeTo.focus();
        } catch {
          // ignore
        }
      });
    }

    function closeCompose() {
      if (!mpCompose) return;
      mpCompose.classList.remove('open');
      mpCompose.setAttribute('aria-hidden', 'true');
    }

    function positionMessengerPopover() {
      const btnRect = messengerBtn.getBoundingClientRect();
      const headerRect = fixedHeader ? fixedHeader.getBoundingClientRect() : null;
      const styles = window.getComputedStyle(messengerPopover);

      const offsetY = Number.parseFloat(styles.getPropertyValue('--mp-offset-y')) || 0;
      const popW = messengerPopover.offsetWidth || 360;
      const popH = messengerPopover.offsetHeight || 650;

      let x = btnRect.right - popW + 8;
      let y = (headerRect ? headerRect.bottom : btnRect.bottom) + offsetY;

      const pad = 8;
      const minX = pad;
      const maxX = Math.max(pad, window.innerWidth - popW - pad);
      const minY = headerRect ? headerRect.bottom : 0;
      const maxY = Math.max(minY, window.innerHeight - popH - pad);

      if (x < minX) x = minX;
      if (x > maxX) x = maxX;
      if (y < minY) y = minY;
      if (y > maxY) y = maxY;

      messengerPopover.style.left = `${x}px`;
      messengerPopover.style.top = `${y}px`;
    }

    function openMessenger() {
      messengerPopover.classList.add('open');
      messengerPopover.setAttribute('aria-hidden', 'false');
      messengerBtn.setAttribute('aria-expanded', 'true');
      positionMessengerPopover();

      // Load conversations when opened.
      try {
        loadConversations();
      } catch {
        // ignore
      }
    }

    function closeMessenger() {
      messengerPopover.classList.remove('open');
      messengerPopover.setAttribute('aria-hidden', 'true');
      messengerBtn.setAttribute('aria-expanded', 'false');
      closeMoreMenu();
      closeOptionsMenu();
    }

    function renderConversations(items) {
      if (!mpConvList || !mpEmpty) return;

      if (!Array.isArray(items) || items.length === 0) {
        mpConvList.classList.remove('has-items');
        mpConvList.innerHTML = '';
        mpEmpty.style.display = '';
        return;
      }

      mpEmpty.style.display = 'none';
      mpConvList.classList.add('has-items');
      mpConvList.innerHTML = items
        .map((c) => {
          const id = Number(c && c.user_id) || 0;
          const name = escapeHtml(c && c.name ? c.name : '');
          const preview = escapeHtml(c && c.last_preview ? c.last_preview : '');
          const time = escapeHtml(formatTime(c && c.last_time ? c.last_time : ''));
          const avatar = escapeHtml(c && c.avatar ? c.avatar : '');
          const unread = Number(c && c.unread_count) || 0;
          const hasPreview = !!preview;
          const hasTime = !!time;
          return `
            <div class="mp-conv-item" role="listitem" data-user-id="${id}" data-name="${name}" data-avatar="${avatar}" data-unread="${unread}">
              <button class="mp-conv-open" type="button" aria-label="Mở đoạn chat với ${name}">
                <span class="mp-conv-avatar" aria-hidden="true">
                  ${avatar ? `<img src="${avatar}" alt="" />` : `<span aria-hidden="true"></span>`}
                </span>
                <span class="mp-conv-text">
                  <div class="mp-conv-name">${name}</div>
                  <div class="mp-conv-meta">
                    <span class="mp-conv-preview-text">${preview}</span>
                    <span class="mp-conv-sep" aria-hidden="true" ${hasPreview && hasTime ? '' : 'hidden'}>·</span>
                    <abbr class="mp-conv-time" ${hasTime ? '' : 'hidden'} aria-label="${time ? `${time} trước` : ''}" title="${time ? `${time} trước` : ''}">${time}</abbr>
                  </div>
                </span>
              </button>
              <button class="mp-conv-more" type="button" aria-label="Lựa chọn khác cho ${name}" title="Tùy chọn">
                <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false">
                  <path d="M2.25 10a1.75 1.75 0 1 1 3.5 0 1.75 1.75 0 0 1-3.5 0zM10 8.25a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5zm6 0a1.75 1.75 0 1 0 0 3.5 1.75 1.75 0 0 0 0-3.5z"></path>
                </svg>
              </button>
              <span class="mp-conv-badge" ${unread > 0 ? '' : 'hidden'} aria-label="Chưa đọc">${unread > 99 ? '99+' : unread}</span>
            </div>
          `;
        })
        .join('');

      rebuildConversationIndex();
    }

    function loadConversations() {
      if (!mpConvList || !mpEmpty) return;
      if (convAbort) convAbort.abort();
      convAbort = new AbortController();

      const url = new URL('../actions/get_conversations.php', window.location.href);
      fetch(url.toString(), {
        signal: convAbort.signal,
        credentials: 'same-origin',
      })
        .then((r) => {
          if (!r.ok) throw new Error('network');
          return r.json();
        })
        .then((data) => {
          currentUserId = Number(data && data.current_user_id) || currentUserId;
          renderConversations((data && data.conversations) || []);
        })
        .catch((err) => {
          if (err && err.name === 'AbortError') return;
          renderConversations([]);
        });
    }

    function stopMiniPolling() {
      if (miniPollTimer) {
        clearInterval(miniPollTimer);
        miniPollTimer = null;
      }
      if (miniAbort) {
        try {
          miniAbort.abort();
        } catch {
          // ignore
        }
        miniAbort = null;
      }
    }

    function closeMini() {
      if (!mpMini) return;
      mpMini.classList.remove('open');
      mpMini.setAttribute('aria-hidden', 'true');

      closeMiniEmoji();
      currentPeer = null;
      lastMessageId = 0;
      stopMiniPolling();

      // stop recording if any
      try {
        if (miniIsRecording && miniMediaRecorder) miniMediaRecorder.stop();
      } catch {
        // ignore
      }
      miniIsRecording = false;
      try {
        if (miniRecordStream) miniRecordStream.getTracks().forEach((t) => t.stop());
      } catch {
        // ignore
      }
      miniRecordStream = null;
      miniMediaRecorder = null;
      miniRecordChunks = [];
      if (mpMiniToolMic && mpMiniToolMic.classList) mpMiniToolMic.classList.remove('is-recording');
      if (mpMiniToolMic) mpMiniToolMic.setAttribute('aria-label', 'Gửi clip âm thanh');

      if (mpMiniMessages) mpMiniMessages.innerHTML = '';
      try {
        if (mpMiniInput) mpMiniInput.innerHTML = '';
      } catch {
        // ignore
      }
    }

    function normalizeMiniInputEmpty() {
      if (!mpMiniInput) return;
      const html = String(mpMiniInput.innerHTML || '').trim();
      if (html === '<br>' || html === '<div><br></div>') {
        mpMiniInput.innerHTML = '';
      }
    }

    function getMiniInputText() {
      if (!mpMiniInput) return '';
      normalizeMiniInputEmpty();
      return String(mpMiniInput.textContent || '').replace(/\u00A0/g, ' ').trim();
    }

    function updateMiniComposerLayout() {
      if (!mpMiniForm || !mpMiniInput) return;
      try {
        normalizeMiniInputEmpty();
        const hasText = !!String(mpMiniInput.textContent || '').replace(/\u00A0/g, ' ').trim();
        const multiLine = (mpMiniInput.scrollHeight > (mpMiniInput.clientHeight + 6));
        mpMiniForm.classList.toggle('is-tools-collapsed', multiLine && (hasText || String(mpMiniInput.innerHTML || '').trim() !== ''));
      } catch {
        // ignore
      }
    }

    function syncMiniComposer() {
      const hasText = !!getMiniInputText();
      if (mpMiniSendBtn) {
        mpMiniSendBtn.disabled = !hasText;
        mpMiniSendBtn.hidden = !hasText;
      }
      if (mpMiniLikeBtn) {
        mpMiniLikeBtn.hidden = hasText;
      }
      updateMiniComposerLayout();
    }

    function isMiniEmojiOpen() {
      return !!(mpMiniEmojiPop && mpMiniEmojiPop.classList.contains('is-open'));
    }

    function openMiniEmoji() {
      if (!mpMiniEmojiPop) return;
      mpMiniEmojiPop.classList.add('is-open');
      try {
        mpMiniEmojiPop.setAttribute('aria-hidden', 'false');
      } catch {
        // ignore
      }
    }

    function closeMiniEmoji() {
      if (!mpMiniEmojiPop) return;
      mpMiniEmojiPop.classList.remove('is-open');
      try {
        mpMiniEmojiPop.setAttribute('aria-hidden', 'true');
      } catch {
        // ignore
      }
    }

    function toggleMiniEmoji() {
      if (isMiniEmojiOpen()) closeMiniEmoji();
      else openMiniEmoji();
    }

    function insertMiniEmoji(emoji) {
      if (!mpMiniInput) return;
      const text = String(emoji || '');
      if (!text) return;

      try {
        mpMiniInput.focus();
      } catch {
        // ignore
      }

      const sel = window.getSelection ? window.getSelection() : null;
      if (!sel) {
        mpMiniInput.appendChild(document.createTextNode(text));
        syncMiniComposer();
        return;
      }

      try {
        if (sel.rangeCount > 0) {
          const range = sel.getRangeAt(0);
          if (range && mpMiniInput.contains(range.startContainer)) {
            range.deleteContents();
            const node = document.createTextNode(text);
            range.insertNode(node);
            range.setStartAfter(node);
            range.collapse(true);
            sel.removeAllRanges();
            sel.addRange(range);
            syncMiniComposer();
            return;
          }
        }
      } catch {
        // ignore
      }

      // fallback append to end
      mpMiniInput.appendChild(document.createTextNode(text));
      try {
        const range = document.createRange();
        range.selectNodeContents(mpMiniInput);
        range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
      } catch {
        // ignore
      }
      syncMiniComposer();
    }

    function openMiniFilePicker(accept) {
      if (!mpMiniFileInput) return;
      try {
        mpMiniFileInput.setAttribute('accept', accept || miniDefaultFileAccept);
      } catch {
        // ignore
      }
      try {
        mpMiniFileInput.click();
      } catch {
        // ignore
      }
    }

    function clearMiniInput() {
      if (!mpMiniInput) return;
      mpMiniInput.innerHTML = '';
      syncMiniComposer();
    }

    function openMini(peer) {
      if (!mpMini || !peer || !peer.user_id) return;
      currentPeer = peer;
      lastMessageId = 0;
      if (mpMiniAvatar) mpMiniAvatar.src = peer.avatar || '';
      if (mpMiniName) mpMiniName.textContent = String(peer.name || '');
      if (mpMiniMessages) mpMiniMessages.innerHTML = '';

      clearMiniInput();

      closeMiniEmoji();

      mpMini.classList.add('open');
      mpMini.setAttribute('aria-hidden', 'false');

      // Remove unread badge immediately when user opens the conversation
      const convBtn = convBtnByUserId.get(Number(peer.user_id)) || null;
      if (convBtn) setConvUnread(convBtn, 0);

      loadMiniMessages(true);
      stopMiniPolling();

      // Prefer realtime. Poll only if socket isn't connected.
      ensureSocketJoined();
      const s = tryInitSocket();
      if (!s || !s.connected) {
        miniPollTimer = setInterval(() => loadMiniMessages(false), 1200);
      }

      syncMiniComposer();

      try {
        if (mpMiniInput) mpMiniInput.focus();
      } catch {
        // ignore
      }
    }

    function appendMiniMessage(msg) {
      if (!mpMiniMessages) return;
      const isSent = Number(msg && msg.from_user) === Number(currentUserId);
      const parsed = parseContent(msg && msg.content ? msg.content : '');
      const time = escapeHtml(formatTime(msg && (msg.created_at || msg.sent_at) ? (msg.created_at || msg.sent_at) : ''));
      const msgId = Number(msg && msg.id) || 0;

      const div = document.createElement('div');
      div.className = `mp-mini-msg ${isSent ? 'sent' : 'received'}`;
      if (msgId) div.dataset.msgId = String(msgId);
      div.innerHTML = `
        <div class="mp-mini-bubble">${parsed.html}</div>
        <div class="mp-mini-time">${time}</div>
      `;
      mpMiniMessages.appendChild(div);
      try {
        mpMiniMessages.scrollTop = mpMiniMessages.scrollHeight;
      } catch {
        // ignore
      }
    }

    function loadMiniMessages(force) {
      if (!currentPeer || !currentPeer.user_id) return;
      if (miniAbort) miniAbort.abort();
      miniAbort = new AbortController();

      const url = new URL('../actions/get_messages.php', window.location.href);
      url.searchParams.set('friend_id', String(currentPeer.user_id));
      url.searchParams.set('last_id', String(force ? 0 : lastMessageId));

      fetch(url.toString(), {
        signal: miniAbort.signal,
        credentials: 'same-origin',
      })
        .then((r) => {
          if (!r.ok) throw new Error('network');
          return r.json();
        })
        .then((data) => {
          const list = (data && data.messages) || [];
          if (!Array.isArray(list) || list.length === 0) return;
          for (const msg of list) {
            appendMiniMessage(msg);
            const id = Number(msg && msg.id) || 0;
            if (id > lastMessageId) lastMessageId = id;
          }

          const peerLastRead = Number(data && data.peer_last_read_message_id) || 0;
          if (peerLastRead > 0) {
            renderMiniSeen(peerLastRead);
          }

          if (force && document.hasFocus && document.hasFocus()) {
            markMiniRead(lastMessageId);
          }
        })
        .catch((err) => {
          if (err && err.name === 'AbortError') return;
        });
    }

    function clearMiniSeen() {
      if (!mpMiniMessages) return;
      const els = Array.from(mpMiniMessages.querySelectorAll('.mp-mini-seen'));
      for (const el of els) {
        try { el.remove(); } catch { /* ignore */ }
      }
    }

    function cssEscapeCompat(s) {
      try {
        if (window.CSS && typeof CSS.escape === 'function') return CSS.escape(String(s));
      } catch {
        // ignore
      }
      return String(s).replace(/[^a-zA-Z0-9_-]/g, (m) => `\\${m}`);
    }

    function renderMiniSeen(peerLastReadId) {
      if (!mpMiniMessages || !currentPeer || !currentPeer.avatar) return;
      const seenId = Number(peerLastReadId) || 0;
      if (seenId <= 0) return;

      clearMiniSeen();

      const selector = `.mp-mini-msg.sent[data-msg-id="${cssEscapeCompat(seenId)}"]`;
      const target = mpMiniMessages.querySelector(selector);
      if (!(target instanceof HTMLElement)) return;

      const wrap = document.createElement('div');
      wrap.className = 'mp-mini-seen';
      wrap.innerHTML = `
        <span class="mp-mini-seen-avatar" aria-label="Đã xem">
          <img src="${escapeHtml(currentPeer.avatar)}" alt="" />
        </span>
      `;
      target.appendChild(wrap);
    }

    function markMiniRead(uptoId) {
      if (!currentPeer || !currentPeer.user_id) return;
      const lastId = Number(uptoId) || 0;
      if (!lastId) return;

      if (!currentUserId) {
        const fromWindow = Number(window.CURRENT_USER_ID) || 0;
        if (fromWindow > 0) currentUserId = fromWindow;
      }

      // Optimistically clear unread badge
      const btn = convBtnByUserId.get(Number(currentPeer.user_id)) || null;
      if (btn) setConvUnread(btn, 0);

      const body = `friend_id=${encodeURIComponent(String(currentPeer.user_id))}&last_id=${encodeURIComponent(String(lastId))}`;
      fetch('../actions/mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body,
      }).catch(() => {});

      // Realtime notify peer (so they can move the seen avatar)
      try {
        ensureSocketJoined();
        const s = tryInitSocket();
        if (s) {
          s.emit('readReceipt', {
            from_user: currentUserId,
            to_user: Number(currentPeer.user_id),
            last_read_message_id: lastId,
          });
        }
      } catch {
        // ignore
      }
    }

    function sendMiniMessage(text) {
      if (!currentPeer || !currentPeer.user_id) return Promise.resolve(false);
      const msg = String(text || '').trim();
      if (!msg) return Promise.resolve(false);

      const body = `message=${encodeURIComponent(msg)}&to_user=${encodeURIComponent(String(currentPeer.user_id))}`;
      return fetch('../actions/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body,
      })
        .then((r) => r.json())
        .then((data) => {
          if (!data || !data.ok) return false;
          if (data.message) {
            appendMiniMessage(data.message);
            const id = Number(data.message.id) || 0;
            if (id > lastMessageId) lastMessageId = id;

            // Realtime relay like Home (do not persist on Node)
            try {
              ensureSocketJoined();
              const s = tryInitSocket();
              if (s) {
                s.emit('sendMessage', {
                  no_persist: true,
                  id: data.message.id,
                  created_at: data.message.created_at,
                  from_user: currentUserId,
                  to_user: Number(currentPeer.user_id),
                  message: data.message.content || msg,
                  content: data.message.content || msg,
                });
              }
            } catch {
              // ignore
            }
          } else {
            loadMiniMessages(false);
          }

          // keep conv list fresh
          scheduleConvRefresh();
          return true;
        })
        .catch(() => false);
    }

    function sendMiniFile(file) {
      if (!currentPeer || !currentPeer.user_id || !file) return Promise.resolve(false);
      const form = new FormData();
      form.append('to_user', String(currentPeer.user_id));
      // Match Home: send as `file` (send_message.php accepts `file`)
      form.append('file', file);

      return fetch('../actions/send_message.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: form,
      })
        .then((r) => r.json())
        .then((data) => {
          if (!data || !data.ok) return false;
          if (data.message) {
            appendMiniMessage(data.message);
            const id = Number(data.message.id) || 0;
            if (id > lastMessageId) lastMessageId = id;

            // Realtime relay like Home (do not persist on Node)
            try {
              ensureSocketJoined();
              const s = tryInitSocket();
              if (s) {
                s.emit('sendMessage', {
                  no_persist: true,
                  id: data.message.id,
                  created_at: data.message.created_at,
                  from_user: currentUserId,
                  to_user: Number(currentPeer.user_id),
                  message: data.message.content || '',
                  content: data.message.content || '',
                });
              }
            } catch {
              // ignore
            }
          } else {
            loadMiniMessages(false);
          }

          scheduleConvRefresh();
          return true;
        })
        .catch(() => false);
    }

    function attachSocketListeners() {
      const s = tryInitSocket();
      if (!s) return;

      // Avoid duplicate listeners if initMessengerPopover runs once per page load
      if (s.__fbMiniBound) return;
      s.__fbMiniBound = true;

      s.on('connect', () => {
        ensureSocketJoined();
        // If mini chat is open, stop polling when socket comes back
        if (mpMini && mpMini.classList.contains('open')) {
          stopMiniPolling();
        }
      });

      s.on('disconnect', () => {
        // fallback polling if mini is open
        if (mpMini && mpMini.classList.contains('open')) {
          stopMiniPolling();
          miniPollTimer = setInterval(() => loadMiniMessages(false), 1200);
        }
      });

      s.on('newMessage', (payload) => {
        const p = payload || {};
        const from = Number(p.from_user) || 0;
        const to = Number(p.to_user) || 0;
        if (!from || !to) return;

        if (!currentUserId) {
          const fromWindow = Number(window.CURRENT_USER_ID) || 0;
          if (fromWindow > 0) currentUserId = fromWindow;
        }
        if (!currentUserId) return;

        const otherUserId = (from === currentUserId) ? to : from;

        const contentString = (typeof p.content === 'string' && p.content.trim() !== '')
          ? p.content
          : (typeof p.message === 'string' ? p.message : '');

        const when = p.created_at || p.time || Date.now();

        // Update mini chat if it matches current thread
        if (mpMini && mpMini.classList.contains('open') && currentPeer && Number(currentPeer.user_id) === otherUserId) {
          const id = Number(p.id) || 0;
          if (id && id <= lastMessageId) return;

          appendMiniMessage({
            id: id || undefined,
            from_user: from,
            to_user: to,
            content: contentString,
            created_at: p.created_at || (p.time ? new Date(p.time).toISOString() : undefined),
          });
          if (id && id > lastMessageId) lastMessageId = id;

          // Auto-mark read for incoming messages when thread is open & tab focused
          if (to === currentUserId && document.hasFocus && document.hasFocus()) {
            markMiniRead(lastMessageId);
          }
        }

        // Realtime conversation list: update preview/time and move to top.
        const preview = convPreviewFromContentString(contentString);
        if (preview) {
          updateConversationRowFromRealtime(otherUserId, preview, when);
        } else {
          scheduleConvRefresh();
        }

        // Unread badge increment (only for incoming, and only if thread isn't actively open)
        const isIncoming = (to === currentUserId && from !== currentUserId);
        if (isIncoming) {
          const isActiveThread = (mpMini && mpMini.classList.contains('open') && currentPeer && Number(currentPeer.user_id) === otherUserId);
          if (!isActiveThread) {
            const btn = convBtnByUserId.get(otherUserId) || null;
            if (btn) setConvUnread(btn, getConvUnread(btn) + 1);
            else scheduleConvRefresh();
          }
        }
      });

      s.on('readReceipt', (payload) => {
        const p = payload || {};
        const from = Number(p.from_user) || 0;
        const to = Number(p.to_user) || 0;
        const lastRead = Number(p.last_read_message_id) || 0;
        if (!from || !to || !lastRead) return;

        if (!currentUserId) {
          const fromWindow = Number(window.CURRENT_USER_ID) || 0;
          if (fromWindow > 0) currentUserId = fromWindow;
        }
        if (!currentUserId) return;

        // If peer read my messages, show their avatar at that message.
        if (to === currentUserId && mpMini && mpMini.classList.contains('open') && currentPeer && Number(currentPeer.user_id) === from) {
          renderMiniSeen(lastRead);
        }
      });
    }

    function setMiniMicUi(recording) {
      if (!mpMiniToolMic) return;
      mpMiniToolMic.classList.toggle('is-recording', !!recording);
      mpMiniToolMic.setAttribute('aria-label', recording ? 'Dừng ghi âm' : 'Gửi clip âm thanh');
    }

    async function stopMiniRecordingAndSend() {
      try {
        if (miniMediaRecorder && miniIsRecording) miniMediaRecorder.stop();
      } catch {
        // ignore
      }
    }

    function toggleMessenger() {
      if (isOpen()) closeMessenger();
      else openMessenger();
    }

    function positionOptionsMenu() {
      if (!mpOptionsBtn || !mpOptionsMenu) return;

      const btnRect = mpOptionsBtn.getBoundingClientRect();
      const styles = window.getComputedStyle(mpOptionsMenu);
      const shiftX = Number.parseFloat(styles.getPropertyValue('--mp-options-shift-x')) || 0;

      // Ensure open so offsetWidth/Height are measurable.
      mpOptionsMenu.classList.add('open');
      mpOptionsMenu.setAttribute('aria-hidden', 'false');

      const menuW = mpOptionsMenu.offsetWidth || 340;
      const menuH = mpOptionsMenu.offsetHeight || 550;

      let x = btnRect.right - menuW + shiftX;
      let y = btnRect.bottom + 8;

      const pad = 8;
      const minX = pad;
      const maxX = Math.max(pad, window.innerWidth - menuW - pad);
      const minY = pad;
      const maxY = Math.max(pad, window.innerHeight - menuH - pad);

      if (x < minX) x = minX;
      if (x > maxX) x = maxX;
      if (y < minY) y = minY;
      if (y > maxY) y = maxY;

      mpOptionsMenu.style.left = `${x}px`;
      mpOptionsMenu.style.top = `${y}px`;

      const caretLeft = Math.max(16, Math.min(menuW - 16, btnRect.right - x));
      mpOptionsMenu.style.setProperty('--mp-options-caret-left', `${caretLeft}px`);
    }

    function toggleOptionsMenu() {
      if (!mpOptionsBtn || !mpOptionsMenu) return;

      const isMenuOpen = mpOptionsMenu.classList.contains('open');
      if (isMenuOpen) {
        closeOptionsMenu();
        return;
      }

      closeMoreMenu();
      mpOptionsBtn.classList.add('is-open');
      mpOptionsBtn.setAttribute('aria-expanded', 'true');
      positionOptionsMenu();
    }

    function positionMoreMenu() {
      if (!mpMoreBtn || !mpMoreMenu) return;

      const tabs = mpMoreBtn.closest('.mp-tabs');
      if (!tabs) return;

      const tabsRect = tabs.getBoundingClientRect();
      const btnRect = mpMoreBtn.getBoundingClientRect();

      mpMoreMenu.classList.add('open');
      mpMoreMenu.setAttribute('aria-hidden', 'false');

      const menuW = mpMoreMenu.offsetWidth || 150;
      let left = btnRect.left - tabsRect.left;
      let top = btnRect.bottom - tabsRect.top + 6;

      const pad = 8;
      const maxLeft = Math.max(pad, tabsRect.width - menuW - pad);
      if (left < pad) left = pad;
      if (left > maxLeft) left = maxLeft;
      if (top < pad) top = pad;

      mpMoreMenu.style.left = `${left}px`;
      mpMoreMenu.style.top = `${top}px`;
    }

    function toggleMoreMenu() {
      if (!mpMoreBtn || !mpMoreMenu) return;

      const isMenuOpen = mpMoreMenu.classList.contains('open');
      if (isMenuOpen) {
        closeMoreMenu();
        return;
      }

      closeOptionsMenu();
      mpMoreBtn.classList.add('is-open');
      mpMoreBtn.setAttribute('aria-expanded', 'true');
      positionMoreMenu();
    }

    messengerBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      toggleMessenger();
    });

    if (mpOptionsBtn) {
      mpOptionsBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!isOpen()) openMessenger();
        toggleOptionsMenu();
      });
    }

    if (mpMoreBtn) {
      mpMoreBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleMoreMenu();
      });
    }

    if (mpComposeBtn) {
      mpComposeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (!isOpen()) openMessenger();
        closeOptionsMenu();
        closeMoreMenu();
        openCompose();
      });
    }

    if (mpComposeClose) {
      mpComposeClose.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeCompose();
      });
    }

    if (mpMiniClose) {
      mpMiniClose.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeMini();
      });
    }

    if (mpMiniForm) {
      mpMiniForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const val = getMiniInputText();
        if (!val) return;
        sendMiniMessage(val).then((ok) => {
          if (ok) clearMiniInput();
        });
      });
    }

    if (mpMiniInput) {
      mpMiniInput.addEventListener('input', () => {
        syncMiniComposer();
      });

      mpMiniInput.addEventListener('keydown', (e) => {
        // Messenger-like: Enter to send, Shift+Enter for new line
        if (e.key !== 'Enter') return;
        if (e.shiftKey) return;
        e.preventDefault();

        const hasText = !!getMiniInputText();
        if (hasText) {
          if (mpMiniForm) {
            if (mpMiniForm.requestSubmit && mpMiniSendBtn) mpMiniForm.requestSubmit(mpMiniSendBtn);
            else mpMiniForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
          }
          return;
        }

        // If no text, behave like Home: send 👍 via Like button
        if (mpMiniLikeBtn && !mpMiniLikeBtn.hidden) {
          sendMiniMessage('👍');
        }
      });
    }

    if (mpMiniLikeBtn) {
      mpMiniLikeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        sendMiniMessage('👍');
      });
    }

    // Plus / GIF / Sticker: simple behavior via file picker (no extra UI)
    if (mpMiniToolPlus) {
      mpMiniToolPlus.addEventListener('click', (e) => {
        e.preventDefault();
        openMiniFilePicker('image/*');
      });
    }
    if (mpMiniToolGif) {
      mpMiniToolGif.addEventListener('click', (e) => {
        e.preventDefault();
        openMiniFilePicker('image/gif');
      });
    }
    if (mpMiniToolSticker) {
      mpMiniToolSticker.addEventListener('click', (e) => {
        e.preventDefault();
        openMiniFilePicker('image/*');
      });
    }
    if (mpMiniToolEmoji) {
      mpMiniToolEmoji.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleMiniEmoji();
      });
    }

    if (mpMiniEmojiPop) {
      mpMiniEmojiPop.addEventListener('click', (e) => {
        e.stopPropagation();
        const target = e.target;
        if (!(target instanceof HTMLElement)) return;
        const btn = target.closest('.fb-emoji-btn');
        if (btn && btn instanceof HTMLButtonElement) {
          const emoji = String(btn.dataset.emoji || btn.textContent || '').trim();
          insertMiniEmoji(emoji);
        }
      });
    }

    document.addEventListener(
      'click',
      (e) => {
        if (!isMiniEmojiOpen()) return;
        const t = e.target;
        if (
          (mpMiniEmojiPop && mpMiniEmojiPop.contains(t)) ||
          (mpMiniToolEmoji && mpMiniToolEmoji.contains(t))
        ) {
          return;
        }
        closeMiniEmoji();
      },
      true
    );

    // Voice recording (mic) - same UX as Home
    if (mpMiniToolMic) {
      mpMiniToolMic.addEventListener('click', async (e) => {
        e.preventDefault();
        if (!currentPeer || !currentPeer.user_id) return;

        // toggle
        if (miniIsRecording) {
          await stopMiniRecordingAndSend();
          return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          alert('Trình duyệt không hỗ trợ ghi âm.');
          return;
        }

        try {
          miniRecordStream = await navigator.mediaDevices.getUserMedia({ audio: true });
          const preferredTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg'];
          let mimeType = '';
          for (const t of preferredTypes) {
            if (window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t)) {
              mimeType = t;
              break;
            }
          }
          miniMediaRecorder = mimeType ? new MediaRecorder(miniRecordStream, { mimeType }) : new MediaRecorder(miniRecordStream);
          miniRecordChunks = [];
          miniIsRecording = true;
          setMiniMicUi(true);

          miniMediaRecorder.ondataavailable = (evt) => {
            if (evt.data && evt.data.size > 0) miniRecordChunks.push(evt.data);
          };

          miniMediaRecorder.onstop = async () => {
            const chunks = miniRecordChunks;
            miniRecordChunks = [];
            miniIsRecording = false;
            setMiniMicUi(false);

            try {
              if (miniRecordStream) miniRecordStream.getTracks().forEach((t) => t.stop());
            } catch {
              // ignore
            }
            miniRecordStream = null;

            if (!chunks.length) return;
            const blob = new Blob(chunks, { type: (miniMediaRecorder && miniMediaRecorder.mimeType) ? miniMediaRecorder.mimeType : 'audio/webm' });
            const ext = (blob.type || '').includes('ogg') ? 'ogg' : 'webm';
            const file = new File([blob], `voice.${ext}`, { type: blob.type || 'audio/webm' });
            await sendMiniFile(file);
          };

          miniMediaRecorder.start();
        } catch (err) {
          console.warn('mini record failed', err);
          alert('Không thể ghi âm (cần cấp quyền micro).');
          try {
            if (miniRecordStream) miniRecordStream.getTracks().forEach((t) => t.stop());
          } catch {
            // ignore
          }
          miniRecordStream = null;
          miniIsRecording = false;
          setMiniMicUi(false);
        }
      });
    }

    if (mpMiniToolPhoto && mpMiniFileInput) {
      mpMiniToolPhoto.addEventListener('click', (e) => {
        e.preventDefault();
        openMiniFilePicker(miniDefaultFileAccept);
      });

      mpMiniFileInput.addEventListener('change', async () => {
        const files = Array.from(mpMiniFileInput.files || []).filter((f) => f);
        if (!files.length) return;
        for (const f of files) {
          // sequential send to keep ordering
          // eslint-disable-next-line no-await-in-loop
          await sendMiniFile(f);
        }
        try {
          mpMiniFileInput.value = '';
        } catch {
          // ignore
        }

        // reset accept to default after use
        try {
          mpMiniFileInput.setAttribute('accept', miniDefaultFileAccept);
        } catch {
          // ignore
        }
      });
    }

    // Tabs (visual only for now)
    messengerPopover.addEventListener('click', (e) => {
      const target = e.target;
      if (!(target instanceof HTMLElement)) return;

      const tab = target.closest('.mp-tab');
      if (tab && tab instanceof HTMLButtonElement && tab.dataset && tab.dataset.tab) {
        const tabs = Array.from(messengerPopover.querySelectorAll('.mp-tab'));
        for (const t of tabs) {
          const isActive = t === tab;
          t.classList.toggle('active', isActive);
          try {
            t.setAttribute('aria-selected', isActive ? 'true' : 'false');
          } catch {
            // ignore
          }
        }
        closeMoreMenu();
        return;
      }

      const switchRow = target.closest('.mp-opts-row[role="switch"]');
      if (switchRow && switchRow instanceof HTMLButtonElement) {
        const checked = switchRow.getAttribute('aria-checked') === 'true';
        switchRow.setAttribute('aria-checked', checked ? 'false' : 'true');
      }

      // Ignore clicks on row options button
      const moreBtn = target.closest('.mp-conv-more');
      if (moreBtn && messengerPopover.contains(moreBtn)) return;

      const openBtn = target.closest('.mp-conv-open');
      const convRow = openBtn ? openBtn.closest('.mp-conv-item') : null;
      if (convRow && convRow instanceof HTMLElement) {
        const uid = Number(convRow.dataset.userId) || 0;
        const name = String(convRow.dataset.name || '');
        const avatar = String(convRow.dataset.avatar || '');
        if (uid > 0) {
          // Home page has its own full chat popup; prefer it when available.
          if (typeof window.fbOpenChat === 'function') {
            try { window.fbOpenChat({ id: uid, name, avatar }); } catch { /* ignore */ }
            closeMessenger();
            return;
          }

          openMini({ user_id: uid, name, avatar });
          closeMessenger();
        }
      }
    });

    if (mpOptionsMenu) {
      mpOptionsMenu.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) return;
        const switchRow = target.closest('.mp-opts-row[role="switch"]');
        if (switchRow && switchRow instanceof HTMLButtonElement) {
          const checked = switchRow.getAttribute('aria-checked') === 'true';
          switchRow.setAttribute('aria-checked', checked ? 'false' : 'true');
        }
      });
    }

    window.addEventListener('resize', () => {
      if (isOpen()) {
        positionMessengerPopover();
        if (mpOptionsMenu && mpOptionsMenu.classList.contains('open')) positionOptionsMenu();
      }
    });

    // Keep popover positioned if page scrolls
    window.addEventListener(
      'scroll',
      () => {
        if (isOpen()) {
          positionMessengerPopover();
          if (mpOptionsMenu && mpOptionsMenu.classList.contains('open')) positionOptionsMenu();
        }
      },
      true
    );

    document.addEventListener(
      'click',
      (e) => {
        if (!isOpen()) return;

        const t = e.target;
        if (
          messengerPopover.contains(t) ||
          messengerBtn.contains(t) ||
          (mpOptionsMenu && mpOptionsMenu.contains(t)) ||
          (mpMoreMenu && mpMoreMenu.contains(t)) ||
          (mpCompose && mpCompose.contains(t))
        ) {
          return;
        }

        closeMessenger();
      },
      true
    );

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' || e.key === 'Esc') {
        if (isMiniEmojiOpen()) {
          closeMiniEmoji();
          return;
        }
        if (mpOptionsMenu && mpOptionsMenu.classList.contains('open')) {
          closeOptionsMenu();
          return;
        }
        if (mpMoreMenu && mpMoreMenu.classList.contains('open')) {
          closeMoreMenu();
          return;
        }
        if (mpCompose && mpCompose.classList.contains('open')) {
          closeCompose();
          return;
        }
        if (mpMini && mpMini.classList.contains('open')) {
          closeMini();
          return;
        }
        if (isOpen()) closeMessenger();
      }
    });

    // Initial ARIA state
    try {
      messengerPopover.setAttribute('aria-hidden', 'true');
      messengerBtn.setAttribute('aria-expanded', 'false');
      if (mpOptionsMenu) mpOptionsMenu.setAttribute('aria-hidden', 'true');
      if (mpMoreMenu) mpMoreMenu.setAttribute('aria-hidden', 'true');
      if (mpCompose) mpCompose.setAttribute('aria-hidden', 'true');
      if (mpMini) mpMini.setAttribute('aria-hidden', 'true');
      if (mpMiniEmojiPop) mpMiniEmojiPop.setAttribute('aria-hidden', 'true');
    } catch {
      // ignore
    }

    // init realtime listeners (polling remains fallback)
    ensureSocketJoined();
    attachSocketListeners();
  }

  initSearch();
  initAccountPopover();
  initMessengerPopover();
})();
