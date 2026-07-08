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

if (!isset($_POST['register'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request!']);
    exit;
}

if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token!']);
    exit;
}

// Collect and sanitise input
$data = [
    'first_name' => trim(htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES, 'UTF-8')),
    'last_name' => trim(htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES, 'UTF-8')),
    'email' => trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)),
    'password' => $_POST['password'] ?? '',
    'confirm_password' => $_POST['confirm_password'] ?? '',
];

$reg = $c->registration();

// Validate
try {
    $reg->validate($data);
} catch (\InvalidArgumentException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

// Duplicate check 
if ($reg->emailExists($data['email'])) {
    echo json_encode(['status' => 'error', 'message' => 'An account with this email already exists!']);
    exit;
}

// Create user 
try {
    $userId = $reg->create($data);
} catch (\RuntimeException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

// Issue verification OTP 
$session = $c->session();
$session->regenerate();
$session->populatePendingVerification(array_merge($data, ['user_id' => $userId]));

try {
    $otp = $c->otp()->issueVerifyOtp(expirySeconds: 600);
    $c->authEmail()->sendVerifyOtp($data['email'], $data['first_name'], $otp);
    $session->refreshCsrf();
    echo json_encode(['status' => 'verify_required', 'message' => 'Account created! Please verify your email.', 'redirect' => 'verify.php']);
} catch (\RuntimeException $e) {
    // Non-fatal: account was created, email just failed — user can request resend
    echo json_encode(['status' => 'verify_required', 'message' => 'Account created! Please verify your email.', 'redirect' => 'verify.php']);
}