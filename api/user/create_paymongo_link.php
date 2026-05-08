<?php
header('Content-Type: application/json');
require_once '../../includes/session.php';
require_once '../../includes/db.php';
require_once '../../includes/paymongo.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_csrf_token(true);

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT b.booking_id, b.total_amount, b.status, b.unit_id,
           COALESCE(u.unit_name, CONCAT(pr.property_name, ' — Unit ', u.unit_number)) AS unit_display,
           pr.property_name
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

// Check for existing active link
$existing = $conn->prepare("
    SELECT paymongo_link_id, status FROM paymongo_payments
    WHERE booking_id = ? AND status NOT IN ('paid', 'expired', 'failed')
    ORDER BY created_at DESC LIMIT 1
");
$existing->bind_param('i', $bookingId);
$existing->execute();
$existingRow = $existing->get_result()->fetch_assoc();
$existing->close();

if ($existingRow) {
    // Return the existing checkout URL instead of erroring
    $existingLink = $conn->prepare("SELECT checkout_url FROM paymongo_payments WHERE paymongo_link_id = ? LIMIT 1");
    $existingLink->bind_param('s', $existingRow['paymongo_link_id']);
    $existingLink->execute();
    $existingLinkRow = $existingLink->get_result()->fetch_assoc();
    $existingLink->close();

    if ($existingLinkRow && !empty($existingLinkRow['checkout_url'])) {
        echo json_encode([
            'success' => true,
            'checkout_url' => $existingLinkRow['checkout_url'],
            'link_id' => $existingRow['paymongo_link_id'],
        ]);
        exit;
    }
}

// Deposit = 50% of total
$depositAmount = (int) round((float) $booking['total_amount'] * 0.5);

if ($depositAmount < 100) {
    echo json_encode(['success' => false, 'message' => 'Amount too small for PayMongo (minimum ₱1.00).']);
    exit;
}

// Verify secret key is present before hitting the API
$secret = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
if (empty($secret)) {
    error_log('[create_paymongo_link] PAYMONGO_SECRET_KEY is not set.');
    echo json_encode(['success' => false, 'message' => 'Payment service is not configured. Please contact support.']);
    exit;
}

try {
    $description = sprintf(
        'PropSight Deposit — Booking #%d: %s',
        $bookingId,
        $booking['unit_display'] ?? $booking['property_name'] ?? 'Unit'
    );

    $link = paymongo_create_link(
        $depositAmount,
        $description,
        ['booking_id' => $bookingId, 'user_id' => $userId]
    );

    if (empty($link['id']) || empty($link['attributes']['checkout_url'])) {
        throw new Exception('Invalid PayMongo link response — missing id or checkout_url.');
    }

    $linkId = $link['id'];
    $checkoutUrl = $link['attributes']['checkout_url'];
    $status = 'pending';
    $amount = (float) $depositAmount;

    $ins = $conn->prepare("
        INSERT INTO paymongo_payments (booking_id, user_id, paymongo_link_id, checkout_url, amount, status, created_at, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))
    ");
    $ins->bind_param('iissds', $bookingId, $userId, $linkId, $checkoutUrl, $amount, $status);
    $ins->execute();
    $ins->close();

    $conn->query("UPDATE bookings SET payment_method='paymongo' WHERE booking_id=$bookingId");

    echo json_encode([
        'success' => true,
        'checkout_url' => $checkoutUrl,
        'link_id' => $linkId,
    ]);

} catch (Exception $e) {
    error_log('[create_paymongo_link] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}