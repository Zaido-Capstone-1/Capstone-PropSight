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

// Validate unit exists & get property_id
$unitCheck = $conn->query("SELECT unit_id, property_id FROM units WHERE unit_id=$unit_id LIMIT 1");
if (!$unitCheck || $unitCheck->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Unit not found.']);
    exit;
}
$unitRow = $unitCheck->fetch_assoc();
$property_id = (int) $unitRow['property_id'];

// Sanitize fields
$unit_number = trim($_POST['unit_number'] ?? '');
$unit_name = trim($_POST['unit_name'] ?? '');
$unit_type = trim($_POST['unit_type'] ?? '');
$floor = (int) ($_POST['floor'] ?? 0);
$rent_amount = (float) ($_POST['rent_amount'] ?? 0);
$status = trim($_POST['status'] ?? 'vacant');
$tenant_name = trim($_POST['tenant_name'] ?? '');
$description = mb_substr(trim($_POST['description'] ?? ''), 0, 500);

$allowed_statuses = ['occupied', 'vacant', 'maintenance'];
if (!in_array($status, $allowed_statuses))
    $status = 'vacant';

// Amenity IDs – only allow IDs belonging to this unit's property
$raw_amenities = array_map('intval', (array) ($_POST['amenity_ids'] ?? []));
$raw_amenities = array_values(array_filter($raw_amenities, fn($id) => $id > 0));
$valid_amenity_ids = [];
if (!empty($raw_amenities)) {
    $placeholders = implode(',', $raw_amenities);
    $validRes = $conn->query("
        SELECT amenity_id FROM amenities
        WHERE amenity_id IN ($placeholders)
          AND property_id = $property_id
          AND status = 'available'
    ");
    while ($row = $validRes->fetch_assoc()) {
        $valid_amenity_ids[] = (int) $row['amenity_id'];
    }
}

$conn->begin_transaction();
try {
    // Update core unit fields
    $stmt = $conn->prepare("
        UPDATE units
        SET unit_number = ?,
            unit_name   = ?,
            unit_type   = ?,
            floor       = ?,
            rent_amount = ?,
            status      = ?,
            tenant_name = ?,
            description = ?
        WHERE unit_id = ?
    ");
    if (!$stmt)
        throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param(
        'sssidsssi',
        $unit_number,
        $unit_name,
        $unit_type,
        $floor,
        $rent_amount,
        $status,
        $tenant_name,
        $description,
        $unit_id
    );
    if (!$stmt->execute())
        throw new Exception($stmt->error);
    $stmt->close();

    // Replace amenities
    if (!$conn->query("DELETE FROM unit_amenities WHERE unit_id=$unit_id")) {
        throw new Exception($conn->error);
    }
    if (!empty($valid_amenity_ids)) {
        $ins = $conn->prepare("INSERT IGNORE INTO unit_amenities (unit_id, amenity_id) VALUES (?, ?)");
        foreach ($valid_amenity_ids as $aid) {
            $ins->bind_param('ii', $unit_id, $aid);
            $ins->execute();
        }
        $ins->close();
    }

    $remove_images = (array) ($_POST['remove_images'] ?? []);
    foreach ($remove_images as $rpath) {
        $rpath = trim($rpath);
        if (!$rpath)
            continue;
        $rpath = ltrim($rpath, '/');
        // Delete from DB
        $del = $conn->prepare("DELETE FROM unit_images WHERE unit_id=? AND image_path=?");
        $del->bind_param('is', $unit_id, $rpath);
        $del->execute();
        $del->close();
        // Delete file from disk
        $fullPath = __DIR__ . '/../../' . $rpath;
        if (file_exists($fullPath))
            @unlink($fullPath);
    }

    // Handle new image uploads
    if (!empty($_FILES['unit_images']['name'][0])) {
        $upload_dir = __DIR__ . '/../../uploads/units/' . $unit_id . '/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0755, true);

        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $max_size = 5 * 1024 * 1024;
        $file_count = count($_FILES['unit_images']['name']);

        // Get current max sort_order
        $sortRes = $conn->query("SELECT COALESCE(MAX(sort_order), -1) AS max_sort FROM unit_images WHERE unit_id=$unit_id");
        $sortRow = $sortRes->fetch_assoc();
        $sort_start = (int) $sortRow['max_sort'] + 1;

        $img_stmt = $conn->prepare("INSERT INTO unit_images (unit_id, image_path, sort_order) VALUES (?, ?, ?)");

        for ($i = 0; $i < $file_count; $i++) {
            $tmp = $_FILES['unit_images']['tmp_name'][$i];
            $mime = mime_content_type($tmp);
            $size = $_FILES['unit_images']['size'][$i];
            $error = $_FILES['unit_images']['error'][$i];

            if ($error !== UPLOAD_ERR_OK)
                continue;
            if ($size > $max_size)
                continue;
            if (!in_array($mime, $allowed_types))
                continue;

            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            };
            $filename = uniqid('unit_', true) . '.' . $ext;
            $dest = $upload_dir . $filename;

            if (!move_uploaded_file($tmp, $dest))
                continue;

            $web_path = 'uploads/units/' . $unit_id . '/' . $filename;
            $sort_order = $sort_start + $i;
            $img_stmt->bind_param('isi', $unit_id, $web_path, $sort_order);
            $img_stmt->execute();
        }

        $img_stmt->close();
    }

    $conn->commit();

    // Return updated unit data for card refresh
    $res = $conn->query("
        SELECT u.*, p.property_name
        FROM units u
        LEFT JOIN properties p ON p.property_id = u.property_id
        WHERE u.unit_id = $unit_id
        LIMIT 1
    ");
    $updatedUnit = $res ? $res->fetch_assoc() : [];

    // Fetch images
    $imgs = [];
    $ir = $conn->query("SELECT image_path FROM unit_images WHERE unit_id=$unit_id ORDER BY sort_order ASC LIMIT 6");
    while ($ir && $row = $ir->fetch_assoc())
        $imgs[] = $row['image_path'];

    echo json_encode([
        'status' => 'success',
        'message' => 'Unit updated successfully.',
        'unit' => array_merge($updatedUnit, ['images' => $imgs]),
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()]);
}

$conn->close();
exit;