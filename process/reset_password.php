<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_params.php';
session_start();
$c = require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

applyRateLimit($conn, 'reset_password', 10, 900); // 10 attempts / 15 min per IP

$token = trim($_POST['token'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$confirm = (string) ($_POST['confirm'] ?? '');

if ($token === '') {
    echo json_encode(['success' => false, 'message' => 'Missing reset token.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

if ($password !== $confirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

$pwr = $c->passwordReset();

try {
    $reset = $pwr->validateToken($token);
    $pwr->applyReset((int) $reset['user_id'], (int) $reset['id'], $password);
    echo json_encode(['success' => true, 'message' => 'Your password has been updated. You can now log in with your new password.']);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log('Password reset apply error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}