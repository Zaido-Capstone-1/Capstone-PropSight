<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_params.php';
session_start();
header('Content-Type: application/json');

require_once '../../includes/db.php';

function json_response(bool $success, string $message): never
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    json_response(false, 'Unauthorized request.');
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    json_response(false, 'Invalid CSRF token.');
}

$email = strtolower(trim($_POST['email'] ?? ''));
$code = trim($_POST['code'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Please provide a valid email address.');
}

if (!preg_match('/^\d{6}$/', $code)) {
    json_response(false, 'Please provide a valid 6-digit code.');
}

$savedCode = (string)($_SESSION['verify_email_otp'] ?? '');
$savedEmail = strtolower((string)($_SESSION['verify_email_address'] ?? ''));
$expiresAt = (int)($_SESSION['verify_email_expires'] ?? 0);

if ($savedCode === '' || $savedEmail === '' || $expiresAt === 0) {
    json_response(false, 'No active verification request. Send a new code first.');
}

if (time() > $expiresAt) {
    unset($_SESSION['verify_email_otp'], $_SESSION['verify_email_address'], $_SESSION['verify_email_expires']);
    json_response(false, 'Verification code expired. Please request a new one.');
}

if (!hash_equals($savedEmail, $email) || !hash_equals($savedCode, $code)) {
    json_response(false, 'Incorrect verification code.');
}

$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('UPDATE users SET verification_status = ? WHERE user_id = ?');
$verified = 'Verified';
$stmt->bind_param('si', $verified, $userId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    json_response(false, 'Unable to verify email right now. Please try again.');
}

$_SESSION['verification_status'] = 'Verified';
unset($_SESSION['verify_email_otp'], $_SESSION['verify_email_address'], $_SESSION['verify_email_expires']);

json_response(true, 'Your email is now verified.');
