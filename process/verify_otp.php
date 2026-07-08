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
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.', 'redirect' => '../pages/login.php']);
    exit;
}

$submitted = trim($_POST['code'] ?? '');
if ($submitted === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter your verification code.']);
    exit;
}

$result = $c->otp()->verifyEmailOtp($submitted);

switch ($result) {
    case 'no_session':
        echo json_encode(['success' => false, 'message' => 'No active code found. Please request a new one.']);
        exit;

    case 'expired':
        echo json_encode(['success' => false, 'message' => 'Your code has expired. Please request a new one.']);
        exit;

    case 'invalid':
        echo json_encode(['success' => false, 'message' => 'Invalid code. Please try again.']);
        exit;
}

// OTP valid — mark user verified and finalise session
$userId = (int) $session->get('user_id');

try {
    $c->registration()->markVerified($userId);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$session->clearVerifyOtp();
$session->set('verification_status', 'Verified');
$session->set('pending_verification', false);
$session->refreshCsrf();

echo json_encode(['success' => true, 'redirect' => 'pages/user/user-dashboard.php']);