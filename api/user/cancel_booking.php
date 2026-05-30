<?php
/**
 * API: /api/user/cancel_booking.php
 * POST — cancel a booking owned by the current user
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/unit_status_sync.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'user')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_verified_user_action(true);
require_csrf_token(true);

$userId = (int) $_SESSION['user_id'];
$bookingId = (int) ($_POST['booking_id'] ?? 0);

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit;
}

$bookingStmt = $conn->prepare(
    'SELECT booking_id, unit_id, status, user_id, confirmed_at, updated_at FROM bookings WHERE booking_id = ? LIMIT 1'
);
$bookingStmt->bind_param('i', $bookingId);
$bookingStmt->execute();
$booking = $bookingStmt->get_result()->fetch_assoc();
$bookingStmt->close();

if (!$booking || (int) $booking['user_id'] !== $userId) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}
if (in_array($booking['status'], ['cancelled', 'completed'])) {
    echo json_encode([
        'success' => false,
        'message' => 'This booking is already ' . $booking['status'] . '.'
    ]);
    exit;
}
if ($booking['status'] === 'active') {
    echo json_encode([
        'success' => false,
        'message' => 'This booking cannot be cancelled — the guest is already checked in. Please contact support.'
    ]);
    exit;
}
if ($booking['status'] === 'confirmed') {
    $paidCheck = $conn->prepare("SELECT payment_id FROM payments WHERE booking_id = ? AND payment_status = 'paid' LIMIT 1");
    $paidCheck->bind_param('i', $bookingId);
    $paidCheck->execute();
    $hasPaid = $paidCheck->get_result()->fetch_assoc();
    $paidCheck->close();

    if ($hasPaid) {
        echo json_encode([
            'success' => false,
            'message' => 'This booking has an associated payment and cannot be cancelled automatically. Please contact support.'
        ]);
        exit;
    }

    $confirmedAt = !empty($booking['confirmed_at']) ? strtotime($booking['confirmed_at']) : strtotime($booking['updated_at']);
    $hoursSinceConfirmed = (time() - $confirmedAt) / 3600;
    if ($hoursSinceConfirmed > 48) {
        echo json_encode([
            'success' => false,
            'message' => 'The 48-hour cancellation window has passed for this confirmed booking. Please contact support.'
        ]);
        exit;
    }
}

mysqli_begin_transaction($conn);
try {
    $cancelStmt = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE booking_id = ?");
    $cancelStmt->bind_param('i', $bookingId);
    if (!$cancelStmt->execute()) {
        $cancelStmt->close();
        throw new Exception(mysqli_error($conn));
    }
    $cancelStmt->close();

    $unitId = (int) $booking['unit_id'];
    if (!syncUnitAvailabilityFromBookings($conn, $unitId))
        throw new Exception('Failed to sync unit availability.');

    // ── Fetch details for notifications ──────────────
    $infoStmt = $conn->prepare(
        "SELECT u.unit_name, u.unit_number, p.property_name,
                CONCAT(usr.first_name,' ',usr.last_name) AS guest_name
         FROM bookings b
         JOIN units u ON u.unit_id = b.unit_id
         LEFT JOIN properties p ON p.property_id = u.property_id
         JOIN users usr ON usr.user_id = b.user_id
         WHERE b.booking_id = ? LIMIT 1"
    );
    $infoStmt->bind_param('i', $bookingId);
    $infoStmt->execute();
    $bkInfo = $infoStmt->get_result()->fetch_assoc();
    $infoStmt->close();

    if ($bkInfo) {
        $bkRef = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
        $unitLabel = $bkInfo['unit_name']
            ?: (($bkInfo['property_name'] ?? '') . ' — Unit ' . ($bkInfo['unit_number'] ?? ''));
        $guestName = $bkInfo['guest_name'] ?? 'A guest';

        // Notify admins
        $ntTitle = "Booking cancelled: $bkRef";
        $ntBody = "$guestName cancelled their booking for $unitLabel.";
        $ntLink = 'pages/admin/reservations.php';
        $admins = mysqli_query($conn, "SELECT user_id FROM users WHERE role='admin' LIMIT 20");
        while ($adm = mysqli_fetch_assoc($admins)) {
            $aId = (int) $adm['user_id'];
            $adminNotifStmt = $conn->prepare(
                "INSERT INTO notifications (user_id,type,title,body,link)
                 VALUES (?, 'booking', ?, ?, ?)"
            );
            $adminNotifStmt->bind_param('isss', $aId, $ntTitle, $ntBody, $ntLink);
            $adminNotifStmt->execute();
            $adminNotifStmt->close();
        }

        // Confirm to the user
        $utTitle = "Booking cancelled: $bkRef";
        $utBody = "Your booking for $unitLabel has been cancelled.";
        $userNotifLink = 'pages/user/bookings.php';
        $userNotifStmt = $conn->prepare(
            "INSERT INTO notifications (user_id,type,title,body,link)
             VALUES (?, 'booking', ?, ?, ?)"
        );
        $userNotifStmt->bind_param('isss', $userId, $utTitle, $utBody, $userNotifLink);
        $userNotifStmt->execute();
        $userNotifStmt->close();
    }

    mysqli_commit($conn);
    echo json_encode([
        'success' => true,
        'message' => 'Your booking has been cancelled. The unit is now available.'
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
