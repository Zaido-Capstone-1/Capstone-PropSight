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
header('X-Debug-Version: v2');
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

$role = $_SESSION['role'] ?? 'user';
$userId = (int) $_SESSION['user_id'];
$since = trim($_GET['since'] ?? '');
$page = trim($_GET['page'] ?? 'dashboard');

// Validate / normalise since timestamp
if (!$since || !strtotime($since)) {
    $since = gmdate('Y-m-d H:i:s', strtotime('-30 seconds'));
}
$sinceEsc = mysqli_real_escape_string($conn, $since);
$now = gmdate('Y-m-d H:i:s') . '+00:00';  // UTC, tagged for JS

$payload = ['success' => true, 'ts' => $now, 'role' => $role];

// ═══════════════════════════════════════════════════════
//  ADMIN PAYLOAD
// ═══════════════════════════════════════════════════════
if ($role === 'admin') {

    // ── Booking stats (always) ─────────────────────────
    $statsRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT
            COUNT(*)                              AS total,
            SUM(status='pending')                 AS pending,
            SUM(status IN('confirmed','active'))  AS confirmed,
            SUM(status='completed')               AS completed,
            SUM(status='cancelled')               AS cancelled
         FROM bookings"
    ));
    $payload['booking_stats'] = $statsRow;

    // ── Unit stats ─────────────────────────────────────
    $unitRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT
            COUNT(*) AS total,
            SUM(status='occupied')    AS occupied,
            SUM(status='vacant')      AS vacant,
            SUM(status='maintenance') AS maintenance
         FROM units"
    ));
    $payload['unit_stats'] = $unitRow;

    // ── New / updated bookings since `since` ───────────
    $newBookingsRes = mysqli_query(
        $conn,
        "SELECT
            b.booking_id, b.checkin_date, b.checkout_date, b.guests,
            b.total_amount, b.status, b.created_at, b.payment_method,
            DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
            CONCAT(u2.first_name,' ',u2.last_name)    AS user_name,
            u2.email AS user_email, u2.phone AS user_phone,
            u2.profile_photo AS user_photo,
            un.unit_name, un.unit_number, un.unit_id,
            p.property_name, p.property_id
         FROM bookings b
         JOIN users     u2 ON u2.user_id = b.user_id
         JOIN units     un ON un.unit_id = b.unit_id
         LEFT JOIN properties p ON p.property_id = un.property_id
         WHERE b.created_at > '$sinceEsc' OR b.updated_at > '$sinceEsc'
         ORDER BY GREATEST(b.created_at, COALESCE(b.updated_at, b.created_at)) DESC
         LIMIT 50"
    );
    $newBookings = [];
    while ($r = mysqli_fetch_assoc($newBookingsRes))
        $newBookings[] = $r;
    fmt_dt_rows($newBookings);
    $payload['new_bookings'] = $newBookings;

    // ── Unread messages count (messages sent TO admin, not yet read) ──
    $msgRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM messages WHERE to_user=$userId AND is_read=0"
    ));
    $payload['unread_messages'] = (int) ($msgRow['c'] ?? 0);

    // ── Open support tickets count ─────────────────────
    $supportRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM support_tickets WHERE status = 'open'"
    ));
    $payload['open_support_tickets'] = (int) ($supportRow['c'] ?? 0);

    // ── Admin notifications — read from admin_notifications table ────────
    // Sync sources first so new events appear without page reload
    require_once __DIR__ . '/../includes/admin_notif_helpers.php';
    sync_notifications($conn, $userId);

    $_anCountRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM admin_notifications WHERE admin_id = $userId AND is_read = 0"
    ));
    $adminNotifUnreadCount = (int) ($_anCountRow['c'] ?? 0);

    // First page only — matches the dropdown's initial page size. "View more"
    // pagination is handled separately via api/admin/notifications.php.
    $_anPageSize = 10;

    $adminNotifs = [];
    $_anRes = mysqli_query(
        $conn,
        "SELECT id, type, ref_id, text, path, ts, is_read
         FROM admin_notifications
         WHERE admin_id = $userId
         ORDER BY ts DESC LIMIT $_anPageSize"
    );
    while ($_anRes && ($n = mysqli_fetch_assoc($_anRes))) {
        fmt_dt_row($n);
        $adminNotifs[] = [
            'id' => $n['ref_id'],
            'db_id' => (int) $n['id'],
            'type' => $n['type'],
            'text' => $n['text'],
            'ts' => $n['ts'],
            'path' => $n['path'] ?? '',
            'is_read' => (int) $n['is_read'],
        ];
    }
    $payload['admin_notifications'] = $adminNotifs;
    $payload['admin_notif_count'] = $adminNotifUnreadCount;
    // Only return messages FROM others (not sent by this admin) to avoid
    // double-rendering with the page's own optimistic send + poll.
    if ($page === 'messages') {
        $newMsgRes = mysqli_query(
            $conn,
            "SELECT m.message_id AS id, m.*, CONCAT(u.first_name,' ',u.last_name) AS sender_name,
                    m.from_user AS sender_id
             FROM messages m
             JOIN users u ON u.user_id = m.from_user
             WHERE m.created_at > '$sinceEsc'
               AND m.to_user = $userId
             ORDER BY m.created_at ASC
             LIMIT 30"
        );
        $newMsgs = [];
        while ($r = mysqli_fetch_assoc($newMsgRes))
            $newMsgs[] = $r;
        fmt_dt_rows($newMsgs);
        $payload['new_messages'] = $newMsgs;
    }

    // ── Check-in/out page: today's list changes ────────
    if ($page === 'checkin') {
        $ciRes = mysqli_query(
            $conn,
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
             LIMIT 30"
        );
        $ci = [];
        while ($r = mysqli_fetch_assoc($ciRes))
            $ci[] = $r;
        fmt_dt_rows($ci);
        $payload['checkin_updates'] = $ci;
    }

    // ── Recent activity for dashboard ─────────────────
    if (in_array($page, ['dashboard', 'task_summary'], true)) {
        // Use $sinceEsc for live polling; for the initial load (since=2000-01-01)
        // fall back to the last 30 days so the feed always has entries.
        $actWhere = (strtotime($since) < strtotime('-1 hour'))
            ? "b.created_at >= DATE_SUB(NOW(), INTERVAL 5 HOUR)"
            : "(b.created_at > '$sinceEsc' OR b.updated_at > '$sinceEsc')";

        $actRes = mysqli_query(
            $conn,
            "SELECT b.booking_id, b.status, b.created_at, b.total_amount,
                CONCAT(u.first_name,' ',u.last_name) AS user_name,
                COALESCE(un.unit_name, CONCAT('Unit #', b.unit_id)) AS unit_name
            FROM bookings b
            JOIN users u   ON u.user_id  = b.user_id
            LEFT JOIN units un ON un.unit_id = b.unit_id
            WHERE $actWhere
            ORDER BY GREATEST(b.created_at, COALESCE(b.updated_at, b.created_at)) DESC
            LIMIT 10"
        );
        $act = [];
        while ($r = mysqli_fetch_assoc($actRes))
            $act[] = $r;
        fmt_dt_rows($act);
        $payload['recent_activity'] = $act;

        // Revenue KPI
        $year = (int) date('Y');
        $rev = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
             WHERE type='Income' AND YEAR(transaction_date)=$year"
        ));
        $payload['total_revenue'] = (float) ($rev['v'] ?? 0);

        // Dashboard KPI details for richer live updates
        $lastYearRevenue = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COALESCE(SUM(amount),0) AS v FROM transactions
             WHERE type='Income' AND YEAR(transaction_date)=" . ($year - 1)
        ));
        $thisYearBookings = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM bookings WHERE YEAR(created_at)=$year"
        ));
        $lastYearBookings = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM bookings WHERE YEAR(created_at)=" . ($year - 1)
        ));
        $pendingBookings = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM bookings WHERE status='pending'"
        ));
        $cancelledThisMonth = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM bookings
             WHERE status='cancelled' AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
        ));
        $totalThisMonth = mysqli_fetch_assoc(mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM bookings
             WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')"
        ));

        $totalRevVal = (float) ($rev['v'] ?? 0);
        $lastRevVal = (float) ($lastYearRevenue['v'] ?? 0);
        $thisBookingsVal = (int) ($thisYearBookings['c'] ?? 0);
        $lastBookingsVal = (int) ($lastYearBookings['c'] ?? 0);
        $totalThisMonthVal = max(1, (int) ($totalThisMonth['c'] ?? 0));

        $payload['dashboard_metrics'] = [
            'revenue_growth' => $lastRevVal > 0 ? round((($totalRevVal - $lastRevVal) / $lastRevVal) * 100, 1) : 0,
            'last_year_revenue' => $lastRevVal,
            'booking_growth' => $lastBookingsVal > 0 ? round((($thisBookingsVal - $lastBookingsVal) / $lastBookingsVal) * 100, 1) : 0,
            'pending_bookings' => (int) ($pendingBookings['c'] ?? 0),
            'cancelled_this_month' => (int) ($cancelledThisMonth['c'] ?? 0),
            'cancel_rate' => round(((int) ($cancelledThisMonth['c'] ?? 0) / $totalThisMonthVal) * 100, 1),
            'occupied_units' => (int) ($unitRow['occupied'] ?? 0),
            'vacant_units' => (int) ($unitRow['vacant'] ?? 0),
            'maintenance_units' => (int) ($unitRow['maintenance'] ?? 0),
            'total_units' => max(1, (int) ($unitRow['total'] ?? 1)),
        ];

        // Full-year chart data (Jan–Dec, current year)
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $revenueSeries = array_fill(0, 12, 0.0);
        $expenseSeries = array_fill(0, 12, 0.0);
        $chartYear = (int) date('Y');

        $rvRes = mysqli_query(
            $conn,
            "SELECT MONTH(transaction_date)-1 AS m, COALESCE(SUM(amount),0) AS v
            FROM transactions WHERE type='Income' AND YEAR(transaction_date)=$chartYear
            GROUP BY m"
        );
        while ($rvRes && ($r = mysqli_fetch_assoc($rvRes)))
            $revenueSeries[(int) $r['m']] = round((float) $r['v'] / 1000, 1);

        $exRes = mysqli_query(
            $conn,
            "SELECT MONTH(expense_date)-1 AS m, COALESCE(SUM(amount),0) AS v
            FROM expenses WHERE YEAR(expense_date)=$chartYear
            GROUP BY m"
        );
        while ($exRes && ($r = mysqli_fetch_assoc($exRes)))
            $expenseSeries[(int) $r['m']] = round((float) $r['v'] / 1000, 1);

        $revenueSeries = array_values($revenueSeries);
        $expenseSeries = array_values($expenseSeries);
        $payload['financial_series'] = [
            'labels' => $labels,
            'revenue_k' => $revenueSeries,
            'expenses_k' => $expenseSeries,
        ];

        $topPropRes = mysqli_query(
            $conn,
            "SELECT p.property_id, p.property_name, p.address,
                    COUNT(u.unit_id) AS total_units,
                    SUM(u.status='occupied') AS occupied
             FROM properties p
             LEFT JOIN units u ON u.property_id = p.property_id
             GROUP BY p.property_id
             ORDER BY occupied DESC
             LIMIT 4"
        );
        $topProperties = [];
        while ($topPropRes && ($r = mysqli_fetch_assoc($topPropRes)))
            $topProperties[] = $r;
        $payload['top_properties'] = $topProperties;

        $taskRes = mysqli_query(
            $conn,
            "SELECT 
                m.request_id,
                m.issue_description AS title, 
                p.property_name, 
                m.priority, 
                m.request_status AS status,
                m.request_date,
                m.created_at
            FROM maintenance_requests m
            LEFT JOIN units u ON u.unit_id = m.unit_id
            LEFT JOIN properties p ON p.property_id = u.property_id
            WHERE m.request_status IN ('open', 'in_progress')
            ORDER BY m.created_at DESC
            LIMIT 50"
        );
        $tasks = [];
        while ($taskRes && ($r = mysqli_fetch_assoc($taskRes)))
            $tasks[] = $r;
        fmt_dt_rows($tasks);
        $payload['task_summary'] = $tasks;

        // Right panel recent transaction activity (includes completed refunds)
        $rpActRes = mysqli_query(
            $conn,
            "(SELECT description, amount, type, transaction_date
                FROM transactions)
             UNION ALL
             (SELECT
                CONCAT('Refund', IF(booking_id IS NOT NULL, CONCAT(' for Booking #', booking_id), '')) AS description,
                refund_amount AS amount,
                'Refund' AS type,
                COALESCE(processed_date, refund_date) AS transaction_date
              FROM refunds
              WHERE refund_status = 'completed' AND COALESCE(processed_date, refund_date) IS NOT NULL)
             ORDER BY transaction_date DESC
             LIMIT 6"
        );
        $rightPanelActivity = [];
        while ($rpActRes && ($r = mysqli_fetch_assoc($rpActRes)))
            $rightPanelActivity[] = $r;
        fmt_dt_rows($rightPanelActivity);
        $payload['right_panel_activity'] = $rightPanelActivity;
    }

    // ═══════════════════════════════════════════════════════
//  USER PAYLOAD
// ═══════════════════════════════════════════════════════
} else {

    // ── User's booking status changes ──────────────────
    $bkRes = mysqli_query(
        $conn,
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
         LIMIT 20"
    );
    $bkChanges = [];
    while ($r = mysqli_fetch_assoc($bkRes))
        $bkChanges[] = $r;
    fmt_dt_rows($bkChanges);
    $payload['booking_updates'] = $bkChanges;

    // ── User booking stats ─────────────────────────────
    $statsRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT
            COUNT(*)                                        AS total,
            SUM(status IN('confirmed','pending','active'))  AS upcoming,
            SUM(status='active')                            AS active_cnt,
            SUM(status='completed')                         AS completed,
            SUM(status='cancelled')                         AS cancelled,
            COALESCE(SUM(CASE WHEN status IN('completed','active') THEN total_amount END),0) AS total_spent
         FROM bookings WHERE user_id = $userId"
    ));
    $payload['booking_stats'] = $statsRow;

    // ── User metrics for live UI cards ──────────────────
    $loyaltyRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COALESCE(SUM(points),0) AS points
         FROM loyalty_points
         WHERE user_id = $userId"
    ));
    $savedRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c
         FROM saved_units
         WHERE user_id = $userId"
    ));
    $loyaltyPoints = max(0, (int) ($loyaltyRow['points'] ?? 0));
    $loyaltyTier = 'Silver';
    if ($loyaltyPoints >= 5000)
        $loyaltyTier = 'Diamond';
    elseif ($loyaltyPoints >= 2000)
        $loyaltyTier = 'Platinum';
    elseif ($loyaltyPoints >= 500)
        $loyaltyTier = 'Gold';
    $payload['user_metrics'] = [
        'booking_total' => (int) ($statsRow['total'] ?? 0),
        'loyalty_points' => $loyaltyPoints,
        'loyalty_tier' => $loyaltyTier,
        'saved_count' => (int) ($savedRow['c'] ?? 0),
        'total_spent' => (float) ($statsRow['total_spent'] ?? 0),
    ];

    if ($page === 'dashboard') {
        $activeRes = mysqli_query(
            $conn,
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
               AND (
                   b.status IN ('confirmed', 'active')
                   OR (b.status = 'pending' AND b.payment_method = 'cash')
               )
             ORDER BY b.checkin_date ASC, b.booking_id DESC
             LIMIT 1"
        );
        $payload['manage_stay_booking'] = $activeRes ? mysqli_fetch_assoc($activeRes) : null;
    }

    $notifRes = mysqli_query(
        $conn,
        "SELECT * FROM notifications
     WHERE user_id = $userId AND is_read = 0 AND type != 'booking'
     ORDER BY created_at DESC LIMIT 10"
    );
    $notifs = [];
    while ($r = mysqli_fetch_assoc($notifRes))
        $notifs[] = $r;
    fmt_dt_rows($notifs);
    $payload['notifications'] = $notifs;

    // Real total unread count (all types except booking)
    $unreadRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM notifications
     WHERE user_id = $userId AND is_read = 0"
    ));
    $payload['unread_notif_count'] = (int) ($unreadRow['c'] ?? 0);

    // ── Unread messages from admins ────────────────────
    $uMsgRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM messages
         WHERE to_user=$userId AND is_read=0"
    ));
    $payload['unread_messages'] = (int) ($uMsgRow['c'] ?? 0);

    // ── New messages for user messages page ───────────
    // Only return messages FROM admins to avoid double-rendering with page's own poll.
    if ($page === 'messages') {
        $uNewMsgRes = mysqli_query(
            $conn,
            "SELECT m.message_id AS id, m.*, CONCAT(u.first_name,' ',u.last_name) AS sender_name,
                    m.from_user AS sender_id
             FROM messages m
             JOIN users u ON u.user_id = m.from_user
             WHERE m.to_user = $userId
               AND m.created_at > '$sinceEsc'
             ORDER BY m.created_at ASC
             LIMIT 30"
        );
        $uNewMsgs = [];
        while ($r = mysqli_fetch_assoc($uNewMsgRes))
            $uNewMsgs[] = $r;
        fmt_dt_rows($uNewMsgs);
        if ($uNewMsgs)
            $payload['new_messages'] = $uNewMsgs;
    }

    // ── Live profile snapshot (for cross-page auto sync) ───────
    $profileRow = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT
            first_name,
            last_name,
            email,
            verification_status,
            profile_photo
         FROM users
         WHERE user_id = $userId
         LIMIT 1"
    ));
    if ($profileRow) {
        $payload['profile_sync'] = [
            'first_name' => (string) ($profileRow['first_name'] ?? ''),
            'last_name' => (string) ($profileRow['last_name'] ?? ''),
            'email' => (string) ($profileRow['email'] ?? ''),
            'verification_status' => (string) ($profileRow['verification_status'] ?? ''),
            'profile_photo' => (string) ($profileRow['profile_photo'] ?? ''),
        ];
    }

    // ── Live unit average ratings (from reviews) ───────────────
    $hasBookingReviews = false;
    if ($tbl = mysqli_query($conn, "SHOW TABLES LIKE 'booking_reviews'")) {
        $hasBookingReviews = mysqli_num_rows($tbl) > 0;
    }
    if ($hasBookingReviews) {
        $rtRes = mysqli_query(
            $conn,
            "SELECT br.unit_id,
                    ROUND(AVG(br.rating), 1) AS avg_rating,
                    COUNT(*) AS rating_count
             FROM booking_reviews br
             WHERE br.created_at > '$sinceEsc' OR br.updated_at > '$sinceEsc'
             GROUP BY br.unit_id
             ORDER BY MAX(COALESCE(br.updated_at, br.created_at)) DESC
             LIMIT 50"
        );
        $unitRatings = [];
        while ($rtRes && ($r = mysqli_fetch_assoc($rtRes))) {
            $unitRatings[] = [
                'unit_id' => (int) $r['unit_id'],
                'avg_rating' => $r['avg_rating'] !== null ? (float) $r['avg_rating'] : null,
                'rating_count' => (int) $r['rating_count'],
            ];
        }
        $payload['unit_ratings'] = $unitRatings;
    }
}

echo json_encode($payload);