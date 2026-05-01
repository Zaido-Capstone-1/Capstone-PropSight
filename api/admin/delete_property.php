<?php
ob_start();
include '../../includes/session.php';
require_once '../../includes/db.php';
ob_end_clean();
header('Content-Type: application/json');
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
require_csrf_token();


$property_id = (int) ($_POST['property_id'] ?? 0);

if ($property_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid property ID.']);
    exit;
}

$check = mysqli_prepare($conn, "SELECT property_id, property_name FROM properties WHERE property_id = ?");
if (!$check) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($check, 'i', $property_id);
mysqli_stmt_execute($check);
$check_result = mysqli_stmt_get_result($check);

if (mysqli_num_rows($check_result) === 0) {
    mysqli_stmt_close($check);
    echo json_encode(['status' => 'error', 'message' => 'Property not found.']);
    exit;
}

$property = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check);

$unit_check = mysqli_prepare($conn, "SELECT COUNT(*) AS unit_count FROM units WHERE property_id = ?");
if (!$unit_check) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($unit_check, 'i', $property_id);
mysqli_stmt_execute($unit_check);
$unit_result = mysqli_stmt_get_result($unit_check);
$unit_data   = mysqli_fetch_assoc($unit_result);
mysqli_stmt_close($unit_check);

if ((int) $unit_data['unit_count'] > 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => "Cannot delete \"{$property['property_name']}\" — remove all units first."
    ]);
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM properties WHERE property_id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $property_id);

if (mysqli_stmt_execute($stmt)) {
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No rows deleted. Property may not exist.']);
        exit;
    }

    echo json_encode([
        'status'  => 'success',
        'message' => "\"{$property['property_name']}\" has been deleted."
    ]);
} else {
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Delete failed: ' . $err
    ]);
}

mysqli_close($conn);
exit;