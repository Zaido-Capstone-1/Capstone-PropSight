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
require_once __DIR__ . '/../../includes/email_templates.php';
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

if (!$refundId || !in_array($action, ['approve', 'reject', 'complete'], true)) {
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
            r.refund_method,
            COALESCE(pp_bk.paymongo_payment_id, pp_inv.paymongo_payment_id, pp_py.paymongo_payment_id) AS paymongo_payment_id,
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
    LEFT JOIN payments py_row
           ON  py_row.payment_id = r.payment_id
           AND r.payment_id      IS NOT NULL
    LEFT JOIN paymongo_payments pp_py
           ON  pp_py.reference_type = 'invoice'
           AND pp_py.status         = 'paid'
           AND pp_py.reference_id   = CAST(REPLACE(py_row.notes, 'INV-PMT-', '') AS UNSIGNED)
           AND py_row.notes         LIKE 'INV-PMT-%'
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

if ($action !== 'complete' && $refund['refund_status'] !== 'pending') {
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

    $pmPaymentId  = $refund['paymongo_payment_id'] ?? '';
    $storedMethod = strtolower(trim($refund['refund_method'] ?? ''));
    $isCard       = ($storedMethod === 'card');

    if ($pmPaymentId && $isCard) {
        try {
            paymongo_request('POST', '/refunds', [
                'amount'     => (int) round($amount * 100),
                'payment_id' => $pmPaymentId,
                'reason'     => 'others',
                'notes'      => "Admin approved refund for $notesLabel",
            ]);
            // Card refunds complete immediately via PayMongo
            $cUpd = $conn->prepare("
                UPDATE refunds SET refund_status='completed', processed_date=?, processed_by=?,
                admin_notes='Auto-refunded via PayMongo (card)', updated_at=NOW() WHERE refund_id=?
            ");
            $cUpd->bind_param('sii', $today, $adminId, $refundId);
            $cUpd->execute();
            $cUpd->close();
        } catch (Throwable $e) {
            error_log('[process_refund] Card PayMongo refund failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'PayMongo refund failed: ' . $e->getMessage()]);
            exit;
        }
        // Notify user of completion
        $ntTitle = "Refund Completed — $refRef";
        $ntBody  = "Your refund of $amtFmt for $refLabel has been completed successfully.";
        $ntLink  = 'pages/user/payment.php';
        $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, ?)");
        $n->bind_param('isss', $userId, $ntTitle, $ntBody, $ntLink);
        $n->execute();
        $n->close();
        echo json_encode(['success' => true, 'message' => "Refund of $amtFmt for $refLabel has been processed automatically."]);
        exit;
    }

    // GCash / Maya / Bank Transfer — manual processing

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
        $rowLabel = $invoiceId ? 'Invoice no.' : 'Booking ref';
        $html = refund_email_html('processing', [
            'name'      => $userName,
            'ref'       => $refRef,
            'ref_label' => $rowLabel,
            'amount'    => $amtFmt,
            'method'    => $storedMethod ? ucfirst($storedMethod) : '',
        ]);
        try {
            $emailService->sendEmail($userEmail, "Refund approved - {$refRef}", $html);
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
        $rowLabel2 = $invoiceId ? 'Invoice no.' : 'Booking ref';
        $html = refund_email_html('declined', [
            'name'           => $userName,
            'ref'            => $refRef,
            'ref_label'      => $rowLabel2,
            'amount'         => $amtFmt,
            'decline_reason' => $reason,
        ]);
        try {
            $emailService->sendEmail($userEmail, "Refund request declined - {$refRef}", $html);
        } catch (Throwable $e) {
            error_log('[process_refund] Reject email failed: ' . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => "Refund request for $refLabel has been rejected."]);
    exit;
}

// \u2500\u2500 2C. COMPLETE \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
if ($action === 'complete') {

    if ($refund['refund_status'] !== 'processing') {
        echo json_encode(['success' => false, 'message' => 'Only processing refunds can be marked complete.']);
        exit;
    }

    $upd = $conn->prepare("
        UPDATE refunds
        SET    refund_status  = 'completed',
               processed_date = ?,
               admin_notes    = 'Manually completed by admin',
               updated_at     = NOW()
        WHERE  refund_id = ?
    ");
    $upd->bind_param('si', $today, $refundId);
    $upd->execute();
    $upd->close();

    // In-app notification
    $ntTitle = "Refund Completed \u2014 $refRef";
    $ntBody  = "Your refund of $amtFmt for $refLabel has been completed successfully.";
    $ntLink  = 'pages/user/payment.php';
    $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, ?)");
    $n->bind_param('isss', $userId, $ntTitle, $ntBody, $ntLink);
    $n->execute();
    $n->close();

    // Email to user
    if ($userEmail) {
        require_once __DIR__ . '/../../includes/email_service.php';
        $rowLabel = $invoiceId ? 'Invoice no.' : 'Booking ref';
        $completedOn = gmdate('M j, Y');
        $html = refund_email_html('completed', [
            'name'      => $userName,
            'ref'       => $refRef,
            'ref_label' => $rowLabel,
            'amount'    => $amtFmt,
            'method'    => $storedMethod ? ucfirst($storedMethod) : '',
            'date'      => $completedOn,
        ]);
        try {
            $emailService->sendEmail($userEmail, "Refund completed - {$refRef}", $html);
        } catch (Throwable $e) {
            error_log('[process_refund] Complete email failed: ' . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => "Refund of $amtFmt for $refLabel has been marked as completed."]);
    exit;
}