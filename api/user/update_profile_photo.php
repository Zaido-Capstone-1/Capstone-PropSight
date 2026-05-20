<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';
require_not_blacklisted();

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'user')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request token.']);
    exit;
}

if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid image file.']);
    exit;
}

require_once __DIR__ . '/../../includes/secure_upload.php';

$userId = (int) $_SESSION['user_id'];

$uploadConfig = [
    'allowedMimeTypes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
    'maxFileSize' => 2 * 1024 * 1024,
    'uploadDir' => 'uploads/profile_photos/',
    'createUniqueNames' => true,
];

$uploader = new SecureFileUpload($uploadConfig);
$result = $uploader->processUpload($_FILES['profile_photo'], 'user_' . $userId . '_');

if (!$result['success']) {
    echo json_encode(['success' => false, 'message' => $result['error']]);
    exit;
}

$destDb = $result['relative_path'];

$stmt = $conn->prepare('UPDATE users SET profile_photo = ? WHERE user_id = ?');
$stmt->bind_param('si', $destDb, $userId);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Could not save profile photo. Try again.']);
    exit;
}

$_SESSION['profile_photo'] = $destDb;
echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully.', 'path' => $destDb]);
exit;