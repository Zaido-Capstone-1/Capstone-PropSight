<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

function json_out(bool $success, string $message): never
{
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(false, 'Invalid request method.');
}

$token       = trim($_POST['token']    ?? '');
$newPassword = $_POST['password']      ?? '';
$confirm     = $_POST['confirm']       ?? '';

if ($token === '') {
    json_out(false, 'Reset token is missing.');
}
if (strlen($newPassword) < 8) {
    json_out(false, 'Password must be at least 8 characters.');
}
if ($newPassword !== $confirm) {
    json_out(false, 'Passwords do not match.');
}

$tokenEsc = mysqli_real_escape_string($conn, $token);

$row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT pr.id, pr.user_id, pr.expires_at, pr.used
     FROM password_resets pr
     WHERE pr.token = '$tokenEsc'
     LIMIT 1"
));

if (!$row) {
    json_out(false, 'This reset link is invalid or has already been used.');
}
if ((int)$row['used'] === 1) {
    json_out(false, 'This reset link has already been used. Please request a new one.');
}
if (strtotime($row['expires_at']) < time()) {
    json_out(false, 'This reset link has expired. Please request a new one.');
}

$userId = (int) $row['user_id'];
$hash   = password_hash($newPassword, PASSWORD_BCRYPT);
$hashEsc = mysqli_real_escape_string($conn, $hash);

// Update the password and mark token as used
mysqli_query($conn, "UPDATE users SET password='$hashEsc' WHERE user_id=$userId");
mysqli_query($conn, "UPDATE password_resets SET used=1 WHERE id={$row['id']}");

// Invalidate all sessions for this user (if login_attempts column exists)
mysqli_query($conn, "UPDATE users SET login_attempts=0, is_locked=0, locked_until=NULL WHERE user_id=$userId");

json_out(true, 'Your password has been reset successfully. You can now log in.');
