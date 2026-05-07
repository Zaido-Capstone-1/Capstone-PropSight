<?php
header('Content-Type: application/json');
require_once '../../includes/session.php';
require_once '../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$bookingId = (int) ($_GET['booking_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT pp.status AS payment_status, b.status AS booking_status
    FROM paymongo_payments pp
    JOIN bookings b ON b.booking_id = pp.booking_id
    WHERE pp.booking_id = ? AND b.user_id = ?
    ORDER BY pp.created_at DESC
    LIMIT 1
");
$stmt->bind_param('ii', $bookingId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => true, 'payment_status' => 'pending', 'booking_status' => 'pending']);
    exit;
}

echo json_encode([
    'success' => true,
    'payment_status' => $row['payment_status'],
    'booking_status' => $row['booking_status'],
]);