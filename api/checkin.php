<?php
/**
 * API: /api/checkin.php
 * POST — process check-in or check-out for a booking
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/unit_status_sync.php';

if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$action    = trim($_POST['action'] ?? '');

if (!$bookingId || !in_array($action, ['checkin', 'checkout'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$booking = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM bookings WHERE booking_id=$bookingId LIMIT 1"));

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    if ($action === 'checkin') {
        if ($booking['checkin_status'] === 'done') {
            throw new Exception('Guest has already checked in.');
        }
        if (!mysqli_query($conn, "UPDATE bookings SET
            checkin_status='done', checkin_actual=NOW(), status='active'
            WHERE booking_id=$bookingId"))
            throw new Exception(mysqli_error($conn));

        if (!syncUnitAvailabilityFromBookings($conn, (int)$booking['unit_id']))
            throw new Exception('Failed to sync unit availability.');

        $msg = 'Guest checked in successfully.';
    } else {
        if ($booking['checkout_status'] === 'done') {
            throw new Exception('Guest has already checked out.');
        }
        if (!mysqli_query($conn, "UPDATE bookings SET
            checkout_status='done', checkout_actual=NOW(), status='completed'
            WHERE booking_id=$bookingId"))
            throw new Exception(mysqli_error($conn));

        if (!syncUnitAvailabilityFromBookings($conn, (int)$booking['unit_id']))
            throw new Exception('Failed to sync unit availability.');

        // Record income transaction on checkout
        $amt = (float)$booking['total_amount'];
        if ($amt > 0) {
            // Mark payment as paid
            mysqli_query($conn, "UPDATE payments SET payment_status='paid', payment_date=CURDATE()
                WHERE booking_id=$bookingId AND payment_status != 'paid'");

            $ref = 'TXN-BK-' . $bookingId;
            $txnExists = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id FROM transactions WHERE reference_no='$ref' LIMIT 1"));
            if (!$txnExists) {
                $propRow = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT u.property_id FROM units u
                     JOIN bookings b ON b.unit_id = u.unit_id
                     WHERE b.booking_id=$bookingId LIMIT 1"));
                $propId = (int)($propRow['property_id'] ?? 0);
                $propIdSql = $propId > 0 ? $propId : 'NULL';
                mysqli_query($conn, "INSERT INTO transactions
                    (reference_no, description, category, type, amount, transaction_date, booking_id, property_id)
                    VALUES ('$ref', 'Booking #$bookingId payment', 'Room Revenue', 'Income', $amt, CURDATE(), $bookingId, $propIdSql)");
            }
        }

        // Award loyalty points (1 point per PHP 10 spent)
        $userId = (int)$booking['user_id'];
        $amt    = (float)$booking['total_amount'];
        $pts    = max(1, (int)floor($amt / 10));
        $desc   = mysqli_real_escape_string($conn, "Booking #$bookingId stay completed");
        mysqli_query($conn, "INSERT INTO loyalty_points (user_id, points, type, description, booking_id)
            VALUES ($userId, $pts, 'earn', '$desc', $bookingId)");

        // Notification
        $notifBody = mysqli_real_escape_string($conn, "You earned $pts loyalty points from your stay!");
        mysqli_query($conn, "INSERT INTO notifications (user_id, type, title, body)
            VALUES ($userId, 'loyalty', 'Points Earned!', '$notifBody')");

        $msg = "Guest checked out. Unit is now vacant. $pts loyalty points awarded.";
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => $msg, 'action' => $action]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
