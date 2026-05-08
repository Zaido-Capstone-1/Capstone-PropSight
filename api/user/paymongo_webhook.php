<?php
require_once '../../includes/db.php';
require_once '../../includes/paymongo.php';

$rawBody = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
$secret = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? getenv('PAYMONGO_WEBHOOK_SECRET');

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
    $conn->query("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, booking_id) VALUES ('$ref', 'PayMongo payment for Booking #$bookingId', 'Room Revenue', 'Income', $amount, '$date', $bookingId)");

    $conn->query("UPDATE bookings SET status='confirmed' WHERE booking_id=$bookingId AND status IN ('pending','confirmed')");
    $unitRow = $conn->query("SELECT unit_id FROM bookings WHERE booking_id=$bookingId LIMIT 1")->fetch_assoc();
    if ($unitRow)
        $conn->query("UPDATE units SET status='occupied' WHERE unit_id=" . (int) $unitRow['unit_id'] . " AND status!='maintenance'");
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
        $conn->query("UPDATE paymongo_payments SET status='failed' WHERE id=" . (int) $row['id']);
        $conn->query("UPDATE bookings SET status='cancelled' WHERE booking_id=$bookingId AND status='pending'");
        $unitRow = $conn->query("SELECT unit_id FROM bookings WHERE booking_id=$bookingId LIMIT 1")->fetch_assoc();
        if ($unitRow)
            $conn->query("UPDATE units SET status='vacant' WHERE unit_id=" . (int) $unitRow['unit_id'] . " AND status!='maintenance'");
    }
}

http_response_code(200);
echo 'ok';