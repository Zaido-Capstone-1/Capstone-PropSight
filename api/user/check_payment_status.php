<?php
header('Content-Type: application/json');
require_once '../../includes/session.php';
require_once '../../includes/db.php';
require_once '../../includes/paymongo.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['user', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$bookingId = (int) ($_GET['booking_id'] ?? 0);
$invoiceId = (int) ($_GET['invoice_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if (!$bookingId && !$invoiceId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id or invoice_id.']);
    exit;
}

// Invoice payment status check
if ($invoiceId && !$bookingId) {
    $stmt = $conn->prepare("
        SELECT pp.status AS payment_status, pp.paymongo_link_id
        FROM paymongo_payments pp
        WHERE pp.reference_id = ? AND pp.reference_type = 'invoice'
        ORDER BY pp.created_at DESC LIMIT 1
    ");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => true, 'payment_status' => 'pending', 'invoice_status' => 'Pending']);
        exit;
    }

    if ($row['payment_status'] === 'paid') {
        echo json_encode(['success' => true, 'payment_status' => 'paid', 'invoice_status' => 'Paid']);
        exit;
    }

    // Poll PayMongo for current link status
    try {
        $link = paymongo_request('GET', '/links/' . $row['paymongo_link_id']);
        $linkStatus = $link['attributes']['status'] ?? '';
        if ($linkStatus === 'paid') {
            // Mark paymongo_payments paid
            $conn->query("UPDATE paymongo_payments SET status='paid' WHERE paymongo_link_id='" . $conn->real_escape_string($row['paymongo_link_id']) . "'");
            // Mark invoice Paid
            $invUpd = $conn->prepare("UPDATE invoices SET status='Paid' WHERE id=? AND status!='Paid'");
            $invUpd->bind_param('i', $invoiceId);
            $invUpd->execute();
            $invUpd->close();
            // Log transaction
            $pmPay = $conn->query("SELECT amount FROM paymongo_payments WHERE paymongo_link_id='" . $conn->real_escape_string($row['paymongo_link_id']) . "' LIMIT 1")->fetch_assoc();
            $invAmt = (float) ($pmPay['amount'] ?? 0);
            $invRef = 'INV-PMT-' . $invoiceId;
            $today = date('Y-m-d');
            // Only insert transaction if not already recorded
            $txCheck = $conn->query("SELECT id FROM transactions WHERE reference_no='$invRef' LIMIT 1");
            if (!$txCheck || $txCheck->num_rows === 0) {
                $conn->query("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date) VALUES ('$invRef', 'PayMongo payment for Invoice #$invoiceId', 'Invoice Revenue', 'Income', $invAmt, '$today')");
            }
            echo json_encode(['success' => true, 'payment_status' => 'paid', 'invoice_status' => 'Paid']);
            exit;
        }
    } catch (Exception $e) {
        error_log('[check_payment_status] Invoice poll failed: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'payment_status' => $row['payment_status'], 'invoice_status' => 'Pending']);
    exit;
}

$stmt = $conn->prepare("
    SELECT pp.status AS payment_status, pp.paymongo_link_id, pp.expires_at, b.status AS booking_status
    FROM paymongo_payments pp
    JOIN bookings b ON b.booking_id = pp.booking_id
    WHERE pp.booking_id = ? AND b.user_id = ?
    ORDER BY pp.created_at DESC
    LIMIT 1
");
$stmt->bind_param('ii', $bookingId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => true, 'payment_status' => 'pending', 'booking_status' => 'pending']);
    exit;
}

if ($row['payment_status'] === 'paid') {
    echo json_encode(['success' => true, 'payment_status' => 'paid', 'booking_status' => $row['booking_status']]);
    exit;
}

try {
    $link = paymongo_request('GET', '/links/' . $row['paymongo_link_id']);
    $linkStatus = $link['attributes']['status'] ?? '';
    $isPaid = $linkStatus === 'paid';

    $isArchived = (bool) ($link['attributes']['archived'] ?? false);

    $isExpired = false;
    if (!empty($row['expires_at']) && !$isPaid) {
        $isExpired = $conn->query(
            "SELECT NOW() > '" . $conn->real_escape_string($row['expires_at']) . "' AS past"
        )->fetch_assoc()['past'] === '1';
    }

    // Also treat PayMongo-archived links as expired
    if ($isArchived && !$isPaid) {
        $isExpired = true;
    }

    if ($isExpired) {
        $conn->query("UPDATE bookings SET status='cancelled' WHERE booking_id=$bookingId AND status='pending'");
        $conn->query("UPDATE paymongo_payments SET status='expired' WHERE paymongo_link_id='" . $conn->real_escape_string($row['paymongo_link_id']) . "'");
        $unitRow = $conn->query("SELECT unit_id FROM bookings WHERE booking_id=$bookingId LIMIT 1")->fetch_assoc();
        if ($unitRow)
            $conn->query("UPDATE units SET status='vacant' WHERE unit_id=" . (int) $unitRow['unit_id'] . " AND status!='maintenance'");
        echo json_encode(['success' => true, 'payment_status' => 'expired', 'booking_status' => 'cancelled']);
        exit;
    }

    $payments = $link['attributes']['payments'] ?? [];
    $hasFailed = false;
    foreach ($payments as $p) {
        if (($p['attributes']['status'] ?? '') === 'failed') {
            $hasFailed = true;
            break;
        }
    }

    if ($hasFailed && !$isPaid) {
        $conn->query("UPDATE bookings SET status='cancelled' WHERE booking_id=$bookingId AND status='pending'");
        $conn->query("UPDATE paymongo_payments SET status='failed' WHERE paymongo_link_id='" . $conn->real_escape_string($row['paymongo_link_id']) . "'");
        $unitRow = $conn->query("SELECT unit_id FROM bookings WHERE booking_id=$bookingId LIMIT 1")->fetch_assoc();
        if ($unitRow)
            $conn->query("UPDATE units SET status='vacant' WHERE unit_id=" . (int) $unitRow['unit_id'] . " AND status!='maintenance'");
        echo json_encode(['success' => true, 'payment_status' => 'failed', 'booking_status' => 'cancelled']);
        exit;
    }

    if ($isPaid) {
        // Check if payment record already exists
        $existingPayment = $conn->query("SELECT payment_id FROM payments WHERE booking_id=$bookingId LIMIT 1")->fetch_assoc();

        if (!$existingPayment) {
            // Get the actual amount paid from paymongo_payments (this is the deposit amount, not full booking amount)
            $pmPaymentData = $conn->query("SELECT amount FROM paymongo_payments WHERE paymongo_link_id='" . $conn->real_escape_string($row['paymongo_link_id']) . "' LIMIT 1")->fetch_assoc();
            $amount = (float) ($pmPaymentData['amount'] ?? 0);

            // Create payment record
            $paymentDatetime = date('Y-m-d H:i:s');
            $date = date('Y-m-d');
            // AFTER
            $bkMethodRow = $conn->query("SELECT payment_method FROM bookings WHERE booking_id=$bookingId LIMIT 1")->fetch_assoc();
            $paymentMethod = $bkMethodRow['payment_method'] ?? '';

            $ins = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (?, ?, ?, ?, 'paid', ?)");
            $notes = 'PayMongo payment via link check (auto-synced)';
            $ins->bind_param('isdss', $bookingId, $paymentDatetime, $amount, $paymentMethod, $notes);
            $ins->execute();
            $newPaymentId = $ins->insert_id;
            $ins->close();

            // Create transaction record
            $ref = 'PMT-' . $newPaymentId;
            $conn->query("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, booking_id) VALUES ('$ref', 'PayMongo payment for Booking #$bookingId', 'Room Revenue', 'Income', $amount, '$date', $bookingId)");

            error_log("check_payment_status: Created payment record for booking $bookingId, amount: $amount");
        }

        $conn->query("UPDATE paymongo_payments SET status='paid' WHERE paymongo_link_id='" . $conn->real_escape_string($row['paymongo_link_id']) . "'");
        $conn->query("UPDATE bookings SET status='confirmed', paid_at=NOW() WHERE booking_id=$bookingId AND status IN ('pending','confirmed')");
        $unitRow = $conn->query("SELECT unit_id FROM bookings WHERE booking_id=$bookingId LIMIT 1")->fetch_assoc();
        if ($unitRow)
            $conn->query("UPDATE units SET status='occupied' WHERE unit_id=" . (int) $unitRow['unit_id'] . " AND status!='maintenance'");
        echo json_encode(['success' => true, 'payment_status' => 'paid', 'booking_status' => 'confirmed']);
        exit;
    }
} catch (Exception $e) {
    error_log('[check_payment_status] PayMongo fallback failed: ' . $e->getMessage());
}

echo json_encode(['success' => true, 'payment_status' => $row['payment_status'], 'booking_status' => $row['booking_status']]);