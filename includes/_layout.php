<?php
require_once __DIR__ . '/../includes/db.php';
$top_nav_items = require __DIR__ . '/user_top_nav.php';
$account_nav_keys = ['dashboard', 'profile', 'saved', 'loyalty', 'settings', 'payment'];
$isVerifiedSidebar = (($_SESSION['verification_status'] ?? '') === 'Verified');
$sidebarPhotoRaw = trim((string) ($_SESSION['profile_photo'] ?? ''));
if ($sidebarPhotoRaw === '' && isset($_SESSION['user_id']) && $conn) {
    $uid = (int) $_SESSION['user_id'];
    $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT profile_photo FROM users WHERE user_id=$uid LIMIT 1"));
    $sidebarPhotoRaw = trim((string) ($r['profile_photo'] ?? ''));
    if ($sidebarPhotoRaw !== '') {
        $_SESSION['profile_photo'] = $sidebarPhotoRaw;
    }
}
$sidebarPhoto = $sidebarPhotoRaw !== '' ? '../../' . ltrim($sidebarPhotoRaw, '/') : '';
$nav_items = [
    'profile' => ['label' => 'View Profile', 'sub' => 'Personal details & preferences', 'href' => 'profile.php', 'badge' => null, 'icon' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    'bookings' => [
        'label' => 'My Bookings',
        'sub' => 'View and manage reservations',
        'href' => 'bookings.php',
        'badge' => (function () {
            global $conn;
            $uid = (int) $_SESSION['user_id'];
            if (!$conn)
                return null;
            $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE user_id=$uid AND status IN('pending','confirmed','active')"));
            return $r['c'] > 0 ? (string) $r['c'] : null;
        })(),
        'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'
    ],
    'payment' => [
        'label' => 'Payment History',
        'sub' => 'View transactions & refunds',
        'href' => 'payment.php',
        'badge' => null,
        'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'
    ],
    'saved' => [
        'label' => 'Saved Rooms',
        'sub' => 'Rooms on your wishlist',
        'href' => 'saved.php',
        'badge' => (function () {
            global $conn;
            $uid = (int) $_SESSION['user_id'];
            if (!$conn)
                return null;
            $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM saved_units WHERE user_id=$uid"));
            return $r['c'] > 0 ? (string) $r['c'] : null;
        })(),
        'icon' => '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>'
    ],
    'loyalty' => [
        'label' => 'Loyalty Points',
        'sub' => (function () {
            global $conn;
            $uid = (int) $_SESSION['user_id'];
            if (!$conn)
                return 'Earn points every stay';
            $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(points),0) AS v FROM loyalty_points WHERE user_id=$uid"));
            $pts = max(0, (int) $r['v']);
            $tier = 'Silver';
            if ($pts >= 5000)
                $tier = 'Diamond';
            elseif ($pts >= 2000)
                $tier = 'Platinum';
            elseif ($pts >= 500)
                $tier = 'Gold';
            return number_format($pts) . ' pts · ' . $tier . ' tier';
        })(),
        'href' => 'loyalty.php',
        'badge' => null,
        'icon' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>'
    ],
    'settings' => ['label' => 'Settings', 'sub' => 'Notifications, privacy, security', 'href' => 'settings.php', 'badge' => null, 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
    'support' => ['label' => 'Support & Help', 'sub' => 'FAQs and contact staff', 'href' => 'support.php', 'badge' => null, 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>'],
];
?>
<?php require __DIR__ . '/_nav.php'; ?>

    <div class="page-shell">
        <?php
        /* Apartment-lifestyle illustration — rendered as inline SVG so no image files needed.
           Each page can override $page_hero_art_id to pick a variant (default: building facade). */
        $art_id = $page_hero_art_id ?? 'default';
        $art_svgs = [
            /* Generic building facade with windows */
            'default' => '<svg viewBox="0 0 340 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Main building body -->
                <rect x="40" y="20" width="120" height="140" rx="3" fill="white" opacity=".07"/>
                <rect x="42" y="22" width="116" height="138" rx="2" fill="none" stroke="white" stroke-width=".8" opacity=".2"/>
                <!-- Windows row 1 -->
                <rect x="54" y="34" width="22" height="18" rx="2" fill="white" opacity=".13"/>
                <rect x="84" y="34" width="22" height="18" rx="2" fill="white" opacity=".13"/>
                <rect x="114" y="34" width="22" height="18" rx="2" fill="#e8c882" opacity=".18"/>
                <!-- Windows row 2 -->
                <rect x="54" y="62" width="22" height="18" rx="2" fill="#e8c882" opacity=".18"/>
                <rect x="84" y="62" width="22" height="18" rx="2" fill="white" opacity=".13"/>
                <rect x="114" y="62" width="22" height="18" rx="2" fill="white" opacity=".13"/>
                <!-- Windows row 3 -->
                <rect x="54" y="90" width="22" height="18" rx="2" fill="white" opacity=".13"/>
                <rect x="84" y="90" width="22" height="18" rx="2" fill="#e8c882" opacity=".22"/>
                <rect x="114" y="90" width="22" height="18" rx="2" fill="white" opacity=".13"/>
                <!-- Door -->
                <rect x="84" y="128" width="32" height="32" rx="2" fill="white" opacity=".12"/>
                <circle cx="112" cy="144" r="2" fill="white" opacity=".3"/>
                <!-- Second building (taller, right) -->
                <rect x="178" y="8" width="90" height="152" rx="3" fill="white" opacity=".05"/>
                <rect x="180" y="10" width="86" height="150" rx="2" fill="none" stroke="white" stroke-width=".8" opacity=".15"/>
                <!-- Second building windows -->
                <rect x="190" y="22" width="18" height="14" rx="1.5" fill="white" opacity=".1"/>
                <rect x="215" y="22" width="18" height="14" rx="1.5" fill="#e8c882" opacity=".2"/>
                <rect x="240" y="22" width="18" height="14" rx="1.5" fill="white" opacity=".1"/>
                <rect x="190" y="44" width="18" height="14" rx="1.5" fill="#e8c882" opacity=".15"/>
                <rect x="215" y="44" width="18" height="14" rx="1.5" fill="white" opacity=".1"/>
                <rect x="240" y="44" width="18" height="14" rx="1.5" fill="#e8c882" opacity=".2"/>
                <rect x="190" y="66" width="18" height="14" rx="1.5" fill="white" opacity=".1"/>
                <rect x="215" y="66" width="18" height="14" rx="1.5" fill="white" opacity=".1"/>
                <rect x="240" y="66" width="18" height="14" rx="1.5" fill="#e8c882" opacity=".15"/>
                <rect x="190" y="88" width="18" height="14" rx="1.5" fill="#e8c882" opacity=".2"/>
                <rect x="215" y="88" width="18" height="14" rx="1.5" fill="white" opacity=".1"/>
                <rect x="240" y="88" width="18" height="14" rx="1.5" fill="white" opacity=".1"/>
                <rect x="190" y="110" width="18" height="14" rx="1.5" fill="white" opacity=".1"/>
                <rect x="215" y="110" width="18" height="14" rx="1.5" fill="#e8c882" opacity=".18"/>
                <rect x="240" y="110" width="18" height="14" rx="1.5" fill="white" opacity=".1"/>
                <!-- Ground line -->
                <line x1="20" y1="160" x2="320" y2="160" stroke="white" stroke-width=".6" opacity=".12"/>
                <!-- Palm accent -->
                <line x1="300" y1="160" x2="300" y2="100" stroke="white" stroke-width="1.5" opacity=".15"/>
                <path d="M300 100 Q310 88 325 84" stroke="white" stroke-width="1.2" opacity=".15" fill="none"/>
                <path d="M300 100 Q290 86 278 88" stroke="white" stroke-width="1.2" opacity=".15" fill="none"/>
                <path d="M300 105 Q315 98 330 102" stroke="white" stroke-width="1" opacity=".12" fill="none"/>
            </svg>',
            /* Key / booking variant */
            'bookings' => '',
        ];
        /* Map page keys to art variants */
        $art_map = ['bookings' => 'bookings'];
        $resolved_art = $art_map[$art_id] ?? 'default';
        $art_svg = $art_svgs[$resolved_art];

        $_heroImg = '../../assets/images/hero.jpg';
        $_heroStyle = ' style="background-image:url(\'' . $_heroImg . '\');background-size:cover;background-position:center;background-repeat:no-repeat;"';
        echo '<div class="page-hero"' . $_heroStyle . '>';
        echo '<div class="page-hero-rule"></div>';
        echo '<div class="page-hero-inner reveal">';
        echo '<div>';
        echo '<div class="breadcrumb"><a href="user-dashboard.php">My Account</a><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg><span>' . htmlspecialchars($page_title) . '</span></div>';
        echo '<h1 class="page-hero-title">' . ($page_hero_html ?? htmlspecialchars($page_title)) . '</h1>';
        echo '<p class="page-hero-sub">' . ($page_hero_sub ?? '') . '</p>';
        echo '</div>';
        echo '<div class="page-hero-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">' . ($page_hero_icon ?? '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>') . '</svg></div>';
        echo '</div>';
        echo '</div>';
        ?>
        <?php if (!$isVerifiedSidebar): ?>
            <div class="verify-lock-banner">
                <div class="verify-lock-text">
                    Your account is not fully verified yet. Booking and support actions are disabled until you verify your
                    Gmail.
                </div>
                <a href="profile.php" class="btn-secondary">Verify now</a>
            </div>
        <?php endif; ?>
        <div class="page-content">