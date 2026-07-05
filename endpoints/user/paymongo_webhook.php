<?php
require_once '../../includes/db.php';
require_once '../../integrations/paymongo.php';

function format_payment_method(string $method): string
{
    return match (strtolower(trim($method))) {
        'gcash', 'qrph' => 'GCash',
        'paymaya', 'maya' => 'Maya',
        'card' => 'Card',
        'cash' => 'Cash',
        'dob', 'online_banking', 'bank_transfer', 'bank transfer', 'bank' => 'Bank Transfer',
        default => ($method !== '' ? ucfirst($method) : 'PayMongo'),
    };
}

$rawBody = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
$secret = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? getenv('PAYMONGO_WEBHOOK_SECRET');

if (!$secret) {
    error_log('PayMongo: Webhook secret not configured');
    http_response_code(500);
    exit('Webhook secret not configured');
}

// PayMongo signature format: "t=<timestamp>,te=<hmac>,li=<hmac>"
// HMAC is computed over "<timestamp>.<rawBody>", NOT over rawBody alone.
$sigValid = false;
$sigParts = [];
foreach (explode(',', $sigHeader) as $part) {
    $kv = explode('=', $part, 2);
    if (count($kv) === 2)
        $sigParts[$kv[0]] = $kv[1];
}
if (!empty($sigParts['t']) && !empty($sigParts['te'])) {
    $toSign = $sigParts['t'] . '.' . $rawBody;
    $expected = hash_hmac('sha256', $toSign, $secret);
    $sigValid = hash_equals($expected, $sigParts['te']);
}
// Allow unsigned in local dev when ngrok sends no signature header
$isDev = (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'local');
if (!$sigValid && !($isDev && $sigHeader === '')) {
    error_log('PayMongo: Invalid HMAC signature. Header: ' . $sigHeader);
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

// Handle both link.payment.paid (GCash/Maya/Online Banking) and checkout_session.payment.paid (Card)
$isLinkPaid = ($type === 'link.payment.paid');
$isCheckoutPaid = ($type === 'checkout_session.payment.paid');

if ($isLinkPaid || $isCheckoutPaid) {

    // Resolve the PayMongo record ID depending on event type
    if ($isLinkPaid) {
        $linkId = $data['id'] ?? null;
        error_log('PayMongo link.payment.paid - linkId: ' . ($linkId ?? 'NULL'));
    } else {
        // checkout_session.payment.paid — data IS the checkout session object
        $linkId = $data['id'] ?? null;
        error_log('PayMongo checkout_session.payment.paid - sessionId: ' . ($linkId ?? 'NULL'));
    }

    if (!$linkId) {
        error_log('PayMongo webhook: linkId/sessionId not found in webhook data');
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
    // For checkout sessions the payment ID lives inside the payment intent
    if ($isCheckoutPaid) {
        $paymentId = $data['attributes']['payment_intent']['attributes']['payments'][0]['id']
            ?? $data['attributes']['payment_intent']['id']
            ?? $data['id']
            ?? null;
    } else {
        // link.payment.paid: actual pay_… ID is nested in payments array
        $paymentId = $data['attributes']['payments'][0]['data']['id']
            ?? $data['attributes']['payments'][0]['id']
            ?? $data['id']
            ?? null;
    }

    // ── Resolve the actual payment method ────────────────────────────────
    // Priority 1: stored in paymongo_payments.payment_method (set by invoice.php or create_paymongo_link.php)
    // Priority 2: from the PayMongo payment object's source.type
    // Priority 3: from bookings table (legacy booking flow)
    $paymentMethod = $row['payment_method'] ?? '';

    if (empty($paymentMethod)) {
        // For link.payment.paid: source type is under data.attributes.source.type
        // For checkout_session.payment.paid: payment method is under
        //   data.attributes.payment_intent.attributes.payment_method_used
        if ($isCheckoutPaid) {
            $paymentMethod = $data['attributes']['payment_intent']['attributes']['payment_method_used']
                ?? $data['attributes']['payment_method_used']
                ?? 'card';
        } else {
            $pmSourceType = $data['attributes']['source']['type'] ?? '';
            if ($pmSourceType) {
                $paymentMethod = $pmSourceType;
            }
        }
    }

    if (empty($paymentMethod) && $bookingId > 0) {
        // Fallback: read from bookings for the legacy non-invoice flow
        $bkMethodStmt = $conn->prepare("SELECT payment_method FROM bookings WHERE booking_id = ? LIMIT 1");
        $bkMethodStmt->bind_param('i', $bookingId);
        $bkMethodStmt->execute();
        $bkMethodRow = $bkMethodStmt->get_result()->fetch_assoc();
        $bkMethodStmt->close();
        $paymentMethod = $bkMethodRow['payment_method'] ?? 'PayMongo';
    }

    if (empty($paymentMethod)) {
        $paymentMethod = 'PayMongo';
    }
    // Normalise to display name before saving
    $paymentMethod = format_payment_method($paymentMethod);

    error_log("PayMongo: Processing payment - bookingId: $bookingId, amount: $amount, paymentId: $paymentId, method: $paymentMethod");

    // Mark this link as paid (save the resolved method too)
    $upd = $conn->prepare("UPDATE paymongo_payments SET status='paid', paymongo_payment_id=?, paid_at=?, payment_method=? WHERE paymongo_link_id=?");
    $upd->bind_param('ssss', $paymentId, $paidAt, $paymentMethod, $linkId);
    $upd->execute();
    $upd->close();

    // ── Invoice payment ───────────────────────────────────────────────────
    if (($row['reference_type'] ?? '') === 'invoice') {
        $invoiceId = (int) ($row['reference_id'] ?? 0);
        if ($invoiceId) {
            // Mark invoice Paid
            $invUpd = $conn->prepare("UPDATE invoices SET status = 'Paid' WHERE id = ? AND status != 'Paid'");
            $invUpd->bind_param('i', $invoiceId);
            $invUpd->execute();
            $invUpd->close();

            // Expire/cancel all OTHER pending links for this invoice
            // so the tenant cannot pay again through a different method button.
            // First fetch them so we can archive each one via the PayMongo API,
            // then mark them cancelled in our DB.
            $otherSel = $conn->prepare("
                SELECT paymongo_link_id, payment_method FROM paymongo_payments
                WHERE  reference_id   = ?
                  AND  reference_type = 'invoice'
                  AND  paymongo_link_id != ?
                  AND  status NOT IN ('paid','expired','failed','cancelled')
            ");
            $otherSel->bind_param('is', $invoiceId, $linkId);
            $otherSel->execute();
            $otherRows = $otherSel->get_result()->fetch_all(MYSQLI_ASSOC);
            $otherSel->close();

            foreach ($otherRows as $other) {
                if (!empty($other['paymongo_link_id'])) {
                    paymongo_archive_link($other['paymongo_link_id'], $other['payment_method']);
                }
            }

            $expStmt = $conn->prepare("
                UPDATE paymongo_payments
                SET    status = 'cancelled'
                WHERE  reference_id   = ?
                  AND  reference_type = 'invoice'
                  AND  paymongo_link_id != ?
                  AND  status NOT IN ('paid','expired','failed','cancelled')
            ");
            $expStmt->bind_param('is', $invoiceId, $linkId);
            $expStmt->execute();
            $expStmt->close();

            $date = date('Y-m-d');
            $invRef = 'INV-PMT-' . $invoiceId;

            // Insert into payments table with the correct payment method
            $pmtNote = $invRef;
            $pmtChk = $conn->prepare("SELECT payment_id FROM payments WHERE notes = ? LIMIT 1");
            $pmtChk->bind_param('s', $pmtNote);
            $pmtChk->execute();
            $pmtChk->store_result();
            $pmtExists = $pmtChk->num_rows > 0;
            $pmtChk->close();

            if (!$pmtExists) {
                $pmtStatus = 'paid';
                $ps = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (NULL, ?, ?, ?, ?, ?)");
                $ps->bind_param('sdsss', $date, $amount, $paymentMethod, $pmtStatus, $pmtNote);
                $ps->execute();
                $ps->close();
            }

            // Insert into transactions with the correct payment method in description
            $txChk = $conn->prepare("SELECT id FROM transactions WHERE reference_no = ? LIMIT 1");
            $txChk->bind_param('s', $invRef);
            $txChk->execute();
            $txChk->store_result();
            $txExists = $txChk->num_rows > 0;
            $txChk->close();

            if (!$txExists) {
                $desc = 'PayMongo payment (' . $paymentMethod . ') for Invoice #' . $invoiceId;
                $invCat = 'Invoice Revenue';
                $invTyp = 'Income';
                $invNotes = '';
                $invTxStmt = $conn->prepare("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, property_id, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, NULL)");
                $invTxStmt->bind_param('ssssdss', $invRef, $desc, $invCat, $invTyp, $amount, $date, $invNotes);
                $invTxStmt->execute();
                $invTxStmt->close();
            }

            error_log('[webhook] Invoice #' . $invoiceId . ' marked Paid via PayMongo link ' . $linkId . ' method: ' . $paymentMethod);
        }
        http_response_code(200);
        echo 'ok';
        exit;
    }

    // ── Regular booking payment ───────────────────────────────────────────
    // Guard against double-insert (webhook + polling race condition)
    $apStmt = $conn->prepare("SELECT payment_id FROM payments WHERE booking_id=? AND payment_status='paid' LIMIT 1");
    $apStmt->bind_param('i', $bookingId);
    $apStmt->execute();
    $alreadyPaid = $apStmt->get_result()->fetch_assoc();
    $apStmt->close();

    $atStmt = $conn->prepare("SELECT id FROM transactions WHERE booking_id=? AND type='Income' LIMIT 1");
    $atStmt->bind_param('i', $bookingId);
    $atStmt->execute();
    $alreadyTxn = $atStmt->get_result()->fetch_assoc();
    $atStmt->close();

    if ($alreadyPaid || $alreadyTxn) {
        // Payment already recorded (likely by the polling script) — just confirm booking status and exit
        $earlyUpd = $conn->prepare("UPDATE bookings SET status='confirmed', paid_at=NOW() WHERE booking_id=? AND status IN ('pending','confirmed')");
        $earlyUpd->bind_param('i', $bookingId);
        $earlyUpd->execute();
        $earlyUpd->close();
        http_response_code(200);
        echo 'ok';
        exit;
    }

    $ins = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (?, ?, ?, ?, 'paid', ?)");
    $paymentDatetime = date('Y-m-d H:i:s');
    $date = date('Y-m-d');
    $notes = 'PayMongo link payment — ' . ($paymentId ?? '');
    $ins->bind_param('isdss', $bookingId, $paymentDatetime, $amount, $paymentMethod, $notes);
    $executeResult = $ins->execute();

    if (!$executeResult) {
        error_log('PayMongo INSERT payment failed: ' . $ins->error);
    } else {
        error_log('PayMongo INSERT payment success');
    }

    $newPaymentId = $ins->insert_id;
    $ins->close();

    $ref = 'PMT-' . $newPaymentId;
    $desc = 'PayMongo payment (' . $paymentMethod . ') for Booking #' . $bookingId;
    $cat = 'Room Revenue';
    $typ = 'Income';

    // Fetch property_id for this booking
    $propStmt = $conn->prepare("SELECT p.property_id FROM bookings b JOIN units u ON u.unit_id = b.unit_id JOIN properties p ON p.property_id = u.property_id WHERE b.booking_id = ? LIMIT 1");
    $propStmt->bind_param('i', $bookingId);
    $propStmt->execute();
    $propRow = $propStmt->get_result()->fetch_assoc();
    $propStmt->close();
    $propertyId = $propRow ? (int) $propRow['property_id'] : null;

    $txStmt = $conn->prepare("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, booking_id, property_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $txStmt->bind_param('ssssdsii', $ref, $desc, $cat, $typ, $amount, $date, $bookingId, $propertyId);
    $txStmt->execute();
    $txStmt->close();

    $bkUpdStmt = $conn->prepare("UPDATE bookings SET status='confirmed', paid_at=NOW() WHERE booking_id=? AND status IN ('pending','confirmed')");
    $bkUpdStmt->bind_param('i', $bookingId);
    $bkUpdStmt->execute();
    $bkUpdStmt->close();

    // Notify admin now that payment is confirmed
    try {
        $bkRef2 = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
        $bkInfoNotifStmt = $conn->prepare("
            SELECT CONCAT(u2.first_name,' ',u2.last_name) AS guest_name,
                   COALESCE(un.unit_name, CONCAT(p.property_name, ' — Unit ', un.unit_number)) AS unit_label,
                   b.checkin_date, b.checkout_date
            FROM bookings b
            JOIN users u2 ON u2.user_id = b.user_id
            JOIN units un ON un.unit_id = b.unit_id
            LEFT JOIN properties p ON p.property_id = un.property_id
            WHERE b.booking_id=? LIMIT 1
        ");
        $bkInfoNotifStmt->bind_param('i', $bookingId);
        $bkInfoNotifStmt->execute();
        $bkInfoNotif = $bkInfoNotifStmt->get_result()->fetch_assoc();
        $bkInfoNotifStmt->close();
        if ($bkInfoNotif) {
            $gName = $bkInfoNotif['guest_name'] ?? 'A guest';
            $uLabel2 = $bkInfoNotif['unit_label'] ?? 'a unit';
            $ciDate = date('M j', strtotime($bkInfoNotif['checkin_date']));
            $coDate = date('M j, Y', strtotime($bkInfoNotif['checkout_date']));
            $ntTitle2 = "Payment confirmed: $bkRef2";
            $ntBody2 = "$gName booked $uLabel2 · $ciDate–$coDate (PayMongo payment received via $paymentMethod).";
            $ntLink2 = 'pages/admin/reservations.php';
            $adminsStmt = $conn->prepare("SELECT user_id FROM users WHERE role='admin' LIMIT 20");
            $adminsStmt->execute();
            $admins2 = $adminsStmt->get_result();
            $adminsStmt->close();
            while ($adm2 = $admins2->fetch_assoc()) {
                $aId2 = (int) $adm2['user_id'];
                $aN = $conn->prepare("INSERT INTO notifications (user_id,type,title,body,link) VALUES (?,'booking',?,?,?)");
                $aN->bind_param('isss', $aId2, $ntTitle2, $ntBody2, $ntLink2);
                $aN->execute();
                $aN->close();
            }
        }
    } catch (\Throwable $notifErr2) {
        error_log('[paymongo_webhook] Admin notif failed: ' . $notifErr2->getMessage());
    }

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
        require_once '../../integrations/email_service.php';
        $bkInfoStmt = $conn->prepare("
            SELECT b.checkin_date, b.checkout_date, b.total_amount,
                   CONCAT(u2.first_name,' ',u2.last_name) AS user_name, u2.email AS user_email,
                   un.unit_name, un.unit_number, p.property_name
            FROM bookings b
            JOIN users u2 ON u2.user_id = b.user_id
            JOIN units  un ON un.unit_id = b.unit_id
            LEFT JOIN properties p ON p.property_id = un.property_id
            WHERE b.booking_id=? LIMIT 1
        ");
        $bkInfoStmt->bind_param('i', $bookingId);
        $bkInfoStmt->execute();
        $bkInfo = $bkInfoStmt->get_result()->fetch_assoc();
        $bkInfoStmt->close();

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
                                <tr><td style='padding:5px 0;color:#6b7280;'>Payment Method</td><td style='text-align:right;'>" . htmlspecialchars($paymentMethod) . "</td></tr>
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
    $linkId = $data['id'] ?? null;

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

        // For invoice links, only mark this specific link as failed — don't cancel siblings
        $failStmt = $conn->prepare("UPDATE paymongo_payments SET status='failed' WHERE id=?");
        $failStmt->bind_param('i', $failRowId);
        $failStmt->execute();
        $failStmt->close();

        // Only cancel the booking for non-invoice payment failures
        if (($row['reference_type'] ?? '') !== 'invoice' && $bookingId > 0) {
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
}

// ── Handle refund.updated ─────────────────────────────────────────────────────
if ($type === 'refund.updated') {
    $refundStatus = $data['attributes']['status'] ?? '';
    $pmRefundId = $data['id'] ?? '';
    $pmPaymentId = $data['attributes']['payment_id'] ?? '';

    error_log("[webhook] refund.updated — pm_refund_id: $pmRefundId, status: $refundStatus, payment_id: $pmPaymentId");

    // Only act when PayMongo confirms the refund succeeded
    if ($refundStatus === 'succeeded' && $pmPaymentId) {

        // Find the matching refund record by paymongo_payment_id
        $rStmt = $conn->prepare("
            SELECT r.refund_id, r.user_id, r.booking_id, r.refund_amount
            FROM   refunds r
            JOIN   paymongo_payments pp ON pp.booking_id = r.booking_id
            WHERE  pp.paymongo_payment_id = ?
              AND  r.refund_status = 'processing'
            LIMIT  1
        ");
        $rStmt->bind_param('s', $pmPaymentId);
        $rStmt->execute();
        $refundRow = $rStmt->get_result()->fetch_assoc();
        $rStmt->close();

        if ($refundRow) {
            $refundId = (int) $refundRow['refund_id'];
            $userId = (int) $refundRow['user_id'];
            $bookingId = (int) $refundRow['booking_id'];
            $amount = (float) $refundRow['refund_amount'];
            $amtFmt = '₱' . number_format($amount, 2);
            $bkRef = 'BK-' . str_pad($bookingId, 6, '0', STR_PAD_LEFT);
            $today = date('Y-m-d');

            // Mark refund as completed
            $upd = $conn->prepare("
                UPDATE refunds
                SET    refund_status  = 'completed',
                       processed_date = ?,
                       updated_at     = NOW()
                WHERE  refund_id = ?
            ");
            $upd->bind_param('si', $today, $refundId);
            $upd->execute();
            $upd->close();

            // In-app notification to user
            $ntTitle = "Refund Completed — $bkRef";
            $ntBody = "Your refund of $amtFmt for booking $bkRef has been completed successfully.";
            $ntLink = 'pages/user/payment.php';
            $n = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'booking', ?, ?, ?)");
            $n->bind_param('isss', $userId, $ntTitle, $ntBody, $ntLink);
            $n->execute();
            $n->close();

            // Email to user
            try {
                require_once '../../integrations/email_service.php';

                $uStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ? LIMIT 1");
                $uStmt->bind_param('i', $userId);
                $uStmt->execute();
                $uInfo = $uStmt->get_result()->fetch_assoc();
                $uStmt->close();

                $userName = htmlspecialchars(trim(($uInfo['first_name'] ?? '') . ' ' . ($uInfo['last_name'] ?? '')));
                $userEmail = $uInfo['email'] ?? '';

                if ($userEmail) {
                    $html = "
                    <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f8fafc;padding:32px 16px;'>
                        <div style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);'>
                            <div style='background:#16a34a;padding:28px 32px;'>
                                <h1 style='color:#fff;margin:0;font-size:22px;font-weight:700;'>✅ Refund Completed</h1>
                            </div>
                            <div style='padding:28px 32px;'>
                                <p style='color:#374151;font-size:15px;margin:0 0 20px;'>Hi {$userName},</p>
                                <p style='color:#374151;font-size:15px;margin:0 0 20px;'>
                                    Your refund has been successfully processed and returned to your original payment method.
                                </p>
                                <div style='background:#f1f5f9;border-radius:8px;padding:18px 20px;margin-bottom:20px;'>
                                    <table style='width:100%;border-collapse:collapse;font-size:14px;color:#374151;'>
                                        <tr><td style='padding:5px 0;color:#6b7280;'>Booking Ref</td><td style='text-align:right;font-weight:700;'>{$bkRef}</td></tr>
                                        <tr><td style='padding:5px 0;color:#6b7280;'>Refund Amount</td><td style='text-align:right;font-weight:700;color:#16a34a;'>{$amtFmt}</td></tr>
                                        <tr><td style='padding:5px 0;color:#6b7280;'>Status</td><td style='text-align:right;font-weight:700;color:#16a34a;'>Completed</td></tr>
                                    </table>
                                </div>
                                <p style='color:#6b7280;font-size:13px;margin:0;'>
                                    Please allow 3–5 business days for the amount to reflect on your statement depending on your bank.
                                </p>
                            </div>
                        </div>
                    </div>";

                    $emailService->sendEmail($userEmail, "Refund Completed — $bkRef", $html);
                }
            } catch (Throwable $e) {
                error_log('[webhook] Refund completed email failed: ' . $e->getMessage());
            }

            error_log("[webhook] Refund $refundId marked completed for booking $bkRef");
        } else {
            error_log("[webhook] refund.updated: no matching processing refund found for payment_id $pmPaymentId");
        }
    }
}

http_response_code(200);
echo 'ok';