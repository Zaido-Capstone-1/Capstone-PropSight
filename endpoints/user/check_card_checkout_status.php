<?php
/**
 * endpoints/user/check_card_checkout_status.php
 *
 * Polls a PayMongo Checkout Session status for card-only payments.
 * Finalises the booking in the DB once payment succeeds.
 *
 * GET params:
 *   booking_id  (int)    required
 *   session_id  (string) required  — the cs_xxx from PayMongo
 *
 * Response JSON:
 *   { success: true,  payment_status: "paid"|"pending"|"failed"|"expired", booking_status: "confirmed"|"pending"|"cancelled" }
 *   { success: false, message: "..." }
 */

header('Content-Type: application/json');
require_once '../../includes/session.php';
require_once '../../includes/db.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$sessionId = trim($_GET['session_id'] ?? '');
$bookingId = (int) ($_GET['booking_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if (!$sessionId || !$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing session_id or booking_id.']);
    exit;
}

// ── Verify session belongs to this user ───────────────────────────────────────
// Special case: _return_check means look up the latest session for this booking
if ($sessionId === '_return_check') {
    $stmt2 = $conn->prepare("
        SELECT pp.paymongo_link_id, pp.status AS payment_status, pp.amount
        FROM paymongo_payments pp
        JOIN bookings b ON b.booking_id = pp.booking_id
        WHERE pp.booking_id = ? AND b.user_id = ? AND pp.payment_method = 'Card'
        ORDER BY pp.created_at DESC LIMIT 1
    ");
    $stmt2->bind_param('ii', $bookingId, $userId);
    $stmt2->execute();
    $row2 = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    if (!$row2) {
        echo json_encode(['success' => true, 'payment_status' => 'pending', 'booking_status' => 'pending']);
        exit;
    }
    $sessionId = $row2['paymongo_link_id'];
    $row = $row2;
}
$stmt = $conn->prepare("
    SELECT pp.status AS payment_status, pp.amount
    FROM paymongo_payments pp
    JOIN bookings b ON b.booking_id = pp.booking_id
    WHERE pp.paymongo_link_id = ? AND pp.booking_id = ? AND b.user_id = ?
    LIMIT 1
");
$stmt->bind_param('sii', $sessionId, $bookingId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Session not found.']);
    exit;
}

// Already finalised — return cached result
if ($row['payment_status'] === 'paid') {
    echo json_encode(['success' => true, 'payment_status' => 'paid', 'booking_status' => 'confirmed']);
    exit;
}
if (in_array($row['payment_status'], ['failed', 'expired'])) {
    echo json_encode(['success' => true, 'payment_status' => $row['payment_status'], 'booking_status' => 'cancelled']);
    exit;
}

// ── Poll PayMongo ─────────────────────────────────────────────────────────────
$secret = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
if (empty($secret)) {
    echo json_encode(['success' => false, 'message' => 'Payment service not configured.']);
    exit;
}

$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . urlencode($sessionId));
$curlOpts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Basic ' . base64_encode($secret . ':'),
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT => 15,
];

$caInfo = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
if ($caInfo && file_exists($caInfo)) {
    $curlOpts[CURLOPT_CAINFO] = $caInfo;
} else {
    $defaultCa = __DIR__ . '/../../extras/ssl/cacert.pem';
    if (file_exists($defaultCa)) {
        $curlOpts[CURLOPT_CAINFO] = $defaultCa;
    } else {
        $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
        $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0;
    }
}

curl_setopt_array($ch, $curlOpts);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    error_log('[check_card_checkout_status] cURL: ' . $curlErr . ' HTTP: ' . $httpCode);
    echo json_encode(['success' => true, 'payment_status' => 'pending', 'booking_status' => 'pending']);
    exit;
}

$decoded = json_decode($response, true);
$pmStatus = $decoded['data']['attributes']['payment_intent']['attributes']['status'] ?? '';
$paidAt = $decoded['data']['attributes']['paid_at'] ?? null;  // non-null = paid

// PayMongo does NOT change checkout session status to "inactive" after payment.
// The reliable signals are:
//   payment_intent.attributes.status === "succeeded"
//   OR paid_at is not null

if ($pmStatus === 'succeeded' || $paidAt !== null) {
    if ($pmStatus === 'succeeded') {
        $amount = (float) $row['amount'];

        // Avoid duplicate payment records
        $existPay = $conn->query("SELECT payment_id FROM payments WHERE booking_id = $bookingId AND payment_status='paid' LIMIT 1")->fetch_assoc();
        $existTxn = $conn->query("SELECT id FROM transactions WHERE booking_id = $bookingId AND type='Income' LIMIT 1")->fetch_assoc();
        if (!$existPay && !$existTxn) {
            $now = date('Y-m-d H:i:s');
            $date = date('Y-m-d');
            $note = 'PayMongo card checkout (session: ' . $conn->real_escape_string($sessionId) . ')';
            $ins = $conn->prepare("INSERT INTO payments (booking_id, payment_date, amount_paid, payment_method, payment_status, notes) VALUES (?, ?, ?, 'Card', 'paid', ?)");
            $ins->bind_param('isds', $bookingId, $now, $amount, $note);
            $ins->execute();
            $newId = $ins->insert_id;
            $ins->close();

            $ref = 'PMT-' . $newId;
            $esc = $conn->real_escape_string('Card checkout payment for Booking #' . $bookingId);
            $propIdRow2 = $conn->query("SELECT p.property_id FROM bookings b JOIN units u ON u.unit_id = b.unit_id JOIN properties p ON p.property_id = u.property_id WHERE b.booking_id = $bookingId LIMIT 1")->fetch_assoc();
            $txPropId2 = $propIdRow2 ? (int) $propIdRow2['property_id'] : 'NULL';
            $conn->query("INSERT INTO transactions (reference_no, description, category, type, amount, transaction_date, booking_id, property_id)
                          VALUES ('$ref', '$esc', 'Room Revenue', 'Income', $amount, '$date', $bookingId, $txPropId2)");
        }

        $conn->query("UPDATE paymongo_payments SET status = 'paid'
                      WHERE paymongo_link_id = '" . $conn->real_escape_string($sessionId) . "'");
        $conn->query("UPDATE bookings SET status = 'confirmed', paid_at = NOW(), payment_method = 'Card'
                      WHERE booking_id = $bookingId AND status IN ('pending','confirmed')");
        $unitRow = $conn->query("SELECT unit_id FROM bookings WHERE booking_id = $bookingId LIMIT 1")->fetch_assoc();
        if ($unitRow) {
            $uid = (int) $unitRow['unit_id'];
            $conn->query("UPDATE units SET status = 'occupied' WHERE unit_id = $uid AND status != 'maintenance'");
        }

        echo json_encode(['success' => true, 'payment_status' => 'paid', 'booking_status' => 'confirmed']);
    } else {
        // paid_at set but intent not succeeded yet — treat as pending
        echo json_encode(['success' => true, 'payment_status' => 'pending', 'booking_status' => 'pending']);
    }
    exit;
}

// Still in progress
echo json_encode(['success' => true, 'payment_status' => 'pending', 'booking_status' => 'pending']);