<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();

header('Content-Type: application/json');

function json_error(string $message): never
{
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function json_response(array $payload): never
{
    echo json_encode($payload);
    exit;
}

function clear_otp_session(): void
{
    unset(
        $_SESSION['pending_otp'],
        $_SESSION['pending_otp_email'],
        $_SESSION['pending_otp_expires'],
        $_SESSION['pending_user']
    );
}

function populate_user_session(array $user): void
{
    $_SESSION['login'] = true;
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['phone'] = $user['phone'];
    $_SESSION['nationality'] = $user['nationality'];
    $_SESSION['birthday'] = $user['birthday'];
    $_SESSION['gender'] = $user['gender'];
    $_SESSION['role'] = $user['role'] ?? 'user';
    $_SESSION['verification_status'] = $user['verification_status'] ?? 'Not Verified';
    $_SESSION['profile_photo'] = $user['profile_photo'] ?? '';
}


if (!isset($_POST['otp'])) {
    json_error('OTP is required!');
}


if (!isset($_SESSION['pending_otp'], $_SESSION['pending_user'], $_SESSION['pending_otp_expires'])) {
    json_error('Session expired. Please log in again.');
}


if (strtotime($_SESSION['pending_otp_expires']) < time()) {
    clear_otp_session();
    json_error('OTP has expired. Please log in again.');
}

$otp_input = trim($_POST['otp']);

if (!hash_equals($_SESSION['pending_otp'], $otp_input)) {
    json_error('Incorrect OTP. Please try again.');
}

$user = $_SESSION['pending_user'];

clear_otp_session();
session_regenerate_id(true);
populate_user_session($user);

json_response([
    'status' => 'success',
    'message' => 'Login successful!',
    'role' => $_SESSION['role'],
]);
