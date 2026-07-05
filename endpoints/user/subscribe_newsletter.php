<?php
/**
 * API: /endpoints/user/subscribe_newsletter.php
 * POST action=subscribe (default) — subscribe the current user to the
 * newsletter, or update their stored email if they subscribe again with
 * a different one.
 * POST action=unsubscribe — remove the current user's subscription.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_not_blacklisted();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

require_csrf_token(true);

$userId = (int) $_SESSION['user_id'];
$action = ($_POST['action'] ?? 'subscribe') === 'unsubscribe' ? 'unsubscribe' : 'subscribe';

if ($action === 'unsubscribe') {
    $stmt = $conn->prepare("DELETE FROM newsletter_subscribers WHERE user_id=?");
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "You've been unsubscribed."]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    }
    $stmt->close();
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

if (strlen($email) > 254) {
    echo json_encode(['success' => false, 'message' => 'That email address is too long.']);
    exit;
}

$checkStmt = $conn->prepare("SELECT id FROM newsletter_subscribers WHERE user_id=? LIMIT 1");
$checkStmt->bind_param('i', $userId);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    $stmt = $conn->prepare("UPDATE newsletter_subscribers SET email=? WHERE user_id=?");
    $stmt->bind_param('si', $email, $userId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => "You're already subscribed \u{2014} we've updated your email."]);
} else {
    $stmt = $conn->prepare("INSERT INTO newsletter_subscribers (user_id, email) VALUES (?, ?)");
    $stmt->bind_param('is', $userId, $email);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Thanks! You're subscribed. \u{1F389}"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    }
    $stmt->close();
}