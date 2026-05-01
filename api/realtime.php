<?php
/**
 * api/realtime.php — PropSight Real-Time Polling Endpoint
 * --------------------------------------------------------
 * GET params:
 *   role        admin | user
 *   since       ISO timestamp — return records newer than this
 *   page        current page context (reservations, dashboard, checkin, messages, bookings, etc.)
 *
 * Returns JSON with only what has changed since `since`.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$role    = $_SESSION['role'] ?? 'user';
$userId  = (int)$_SESSION['user_id'];
$since   = trim($_GET['since'] ?? '');
$page    = trim($_GET['page'] ?? 'dashboard');

// Validate / normalise since timestamp
if (!$since || !strtotime($since)) {
    $since = date('Y-m-d H:i:s', strtotime('-30 seconds'));
}
$sinceEsc = mysqli_real_escape_string($conn, $since);
$now      = date('Y-m-d H:i:s');

$payload = ['success' => true, 'ts' => $now, 'role' => $role];

// ═══════════════════════════════════════════════════════
//  ADMIN PAYLOAD
// ═══════════════════════════════════════════════════════
if ($role === 'admin') {

    // ── Booking stats (always) ─────────────────────────
    $statsRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            COUNT(*)                              AS total,
            SUM(status='pending')                 AS pending,
            SUM(status IN('confirmed','active'))  AS confirmed,
            SUM(status='completed')               AS completed,
            SUM(status='cancelled')               AS cancelled
         FROM bookings"));
    $payload['booking_stats'] = $statsRow;

    // ── Unit stats ─────────────────────────────────────
    $unitRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            COUNT(*) AS total,
            SUM(status='occupied')    AS occupied,
            SUM(status='vacant')      AS vacant,
            SUM(status='maintenance') AS maintenance
         FROM units"));
    $payload['unit_stats'] = $unitRow;

    // ── New / updated bookings since `since` ───────────
    $newBookingsRes = mysqli_query($conn,
        "SELECT
            b.booking_id, b.checkin_date, b.checkout_date, b.guests,
            b.total_amount, b.status, b.created_at, b.payment_method,
            DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
            CONCAT(u2.first_name,' ',u2.last_name)    AS user_name,
            u2.email AS user_email, u2.phone AS user_phone,
            un.unit_name, un.unit_number, un.unit_id,
            p.property_name, p.property_id
         FROM bookings b
         JOIN users     u2 ON u2.user_id = b.user_id
         JOIN units     un ON un.unit_id = b.unit_id
         LEFT JOIN properties p ON p.property_id = un.property_id
         WHERE b.created_at > '$sinceEsc' OR b.updated_at > '$sinceEsc'
         ORDER BY GREATEST(b.created_at, COALESCE(b.updated_at, b.created_at)) DESC
         LIMIT 50");
    $newBookings = [];
    while ($r = mysqli_fetch_assoc($newBookingsRes)) $newBookings[] = $r;
    $payload['new_bookings'] = $newBookings;

    // ── Unread messages count (messages sent TO admin, not yet read) ──
    $msgRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM messages WHERE to_user=$userId AND is_read=0"));
    $payload['unread_messages'] = (int)($msgRow['c'] ?? 0);

    // ── New messages (for messages page real-time) ─────
    // Only return messages FROM others (not sent by this admin) to avoid
    // double-rendering with the page's own optimistic send + poll.
    if ($page === 'messages') {
        $newMsgRes = mysqli_query($conn,
            "SELECT m.message_id AS id, m.*, CONCAT(u.first_name,' ',u.last_name) AS sender_name,
                    m.from_user AS sender_id
             FROM messages m
             JOIN users u ON u.user_id = m.from_user
             WHERE m.created_at > '$sinceEsc'
               AND m.to_user = $userId
             ORDER BY m.created_at ASC
             LIMIT 30");
        $newMsgs = [];
        while ($r = mysqli_fetch_assoc($newMsgRes)) $newMsgs[] = $r;
        $payload['new_messages'] = $newMsgs;
    }

    // ── Check-in/out page: today's list changes ────────
    if ($page === 'checkin') {
        $ciRes = mysqli_query($conn,
            "SELECT b.booking_id, b.checkin_date, b.checkout_date, b.status,
                    CONCAT(u.first_name,' ',u.last_name) AS guest_name,
                    un.unit_name, un.unit_number, p.property_name
             FROM bookings b
             JOIN users u  ON u.user_id  = b.user_id
             JOIN units un ON un.unit_id = b.unit_id
             LEFT JOIN properties p ON p.property_id = un.property_id
             WHERE (b.updated_at > '$sinceEsc' OR b.created_at > '$sinceEsc')
               AND (b.checkin_date = CURDATE() OR b.checkout_date = CURDATE()
                    OR b.status = 'active')
             ORDER BY b.checkin_date ASC
             LIMIT 30");
        $ci = [];
        while ($r = mysqli_fetch_assoc($ciRes)) $ci[] = $r;
        $payload['checkin_updates'] = $ci;
    }

    // ── Recent activity for dashboard ─────────────────
    if (in_array($page, ['dashboard', 'task_summary'], true)) {
        $actRes = mysqli_query($conn,
            "SELECT b.booking_id, b.status, b.created_at, b.total_amount,
                    CONCAT(u.first_name,' ',u.last_name) AS user_name,
                    un.unit_name
             FROM bookings b
             JOIN users u  ON u.user_id  = b.user_id
             JOIN units un ON un.unit_id = b.unit_id
             WHERE b.created_at > '$sinceEsc' OR b.updated_at > '$sinceEsc'
             ORDER BY GREATEST(b.created_at, COALESCE(b.updated_at, b.created_at)) DESC
             LIMIT 10");
        $act = [];
        while ($r = mysqli_fetch_assoc($actRes)) $act[] = $r;
        $payload['recent_activity'] = $act;

        // Revenue KPI
        $year = (int)date('Y');
        $rev  = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
             WHERE type='Income' AND YEAR(transaction_date)=$year"));
        $payload['total_revenue'] = (float)($rev['v'] ?? 0);

        // Dashboard KPI details for richer live updates
        $lastYearRevenue = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
             WHERE type='Income' AND YEAR(transaction_date)=" . ($year - 1)));
        $thisYearBookings = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM bookings WHERE YEAR(created_at)=$year"));
        $lastYearBookings = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM bookings WHERE YEAR(created_at)=" . ($year - 1)));
        $pendingBookings = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM bookings WHERE status='pending'"));
        $cancelledThisMonth = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM bookings
             WHERE status='cancelled' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"));
        $totalThisMonth = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM bookings
             WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"));

        $totalRevVal = (float)($rev['v'] ?? 0);
        $lastRevVal = (float)($lastYearRevenue['v'] ?? 0);
        $thisBookingsVal = (int)($thisYearBookings['c'] ?? 0);
        $lastBookingsVal = (int)($lastYearBookings['c'] ?? 0);
        $totalThisMonthVal = max(1, (int)($totalThisMonth['c'] ?? 0));

        $payload['dashboard_metrics'] = [
            'revenue_growth' => $lastRevVal > 0 ? round((($totalRevVal - $lastRevVal) / $lastRevVal) * 100, 1) : 0,
            'last_year_revenue' => $lastRevVal,
            'booking_growth' => $lastBookingsVal > 0 ? round((($thisBookingsVal - $lastBookingsVal) / $lastBookingsVal) * 100, 1) : 0,
            'pending_bookings' => (int)($pendingBookings['c'] ?? 0),
            'cancelled_this_month' => (int)($cancelledThisMonth['c'] ?? 0),
            'cancel_rate' => round(((int)($cancelledThisMonth['c'] ?? 0) / $totalThisMonthVal) * 100, 1),
            'occupied_units' => (int)($unitRow['occupied'] ?? 0),
            'vacant_units' => (int)($unitRow['vacant'] ?? 0),
            'maintenance_units' => (int)($unitRow['maintenance'] ?? 0),
            'total_units' => max(1, (int)($unitRow['total'] ?? 1)),
        ];

        // Last 8 months trend for revenue and expenses
        $labels = [];
        $revenueSeries = [];
        $expenseSeries = [];
        for ($i = 7; $i >= 0; $i--) {
            $ts = strtotime("-$i months");
            $ty = (int)date('Y', $ts);
            $tm = (int)date('n', $ts);
            $labels[] = date('M', $ts);

            $rv = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
                 WHERE type='Income' AND YEAR(transaction_date)=$ty AND MONTH(transaction_date)=$tm"));
            $ex = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT COALESCE(SUM(amount),0) AS v FROM expenses
                 WHERE YEAR(expense_date)=$ty AND MONTH(expense_date)=$tm"));

            $revenueSeries[] = round(((float)($rv['v'] ?? 0)) / 1000, 1);
            $expenseSeries[] = round(((float)($ex['v'] ?? 0)) / 1000, 1);
        }
        $payload['financial_series'] = [
            'labels' => $labels,
            'revenue_k' => $revenueSeries,
            'expenses_k' => $expenseSeries,
        ];

        $topPropRes = mysqli_query($conn,
            "SELECT p.property_id, p.property_name, p.address,
                    COUNT(u.unit_id) AS total_units,
                    SUM(u.status='occupied') AS occupied
             FROM properties p
             LEFT JOIN units u ON u.property_id = p.property_id
             GROUP BY p.property_id
             ORDER BY occupied DESC
             LIMIT 4");
        $topProperties = [];
        while ($topPropRes && ($r = mysqli_fetch_assoc($topPropRes))) $topProperties[] = $r;
        $payload['top_properties'] = $topProperties;

        $taskRes = mysqli_query($conn,
            "SELECT 
                m.issue_description AS title, 
                p.property_name, 
                m.priority, 
                m.request_status AS status,
                m.request_date
             FROM maintenance_requests m
             LEFT JOIN units u ON u.unit_id = m.unit_id
             LEFT JOIN properties p ON p.property_id = u.property_id
             ORDER BY m.request_date DESC
             LIMIT 5");
        $tasks = [];
        while ($taskRes && ($r = mysqli_fetch_assoc($taskRes))) $tasks[] = $r;
        $payload['task_summary'] = $tasks;

        // Right panel recent transaction activity
        $rpActRes = mysqli_query($conn,
            "SELECT description, amount, type, transaction_date
             FROM transactions
             ORDER BY transaction_date DESC, id DESC
             LIMIT 5");
        $rightPanelActivity = [];
        while ($rpActRes && ($r = mysqli_fetch_assoc($rpActRes))) $rightPanelActivity[] = $r;
        $payload['right_panel_activity'] = $rightPanelActivity;
    }

// ═══════════════════════════════════════════════════════
//  USER PAYLOAD
// ═══════════════════════════════════════════════════════
} else {

    // ── User's booking status changes ──────────────────
    $bkRes = mysqli_query($conn,
        "SELECT
            b.booking_id, b.status, b.checkin_date, b.checkout_date,
            b.total_amount, b.updated_at, b.unit_id, b.guests,
            DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
            un.unit_name, un.unit_number,
            p.property_name, p.address, p.latitude, p.longitude,
            (
                SELECT ui.image_path
                FROM unit_images ui
                WHERE ui.unit_id = b.unit_id
                ORDER BY ui.sort_order ASC, ui.image_id ASC
                LIMIT 1
            ) AS image_path
         FROM bookings b
         JOIN units un ON un.unit_id = b.unit_id
         LEFT JOIN properties p ON p.property_id = un.property_id
         WHERE b.user_id = $userId
           AND (b.updated_at > '$sinceEsc' OR b.created_at > '$sinceEsc')
         ORDER BY GREATEST(b.created_at, COALESCE(b.updated_at, b.created_at)) DESC
         LIMIT 20");
    $bkChanges = [];
    while ($r = mysqli_fetch_assoc($bkRes)) $bkChanges[] = $r;
    $payload['booking_updates'] = $bkChanges;

    // ── User booking stats ─────────────────────────────
    $statsRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            COUNT(*)                              AS total,
            SUM(status IN('confirmed','pending')) AS upcoming,
            SUM(status='active')                  AS active_cnt,
            SUM(status='completed')               AS completed,
            SUM(status='cancelled')               AS cancelled
         FROM bookings WHERE user_id = $userId"));
    $payload['booking_stats'] = $statsRow;

    // ── User metrics for live UI cards ──────────────────
    $loyaltyRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(points),0) AS points
         FROM loyalty_points
         WHERE user_id = $userId"));
    $savedRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c
         FROM saved_units
         WHERE user_id = $userId"));
    $loyaltyPoints = max(0, (int)($loyaltyRow['points'] ?? 0));
    $loyaltyTier = 'Silver';
    if ($loyaltyPoints >= 5000) $loyaltyTier = 'Diamond';
    elseif ($loyaltyPoints >= 2000) $loyaltyTier = 'Platinum';
    elseif ($loyaltyPoints >= 500) $loyaltyTier = 'Gold';
    $payload['user_metrics'] = [
        'booking_total' => (int)($statsRow['total'] ?? 0),
        'loyalty_points' => $loyaltyPoints,
        'loyalty_tier' => $loyaltyTier,
        'saved_count' => (int)($savedRow['c'] ?? 0),
    ];

    if ($page === 'dashboard') {
        $activeRes = mysqli_query($conn,
            "SELECT
                b.booking_id, b.unit_id, b.checkin_date, b.checkout_date,
                b.status, b.guests, b.total_amount,
                DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
                un.unit_name, un.unit_number,
                p.property_name, p.address, p.latitude, p.longitude,
                (
                    SELECT ui.image_path
                    FROM unit_images ui
                    WHERE ui.unit_id = b.unit_id
                    ORDER BY ui.sort_order ASC, ui.image_id ASC
                    LIMIT 1
                ) AS image_path
             FROM bookings b
             JOIN units un ON un.unit_id = b.unit_id
             LEFT JOIN properties p ON p.property_id = un.property_id
             WHERE b.user_id = $userId
               AND b.status IN ('pending','confirmed','active')
             ORDER BY b.checkin_date ASC, b.booking_id DESC
             LIMIT 1");
        $payload['manage_stay_booking'] = $activeRes ? mysqli_fetch_assoc($activeRes) : null;
    }

    // ── Unread notifications ───────────────────────────
    // Exclude 'booking' type — those are surfaced via the ps:new_bookings
    // channel and shown as inline badges on the reservations table.
    // Toasting them here causes repeated cross-page popups.
    $notifRes = mysqli_query($conn,
        "SELECT * FROM notifications
         WHERE user_id = $userId AND is_read = 0 AND type != 'booking'
         ORDER BY created_at DESC LIMIT 10");
    $notifs = [];
    while ($r = mysqli_fetch_assoc($notifRes)) $notifs[] = $r;
    $payload['notifications']     = $notifs;
    $payload['unread_notif_count'] = count($notifs);

    // ── Unread messages from admins ────────────────────
    $uMsgRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM messages
         WHERE to_user=$userId AND is_read=0"));
    $payload['unread_messages'] = (int)($uMsgRow['c'] ?? 0);

    // ── New messages for user messages page ───────────
    // Only return messages FROM admins to avoid double-rendering with page's own poll.
    if ($page === 'messages') {
        $uNewMsgRes = mysqli_query($conn,
            "SELECT m.message_id AS id, m.*, CONCAT(u.first_name,' ',u.last_name) AS sender_name,
                    m.from_user AS sender_id
             FROM messages m
             JOIN users u ON u.user_id = m.from_user
             WHERE m.to_user = $userId
               AND m.created_at > '$sinceEsc'
             ORDER BY m.created_at ASC
             LIMIT 30");
        $uNewMsgs = [];
        while ($r = mysqli_fetch_assoc($uNewMsgRes)) $uNewMsgs[] = $r;
        if ($uNewMsgs) $payload['new_messages'] = $uNewMsgs;
    }

    // ── Live profile snapshot (for cross-page auto sync) ───────
    $profileRow = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT
            first_name,
            last_name,
            email,
            verification_status,
            profile_photo
         FROM users
         WHERE user_id = $userId
         LIMIT 1"));
    if ($profileRow) {
        $payload['profile_sync'] = [
            'first_name' => (string)($profileRow['first_name'] ?? ''),
            'last_name' => (string)($profileRow['last_name'] ?? ''),
            'email' => (string)($profileRow['email'] ?? ''),
            'verification_status' => (string)($profileRow['verification_status'] ?? ''),
            'profile_photo' => (string)($profileRow['profile_photo'] ?? ''),
        ];
    }

    // ── Live unit average ratings (from reviews) ───────────────
    $hasBookingReviews = false;
    if ($tbl = mysqli_query($conn, "SHOW TABLES LIKE 'booking_reviews'")) {
        $hasBookingReviews = mysqli_num_rows($tbl) > 0;
    }
    if ($hasBookingReviews) {
        $rtRes = mysqli_query($conn,
            "SELECT br.unit_id,
                    ROUND(AVG(br.rating), 1) AS avg_rating,
                    COUNT(*) AS rating_count
             FROM booking_reviews br
             WHERE br.created_at > '$sinceEsc' OR br.updated_at > '$sinceEsc'
             GROUP BY br.unit_id
             ORDER BY MAX(COALESCE(br.updated_at, br.created_at)) DESC
             LIMIT 50");
        $unitRatings = [];
        while ($rtRes && ($r = mysqli_fetch_assoc($rtRes))) {
            $unitRatings[] = [
                'unit_id' => (int)$r['unit_id'],
                'avg_rating' => $r['avg_rating'] !== null ? (float)$r['avg_rating'] : null,
                'rating_count' => (int)$r['rating_count'],
            ];
        }
        $payload['unit_ratings'] = $unitRatings;
    }
}

echo json_encode($payload);
