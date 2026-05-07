<?php
header('Content-Type: application/json');
require_once '../../includes/session.php';
require_once '../../includes/db.php';
require_once '../../includes/paymongo.php';
require_csrf_token(true);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id.']);
    exit;
}

// Fetch booking — must belong to this user and be pending
$stmt = $conn->prepare("
    SELECT b.booking_id, b.total_amount, b.status, b.unit_id,
           u.unit_number, pr.property_name
    FROM bookings b
    LEFT JOIN units u ON u.unit_id = b.unit_id
    LEFT JOIN properties pr ON pr.property_id = u.property_id
    WHERE b.booking_id = ? AND b.user_id = ? AND b.status IN ('pending', 'confirmed')
    LIMIT 1
");
$stmt->bind_param('ii', $bookingId, $userId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or already paid.']);
    exit;
}

// Check no existing unpaid link for this booking
$existing = $conn->prepare("
    SELECT paymongo_link_id FROM paymongo_payments
    WHERE booking_id = ? AND status NOT IN ('paid', 'expired')
    ORDER BY created_at DESC LIMIT 1
");
$existing->bind_param('i', $bookingId);
$existing->execute();
$existingRow = $existing->get_result()->fetch_assoc();
$existing->close();

if ($existingRow) {
    echo json_encode(['success' => false, 'message' => 'A payment link already exists for this booking.']);
    exit;
}

try {
    $description = sprintf(
        'PropSight — Booking #%d: %s Unit %s',
        $bookingId,
        $booking['property_name'],
        $booking['unit_number']
    );

    $link = paymongo_create_link(
        (int) $booking['total_amount'],
        $description,
        ['booking_id' => $bookingId, 'user_id' => $userId]
    );

    if (empty($link['id']) || empty($link['attributes']['checkout_url'])) {
        throw new Exception('Invalid PayMongo link response.');
    }

    $linkId = $link['id'];
    $checkoutUrl = $link['attributes']['checkout_url'];
    $status = 'pending';

    // Save to paymongo_payments table. The booking remains pending until PayMongo webhook marks it paid.
    $ins = $conn->prepare("
        INSERT INTO paymongo_payments (booking_id, user_id, paymongo_link_id, checkout_url, amount, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $amount = (float) $booking['total_amount'];
    $ins->bind_param('iissds', $bookingId, $userId, $linkId, $checkoutUrl, $amount, $status);
    $ins->execute();
    $ins->close();

    // Update booking payment_method only after link creation succeeded
    $conn->query("UPDATE bookings SET payment_method='paymongo' WHERE booking_id=$bookingId");

    echo json_encode([
        'success' => true,
        'checkout_url' => $checkoutUrl,
        'link_id' => $linkId,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}