<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ob_start();
header('Content-Type: application/json');
include '../../includes/session.php';
require_once '../../includes/db.php';
require_once '../../includes/rate_limiter.php';
require_not_blacklisted();

applyRateLimit($conn, 'user_api', 30, 3600);

require_once __DIR__ . '/../../includes/unit_status_sync.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'user')) {
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
require_verified_user_action(true);
require_csrf_token(true);

$user_id = $_SESSION['user_id'];

$check = $conn->prepare("
    SELECT booking_id 
    FROM bookings 
    WHERE user_id = ? 
    AND status IN ('pending','confirmed','active')
    LIMIT 1
");
$check->bind_param("i", $user_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'You already have an active booking.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$unitId = (int) ($_POST['unit_id'] ?? 0);
$checkin = trim($_POST['checkin'] ?? '');
$checkout = trim($_POST['checkout'] ?? '');
$guests = max(1, (int) ($_POST['guests'] ?? 1));
$paymentMethod = trim($_POST['payment_method'] ?? 'cash');

if (!$unitId || !$checkin || !$checkout) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing required booking details.']);
    exit;
}

$dtIn = DateTime::createFromFormat('Y-m-d', $checkin);
$dtOut = DateTime::createFromFormat('Y-m-d', $checkout);

if (!$dtIn || !$dtOut) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
    exit;
}
if ($dtOut <= $dtIn) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Check-out must be after check-in.']);
    exit;
}
if ($dtIn < new DateTime('today')) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Check-in date cannot be in the past.']);
    exit;
}

$nights = $dtIn->diff($dtOut)->days;
if ($nights < 1) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Minimum stay is 1 night.']);
    exit;
}
if ($guests > 10) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Maximum 10 guests allowed.']);
    exit;
}

try {
    $unitStmt = $conn->prepare(
        "SELECT u.unit_id, u.rent_amount, u.status, u.unit_name, u.unit_number, p.property_name
         FROM units u
         LEFT JOIN properties p ON p.property_id = u.property_id
         WHERE u.unit_id = ?
         LIMIT 1"
    );
    $unitStmt->bind_param('i', $unitId);
    $unitStmt->execute();
    $unit = $unitStmt->get_result()->fetch_assoc();
    $unitStmt->close();

    if (!$unit) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unit not found.']);
        exit;
    }

    if ($unit['status'] !== 'vacant') {
        $busyStmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM bookings
             WHERE unit_id = ?
               AND status IN ('pending','confirmed','active')
               AND checkout_date >= CURDATE()"
        );
        $busyStmt->bind_param('i', $unitId);
        $busyStmt->execute();
        $stillBusy = $busyStmt->get_result()->fetch_assoc();
        $busyStmt->close();

        if ((int) ($stillBusy['c'] ?? 0) > 0) {
            $reason = $unit['status'] === 'maintenance'
                ? 'This unit is currently under maintenance.'
                : 'This unit is not available for booking.';
            ob_clean();
            echo json_encode(['success' => false, 'message' => $reason]);
            exit;
        }
    }

    $conflictStmt = $conn->prepare(
        "SELECT booking_id FROM bookings
         WHERE unit_id = ?
           AND status NOT IN ('cancelled', 'completed')
           AND checkin_date < ?
           AND checkout_date > ?
         LIMIT 1"
    );
    $conflictStmt->bind_param('iss', $unitId, $checkout, $checkin);
    $conflictStmt->execute();
    $hasConflict = $conflictStmt->get_result()->fetch_assoc();
    $conflictStmt->close();

    if ($hasConflict) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'These dates are already booked. Please choose different dates.']);
        exit;
    }

    $totalAmount = $nights * (float) $unit['rent_amount'];
    $email = trim((string) ($_SESSION['email'] ?? ''));
    $fullName = trim((string) ($_SESSION['name'] ?? ''));

    $tenantStmt = $conn->prepare('SELECT tenant_id FROM tenants WHERE email = ? LIMIT 1');
    $tenantStmt->bind_param('s', $email);
    $tenantStmt->execute();
    $tenant = $tenantStmt->get_result()->fetch_assoc();
    $tenantStmt->close();

    if (!$tenant) {
        $newTenantStmt = $conn->prepare(
            'INSERT INTO tenants (full_name, email, move_in_date) VALUES (?, ?, ?)'
        );
        $newTenantStmt->bind_param('sss', $fullName, $email, $checkin);
        $newTenantStmt->execute();
        $tenantId = (int) $newTenantStmt->insert_id;
        $newTenantStmt->close();
    } else {
        $tenantId = (int) $tenant['tenant_id'];
    }

    $conn->begin_transaction();

    $bookingStmt = $conn->prepare(
        "INSERT INTO bookings
         (unit_id, tenant_id, user_id, checkin_date, checkout_date, guests, total_amount, payment_method, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $bookingStmt->bind_param('iiissids', $unitId, $tenantId, $userId, $checkin, $checkout, $guests, $totalAmount, $paymentMethod);
    $bookingStmt->execute();
    $bookingId = (int) $bookingStmt->insert_id;
    $bookingStmt->close();

    $tenantUpdateStmt = $conn->prepare(
        'UPDATE units SET tenant_name = ?, tenant_id = ? WHERE unit_id = ?'
    );
    $tenantUpdateStmt->bind_param('sii', $fullName, $tenantId, $unitId);
    $tenantUpdateStmt->execute();
    $tenantUpdateStmt->close();

    if (!syncUnitAvailabilityFromBookings($conn, $unitId)) {
        throw new \RuntimeException('Failed to sync unit availability.');
    }

    // Ensure the booked unit is explicitly marked occupied after successful reservation creation.
    // if (!mysqli_query($conn, "UPDATE units SET status='occupied' WHERE unit_id=$unitId AND status!='maintenance'")) {
    //     throw new \RuntimeException('Failed to mark unit occupied.');
    // }

    if ($paymentMethod === 'cash') {
        if (!mysqli_query($conn, "UPDATE units SET status='occupied' WHERE unit_id=$unitId AND status!='maintenance'")) {
            throw new \RuntimeException('Failed to mark unit occupied.');
        }
    }

    $conn->commit();

    $unitDisplay = !empty($unit['unit_name'])
        ? $unit['unit_name']
        : (($unit['property_name'] ?? '') . ' — Unit ' . ($unit['unit_number'] ?? $unitId));

    try {
        $bkRef = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
        $notifTitle = "New booking: $bkRef";
        $notifBody = "$fullName booked $unitDisplay · " .
            $dtIn->format('M j') . '–' . $dtOut->format('M j, Y') .
            " ($nights nights)";
        $notifLink = 'pages/admin/reservations.php';

        $admins = $conn->query("SELECT user_id FROM users WHERE role='admin' LIMIT 20");
        while ($adm = $admins->fetch_assoc()) {
            $adminId = (int) $adm['user_id'];
            $adminNotifStmt = $conn->prepare(
                "INSERT INTO notifications (user_id, type, title, body, link)
                 VALUES (?, 'booking', ?, ?, ?)"
            );
            $adminNotifStmt->bind_param('isss', $adminId, $notifTitle, $notifBody, $notifLink);
            $adminNotifStmt->execute();
            $adminNotifStmt->close();
        }

        $userNotifTitle = "Booking submitted: $bkRef";
        $userNotifBody = "Your booking for $unitDisplay (" .
            $dtIn->format('M j') . '–' . $dtOut->format('M j, Y') .
            ") is pending admin confirmation.";
        $userNotifLink = 'pages/user/bookings.php';
        $userNotifStmt = $conn->prepare(
            "INSERT INTO notifications (user_id, type, title, body, link)
             VALUES (?, 'booking', ?, ?, ?)"
        );
        $userNotifStmt->bind_param('isss', $userId, $userNotifTitle, $userNotifBody, $userNotifLink);
        $userNotifStmt->execute();
        $userNotifStmt->close();
    } catch (\Throwable $notifErr) {
        error_log('[book_unit.php] Notification failed (non-fatal): ' . $notifErr->getMessage());
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'booking_id' => $bookingId,
        'unit_id' => $unitId,
        'unit_name' => $unitDisplay,
        'property_name' => (string) ($unit['property_name'] ?? ''),
        'checkin' => $dtIn->format('Y-m-d'),
        'checkout' => $dtOut->format('Y-m-d'),
        'nights' => $nights,
        'guests' => $guests,
        'total_amount' => '₱' . number_format($totalAmount, 2),
        'status' => 'pending',
        'message' => 'Booking submitted successfully!',
    ]);

} catch (\Throwable $e) {
    if ($conn->in_transaction ?? false) {
        $conn->rollback();
    }
    error_log('[book_unit.php] Booking failed for user_id=' . $userId . ' unit_id=' . $unitId . ': ' . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Booking failed: ' . $e->getMessage()]);
}