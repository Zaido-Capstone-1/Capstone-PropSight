<?php
header('Content-Type: application/json');
require_once '../../includes/session.php';
require_once '../../includes/db.php';
require_once '../../integrations/paymongo.php';

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

/* ─── Helper: resolve payment method from paymongo_payments row + link data ─── */
function format_payment_method(string $method): string
{
    return match (strtolower(trim($method))) {
        'gcash' => 'GCash',
        'paymaya', 'maya' => 'Maya',
        'card' => 'Card',
        'dob', 'online_banking', 'bank_transfer' => 'Bank Transfer',
        default => ucfirst($method) ?: 'PayMongo',
    };
}

function resolve_payment_method(mysqli $conn, array $pmRow, array $linkData = []): string
{
    // Priority 1: already stored in paymongo_payments.payment_method
    if (!empty($pmRow['payment_method'])) {
        return $pmRow['payment_method'];
    }

    // Priority 2: from the PayMongo link payments array source type
    $payments = $linkData['attributes']['payments'] ?? [];
    foreach ($payments as $p) {
        $sourceType = $p['attributes']['source']['type'] ?? '';
        if ($sourceType) {
            return $sourceType; // e.g. 'gcash', 'card', 'paymaya'
        }
    }

    // Priority 3: from bookings table (legacy booking flow)
    $bookingId = (int) ($pmRow['booking_id'] ?? 0);
    if ($bookingId > 0) {
        $bkRow = $conn->query("SELECT payment_method FROM bookings WHERE booking_id=$bookingId LIMIT 1")->fetch_assoc();
        if (!empty($bkRow['payment_method'])) {
            return $bkRow['payment_method'];
        }
    }

    return 'PayMongo'; // last-resort default
}

// ── Invoice payment status check ─────────────────────────────────────────────
if ($invoiceId && !$bookingId) {

    // Fetch all links for this invoice (one per method)
    $stmt = $conn->prepare("
        SELECT pp.id, pp.status AS payment_status, pp.paymongo_link_id, pp.amount, pp.payment_method
        FROM paymongo_payments pp
        WHERE pp.reference_id = ? AND pp.reference_type = 'invoice'
        ORDER BY pp.created_at DESC
    ");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $pmRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($pmRows)) {
        echo json_encode(['success' => true, 'payment_status' => 'pending', 'invoice_status' => 'Pending']);
        exit;
    }

    // Fast path: a row is already marked paid locally
    foreach ($pmRows as $pmRow) {
        if ($pmRow['payment_status'] === 'paid') {
            echo json_encode(['success' => true, 'payment_status' => 'paid', 'invoice_status' => 'Paid']);
            exit;
        }
    }

    // Poll PayMongo for each link — stop at the first one that comes back paid
    $paid_row = null;
    $paid_link = null;
    $paid_method = null;

    foreach ($pmRows as $pmRow) {
        if (empty($pmRow['paymongo_link_id']))
            continue;
        if (in_array($pmRow['payment_status'], ['failed', 'expired', 'cancelled']))
            continue;

        try {
            $isCard = ($pmRow['payment_method'] === 'card');

            if ($isCard) {
                $session = paymongo_request('GET', '/checkout_sessions/' . $pmRow['paymongo_link_id']);
                $csStatus = $session['attributes']['payment_intent']['attributes']['status'] ?? '';
                $isPaid = ($csStatus === 'succeeded');
            } else {
                $session = paymongo_request('GET', '/links/' . $pmRow['paymongo_link_id']);
                $isPaid = (($session['attributes']['status'] ?? '') === 'paid');
            }

            if ($isPaid) {
                $paid_row = $pmRow;
                $paid_link = $session;
                $paid_method = format_payment_method($isCard ? 'card' : resolve_payment_method($conn, $pmRow, $session));
                break;
            }
        } catch (Exception $e) {
            error_log('[check_payment_status] Invoice poll failed (' . $pmRow['paymongo_link_id'] . '): ' . $e->getMessage());
        }
    }

    if ($paid_row && $paid_link) {
        $paidLinkId = $paid_row['paymongo_link_id'];
        $invAmt = (float) $paid_row['amount'];
        $today = date('Y-m-d');

        // 1. Mark this link paid (store resolved method)
        $conn->query("UPDATE paymongo_payments SET status='paid', payment_method='" . $conn->real_escape_string($paid_method) . "' WHERE paymongo_link_id='" . $conn->real_escape_string($paidLinkId) . "'");

        // 2. Cancel/expire all other pending links for this invoice
        $expStmt = $conn->prepare("
            UPDATE paymongo_payments
            SET    status = 'cancelled'
            WHERE  reference_id   = ?
              AND  reference_type = 'invoice'
              AND  paymongo_link_id != ?
              AND  status NOT IN ('paid','expired','failed','cancelled')
        ");
        $expStmt->bind_param('is', $invoiceId, $paidLinkId);
        $expStmt->execute();
        $expStmt->close();

        // 3. Mark invoice Paid
        $invUpd = $conn->prepare("UPDATE invoices SET status='Paid' WHERE id=? AND status!='Paid'");
        $invUpd->bind_param('i', $invoiceId);
        $invUpd->execute();
        $invUpd->close();

        // 4. Insert into payments with the correct payment_method
        $invRef = 'INV-PMT-' . $invoiceId;
        $pmtCheck = $conn->query("SELECT payment_id FROM payments WHERE notes='" . $conn->real_escape_string($invRef) . "' LIMIT 1");
        if (!$pmtCheck || $pmtCheck->num_rows === 0) {
            $pmtStatus = 'paid';
            $ps = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (NULL, ?, ?, ?, ?, ?)");
            $ps->bind_param('sdsss', $today, $invAmt, $paid_method, $pmtStatus, $invRef);
            $ps->execute();
            $ps->close();
        }

        // 5. Insert transaction with the correct payment_method in description
        $txCheck = $conn->query("SELECT id FROM transactions WHERE reference_no='" . $conn->real_escape_string($invRef) . "' LIMIT 1");
        if (!$txCheck || $txCheck->num_rows === 0) {
            $desc = 'PayMongo payment (' . $paid_method . ') for Invoice #' . $invoiceId;
            $safeRef = $conn->real_escape_string($invRef);
            $safeDesc = $conn->real_escape_string($desc);
            $conn->query("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date) VALUES ('$safeRef', '$safeDesc', 'Invoice Revenue', 'Income', $invAmt, '$today')");
        }

        echo json_encode(['success' => true, 'payment_status' => 'paid', 'invoice_status' => 'Paid', 'payment_method' => $paid_method]);
        exit;
    }

    // No link paid yet — return the most recent status
    $latestStatus = $pmRows[0]['payment_status'] ?? 'pending';
    echo json_encode(['success' => true, 'payment_status' => $latestStatus, 'invoice_status' => 'Pending']);
    exit;
}

// ── Booking payment status check ──────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT pp.status AS payment_status, pp.paymongo_link_id, pp.expires_at,
           pp.payment_method, pp.amount,
           b.status AS booking_status
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
    $isCard = ($row['payment_method'] === 'card');
    if ($isCard) {
        $link = paymongo_request('GET', '/checkout_sessions/' . $row['paymongo_link_id']);
        $csStatus = $link['attributes']['payment_intent']['attributes']['status'] ?? '';
        $isPaid = ($csStatus === 'succeeded');
        $isArchived = false;
    } else {
        $link = paymongo_request('GET', '/links/' . $row['paymongo_link_id']);
        $linkStatus = $link['attributes']['status'] ?? '';
        $isPaid = ($linkStatus === 'paid');
        $isArchived = (bool) ($link['attributes']['archived'] ?? false);
    }

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
        // Resolve the actual payment method used
        $paymentMethod = format_payment_method(resolve_payment_method($conn, $row, $link));

        // Check if payment record already exists for this booking
        $existingPayment = $conn->query("SELECT payment_id FROM payments WHERE booking_id=$bookingId AND payment_status='paid' LIMIT 1")->fetch_assoc();
        $existingTxn    = $conn->query("SELECT id FROM transactions WHERE booking_id=$bookingId AND type='Income' LIMIT 1")->fetch_assoc();

        if (!$existingPayment && !$existingTxn) {
            $pmPaymentData = $conn->query("SELECT amount FROM paymongo_payments WHERE paymongo_link_id='" . $conn->real_escape_string($row['paymongo_link_id']) . "' LIMIT 1")->fetch_assoc();
            $amount = (float) ($pmPaymentData['amount'] ?? 0);

            $paymentDatetime = date('Y-m-d H:i:s');
            $date = date('Y-m-d');

            // Save payment with the resolved method
            $ins = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (?, ?, ?, ?, 'paid', ?)");
            $notes = 'PayMongo payment via link check (auto-synced)';
            $ins->bind_param('isdss', $bookingId, $paymentDatetime, $amount, $paymentMethod, $notes);
            $ins->execute();
            $newPaymentId = $ins->insert_id;
            $ins->close();

            // Save transaction with the resolved method in description
            $ref = 'PMT-' . $newPaymentId;
            $desc = 'PayMongo payment (' . $paymentMethod . ') for Booking #' . $bookingId;
            $propIdRow = $conn->query("SELECT p.property_id FROM bookings b JOIN units u ON u.unit_id = b.unit_id JOIN properties p ON p.property_id = u.property_id WHERE b.booking_id = $bookingId LIMIT 1")->fetch_assoc();
            $txPropId = $propIdRow ? (int) $propIdRow['property_id'] : 'NULL';
            $conn->query("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, booking_id, property_id) VALUES ('$ref', '" . $conn->real_escape_string($desc) . "', 'Room Revenue', 'Income', $amount, '$date', $bookingId, $txPropId)");

            error_log("check_payment_status: Created payment record for booking $bookingId, amount: $amount, method: $paymentMethod");
        }

        // Update paymongo_payments with resolved method
        $conn->query("UPDATE paymongo_payments SET status='paid', payment_method='" . $conn->real_escape_string($paymentMethod) . "' WHERE paymongo_link_id='" . $conn->real_escape_string($row['paymongo_link_id']) . "'");
        $conn->query("UPDATE bookings SET status='confirmed', paid_at=NOW() WHERE booking_id=$bookingId AND status IN ('pending','confirmed')");
        $unitRow = $conn->query("SELECT unit_id FROM bookings WHERE booking_id=$bookingId LIMIT 1")->fetch_assoc();
        if ($unitRow)
            $conn->query("UPDATE units SET status='occupied' WHERE unit_id=" . (int) $unitRow['unit_id'] . " AND status!='maintenance'");
        echo json_encode(['success' => true, 'payment_status' => 'paid', 'booking_status' => 'confirmed', 'payment_method' => $paymentMethod]);
        exit;
    }
} catch (Exception $e) {
    error_log('[check_payment_status] PayMongo fallback failed: ' . $e->getMessage());
}

echo json_encode(['success' => true, 'payment_status' => $row['payment_status'], 'booking_status' => $row['booking_status']]);