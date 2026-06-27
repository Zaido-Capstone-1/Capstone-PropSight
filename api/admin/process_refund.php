<?php
/**
 * API: /api/admin/process_refund.php
 * POST — admin approves or rejects a refund request (booking or invoice)
 *
 * POST params:
 *   refund_id   int
 *   action      'approve' | 'reject'
 *   reason      string (required when action=reject)
 *   csrf_token  string
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/paymongo.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_csrf_token(true);

$adminId  = (int) $_SESSION['user_id'];
$refundId = (int) ($_POST['refund_id'] ?? 0);
$action   = trim($_POST['action'] ?? '');
$reason   = trim($_POST['reason'] ?? '');

if (!$refundId || !in_array($action, ['approve', 'reject'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if ($action === 'reject' && !$reason) {
    echo json_encode(['success' => false, 'message' => 'A rejection reason is required.']);
    exit;
}

// ── 1. Fetch the refund record ────────────────────────────────────────────────
// Handles both booking refunds (pp.booking_id) and invoice refunds (pp.reference_id)
// COALESCE picks whichever JOIN finds a matching paid paymongo_payments row.
$stmt = $conn->prepare("
    SELECT  r.refund_id,
            r.booking_id,
            r.invoice_id,
            r.payment_id,
            r.user_id,
            r.refund_amount,
            r.refund_status,
            COALESCE(pp_bk.paymongo_payment_id, pp_inv.paymongo_payment_id) AS paymongo_payment_id,
            u.first_name,
            u.last_name,
            u.email
    FROM    refunds r
    JOIN    users u ON u.user_id = r.user_id
    LEFT JOIN paymongo_payments pp_bk
           ON  pp_bk.booking_id = r.booking_id
           AND pp_bk.status     = 'paid'
           AND r.booking_id     IS NOT NULL
    LEFT JOIN paymongo_payments pp_inv
           ON  pp_inv.reference_id   = r.invoice_id
           AND pp_inv.reference_type = 'invoice'
           AND pp_inv.status         = 'paid'
           AND r.invoice_id          IS NOT NULL
    WHERE   r.refund_id = ?
    LIMIT   1
");
$stmt->bind_param('i', $refundId);
$stmt->execute();
$refund = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$refund) {
    echo json_encode(['success' => false, 'message' => 'Refund request not found.']);
    exit;
}

if ($refund['refund_status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'This refund has already been processed.']);
    exit;
}

$userId    = (int) $refund['user_id'];
$bookingId = (int) ($refund['booking_id'] ?? 0);
$invoiceId = (int) ($refund['invoice_id'] ?? 0);
$amount    = (float) $refund['refund_amount'];
$amtFmt    = '₱' . number_format($amount, 2);
$userName  = htmlspecialchars(trim($refund['first_name'] . ' ' . $refund['last_name']));
$userEmail = $refund['email'] ?? '';
$today     = date('Y-m-d');

// Build a human-readable reference for notifications and emails
if ($invoiceId) {
    // Fetch invoice number for display
    $invStmt = $conn->prepare("SELECT invoice_no FROM invoices WHERE id = ? LIMIT 1");
    $invStmt->bind_param('i', $invoiceId);
    $invStmt->execute();
    $invRow = $invStmt->get_result()->fetch_assoc();
    $invStmt->close();
    $refRef    = $invRow['invoice_no'] ?? "INV-$invoiceId";
    $refLabel  = "invoice $refRef";
    $notesLabel = "invoice $refRef";
} else {
    $refRef    = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
    $refLabel  = "booking $refRef";
    $notesLabel = "booking $refRef";
}

// ── 2A. APPROVE ───────────────────────────────────────────────────────────────
if ($action === 'approve') {

    $pmPaymentId = $refund['paymongo_payment_id'] ?? '';

    if ($pmPaymentId) {
        try {
            paymongo_request('POST', '/refunds', [
                'amount'     => (int) round($amount * 100),
                'payment_id' => $pmPaymentId,
                'reason'     => 'others',
                'notes'      => "Admin approved refund for $notesLabel",
            ]);
        } catch (Throwable $e) {
            error_log('[process_refund] PayMongo refund failed: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'PayMongo refund failed: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    // Update refund record
    $upd = $conn->prepare("
        UPDATE refunds
        SET    refund_status  = 'processing',
               processed_date = ?,
               processed_by   = ?,
               admin_notes    = 'Approved by admin',
               updated_at     = NOW()
        WHERE  refund_id = ?
    ");
    $upd->bind_param('sii', $today, $adminId, $refundId);
    $upd->execute();
    $upd->close();

    // In-app notification to user
    $ntTitle = "Refund Approved — $refRef";
    $ntBody  = "Your refund of $amtFmt for $refLabel has been approved and is now being processed. You will be notified once it is completed.";
    $ntLink  = 'pages/user/payment.php';
    $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, ?)");
    $n->bind_param('isss', $userId, $ntTitle, $ntBody, $ntLink);
    $n->execute();
    $n->close();

    // Email to user
    if ($userEmail) {
        require_once __DIR__ . '/../../includes/email_service.php';

        // Table row label differs for booking vs invoice
        $rowLabel = $invoiceId ? 'Invoice No.' : 'Booking Ref';

        $html = "
        <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f8fafc;padding:32px 16px;'>
            <div style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);'>
                <div style='background:#16a34a;padding:28px 32px;'>
                    <h1 style='color:#fff;margin:0;font-size:22px;font-weight:700;'>&#x2705; Refund Approved</h1>
                </div>
                <div style='padding:28px 32px;'>
                    <p style='color:#374151;font-size:15px;margin:0 0 20px;'>Hi {$userName},</p>
                    <p style='color:#374151;font-size:15px;margin:0 0 20px;'>
                        Great news! Your refund request has been approved and is now being processed.
                        The amount will be returned to your original payment method within 5–10 business days.
                    </p>
                    <div style='background:#f1f5f9;border-radius:8px;padding:18px 20px;margin-bottom:20px;'>
                        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#374151;'>
                            <tr><td style='padding:5px 0;color:#6b7280;'>{$rowLabel}</td><td style='text-align:right;font-weight:700;'>{$refRef}</td></tr>
                            <tr><td style='padding:5px 0;color:#6b7280;'>Refund Amount</td><td style='text-align:right;font-weight:700;color:#16a34a;'>{$amtFmt}</td></tr>
                            <tr><td style='padding:5px 0;color:#6b7280;'>Status</td><td style='text-align:right;'>Approved — Processing</td></tr>
                        </table>
                    </div>
                    <p style='color:#6b7280;font-size:13px;margin:0;'>If you have questions, please contact our support team.</p>
                </div>
            </div>
        </div>";

        try {
            $emailService->sendEmail($userEmail, "Refund Approved — $refRef", $html);
        } catch (Throwable $e) {
            error_log('[process_refund] Approve email failed: ' . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => "Refund of $amtFmt for $refLabel is now processing."]);
    exit;
}

// ── 2B. REJECT ────────────────────────────────────────────────────────────────
if ($action === 'reject') {

    $upd = $conn->prepare("
        UPDATE refunds
        SET    refund_status  = 'rejected',
               processed_date = ?,
               processed_by   = ?,
               admin_notes    = ?,
               updated_at     = NOW()
        WHERE  refund_id = ?
    ");
    $upd->bind_param('sisi', $today, $adminId, $reason, $refundId);
    $upd->execute();
    $upd->close();

    // In-app notification to user
    $ntTitle = "Refund Rejected — $refRef";
    $ntBody  = "Your refund request of $amtFmt for $refLabel was not approved. Reason: $reason";
    $ntLink  = 'pages/user/payment.php';
    $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, ?)");
    $n->bind_param('isss', $userId, $ntTitle, $ntBody, $ntLink);
    $n->execute();
    $n->close();

    // Email to user
    if ($userEmail) {
        require_once __DIR__ . '/../../includes/email_service.php';
        $reasonEsc = htmlspecialchars($reason);
        $rowLabel  = $invoiceId ? 'Invoice No.' : 'Booking Ref';

        $html = "
        <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f8fafc;padding:32px 16px;'>
            <div style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);'>
                <div style='background:#dc2626;padding:28px 32px;'>
                    <h1 style='color:#fff;margin:0;font-size:22px;font-weight:700;'>Refund Request Update</h1>
                </div>
                <div style='padding:28px 32px;'>
                    <p style='color:#374151;font-size:15px;margin:0 0 20px;'>Hi {$userName},</p>
                    <p style='color:#374151;font-size:15px;margin:0 0 20px;'>
                        After reviewing your refund request, we were unable to approve it at this time.
                    </p>
                    <div style='background:#f1f5f9;border-radius:8px;padding:18px 20px;margin-bottom:20px;'>
                        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#374151;'>
                            <tr><td style='padding:5px 0;color:#6b7280;'>{$rowLabel}</td><td style='text-align:right;font-weight:700;'>{$refRef}</td></tr>
                            <tr><td style='padding:5px 0;color:#6b7280;'>Refund Amount</td><td style='text-align:right;'>{$amtFmt}</td></tr>
                            <tr><td style='padding:5px 0;color:#6b7280;'>Status</td><td style='text-align:right;font-weight:700;color:#dc2626;'>Rejected</td></tr>
                            <tr><td style='padding:5px 0;color:#6b7280;'>Reason</td><td style='text-align:right;'>{$reasonEsc}</td></tr>
                        </table>
                    </div>
                    <p style='color:#6b7280;font-size:13px;margin:0;'>
                        If you believe this is an error or would like to appeal, please contact our support team.
                    </p>
                </div>
            </div>
        </div>";

        try {
            $emailService->sendEmail($userEmail, "Refund Request Update — $refRef", $html);
        } catch (Throwable $e) {
            error_log('[process_refund] Reject email failed: ' . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => "Refund request for $refLabel has been rejected."]);
    exit;
}