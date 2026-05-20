<?php
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

$userRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT created_at, verification_status, profile_photo, id_verified, id_document_path, id_reject_reason FROM users WHERE user_id=$userId LIMIT 1"
));
$memberSince = $userRow ? date('F Y', strtotime($userRow['created_at'])) : 'Unknown';
$isVerified = ($userRow['verification_status'] ?? '') === 'Verified';
$idVerified = $userRow['id_verified'] ?? 'none';         // none | pending | approved | rejected
$idRejectReason = $userRow['id_reject_reason'] ?? '';
$idDocPath = $userRow['id_document_path'] ?? '';
$profilePhotoRaw = trim((string) ($userRow['profile_photo'] ?? ''));
$profilePhoto = $profilePhotoRaw !== '' ? '../../' . ltrim($profilePhotoRaw, '/') : '';

// Keep session in sync
$_SESSION['id_verified'] = $idVerified;

$totalStays = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM bookings WHERE user_id=$userId AND status='completed'"
))['c'] ?? 0);

$loyaltyBal = max(0, (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(points),0) AS v FROM loyalty_points WHERE user_id=$userId"
))['v'] ?? 0));

$tierName = 'Silver';
if ($loyaltyBal >= 5000) {
    $tierName = 'Diamond';
} elseif ($loyaltyBal >= 2000) {
    $tierName = 'Platinum';
} elseif ($loyaltyBal >= 500) {
}