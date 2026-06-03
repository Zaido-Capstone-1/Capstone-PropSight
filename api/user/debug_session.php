<?php
/**
 * api/user/debug_session.php
 * TEMPORARY - delete after debugging
 * Usage: /api/user/debug_session.php?session_id=cs_xxx
 */
require_once '../../includes/session.php';
header('Content-Type: application/json');

$sessionId = trim($_GET['session_id'] ?? '');
if (!$sessionId) { echo json_encode(['error' => 'missing session_id']); exit; }

$secret = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');

$ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . urlencode($sessionId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Basic ' . base64_encode($secret . ':'),
        'Accept: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 15,
]);
$response = curl_exec($ch);
curl_close($ch);

// Pretty print the full raw response so we can see the exact structure
$decoded = json_decode($response, true);
echo json_encode($decoded, JSON_PRETTY_PRINT);