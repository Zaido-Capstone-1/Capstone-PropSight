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