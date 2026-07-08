<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
$c = require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$session = $c->session();

// Must be in a pending-verification session
if (!$session->get('login') || !$session->get('pending_verification')) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please register again.']);
    exit;
}

$email     = (string) $session->get('email', '');
$firstName = (string) $session->get('first_name', '');

if ($email === '') {
    echo json_encode(['success' => false, 'message' => 'Session data missing. Please register again.']);
    exit;
}

try {
    $otp = $c->otp()->issueVerifyOtp(expirySeconds: 600, rateLimitSeconds: 60);
    $c->authEmail()->sendVerifyOtp($email, $firstName, $otp);
    echo json_encode(['success' => true, 'message' => 'Verification code sent! Please check your inbox.']);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}