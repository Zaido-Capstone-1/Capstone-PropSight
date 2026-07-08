<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
$c = require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$submitted = trim($_POST['otp'] ?? '');
if ($submitted === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please enter your verification code.']);
    exit;
}

$session = $c->session();
$result = $c->otp()->verifyLoginOtp($submitted);

switch ($result) {
    case 'no_session':
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.', 'redirect' => '../pages/login.php']);
        exit;

    case 'expired':
        echo json_encode(['status' => 'error', 'message' => 'Your code has expired. Please log in again.', 'redirect' => '../pages/login.php']);
        exit;

    case 'invalid':
        echo json_encode(['status' => 'error', 'message' => 'Invalid verification code. Please try again.']);
        exit;
}

// OTP valid
$user = $session->getPendingUser();
$session->clearLoginOtp();
$session->regenerate();
$session->populateUser($user);
$session->refreshCsrf();

$redirect = ($user['role'] === 'admin')
    ? '../pages/admin/index.php'
    : '../pages/user/user-dashboard.php';

echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'role' => $user['role'] ?? 'user']);