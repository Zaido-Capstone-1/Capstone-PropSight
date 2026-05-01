<?php
/**
 * API: /api/user/save_toggle.php
 * POST — toggle save/unsave a unit for the current user
 * Alias for /api/user/saved.php POST action=toggle (different URL path)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required.']);
    exit;
}

require_csrf_token(true);

$userId = (int) $_SESSION['user_id'];
$unitId = (int) ($_POST['unit_id'] ?? 0);

if (!$unitId) {
    echo json_encode(['success' => false, 'message' => 'unit_id required.']);
    exit;
}

// Verify unit exists
$unitExists = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT unit_id FROM units WHERE unit_id=$unitId LIMIT 1"
));
if (!$unitExists) {
    echo json_encode(['success' => false, 'message' => 'Unit not found.']);
    exit;
}

// Toggle
$existing = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT id FROM saved_units WHERE user_id=$userId AND unit_id=$unitId LIMIT 1"
));

function get_saved_count($conn, $userId) {
    $row = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM saved_units WHERE user_id=$userId"
    ));
    return (int)($row['c'] ?? 0);
}

if ($existing) {
    mysqli_query($conn, "DELETE FROM saved_units WHERE user_id=$userId AND unit_id=$unitId");
    echo json_encode([
        'success' => true,
        'saved' => false,
        'saved_count' => get_saved_count($conn, $userId),
        'message' => 'Removed from saved.'
    ]);
} else {
    if (mysqli_query($conn, "INSERT INTO saved_units (user_id, unit_id) VALUES ($userId, $unitId)")) {
        echo json_encode([
            'success' => true,
            'saved' => true,
            'saved_count' => get_saved_count($conn, $userId),
            'message' => 'Added to saved.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
}