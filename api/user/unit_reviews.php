<?php
/**
 * API: /api/user/unit_reviews.php
 * GET  ?unit_id=X&page=1&limit=5  — paginated reviews for a unit
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

$unitId = (int)($_GET['unit_id'] ?? 0);
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = max(1, min(20, (int)($_GET['limit'] ?? 5)));
$offset = ($page - 1) * $limit;

if (!$unitId) {
    echo json_encode(['success' => false, 'message' => 'Invalid unit.']);
    exit;
}

// Total count
$total = (int)(mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM booking_reviews WHERE unit_id = $unitId"))['c'] ?? 0);

// Reviews with reviewer name
$res = mysqli_query($conn, "
    SELECT
        br.rating,
        br.comment,
        br.created_at,
        u.first_name,
        u.last_name
    FROM booking_reviews br
    JOIN users u ON u.user_id = br.user_id
    WHERE br.unit_id = $unitId
    ORDER BY br.created_at DESC
    LIMIT $limit OFFSET $offset
");

$reviews = [];
while ($row = mysqli_fetch_assoc($res)) {
    $reviews[] = [
        'rating'   => (int)$row['rating'],
        'comment'  => $row['comment'],
        'author'   => trim($row['first_name'] . ' ' . mb_substr($row['last_name'], 0, 1) . '.'),
        'date'     => date('F Y', strtotime($row['created_at'])),
    ];
}

echo json_encode([
    'success'    => true,
    'reviews'    => $reviews,
    'total'      => $total,
    'page'       => $page,
    'limit'      => $limit,
    'total_pages' => (int)ceil($total / $limit),
]);
