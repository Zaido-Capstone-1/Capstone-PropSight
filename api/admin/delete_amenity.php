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
    echo json_encode(['status'=>'error','message'=>'Invalid request.']); exit;
}
require_csrf_token();


$amenity_id = (int)($_POST['amenity_id'] ?? 0);
if ($amenity_id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid amenity.']); exit; }

$stmt = $conn->prepare("DELETE FROM amenities WHERE amenity_id=?");
$stmt->bind_param('i', $amenity_id);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['status'=>'success','message'=>'Amenity removed.']);
} else {
    $err = $stmt->error; $stmt->close();
    echo json_encode(['status'=>'error','message'=>'Delete failed: '.$err]);
}
$conn->close();