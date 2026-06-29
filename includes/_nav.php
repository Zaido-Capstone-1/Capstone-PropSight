<?php
/**
 * _nav.php — Shared user navbar partial.
 *
 * Requires the calling page to have set:
 *   $page_title      string  — used in <title>
 *   $active_nav      string  — key from user_top_nav.php; also matches $account_nav_keys for grouping
 *   $initials        string  — e.g. "KZ"
 *   $sidebarPhoto    string  — resolved URL to profile photo, or ''
 *   $top_nav_items   array   — from require user_top_nav.php
 *   $account_nav_keys array  — keys that map to the "My Account" nav item
 *
 * Optional (page-specific <head> additions):
 *   $page_extra_head string  — raw HTML to inject inside <head> before </head>
 *                              (use for page-specific CSS links, meta tags, etc.)
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>">
    <title>Boracay Accommodation —
        <?php echo htmlspecialchars($page_title); ?>
    </title>
    <link rel="icon" type="image/png" href="../../assets/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../../assets/css/user-css/layout.css?v=7">
    <link rel="stylesheet" href="../../assets/css/user-css/bottom-nav.css">
    <link rel="stylesheet" href="../../assets/css/user-css/floating-chat.css?v=4">
    <link
        href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=block"
        rel="stylesheet">
    <?php if (!empty($page_extra_head))
        echo $page_extra_head; ?>
</head>

<body>
    <script>
        window.PS_CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
        window.PS_USER_ID = <?php echo json_encode((int) ($_SESSION['user_id'] ?? 0)); ?>;
        window.psGetCsrfToken = function () {
            if (window.PS_CSRF_TOKEN) return String(window.PS_CSRF_TOKEN);
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? String(meta.getAttribute('content') || '') : '';
        };
        window.psAppendCsrf = function (target) {
            const token = window.psGetCsrfToken();
            if (!token || !target || typeof target.append !== 'function') return target;
            target.append('csrf_token', token);
            return target;
        };
    </script>
    <script src="../../assets/js/user-js/floating-chat.js?v=5"></script>

    <header id="hdr">
        <a href="user-dashboard.php" class="logo">
            <img src="../../assets/images/logo.png" alt="Boracay Accommodation" class="logo-icon">
            <div class="logo-wordmark">
                <strong>Boracay Accommodation</strong>
                <span>Boracay, Philippines</span>
            </div>
        </a>
        <nav>
            <?php foreach ($top_nav_items as $top_nav):
                $topNavKeys = array_column($top_nav_items, 'key');
                $isTopActive = ($active_nav === $top_nav['key']) ||
                    ($top_nav['key'] === 'dashboard' && !in_array($active_nav, $topNavKeys, true) && in_array($active_nav, $account_nav_keys, true));
                ?>
                <a href="<?php echo htmlspecialchars($top_nav['href']); ?>"
                    class="<?php echo $isTopActive ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($top_nav['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="header-right">
            <?php if (empty($hideBrowseBtn)): ?>
            <a href="units.php" class="btn-browse" style="text-decoration:none;">Browse Rooms</a>
            <?php endif; ?>
            <button id="chatBellBtn" type="button" aria-label="Messages" style="background:none;border:none;cursor:pointer;padding:6px;border-radius:50%;
                       color:var(--text-soft);display:flex;align-items:center;justify-content:center;
                       position:relative;transition:background 0.2s;" onmouseenter="this.style.background='var(--navy-50,var(--blue-50,#eff6ff))'"
                onmouseleave="this.style.background='none'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
                <span id="chatMsgBadge" data-rt="messages" style="display:none;position:absolute;top:2px;right:2px;font-size:.62rem;background:#ef4444;color:#fff;border-radius:99px;min-width:15px;height:15px;padding:0 3px;align-items:center;justify-content:center;font-weight:700;pointer-events:none;">0</span>
            </button>
            <div style="position:relative;display:inline-flex;align-items:center;">
                <button id="notifBellBtn" aria-label="Notifications" style="background:none;border:none;cursor:pointer;padding:6px;border-radius:50%;
                           color:var(--text-soft);display:flex;align-items:center;justify-content:center;
                           transition:background 0.2s;" onmouseenter="this.style.background='var(--blue-50,#eff6ff)'"
                    onmouseleave="this.style.background='none'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        style="width:20px;height:20px;">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                    <span data-rt="notif-count" style="display:none;position:absolute;top:2px;right:2px;
                           font-size:0.62rem;background:#ef4444;color:#fff;border-radius:99px;
                           min-width:15px;height:15px;padding:0 3px;
                           align-items:center;justify-content:center;font-weight:700;pointer-events:none;">0</span>
                </button>
            </div>
            <div class="btn-profile-wrap">
                <button class="btn-profile" id="profileBtn" aria-label="My Profile" style="position:relative;">
                    <?php if ($sidebarPhoto): ?>
                        <img src="<?php echo htmlspecialchars($sidebarPhoto); ?>" alt="Profile photo"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                    <?php endif; ?>
                    <span class="profile-initials" <?php echo $sidebarPhoto ? 'style="display:none;"' : ''; ?>>
                        <?php echo $initials; ?>
                    </span>
                </button>
                <span class="profile-dot"></span>
            </div>
            <button class="hamburger" id="hamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </header>

    <?php require __DIR__ . '/_unitdetails_layout.php'; ?>

    <div id="toast">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="20 6 9 17 4 12" />
        </svg>
        <span id="toastMsg"></span>
    </div>