<?php
include '../../includes/session.php';
require_once '../../includes/db.php';
header('Content-Type: application/json');
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}


$row = $conn->query("
    SELECT COUNT(*) AS total,
           SUM(status='available')   AS available,
           SUM(status='unavailable') AS unavailable,
           SUM(status='maintenance') AS maintenance
    FROM amenities
")->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'stats' => [
        'total' => (int) $row['total'],
        'available' => (int) $row['available'],
        'unavailable' => (int) $row['unavailable'],
        'maintenance' => (int) $row['maintenance'],
    ]
]);
$conn->close();