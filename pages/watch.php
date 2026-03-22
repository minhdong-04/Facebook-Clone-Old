<?php
require_once __DIR__ . '/../includes/db.php';

if (!Database::isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

$currentUser = Database::getCurrentUser();
if (!$currentUser) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

function fb_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fb_avatar_url(?string $avatar): string
{
    $avatar = trim((string)$avatar);
    if ($avatar === '') return '../assets/images/default-avatar.png';
    if (preg_match('~^https?://~i', $avatar)) return $avatar;
    if (strlen($avatar) > 0 && $avatar[0] === '/') return $avatar;
    $uploadsPath = __DIR__ . '/../uploads/' . $avatar;
    if (is_file($uploadsPath)) return '../uploads/' . rawurlencode($avatar);
    $assetsPath = __DIR__ . '/../assets/images/' . $avatar;
    if (is_file($assetsPath)) return '../assets/images/' . rawurlencode($avatar);
    return '../uploads/' . rawurlencode($avatar);
}

$currentUserNameSafe = fb_escape((string)($currentUser['name'] ?? ''));
$currentUserAvatar = fb_avatar_url($currentUser['avatar'] ?? null);

// Resolve a video file to show on this page.
// Priority: explicit `?file=filename.mp4` query param (basename-validated),
// otherwise pick the most recently modified .mp4 in the uploads folder.
$videoFileUrl = '';
try {
    if (isset($_GET['file']) && is_string($_GET['file']) && $_GET['file'] !== '') {
        $candidate = basename($_GET['file']);
        $fullPath = __DIR__ . '/../uploads/' . $candidate;
        if (is_file($fullPath)) {
            $videoFileUrl = '../uploads/' . rawurlencode($candidate);
        }
    }

    if ($videoFileUrl === '') {
        $pattern = __DIR__ . '/../uploads/*.mp4';
        $list = glob($pattern);
        if (is_array($list) && count($list) > 0) {
            usort($list, function ($a, $b) {
                return filemtime($b) <=> filemtime($a);
            });
            $best = $list[0];
            if (is_file($best)) {
                $videoFileUrl = '../uploads/' . rawurlencode(basename($best));
            }
        }
    }
} catch (Throwable $_e) {
    // fallback to empty — template will handle missing video
    $videoFileUrl = '';
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facebook</title>
    <link rel="icon" href="../uploads/fb_logo.jpg">
    <link rel="stylesheet" href="../assets/css/messenger-popover.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script>
        (function() {
            try {
                const KEY = 'fbmenu-theme';
                const root = document.documentElement;
                if (!root) return;

                const apply = () => {
                    let v = 'off';
                    try {
                        v = localStorage.getItem(KEY) || 'off';
                    } catch (_e) {
                        v = 'off';
                    }
                    const theme = (v === 'on') ? 'dark' : (v === 'auto' ? 'auto' : 'light');
                    root.setAttribute('data-theme', theme);
                    try {
                        document.body && document.body.setAttribute('data-theme', theme);
                    } catch (_e) {}
                };

                apply();
                window.addEventListener('storage', (e) => {
                    if (e && e.key === KEY) apply();
                });

                const mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
                if (mq) {
                    const onChange = () => {
                        try {
                            if ((localStorage.getItem(KEY) || 'off') === 'auto') apply();
                        } catch (_e) {}
                    };
                    try {
                        mq.addEventListener ? mq.addEventListener('change', onChange) : mq.addListener(onChange);
                    } catch (_e) {}
                }
            } catch (_e) {}
        })();
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #000;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #fff;
            height: 100vh;
            overflow: hidden;
            padding-top: 56px;
        }

        :root {
            --fb-blue: #0866ff;
            --app-page-bg: #000;
            --app-surface-bg: #ffffff;
            --app-text: #050505;
            --app-muted: #65676B;
            --app-hover: #f2f2f2;
            --app-icon-bg: #e4e6eb;
            --app-active-pill-bg: #ffffff;
            --icon-hover-bg: #f0f2f5;
            --search-bg: #f0f2f5;
            --search-text: #050505;
            --search-placeholder: #606770;
            --search-border: rgba(0, 0, 0, 0.06);
            /* See-all button (account popover) - dark theme defaults */
            --seeall-bg: #393b3d;
            --seeall-text: #e4e6eb;
            --seeall-hover: #4a4b4d;
        }

        /* ===== Home-like TOP NAV (copied from home.php) ===== */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: var(--app-surface-bg);
            display: flex;
            align-items: center;
            padding: 0 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .1);
            z-index: 1000;
        }

        .header-left {
            display: flex;
            align-items: center;
            width: 360px;
            gap: 12px;
        }

        .logo-area {
            display: flex;
            align-items: center;
        }

        .fb-logo {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 999px;
            user-select: none;
        }

        .fb-logo:focus-visible {
            outline: 2px solid rgba(8, 102, 255, .55);
            outline-offset: 2px;
        }

        .back-btn {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--app-muted);
            opacity: 0;
            pointer-events: none;
            transition: .2s;
            z-index: 10;
            background: transparent;
            border: none;
        }

        .back-btn:hover {
            background: var(--app-hover);
        }

        .search-wrapper {
            height: 40px;
            width: 240px;
            background: var(--search-bg);
            border-radius: 20px;
            position: relative;
            display: flex;
            align-items: center;
            transition: width .2s, background .2s;
            box-shadow: none;
            border: 1px solid var(--search-border);
            padding-right: 8px;
        }

        #searchResult {
            position: absolute;
            top: calc(100% + 8px);
            left: calc(50% - 30px);
            transform: translateX(-50%);
            width: calc(100% + 56px);
            padding: 0;
            z-index: 2;
            pointer-events: none;
            max-height: 360px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            background: transparent;
        }

        #searchResult .empty {
            padding: 10px 12px;
            font-size: 15px;
            color: var(--app-muted);
            background: transparent;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px);
            transition: opacity .12s ease, transform .12s ease;
            pointer-events: auto;
        }

        .search-wrapper:focus-within #searchResult .empty {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            color: var(--search-placeholder);
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-icon svg {
            width: 22px;
            height: 22px;
            margin-top: 5px;
        }

        .fake-placeholder {
            position: absolute;
            left: 38px;
            font-size: 15px;
            color: var(--search-placeholder);
            pointer-events: none;
            z-index: 2;
            white-space: nowrap;
            user-select: none;
        }

        .search-input {
            width: 100%;
            height: 100%;
            border: none;
            background: transparent;
            outline: none;
            font-size: 15px;
            color: var(--search-text);
            caret-color: var(--search-text);
            padding-left: 56px;
            padding-right: 16px;
        }

        .search-input::placeholder {
            opacity: 0;
        }

        .header-left.is-searching .logo-area {
            display: none;
        }

        .header-left.is-searching .back-btn {
            opacity: 1;
            pointer-events: auto;
        }

        .search-wrapper.focused .fake-placeholder,
        .search-wrapper.has-text .fake-placeholder {
            opacity: 0;
            transform: translateX(-26px);
            transition: transform .24s ease, opacity .18s ease;
        }

        #searchResult {
            display: none;
        }

        #searchResult .user-item {
            pointer-events: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: 10px;
            cursor: pointer;
            user-select: none;
        }

        #searchResult .user-item:hover {
            background: var(--app-hover);
        }

        #searchResult .user-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        #searchResult .user-left span {
            font-size: 15px;
            color: var(--app-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #searchResult .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            object-fit: cover;
            background: var(--app-icon-bg);
            flex-shrink: 0;
        }

        #searchResult .user-avatar.ph {
            display: inline-block;
        }

        #searchResult .recent-remove {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            border: none;
            background: transparent;
            color: var(--app-muted);
            cursor: pointer;
        }

        #searchResult .recent-remove:hover {
            background: var(--app-hover);
        }

        .center-nav-wrap {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1001;
        }

        .center-nav {
            display: flex;
            gap: 10px;
            align-items: center;
            height: 56px;
            padding: 0 8px;
            transform: translateX(-30px);
        }

        .cnav-item {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 64px;
            height: 56px;
            padding: 0 6px;
            border-radius: 12px;
            position: relative;
            cursor: pointer;
            color: var(--app-muted);
            text-decoration: none;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            outline: none;
            transition: background .18s ease, transform .12s ease;
        }

        .cnav-item .icon-bg {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 100px;
            height: 48px;
            padding: 0 14px;
            border-radius: 10px;
            background: transparent;
            transition: background .18s ease, transform .12s ease, box-shadow .18s ease;
            flex-shrink: 0;
            box-sizing: border-box;
        }

        .cnav-item svg {
            width: 23px;
            height: 23px;
            display: block;
            color: inherit;
            fill: currentColor;
        }

        .cnav-item:not(.active):hover .icon-bg,
        .cnav-item:not(.active):focus-visible .icon-bg {
            background: var(--icon-hover-bg);
            border-radius: 8px;
            transition: background 0.2s;
        }

        .center-nav .cnav-item.active {
            color: var(--fb-blue) !important;
        }

        .center-nav .cnav-item.active svg {
            color: var(--fb-blue) !important;
        }

        .center-nav .cnav-item.active svg * {
            fill: currentColor !important;
            stroke: none !important;
        }

        .cnav-item.active .icon-bg {
            background: var(--app-active-pill-bg) !important;
            border-radius: 8px !important;
            border: none !important;
            transform: none !important;
            box-shadow: none !important;
        }

        .cnav-item .underline {
            position: absolute;
            bottom: 0px;
            left: 12px;
            right: 12px;
            height: 3px;
            border-radius: 3px;
            transform-origin: center;
            transform: scaleX(0);
            background: transparent;
            transition: transform .36s cubic-bezier(.08, .52, .52, 1), background .28s ease;
        }

        .cnav-item.active .underline {
            transform: scaleX(1);
            background: var(--fb-blue);
        }

        .header-right {
            width: 300px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .header-right .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            border: none;
            background: var(--app-icon-bg);
            color: var(--app-text);
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            user-select: none;
            transition: background .12s ease, transform .12s ease;
        }

        .header-right .icon-btn {
            text-decoration: none;
        }

        .header-right .icon-btn:hover {
            background: var(--icon-hover-bg);
        }

        .header-right .icon-btn:active {
            transform: scale(0.98);
        }

        .header-right .icon-btn:focus-visible {
            outline: 2px solid rgba(8, 102, 255, .55);
            outline-offset: 2px;
        }

        .header-right .icon-btn svg {
            width: 20px;
            height: 20px;
            display: block;
            fill: currentColor;
        }

        .header-right .icon-btn.account-btn {
            background: transparent;
        }

        .header-right .icon-btn.account-btn:hover {
            background: transparent;
        }

        .header-right .icon-btn.account-btn .account-avatar {
            width: 40px;
            height: 40px;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .header-right .icon-btn.account-btn .account-avatar>svg {
            width: 40px;
            height: 40px;
            display: block;
        }

        .header-right .icon-btn.account-btn .account-avatar-ring {
            fill: none;
            stroke: rgba(0, 0, 0, .08);
            stroke-width: 1;
        }

        .header-right .icon-btn.account-btn .account-caret {
            position: absolute;
            bottom: 6px;
            right: 6px;
            transform: translate(50%, 50%);
        }

        .header-right .icon-btn.account-btn .account-caret-bg {
            width: 11px;
            height: 11px;
            border-radius: 999px;
            background: var(--app-icon-bg);
            color: var(--app-text);
            box-shadow: 0 0 0 2px var(--app-surface-bg);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .header-right .icon-btn.account-btn .account-caret-bg svg {
            width: 11px;
            height: 11px;
        }

        /* ================= Account popover (Home) ================= */
        .account-popover {
            --panel-bg: var(--app-surface-bg);
            --text: var(--app-text);
            --secondary-text: var(--app-muted);
            --hover-bg: var(--app-hover);
            --icon-bg: var(--app-icon-bg);
            --sprite-filter: var(--app-sprite-filter, none);

            --item-height: 44px;
            --panel-padding: 10px;

            --font-user-name: 16px;
            --font-body: 13px;
            --font-item-label: 15px;
            --font-sec-title: 20px;
            --font-submenu-label: 15px;
            --font-setting-name: 14px;
            --font-setting-desc: 13px;
            --font-radio-label: 14px;
            --font-simple-text: 15px;

            --acct-x: 0px;
            --acct-y: 0px;
            --acct-offset-y: -4px;
            --acct-offset-x: 2px;
            --acct-left-pad: 8px;
            --acct-right-pad: 4px;

            position: fixed;
            top: 0;
            left: 0;
            width: 370px;
            height: 460px;
            max-height: calc(100vh - 72px);
            max-width: calc(100vw - 16px);
            background: var(--panel-bg);
            border-radius: 15px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
            z-index: 2500;
            display: none;
            transform: translate(var(--acct-x), var(--acct-y)) translate(-100%, 0);
        }

        .account-popover.open {
            display: block;
        }

        .account-popover .acct-menu {
            width: 100%;
            height: 100%;
            background: var(--panel-bg);
            color: var(--text);
        }

        .account-popover .acct-menu .fb-menu-container,
        .account-popover .acct-menu .menu-slider {
            width: 100%;
            height: 100%;
        }

        .account-popover .acct-menu .menu-slider {
            overflow: hidden;
        }

        .account-popover .acct-menu .menu-track {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.18s ease;
            will-change: transform;
        }

        .account-popover .acct-menu .menu-panel {
            flex: 0 0 100%;
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            padding: var(--panel-padding);
        }

        .account-popover .acct-menu .menu-slider.slide-active .menu-track {
            transform: translateX(-100%);
        }

        .account-popover .acct-menu .profile-card {
            background: var(--panel-bg);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            padding: 6px 0 8px;
            margin-bottom: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        .account-popover .acct-menu .profile-info {
            position: relative;
            padding: 6px 12px 8px;
        }

        .account-popover .acct-menu .profile-info .profile-info-inner {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 10px;
            cursor: pointer;
            user-select: none;
        }

        .account-popover .acct-menu .profile-info .profile-info-inner:hover {
            background: var(--hover-bg);
        }

        .account-popover .acct-menu .profile-info::after {
            content: "";
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 6px;
            width: calc(100% - 24px);
            height: 2px;
            background: #6a6a6a;
            border-radius: 1px;
            pointer-events: none;
            opacity: 0.22;
        }

        .account-popover .acct-menu .avatar-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #ced0d4;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 6px;
            overflow: hidden;
            flex: 0 0 auto;
        }

        .account-popover .acct-menu .acct-menu-avatar-ring {
            fill: none;
            stroke: rgba(0, 0, 0, .08);
            stroke-width: 1;
        }

        .account-popover .acct-menu .user-name {
            font-size: calc(var(--font-user-name) - 3px);
            font-weight: 600;
            color: var(--text);
            line-height: 1.15;
        }

        .account-popover .acct-menu .see-all-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 5px 16px 8px 16px;
            padding: 10px 12px;
            background: var(--seeall-bg);
            border-radius: 6px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
            font-weight: 600;
            color: var(--seeall-text);
            font-size: calc(var(--font-body) + 3px);
            line-height: 1.2;
            cursor: pointer;
            user-select: none;
        }

        .account-popover .acct-menu .see-all-btn:hover,
        .see-all-btn:hover {
            background: var(--seeall-hover) !important;
            border-radius: 8px !important;
            transition: background 0.2s;
        }

        .account-popover .acct-menu .see-all-btn .see-all-ico {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
            display: inline-block;
            background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yr/r/2qgoWm4SGrp.png");
            background-position: 0px -449px;
            background-size: auto;
            background-repeat: no-repeat;
            filter: var(--sprite-filter);
        }

        .account-popover .acct-menu .menu-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .account-popover .acct-menu .menu-item,
        .account-popover .acct-menu .sub-menu-item {
            display: flex;
            align-items: center;
            padding: 6px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.12s;
            text-decoration: none;
            color: inherit;
            min-height: var(--item-height);
        }

        .account-popover .acct-menu .menu-item:hover,
        .account-popover .acct-menu .sub-menu-item:hover {
            background: var(--hover-bg);
            border-radius: 16px;
        }

        .account-popover .acct-menu .icon-wrapper {
            width: 30px;
            height: 30px;
            background: var(--icon-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 8px 0 4px;
            flex-shrink: 0;
        }

        .account-popover .acct-menu .icon-wrapper svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
            display: block;
        }

        .account-popover .acct-menu .item-label-wrapper {
            display: flex;
            flex-direction: row;
            align-items: center;
            flex: 1;
            min-width: 0;
            gap: 8px;
        }

        .account-popover .acct-menu .item-label {
            font-size: var(--font-item-label);
            font-weight: 600;
            color: var(--text);
            line-height: 1.25;
            padding: 1px 0 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .account-popover .acct-menu .item-ctrl {
            font-size: 12px;
            color: var(--secondary-text);
            letter-spacing: 0.4px;
            font-weight: 500;
            display: none;
        }

        .account-popover .acct-menu .chev-right {
            width: 20px;
            height: 20px;
            fill: var(--secondary-text);
            margin-left: auto;
            flex-shrink: 0;
        }

        .account-popover .acct-menu .menu-item.has-ctrl {
            min-height: calc(var(--item-height) + 10px);
        }

        .account-popover .acct-menu .menu-item.has-ctrl .item-label-wrapper {
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 1px;
        }

        .account-popover .acct-menu .menu-item.has-ctrl .item-ctrl {
            display: block;
            line-height: 1.1;
            font-size: 11px;
        }

        .account-popover .acct-menu .fb-sprite {
            background-image: url("https://static.xx.fbcdn.net/rsrc.php/v4/yk/r/vSXg3cmJhul.png");
            background-repeat: no-repeat;
            background-size: auto;
            width: 20px;
            height: 20px;
            background-position: 0px -285px;
            display: inline-block;
            filter: var(--sprite-filter);
        }

        .account-popover .acct-menu .fb-sprite-privacy-check {
            background-position: 0px -390px;
        }

        .account-popover .acct-menu .footer-links {
            margin-top: 4px;
            padding: 2px 8px 0;
            font-size: 12px;
            color: var(--secondary-text);
            line-height: 1.35;
            white-space: normal;
        }

        .account-popover .acct-menu .footer-links span {
            cursor: pointer;
        }

        .account-popover .acct-menu .footer-links span:hover {
            text-decoration: underline;
        }

        .account-popover .acct-menu .footer-links .dot {
            cursor: default;
            margin: 0 4px;
        }

        .account-popover .acct-menu .footer-links .dot:hover {
            text-decoration: none;
        }

        .account-popover .acct-menu .sec-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            padding: 6px 0 12px;
            position: sticky;
            top: 0;
            background: var(--panel-bg);
            z-index: 3;
        }

        .account-popover .acct-menu .back-btn {
            position: static;
            left: auto;
            top: auto;
            transform: none;
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            background: transparent;
            opacity: 1;
            pointer-events: auto;
            z-index: auto;
        }

        .account-popover .acct-menu .back-btn:hover {
            background: var(--hover-bg);
        }

        .account-popover .acct-menu .back-btn svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .account-popover .acct-menu .sec-title {
            font-size: var(--font-sec-title);
            font-weight: 800;
            letter-spacing: -0.2px;
            color: var(--text);
            line-height: 1.05;
        }

        .account-popover .acct-menu .sec-view {
            display: none;
        }

        .account-popover .acct-menu .sec-view.active {
            display: block;
        }

        .account-popover .acct-menu .sub-menu-list {
            display: flex;
            flex-direction: column;
            padding-top: 4px;
            gap: 4px;
        }

        .account-popover .acct-menu .sub-menu-item .item-label {
            font-size: var(--font-submenu-label);
            font-weight: 700;
            line-height: 1.3;
            padding: 1px 0;
        }

        .account-popover .acct-menu .setting-block {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .account-popover .acct-menu .setting-icon {
            width: 32px;
            height: 32px;
            background: var(--icon-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .account-popover .acct-menu .setting-icon svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
            display: block;
        }

        .account-popover .acct-menu .setting-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .account-popover .acct-menu .setting-name {
            font-size: var(--font-setting-name);
            font-weight: 600;
            color: var(--text);
            margin-bottom: 2px;
            margin-top: 0;
        }

        .account-popover .acct-menu .setting-desc {
            font-size: var(--font-setting-desc);
            color: var(--secondary-text);
            margin-bottom: 8px;
            line-height: 1.25;
            display: -webkit-box;
            line-clamp: 2;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Chỉ riêng view “Màn hình và trợ năng”: hiện đầy đủ mô tả */
        .account-popover .acct-menu .sec-view[data-view="accessibility"] .setting-desc {
            display: block;
            overflow: visible;
            line-clamp: unset;
            -webkit-line-clamp: unset;
            -webkit-box-orient: unset;
        }

        .account-popover .acct-menu .sec-view[data-view="accessibility"] .setting-name {
            font-size: 18px;
        }

        .account-popover .acct-menu .sec-view[data-view="accessibility"] .setting-desc {
            font-size: 18px;
        }

        .account-popover .acct-menu .sec-view[data-view="accessibility"] .radio-label {
            font-size: 18px;
        }

        .account-popover .acct-menu .simple-item {
            display: flex;
            align-items: center;
            padding: 10px 8px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 2px;
        }

        .account-popover .acct-menu .simple-item:hover {
            background-color: var(--hover-bg);
        }

        .account-popover .acct-menu .simple-text {
            flex: 1;
            font-size: var(--font-simple-text);
            font-weight: 600;
            margin-left: 0;
            color: var(--text);
        }

        .account-popover .acct-menu .radio-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 8px;
            border-radius: 8px;
            cursor: pointer;
        }

        .account-popover .acct-menu .radio-row:hover {
            background-color: var(--hover-bg);
        }

        .account-popover .acct-menu .radio-label {
            font-size: var(--font-radio-label);
            font-weight: 600;
            color: var(--text);
        }

        .account-popover .acct-menu .radio-circle {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2px solid var(--secondary-text);
            position: relative;
            background: transparent;
            box-sizing: border-box;
        }

        .account-popover .acct-menu .radio-row.selected .radio-circle {
            border-color: var(--text);
        }

        .account-popover .acct-menu .radio-circle::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--text);
            transform: translate(-50%, -50%) scale(0.6);
            opacity: 0;
            transition: opacity .14s ease, transform .14s ease;
        }

        .account-popover .acct-menu .radio-row.selected .radio-circle::after {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        .account-popover .acct-menu .menu-panel::-webkit-scrollbar {
            width: 10px;
        }

        .account-popover .acct-menu .menu-panel::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.08);
            border-radius: 10px;
        }

        .account-popover .acct-menu .menu-panel::-webkit-scrollbar-track {
            background: transparent;
        }

        .video-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .main-img {
            max-width: 680px;
            max-height: 92vh;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
        }

        /* Các nút tương tác bên phải */
        .actions {
            position: absolute;
            right: 20px;
            bottom: 120px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .btn {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            font-size: 24px;
        }

        .count {
            text-align: center;
            margin-top: 4px;
            font-size: 14px;
            opacity: 0.9;
        }

        /* Thông tin người đăng */
        .info {
            position: absolute;
            bottom: 100px;
            left: 20px;
            max-width: 560px;
        }

        .user {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #fff;
            background-image: url('https://i.imgur.com/3f3pK8Z.jpeg');
            background-size: cover;
        }

        .name {
            font-weight: 600;
            font-size: 17px;
        }

        .follow {
            color: #b0b3b8;
            font-size: 14px;
        }

        .desc {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .main-video {
    max-width: 680px;
    max-height: 92vh;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.8);
    object-fit: cover;
}

        .video-player-wrap {
            position: relative;
            display: inline-block;
        }

        .play-overlay {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 88px;
            height: 88px;
            border-radius: 999px;
            background: rgba(0,0,0,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 12;
            transition: transform .12s ease, opacity .12s ease;
        }

        .play-overlay:active { transform: translate(-50%, -50%) scale(0.98); }
        .play-overlay svg { width: 36px; height: 36px; fill: #fff; }
        .play-overlay.hidden { opacity: 0; pointer-events: none; }

        .more {
            color: #b0b3b8;
            font-size: 14px;
        }

    </style>
</head>

<body>

    <header class="header">
        <div class="header-left" id="headerBox">
            <div class="logo-area">
                <div class="fb-logo" title="Facebook" id="fbLogo" tabindex="0" role="button" aria-label="Facebook">
                    <svg viewBox="0 0 36 36" width="40" height="40" aria-hidden="true" focusable="false">
                        <path d="M20.181 35.87C29.094 34.791 36 27.202 36 18c0-9.941-8.059-18-18-18S0 8.059 0 18c0 8.442 5.811 15.526 13.652 17.471L14 34h5.5l.681 1.87Z" fill="#0866FF"></path>
                        <path d="M13.651 35.471v-11.97H9.936V18h3.715v-2.37c0-6.127 2.772-8.964 8.784-8.964 1.138 0 3.103.223 3.91.446v4.983c-.425-.043-1.167-.065-2.081-.065-2.952 0-4.09 1.116-4.09 4.025V18h5.883l-1.008 5.5h-4.867v12.37a18.183 18.183 0 0 1-6.53-.399Z" fill="#fff"></path>
                    </svg>
                </div>
            </div>

            <div class="search-wrapper" id="searchWrapper">
                <div class="search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false" preserveAspectRatio="xMidYMid meet">
                        <path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zM9.5 14A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z" />
                    </svg>
                </div>

                <span class="fake-placeholder" id="fakePlaceholder">Tìm kiếm trên Facebook</span>
                <button class="back-btn" id="backBtn" aria-label="Quay lại" title="Quay lại" tabindex="0">
                    <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                    </svg>
                </button>
                <input type="text" class="search-input" id="searchInput" placeholder="Tìm kiếm trên Facebook" autocomplete="off">

                <div id="searchResult">
                    <div class="empty">Không có tìm kiếm nào gần đây</div>
                </div>
            </div>
        </div>

        <div class="center-nav-wrap" aria-hidden="false">
            <nav class="center-nav" role="navigation" aria-label="Main">
                <a class="cnav-item" href="../pages/home.php" title="Trang chủ" data-key="home">
                    <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M9.464 1.286C10.294.803 11.092.5 12 .5c.908 0 1.707.303 2.537.786.795.462 1.7 1.142 2.815 1.977l2.232 1.675c1.391 1.042 2.359 1.766 2.888 2.826.53 1.059.53 2.268.528 4.006v4.3c0 1.355 0 2.471-.119 3.355-.124.928-.396 1.747-1.052 2.403-.657.657-1.476.928-2.404 1.053-.884.119-2 .119-3.354.119H7.93c-1.354 0-2.471 0-3.355-.119-.928-.125-1.747-.396-2.403-1.053-.656-.656-.928-1.475-1.053-2.403C1 18.541 1 17.425 1 16.07v-4.3c0-1.738-.002-2.947.528-4.006.53-1.06 1.497-1.784 2.888-2.826L6.65 3.263c1.114-.835 2.02-1.515 2.815-1.977zM10.5 13A1.5 1.5 0 0 0 9 14.5V21h6v-6.5a1.5 1.5 0 0 0-1.5-1.5h-3z" />
                        </svg></div>
                    <div class="underline"></div>
                </a>

                <a class="cnav-item" href="../pages/friends.php" title="Bạn bè" data-key="friends">
                    <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M12.496 5a4 4 0 1 1 8 0 4 4 0 0 1-8 0zm4-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-9 2.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm-2 4a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM5.5 15a5 5 0 0 0-5 5 3 3 0 0 0 3 3h8.006a3 3 0 0 0 3-3 5 5 0 0 0-5-5H5.5zm-3 5a3 3 0 0 1 3-3h4.006a3 3 0 0 1 3 3 1 1 0 0 1-1 1H3.5a1 1 0 0 1-1-1zm12-9.5a5.04 5.04 0 0 0-.37.014 1 1 0 0 0 .146 1.994c.074-.005.149-.008.224-.008h4.006a3 3 0 0 1 3 3 1 1 0 0 1-1 1h-3.398a1 1 0 1 0 0 2h3.398a3 3 0 0 0 3-3 5 5 0 0 0-5-5H14.5z" />
                        </svg></div>
                    <div class="underline"></div>
                </a>

                <a class="cnav-item active" href="../pages/watch.php" title="Watch" data-key="watch" aria-current="page">
                    <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24">
                            <path d="M10.996 12.132A1 1 0 0 0 9.5 13v4a1 1 0 0 0 1.496.868l3.5-2a1 1 0 0 0 0-1.736l-3.5-2z"></path>
                            <path d="M12.075 1h-.15C9.632 1 7.81 1 6.38 1.192c-1.472.198-2.674.616-3.623 1.565-.949.95-1.367 2.15-1.565 3.623C1 7.81 1 9.632 1 11.925v.15c0 2.293 0 4.116.192 5.545.198 1.472.616 2.674 1.565 3.623.95.949 2.15 1.367 3.623 1.565C7.81 23 9.632 23 11.925 23h.15c2.293 0 4.116 0 5.545-.192 1.472-.198 2.674-.616 3.623-1.565.949-.95 1.367-2.15 1.565-3.623.192-1.43.192-3.252.192-5.545v-.15c0-2.293 0-4.116-.192-5.545-.198-1.472-.616-2.674-1.565-3.623-.95-.949-2.15-1.367-3.623-1.565C16.19 1 14.368 1 12.075 1zM4.172 4.172c.515-.516 1.224-.83 2.475-.998l.183-.023L8.113 7H3.132c.013-.121.027-.239.042-.353.168-1.25.482-1.96.998-2.475zM10.22 7 8.895 3.023C9.778 3 10.801 3 12 3c.642 0 1.234 0 1.78.004L15.114 7H10.22zm6.253 2h4.507c.02.86.02 1.848.02 3 0 2.385-.002 4.074-.174 5.353-.168 1.25-.482 1.96-.998 2.475-.515.516-1.224.83-2.475.998-1.28.172-2.968.174-5.353.174s-4.074-.002-5.353-.174c-1.25-.168-1.96-.482-2.475-.998-.516-.515-.83-1.224-.998-2.475C3.002 16.073 3 14.385 3 12c0-1.152 0-2.14.02-3h13.454zm.747-2-1.316-3.949c.537.026 1.016.065 1.448.123 1.25.168 1.96.482 2.475.998.516.515.83 1.224.998 2.475.015.114.03.232.042.353H17.22z" />
                        </svg></div>
                    <div class="underline"></div>
                </a>

                <a class="cnav-item" href="../pages/marketplace.php" title="Marketplace" data-key="market">
                    <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path d="M1.588 3.227A3.125 3.125 0 0 1 4.58 1h14.84c1.38 0 2.597.905 2.993 2.227l.816 2.719a6.47 6.47 0 0 1 .272 1.854A5.183 5.183 0 0 1 22 11.455v4.615c0 1.355 0 2.471-.119 3.355-.125.928-.396 1.747-1.053 2.403-.656.657-1.475.928-2.403 1.053-.884.12-2 .119-3.354.119H8.929c-1.354 0-2.47 0-3.354-.119-.928-.125-1.747-.396-2.403-1.053-.657-.656-.929-1.475-1.053-2.403-.12-.884-.119-2-.119-3.354V11.5l.001-.045A5.184 5.184 0 0 1 .5 7.8c0-.628.092-1.252.272-1.854l.816-2.719zM10 21h4v-3.5a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5V21zm6-.002c.918-.005 1.608-.025 2.159-.099.706-.095 1.033-.262 1.255-.485.223-.222.39-.55.485-1.255.099-.735.101-1.716.101-3.159v-3.284a5.195 5.195 0 0 1-1.7.284 5.18 5.18 0 0 1-3.15-1.062A5.18 5.18 0 0 1 12 13a5.18 5.18 0 0 1-3.15-1.062A5.18 5.18 0 0 1 5.7 13a5.2 5.2 0 0 1-1.7-.284V16c0 1.442.002 2.424.1 3.159.096.706.263 1.033.486 1.255.222.223.55.39 1.255.485.551.074 1.24.094 2.159.1V17.5a2.5 2.5 0 0 1 2.5-2.5h3a2.5 2.5 0 0 1 2.5 2.5v3.498zM4.581 3c-.497 0-.935.326-1.078.802l-.815 2.72A4.45 4.45 0 0 0 2.5 7.8a3.2 3.2 0 0 0 5.6 2.117 1 1 0 0 1 1.5 0A3.19 3.19 0 0 0 12 11a3.19 3.19 0 0 0 2.4-1.083 1 1 0 0 1 1.5 0A3.2 3.2 0 0 0 21.5 7.8c0-.434-.063-.865-.188-1.28l-.816-2.72A1.125 1.125 0 0 0 19.42 3H4.58z" />
                        </svg></div>
                    <div class="underline"></div>
                </a>

                <a class="cnav-item" href="../pages/group.php" title="Nhóm" data-key="groups">
                    <div class="icon-bg"><svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path d="M.5 12c0 6.351 5.149 11.5 11.5 11.5S23.5 18.351 23.5 12 18.351.5 12 .5.5 5.649.5 12zm2 0c0-.682.072-1.348.209-1.99a2 2 0 0 1 0 3.98A9.539 9.539 0 0 1 2.5 12zm.84-3.912A9.502 9.502 0 0 1 12 2.5a9.502 9.502 0 0 1 8.66 5.588 4.001 4.001 0 0 0 0 7.824 9.514 9.514 0 0 1-1.755 2.613A5.002 5.002 0 0 0 14 14.5h-4a5.002 5.002 0 0 0-4.905 4.025 9.515 9.515 0 0 1-1.755-2.613 4.001 4.001 0 0 0 0-7.824zM12 5a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm-2 4a2 2 0 1 0 4 0 2 2 0 0 0-4 0zm11.291 1.01a9.538 9.538 0 0 1 0 3.98 2 2 0 0 1 0-3.98zM16.99 20.087A9.455 9.455 0 0 1 12 21.5c-1.83 0-3.54-.517-4.99-1.414a1.004 1.004 0 0 1-.01-.148V19.5a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v.438a1 1 0 0 1-.01.148z"></path>
                        </svg></div>
                    <div class="underline"></div>
                </a>
            </nav>
        </div>

        <div class="header-right">
            <button class="icon-btn" type="button" aria-label="Menu">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M18.5 1A1.5 1.5 0 0 0 17 2.5v3A1.5 1.5 0 0 0 18.5 7h3A1.5 1.5 0 0 0 23 5.5v-3A1.5 1.5 0 0 0 21.5 1h-3zm0 8a1.5 1.5 0 0 0-1.5 1.5v3a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5v-3A1.5 1.5 0 0 0 21.5 9h-3zm-16 8A1.5 1.5 0 0 0 1 18.5v3A1.5 1.5 0 0 0 2.5 23h3A1.5 1.5 0 0 0 7 21.5v-3A1.5 1.5 0 0 0 5.5 17h-3zm8 0A1.5 1.5 0 0 0 9 18.5v3a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5v-3a1.5 1.5 0 0 0-1.5-1.5h-3zm8 0a1.5 1.5 0 0 0-1.5 1.5v3a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5v-3a1.5 1.5 0 0 0-1.5-1.5h-3zm-16-8A1.5 1.5 0 0 0 1 10.5v3A1.5 1.5 0 0 0 2.5 15h3A1.5 1.5 0 0 0 7 13.5v-3A1.5 1.5 0 0 0 5.5 9h-3zm0-8A1.5 1.5 0 0 0 1 2.5v3A1.5 1.5 0 0 0 2.5 7h3A1.5 1.5 0 0 0 7 5.5v-3A1.5 1.5 0 0 0 5.5 1h-3zm8 0A1.5 1.5 0 0 0 9 2.5v3A1.5 1.5 0 0 0 10.5 7h3A1.5 1.5 0 0 0 15 5.5v-3A1.5 1.5 0 0 0 13.5 1h-3zm0 8A1.5 1.5 0 0 0 9 10.5v3a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5v-3A1.5 1.5 0 0 0 13.5 9h-3z"></path>
                </svg>
            </button>

            <button class="icon-btn" id="messengerBtn" type="button" aria-label="Messenger" title="Messenger" aria-expanded="false" aria-controls="messengerPopover">
                <svg viewBox="0 0 12 12" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false">
                    <g stroke="none" stroke-width="1" fill-rule="evenodd">
                        <path d="m106.868 921.248-1.892 2.925a.32.32 0 0 1-.443.094l-1.753-1.134a.2.2 0 0 0-.222.003l-1.976 1.363c-.288.199-.64-.143-.45-.437l1.892-2.925a.32.32 0 0 1 .443-.095l1.753 1.134a.2.2 0 0 0 .222-.003l1.976-1.363c.288-.198.64.144.45.438m-3.368-4.251c-3.323 0-5.83 2.432-5.83 5.658 0 1.642.652 3.128 1.834 4.186a.331.331 0 0 1 .111.234l.03 1.01a.583.583 0 0 0 .82.519l1.13-.5a.32.32 0 0 1 .22-.015c.541.148 1.108.223 1.685.223 3.323 0 5.83-2.432 5.83-5.657 0-3.226-2.507-5.658-5.83-5.658" transform="translate(-450 -1073.5) translate(352.5 156.845)"></path>
                    </g>
                </svg>
            </button>

            <button class="icon-btn" type="button" aria-label="Thông báo">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M3 9.5a9 9 0 1 1 18 0v2.927c0 1.69.475 3.345 1.37 4.778a1.5 1.5 0 0 1-1.272 2.295h-4.625a4.5 4.5 0 0 1-8.946 0H2.902a1.5 1.5 0 0 1-1.272-2.295A9.01 9.01 0 0 0 3 12.43V9.5z"></path>
                </svg>
            </button>

            <button class="icon-btn account-btn" id="accountBtn" type="button" aria-label="Tài khoản" title="Tài khoản" aria-expanded="false" aria-controls="accountPopover">
                <span class="account-avatar" aria-hidden="true">
                    <svg aria-label="<?= $currentUserNameSafe ?>" role="img" viewBox="0 0 40 40" width="40" height="40" focusable="false">
                        <mask id="watchAcctMask">
                            <circle cx="20" cy="20" r="20" fill="#fff"></circle>
                            <circle cx="34" cy="34" r="8" fill="#000"></circle>
                        </mask>
                        <g mask="url(#watchAcctMask)">
                            <image x="0" y="0" width="100%" height="100%" preserveAspectRatio="xMidYMid slice" href="<?= fb_escape($currentUserAvatar) ?>" xlink:href="<?= fb_escape($currentUserAvatar) ?>"></image>
                            <circle class="account-avatar-ring" cx="20" cy="20" r="20"></circle>
                        </g>
                    </svg>

                    <span class="account-caret" aria-hidden="true">
                        <span class="account-caret-bg" aria-hidden="true">
                            <svg viewBox="0 0 16 16" width="12" height="12" fill="currentColor" aria-hidden="true" focusable="false">
                                <path fill-rule="nonzero" d="M4.707 6.293a1 1 0 0 0-1.414 1.414l4 4a1 1 0 0 0 1.414 0l4-4a1 1 0 0 0-1.414-1.414L8 9.586 4.707 6.293z"></path>
                            </svg>
                        </span>
                    </span>
                </span>
            </button>
        </div>
    </header>

    <!-- Account popover (Home) -->
    <section class="account-popover" id="accountPopover" role="dialog" aria-label="Tài khoản" aria-hidden="true">
        <div class="acct-menu" id="acctMenuRoot">
            <div class="fb-menu-container">
                <div class="menu-slider" id="acctMenuSlider">
                    <div class="menu-track">

                        <!-- PANEL 1: MAIN MENU -->
                        <div class="menu-panel" aria-hidden="false">
                            <div class="profile-card">
                                <div class="profile-info">
                                    <div class="profile-info-inner" role="button" tabindex="0" aria-label="Trang cá nhân của bạn">
                                        <div class="avatar-circle" aria-hidden="true">
                                            <svg aria-hidden="true" role="none" viewBox="0 0 36 36" focusable="false">
                                                <mask id="acctMenuAvatarMask">
                                                    <circle cx="18" cy="18" r="18" fill="#fff"></circle>
                                                </mask>
                                                <g mask="url(#acctMenuAvatarMask)">
                                                    <image x="0" y="0" width="100%" height="100%" preserveAspectRatio="xMidYMid slice" href="<?= fb_escape($currentUserAvatar) ?>" xlink:href="<?= fb_escape($currentUserAvatar) ?>"></image>
                                                    <circle class="acct-menu-avatar-ring" cx="18" cy="18" r="18"></circle>
                                                </g>
                                            </svg>
                                        </div>
                                        <div class="user-name"><?= $currentUserNameSafe ?></div>
                                    </div>
                                </div>

                                <div class="see-all-btn" role="button" tabindex="0" aria-label="Xem tất cả trang cá nhân">
                                    <span class="see-all-ico" aria-hidden="true"></span>
                                    <span class="see-all-text">Xem tất cả trang cá nhân</span>
                                </div>
                            </div>

                            <div class="menu-list">
                                <a href="#" class="menu-item" id="acctBtnSettingsPrivacy">
                                    <div class="icon-wrapper" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                                            <path d="M7.5 10a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0z"></path>
                                            <path d="M17.773 8.983a1.748 1.748 0 0 0 0 2.034v-.001l.949 1.328c.302.423.31.988.023 1.42l-1.387 2.081a1.25 1.25 0 0 1-1.195.547l-1.235-.154a1.75 1.75 0 0 0-1.856 1.122l-.498 1.328a1.25 1.25 0 0 1-1.17.811H8.597a1.25 1.25 0 0 1-1.17-.811l-.476-1.269a1.75 1.75 0 0 0-1.91-1.115l-1.165.182a1.248 1.248 0 0 1-1.246-.561L1.238 13.75a1.25 1.25 0 0 1 .036-1.4l.934-1.307a1.75 1.75 0 0 0-.018-2.059l-.904-1.22a1.249 1.249 0 0 1-.06-1.399l1.398-2.272a1.25 1.25 0 0 1 1.258-.58l1.16.181A1.75 1.75 0 0 0 6.95 2.579l.476-1.269a1.25 1.25 0 0 1 1.17-.811h2.807c.52 0 .987.323 1.17.811l.498 1.328a1.752 1.752 0 0 0 1.856 1.122l1.235-.154a1.25 1.25 0 0 1 1.195.547l1.387 2.081a1.25 1.25 0 0 1-.023 1.42l-.95 1.329zM10 6a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path>
                                        </svg>
                                    </div>
                                    <div class="item-label-wrapper">
                                        <div class="item-label">Cài đặt và quyền riêng tư</div>
                                    </div>
                                    <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                                    </svg>
                                </a>

                                <a href="#" class="menu-item" id="acctBtnHelpSupport">
                                    <div class="icon-wrapper" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z" />
                                        </svg>
                                    </div>
                                    <div class="item-label-wrapper">
                                        <div class="item-label">Trợ giúp và hỗ trợ</div>
                                    </div>
                                    <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                                    </svg>
                                </a>

                                <a href="#" class="menu-item" id="acctBtnDisplayAccessibility">
                                    <div class="icon-wrapper" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z" />
                                        </svg>
                                    </div>
                                    <div class="item-label-wrapper">
                                        <div class="item-label">Màn hình và trợ năng</div>
                                    </div>
                                    <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                                    </svg>
                                </a>

                                <a href="#" class="menu-item has-ctrl">
                                    <div class="icon-wrapper" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z" />
                                        </svg>
                                    </div>
                                    <div class="item-label-wrapper">
                                        <div class="item-label">Đóng góp ý kiến</div>
                                        <div class="item-ctrl">CTRL B</div>
                                    </div>
                                </a>

                                <a href="../auth/logout.php" class="menu-item" id="acctBtnLogout">
                                    <div class="icon-wrapper" aria-hidden="true">
                                        <i class="fb-sprite" aria-hidden="true" style="display:inline-block;"></i>
                                    </div>
                                    <div class="item-label-wrapper">
                                        <div class="item-label">Đăng xuất</div>
                                    </div>
                                </a>
                            </div>

                            <div class="footer-links">
                                <span>Quyền riêng tư</span> <span class="dot">·</span>
                                <span>Điều khoản</span> <span class="dot">·</span>
                                <span>Quảng cáo</span> <span class="dot">·</span>
                                <span>Lựa chọn quảng cáo</span> <span class="dot">·</span>
                                <span>Cookie</span> <span class="dot">·</span>
                                <span class="footer-more">Xem thêm</span>
                            </div>
                        </div>

                        <!-- PANEL 2: SECONDARY -->
                        <div class="menu-panel" aria-hidden="true">
                            <div class="sec-header">
                                <button class="back-btn" id="acctBtnBack" type="button" title="Quay lại" aria-label="Quay lại">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
                                    </svg>
                                </button>
                                <div class="sec-title" id="acctSecTitle">Màn hình và trợ năng</div>
                            </div>

                            <div id="acctSecViews">
                                <div class="sec-view" data-view="settings">
                                    <div class="sub-menu-list">
                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true">
                                                <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                                                    <path d="M7.5 10a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0z"></path>
                                                    <path d="M17.773 8.983a1.748 1.748 0 0 0 0 2.034v-.001l.949 1.328c.302.423.31.988.023 1.42l-1.387 2.081a1.25 1.25 0 0 1-1.195.547l-1.235-.154a1.75 1.75 0 0 0-1.856 1.122l-.498 1.328a1.25 1.25 0 0 1-1.17.811H8.597a1.25 1.25 0 0 1-1.17-.811l-.476-1.269a1.75 1.75 0 0 0-1.91-1.115l-1.165.182a1.248 1.248 0 0 1-1.246-.561L1.238 13.75a1.25 1.25 0 0 1 .036-1.4l.934-1.307a1.75 1.75 0 0 0-.018-2.059l-.904-1.22a1.249 1.249 0 0 1-.06-1.399l1.398-2.272a1.25 1.25 0 0 1 1.258-.58l1.16.181A1.75 1.75 0 0 0 6.95 2.579l.476-1.269a1.25 1.25 0 0 1 1.17-.811h2.807c.52 0 .987.323 1.17.811l.498 1.328a1.752 1.752 0 0 0 1.856 1.122l1.235-.154a1.25 1.25 0 0 1 1.195.547l1.387 2.081a1.25 1.25 0 0 1-.023 1.42l-.95 1.329zM10 6a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"></path>
                                                </svg>
                                            </div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Cài đặt</div>
                                            </div>
                                        </a>

                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                                                    <path d="M12 .5C5.649.5.5 5.649.5 12S5.649 23.5 12 23.5 23.5 18.351 23.5 12 18.351.5 12 .5zM2.983 9H7.16c-.105.958-.16 1.965-.16 3s.055 2.042.16 3H2.983a9.49 9.49 0 0 1-.483-3c0-1.048.17-2.057.483-3zm.938-2a9.53 9.53 0 0 1 4.826-3.928c-.186.358-.356.743-.51 1.147A17.163 17.163 0 0 0 7.462 7H3.92zm5.251 2h5.656c.111.942.172 1.949.172 3s-.06 2.058-.172 3H9.172A25.628 25.628 0 0 1 9 12c0-1.051.06-2.058.172-3zm5.324-2H9.504c.167-.766.37-1.461.602-2.069.337-.883.713-1.53 1.08-1.937.367-.407.644-.494.814-.494.17 0 .447.087.814.494.367.408.743 1.054 1.08 1.937.231.608.435 1.303.602 2.069zm2.344 2h4.177a9.49 9.49 0 0 1 .483 3 9.49 9.49 0 0 1-.483 3H16.84c.105-.958.16-1.965.16-3s-.055-2.042-.16-3zm3.24-2h-3.542a17.154 17.154 0 0 0-.775-2.78 11.02 11.02 0 0 0-.51-1.148A9.53 9.53 0 0 1 20.08 7zM8.746 20.928A9.53 9.53 0 0 1 3.92 17h3.54a17.15 17.15 0 0 0 .776 2.78c.154.405.324.79.51 1.148zm1.36-1.86A14.592 14.592 0 0 1 9.503 17h4.992c-.167.766-.37 1.461-.602 2.069-.337.883-.713 1.53-1.08 1.937-.367.407-.644.494-.814.494-.17 0-.447-.087-.814-.494-.367-.408-.743-1.054-1.08-1.937zm5.656.713c.313-.822.575-1.76.775-2.781h3.541a9.53 9.53 0 0 1-4.826 3.928c.186-.358.356-.743.51-1.147z"></path>
                                                </svg>
                                            </div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Ngôn ngữ</div>
                                            </div>
                                            <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                                            </svg>
                                        </a>

                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true"><i class="fb-sprite fb-sprite-privacy-check" aria-hidden="true"></i></div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Kiểm tra quyền riêng tư</div>
                                            </div>
                                        </a>

                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true"><svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                                                    <path d="M10 .375a4 4 0 0 0-4 4v3.04a8.83 8.83 0 0 0-.593.058c-.764.103-1.426.325-1.955.854-.529.529-.751 1.19-.854 1.955-.098.73-.098 1.656-.098 2.79v.107c0 1.133 0 2.058.098 2.79.103.763.325 1.425.854 1.954.529.529 1.19.751 1.955.854.73.098 1.656.098 2.79.098h3.607c1.133 0 2.058 0 2.79-.098.763-.103 1.425-.325 1.954-.854.529-.529.751-1.19.854-1.955.098-.73.098-1.656.098-2.79v-.107c0-1.133 0-2.058-.098-2.79-.103-.763-.325-1.425-.854-1.954-.529-.529-1.19-.751-1.955-.854A8.83 8.83 0 0 0 14 7.416V4.375a4 4 0 0 0-4-4zm-2.5 4a2.5 2.5 0 0 1 5 0v3h-5v-3z"></path>
                                                </svg></div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Trung tâm quyền riêng tư</div>
                                            </div>
                                        </a>

                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true"><svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                                                    <path d="M3.5 5.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM7.75 3a1 1 0 0 0 0 2h9.5a1 1 0 1 0 0-2h-9.5zM5 10a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm2.75-1a1 1 0 0 0 0 2h9.5a1 1 0 1 0 0-2h-9.5zM5 16a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm2.75-1a1 1 0 1 0 0 2h9.5a1 1 0 1 0 0-2h-9.5z"></path>
                                                </svg></div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Nhật ký hoạt động</div>
                                            </div>
                                        </a>

                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true"><svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                                                    <path d="M3.854 7.25H2.75a.75.75 0 0 1 0-1.5h1.104a2.751 2.751 0 1 1 0 1.5zm9.646 3.5a2.75 2.75 0 0 1 2.646 2h1.104a.75.75 0 0 1 0 1.5h-1.104a2.751 2.751 0 1 1-2.646-3.5zM9.25 13.5a.75.75 0 0 0-.75-.75H2.75a.75.75 0 0 0 0 1.5H8.5a.75.75 0 0 0 .75-.75zm2.25-7.75a.75.75 0 0 0 0 1.5h5.75a.75.75 0 0 0 0-1.5H11.5z"></path>
                                                </svg></div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Tùy chọn nội dung</div>
                                            </div>
                                        </a>
                                    </div>
                                </div>

                                <div class="sec-view" data-view="help">
                                    <div class="sub-menu-list">
                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M12 2a10 10 0 1 0 10 10A10.01 10.01 0 0 0 12 2zm1 16h-2v-2h2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z" />
                                                </svg></div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Trung tâm trợ giúp</div>
                                            </div>
                                        </a>

                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true"><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true">
                                                    <path d="M6.25 1a3.25 3.25 0 1 0 0 6.5 3.25 3.25 0 0 0 0-6.5zM4.583 8.5A4.083 4.083 0 0 0 .5 12.583 2.417 2.417 0 0 0 2.917 15H6.25a.75.75 0 0 0 .75-.75v-5a.75.75 0 0 0-.75-.75H4.583zm11.947 4.53a.75.75 0 1 0-1.06-1.06L13 14.44l-.97-.97a.75.75 0 1 0-1.06 1.06l1.028 1.029c.554.553 1.45.553 2.004 0l2.528-2.529z" />
                                                    <path d="M8.5 10.75a2.25 2.25 0 0 1 2.25-2.25h6A2.25 2.25 0 0 1 19 10.75v6A2.25 2.25 0 0 1 16.75 19h-6a2.25 2.25 0 0 1-2.25-2.25v-6zm2.25-.75a.75.75 0 0 0-.75.75v6c0 .414.336.75.75.75h6a.75.75 0 0 0 .75-.75v-6a.75.75 0 0 0-.75-.75h-6z" />
                                                </svg></div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Trạng thái tài khoản</div>
                                            </div>
                                        </a>

                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 4-8 5L4 8V6l8 5 8-5v2z" />
                                                </svg></div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Hộp thư hỗ trợ</div>
                                            </div>
                                        </a>

                                        <a href="#" class="sub-menu-item" aria-disabled="true">
                                            <div class="icon-wrapper" aria-hidden="true"><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true">
                                                    <path d="M12.805 2.5h-5.61c-1.367 0-2.47 0-3.337.117-.9.12-1.658.38-2.26.981-.602.602-.86 1.36-.981 2.26C.5 6.725.5 7.828.5 9.195v1.61c0 1.367 0 2.47.117 3.337.12.9.38 1.658.981 2.26.602.602 1.36.86 2.26.982.867.116 1.97.116 3.337.116h5.61c1.367 0 2.47 0 3.337-.116.9-.122 1.658-.38 2.26-.982.602-.602.86-1.36.982-2.26.116-.867.116-1.97.116-3.337v-1.61c0-1.367 0-2.47-.116-3.337-.122-.9-.38-1.658-.982-2.26-.602-.602-1.36-.86-2.26-.981-.867-.117-1.97-.117-3.337-.117zM10 5.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5.5zm0 9a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
                                                </svg></div>
                                            <div class="item-label-wrapper">
                                                <div class="item-label">Báo cáo sự cố</div>
                                            </div>
                                        </a>
                                    </div>
                                </div>

                                <div class="sec-view" data-view="accessibility">
                                    <div class="setting-block">
                                        <div class="setting-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9c0-.46-.04-.92-.1-1.36a5.389 5.389 0 0 1-4.4 2.26 5.403 5.403 0 0 1-3.14-9.8c-.44-.06-.9-.1-1.36-.1z" />
                                            </svg></div>
                                        <div class="setting-content">
                                            <div class="setting-name">Chế độ tối</div>
                                            <div class="setting-desc">Điều chỉnh giao diện của Facebook để giảm độ chói và cho đôi mắt được nghỉ ngơi.</div>
                                            <div class="radio-row" data-group="darkmode" data-value="off"><span class="radio-label">Tắt</span>
                                                <div class="radio-circle"></div>
                                            </div>
                                            <div class="radio-row" data-group="darkmode" data-value="on"><span class="radio-label">Bật</span>
                                                <div class="radio-circle"></div>
                                            </div>
                                            <div class="radio-row" data-group="darkmode" data-value="auto"><span class="radio-label">Tự động</span>
                                                <div class="radio-circle"></div>
                                            </div>
                                            <div class="setting-desc" style="font-size:12px; margin-top:8px;">Chúng tôi sẽ tự động điều chỉnh màn hình theo cài đặt hệ thống trên thiết bị của bạn (khi chọn Tự động).</div>
                                        </div>
                                    </div>

                                    <div class="setting-block">
                                        <div class="setting-icon"><svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                                                <path d="m10.002 8.61.532 1.884H9.468l.534-1.884z"></path>
                                                <path d="M18.25 7.5a.75.75 0 0 1-.75-.75V3.56l-2.404 2.405a6.5 6.5 0 0 1-9.131 9.131L3.56 17.5h2.69a.75.75 0 0 1 0 1.5h-3.5A1.75 1.75 0 0 1 1 17.25v-3.5a.75.75 0 0 1 1.5 0v2.69l2.404-2.405a6.5 6.5 0 0 1 9.131-9.131L16.44 2.5h-3.19a.75.75 0 0 1 0-1.5h4c.966 0 1.75.784 1.75 1.75v4a.75.75 0 0 1-.75.75zm-9.042-.67-1.56 5.5a.625.625 0 0 0 1.203.34l.263-.926h1.773l.262.926a.625.625 0 1 0 1.203-.34l-1.553-5.5a.625.625 0 0 0-.602-.455H9.81a.625.625 0 0 0-.601.455z"></path>
                                            </svg></div>
                                        <div class="setting-content">
                                            <div class="setting-name">Chế độ Thu gọn</div>
                                            <div class="setting-desc">Giảm kích thước phông chữ để có thêm nội dung vừa với màn hình.</div>
                                            <div class="radio-row" data-group="compact" data-value="off"><span class="radio-label">Tắt</span>
                                                <div class="radio-circle"></div>
                                            </div>
                                            <div class="radio-row" data-group="compact" data-value="on"><span class="radio-label">Bật</span>
                                                <div class="radio-circle"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="simple-item">
                                        <div class="setting-icon"><svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M20 5H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-9 3h2v2h-2V8zm0 3h2v2h-2v-2zM8 8h2v2H8V8zm0 3h2v2H8v-2zm-1 2H5v-2h2v2zm0-3H5V8h2v2zm9 7H8v-2h8v2zm0-4h-2v-2h2v2zm0-3h-2V8h2v2zm3 3h-2v-2h2v2zm0-3h-2V8h2v2z" />
                                            </svg></div>
                                        <div class="simple-text">Bàn phím</div>
                                        <svg class="chev-right" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z" />
                                        </svg>
                                    </div>

                                    <div class="simple-item">
                                        <div class="setting-icon"><svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true">
                                                <path d="M10 .5a9.5 9.5 0 1 0 0 19 9.5 9.5 0 0 0 0-19zM10 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zM5.98 7.286l.005.002.017.005a8.811 8.811 0 0 0 .345.103c.238.067.575.157.97.248.8.183 1.8.356 2.683.356.883 0 1.882-.173 2.682-.356a19.414 19.414 0 0 0 1.316-.351l.017-.005.004-.002a.75.75 0 0 1 .462 1.428h-.003l-.007.003-.022.007a9.877 9.877 0 0 1-.386.115c-.258.073-.621.17-1.047.267-.403.092-.872.187-1.366.26v1.013l1.322 4.667a.75.75 0 0 1-1.424.469L10 11.415l-1.548 4.1a.75.75 0 0 1-1.424-.47L8.35 10.38V9.366a18.111 18.111 0 0 1-1.366-.26 20.89 20.89 0 0 1-1.433-.382l-.022-.007-.007-.002-.003-.001a.75.75 0 0 1 .462-1.428z" />
                                            </svg></div>
                                        <div class="simple-text">Cài đặt trợ năng</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <div class="video-container">
      
        <?php if ($videoFileUrl !== ''): ?>
        <div class="video-player-wrap">
            <video
                class="main-video"
                src="<?= fb_escape($videoFileUrl) ?>"
                autoplay
                muted
                loop
                playsinline></video>
            <button type="button" class="play-overlay" aria-label="Play/Pause">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M8 5v14l11-7z"></path>
                </svg>
            </button>
        </div>
        <?php else: ?>
        <div class="main-img" style="display:flex;align-items:center;justify-content:center;color:#b0b3b8;">
            Không tìm thấy video để phát.
        </div>
        <?php endif; ?>


        <!-- Thông tin người đăng -->
        <div class="info">
            <div class="user">
                <div class="avatar"></div>
                <div>
                    <div class="name">Minh Đồng</div>
                    <div class="follow">Theo dõi</div>
                </div>
            </div>
            <div class="desc">
                Làm thế nào để xác định vị trí của một người đang tấn công bạn hoặc thông tin bạn bằng cách gọi cho anh ấy trên WhatsApp 😈 Nếu bạn muốn học nhiều kỹ thuật khác...<br>
                <span class="more">Xem thêm</span>
            </div>
        </div>

    </div>

    <?php include __DIR__ . '/../includes/messenger_popover.php'; ?>

    <script>
        (function() {
            const logo = document.getElementById('fbLogo');
            if (!logo) return;
            logo.addEventListener('click', () => {
                window.location.href = '../pages/home.php';
            });
            logo.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = '../pages/home.php';
                }
            });
        })();
    </script>

    <script>
        // Play/pause overlay wiring for the watch video
        (function() {
            try {
                const wrap = document.querySelector('.video-player-wrap');
                if (!wrap) return;
                const vid = wrap.querySelector('video');
                const btn = wrap.querySelector('.play-overlay');
                if (!vid || !btn) return;

                function updateOverlay() {
                    if (vid.paused || vid.readyState === 0) {
                        btn.classList.remove('hidden');
                        btn.setAttribute('aria-pressed', 'false');
                    } else {
                        btn.classList.add('hidden');
                        btn.setAttribute('aria-pressed', 'true');
                    }
                }

                // Toggle play/pause
                function toggle() {
                    try {
                        if (vid.paused) vid.play();
                        else vid.pause();
                    } catch (err) {}
                }

                btn.addEventListener('click', (e) => { e.stopPropagation(); toggle(); updateOverlay(); });
                vid.addEventListener('click', () => { toggle(); updateOverlay(); });
                vid.addEventListener('play', updateOverlay);
                vid.addEventListener('pause', updateOverlay);
                vid.addEventListener('loadeddata', updateOverlay);

                // keyboard accessibility: space toggles when focused on video wrapper
                wrap.addEventListener('keydown', (e) => {
                    if (e.key === ' ' || e.code === 'Space' || e.key === 'Enter') {
                        e.preventDefault();
                        toggle();
                        updateOverlay();
                    }
                });

                // initial state (delay slightly to allow autoplay to start)
                setTimeout(updateOverlay, 80);
            } catch (err) {}
        })();
    </script>

    <script src="../assets/js/topnav.js"></script>

</body>

</html>