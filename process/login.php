<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
$c = require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../vendor/autoload.php';
applyRateLimit($conn, 'login', 10, 900); // 10 attempts per 15 minutes

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// CSRF 
if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token!']);
    exit;
}

$email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$password = $_POST['password'] ?? '';

if (!isset($_POST['login'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request!']);
    exit;
}

if ($email === '' || $password === '') {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

$auth = $c->auth();
$session = $c->session();

// Find user 
$user = $auth->findByEmail($email);
if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
    exit;
}

// Lockout check (with auto-expiry) 
$user = $auth->handleExpiredLockout($user);

if ($user['is_locked']) {
    $remaining = max(0, (int) ceil((strtotime($user['locked_until']) - time()) / 60));
    echo json_encode([
        'status' => 'error',
        'message' => "Your account is temporarily locked. Please try again in {$remaining} minute(s).",
    ]);
    exit;
}

// Password verification
if (!$auth->verifyPassword($password, $user['password'])) {
    $result = $auth->recordFailedAttempt($user);
    $remaining = $auth->maxAttempts() - $result['attempts'];
    $baseMessage = 'Invalid email or password.';

    if ($result['locked']) {
        echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Account locked for 5 minutes.']);
    } else {
        $hint = $remaining > 0 ? " {$remaining} attempt(s) remaining." : '';
        echo json_encode(['status' => 'error', 'message' => $baseMessage . $hint]);
    }
    exit;
}

// Account active check
if (isset($user['is_active']) && !$user['is_active']) {
    echo json_encode(['status' => 'error', 'message' => 'Your account is inactive. Please contact support.']);
    exit;
}

// Reset failed attempts on success
$auth->resetAttempts($user['user_id']);

// 2FA / OTP flow
if ($auth->isTwoFactorEnabled($user['user_id'])) {
    try {
        $otp = $c->otp()->issueLoginOtp($user, expiryMinutes: 5);
        $c->authEmail()->sendLoginOtp($user['email'], $otp);
        $session->refreshCsrf();
        echo json_encode(['status' => 'otp_sent', 'message' => 'OTP sent to your email!', 'require_otp' => true, 'otp_expires_in_seconds' => 300]);
    } catch (\RuntimeException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Direct login
$session->regenerate();
$session->populateUser($user);
$session->refreshCsrf();

$redirect = ($user['role'] === 'admin') ? '../pages/admin/index.php' : '../pages/user/user-dashboard.php';
echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'role' => $session->get('role')]);