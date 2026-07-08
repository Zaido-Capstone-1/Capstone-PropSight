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

$email = trim(strtolower(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
  exit;
}

if (strlen($email) > 254 || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
  echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
  exit;
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$pwr = $c->passwordReset();

// IP rate limit 
if ($pwr->isIpRateLimited($ip)) {
  echo json_encode(['success' => true, 'message' => "If that email is registered and verified, you'll receive a reset link shortly. Check your inbox (and spam folder)."]);
  exit;
}

$genericSuccess = ['success' => true, 'message' => "If that email is registered and verified, you'll receive a reset link shortly. Check your inbox (and spam folder)."];

$user = $pwr->findActiveUser($email);
$pwr->recordAttempt($email, $ip, $user !== null);

if (!$user) {
  echo json_encode(['success' => false, 'message' => "No account found with that email address. Please check and try again, or sign up if you don't have an account."]);
  exit;
}

// Verification check 
$isAdmin = strtolower($user['role'] ?? '') === 'admin';
if (!$isAdmin && ($user['verification_status'] ?? '') !== 'Verified') {
  echo json_encode(['success' => false, 'message' => 'Your email address needs to be verified before you can reset your password. Check your inbox for the verification link, or contact support.']);
  exit;
}

// Per-user rate limit 
if ($pwr->isUserRateLimited($user['user_id'])) {
  echo json_encode(['success' => true, 'message' => 'A reset link was recently sent. Please check your email or wait a few minutes before requesting another.']);
  exit;
}

// Generate token and send email 
try {
  $token = $pwr->generateToken($user['user_id'], $email);
  $resetLink = $pwr->buildResetLink($token);
  $c->authEmail()->sendPasswordReset($email, $user['first_name'], $resetLink, $pwr->expiryMinutes());
  echo json_encode(['success' => true, 'message' => 'Reset link sent! Check your email (and spam folder) for instructions.']);
} catch (\RuntimeException $e) {
  $msg = $e->getMessage();
  if (str_contains($msg, 'configuration')) {
    echo json_encode(['success' => false, 'message' => 'Email service is not properly configured. Please contact support.']);
  } elseif (str_contains($msg, 'authentication')) {
    echo json_encode(['success' => false, 'message' => 'Email service authentication failed. Please contact support.']);
  } elseif (str_contains($msg, 'timeout') || str_contains($msg, 'connection')) {
    echo json_encode(['success' => false, 'message' => 'Email service is temporarily unavailable. Please try again in a few moments.']);
  } else {
    echo json_encode(['success' => false, 'message' => 'Could not send reset email. Please try again later or contact support.']);
  }
  error_log('Password reset send error: ' . $msg);
}