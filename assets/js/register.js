/* register.js — sửa: xử lý redirect 3xx từ server + giữ nguyên UX cũ */

/* ========== TRẠNG THÁI CHUNG ========== */
let submitCount = 0;
let hasValidated = false;
let hasServerError = false;
let currentVisibleTooltip = null;

function findWrapper(el) {
    return el?.closest('#ageArea') ||
        el?.closest('.birthday-wrapper') ||
        el?.closest('.gender-wrapper') ||
        el?.closest('.input-group') || null;
}

function getBirthdayValues() {
    return {
        day: document.querySelector('input[name="birthday_day"]').value || '0',
        month: document.querySelector('input[name="birthday_month"]').value || '0',
        year: document.querySelector('input[name="birthday_year"]').value || '0'
    };
}

function hideAllTooltips() {
    document.querySelectorAll('.error-tooltip.show').forEach(t => t.classList.remove('show'));
    document.querySelectorAll('.active-error').forEach(w => w.classList.remove('active-error'));
    currentVisibleTooltip = null;
}

function closeAllOptions() {
    document.querySelectorAll('.fake-select .options').forEach(opt => opt.style.display = 'none');
    document.querySelectorAll('.fake-select').forEach(s => s.classList.remove('active'));
}

function restoreBirthdayFakeSelects() {
    document.querySelectorAll('.fake-select').forEach(s => {
        s.style.visibility = 'visible';
        const opt = s.querySelector('.options');
        if (opt && s.classList.contains('active')) opt.style.display = 'block';
    });
}

/*
  showTooltipFor(wrapper, preserveOptions)
  preserveOptions = true -> don't close dropdown options (we only close if they overlap)
*/
function showTooltipFor(wrapper, preserveOptions = false) {
    if (!wrapper) return;
    hideAllTooltips();
    if (!preserveOptions) closeAllOptions();
    wrapper.classList.add('active-error');
    const tooltip = wrapper.querySelector('.birthday-inner .error-tooltip') || wrapper.querySelector('.error-tooltip');
    if (tooltip) {
        tooltip.classList.add('show');
        currentVisibleTooltip = tooltip;
    }
}

/* ========== SUBMIT ========== */
// Make sure DOM exists
document.addEventListener('DOMContentLoaded', function() {
    const formEl = document.getElementById('registerForm');
    if (!formEl) return;

    formEl.addEventListener('submit', function(e) {
        e.preventDefault();
        submitCount++;

        // reset visuals
        document.querySelectorAll('.input-group, .birthday-wrapper, .gender-wrapper, #ageArea')
            .forEach(el => el.classList.remove('error', 'active-error'));
        document.querySelectorAll('.label-with-help, .gender-label').forEach(l => l.classList.remove('error'));
        hideAllTooltips();
        restoreBirthdayFakeSelects();
        closeAllOptions();

        // collect values (same as before)
        const ho = (document.querySelector('input[name="ho"]')?.value || '').trim();
        const ten = (document.querySelector('input[name="ten"]')?.value || '').trim();
        const { day, month, year } = getBirthdayValues();
        const ageInput = (document.querySelector('input[name="age_input"]')?.value || '').trim();
        const sex = document.querySelector('input[name="sex"]:checked');
        const customText = (document.querySelector('input[name="custom_gender_text"]')?.value || '').trim();
        const customSelect = document.querySelector('select[name="custom_gender"]')?.value || '';
        const email = (document.querySelector('input[name="email"]')?.value || '').trim();
        const pass = document.querySelector('input[name="matkhau"]')?.value || '';

        // client-side validation (unchanged)
        let hasError = false;
        if (!ho) {
            document.querySelector('input[name="ho"]').closest('.input-group').classList.add('error');
            hasError = true;
        }
        if (!ten) {
            document.querySelector('input[name="ten"]').closest('.input-group').classList.add('error');
            hasError = true;
        }

        const ageMode = document.getElementById('ageArea').style.display === 'flex';
        if (ageMode) {
            if (!ageInput || parseInt(ageInput) < 13) {
                document.querySelector('#ageArea').classList.add('error');
                const t = document.querySelector('#ageArea .error-tooltip');
                if (t) {
                    t.classList.add('show');
                    currentVisibleTooltip = t;
                }
                hasError = true;
            }
        } else {
            if (day === "0" || month === "0" || year === "0") {
                document.querySelector('#birthdayLabel').classList.add('error');
                document.querySelector('.birthday-wrapper').classList.add('error');
                hasError = true;
            }
        }

        if (!sex || (sex.value === "-1" && !customText && !customSelect)) {
            document.querySelector('#genderLabel').classList.add('error');
            document.querySelectorAll('.gender-label').forEach(l => l.classList.add('error'));
            hasError = true;
        }

        if (!email) {
            const f = document.querySelector('input[name="email"]');
            f.closest('.input-group').classList.add('error');
            hasError = true;
        }
        if (!pass || pass.length < 6) {
            document.querySelector('input[name="matkhau"]').closest('.input-group').classList.add('error');
            hasError = true;
        }

        hasValidated = true;
        hasServerError = hasError;

        if (hasError && submitCount >= 2) {
            switchToAgeInput();
            submitCount = 0;
        }

        if (hasError) {
            // focus first error and show tooltip (same behaviour as before)
            const firstError = document.querySelector('.error');
            if (firstError) {
                const target = firstError.querySelector('input, .fake-select');
                if (target) try { target.focus(); } catch (err) {}
                const preserve = firstError.classList.contains('birthday-wrapper');
                showTooltipFor(firstError, preserve);
            }
            return; // stop; do not send to server
        }

        // No client error -> submit via AJAX (fetch) so PHP can return JSON redirect
        submitCount = 0;
        hasServerError = false;

        const form = this;
        const fd = new FormData(form);

        let targetUrl = form.action || window.location.pathname;
const ajaxFallbackParam = '_ajax=1';
if (targetUrl.indexOf('?') === -1) targetUrl += '?' + ajaxFallbackParam;
else targetUrl += '&' + ajaxFallbackParam;

fetch(targetUrl, {
    method: 'POST',
    body: fd,
    credentials: 'same-origin',
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    },
    redirect: 'follow'
}).then(async (res) => {
            // If server issued a redirect and browser followed it,
            // fetch exposes res.redirected = true and res.url = final URL.
            if (res.redirected) {
                // Go straight to the final URL (likely verify.php)
                window.location.href = res.url;
                return null;
            }

            // If server returned a JSON (normal AJAX response)
            const ct = res.headers.get('content-type') || '';
            if (ct.indexOf('application/json') !== -1) {
                return res.json();
            }

            // If server returns HTML but not redirect, try to inspect status:
            // - 200 HTML: maybe server rendered page (non-AJAX flow) -> navigate to it
            // We'll attempt to read text and if it contains a known redirect snippet we act, else reload.
            const txt = await res.text();
            // If the server returned a small page that contains a JS redirect to verify.php, try to parse it:
            if (txt.indexOf('window.location.href') !== -1 && txt.indexOf('verify.php') !== -1) {
                // fallback: navigate to verify.php
                window.location.href = 'verify.php';
                return null;
            }

            // otherwise reload current page (non-AJAX fallback)
            window.location.reload();
            return null;
        }).then(data => {
            if (!data) return;
            if (data.ok && data.redirect) {
                // go to verify page
                window.location.href = data.redirect;
                return;
            }
            // handle server validation errors
            if (data.errors) {
                // clear previous
                document.querySelectorAll('.input-group, .birthday-wrapper, .gender-wrapper, #ageArea').forEach(el => el.classList.remove('error'));
                // apply errors
                Object.keys(data.errors).forEach(key => {
                    const msg = data.errors[key];
                    // map known keys to UI
                    if (key === 'birthday') {
                        document.querySelector('#birthdayLabel').classList.add('error');
                        document.querySelector('.birthday-wrapper').classList.add('error');
                        const tt = document.querySelector('.birthday-wrapper .error-tooltip');
                        if (tt) { tt.textContent = msg; tt.classList.add('show'); currentVisibleTooltip = tt; }
                    } else if (key === 'age') {
                        const area = document.querySelector('#ageArea');
                        if (area) { area.classList.add('error'); const tt = area.querySelector('.error-tooltip'); if (tt) { tt.textContent = msg; tt.classList.add('show'); currentVisibleTooltip = tt; } }
                    } else if (key === 'sex') {
                        document.querySelector('#genderLabel').classList.add('error');
                        document.querySelectorAll('.gender-label').forEach(l => l.classList.add('error'));
                        const tt = document.querySelector('#customouter .error-tooltip');
                        if (tt) { tt.textContent = msg; tt.classList.add('show'); currentVisibleTooltip = tt; }
                    } else {
                        // fallback: try to find field by name
                        const field = document.querySelector('[name="' + key + '"]');
                        if (field) {
                            const wrap = field.closest('.input-group') || field.closest('.birthday-wrapper') || field.parentElement;
                            if (wrap) wrap.classList.add('error');
                            const tt = wrap?.querySelector('.error-tooltip');
                            if (tt) { tt.textContent = msg; tt.classList.add('show'); currentVisibleTooltip = tt; }
                        }
                    }
                });
            }
            if (data.global_error) {
                // show global error — you can replace with nicer UI
                alert((data.global_error.title || 'Lỗi') + "\n\n" + (data.global_error.message || 'Đã xảy ra lỗi'));
            }
        }).catch(err => {
            console.error('Submit error', err);
            alert('Lỗi mạng hoặc máy chủ. Vui lòng thử lại.');
        });
    });
});

/* ========== FIELD VALIDATION ========== */
function validateField() {
    const wrapper = findWrapper(this);
    if (!wrapper) return;

    if (['ho', 'ten', 'email'].includes(this.name)) {
        if (this.value.trim() !== "") {
            wrapper.classList.remove('error', 'active-error');
            wrapper.querySelector('.error-tooltip')?.classList.remove('show');
            if (currentVisibleTooltip && wrapper.contains(currentVisibleTooltip)) currentVisibleTooltip = null;
        }
    }

    if (this.name === 'matkhau') {
        if (this.value.length >= 6) {
            wrapper.classList.remove('error', 'active-error');
            wrapper.querySelector('.error-tooltip')?.classList.remove('show');
        }
    }

    if (this.name.includes('birthday_') || this.name === 'age_input') {
        const ageMode = document.getElementById('ageArea').style.display === 'flex';
        const { day, month, year } = getBirthdayValues();
        const ageVal = document.querySelector('input[name="age_input"]')?.value.trim();
        let isValid = ageMode ? (ageVal !== "" && !isNaN(parseInt(ageVal)) && parseInt(ageVal) >= 13 && parseInt(ageVal) <= 120) : (day !== "0" && month !== "0" && year !== "0");

        document.querySelector('#birthdayLabel').classList.remove('error');
        document.querySelector('#ageArea')?.classList.remove('error');
        document.querySelector('.birthday-wrapper')?.classList.remove('error');

        if (!isValid && hasValidated) {
            document.querySelector('#birthdayLabel').classList.add('error');
            if (ageMode) document.querySelector('#ageArea').classList.add('error');
            else document.querySelector('.birthday-wrapper').classList.add('error');
        } else {
            const bw = document.querySelector('.birthday-wrapper');
            bw?.querySelector('.error-tooltip')?.classList.remove('show');
            if (currentVisibleTooltip && bw && bw.contains(currentVisibleTooltip)) currentVisibleTooltip = null;
            restoreBirthdayFakeSelects();
        }
    }

    if (this.name === 'sex' || this.name === 'custom_gender_text' || this.name === 'custom_gender') {
        const sexVal = document.querySelector('input[name="sex"]:checked');
        const customText = document.querySelector('input[name="custom_gender_text"]')?.value.trim();
        const customSel = document.querySelector('select[name="custom_gender"]')?.value;
        if (sexVal && (sexVal.value !== "-1" || customText || customSel)) {
            document.querySelector('#genderLabel').classList.remove('error');
            document.querySelectorAll('.gender-label').forEach(l => l.classList.remove('error'));
            document.querySelector('#customouter .error-tooltip')?.classList.remove('show');
        }
    }
}

document.querySelectorAll('input, .fake-select').forEach(el => {
    el.addEventListener('input', validateField);
    el.addEventListener('change', validateField);
    el.addEventListener('focus', function() {
        const focusedWrapper = findWrapper(this);
        if (currentVisibleTooltip) {
            const tipWrapper = currentVisibleTooltip.closest('.birthday-wrapper, .input-group, .gender-wrapper, #ageArea, .age-area');
            if (!focusedWrapper || (tipWrapper && !focusedWrapper.contains(currentVisibleTooltip))) {
                hideAllTooltips();
                restoreBirthdayFakeSelects();
                closeAllOptions();
            }
        }
    });
});

/* ========== FAKE-SELECT HANDLER ========== */
document.querySelectorAll('.fake-select').forEach(select => {
    const options = select.querySelector('.options');
    const hiddenInput = select.closest('.birthday-inner').querySelector(`input[name="${select.dataset.name}"]`);
    const birthdayWrapper = select.closest('.birthday-wrapper');

    select.addEventListener('click', e => {
        e.stopPropagation();

        // If birthday-wrapper has error -> show tooltip immediately (but keep visuals)
        if (birthdayWrapper && birthdayWrapper.classList.contains('error')) {
            // show tooltip but preserve options visuals
            showTooltipFor(birthdayWrapper, true);
            // now also toggle this dropdown (so user can open options on same click)
            const isOpen = select.classList.contains('active');
            // close others first
            document.querySelectorAll('.fake-select').forEach(s => {
                if (s !== select) {
                    s.classList.remove('active');
                    const opt = s.querySelector('.options');
                    if (opt) opt.style.display = 'none';
                }
            });
            if (isOpen) {
                select.classList.remove('active');
                options.style.display = 'none';
            } else {
                select.classList.add('active');
                options.style.display = 'block';
            }
            return;
        }

        // Normal behavior (no birthday error)
        hideAllTooltips();
        restoreBirthdayFakeSelects();
        document.querySelectorAll('.fake-select').forEach(s => {
            if (s !== select) {
                s.classList.remove('active');
                const opt = s.querySelector('.options');
                if (opt) opt.style.display = 'none';
            }
        });

        select.classList.toggle('active');
        options.style.display = select.classList.contains('active') ? 'block' : 'none';
    });

    // chọn option
    options.addEventListener("click", (e) => {
        // chặn event không bubble lên parent (quan trọng)
        e.stopPropagation();

        // tìm <li> gần nhất (nếu click vào con của li)
        const li = e.target.closest && e.target.closest("li");
        if (!li || !options.contains(li)) return;

        const value = li.dataset.value;
        const text = li.textContent.trim();

        select.querySelector(".selected-value").textContent = text;

        options
          .querySelectorAll("li")
          .forEach((item) => item.classList.remove("selected"));
        li.classList.add("selected");

        if (hiddenInput) hiddenInput.value = value;

        // đóng dropdown hiện tại NGAY lập tức
        select.classList.remove("active");
        options.style.display = "none";

        // validate lại
        if (hiddenInput) {
          validateField.call(hiddenInput);
          hiddenInput.dispatchEvent(new Event("change"));
        }
    });
});

/* ========== CLICK WRAPPER LỖI ========== */
document.querySelectorAll('.input-group, .birthday-wrapper, .gender-wrapper, #ageArea, .age-area')
    .forEach(wrapper => {
        wrapper.addEventListener('click', function(ev) {
            if (!this.classList.contains('error')) return;
            hideAllTooltips();

            if (this.classList.contains('birthday-wrapper')) {
                // chỉ đóng dropdown options (nếu đang mở) nhưng KHÔNG ẩn fake-select visuals
                document.querySelectorAll('.fake-select .options').forEach(opt => opt.style.display = 'none');

                const tt = this.querySelector('.birthday-inner .error-tooltip');
                if (tt) {
                    this.classList.add('active-error');
                    tt.classList.add('show');
                    currentVisibleTooltip = tt;
                }
            } else {
                const tt = this.querySelector('.error-tooltip');
                if (tt) {
                    this.classList.add('active-error');
                    tt.classList.add('show');
                    currentVisibleTooltip = tt;
                }
            }
            ev.stopPropagation();
        });
    });

/* ========== CLICK NGOÀI ========== */
document.addEventListener('click', function(e) {
    if (!e.target.closest('.error') && !e.target.closest('.error-tooltip')) {
        hideAllTooltips();
        restoreBirthdayFakeSelects();
        closeAllOptions();
    }
});

/* ========== SWITCH Age <-> Birthday ========== */
// ---------- Init: lưu text gốc và text age (lấy từ DOM) ----------
let __originalBirthdayLabelText = null;
let __ageLabelText = null;

document.addEventListener('DOMContentLoaded', function() {
    const bLbl = document.getElementById('birthdayLabel');
    const ageArea = document.getElementById('ageArea');

    // Lấy text node đầu tiên (server render) của birthdayLabel
    if (bLbl) {
        const tn = Array.from(bLbl.childNodes).find(n => n.nodeType === 3);
        __originalBirthdayLabelText = tn ? tn.nodeValue.trim() : bLbl.textContent.trim();
    }

    // Lấy text node đầu tiên (server render) của ageArea (bạn in trực tiếp tên tuổi vào đó)
    if (ageArea) {
        const tnAge = Array.from(ageArea.childNodes).find(n => n.nodeType === 3 && n.nodeValue.trim() !== '');
        __ageLabelText = tnAge ? tnAge.nodeValue.trim() : null;
        if (!__ageLabelText) {
            const labelEl = ageArea.querySelector('#ageLabel');
            if (labelEl) __ageLabelText = labelEl.textContent.trim();
        }
    }

    // Fallbacks
    if (!__originalBirthdayLabelText) __originalBirthdayLabelText = (document.getElementById('birthdayLabel')?.textContent || 'Ngày sinh').trim();
    if (!__ageLabelText) __ageLabelText = (document.getElementById('ageLabel')?.dataset?.ageLabel) || (document.getElementById('ageArea')?.textContent?.trim() || (document.getElementById('ageArea') ? document.getElementById('ageArea').querySelector('input')?.placeholder : null) ) || 'Tuổi';
});

// helper cập nhật text node của #birthdayLabel
function setBirthdayLabelText(newText) {
    const lbl = document.getElementById('birthdayLabel');
    if (!lbl) return;
    let textNode = Array.from(lbl.childNodes).find(n => n.nodeType === 3);
    if (textNode) {
        textNode.nodeValue = newText + ' ';
    } else {
        const firstEl = lbl.querySelector('*');
        const tn = document.createTextNode(newText + ' ');
        if (firstEl) lbl.insertBefore(tn, firstEl);
        else lbl.insertBefore(tn, lbl.firstChild);
    }
}

// helper show/hide help icon giữ nguyên từ code gốc
function setHelpIconVisible(visible) {
    const lbl = document.getElementById('birthdayLabel');
    if (!lbl) return;
    let icon = lbl.querySelector('.help-icon');
    if (!icon && visible) {
        icon = document.createElement('span');
        icon.className = 'help-icon';
        icon.textContent = '?';
        lbl.appendChild(icon);
    }
    if (icon) icon.style.display = visible ? '' : 'none';
}

/* ===== thay thế chức năng switch ===== */
function switchToAgeInput() {
    const ageText = __ageLabelText || 'Tuổi';
    setBirthdayLabelText(ageText);
    setHelpIconVisible(false);

    const birthdayArea = document.getElementById('birthdayArea');
    const ageArea = document.getElementById('ageArea');
    if (birthdayArea) birthdayArea.style.display = "none";
    if (ageArea) ageArea.style.display = "flex";

    const ageInput = document.querySelector('#ageArea input[name="age_input"]');
    if (!ageInput?.value?.trim() || parseInt(ageInput.value, 10) < 13) {
        ageArea?.classList.add('error');
        hasValidated = true;
    }

    const bw = document.querySelector('.birthday-wrapper');
    bw?.classList.remove('error', 'active-error');
    bw?.querySelector('.error-tooltip')?.classList.remove('show');

    closeAllOptions();
    restoreBirthdayFakeSelects();
}

function switchToBirthday() {
    const orig = __originalBirthdayLabelText || 'Ngày sinh';
    setBirthdayLabelText(orig);
    setHelpIconVisible(true);

    const birthdayArea = document.getElementById('birthdayArea');
    const ageArea = document.getElementById('ageArea');
    if (birthdayArea) birthdayArea.style.display = "flex";
    if (ageArea) ageArea.style.display = "none";

    if (ageArea) {
        ageArea.classList.remove('error', 'active-error');
        ageArea.querySelector('.error-tooltip')?.classList.remove('show');
    }

    const { day, month, year } = getBirthdayValues();
    const wrapper = document.querySelector('.birthday-wrapper');

    restoreBirthdayFakeSelects();

    if (day === "0" || month === "0" || year === "0") {
        if (wrapper) {
            wrapper.classList.add('error');
            if (hasValidated || hasServerError) {
                showTooltipFor(wrapper);
                const firstSelect = document.querySelector('.fake-select');
                try { firstSelect?.focus(); } catch (e) {}
            } else {
                closeAllOptions();
            }
        }
    } else {
        if (wrapper) {
            wrapper.classList.remove('error', 'active-error');
            wrapper.querySelector('.error-tooltip')?.classList.remove('show');
        }
        restoreBirthdayFakeSelects();
    }
}

/* ========== GENDER CUSTOM ========== */
function showCustom() {
    document.getElementById('customouter').style.display = "block";
}

function hideCustom() {
    document.getElementById('customouter').style.display = "none";
}
