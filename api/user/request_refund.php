<?php
/**
 * API: /api/user/request_refund.php
 * POST — user submits a refund request for a paid PayMongo booking
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/email_templates.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_csrf_token(true);

$userId = (int) $_SESSION['user_id'];
$bookingId = (int) ($_POST['booking_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if (!$bookingId || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Booking ID and reason are required.']);
    exit;
}

// ── 1. Verify booking belongs to user and was paid via PayMongo ──────────────
$stmt = $conn->prepare("
    SELECT pp.id          AS pm_id,
       pp.amount,
       py.payment_id,
       py.payment_method,
       b.status       AS booking_status
    FROM   paymongo_payments pp
    JOIN   bookings b  ON b.booking_id = pp.booking_id
    JOIN   payments py ON py.booking_id = pp.booking_id AND py.payment_method IN ('GCash', 'Maya', 'Bank Transfer')
    WHERE  pp.booking_id = ?
      AND  b.user_id     = ?
      AND  pp.status     = 'paid'
      AND  b.status      = 'cancelled'
    ORDER  BY pp.paid_at DESC
    LIMIT  1
");
$stmt->bind_param('ii', $bookingId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $check = $conn->prepare("
        SELECT pp.status AS pm_status, b.status AS bk_status
        FROM   paymongo_payments pp
        JOIN   bookings b ON b.booking_id = pp.booking_id
        WHERE  pp.booking_id = ? AND b.user_id = ?
        LIMIT  1
    ");
    $check->bind_param('ii', $bookingId, $userId);
    $check->execute();
    $chk = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$chk) {
        $msg = 'Booking not found.';
    } elseif ($chk['pm_status'] !== 'paid') {
        $msg = 'This booking has no completed PayMongo payment to refund.';
    } elseif ($chk['bk_status'] !== 'cancelled') {
        $msg = 'Your booking must be cancelled before a refund can be requested.';
    } else {
        $msg = 'This booking is not eligible for a refund.';
    }

    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ── 2. Block duplicate pending requests ──────────────────────────────────────
$dup = $conn->prepare("
    SELECT refund_id FROM refunds
    WHERE booking_id = ? AND user_id = ? AND refund_status IN ('pending', 'processing')
    LIMIT 1
");
$dup->bind_param('ii', $bookingId, $userId);
$dup->execute();
$dupRow = $dup->get_result()->fetch_assoc();
$dup->close();

if ($dupRow) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending refund request for this booking.']);
    exit;
}

// ── 3. Insert refund request ──────────────────────────────────────────────────
$amount = (float) $row['amount'];
$paymentId = (int) $row['payment_id'];
$rawMethod = strtolower(trim($row['payment_method'] ?? ''));
$method = match ($rawMethod) {
    'gcash'                      => 'GCash',
    'paymaya', 'maya'            => 'Maya',
    'card'                       => 'Card',
    'dob', 'online_banking',
    'bank_transfer', 'qrph'      => 'Bank Transfer',
    default                      => ucfirst($row['payment_method'] ?? 'PayMongo'),
};
$today = date('Y-m-d');

$ins = $conn->prepare("
    INSERT INTO refunds (payment_id, booking_id, user_id, refund_amount, refund_reason,
                         refund_status, refund_method, refund_date, created_at)
    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
");
$ins->bind_param('iiidsss', $paymentId, $bookingId, $userId, $amount, $reason, $method, $today);
if (!$ins->execute()) {
    $ins->close();
    echo json_encode(['success' => false, 'message' => 'Failed to submit refund request. Please try again.']);
    exit;
}
$ins->close();

// ── 4. Fetch user info for emails ─────────────────────────────────────────────
$bkRef = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
$amtFmt = '₱' . number_format($amount, 2);

$uStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ? LIMIT 1");
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$uInfo = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$userName = htmlspecialchars(trim(($uInfo['first_name'] ?? '') . ' ' . ($uInfo['last_name'] ?? '')));
$userEmail = $uInfo['email'] ?? '';

// ── 5. Notify admins (in-app + email) ────────────────────────────────────────
$ntTitle = "Refund Request — $bkRef";
$ntBody = "A guest has requested a refund of $amtFmt for booking $bkRef.";
$ntLink = 'pages/admin/refunds.php';

require_once __DIR__ . '/../../includes/admin_notif_helpers.php';
$admins = $conn->query("SELECT user_id, first_name, email FROM users WHERE role='admin' AND is_active=1 LIMIT 20");
while ($adm = $admins->fetch_assoc()) {
    $aId = (int) $adm['user_id'];
    $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, ?)");
    $n->bind_param('isss', $aId, $ntTitle, $ntBody, $ntLink);
    $n->execute();
    $n->close();
    upsert_notif(
        $conn,
        $aId,
        'refund',
        'refund-' . $bookingId,
        $ntTitle . ': ' . mb_substr($ntBody, 0, 80),
        'refunds.php',
        gmdate('Y-m-d H:i:s')
    );
}

// ── 6. Confirmation email to user ─────────────────────────────────────────
require_once __DIR__ . '/../../includes/email_service.php';
if ($userEmail) {
    $userHtml = refund_email_html('received', [
        'name'      => $userName,
        'ref'       => $bkRef,
        'ref_label' => 'Booking ref',
        'amount'    => $amtFmt,
        'method'    => $method,
        'reason'    => $reason,
    ]);
    try {
        $emailService->sendEmail($userEmail, "Refund request received — {$bkRef}", $userHtml);
    } catch (Throwable $e) {
        error_log('[request_refund] User confirmation email failed: ' . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Refund request submitted. We\'ll review it and get back to you within 1–2 business days.'
]);