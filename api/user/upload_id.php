<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'user')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_csrf_token(true);

if (!isset($_FILES['id_document']) || !is_array($_FILES['id_document'])) {
    echo json_encode(['success' => false, 'message' => 'No document uploaded.']);
    exit;
}

$file = $_FILES['id_document'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload failed. Please try again.']);
    exit;
}

$maxBytes = 5 * 1024 * 1024;
if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'File must be between 1 byte and 5MB.']);
    exit;
}

$allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
$ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Use JPG, PNG, or PDF.']);
    exit;
}

$uploadsRoot = realpath(__DIR__ . '/../../');
if ($uploadsRoot === false) {
    echo json_encode(['success' => false, 'message' => 'Server path is invalid.']);
    exit;
}
$targetDir = $uploadsRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'id_documents';
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    echo json_encode(['success' => false, 'message' => 'Could not create upload directory.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$safeName = 'user_' . $userId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Could not store uploaded file.']);
    exit;
}

// Notify admins that a user uploaded a new ID document.
$refPath = 'uploads/id_documents/' . $safeName;
$adminRes = mysqli_query($conn, "SELECT user_id FROM users WHERE role='admin' AND is_active=1");
while ($admin = mysqli_fetch_assoc($adminRes)) {
    $adminId = (int)$admin['user_id'];
    $title = 'New ID upload submitted';
    $body = 'User #' . $userId . ' uploaded a new verification ID document.';
    $link = $refPath;
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, type, title, body, link)
         VALUES (?, 'support', ?, ?, ?)"
    );
    $stmt->bind_param('isss', $adminId, $title, $body, $link);
    $stmt->execute();
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'message' => 'ID document uploaded. Our team will review it shortly.',
]);

