<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_not_blacklisted();

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$messageId = (int)($_GET['message_id'] ?? 0);
if (!$messageId) {
    http_response_code(400);
    exit('Message ID required');
}

$userId = (int)$_SESSION['user_id'];

// Verify user is part of this conversation (sender or recipient)
$stmt = $conn->prepare("
    SELECT attachment_url 
    FROM messages 
    WHERE message_id = ? 
      AND (from_user = ? OR to_user = ?) 
      AND attachment_url IS NOT NULL 
    LIMIT 1
");
$stmt->bind_param('iii', $messageId, $userId, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !$row['attachment_url']) {
    http_response_code(404);
    exit('Attachment not found or access denied');
}

$relativePath = $row['attachment_url'];
$absolutePath = realpath(__DIR__ . '/../' . $relativePath);
$uploadsRoot = realpath(__DIR__ . '/../uploads/messages/');

// Security: Ensure the file is within the allowed directory (prevent path traversal)
if ($absolutePath === false || strpos($absolutePath, $uploadsRoot) !== 0) {
    error_log("Potential path traversal attempt: $relativePath");
    http_response_code(403);
    exit('Invalid file path');
}

if (!file_exists($absolutePath)) {
    http_response_code(404);
    exit('File not found');
}

// Determine MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($absolutePath);

// Set headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: inline; filename="' . basename($absolutePath) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-cache');

// Output file
readfile($absolutePath);
exit;