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

// Record this login — stored as UTC like every other timestamp column
// (see includes/db.php's DT_KEYS/fmt_dt), so the Settings page can show it
// converted to each viewer's own local time instead of the server's.
$touchLogin = $conn->prepare('UPDATE users SET last_login = UTC_TIMESTAMP() WHERE user_id = ?');
$touchLogin->bind_param('i', $user['user_id']);
$touchLogin->execute();
$touchLogin->close();

$session->regenerate();
$session->populateUser($user);
$session->refreshCsrf();

$redirect = ($user['role'] === 'admin')
    ? '../pages/admin/index'
    : '../pages/user/user-dashboard';

echo json_encode(['status' => 'success', 'message' => 'Login successful!', 'role' => $user['role'] ?? 'user']);