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


$unit_number = trim($_POST['unit_number'] ?? '');
$unit_name = trim($_POST['unit_name'] ?? '');
$property_id = (int) ($_POST['property_id'] ?? 0);
$unit_type = trim($_POST['unit_type'] ?? '');
$floor = (int) ($_POST['floor'] ?? 0);
$rent_amount = (float) ($_POST['rent_amount'] ?? 0);
$status = trim($_POST['status'] ?? 'vacant');
$tenant_name = trim($_POST['tenant_name'] ?? '');
$description = trim($_POST['description'] ?? '');
if (mb_strlen($description) > 500)
    $description = mb_substr($description, 0, 500);

$raw_amenities = $_POST['amenity_ids'] ?? [];
$amenity_ids = array_values(array_filter(array_map('intval', (array) $raw_amenities)));

error_log('[process_add_unit] amenity_ids received: ' . json_encode($amenity_ids));

if ($property_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a property.']);
    exit;
}

$allowed_statuses = ['occupied', 'vacant', 'maintenance'];
if (!in_array($status, $allowed_statuses))
    $status = 'vacant';

$check = $conn->prepare("SELECT property_id, property_name FROM properties WHERE property_id = ?");
if (!$check) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$check->bind_param('i', $property_id);
$check->execute();
$check_result = $check->get_result();
if ($check_result->num_rows === 0) {
    $check->close();
    echo json_encode(['status' => 'error', 'message' => 'Property not found.']);
    exit;
}
$property = $check_result->fetch_assoc();
$check->close();

if ($unit_number !== '') {
    $dup = $conn->prepare("SELECT unit_id FROM units WHERE unit_number = ? AND property_id = ?");
    if (!$dup) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $dup->bind_param('si', $unit_number, $property_id);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $dup->close();
        echo json_encode(['status' => 'error', 'message' => "Unit \"$unit_number\" already exists in this property."]);
        exit;
    }
    $dup->close();
}

$display_name = $unit_number !== '' ? $unit_number : ($unit_name !== '' ? $unit_name : 'New unit');

$stmt = $conn->prepare("
    INSERT INTO units (property_id, unit_number, unit_name, unit_type, floor, rent_amount, status, tenant_name, description)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param('isssidss' . 's', $property_id, $unit_number, $unit_name, $unit_type, $floor, $rent_amount, $status, $tenant_name, $description);

if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Insert failed: ' . $err]);
    exit;
}
$unit_id = $conn->insert_id;
$stmt->close();

$saved_amenity_ids = [];

if (!empty($amenity_ids)) {
    $placeholders = implode(',', array_fill(0, count($amenity_ids), '?'));
    $types = str_repeat('i', count($amenity_ids));

    $val_stmt = $conn->prepare("
        SELECT amenity_id, name, icon
        FROM amenities
        WHERE amenity_id IN ($placeholders)
          AND property_id = ?
    ");

    if ($val_stmt) {
        $bind_params = array_merge($amenity_ids, [$property_id]);
        $bind_types = $types . 'i';
        $val_stmt->bind_param($bind_types, ...$bind_params);
        $val_stmt->execute();
        $val_result = $val_stmt->get_result();

        $valid_amenities = [];
        while ($row = $val_result->fetch_assoc()) {
            $valid_amenities[] = $row;
        }
        $val_stmt->close();

        if (!empty($valid_amenities)) {
            $ins = $conn->prepare("
                INSERT IGNORE INTO unit_amenities (unit_id, amenity_id)
                VALUES (?, ?)
            ");
            if ($ins) {
                foreach ($valid_amenities as $am) {
                    $ins->bind_param('ii', $unit_id, $am['amenity_id']);
                    if ($ins->execute()) {
                        $saved_amenity_ids[] = [
                            'amenity_id' => (int) $am['amenity_id'],
                            'name' => $am['name'],
                            'icon' => $am['icon'] ?? '',
                        ];
                    }
                }
                $ins->close();
            }
        }
    }
}

$saved_images = [];

if (!empty($_FILES['unit_images']['name'][0])) {

    $upload_dir = __DIR__ . '/../../uploads/units/' . $unit_id . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $max_size = 5 * 1024 * 1024;
    $file_count = count($_FILES['unit_images']['name']);

    $img_stmt = $conn->prepare("
        INSERT INTO unit_images (unit_id, image_path, sort_order)
        VALUES (?, ?, ?)
    ");

    for ($i = 0; $i < $file_count; $i++) {
        $tmp = $_FILES['unit_images']['tmp_name'][$i];
        $origName = $_FILES['unit_images']['name'][$i];
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

        if ($img_stmt) {
            $img_stmt->bind_param('isi', $unit_id, $web_path, $i);
            $img_stmt->execute();
        }

        $saved_images[] = $web_path;
    }

    if ($img_stmt)
        $img_stmt->close();
}

echo json_encode([
    'status' => 'success',
    'message' => "\"$display_name\" added to {$property['property_name']}.",
    'unit' => [
        'unit_id' => $unit_id,
        'unit_number' => $unit_number,
        'unit_name' => $unit_name,
        'property_id' => $property_id,
        'property_name' => $property['property_name'],
        'unit_type' => $unit_type,
        'floor' => $floor,
        'rent_amount' => $rent_amount,
        'status' => $status,
        'tenant_name' => $tenant_name,
        'description' => $description,
        'images' => $saved_images,
        'amenities' => $saved_amenity_ids,
    ]
]);

$conn->close();
exit;