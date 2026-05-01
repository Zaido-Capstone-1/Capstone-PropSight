<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'user')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_csrf_token(true);

$confirmText = trim((string)($_POST['confirm_text'] ?? ''));
if ($confirmText !== 'DELETE') {
    echo json_encode(['success' => false, 'message' => 'Invalid confirmation text.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

mysqli_begin_transaction($conn);
try {
    $deleteQueries = [
        'DELETE FROM saved_units WHERE user_id = ?',
        'DELETE FROM loyalty_points WHERE user_id = ?',
        'DELETE FROM support_messages WHERE user_id = ?',
        'DELETE FROM support_tickets WHERE user_id = ?',
        'DELETE FROM messages WHERE from_user = ? OR to_user = ?',
        'DELETE FROM notifications WHERE user_id = ?',
        'DELETE FROM payment_methods WHERE user_id = ?',
        'DELETE FROM user_settings WHERE user_id = ?',
        'DELETE FROM bookings WHERE user_id = ?',
    ];

    foreach ($deleteQueries as $sql) {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Failed to prepare account cleanup query.');
        }
        if (substr_count($sql, '?') === 2) {
            $stmt->bind_param('ii', $userId, $userId);
        } else {
            $stmt->bind_param('i', $userId);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed while deleting account data.');
        }
        $stmt->close();
    }

    $userDelete = $conn->prepare('DELETE FROM users WHERE user_id = ? LIMIT 1');
    $userDelete->bind_param('i', $userId);
    if (!$userDelete->execute()) {
        $userDelete->close();
        throw new Exception('Could not remove user account.');
    }
    $userDelete->close();

    mysqli_commit($conn);

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    echo json_encode(['success' => true, 'message' => 'Your account has been permanently deleted.']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

