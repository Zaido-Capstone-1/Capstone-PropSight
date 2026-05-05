<?php
require_once __DIR__ . '/../../includes/session_params.php';
session_start();
require_once '../../includes/db.php';
require_not_blacklisted();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['toast_error'] = 'Invalid request token.';
    header('Location: ../../pages/user/profile.php');
    exit;
}

if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['toast_error'] = 'Please select a valid image file.';
    header('Location: ../../pages/user/profile.php');
    exit;
}

require_once '../../includes/secure_upload.php';

// Get user ID from session
$userId = (int)$_SESSION['user_id'];

// Configure upload for profile photos
$uploadConfig = [
    'allowedMimeTypes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
    'maxFileSize' => 2 * 1024 * 1024, // 2MB
    'uploadDir' => 'uploads/profile_photos/',
    'createUniqueNames' => true,
];

$secureUpload = new SecureFileUpload($uploadConfig);
$result = $secureUpload->processUpload($_FILES['profile_photo'], 'user_' . $userId . '_');

if (!$result['success']) {
    $_SESSION['toast_error'] = $result['error'];
    header('Location: ../../pages/user/profile.php');
    exit;
}

$destDb = $result['relative_path'];

$stmt = $conn->prepare('UPDATE users SET profile_photo = ? WHERE user_id = ?');
$stmt->bind_param('si', $destDb, $userId);

if (!$stmt->execute()) {
    $_SESSION['toast_error'] = 'Could not save profile photo. Try again.';
    header('Location: ../../pages/user/profile.php');
    exit;
}

$_SESSION['profile_photo'] = $destDb;
$_SESSION['toast_success'] = 'Profile picture updated successfully.';
header('Location: ../../pages/user/profile.php');
exit;
?>
