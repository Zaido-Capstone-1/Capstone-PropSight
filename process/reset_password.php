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

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm'] ?? '';

if ($token === '') {
    echo json_encode(['success' => false, 'message' => 'Reset token is missing.']);
    exit;
}

// Password validation
if ($password === '' || $confirmPassword === '') {
    echo json_encode(['success' => false, 'message' => 'Both password fields are required.']);
    exit;
}

if ($password !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain at least 1 uppercase letter and 1 number.']);
    exit;
}

// Validate token
$pwr = $c->passwordReset();

try {
    $resetRow = $pwr->validateToken($token);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Apply reset
try {
    $pwr->applyReset($resetRow['user_id'], $resetRow['id'], $password);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Your password has been reset successfully. You can now log in.']);