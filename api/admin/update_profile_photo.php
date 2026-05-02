<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/session_params.php';
session_start();
header('Content-Type: application/json');

function json_out(bool $ok, string $msg, array $extra = []): never
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

// Auth guard
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    json_out(false, 'Unauthorized.');
}

require_once __DIR__ . '/../../includes/db.php';

$action = trim((string)($_POST['action'] ?? ''));
$adminId = (int)$_SESSION['user_id'];

// ── Upload ─────────────────────────────────────────────────────────────────
if ($action === 'upload_photo') {
    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        json_out(false, 'No file received or upload error.');
    }

    $file     = $_FILES['photo'];
    $mime     = mime_content_type($file['tmp_name']);
    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        json_out(false, 'Only JPEG, PNG, WebP, or GIF images are allowed.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        json_out(false, 'Image must be under 5 MB.');
    }

    $ext      = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'gif',
    };
    $uploadDir = __DIR__ . '/../../assets/images/profile_photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename  = 'admin_' . $adminId . '_' . time() . '.' . $ext;
    $dest      = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        json_out(false, 'Failed to save the uploaded file.');
    }

    // Remove old photo file if it exists
    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT profile_photo FROM users WHERE user_id=$adminId LIMIT 1"));
    if (!empty($old['profile_photo'])) {
        $oldPath = __DIR__ . '/../../' . ltrim($old['profile_photo'], '/');
        if (is_file($oldPath)) @unlink($oldPath);
    }

    $photoPath = 'assets/images/profile_photos/' . $filename;
    $escaped   = mysqli_real_escape_string($conn, $photoPath);
    mysqli_query($conn, "UPDATE users SET profile_photo='$escaped' WHERE user_id=$adminId");

    // Update session
    $_SESSION['profile_photo'] = $photoPath;

    json_out(true, 'Photo updated.', ['photo_url' => $photoPath]);
}

// ── Remove ─────────────────────────────────────────────────────────────────
if ($action === 'remove_photo') {
    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT profile_photo FROM users WHERE user_id=$adminId LIMIT 1"));
    if (!empty($old['profile_photo'])) {
        $oldPath = __DIR__ . '/../../' . ltrim($old['profile_photo'], '/');
        if (is_file($oldPath)) @unlink($oldPath);
    }
    mysqli_query($conn, "UPDATE users SET profile_photo=NULL WHERE user_id=$adminId");
    $_SESSION['profile_photo'] = '';
    json_out(true, 'Photo removed.');
}

json_out(false, 'Unknown action.');
