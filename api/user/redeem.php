<?php
/**
 * API: /api/user/redeem.php
 * POST — redeem a loyalty reward
 *        Delegates to loyalty.php POST logic (separate endpoint as listed in api/index.php)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
require_verified_user_action(true);
require_csrf_token(true);
$rewardId = (int) ($_POST['reward_id'] ?? 0);
$pts = (int) ($_POST['points'] ?? 0);
$name = mysqli_real_escape_string($conn, trim($_POST['reward_name'] ?? 'Reward'));

if ($pts <= 0 || !$rewardId) {
    echo json_encode(['success' => false, 'message' => 'reward_id and points are required.']);
    exit;
}

// Check current balance
$balRes = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(points),0) AS bal FROM loyalty_points WHERE user_id=$userId"
));
$balance = max(0, (int) $balRes['bal']);

if ($balance < $pts) {
    echo json_encode([
        'success' => false,
        'message' => "Not enough points. You have $balance pts, need $pts.",
        'balance' => $balance,
    ]);
    exit;
}

// Record redemption
$deduction = -$pts;
$desc = mysqli_real_escape_string($conn, "Redeemed: $name");
$sql = "INSERT INTO loyalty_points (user_id, points, type, description)
        VALUES ($userId, $deduction, 'redeem', '$desc')";

if (mysqli_query($conn, $sql)) {
    // Also log in loyalty_redemptions if that table exists
    $tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'loyalty_redemptions'");
    if (mysqli_num_rows($tableCheck) > 0) {
        mysqli_query(
            $conn,
            "INSERT INTO loyalty_redemptions (user_id, reward_name, points_used, created_at)
             VALUES ($userId, '$name', $pts, NOW())"
        );
    }

    $newBalance = $balance - $pts;
    echo json_encode([
        'success' => true,
        'message' => "\"$name\" redeemed successfully!",
        'new_balance' => $newBalance,
        'points_used' => $pts,
    ]);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
}