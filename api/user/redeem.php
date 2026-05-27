<?php
/**
 * API: /api/user/redeem.php
 * POST — redeem a loyalty reward
 *        Saves to loyalty_points (deduction) and loyalty_redemptions (record).
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
$name = trim($_POST['reward_name'] ?? 'Reward');

if ($pts <= 0 || $rewardId <= 0 || $name === '') {
    echo json_encode(['success' => false, 'message' => 'reward_id, reward_name, and points are required.']);
    exit;
}

/* ── Validate reward exists in DB catalogue ── */
$catStmt = $conn->prepare(
    "SELECT reward_id, name, points_cost FROM loyalty_rewards WHERE reward_id = ? AND is_active = 1 LIMIT 1"
);
$catStmt->bind_param('i', $rewardId);
$catStmt->execute();
$reward = $catStmt->get_result()->fetch_assoc();
$catStmt->close();

if (!$reward) {
    echo json_encode(['success' => false, 'message' => 'Invalid or inactive reward.']);
    exit;
}

// Authoritative points cost from DB — ignore client-supplied value to prevent tampering
$authoritative_pts = (int) $reward['points_cost'];
$name = $reward['name']; // use DB name, not client-supplied
if ($pts !== $authoritative_pts) {
    echo json_encode(['success' => false, 'message' => 'Points mismatch. Please refresh and try again.']);
    exit;
}

/* ── Check current balance using prepared statement ── */
$balStmt = $conn->prepare('SELECT COALESCE(SUM(points), 0) AS bal FROM loyalty_points WHERE user_id = ?');
$balStmt->bind_param('i', $userId);
$balStmt->execute();
$balance = max(0, (int) $balStmt->get_result()->fetch_assoc()['bal']);
$balStmt->close();

if ($balance < $pts) {
    echo json_encode([
        'success' => false,
        'message' => "Not enough points. You have {$balance} pts but need {$pts} pts.",
        'balance' => $balance,
    ]);
    exit;
}

/* ── Generate voucher code ── */
$voucher_code = 'PS-R' . str_pad($rewardId, 2, '0', STR_PAD_LEFT)
    . '-' . strtoupper(substr(base_convert(time(), 10, 36), -4))
    . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

/* ── Begin transaction ── */
$conn->begin_transaction();
try {
    // 1. Record point deduction
    $deduction = -$pts;
    $desc = "Redeemed: {$name}";
    $deductStmt = $conn->prepare(
        "INSERT INTO loyalty_points (user_id, points, type, description) VALUES (?, ?, 'redeem', ?)"
    );
    $deductStmt->bind_param('iis', $userId, $deduction, $desc);
    $deductStmt->execute();
    $deductStmt->close();

    // 2. Record in loyalty_redemptions (create table if missing)
    $conn->query("
        CREATE TABLE IF NOT EXISTS loyalty_redemptions (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id       INT UNSIGNED NOT NULL,
            reward_id     TINYINT UNSIGNED NOT NULL,
            reward_name   VARCHAR(100) NOT NULL,
            points_used   SMALLINT UNSIGNED NOT NULL,
            voucher_code  VARCHAR(32) NOT NULL,
            status        ENUM('active','used','expired') NOT NULL DEFAULT 'active',
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_voucher (voucher_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $redemptionStmt = $conn->prepare(
        "INSERT INTO loyalty_redemptions (user_id, reward_id, reward_name, points_used, voucher_code)
         VALUES (?, ?, ?, ?, ?)"
    );
    $redemptionStmt->bind_param('iisis', $userId, $rewardId, $name, $pts, $voucher_code);
    $redemptionStmt->execute();
    $redemptionStmt->close();

    $conn->commit();

    $newBalance = $balance - $pts;
    echo json_encode([
        'success' => true,
        'message' => "\"{$name}\" redeemed successfully!",
        'new_balance' => $newBalance,
        'points_used' => $pts,
        'voucher_code' => $voucher_code,
        'reward_id' => $rewardId,
        'reward_name' => $name,
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log('Redemption error user_id=' . $userId . ': ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Redemption failed. Please try again.']);
}