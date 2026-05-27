<?php
/**
 * API: /api/user/request_refund.php
 * POST — user submits a refund request for a paid PayMongo booking
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

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
           b.status       AS booking_status
    FROM   paymongo_payments pp
    JOIN   bookings b  ON b.booking_id = pp.booking_id
    JOIN   payments py ON py.booking_id = pp.booking_id AND py.payment_method = 'paymongo'
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
$method = 'paymongo';
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

$admins = $conn->query("SELECT user_id, first_name, email FROM users WHERE role='admin' AND is_active=1 LIMIT 20");
while ($adm = $admins->fetch_assoc()) {
    // In-app notification
    $aId = (int) $adm['user_id'];
    $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, ?)");
    $n->bind_param('isss', $aId, $ntTitle, $ntBody, $ntLink);
    $n->execute();
    $n->close();


}

// ── 6. Confirmation email to user ─────────────────────────────────────────────
require_once __DIR__ . '/../../includes/email_service.php';
if ($userEmail) {
    $userHtml = "
    <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f8fafc;padding:32px 16px;'>
        <div style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);'>
            <div style='background:#1d4ed8;padding:28px 32px;'>
                <h1 style='color:#fff;margin:0;font-size:22px;font-weight:700;'>Refund Request Received</h1>
            </div>
            <div style='padding:28px 32px;'>
                <p style='color:#374151;font-size:15px;margin:0 0 20px;'>Hi {$userName},</p>
                <p style='color:#374151;font-size:15px;margin:0 0 20px;'>We've received your refund request and our team will review it within 1–2 business days.</p>
                <div style='background:#f1f5f9;border-radius:8px;padding:18px 20px;margin-bottom:20px;'>
                    <table style='width:100%;border-collapse:collapse;font-size:14px;color:#374151;'>
                        <tr><td style='padding:5px 0;color:#6b7280;'>Booking Ref</td><td style='text-align:right;font-weight:700;'>{$bkRef}</td></tr>
                        <tr><td style='padding:5px 0;color:#6b7280;'>Refund Amount</td><td style='text-align:right;font-weight:700;color:#1d4ed8;'>{$amtFmt}</td></tr>
                        <tr><td style='padding:5px 0;color:#6b7280;'>Status</td><td style='text-align:right;'>Pending Review</td></tr>
                    </table>
                </div>
                <p style='color:#6b7280;font-size:13px;margin:0;'>You'll receive another email once your request has been approved or rejected. If you have questions, please contact us.</p>
            </div>
        </div>
    </div>";

    try {
        $emailService->sendEmail($userEmail, "Refund Request Received — $bkRef", $userHtml);
    } catch (Throwable $e) {
        error_log('[request_refund] User confirmation email failed: ' . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Refund request submitted. We\'ll review it and get back to you within 1–2 business days.'
]);