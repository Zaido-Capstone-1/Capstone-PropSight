<?php
include '../../includes/session.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}
require_csrf_token();


if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$unit_id = (int) ($_POST['unit_id'] ?? 0);
if ($unit_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid unit.']);
    exit;
}

// Validate the unit exists
$unitCheck = $conn->query("SELECT unit_id, property_id FROM units WHERE unit_id=$unit_id LIMIT 1");
if (!$unitCheck || $unitCheck->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unit not found.']);
    exit;
}
$unitRow = $unitCheck->fetch_assoc();
$property_id = (int) $unitRow['property_id'];

// Sanitize incoming amenity IDs — only allow amenities that belong to this unit's property
$raw = array_map('intval', (array) ($_POST['amenity_ids'] ?? []));
$raw = array_values(array_filter($raw, fn($id) => $id > 0));

$valid_ids = [];
if (!empty($raw)) {
    $placeholders = implode(',', $raw);
    $validRes = $conn->query("
        SELECT amenity_id FROM amenities
        WHERE amenity_id IN ($placeholders)
          AND property_id = $property_id
          AND status = 'available'
    ");
    while ($row = $validRes->fetch_assoc()) {
        $valid_ids[] = (int) $row['amenity_id'];
    }
}

// Replace amenities: delete all existing, insert the checked ones
$conn->begin_transaction();
try {
    if (!$conn->query("DELETE FROM unit_amenities WHERE unit_id=$unit_id")) {
        throw new Exception($conn->error);
    }
    if (!empty($valid_ids)) {
        $ins = $conn->prepare("INSERT IGNORE INTO unit_amenities (unit_id, amenity_id) VALUES (?, ?)");
        foreach ($valid_ids as $aid) {
            $ins->bind_param('ii', $unit_id, $aid);
            $ins->execute();
        }
        $ins->close();
    }
    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Amenities updated.', 'count' => count($valid_ids)]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Failed to update: ' . $e->getMessage()]);
}

$conn->close();
exit;
