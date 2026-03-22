    <footer class="bg-white border-top py-4 mt-auto">
        <div class="container" style="max-width: 1050px; font-size: 12px; color: #8a8d91;">

            <div class="d-flex flex-wrap gap-2 mb-1">
                <a href="?lang=vi" class="footer-link">Tiếng Việt</a>
                <a href="?lang=en" class="footer-link">English (UK)</a>
                <a href="?lang=zh" class="footer-link">中文(台灣)</a>
                <a href="?lang=kr" class="footer-link">한국어</a>
                <a href="?lang=jp" class="footer-link">日本語</a>
                <a href="?lang=fr" class="footer-link">Français (France)</a>
                <a href="?lang=th" class="footer-link">ภาษาไทย</a>
                <a href="?lang=es" class="footer-link">Español</a>
                <a href="?lang=pt" class="footer-link">Português (Brasil)</a>
                <a href="?lang=de" class="footer-link">Deutsch</a>
                <a href="?lang=it" class="footer-link">Italiano</a>
                <a href="#" class="footer" title="Xem thêm" data-bs-toggle="modal" data-bs-target="#languageModal">+</a>
            </div>
            <div class="modal fade" id="languageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                    <div class="modal-content border-0 shadow">
                        <!-- Header -->
                        <div class="modal-header border-0">
                            <h5 class="modal-title fw-bold fs-5">Chọn ngôn ngữ của bạn</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <!-- Search box -->
                        <div class="modal-body pt-0">
                            <div class="px-3 mb-3">
                                <input type="text" class="form-control form-control-sm" placeholder="Tìm ngôn ngữ..." id="languageSearch">
                            </div>

                            <div class="row g-3" id="languageList">
                                <div class="col-12">
                                    <strong class="small text-muted">Ngôn ngữ được đề xuất</strong>
                                </div>
                                <div class="d-flex flex-wrap gap-3 mb-2 small">
                                    <a href="?lang=vi" class="language-item py-2 text-dark <?= ($_SESSION['lang'] ?? 'vi') == 'vi' ? 'fw-bold text-primary' : '' ?>">Tiếng Việt <?= ($_SESSION['lang'] ?? 'vi') == 'vi' ? '✓' : '' ?></a>
                                    <a href="?lang=en" class="language-item py-2 text-dark <?= ($_SESSION['lang'] ?? 'vi') == 'en' ? 'fw-bold text-primary' : '' ?>">English (UK) <?= ($_SESSION['lang'] ?? 'vi') == 'en' ? '✓' : '' ?></a>
                                    <a href="?lang=zh" class="language-item py-2 text-dark <?= ($_SESSION['lang'] ?? 'vi') == 'zh' ? 'fw-bold text-primary' : '' ?>">中文(台灣) <?= ($_SESSION['lang'] ?? 'vi') == 'zh' ? '✓' : '' ?></a>
                                    <a href="?lang=kr" class="language-item py-2 text-dark <?= ($_SESSION['lang'] ?? 'vi') == 'kr' ? 'fw-bold text-primary' : '' ?>">한국어 <?= ($_SESSION['lang'] ?? 'vi') == 'ko' ? '✓' : '' ?></a>
                                    <a href="?lang=jp" class="language-item py-2 text-dark <?= ($_SESSION['lang'] ?? 'vi') == 'jp' ? 'fw-bold text-primary' : '' ?>">日本語 <?= ($_SESSION['lang'] ?? 'vi') == 'ja' ? '✓' : '' ?></a>
                                    <a href="?lang=es" class="language-item py-2 text-dark <?= ($_SESSION['lang'] ?? 'vi') == 'es' ? 'fw-bold text-primary' : '' ?>">Español <?= ($_SESSION['lang'] ?? 'vi') == 'es' ? '✓' : '' ?></a>
                                    <a href="?lang=pt" class="language-item py-2 text-dark <?= ($_SESSION['lang'] ?? 'vi') == 'pt' ? 'fw-bold text-primary' : '' ?>">Português (Brasil) <?= ($_SESSION['lang'] ?? 'vi') == 'pt' ? '✓' : '' ?></a>
                                </div>

                                <div class="col-12 mt-3">
                                    <hr class="my-2">
                                </div>
                                <div class="col-12">
                                    <strong class="small text-muted">Tất cả ngôn ngữ</strong>
                                </div>

                                <div class="d-flex flex-wrap gap-3 mb-2 small" id="languageListInline">
                                    
                                    <a href="?lang=vi" class="language-item py-2 text-dark">Tiếng Việt</a>
                                    <a href="?lang=en" class="language-item py-2 text-dark">English</a>
                                    <a href="?lang=zh" class="language-item py-2 text-dark"></a>
                                    <a href="?lang=kr" class="language-item py-2 text-dark">中文(台灣)</a>
                                    <a href="?lang=jp" class="language-item py-2 text-dark">한국어</a>
                                    <a href="?lang=es" class="language-item py-2 text-dark">Español</a>
                                    <a href="?lang=pt" class="language-item py-2 text-dark">Português (Brasil)</a>
                                    <a href="?lang=ar" class="language-item py-1 text-dark">العربية</a>
                                    <a href="?lang=de" class="language-item py-1 text-dark">Deutsch</a>
                                    <a href="?lang=fr" class="language-item py-1 text-dark">Français (France)</a>
                                    <a href="?lang=hi" class="language-item py-1 text-dark">हिन्दी</a>
                                    <a href="?lang=id" class="language-item py-1 text-dark">Bahasa Indonesia</a>
                                    <a href="?lang=it" class="language-item py-1 text-dark">Italiano</a>
                                    <a href="?lang=th" class="language-item py-1 text-dark">ภาษาไทย</a>
                                    <a href="?lang=tr" class="language-item py-1 text-dark">Türkçe</a>
                                    <a href="?lang=ru" class="language-item py-1 text-dark">Русский</a>
                                    <a href="?lang=pl" class="language-item py-1 text-dark">Polski</a>
                                    <a href="?lang=nl" class="language-item py-1 text-dark">Nederlands</a>
                                    <a href="?lang=sv" class="language-item py-1 text-dark">Svenska</a>
                                </div>

                            </div>
                        </div>

                        <div class="modal-footer border-0 justify-content-center">
                            <button type="button" class="btn btn-primary px-5" data-bs-dismiss="modal">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>



            <hr class="my-2 border-secondary-subtle">


            <div class="d-flex flex-wrap gap-0-8 small mb-0">
                <a href="#" class="footer-link"><?= $text['signup'] ?? 'Đăng ký' ?></a>
                <a href="#" class="footer-link"><?= $text['login'] ?? 'Đăng nhập' ?></a>
                <a href="#" class="footer-link"><?= $text['messenger'] ?? 'Messenger' ?></a>
                <a href="#" class="footer-link"><?= $text['facebook_lite'] ?? 'Facebook Lite' ?></a>
                <a href="#" class="footer-link"><?= $text['video'] ?? 'Video' ?></a>
                <a href="#" class="footer-link"><?= $text['meta_pay'] ?? 'Meta Pay' ?></a>
                <a href="#" class="footer-link"><?= $text['meta_store'] ?? 'Cửa hàng trên Meta' ?></a>
                <a href="#" class="footer-link"><?= $text['meta_quest'] ?? 'Meta Quest' ?></a>
                <a href="#" class="footer-link"><?= $text['rayban_meta'] ?? 'Ray-Ban Meta' ?></a>
                <a href="#" class="footer-link"><?= $text['meta_ai'] ?? 'Meta AI' ?></a>
                <a href="#" class="footer-link"><?= $text['ai_content'] ?? 'Nội dung khác do Meta AI tạo' ?></a>
                <a href="#" class="footer-link"><?= $text['instagram'] ?? 'Instagram' ?></a>
                <a href="#" class="footer-link"><?= $text['threads'] ?? 'Threads' ?></a>
                <a href="#" class="footer-link"><?= $text['voting_center'] ?? 'Trung tâm thông tin bỏ phiếu' ?></a>
                <a href="#" class="footer-link"><?= $text['privacy_policy'] ?? 'Chính sách quyền riêng tư' ?></a>
                <a href="#" class="footer-link"><?= $text['privacy_center'] ?? 'Trung tâm quyền riêng tư' ?></a>
                <a href="#" class="footer-link"><?= $text['about'] ?? 'Giới thiệu' ?></a>
                <a href="#" class="footer-link"><?= $text['create_ad'] ?? 'Tạo quảng cáo' ?></a>
                <a href="#" class="footer-link"><?= $text['create_page'] ?? 'Tạo Trang' ?></a>
                <a href="#" class="footer-link"><?= $text['terms'] ?? 'Điều khoản' ?></a>
                <a href="#" class="footer-link"><?= $text['developers'] ?? 'Nhà phát triển' ?></a>
                <a href="#" class="footer-link"><?= $text['careers'] ?? 'Tuyển dụng' ?></a>
                <a href="#" class="footer-link"><?= $text['cookies'] ?? 'Cookie' ?></a>
                <a href="https://www.facebook.com/help/568137493302217" class="footer-link">
                    <?= $text['ad_choices'] ?? 'Lựa chọn quảng cáo' ?>
                    <span class="ad-choices-icon-tooltip">
                        <i class="img sp_GPvE0syHYuh sx_7d98b4"></i>
                    </span>
                </a>
                <a href="#" class="footer-link"><?= $text['help'] ?? 'Trợ giúp' ?></a>
                <a href="#" class="footer-link"><?= $text['contact_upload'] ?? 'Tải thông tin liên hệ &amp; đối tượng không phải người dùng' ?></a>
            </div>

            <div class="text-muted small">
                Meta © <span id="year">2025</span>
            </div>
        </div>
    </footer>

    <style>
        .footer {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 35px;
            height: 22px;
            background-color: #f5f6f7;
            border: 1px solid #ccd0d5;
            border-radius: 2px;
            font-size: 18px;
            font-weight: 900;
            color: #4b4f56;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
            box-sizing: border-box;
        }

        #languageModal .modal-body a:hover {
            text-decoration: underline;
            color: #1877f2 !important;
        }

        .footer:hover {
            background-color: #ebedf0;
        }

        .gap-0-8 {
            column-gap: 15px;
            row-gap: 5px;
        }

        .sp_GPvE0syHYuh.sx_7d98b4 {
            background-position: -22px -37px;
        }

        .sp_GPvE0syHYuh {
            background-image: url(https://static.xx.fbcdn.net/rsrc.php/v4/yB/r/sQkNqpqx9ne.png);
            background-position: 0 -8px;
            background-repeat: no-repeat;
            display: inline-block;
            height: 13.1px;
            width: 13.1px;
        }

    
    .ad-choices-icon-tooltip {
        position: relative;
        display: inline-block;
        width: 16px;
        height: 16px;
        margin-left: 3px;
        vertical-align: -1px;
        cursor: pointer;
    }


    .ad-choices-icon-tooltip::after {
        content: "Tìm hiểu về Lựa chọn quảng cáo.";
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        color: #1c1e21;
        font-size: 12px;
        font-weight: 500;
        padding: 8px 12px;
        border: 1px solid #1c1e21;
        border-radius: 6px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        white-space: nowrap;
        z-index: 9999;
        margin-top: 8px;
        

        opacity: 0;
        visibility: hidden;
        transition: opacity 0.5s ease, visibility 0s linear 1s;  
    }


    .ad-choices-icon-tooltip:hover::after {
        opacity: 1;
        visibility: visible;
        transition-delay: 0s, 0s;  
    }


        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }

        .text-muted {
            font-size: 12px;
            color: #737373 !important;
            text-decoration: none !important;
            padding: 2px 2px;
            border-radius: 4px;
            transition: background 0.2s;
            display: block;
            margin-top: 25px;
            text-align: left;
        }

        .footer-link {
            font-size: 12px;
            color: #8a8d91 !important;
            text-decoration: none !important;
            padding: 2px 2px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .footer-link:hover {
            text-decoration: underline !important;
        }

        @media (max-width: 576px) {
            .footer-link {
                font-size: 11px;
            }
        }
    </style>

    <script>
        document.getElementById('languageSearch')?.addEventListener('input', function(e) {
            const term = e.target.value.trim().toLowerCase()
                .replace(/[àáảãạâầấẩẫậăằắẳẵặ]/g, 'a')
                .replace(/[èéẻẽẹêềếểễệ]/g, 'e')
                .replace(/[ìíỉĩị]/g, 'i')
                .replace(/[òóỏõọôồốổỗộơờớởỡợ]/g, 'o')
                .replace(/[ùúủũụưừứửữự]/g, 'u')
                .replace(/[ỳýỷỹỵ]/g, 'y')
                .replace(/đ/g, 'd');

            const allLinks = document.querySelectorAll('#languageModal .language-item');
            let hasVisible = false;

            allLinks.forEach(link => {
                let text = link.textContent.toLowerCase();
                // Bỏ dấu tiếng Việt để so sánh
                text = text.replace(/[àáảãạâầấẩẫậăằắẳẵặ]/g, 'a')
                    .replace(/[èéẻẽẹêềếểễệ]/g, 'e')
                    .replace(/[ìíỉĩị]/g, 'i')
                    .replace(/[òóỏõọôồốổỗộơờớởỡợ]/g, 'o')
                    .replace(/[ùúủũụưừứửữự]/g, 'u')
                    .replace(/[ỳýỷỹỵ]/g, 'y')
                    .replace(/đ/g, 'd');

                if (term === '' || text.includes(term)) {
                    link.style.display = '';
                    hasVisible = true;
                } else {
                    link.style.display = 'none';
                }
            });

            // Ẩn/hiện cột
            document.querySelectorAll('#languageModal .col-6, #languageModal .col-md-3').forEach(col => {
                const visible = col.querySelector('.language-item[style=""]');
                col.style.display = visible ? '' : 'none';
            });

            // Thông báo không tìm thấy
            let msg = document.getElementById('noResultMsg');
            if (term !== '' && !hasVisible) {
                if (!msg) {
                    msg = document.createElement('div');
                    msg.id = 'noResultMsg';
                    msg.className = 'text-center text-muted py-4 small';
                    msg.innerHTML = `Không tìm thấy kết quả cho "<strong>${e.target.value}</strong>"`;
                    document.querySelector('#languageModal .modal-body').appendChild(msg);
                }
            } else if (msg) {
                msg.remove();
            }
        });

        document.getElementById('year').textContent = new Date().getFullYear();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>