<?php
include '../../includes/session.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}


$result = $conn->query("SELECT
    COUNT(DISTINCT p.property_id)                AS total,
    SUM(u.status = 'occupied')                   AS occupied,
    SUM(u.status = 'vacant')                     AS vacant,
    SUM(u.status = 'maintenance')                AS maintenance,
    SUM(CASE WHEN
        MONTH(p.created_at) = MONTH(NOW()) AND
        YEAR(p.created_at)  = YEAR(NOW())
        THEN 1 ELSE 0 END)                       AS new_this_month
FROM properties p
LEFT JOIN units u ON u.property_id = p.property_id
");

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}

$stats = $result->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'stats' => [
        'total' => (int) $stats['total'],
        'occupied' => (int) $stats['occupied'],
        'vacant' => (int) $stats['vacant'],
        'maintenance' => (int) $stats['maintenance'],
        'new_this_month' => (int) $stats['new_this_month'],
    ]
]);

$conn->close();
exit;