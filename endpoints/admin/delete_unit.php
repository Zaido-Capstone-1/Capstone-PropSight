<?php
include '../../includes/session.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}
require_csrf_token();


$unit_id = (int) ($_POST['unit_id'] ?? 0);

if ($unit_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid unit ID.']);
    exit;
}

$check = $conn->prepare("SELECT unit_id, unit_number FROM units WHERE unit_id = ?");
$check->bind_param('i', $unit_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    $check->close();
    echo json_encode(['status' => 'error', 'message' => 'Unit not found.']);
    exit;
}

$unit = $result->fetch_assoc();
$check->close();

$stmt = $conn->prepare("DELETE FROM units WHERE unit_id = ?");
$stmt->bind_param('i', $unit_id);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode([
        'status'  => 'success',
        'message' => "\"{$unit['unit_number']}\" has been deleted."
    ]);
} else {
    $err = $stmt->error;
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $err]);
}

$conn->close();
exit;