<?php
function paymongo_request(string $method, string $endpoint, array $body = []): array
{
    $secret = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
    if (empty($secret)) {
        throw new Exception('PayMongo secret key is not configured.');
    }

    $ch = curl_init('https://api.paymongo.com/v1' . $endpoint);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($secret . ':'),
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ];

    $caInfo = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($caInfo && file_exists($caInfo)) {
        $curlOpts[CURLOPT_CAINFO] = $caInfo;
    } else {
        $defaultCa = __DIR__ . '/../extras/ssl/cacert.pem';
        if (file_exists($defaultCa)) {
            $curlOpts[CURLOPT_CAINFO] = $defaultCa;
        } else {
            // Fall back to insecure mode only when no CA bundle is available.
            // If this is production, configure curl.cainfo or openssl.cafile instead.
            $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
            $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0;
        }
    }

    curl_setopt_array($ch, $curlOpts);
    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => ['attributes' => $body]]));
    }

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('PayMongo request failed: ' . ($curlErr ?: 'unknown cURL error') . ' (' . $curlErrno . ').');
    }
    if ($response === '') {
        throw new Exception('PayMongo returned an empty response.');
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid PayMongo JSON response: ' . json_last_error_msg() . '. Response: ' . substr($response, 0, 1000));
    }
    if ($httpCode >= 400) {
        $msg = $decoded['errors'][0]['detail'] ?? 'PayMongo error';
        throw new Exception($msg);
    }
    return $decoded['data'] ?? $decoded;
}

function paymongo_create_link(int $amount_php, string $description, array $meta = []): array
{
    return paymongo_request('POST', '/links', [
        'amount' => $amount_php * 100,   // convert to centavos
        'description' => $description,
        'currency' => 'PHP',
        'remarks' => json_encode($meta),
    ]);
}

/**
 * Create a PayMongo Payment Intent (used for card payments).
 * Returns the full intent data array.
 */
function paymongo_create_payment_intent(int $amount_php, string $description, array $meta = []): array
{
    return paymongo_request('POST', '/payment_intents', [
        'amount' => $amount_php * 100, // centavos
        'payment_method_allowed' => ['card'],
        'payment_method_options' => ['card' => ['request_three_d_secure' => 'any']],
        'currency' => 'PHP',
        'description' => $description,
        'metadata' => $meta,
    ]);
}

/**
 * Create a PayMongo Payment Method from raw card data.
 * Returns the payment method data array.
 */
function paymongo_create_payment_method(
    string $card_number,
    int $exp_month,
    int $exp_year,
    string $cvc,
    string $holder_name,
    string $email = '',
    string $phone = ''
): array {
    $billing = ['name' => $holder_name];
    if ($email)
        $billing['email'] = $email;
    if ($phone)
        $billing['phone'] = $phone;

    return paymongo_request('POST', '/payment_methods', [
        'type' => 'card',
        'details' => [
            'card_number' => $card_number,
            'exp_month' => $exp_month,
            'exp_year' => $exp_year,
            'cvc' => $cvc,
        ],
        'billing' => $billing,
    ]);
}

/**
 * Attach a Payment Method to a Payment Intent.
 * Returns the updated intent. Check attributes.status:
 *   - 'succeeded'         → payment done
 *   - 'awaiting_next_action' → 3DS required; use next_action.redirect.url
 *   - 'processing'        → async, poll later
 */
function paymongo_attach_payment_method(string $intent_id, string $payment_method_id, string $return_url): array
{
    $ch = curl_init('https://api.paymongo.com/v1/payment_intents/' . $intent_id . '/attach');
    $secret = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
    $body = json_encode([
        'data' => [
            'attributes' => [
                'payment_method' => $payment_method_id,
                'return_url' => $return_url,
            ]
        ]
    ]);

    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($secret . ':'),
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ];

    $caInfo = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
    if ($caInfo && file_exists($caInfo)) {
        $curlOpts[CURLOPT_CAINFO] = $caInfo;
    } else {
        $defaultCa = __DIR__ . '/../extras/ssl/cacert.pem';
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
        throw new Exception('PayMongo attach failed: ' . $curlErr);
    }
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid PayMongo attach response: ' . json_last_error_msg());
    }
    if ($httpCode >= 400) {
        $msg = $decoded['errors'][0]['detail'] ?? 'PayMongo attach error';
        throw new Exception($msg);
    }
    return $decoded['data'] ?? $decoded;
}

/**
 * Retrieve a Payment Intent by ID.
 */
function paymongo_get_payment_intent(string $intent_id): array
{
    return paymongo_request('GET', '/payment_intents/' . $intent_id);
}

/**
 * Expire/archive a PayMongo payment link or checkout session so it can no longer be paid.
 * - Links (GCash/Maya/Online Banking): POST /links/{id}/archive
 * - Checkout Sessions (card):          POST /checkout_sessions/{id}/expire
 * Silently ignores 404/already-expired errors.
 */
function paymongo_archive_link(string $link_id, string $payment_method = ''): void
{
    try {
        if ($payment_method === 'card' || str_starts_with($link_id, 'cs_')) {
            paymongo_request('POST', '/checkout_sessions/' . $link_id . '/expire');
        } else {
            paymongo_request('POST', '/links/' . $link_id . '/archive');
        }
    } catch (Throwable $e) {
        // 404 = already expired/archived — safe to ignore
        error_log('[paymongo_archive_link] ' . $link_id . ': ' . $e->getMessage());
    }
}

function paymongo_verify_webhook(string $rawBody, string $sigHeader, string $secret): bool
{
    // header format: t=timestamp,te=hash,li=hash
    $parts = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $v] = explode('=', $part, 2);
        $parts[$k] = $v;
    }
    if (empty($parts['t']) || empty($parts['te']))
        return false;
    $payload = $parts['t'] . '.' . $rawBody;
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $parts['te']);
}