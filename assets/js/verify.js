(function () {
  const codeInput = document.getElementById("code");
  const continueBtn = document.getElementById("continueBtn");
  const verifyForm = document.getElementById("verifyForm");
  const inputBox = document.getElementById("inputBox");

  // Nếu trang không có phần verify thì bỏ qua
  if (!codeInput || !continueBtn || !verifyForm) return;

  /* Hiệu ứng khi focus */
  if (inputBox) {
    codeInput.addEventListener("focus", function () {
      inputBox.classList.add("focused");
    });
    codeInput.addEventListener("blur", function () {
      inputBox.classList.remove("focused");
    });
  }

  /* Chỉ cho nhập 6 chữ số */
  codeInput.addEventListener("input", function () {
    let v = this.value.replace(/\D/g, "").slice(0, 6);
    this.value = v;
    continueBtn.disabled = v.length !== 6;
  });

  /* Click Continue */
  continueBtn.addEventListener("click", function (e) {
    const v = codeInput.value.replace(/\D/g, "");
    if (v.length === 6) {
      verifyForm.submit();
    } else {
      codeInput.focus();
    }
  });

  /* Nhấn Enter để submit */
  codeInput.addEventListener("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      if (!continueBtn.disabled) continueBtn.click();
    }
  });
})();

/* ----- Helper: chuẩn hóa chuỗi bỏ dấu (dùng cho tìm kiếm) ----- */
function normalizeVietnamese(s) {
  return (s || "")
    .toLowerCase()
    .replace(/[àáảãạâầấẩẫậăằắẳẵặ]/g, "a")
    .replace(/[èéẻẽẹêềếểễệ]/g, "e")
    .replace(/[ìíỉĩị]/g, "i")
    .replace(/[òóỏõọôồốổỗộơờớởỡợ]/g, "o")
    .replace(/[ùúủũụưừứửữự]/g, "u")
    .replace(/[ỳýỷỹỵ]/g, "y")
    .replace(/đ/g, "d")
    .trim();
}

/* Chạy khi DOM sẵn sàng */
document.addEventListener("DOMContentLoaded", function () {
  /* ===== 1) Tìm kiếm ngôn ngữ trong modal ===== */
  (function () {
  const input = document.getElementById("languageSearch");
  if (!input) {
    console.warn('[lang-search] input #languageSearch không tìm thấy');
    return;
  }

  // chuẩn bị vùng thông báo
  let msgContainer = document.getElementById("noResultMsg");
  if (!msgContainer) {
    msgContainer = document.createElement("div");
    msgContainer.id = "noResultMsg";
    msgContainer.className = "text-center text-muted py-3 small d-none";
    const modalBody = document.querySelector("#languageModal .modal-body") || document.querySelector(".modal.show .modal-body");
    if (modalBody) modalBody.appendChild(msgContainer);
  }

  // trả về tất cả các link ngôn ngữ đáng tin cậy
  function getAllLanguageItems() {
    // chọn rõ ràng: các language-item trong modal / trong lists / anchors nằm trong footer langs
    let items = Array.from(document.querySelectorAll(
      "#languageModal a.language-item, #languageList a, #languageListInline a, .footer-row.langs a, .footer-row a, .language-item"
    ));
    // loại bỏ những thẻ không có text (ví dụ icon-only)
    items = items.filter(a => (a.textContent || "").trim().length > 0);
    return items;
  }

  function sanitizeForSearch(s) {
    if (!s) return "";
    // loại ✓, ngoặc, ký tự lạ, nhiều khoảng trắng
    s = s.replace(/✓/g, "").replace(/\u2713/g, "");
    s = s.replace(/\(.*?\)/g, " ");
    s = s.replace(/[^0-9A-Za-z\u00C0-\u024F\u0400-\u04FF\u0600-\u06FF\u0E00-\u0E7F\u0590-\u05FF\u4E00-\u9FFF\s]/gu, " ");
    s = s.replace(/\s+/g, " ").trim().toLowerCase();
    return typeof normalizeVietnamese === 'function' ? normalizeVietnamese(s) : s;
  }

  input.addEventListener("input", function (e) {
    const termRaw = e.target.value || "";
    const term = sanitizeForSearch(termRaw);
    const allLinks = getAllLanguageItems();

    console.log('[lang-search] termRaw:', termRaw, ' => term:', term, 'items found:', allLinks.length);

    let hasVisible = false;

    allLinks.forEach((link) => {
      const original = (link.textContent || "").trim();
      // loại các ký tự không mong muốn trước khi chuẩn hóa
      let txt = original.replace(/✓/g, "").replace(/\u2713/g, "").replace(/\(.*?\)/g, " ").replace(/\s+/g, " ").trim();
      const textNorm = sanitizeForSearch(txt);

      const match = term === "" || (textNorm && term && textNorm.indexOf(term) !== -1);

      if (match) {
        // hiển thị: gỡ d-none nếu có, và gỡ style display:none nếu đã có
        link.classList.remove("d-none");
        if (link.style) link.style.display = "";
        hasVisible = true;
      } else {
        // ẩn: ưu tiên class d-none (bootstrap), fallback display none
        link.classList.add("d-none");
        if (link.style) link.style.display = "none";
      }
    });

    if (!hasVisible && msgContainer) {
      msgContainer.innerHTML = `Không tìm thấy kết quả cho "<strong>${termRaw.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</strong>"`;
      msgContainer.classList.remove("d-none");
    } else if (msgContainer) {
      msgContainer.classList.add("d-none");
    }
  });
})();
  

 /* ===== 2) Modal "Cập nhật thông tin liên hệ" (custom modal) ===== */
(function () {
  const contactModal = document.getElementById("contactModal");
  const updateContactBtn = document.getElementById("updateContactBtn");
  const modalClose = document.getElementById("modalClose");
  const modalCancel = document.getElementById("modalCancel");
  const modalAdd = document.getElementById("modalAdd");
  const contactForm = document.getElementById("contactForm");
  const newContact = document.getElementById("newContact");

  if (!contactModal) return;

  function openModal() {
    contactModal.setAttribute("aria-hidden", "false");
    contactModal.style.display = "flex";
    contactModal.classList.add("show");
    document.documentElement.style.overflow = "hidden";
    document.body.style.overflow = "hidden";
    const focusable = contactModal.querySelector("input, button, textarea, [tabindex]");
    if (focusable) focusable.focus();
  }

  function closeModal() {
    contactModal.setAttribute("aria-hidden", "true");
    contactModal.style.display = "none";
    contactModal.classList.remove("show");
    document.documentElement.style.overflow = "";
    document.body.style.overflow = "";
    if (updateContactBtn) updateContactBtn.focus();
  }

  if (updateContactBtn) {
    updateContactBtn.addEventListener("click", function (e) {
      e.preventDefault();
      openModal();
    });
  }

  if (modalClose) {
    modalClose.addEventListener("click", function (e) {
      e.preventDefault();
      closeModal();
    });
  }

  if (modalCancel) {
    modalCancel.addEventListener("click", function (e) {
      e.preventDefault();
      closeModal();
    });
  }

  contactModal.addEventListener("click", function (e) {
    // click nền (overlay) đóng modal
    if (e.target === contactModal) closeModal();
  });

  document.addEventListener("keydown", function (e) {
    if ((e.key === "Escape" || e.key === "Esc") && contactModal.getAttribute("aria-hidden") === "false") {
      closeModal();
    }
  });

  if (modalAdd) {
    modalAdd.addEventListener("click", function (e) {
      e.preventDefault();
      // Ví dụ: validate trước khi submit
      const val = newContact ? newContact.value.trim() : "";
      if (!val) {
        // thông báo ngắn, hoặc set focus lại
        if (newContact) {
          newContact.focus();
        }
        return;
      }
      // Nếu muốn gửi form thực sự thì uncomment:
      // contactForm.submit();
      // Hiện demo: đóng modal sau khi thêm
      closeModal();
    });
  }

  if (contactForm) {
    contactForm.addEventListener("keydown", function (e) {
      if (e.key === "Enter") e.preventDefault();
    });
  }
})();


  /* ===== 3) Account dropdown (tooltip nhỏ ở góc phải) ===== */
  (function () {
    const accountBtn = document.getElementById("accountBtn");
    const accountDropdown = document.getElementById("accountDropdown");
    if (!accountBtn || !accountDropdown) return;

    accountBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      const isOpen = accountDropdown.classList.contains("open");
      if (isOpen) {
        // Đóng dropdown
        accountDropdown.classList.remove("open");
        accountDropdown.setAttribute("aria-hidden", "true");
        accountBtn.setAttribute("aria-expanded", "false");
      } else {
        // Mở dropdown
        accountDropdown.classList.add("open");
        accountDropdown.setAttribute("aria-hidden", "false");
        accountBtn.setAttribute("aria-expanded", "true");
      }
    });

    // Click ngoài sẽ đóng dropdown
    document.addEventListener("click", function (e) {
      if (accountDropdown.classList.contains("open")) {
        const inAccount = e.target.closest && e.target.closest(".account-area");
        if (!inAccount) {
          accountDropdown.classList.remove("open");
          accountDropdown.setAttribute("aria-hidden", "true");
          accountBtn.setAttribute("aria-expanded", "false");
        }
      }
    });

    // ESC đóng dropdown
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" || e.key === "Esc") {
        if (accountDropdown.classList.contains("open")) {
          accountDropdown.classList.remove("open");
          accountDropdown.setAttribute("aria-hidden", "true");
          accountBtn.setAttribute("aria-expanded", "false");
        }
      }
    });
  })();

  /* ===== 4) Nút "Gửi lại email" (throttle + feedback) ===== */
  (function () {
    const resendBtn = document.getElementById("resendBtn");
    const resendMsg = document.getElementById("resendMsg");
    if (!resendBtn) return;

    let cooldown = false;
    let cooldownTimer = null;

    resendBtn.addEventListener("click", function (e) {
      e.preventDefault();
      if (cooldown) return;
      cooldown = true;
      resendBtn.disabled = true;
      if (resendMsg) resendMsg.textContent = " Đang gửi...";

      // Gọi endpoint PHP để gửi lại (khớp file backend auth/resend_code.php)
      fetch("resend_code.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=resend",
      })
        .then((resp) => resp.json())
        .then((json) => {
          if (json && json.success) {
            if (resendMsg) resendMsg.textContent = " Mã đã được gửi lại.";
          } else {
            if (resendMsg)
              resendMsg.textContent =
                " " + (json.message || "Không thể gửi email.");
          }
        })
        .catch(() => {
          if (resendMsg) resendMsg.textContent = " Lỗi kết nối.";
        })
        .finally(() => {
          // Thời gian chặn (cooldown) 60s khớp backend
          const wait = 60000;
          let left = Math.ceil(wait / 1000);
          if (resendMsg) resendMsg.textContent = ` Vui lòng chờ ${left}s`;
          cooldownTimer = setInterval(() => {
            left--;
            if (left <= 0) {
              clearInterval(cooldownTimer);
              cooldown = false;
              resendBtn.disabled = false;
              if (resendMsg) resendMsg.textContent = "";
            } else {
              if (resendMsg) resendMsg.textContent = ` Vui lòng chờ ${left}s`;
            }
          }, 1000);
        });
    });
  })();

  /* ===== 5) Luồng modal xác nhận đăng xuất ===== */
  (function () {
    const overlay = document.getElementById("logoutConfirmOverlay");
    const btnClose = document.getElementById("logoutModalClose");
    const btnCancel = document.getElementById("logoutCancelBtn");
    const btnConfirm = document.getElementById("logoutConfirmBtn");
    const logoutLink = document.getElementById("logoutLink");

    if (!overlay) return;

    // Hiện modal xác nhận: bật overlay, focus, khoá scroll trang nền
    function showModal() {
      overlay.style.display = "flex";
      overlay.setAttribute("aria-hidden", "false");
      (btnClose || btnCancel || btnConfirm) &&
        (btnClose || btnCancel || btnConfirm).focus();
      document.documentElement.style.overflow = "hidden";
      document.body.style.overflow = "hidden";
    }
    // Ẩn modal: tắt overlay, trả lại scroll, trả focus về link đăng xuất
    function hideModal() {
      overlay.style.display = "none";
      overlay.setAttribute("aria-hidden", "true");
      document.documentElement.style.overflow = "";
      document.body.style.overflow = "";
      if (logoutLink) logoutLink.focus();
    }

    // Mở khi click vào link "Đăng xuất" trong dropdown
    if (logoutLink) {
      logoutLink.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        // Đảm bảo dropdown đóng (tránh hiển thị chồng chéo)
        const accountDropdown = document.getElementById("accountDropdown");
        const accountBtn = document.getElementById("accountBtn");
        if (accountDropdown && accountDropdown.classList.contains("open")) {
          accountDropdown.classList.remove("open");
          accountDropdown.setAttribute("aria-hidden", "true");
          if (accountBtn) accountBtn.setAttribute("aria-expanded", "false");
        }
        showModal();
      });
    }

    if (btnClose)
      btnClose.addEventListener("click", function (e) {
        e.preventDefault();
        hideModal();
      });
    if (btnCancel)
      btnCancel.addEventListener("click", function (e) {
        e.preventDefault();
        hideModal();
      });

    // Xác nhận đăng xuất: điều hướng tới href của logoutLink (hoặc 'logout.php' nếu không có)
    if (btnConfirm)
      btnConfirm.addEventListener("click", function (e) {
        e.preventDefault();
        const href =
          logoutLink && logoutLink.href ? logoutLink.href : "logout.php";
        window.location.href = href;
      });

    // Click vào backdrop (overlay) ngoài dialog sẽ đóng modal
    overlay.addEventListener("click", function (e) {
      if (e.target === overlay) hideModal();
    });

    // ESC đóng modal
    document.addEventListener("keydown", function (e) {
      if (
        (e.key === "Escape" || e.key === "Esc") &&
        overlay.style.display === "flex"
      ) {
        hideModal();
      }
    });
  })();

  /* ===== 6) Cập nhật năm ở footer nếu có phần tử #year ===== */
  (function () {
    const el = document.getElementById("year");
    if (el) el.textContent = new Date().getFullYear();
  })();
}); // Kết thúc DOMContentLoaded


document.addEventListener('DOMContentLoaded', function () {
  const wrap = document.querySelector('.floating-wrap');
  const input = wrap ? wrap.querySelector('#newContact') : null;

  if (!wrap || !input) return;

  // copy placeholder vào data-placeholder để CSS đọc (nếu chưa có)
  const ph = input.getAttribute('placeholder') || '';
  if (!wrap.getAttribute('data-placeholder')) {
    wrap.setAttribute('data-placeholder', ph);
  }

  // khởi tạo trạng thái filled nếu đã có value (ví dụ khi reload có value)
  if (input.value && input.value.trim() !== '') {
    wrap.classList.add('filled');
  } else {
    wrap.classList.remove('filled');
  }

  // focus/blur để thêm class focused
  input.addEventListener('focus', function () {
    wrap.classList.add('focused');
  });
  input.addEventListener('blur', function () {
    wrap.classList.remove('focused');
    if (input.value && input.value.trim() !== '') {
      wrap.classList.add('filled');
    } else {
      wrap.classList.remove('filled');
    }
  });

  // input -> cập nhật filled khi người gõ
  input.addEventListener('input', function () {
    if (input.value && input.value.trim() !== '') wrap.classList.add('filled');
    else wrap.classList.remove('filled');
  });
});




