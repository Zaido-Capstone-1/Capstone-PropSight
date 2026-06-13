<?php
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

// Block booking if ID is not approved
$idStatus = $_SESSION['id_verified'] ?? 'none';
if ($idStatus !== 'approved') {
    ob_clean();
    $msg = match ($idStatus) {
        'pending' => 'Your ID is still under review. You can book once it\'s approved.',
        'rejected' => 'Your ID verification was rejected. Please re-upload a valid government ID.',
        default => 'You need to verify your identity before booking. Please upload a valid government ID on your profile.',
    };
    echo json_encode(['success' => false, 'message' => $msg, 'id_gate' => true, 'id_status' => $idStatus]);
    exit;
}

$user_id = $_SESSION['user_id'];

// ── Advisory lock: prevents duplicate rows from simultaneous requests ──────
$lockName = 'book_unit_user_' . $user_id;
$lockResult = $conn->query("SELECT GET_LOCK('" . $conn->real_escape_string($lockName) . "', 5)");
$lockRow = $lockResult ? $lockResult->fetch_row() : null;
if (!$lockRow || !$lockRow[0]) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Could not acquire booking lock. Please try again.']);
    exit;
}
// Ensure lock is released on any exit path
register_shutdown_function(function () use ($conn, $lockName) {
    $conn->query("SELECT RELEASE_LOCK('" . $conn->real_escape_string($lockName) . "')");
});

$check = $conn->prepare("
    SELECT booking_id, checkin_date, checkout_date, status, unit_id
    FROM bookings 
    WHERE user_id = ? 
    AND status IN ('pending','confirmed','active')
    LIMIT 1
");
$check->bind_param("i", $user_id);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    $incomingUnitId = (int) ($_POST['unit_id'] ?? 0);
    $incomingCheckin = trim($_POST['checkin'] ?? '');
    $incomingCheckout = trim($_POST['checkout'] ?? '');

    // If same unit + same dates + still pending → reuse it, don't create a duplicate
    if (
        (int) $existing['unit_id'] === $incomingUnitId &&
        $existing['checkin_date'] === $incomingCheckin &&
        $existing['checkout_date'] === $incomingCheckout &&
        $existing['status'] === 'pending'
    ) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'booking_id' => (int) $existing['booking_id'],
            'reused' => true,
        ]);
        exit;
    }

    // Different unit/dates or already confirmed/active → block
    $bkRef = 'BK-' . str_pad($existing['booking_id'], 6, '0', STR_PAD_LEFT);
    $bkIn = date('M j, Y', strtotime($existing['checkin_date']));
    $bkOut = date('M j, Y', strtotime($existing['checkout_date']));
    $bkStatus = ucfirst($existing['status']);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => "You already have an active booking ($bkRef · $bkStatus: $bkIn – $bkOut). Please complete or cancel it before making a new one.",
    ]);
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

    // Block maintenance units entirely; booked/occupied can accept future bookings
    if ($unit['status'] === 'maintenance') {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'This unit is currently under maintenance.']);
        exit;
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

    $clientTotal = (float) ($_POST['total_amount'] ?? 0);
    $baseTotal = $nights * (float) $unit['rent_amount'];
    // Accept client total if within 2x of base (covers Peak season), else fall back
    $totalAmount = ($clientTotal > 0 && $clientTotal <= $baseTotal * 2.5)
        ? $clientTotal
        : $baseTotal;
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

    // If checkin is today, mark occupied immediately; otherwise mark booked
    $today = new DateTime('today');
    if ($dtIn <= $today) {
        mysqli_query($conn, "UPDATE units SET status='occupied' WHERE unit_id=$unitId AND status!='maintenance'");
    } else {
        mysqli_query($conn, "UPDATE units SET status='booked' WHERE unit_id=$unitId AND status!='maintenance'");
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

        require_once __DIR__ . '/../../includes/admin_notif_helpers.php';
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
            upsert_notif(
                $conn,
                $adminId,
                'new_booking',
                'booking-' . $bookingId,
                $notifTitle . ': ' . mb_substr($notifBody, 0, 80),
                'reservations.php?status=pending',
                gmdate('Y-m-d H:i:s')
            );
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