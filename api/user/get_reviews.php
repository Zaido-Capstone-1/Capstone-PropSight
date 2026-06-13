<?php
require_once '../../includes/db.php';
require_once '../../includes/session.php';

header('Content-Type: application/json');

$unit_id = (int) ($_GET['unit_id'] ?? 0);
$reviewPage = max(1, (int) ($_GET['page'] ?? 1));
$reviewLimit = (int) ($_GET['limit'] ?? 10);
$reviewLimit = in_array($reviewLimit, [5, 10]) ? $reviewLimit : 10;
$starFilter = (int) ($_GET['star'] ?? 0); // 0 = all
$reviewOffset = ($reviewPage - 1) * $reviewLimit;

if (!$unit_id) {
    echo json_encode(['success' => false]);
    exit;
}

$whereStar = $starFilter > 0 ? "AND ROUND(rating) = $starFilter" : '';

// Total count
$totalReviews = (int) mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS c FROM booking_reviews WHERE unit_id=$unit_id $whereStar")
)['c'];
$totalReviewPages = max(1, (int) ceil($totalReviews / $reviewLimit));

// Reviews for this page
$res = mysqli_query(
    $conn,
    "SELECT r.rating, r.comment, r.created_at,
            CONCAT(u.first_name,' ',LEFT(u.last_name,1),'.') AS reviewer,
            u.profile_photo AS reviewer_photo
     FROM booking_reviews r
     JOIN users u ON u.user_id = r.user_id
     WHERE r.unit_id = $unit_id $whereStar
     ORDER BY r.created_at DESC
     LIMIT $reviewLimit OFFSET $reviewOffset"
);

$reviews = [];
while ($row = mysqli_fetch_assoc($res))
    $reviews[] = $row;

echo json_encode([
    'success' => true,
    'reviews' => $reviews,
    'page' => $reviewPage,
    'totalPages' => $totalReviewPages,
    'totalReviews' => $totalReviews,
]);