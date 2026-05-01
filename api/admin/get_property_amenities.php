<?php
include '../../includes/session.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$property_id = (int) ($_GET['property_id'] ?? 0);

if ($property_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid property.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT amenity_id, name, icon
    FROM amenities
    WHERE property_id = ?
      AND status = 'available'
    ORDER BY name ASC
");

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Query error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $property_id);
$stmt->execute();
$result = $stmt->get_result();

$amenities = [];
while ($row = $result->fetch_assoc()) {
    $amenities[] = [
        'amenity_id' => (int) $row['amenity_id'],
        'name' => $row['name'],
        'icon' => $row['icon'] ?? '',
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['status' => 'success', 'amenities' => $amenities]);
exit;