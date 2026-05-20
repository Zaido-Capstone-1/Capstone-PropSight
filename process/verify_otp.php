<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
header('Content-Type: application/json');

require_once '../includes/db.php';

function out(bool $ok, string $msg, array $extra = []): never
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

if (empty($_SESSION['login']) || empty($_SESSION['user_id']) || empty($_SESSION['pending_verification'])) {
    out(false, 'Unauthorized.');
}

$code = trim($_POST['code'] ?? '');

if (!preg_match('/^\d{6}$/', $code)) {
    out(false, 'Please enter the 6-digit code.');
}

$savedOtp = (string) ($_SESSION['verify_otp'] ?? '');
$expiresAt = (int) ($_SESSION['verify_otp_expires'] ?? 0);

if ($savedOtp === '' || $expiresAt === 0) {
    out(false, 'No active code found. Please request a new one.');
}

if (time() > $expiresAt) {
    unset($_SESSION['verify_otp'], $_SESSION['verify_method'], $_SESSION['verify_otp_expires']);
    out(false, 'Code has expired. Please request a new one.');
}

if (!hash_equals($savedOtp, $code)) {
    out(false, 'Incorrect code. Please try again.');
}

// Mark as verified in DB
$userId = (int) $_SESSION['user_id'];
$stmt = $conn->prepare("UPDATE users SET verification_status = 'Verified' WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    out(false, 'Could not verify your account. Please try again.');
}

// Clear pending state — full access granted
unset(
    $_SESSION['pending_verification'],
    $_SESSION['verify_otp'],
    $_SESSION['verify_method'],
    $_SESSION['verify_otp_expires'],
    $_SESSION['otp_last_sent']
);
$_SESSION['verification_status'] = 'Verified';

$role = $_SESSION['role'] ?? 'user';
$redirect = $role === 'admin' ? 'pages/admin/index.php' : 'pages/user/user-dashboard.php';

out(true, 'Verified! Taking you to your dashboard…', ['redirect' => $redirect]);
