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

$userId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT profile_photo FROM users WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$currentPhoto = trim((string) ($row['profile_photo'] ?? ''));

if ($currentPhoto === '') {
    echo json_encode(['success' => false, 'message' => 'No profile photo to remove.']);
    exit;
}

// Only allow deleting files within the expected uploads/profile_photos/ directory,
// to prevent any possibility of path traversal deleting unrelated files.
$uploadsBase = realpath(__DIR__ . '/../../uploads/profile_photos');
$targetPath = realpath(__DIR__ . '/../../' . ltrim($currentPhoto, '/'));

if ($uploadsBase && $targetPath && strpos($targetPath, $uploadsBase) === 0 && is_file($targetPath)) {
    @unlink($targetPath);
}

$stmt = $conn->prepare("UPDATE users SET profile_photo = '' WHERE user_id = ?");
$stmt->bind_param('i', $userId);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Could not remove profile photo. Try again.']);
    exit;
}

$_SESSION['profile_photo'] = '';
echo json_encode(['success' => true, 'message' => 'Profile picture removed.']);
exit;