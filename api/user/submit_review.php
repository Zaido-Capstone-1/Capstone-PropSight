<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

require_verified_user_action(true);
require_csrf_token(true);

$userId = (int) $_SESSION['user_id'];
$bookingId = (int) ($_POST['booking_id'] ?? 0);
$rating = (int) ($_POST['rating'] ?? 0);
$comment = trim((string) ($_POST['comment'] ?? ''));
$cleanliness = min(5, max(1, (float) ($_POST['cleanliness'] ?? 3)));
$locationRating = min(5, max(1, (float) ($_POST['location_rating'] ?? 3)));
$valueRating = min(5, max(1, (float) ($_POST['value_rating'] ?? 3)));
$comfort = min(5, max(1, (float) ($_POST['comfort'] ?? 3)));

if ($bookingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    exit;
}
if ($comment === '') {
    echo json_encode(['success' => false, 'message' => 'Please provide your review comment.']);
    exit;
}

$bookingStmt = $conn->prepare(
    'SELECT booking_id, unit_id, status, user_id FROM bookings WHERE booking_id = ? LIMIT 1'
);
$bookingStmt->bind_param('i', $bookingId);
$bookingStmt->execute();
$booking = $bookingStmt->get_result()->fetch_assoc();
$bookingStmt->close();

if (!$booking || (int) $booking['user_id'] !== $userId) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}
if (strtolower((string) $booking['status']) !== 'completed') {
    echo json_encode(['success' => false, 'message' => 'You can review completed bookings only.']);
    exit;
}

$unitId = (int) $booking['unit_id'];

mysqli_begin_transaction($conn);
try {
    // One review per booking; allow update if user edits later.
    $upsertSql = "
        INSERT INTO booking_reviews (booking_id, unit_id, user_id, rating, comment)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rating=VALUES(rating),
            comment=VALUES(comment),
            updated_at=CURRENT_TIMESTAMP
    ";
    $upsertStmt = $conn->prepare($upsertSql);
    if (!$upsertStmt) {
        throw new Exception('Could not prepare review statement.');
    }
    $upsertSql = "
        INSERT INTO booking_reviews (booking_id, unit_id, user_id, rating, comment, cleanliness, location_rating, value_rating, comfort)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rating=VALUES(rating), comment=VALUES(comment),
            cleanliness=VALUES(cleanliness), location_rating=VALUES(location_rating),
            value_rating=VALUES(value_rating), comfort=VALUES(comfort),
            updated_at=CURRENT_TIMESTAMP
    ";
    $upsertStmt = $conn->prepare($upsertSql);
    $upsertStmt->bind_param('iiiisdddd', $bookingId, $unitId, $userId, $rating, $comment, $cleanliness, $locationRating, $valueRating, $comfort);
    if (!$upsertStmt->execute()) {
        $upsertStmt->close();
        throw new Exception('Could not save review: ' . mysqli_error($conn));
    }
    $upsertStmt->close();

    // If bookings.rating exists in this deployment, keep it synced.
    $hasRatingCol = false;
    if ($chk = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'rating'")) {
        $hasRatingCol = mysqli_num_rows($chk) > 0;
    }
    if ($hasRatingCol) {
        $syncStmt = $conn->prepare('UPDATE bookings SET rating = ? WHERE booking_id = ?');
        $syncStmt->bind_param('ii', $rating, $bookingId);
        $syncStmt->execute();
        $syncStmt->close();
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Review submitted successfully.']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

