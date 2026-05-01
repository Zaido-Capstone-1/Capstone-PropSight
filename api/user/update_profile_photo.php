<?php
require_once __DIR__ . '/../../includes/session_params.php';
session_start();
require_once '../../includes/db.php';

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

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$tmpPath = $_FILES['profile_photo']['tmp_name'];
$mime = mime_content_type($tmpPath);

if (!isset($allowed[$mime])) {
    $_SESSION['toast_error'] = 'Only JPG, PNG, and WEBP files are allowed.';
    header('Location: ../../pages/user/profile.php');
    exit;
}

$maxSize = 2 * 1024 * 1024;
if ((int)$_FILES['profile_photo']['size'] > $maxSize) {
    $_SESSION['toast_error'] = 'Profile photo must be 2MB or less.';
    header('Location: ../../pages/user/profile.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$ext = $allowed[$mime];
$fileName = 'user_' . $userId . '_' . time() . '.' . $ext;

$uploadDirFs = __DIR__ . '/../../uploads/profile_photos';
if (!is_dir($uploadDirFs)) {
    mkdir($uploadDirFs, 0775, true);
}

$destFs = $uploadDirFs . '/' . $fileName;
$destDb = 'uploads/profile_photos/' . $fileName;

if (!move_uploaded_file($tmpPath, $destFs)) {
    $_SESSION['toast_error'] = 'Failed to upload profile photo. Try again.';
    header('Location: ../../pages/user/profile.php');
    exit;
}

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
