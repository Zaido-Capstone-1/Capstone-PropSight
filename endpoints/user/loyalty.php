<?php
/**
 * API: /endpoints/user/loyalty.php
 * GET  — returns points balance, tier, history, rewards
 * POST — redeem a reward
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_not_blacklisted();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

function getTier(int $pts): array
{
    if ($pts >= 5000)
        return ['name' => 'Diamond', 'next' => null, 'min' => 5000, 'max' => null, 'icon' => '👑'];
    if ($pts >= 2000)
        return ['name' => 'Platinum', 'next' => 'Diamond', 'min' => 2000, 'max' => 4999, 'icon' => '💎'];
    if ($pts >= 500)
        return ['name' => 'Gold', 'next' => 'Platinum', 'min' => 500, 'max' => 1999, 'icon' => '🥇'];
    return ['name' => 'Silver', 'next' => 'Gold', 'min' => 0, 'max' => 499, 'icon' => '🥈'];
}

function syncCompletedBookingPoints(mysqli $conn, int $userId): void
{
    $stmt = $conn->prepare("
        SELECT b.booking_id, b.total_amount
        FROM bookings b
        LEFT JOIN loyalty_points lp
               ON lp.booking_id = b.booking_id
              AND lp.user_id = b.user_id
              AND lp.type = 'earn'
        WHERE b.user_id = ?
          AND b.status = 'completed'
          AND lp.id IS NULL
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res)
        return;

    while ($row = mysqli_fetch_assoc($res)) {
        $bookingId = (int) ($row['booking_id'] ?? 0);
        $amount = (float) ($row['total_amount'] ?? 0);
        if ($bookingId <= 0)
            continue;

        $pts = max(1, (int) floor($amount / 10)); // PHP 10 = 1 point
        $desc = "Booking #$bookingId stay completed";
        $ins = $conn->prepare(
            "INSERT INTO loyalty_points (user_id, points, type, description, booking_id)
             VALUES (?, ?, 'earn', ?, ?)"
        );
        $ins->bind_param('iisi', $userId, $pts, $desc, $bookingId);
        $ins->execute();
        $ins->close();
    }
    $stmt->close();
}

if ($method === 'GET') {
    // Auto-heal: ensure all completed bookings have earned points.
    syncCompletedBookingPoints($conn, $userId);

    // Calculate total balance
    $balStmt = $conn->prepare('SELECT COALESCE(SUM(points), 0) AS bal FROM loyalty_points WHERE user_id = ?');
    $balStmt->bind_param('i', $userId);
    $balStmt->execute();
    $balRes = $balStmt->get_result()->fetch_assoc();
    $balStmt->close();
    $balance = max(0, (int) $balRes['bal']);

    $tier = getTier($balance);
    $ptsToNext = $tier['next'] ? ($tier['max'] + 1 - $balance) : 0;

    // History
    $histStmt = $conn->prepare("
        SELECT lp.id, lp.points, lp.type, lp.description, lp.created_at,
               b.checkin_date, b.checkout_date,
               COALESCE(u.unit_name, u.unit_number,'—') AS unit_label,
               p.property_name
        FROM loyalty_points lp
        LEFT JOIN bookings   b ON b.booking_id  = lp.booking_id
        LEFT JOIN units      u ON u.unit_id      = b.unit_id
        LEFT JOIN properties p ON p.property_id  = u.property_id
        WHERE lp.user_id=?
        ORDER BY lp.created_at DESC
        LIMIT 30
    ");
    $histStmt->bind_param('i', $userId);
    $histStmt->execute();
    $histRes = $histStmt->get_result();
    $history = [];
    while ($row = mysqli_fetch_assoc($histRes)) {
        fmt_dt_row($row);
        $history[] = $row;
    }
    $histStmt->close();

    // Rewards catalogue from DB (active only)
    $rwStmt = $conn->query(
        "SELECT reward_id AS id, name, description AS `desc`, points_cost AS pts FROM loyalty_rewards WHERE is_active = 1 ORDER BY pts ASC"
    );
    $rewards = [];
    while ($rw = $rwStmt->fetch_assoc())
        $rewards[] = $rw;

    // Vouchers — get_result() called once, not inside while condition
    $voucherStmt = $conn->prepare("
        SELECT reward_name, voucher_code, points_used, status, created_at
        FROM loyalty_redemptions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $voucherStmt->bind_param('i', $userId);
    $voucherStmt->execute();
    $voucherRes = $voucherStmt->get_result();
    $vouchers = [];
    while ($row = $voucherRes->fetch_assoc())
        $vouchers[] = $row;
    $voucherStmt->close();

    echo json_encode([
        'success' => true,
        'balance' => $balance,
        'tier' => $tier,
        'pts_to_next' => $ptsToNext,
        'history' => $history,
        'rewards' => $rewards,
        'vouchers' => $vouchers,
    ]);
    exit;
}

if ($method === 'POST') {
    require_verified_user_action(true);
    require_csrf_token(true);
    $action = $_POST['action'] ?? 'redeem';
    $pts = (int) ($_POST['points'] ?? 0);
    $name = trim($_POST['reward_name'] ?? 'Reward');

    if ($action === 'redeem') {
        if ($pts <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid points.']);
            exit;
        }

        // Check balance
        $balStmt = $conn->prepare('SELECT COALESCE(SUM(points),0) AS bal FROM loyalty_points WHERE user_id = ?');
        $balStmt->bind_param('i', $userId);
        $balStmt->execute();
        $balRes = $balStmt->get_result()->fetch_assoc();
        $balStmt->close();
        $balance = max(0, (int) $balRes['bal']);

        if ($balance < $pts) {
            echo json_encode(['success' => false, 'message' => 'Insufficient points.']);
            exit;
        }

        $deduction = -$pts;
        $desc = "Redeemed: $name";
        $redeemStmt = $conn->prepare(
            "INSERT INTO loyalty_points (user_id, points, type, description)
             VALUES (?, ?, 'redeem', ?)"
        );
        $redeemStmt->bind_param('iis', $userId, $deduction, $desc);
        if ($redeemStmt->execute()) {
            $newBal = $balance - $pts;
            echo json_encode([
                'success' => true,
                'message' => "\"$name\" redeemed successfully!",
                'new_balance' => $newBal,
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        $redeemStmt->close();
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}