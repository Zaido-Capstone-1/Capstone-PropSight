<?php
/**
 * API: /api/booking_status.php
 * POST — update a booking's status (thin wrapper used by calendar/checkin pages)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/unit_status_sync.php';

$allowed_roles = ['admin', 'manager', 'frontdesk'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}
require_csrf_token();


$bookingId = (int)($_POST['booking_id'] ?? 0);
$newStatus = trim($_POST['status'] ?? '');
$allowed   = ['pending', 'confirmed', 'active', 'completed', 'cancelled'];

if (!$bookingId || !in_array($newStatus, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'booking_id and valid status required.']);
    exit;
}

$bkRow = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT unit_id, status, total_amount, payment_method FROM bookings WHERE booking_id=$bookingId LIMIT 1"));
if (!$bkRow) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    $statusEsc = mysqli_real_escape_string($conn, $newStatus);
    $confirmedAtSql = $newStatus === 'confirmed' ? ", confirmed_at = NOW()" : "";
    mysqli_query($conn, "UPDATE bookings SET status='$statusEsc'$confirmedAtSql WHERE booking_id=$bookingId");

    // Sync unit status
    $unitId = (int)$bkRow['unit_id'];
    if (!syncUnitAvailabilityFromBookings($conn, $unitId)) {
        throw new Exception('Failed to sync unit availability.');
    }

    // On confirmation: auto-create a pending payment for non-cash methods only.
    // Cash is excluded — admin must record it manually after receiving the money.
    if (in_array($newStatus, ['confirmed', 'active'])) {
        $payMethod = strtolower(trim($bkRow['payment_method'] ?? ''));
        $amt = (float)($bkRow['total_amount'] ?? 0);
        if ($amt > 0 && $payMethod !== 'cash' && $payMethod !== '') {
            $payExists = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT payment_id FROM payments WHERE booking_id=$bookingId LIMIT 1"));
            if (!$payExists) {
                $payMethodEsc = mysqli_real_escape_string($conn, $bkRow['payment_method']);
                mysqli_query($conn, "INSERT INTO payments
                    (booking_id, payment_date, amount_paid, payment_method, payment_status, notes)
                    VALUES ($bookingId, CURDATE(), $amt, '$payMethodEsc', 'pending', 'Auto-created on booking confirmation')");
            }
        }
    }

    // Auto-create income transaction on completion
    if ($newStatus === 'completed') {
        $amt = (float)($bkRow['total_amount'] ?? 0);
        if ($amt > 0) {
            // Mark any existing payment as paid
            mysqli_query($conn, "UPDATE payments SET payment_status='paid', payment_date=CURDATE()
                WHERE booking_id=$bookingId AND payment_status != 'paid'");

            $ref = 'TXN-BK-' . $bookingId;
            $exists = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id FROM transactions WHERE reference_no='$ref' LIMIT 1"));
            if (!$exists) {
                $propRow = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT property_id FROM units WHERE unit_id={$bkRow['unit_id']} LIMIT 1"));
                $propId = (int)($propRow['property_id'] ?? 0);
                $propIdSql = $propId > 0 ? $propId : 'NULL';
                mysqli_query($conn, "INSERT INTO transactions
                    (reference_no, description, category, type, amount, transaction_date, booking_id, property_id)
                    VALUES ('$ref', 'Booking #$bookingId payment', 'Room Revenue', 'Income', $amt, CURDATE(), $bookingId, $propIdSql)");
            }
        }
    }

    mysqli_commit($conn);

    $labels = [
        'confirmed' => 'Booking confirmed. Unit marked occupied.',
        'cancelled'  => 'Booking cancelled. Unit is now vacant.',
        'completed'  => 'Booking completed. Revenue recorded.',
        'active'     => 'Booking set to active.',
        'pending'    => 'Booking reset to pending.',
    ];

    echo json_encode([
        'success'    => true,
        'message'    => $labels[$newStatus],
        'new_status' => $newStatus,
        'booking_id' => $bookingId,
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
