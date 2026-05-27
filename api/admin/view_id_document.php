<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

// Admin-only access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$userId = (int) ($_GET['user_id'] ?? 0);
if (!$userId) {
    http_response_code(400);
    exit('User ID required');
}

// Get the ID document path from database
$stmt = $conn->prepare("SELECT id_document_path FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !$row['id_document_path']) {
    http_response_code(404);
    exit('ID document not found');
}

// The database stores: "uploads/id_documents/filename.jpg"
$relativePath = $row['id_document_path'];

// Build absolute path from project root
$projectRoot = realpath(__DIR__ . '/../../');
$absolutePath = realpath($projectRoot . '/' . $relativePath);
$uploadsRoot = realpath($projectRoot . '/uploads/id_documents');

// Debug logging (remove after testing)
error_log("DEBUG ID Document:");
error_log("  relativePath: $relativePath");
error_log("  projectRoot: $projectRoot");
error_log("  absolutePath: $absolutePath");
error_log("  uploadsRoot: $uploadsRoot");
error_log("  file_exists: " . ($absolutePath && file_exists($absolutePath) ? 'YES' : 'NO'));

// Security: Ensure the file is within the allowed directory (prevent path traversal)
if ($absolutePath === false || strpos($absolutePath, $uploadsRoot) !== 0) {
    error_log("SECURITY: Path traversal attempt blocked - relativePath: $relativePath");
    http_response_code(403);
    exit('Invalid file path');
}

if (!file_exists($absolutePath)) {
    http_response_code(404);
    exit('File not found on disk');
}

// Determine MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($absolutePath);

// Set headers for inline display
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: inline; filename="' . basename($absolutePath) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, no-cache');

// Output file
readfile($absolutePath);
exit;