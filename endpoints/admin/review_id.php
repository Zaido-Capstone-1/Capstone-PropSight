<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

$targetUserId = (int) ($_POST['user_id'] ?? 0); 
$action = $_POST['action'] ?? ''; // 'approve' or 'reject'
$rejectReason = trim($_POST['reject_reason'] ?? '');

if (!$targetUserId || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

if ($action === 'reject' && !$rejectReason) {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for rejection.']);
    exit;
}

// Get user details for logging/notification
$userStmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
$userStmt->bind_param('i', $targetUserId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

$userName = trim($user['first_name'] . ' ' . $user['last_name']);

if ($action === 'approve') {
    $stmt = $conn->prepare(
        "UPDATE users SET id_verified = 'approved', id_verified_at = NOW(), id_reject_reason = NULL WHERE user_id = ?"
    );
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $stmt->close();

    // Notify user
    $title = 'Identity Verified ✓';
    $body = 'Your government ID has been approved. You can now book a unit!';
    $responseMessage = "ID approved for {$userName}.";
} else {
    $stmt = $conn->prepare(
        "UPDATE users SET id_verified = 'rejected', id_verified_at = NULL, id_reject_reason = ? WHERE user_id = ?"
    );
    $stmt->bind_param('si', $rejectReason, $targetUserId);
    $stmt->execute();
    $stmt->close();

    // Notify user
    $title = 'ID Verification Failed';
    $body = 'Your ID was not approved: ' . $rejectReason . '. Please re-upload a clearer photo.';
    $responseMessage = "ID rejected for {$userName}.";
}

$link = 'pages/user/profile.php';
$notifStmt = $conn->prepare(
    "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'support', ?, ?, ?)"
);
$notifStmt->bind_param('isss', $targetUserId, $title, $body, $link);
$notifStmt->execute();
$notifStmt->close();

echo json_encode(['success' => true, 'action' => $action, 'message' => $responseMessage]);