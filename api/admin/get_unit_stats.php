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
        COUNT(*)                      AS total,
        SUM(status = 'occupied')      AS occupied,
        SUM(status = 'vacant')        AS vacant,
        SUM(status = 'maintenance')   AS maintenance
    FROM units
");

$stats = $result->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'stats'  => [
        'total'       => (int) $stats['total'],
        'occupied'    => (int) $stats['occupied'],
        'vacant'      => (int) $stats['vacant'],
        'maintenance' => (int) $stats['maintenance'],
    ]
]);

$conn->close();
exit;