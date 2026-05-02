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


$property_id = (int) ($_POST['property_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$status = trim($_POST['status'] ?? 'available');
$icon = trim($_POST['icon'] ?? 'pool');

if ($property_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid property.']);
    exit;
}
if ($name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Name is required.']);
    exit;
}

$allowed = ['available', 'unavailable', 'maintenance'];
if (!in_array($status, $allowed))
    $status = 'available';

$chk = $conn->prepare("SELECT property_name FROM properties WHERE property_id=?");
$chk->bind_param('i', $property_id);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Property not found.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO amenities (property_id, name, icon, status) VALUES (?,?,?,?)");
$stmt->bind_param('isss', $property_id, $name, $icon, $status);

if ($stmt->execute()) {
    $id = $conn->insert_id;
    $stmt->close();
    echo json_encode([
        'status' => 'success',
        'message' => '"' . $name . '" added successfully.',
        'amenity' => [
            'amenity_id' => $id,
            'property_id' => $property_id,
            'name' => $name,
            'icon' => $icon,
            'status' => $status,
        ]
    ]);
} else {
    $err = $stmt->error;
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $err]);
}
$conn->close();