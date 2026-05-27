<?php
require_once '../../includes/db.php';
require_once '../../includes/paymongo.php';

$rawBody = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
$secret = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? getenv('PAYMONGO_WEBHOOK_SECRET');

if (!$secret) {
    error_log('PayMongo: Webhook secret not configured');
    http_response_code(500);
    exit('Webhook secret not configured');
}

$expectedSig = hash_hmac('sha256', $rawBody, $secret);

if (!hash_equals($expectedSig, $sigHeader)) {
    error_log('PayMongo: Invalid HMAC signature - possible spoofed webhook');
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($rawBody, true);
$type = $event['data']['attributes']['type'] ?? '';
$data = $event['data']['attributes']['data'] ?? [];

// Log webhook for debugging
error_log('PayMongo Webhook: ' . json_encode([
    'type' => $type,
    'event' => $event,
]));

if ($type === 'link.payment.paid') {
    $linkId = $data['attributes']['links'][0]['id'] ?? null;
    error_log('PayMongo link.payment.paid - linkId: ' . ($linkId ?? 'NULL'));

    if (!$linkId) {
        error_log('PayMongo webhook: linkId not found in webhook data');
        http_response_code(200);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM paymongo_payments WHERE paymongo_link_id = ? LIMIT 1");
    $stmt->bind_param('s', $linkId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    error_log('PayMongo: Found paymongo_payments record: ' . json_encode($row));

    if (!$row || $row['status'] === 'paid') {
        error_log('PayMongo: Record not found or already paid');
        http_response_code(200);
        exit;
    }

    $bookingId = (int) $row['booking_id'];
    $amount = (float) $row['amount'];
    $paidAt = date('Y-m-d H:i:s');
    $paymentId = $data['id'] ?? null;

    error_log("PayMongo: Processing payment - bookingId: $bookingId, amount: $amount, paymentId: $paymentId");

    $upd = $conn->prepare("UPDATE paymongo_payments SET status='paid', paymongo_payment_id=?, paid_at=? WHERE paymongo_link_id=?");
    $upd->bind_param('sss', $paymentId, $paidAt, $linkId);
    $upd->execute();
    $upd->close();

    $ins = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (?, ?, ?, 'paymongo', 'paid', ?)");
    $date = date('Y-m-d');
    $notes = 'PayMongo link payment — ' . ($paymentId ?? '');
    $ins->bind_param('isds', $bookingId, $date, $amount, $notes);
    $executeResult = $ins->execute();

    if (!$executeResult) {
        error_log('PayMongo INSERT payment failed: ' . $ins->error);
    } else {
        error_log('PayMongo INSERT payment success');
    }

    $newPaymentId = $ins->insert_id;
    $ins->close();

    $ref = 'PMT-' . $newPaymentId;
    $desc = 'PayMongo payment for Booking #' . $bookingId;
    $cat = 'Room Revenue';
    $typ = 'Income';
    $txStmt = $conn->prepare("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, booking_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $txStmt->bind_param('ssssdsi', $ref, $desc, $cat, $typ, $amount, $date, $bookingId);
    $txStmt->execute();
    $txStmt->close();

    $bkUpdStmt = $conn->prepare("UPDATE bookings SET status='confirmed' WHERE booking_id=? AND status IN ('pending','confirmed')");
    $bkUpdStmt->bind_param('i', $bookingId);
    $bkUpdStmt->execute();
    $bkUpdStmt->close();

    $unitStmt = $conn->prepare("SELECT unit_id FROM bookings WHERE booking_id=? LIMIT 1");
    $unitStmt->bind_param('i', $bookingId);
    $unitStmt->execute();
    $unitRow = $unitStmt->get_result()->fetch_assoc();
    $unitStmt->close();
    if ($unitRow) {
        $unitId = (int) $unitRow['unit_id'];
        $uUpdStmt = $conn->prepare("UPDATE units SET status='occupied' WHERE unit_id=? AND status!='maintenance'");
        $uUpdStmt->bind_param('i', $unitId);
        $uUpdStmt->execute();
        $uUpdStmt->close();
    }

    // Send confirmation email
    try {
        require_once '../../includes/email_service.php';
        $bkInfo = $conn->query("
            SELECT b.checkin_date, b.checkout_date, b.total_amount,
                   CONCAT(u2.first_name,' ',u2.last_name) AS user_name, u2.email AS user_email,
                   un.unit_name, un.unit_number, p.property_name
            FROM bookings b
            JOIN users u2 ON u2.user_id = b.user_id
            JOIN units  un ON un.unit_id = b.unit_id
            LEFT JOIN properties p ON p.property_id = un.property_id
            WHERE b.booking_id=$bookingId LIMIT 1
        ")->fetch_assoc();

        if ($bkInfo && !empty($bkInfo['user_email'])) {
            $bkRef = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
            $uLabel = $bkInfo['unit_name'] ?: (($bkInfo['property_name'] ?? '') . ' — Unit ' . ($bkInfo['unit_number'] ?? ''));
            $checkin = date('F j, Y', strtotime($bkInfo['checkin_date']));
            $checkout = date('F j, Y', strtotime($bkInfo['checkout_date']));
            $amt = '₱' . number_format((float) $bkInfo['total_amount'], 2);
            $userName = htmlspecialchars($bkInfo['user_name']);
            $uLabelEsc = htmlspecialchars($uLabel);

            $html = "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f8fafc;padding:32px 16px;'>
                <div style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);'>
                    <div style='background:#16a34a;padding:28px 32px;'>
                        <h1 style='color:#fff;margin:0;font-size:22px;font-weight:700;'>🎉 Payment Received & Booking Confirmed!</h1>
                    </div>
                    <div style='padding:28px 32px;'>
                        <p style='color:#374151;font-size:15px;margin:0 0 20px;'>Hi {$userName},</p>
                        <div style='background:#f1f5f9;border-radius:8px;padding:18px 20px;margin-bottom:20px;'>
                            <table style='width:100%;border-collapse:collapse;font-size:14px;color:#374151;'>
                                <tr><td style='padding:5px 0;color:#6b7280;'>Booking Ref</td><td style='text-align:right;font-weight:700;'>{$bkRef}</td></tr>
                                <tr><td style='padding:5px 0;color:#6b7280;'>Unit</td><td style='text-align:right;'>{$uLabelEsc}</td></tr>
                                <tr><td style='padding:5px 0;color:#6b7280;'>Check-in</td><td style='text-align:right;'>{$checkin}</td></tr>
                                <tr><td style='padding:5px 0;color:#6b7280;'>Check-out</td><td style='text-align:right;'>{$checkout}</td></tr>
                                <tr><td style='padding:5px 0;color:#6b7280;'>Amount Paid</td><td style='text-align:right;font-weight:700;color:#16a34a;'>{$amt}</td></tr>
                            </table>
                        </div>
                        <p style='color:#6b7280;font-size:13px;margin:0;'>If you have questions, please contact us.</p>
                    </div>
                </div>
            </div>";

            $emailService->sendEmail($bkInfo['user_email'], "Booking Confirmed — $bkRef", $html);
        }
    } catch (\Throwable $emailErr) {
        error_log('[paymongo_webhook] Email failed (non-fatal): ' . $emailErr->getMessage());
    }
}

// Handle payment.failed
if ($type === 'payment.failed') {
    $sourceId = $data['attributes']['source']['id'] ?? null;
    $linkId = $data['attributes']['links'][0]['id'] ?? null;

    $row = null;
    if (!$row && $linkId) {
        $stmt = $conn->prepare("SELECT * FROM paymongo_payments WHERE paymongo_link_id = ? AND status = 'pending' LIMIT 1");
        $stmt->bind_param('s', $linkId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($row) {
        $bookingId = (int) $row['booking_id'];
        $failRowId = (int) $row['id'];

        $failStmt = $conn->prepare("UPDATE paymongo_payments SET status='failed' WHERE id=?");
        $failStmt->bind_param('i', $failRowId);
        $failStmt->execute();
        $failStmt->close();

        $cancelStmt = $conn->prepare("UPDATE bookings SET status='cancelled' WHERE booking_id=? AND status='pending'");
        $cancelStmt->bind_param('i', $bookingId);
        $cancelStmt->execute();
        $cancelStmt->close();

        $unitSelStmt = $conn->prepare("SELECT unit_id FROM bookings WHERE booking_id=? LIMIT 1");
        $unitSelStmt->bind_param('i', $bookingId);
        $unitSelStmt->execute();
        $unitRow = $unitSelStmt->get_result()->fetch_assoc();
        $unitSelStmt->close();
        if ($unitRow) {
            $unitId = (int) $unitRow['unit_id'];
            $vacantStmt = $conn->prepare("UPDATE units SET status='vacant' WHERE unit_id=? AND status!='maintenance'");
            $vacantStmt->bind_param('i', $unitId);
            $vacantStmt->execute();
            $vacantStmt->close();
        }
    }
}

http_response_code(200);
echo 'ok';