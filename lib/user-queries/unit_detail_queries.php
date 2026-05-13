<?php
require_once '../../includes/db.php';

$unit_id = (int) ($_GET['id'] ?? 0);
if ($unit_id <= 0) {
    header('Location: user-dashboard.php');
    exit;
}

$_uid = (int) $_SESSION['user_id'];

// ── Unit ─────────────────────────────────────────────────────────────────────
$unit = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT u.*, p.property_name, p.address, p.city, p.latitude, p.longitude,
            ROUND(AVG(r.rating),1) AS rating
     FROM units u
     LEFT JOIN properties p ON p.property_id = u.property_id
     LEFT JOIN booking_reviews r ON r.unit_id = u.unit_id
     WHERE u.unit_id = $unit_id
     GROUP BY u.unit_id LIMIT 1"
));
if (!$unit) {
    header('Location: user-dashboard.php');
    exit;
}

// ── Images ────────────────────────────────────────────────────────────────────
$imagesRes = mysqli_query(
    $conn,
    "SELECT image_path FROM unit_images WHERE unit_id=$unit_id ORDER BY image_id ASC"
);
$images = [];
while ($row = mysqli_fetch_assoc($imagesRes))
    $images[] = '../../' . ltrim($row['image_path'], '/');
if (empty($images) && !empty($unit['image_path']))
    $images[] = '../../' . ltrim($unit['image_path'], '/');

// ── Amenities ─────────────────────────────────────────────────────────────────
$amenRes = mysqli_query(
    $conn,
    "SELECT a.name FROM unit_amenities ua
     JOIN amenities a ON a.amenity_id = ua.amenity_id
     WHERE ua.unit_id = $unit_id ORDER BY a.name ASC"
);
$amenities = [];
while ($row = mysqli_fetch_assoc($amenRes))
    $amenities[] = $row['name'];

// ── Reviews ───────────────────────────────────────────────────────────────────
$reviewPage = max(1, (int) ($_GET['rp'] ?? 1));
$reviewLimit = 5;
$reviewOffset = ($reviewPage - 1) * $reviewLimit;

$totalReviews = (int) (mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS c FROM booking_reviews WHERE unit_id=$unit_id"
))['c'] ?? 0);
$totalReviewPages = max(1, (int) ceil($totalReviews / $reviewLimit));

$reviewsRes = mysqli_query(
    $conn,
    "SELECT r.rating, r.comment, r.created_at,
            CONCAT(u.first_name,' ',LEFT(u.last_name,1),'.') AS reviewer
     FROM booking_reviews r
     JOIN users u ON u.user_id = r.user_id
     WHERE r.unit_id = $unit_id
     ORDER BY r.created_at DESC
     LIMIT $reviewLimit OFFSET $reviewOffset"
);
$reviews = [];
while ($row = mysqli_fetch_assoc($reviewsRes))
    $reviews[] = $row;

// ── Saved? ────────────────────────────────────────────────────────────────────
$isSaved = (bool) mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT 1 FROM saved_units WHERE user_id=$_uid AND unit_id=$unit_id LIMIT 1"
));

// ── Popular payment ───────────────────────────────────────────────────────────
$_pmRow = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT payment_method FROM bookings
     WHERE payment_method IS NOT NULL AND payment_method!='' AND status!='cancelled'
     GROUP BY payment_method ORDER BY COUNT(*) DESC LIMIT 1"
));
$popularPaymentMethod = $_pmRow['payment_method'] ?? 'GCash';

// ── Active booking ────────────────────────────────────────────────────────────
$hasActiveBooking = (bool) mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT 1 FROM bookings
     WHERE user_id=$_uid AND unit_id=$unit_id
       AND status NOT IN ('cancelled','completed') LIMIT 1"
));