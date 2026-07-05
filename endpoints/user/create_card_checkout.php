<?php
/**
 * endpoints/user/create_card_checkout.php
 *
 * Creates a PayMongo Checkout Session restricted to card payments only.
 * Card details are entered entirely on PayMongo's hosted page —
 * they never touch your server, keeping you fully out of PCI scope.
 *
 * Billing address is pre-filled from the property address so the
 * user only needs to enter their card number, expiry, and CVC.
 *
 * POST params:
 *   booking_id  (int)  required
 *
 * Response JSON:
 *   { success: true,  checkout_url: "...", session_id: "cs_..." }
 *   { success: false, message: "..." }
 */

header('Content-Type: application/json');
require_once '../../includes/session.php';
require_once '../../includes/db.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_csrf_token(true);

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Missing booking_id.']);
    exit;
}

// ── Fetch booking + property address ─────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT b.booking_id, b.total_amount, b.status, b.unit_id,
           COALESCE(u.unit_name, CONCAT(pr.property_name, ' — Unit ', u.unit_number)) AS unit_display,
           us.first_name, us.last_name, us.email, us.phone,
           pr.address  AS prop_address,
           pr.city     AS prop_city,
           pr.state    AS prop_state,
           pr.zip      AS prop_zip
    FROM bookings b
    LEFT JOIN units u       ON u.unit_id      = b.unit_id
    LEFT JOIN properties pr ON pr.property_id = u.property_id
    LEFT JOIN users us      ON us.user_id      = b.user_id
    WHERE b.booking_id = ? AND b.user_id = ? AND b.status IN ('pending','confirmed')
    LIMIT 1
");
$stmt->bind_param('ii', $bookingId, $userId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or already paid.']);
    exit;
}

$amount = (int) round((float) $booking['total_amount']);
$unitId = (int) ($booking['unit_id'] ?? 0);
if ($amount < 100) {
    echo json_encode(['success' => false, 'message' => 'Amount too small (minimum ₱1.00).']);
    exit;
}

// ── Secret key ────────────────────────────────────────────────────────────────
$secret = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
if (empty($secret)) {
    error_log('[create_card_checkout] PAYMONGO_SECRET_KEY is not set.');
    echo json_encode(['success' => false, 'message' => 'Payment service is not configured.']);
    exit;
}

// ── Reuse existing unexpired session ─────────────────────────────────────────
$existing = $conn->prepare("
    SELECT paymongo_link_id, checkout_url
    FROM paymongo_payments
    WHERE booking_id = ? AND payment_method = 'Card'
      AND status NOT IN ('paid','expired','failed')
      AND expires_at > NOW()
    ORDER BY created_at DESC LIMIT 1
");
$existing->bind_param('i', $bookingId);
$existing->execute();
$existingRow = $existing->get_result()->fetch_assoc();
$existing->close();

if ($existingRow && $existingRow['checkout_url']) {
    echo json_encode([
        'success' => true,
        'checkout_url' => $existingRow['checkout_url'],
        'session_id' => $existingRow['paymongo_link_id'],
        'reused' => true,
    ]);
    exit;
}

// ── Build success & cancel URLs ───────────────────────────────────────────────
// Use the current script's path to derive the base, so it works in any subfolder
// e.g. /PropSight-Capstone/endpoints/user/create_card_checkout.php
//   → /PropSight-Capstone/pages/user/unit_detail.php
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$projectBase = dirname(dirname($scriptDir));
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $projectBase;
$successUrl = $baseUrl . '/pages/user/card_payment_done.php?status=success';
$cancelUrl = $baseUrl . '/pages/user/card_payment_done.php?status=cancelled';

// ── Build billing — pre-fill address from property ───────────────────────────
$holderName = trim(($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? ''));

$billing = [
    'name' => $holderName ?: 'Tenant',
    'email' => $booking['email'] ?? '',
    'phone' => $booking['phone'] ?? '',
];

// Add address block only when we have enough data to satisfy PayMongo
$propAddress = trim($booking['prop_address'] ?? '');
$propCity = trim($booking['prop_city'] ?? '');
$propState = trim($booking['prop_state'] ?? '');
$propZip = trim($booking['prop_zip'] ?? '');

if ($propAddress && $propCity) {
    $billing['address'] = [
        'line1' => $propAddress,
        'city' => $propCity,
        'state' => $propState ?: $propCity,   // fallback if state is empty
        'postal_code' => $propZip ?: '1000',     // fallback to Manila postal code
        'country' => 'PH',
    ];
}

// ── Build request body ────────────────────────────────────────────────────────
$description = sprintf(
    'PropSight Deposit — Booking #%d: %s',
    $bookingId,
    $booking['unit_display'] ?? 'Unit'
);

$body = [
    'data' => [
        'attributes' => [
            'billing' => $billing,
            'send_email_receipt' => true,
            'show_description' => true,
            'show_line_items' => true,
            'description' => $description,
            'line_items' => [
                [
                    'currency' => 'PHP',
                    'amount' => $amount * 100,
                    'name' => $booking['unit_display'] ?? 'Apartment Deposit',
                    'quantity' => 1,
                ],
            ],
            'payment_method_types' => ['card'],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'booking_id' => (string) $bookingId,
                'user_id' => (string) $userId,
            ],
        ],
    ],
];

// ── Call PayMongo ─────────────────────────────────────────────────────────────
$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
$curlOpts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_HTTPHEADER => [
        'Authorization: Basic ' . base64_encode($secret . ':'),
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT => 30,
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

if ($response === false) {
    error_log('[create_card_checkout] cURL error: ' . $curlErr);
    echo json_encode(['success' => false, 'message' => 'Could not reach payment service.']);
    exit;
}

$decoded = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE || $httpCode >= 400) {
    $errMsg = $decoded['errors'][0]['detail'] ?? ('PayMongo error (HTTP ' . $httpCode . ')');
    error_log('[create_card_checkout] ' . $errMsg . ' | body: ' . $response);
    echo json_encode(['success' => false, 'message' => $errMsg]);
    exit;
}

$sessionId = $decoded['data']['id'] ?? '';
$checkoutUrl = $decoded['data']['attributes']['checkout_url'] ?? '';

if (!$sessionId || !$checkoutUrl) {
    error_log('[create_card_checkout] Missing session id or checkout_url: ' . $response);
    echo json_encode(['success' => false, 'message' => 'Invalid response from payment service.']);
    exit;
}

// ── Persist ───────────────────────────────────────────────────────────────────
$amountFloat = (float) $amount;
$paymentMethod = 'Card';
$status = 'pending';

$ins = $conn->prepare("
    INSERT INTO paymongo_payments
        (booking_id, user_id, paymongo_link_id, checkout_url, amount, status, payment_method, created_at, expires_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))
");
$ins->bind_param('iissdss', $bookingId, $userId, $sessionId, $checkoutUrl, $amountFloat, $status, $paymentMethod);
$ins->execute();
$ins->close();

$upd = $conn->prepare("UPDATE bookings SET payment_method = 'Card' WHERE booking_id = ?");
$upd->bind_param('i', $bookingId);
$upd->execute();
$upd->close();

echo json_encode([
    'success' => true,
    'checkout_url' => $checkoutUrl,
    'session_id' => $sessionId,
]);