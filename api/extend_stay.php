<?php
/**
 * API: /api/extend_stay.php
 * POST — extend a guest's checkout date
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$bookingId  = (int)($_POST['booking_id'] ?? 0);
$newCheckout = trim($_POST['new_checkout'] ?? '');

if (!$bookingId || !$newCheckout || !strtotime($newCheckout)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newCheckout)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit;
}

$newCheckoutEsc = mysqli_real_escape_string($conn, $newCheckout);

$booking = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM bookings WHERE booking_id=$bookingId LIMIT 1"));

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

if (in_array($booking['status'], ['cancelled', 'completed'])) {
    echo json_encode(['success' => false, 'message' => 'Cannot extend a ' . $booking['status'] . ' booking.']);
    exit;
}

// New checkout must be after current checkout
if ($newCheckout <= $booking['checkout_date']) {
    echo json_encode(['success' => false, 'message' => 'New check-out date must be after the current check-out date (' . $booking['checkout_date'] . ').']);
    exit;
}

// Check for conflicting bookings on the same unit
$unitId = (int)$booking['unit_id'];
$currentCheckout = mysqli_real_escape_string($conn, $booking['checkout_date']);
$conflict = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT booking_id FROM bookings
     WHERE unit_id = $unitId
       AND booking_id != $bookingId
       AND status NOT IN ('cancelled','completed')
       AND checkin_date < '$newCheckoutEsc'
       AND checkout_date > '$currentCheckout'
     LIMIT 1"
));

if ($conflict) {
    echo json_encode(['success' => false, 'message' => 'Another booking (#BK-' . str_pad($conflict['booking_id'], 4, '0', STR_PAD_LEFT) . ') conflicts with the new check-out date.']);
    exit;
}

// Recalculate nights and total amount
$oldNights = (int)(( strtotime($booking['checkout_date']) - strtotime($booking['checkin_date']) ) / 86400);
$newNights = (int)(( strtotime($newCheckout) - strtotime($booking['checkin_date']) ) / 86400);
$extraNights = $newNights - $oldNights;

$newTotal = $oldNights > 0
    ? round(($booking['total_amount'] / $oldNights) * $newNights, 2)
    : $booking['total_amount'];

mysqli_begin_transaction($conn);
try {
    if (!mysqli_query($conn, "UPDATE bookings
        SET checkout_date = '$newCheckoutEsc',
            total_amount  = $newTotal
        WHERE booking_id = $bookingId"))
        throw new Exception(mysqli_error($conn));

    $extraLabel = $extraNights === 1 ? '1 extra night' : "$extraNights extra nights";
    mysqli_commit($conn);
    echo json_encode([
        'success'  => true,
        'message'  => "Stay extended by $extraLabel. New check-out: " . date('M j, Y', strtotime($newCheckout)) . '.',
        'new_checkout' => $newCheckout,
        'new_total'    => $newTotal,
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
