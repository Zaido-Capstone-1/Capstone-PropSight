<?php
// No session needed — this is called by PayMongo's servers
require_once '../../includes/db.php';
require_once '../../includes/paymongo.php';

$rawBody = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
$secret = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? getenv('PAYMONGO_WEBHOOK_SECRET');

if (!paymongo_verify_webhook($rawBody, $sigHeader, $secret)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($rawBody, true);
$type = $event['data']['attributes']['type'] ?? '';
$data = $event['data']['attributes']['data'] ?? [];

if ($type === 'link.payment.paid') {
    $linkId = $data['attributes']['links'][0]['id'] ?? null;
    if (!$linkId) {
        http_response_code(200);
        exit;
    }

    // Find the matching paymongo_payments row
    $stmt = $conn->prepare("SELECT * FROM paymongo_payments WHERE paymongo_link_id = ? LIMIT 1");
    $stmt->bind_param('s', $linkId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['status'] === 'paid') {
        http_response_code(200);
        exit;
    }

    $bookingId = (int) $row['booking_id'];
    $amount = (float) $row['amount'];
    $paidAt = date('Y-m-d H:i:s');
    $paymentId = $data['id'] ?? null;

    // Mark paymongo_payments as paid
    $upd = $conn->prepare("UPDATE paymongo_payments SET status='paid', paymongo_payment_id=?, paid_at=? WHERE paymongo_link_id=?");
    $upd->bind_param('sss', $paymentId, $paidAt, $linkId);
    $upd->execute();
    $upd->close();

    // Insert into payments table (same as manual admin payment)
    $ins = $conn->prepare("
        INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes)
        VALUES (?, ?, ?, 'paymongo', 'paid', ?)
    ");
    $date = date('Y-m-d');
    $notes = 'PayMongo link payment — ' . ($paymentId ?? '');
    $ins->bind_param('idss', $bookingId, $date, $amount, $notes);
    $ins->execute();
    $newPaymentId = $ins->insert_id;
    $ins->close();

    // Insert transaction
    $ref = 'PMT-' . $newPaymentId;
    $conn->query("
        INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, booking_id)
        VALUES ('$ref', 'PayMongo payment for Booking #$bookingId', 'Room Revenue', 'Income', $amount, '$date', $bookingId)
    ");

    // Update booking status to confirmed only after successful PayMongo payment
    $updBooking = $conn->prepare("UPDATE bookings SET status='confirmed' WHERE booking_id = ? AND status IN ('pending', 'confirmed')");
    $updBooking->bind_param('i', $bookingId);
    $updBooking->execute();
    $updBooking->close();

    // Notify user
    $userRow = $conn->prepare("SELECT user_id FROM bookings WHERE booking_id = ? LIMIT 1");
    $userRow->bind_param('i', $bookingId);
    $userRow->execute();
    $userRow = $userRow->get_result()->fetch_assoc();
    if ($userRow) {
        $uid = (int) $userRow['user_id'];
        $title = "Payment confirmed — Booking #BK-" . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
        $body = "Your payment of ₱" . number_format($amount, 2) . " was received via PayMongo.";
        $link = "pages/user/bookings.php";
        $notif = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'payment', ?, ?, ?)");
        $notif->bind_param('isss', $uid, $title, $body, $link);
        $notif->execute();
        $notif->close();
    }
}

http_response_code(200);
echo 'ok';