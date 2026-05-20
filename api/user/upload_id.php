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

require_csrf_token(true);

$userId = (int) $_SESSION['user_id'];

// Block re-upload if already approved
$statusRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id_verified FROM users WHERE user_id=$userId LIMIT 1"));
if (($statusRow['id_verified'] ?? '') === 'approved') {
    echo json_encode(['success' => false, 'message' => 'Your ID is already verified. No need to re-upload.']);
    exit;
}

// ── File presence ────────────────────────────────────────────
if (!isset($_FILES['id_document']) || !is_array($_FILES['id_document'])) {
    echo json_encode(['success' => false, 'message' => 'No file received.']);
    exit;
}

$file = $_FILES['id_document'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error. Please try again.']);
    exit;
}

// ── Size: 50 KB minimum, 5 MB maximum ───────────────────────
$minBytes = 50 * 1024;       // 50 KB — prevents blank/corrupt files
$maxBytes = 5 * 1024 * 1024; // 5 MB
$size = (int) ($file['size'] ?? 0);
if ($size < $minBytes) {
    echo json_encode(['success' => false, 'message' => 'File is too small (min 50 KB). Please upload a clear photo of your ID.']);
    exit;
}
if ($size > $maxBytes) {
    echo json_encode(['success' => false, 'message' => 'File is too large (max 5 MB). Please compress and try again.']);
    exit;
}

// ── Extension: JPG/PNG only (no PDF — image is required for dimension check) ──
$ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG or PNG images are accepted. Please photograph your ID and upload the image.']);
    exit;
}

// ── Real MIME type via finfo (not trusting the extension alone) ──
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowedMimes = ['image/jpeg', 'image/png'];
if (!in_array($mimeType, $allowedMimes, true)) {
    echo json_encode(['success' => false, 'message' => 'File content does not match a valid image. Please upload a real JPG or PNG photo.']);
    exit;
}

// ── Image dimensions: minimum 400 × 300 px ───────────────────
$imgInfo = @getimagesize($file['tmp_name']);
if (!$imgInfo || $imgInfo[0] < 400 || $imgInfo[1] < 300) {
    echo json_encode(['success' => false, 'message' => 'Image is too small or unreadable. Minimum size is 400×300 px. Please take a clearer photo.']);
    exit;
}

// ── Aspect ratio sanity check: must look like a card/document (not a tiny square selfie) ──
$ratio = $imgInfo[0] / $imgInfo[1]; // width ÷ height
if ($ratio < 0.5 || $ratio > 3.5) {
    echo json_encode(['success' => false, 'message' => 'Image proportions look wrong. Please upload a straight-on photo of your ID card or document.']);
    exit;
}

// ── Store file ───────────────────────────────────────────────
$uploadsRoot = realpath(__DIR__ . '/../../');
if ($uploadsRoot === false) {
    echo json_encode(['success' => false, 'message' => 'Server path error.']);
    exit;
}
$targetDir = $uploadsRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'id_documents';
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    echo json_encode(['success' => false, 'message' => 'Could not create upload directory.']);
    exit;
}

$safeName = 'user_' . $userId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'message' => 'Could not save file. Please try again.']);
    exit;
}

$refPath = 'uploads/id_documents/' . $safeName;

// ── Auto-approve immediately ─────────────────────────────────
$updateStmt = $conn->prepare(
    "UPDATE users SET id_verified = 'approved', id_document_path = ?, id_verified_at = NOW(), id_reject_reason = NULL WHERE user_id = ?"
);
$updateStmt->bind_param('si', $refPath, $userId);
$updateStmt->execute();
$updateStmt->close();

$_SESSION['id_verified'] = 'approved';

// Notify the user
$notifTitle = 'Identity Verified ✓';
$notifBody = 'Your government ID has been accepted. You can now book a unit!';
$notifLink = 'pages/user/profile.php';
$notifStmt = $conn->prepare(
    "INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, 'support', ?, ?, ?)"
);
$notifStmt->bind_param('isss', $userId, $notifTitle, $notifBody, $notifLink);
$notifStmt->execute();
$notifStmt->close();

echo json_encode([
    'success' => true,
    'id_verified' => 'approved',
    'message' => 'ID verified! You can now book a unit.',
]);