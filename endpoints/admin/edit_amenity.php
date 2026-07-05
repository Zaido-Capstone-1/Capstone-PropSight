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


$amenity_id = (int) ($_POST['amenity_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$status = trim($_POST['status'] ?? 'available');
$icon = trim($_POST['icon'] ?? 'pool');

if ($amenity_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid amenity (id=' . $amenity_id . ').']);
    exit;
}
if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Name is required.']);
    exit;
}

$allowed = ['available', 'unavailable', 'maintenance'];
if (!in_array($status, $allowed))
    $status = 'available';

$stmt = $conn->prepare("UPDATE amenities SET name=?, icon=?, status=? WHERE amenity_id=?");
$stmt->bind_param('sssi', $name, $icon, $status, $amenity_id);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => '"' . $name . '" updated successfully.']);
} else {
    $err = $stmt->error;
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $err]);
}
$conn->close();