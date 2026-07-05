<?php
include '../../includes/session.php';
include '../../includes/db.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!$csrf || $csrf !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = trim($_POST['action'] ?? '');
$request_id = (int) ($_POST['request_id'] ?? 0);

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

if ($action === 'update_status') {
    $allowed = ['open', 'in_progress', 'pending', 'completed', 'closed'];
    $status = trim($_POST['status'] ?? '');
    if (!in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    $s = mysqli_real_escape_string($conn, $status);
    mysqli_query($conn, "UPDATE maintenance_requests SET request_status='$s' WHERE request_id=$request_id");
    echo json_encode(['success' => true, 'message' => 'Status updated']);

} elseif ($action === 'delete') {
    mysqli_query($conn, "DELETE FROM maintenance_requests WHERE request_id=$request_id");
    echo json_encode(['success' => true, 'message' => 'Request deleted']);

} else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}