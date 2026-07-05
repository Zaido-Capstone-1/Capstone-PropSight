<?php
/**
 * API: /endpoints/user/request_invoice_refund.php
 * POST — user submits a refund request for a paid PayMongo invoice
 *
 * POST params:
 *   invoice_id   int
 *   reason       string
 *   csrf_token   string
 *
 * Abuse protections:
 *   1. Blocks if a refund (any status) already exists for this invoice
 *   2. Blocks if invoice was paid more than REFUND_WINDOW_DAYS ago
 *   3. Blocks if user has submitted >= REFUND_DAILY_LIMIT invoice refunds in the last 24 hours
 *   4. CSRF token required
 *   5. Session + role check
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/email_templates.php';

// ── Config ────────────────────────────────────────────────────────────────────
const REFUND_WINDOW_DAYS = 30;  // max days after payment to request a refund
const REFUND_DAILY_LIMIT = 3;   // max invoice refund requests per user per 24 hours

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_csrf_token(true);

$userId = (int) $_SESSION['user_id'];
$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if (!$invoiceId || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Invoice ID and reason are required.']);
    exit;
}

if (mb_strlen($reason) < 10) {
    echo json_encode(['success' => false, 'message' => 'Please provide a more detailed reason (at least 10 characters).']);
    exit;
}

// ── Protection 3: Rate limit — max REFUND_DAILY_LIMIT requests per 24 hrs ────
$rateStmt = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM   refunds
    WHERE  user_id    = ?
      AND  invoice_id IS NOT NULL
      AND  created_at >= NOW() - INTERVAL 1 DAY
");
$rateStmt->bind_param('i', $userId);
$rateStmt->execute();
$rateRow = $rateStmt->get_result()->fetch_assoc();
$rateStmt->close();

if ((int) $rateRow['cnt'] >= REFUND_DAILY_LIMIT) {
    echo json_encode([
        'success' => false,
        'message' => 'You have submitted too many refund requests today. Please contact support if you need further assistance.'
    ]);
    exit;
}

// ── Protection 1: Block any existing refund for this invoice ─────────────────
// Covers: pending, processing, completed, rejected.
// Even a rejected refund blocks re-submission — user must contact support.
// This prevents gaming the system by resubmitting after rejection.
$dupStmt = $conn->prepare("
    SELECT refund_id, refund_status
    FROM   refunds
    WHERE  invoice_id = ?
      AND  user_id   = ?
    ORDER BY created_at DESC
    LIMIT  1
");
$dupStmt->bind_param('ii', $invoiceId, $userId);
$dupStmt->execute();
$dupRow = $dupStmt->get_result()->fetch_assoc();
$dupStmt->close();

if ($dupRow) {
    $existingStatus = $dupRow['refund_status'];
    $messages = [
        'pending' => 'You already have a pending refund request for this invoice. Please wait for admin review.',
        'processing' => 'Your refund for this invoice is already being processed.',
        'completed' => 'A refund has already been completed for this invoice.',
        'rejected' => 'Your previous refund request for this invoice was rejected. Please contact support directly if you wish to appeal.',
    ];
    echo json_encode([
        'success' => false,
        'message' => $messages[$existingStatus] ?? 'A refund request already exists for this invoice.'
    ]);
    exit;
}

// ── Verify invoice belongs to user, is paid, and has a PayMongo payment ──────
$stmt = $conn->prepare("
    SELECT  i.id            AS invoice_id,
            i.invoice_no,
            i.total,
            i.status        AS invoice_status,
            pp.id           AS pm_row_id,
            pp.paymongo_payment_id,
            pp.payment_method,
            pp.status       AS pm_status,
            COALESCE(pp.paid_at, pp.created_at) AS paid_at
    FROM    invoices i
    JOIN    tenants  t  ON  t.tenant_id = i.tenant_id
    JOIN    users    u  ON  u.email     = t.email
    JOIN    paymongo_payments pp
               ON  pp.reference_id   = i.id
               AND pp.reference_type = 'invoice'
               AND pp.status         = 'paid'
    WHERE   i.id     = ?
      AND   u.user_id = ?
      AND   i.status  = 'Paid'
    ORDER BY pp.paid_at DESC
    LIMIT 1
");
$stmt->bind_param('ii', $invoiceId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    // Give a specific error based on what's actually wrong
    $checkStmt = $conn->prepare("
        SELECT i.status AS inv_status,
               pp.status AS pm_status
        FROM   invoices i
        JOIN   tenants  t  ON t.tenant_id = i.tenant_id
        JOIN   users    u  ON u.email     = t.email
        LEFT JOIN paymongo_payments pp
                   ON pp.reference_id    = i.id
                   AND pp.reference_type = 'invoice'
        WHERE  i.id      = ?
          AND  u.user_id = ?
        LIMIT 1
    ");
    $checkStmt->bind_param('ii', $invoiceId, $userId);
    $checkStmt->execute();
    $chk = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$chk) {
        $msg = 'Invoice not found.';
    } elseif ($chk['inv_status'] !== 'Paid') {
        $msg = 'This invoice has not been paid yet and cannot be refunded.';
    } elseif ($chk['pm_status'] !== 'paid') {
        $msg = 'This invoice has no completed PayMongo payment on record. Only invoices paid online via PayMongo are eligible for refund.';
    } else {
        $msg = 'This invoice is not eligible for a refund.';
    }

    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ── Protection 2: Time window — must be within REFUND_WINDOW_DAYS of payment ─
$paidAt = strtotime($row['paid_at']);
$daysSincePaid = (time() - $paidAt) / 86400;

if ($daysSincePaid > REFUND_WINDOW_DAYS) {
    $paidFormatted = date('M j, Y', $paidAt);
    echo json_encode([
        'success' => false,
        'message' => "Refund requests must be submitted within " . REFUND_WINDOW_DAYS . " days of payment. "
            . "This invoice was paid on {$paidFormatted} (" . round($daysSincePaid) . " days ago). "
            . "Please contact support if you believe this is an error."
    ]);
    exit;
}

// ── All checks passed — insert refund request ─────────────────────────────────
$amount = (float) $row['total'];
$invoiceNo = $row['invoice_no'];
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
// payment_id is NULL for invoice refunds — FK now allows NULL after migration

$ins = $conn->prepare("
    INSERT INTO refunds
        (payment_id, booking_id, invoice_id, user_id,
         refund_amount, refund_reason, refund_status,
         refund_method, refund_date, created_at)
    VALUES (NULL, NULL, ?, ?, ?, ?, 'pending', ?, ?, NOW())
");
$ins->bind_param(
    'iidsss',
    $invoiceId,
    $userId,
    $amount,
    $reason,
    $method,
    $today
);

if (!$ins->execute()) {
    $ins->close();
    error_log('[request_invoice_refund] INSERT failed: ' . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Failed to submit refund request. Please try again.']);
    exit;
}
$ins->close();

// ── Fetch user info for notifications ─────────────────────────────────────────
$uStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ? LIMIT 1");
$uStmt->bind_param('i', $userId);
$uStmt->execute();
$uInfo = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

$userName = htmlspecialchars(trim(($uInfo['first_name'] ?? '') . ' ' . ($uInfo['last_name'] ?? '')));
$userEmail = $uInfo['email'] ?? '';
$amtFmt = '₱' . number_format($amount, 2);
$paidFmt = date('M j, Y', $paidAt);
$deadlineFmt = date('M j, Y', strtotime("+30 days", $paidAt));

// ── Notify admins (in-app) ────────────────────────────────────────────────────
$ntTitle = "Refund Request — Invoice {$invoiceNo}";
$ntBody = "{$userName} requested a refund of {$amtFmt} for invoice {$invoiceNo}.";
$ntLink = 'pages/admin/refunds.php';

$admins = $conn->query("SELECT user_id FROM users WHERE role='admin' AND is_active=1 LIMIT 20");
while ($adm = $admins->fetch_assoc()) {
    $aId = (int) $adm['user_id'];
    $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, ?)");
    $n->bind_param('isss', $aId, $ntTitle, $ntBody, $ntLink);
    $n->execute();
    $n->close();
}

// ── Confirmation email to user ─────────────────────────────────────────────
require_once __DIR__ . '/../../integrations/email_service.php';
if ($userEmail) {
    $userHtml = refund_email_html('received', [
        'name'      => $userName,
        'ref'       => $invoiceNo,
        'ref_label' => 'Invoice no.',
        'amount'    => $amtFmt,
        'method'    => $method,
        'date'      => $paidFmt,
        'reason'    => $reason,
    ]);
    try {
        $emailService->sendEmail($userEmail, "Refund request received — Invoice {$invoiceNo}", $userHtml);
    } catch (Throwable $e) {
        error_log('[request_invoice_refund] User email failed: ' . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'message' => "Refund request submitted for invoice {$invoiceNo}. We'll review it and get back to you within 1–2 business days."
]);