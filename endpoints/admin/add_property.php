<?php
include '../../includes/session.php';
require_once '../../includes/db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../pages/admin/add_property.php');
    exit;
}
require_csrf_token();

$name = trim($_POST['name'] ?? '');
$type = trim($_POST['type'] ?? '');
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$zip = trim($_POST['zip'] ?? '');
$lat = trim($_POST['latitude'] ?? '');
$lng = trim($_POST['longitude'] ?? '');

/* Validate lat/lng — accept empty (optional) but reject non-numeric */
$latVal = ($lat !== '') ? (float) $lat : null;
$lngVal = ($lng !== '') ? (float) $lng : null;
if ($lat !== '' && !is_numeric($lat))
    $latVal = null;
if ($lng !== '' && !is_numeric($lng))
    $lngVal = null;

$errors = [];

if ($name === '')
    $errors['name'] = 'Property name is required.';
if ($address === '')
    $errors['address'] = 'Street address is required.';
if ($city === '')
    $errors['city'] = 'City is required.';

if ($type === '')
    $type = 'Residential';

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old'] = $_POST;
    header('Location: ../../pages/admin/add_property.php');
    exit;
}

$full_address = $address;
if ($city !== '')
    $full_address .= ', ' . $city;
if ($state !== '')
    $full_address .= ', ' . $state;
if ($zip !== '')
    $full_address .= ' ' . $zip;

$status = 'Active';

/*
 * NOTE: Run this ALTER TABLE once on your database if latitude/longitude
 *       columns don't exist yet:
 *
 *   ALTER TABLE `properties`
 *     ADD COLUMN `latitude`  DECIMAL(10,7) DEFAULT NULL,
 *     ADD COLUMN `longitude` DECIMAL(10,7) DEFAULT NULL;
 */

/* Check whether lat/lng columns exist to stay backward-compatible */
$hasCoords = false;
$colCheck = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'properties'
       AND COLUMN_NAME IN ('latitude','longitude')"
);
if ($colCheck) {
    $row = mysqli_fetch_assoc($colCheck);
    $hasCoords = ((int) $row['c'] === 2);
}

if ($hasCoords) {
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO properties
            (property_name, property_type, address, city, state, zip, status, latitude, longitude, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        $_SESSION['form_errors'] = ['db' => 'Database error: ' . mysqli_error($conn)];
        $_SESSION['form_old'] = $_POST;
        header('Location: ../../pages/admin/add_property.php');
        exit;
    }
    mysqli_stmt_bind_param($stmt, 'sssssssdd', $name, $type, $full_address, $city, $state, $zip, $status, $latVal, $lngVal);
} else {
    /* Fallback: save without coords */
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO properties
            (property_name, property_type, address, city, state, zip, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        $_SESSION['form_errors'] = ['db' => 'Database error: ' . mysqli_error($conn)];
        $_SESSION['form_old'] = $_POST;
        header('Location: ../../pages/admin/add_property.php');
        exit;
    }
    mysqli_stmt_bind_param($stmt, 'sssssss', $name, $type, $full_address, $city, $state, $zip, $status);
}

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $_SESSION['form_success'] = true;
    header('Location: ../../pages/admin/add_property.php');
    exit;
} else {
    $err_msg = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['form_errors'] = ['db' => 'Failed to save property: ' . $err_msg];
    $_SESSION['form_old'] = $_POST;
    header('Location: ../../pages/admin/add_property.php');
    exit;
}