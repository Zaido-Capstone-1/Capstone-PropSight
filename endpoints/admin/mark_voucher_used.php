<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$action = $_POST['action'] ?? 'check';
$voucher_code = strtoupper(trim($_POST['voucher_code'] ?? ''));

if (!$voucher_code) {
    echo json_encode(['success' => false, 'message' => 'Voucher code is required.']);
    exit;
}

// Look up the voucher
$stmt = $conn->prepare("
    SELECT lr.id, lr.reward_name, lr.points_used, lr.status, lr.created_at,
           u.first_name, u.last_name, u.email
    FROM loyalty_redemptions lr
    JOIN users u ON u.user_id = lr.user_id
    WHERE lr.voucher_code = ?
    LIMIT 1
");
$stmt->bind_param('s', $voucher_code);
$stmt->execute();
$voucher = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$voucher) {
    echo json_encode(['success' => false, 'message' => 'Voucher not found.']);
    exit;
}

if ($voucher['status'] === 'used') {
    echo json_encode(['success' => false, 'message' => 'This voucher has already been used.', 'voucher' => $voucher]);
    exit;
}

if ($voucher['status'] === 'expired') {
    echo json_encode(['success' => false, 'message' => 'This voucher has expired.', 'voucher' => $voucher]);
    exit;
}

// action=check → just return info, don't mark used
if ($action === 'check') {
    echo json_encode(['success' => true, 'action' => 'check', 'voucher' => $voucher]);
    exit;
}

// action=use → mark as used
if ($action === 'use') {
    $upd = $conn->prepare("UPDATE loyalty_redemptions SET status = 'used' WHERE voucher_code = ?");
    $upd->bind_param('s', $voucher_code);
    $upd->execute();
    $upd->close();

    echo json_encode(['success' => true, 'action' => 'used', 'voucher' => $voucher]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);