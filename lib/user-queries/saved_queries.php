<?php
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

$sort = $_GET['sort'] ?? 'date_desc';
$orderBy = match ($sort) {
    'price_asc' => 'u.rent_amount ASC',
    'price_desc' => 'u.rent_amount DESC',
    default => 's.created_at DESC',
};

$savedRatingExpr = "NULL AS rating";
$savedHasBookingRating = false;
$savedHasUnitRating = false;

if ($tbl = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'rating'")) {
    $savedHasBookingRating = mysqli_num_rows($tbl) > 0;
}
if ($tbl = mysqli_query($conn, "SHOW COLUMNS FROM units LIKE 'rating'")) {
    $savedHasUnitRating = mysqli_num_rows($tbl) > 0;
}

if ($savedHasBookingRating) {
    $savedRatingExpr = "(
            SELECT ROUND(AVG(br.rating), 1)
            FROM bookings br
            WHERE br.unit_id = u.unit_id
              AND br.rating IS NOT NULL
        ) AS rating";
} elseif ($savedHasUnitRating) {
    $savedRatingExpr = "u.rating AS rating";
}

$res = mysqli_query($conn, "
    SELECT s.id AS saved_id, s.created_at AS saved_at,
           u.unit_id, u.unit_number, u.unit_name, u.unit_type,
           u.floor, u.rent_amount, u.status, u.description, u.max_guests,
           p.property_name, p.city, p.address,
           (SELECT ui.image_path FROM unit_images ui
            WHERE ui.unit_id=u.unit_id
            ORDER BY ui.sort_order ASC, ui.image_id ASC LIMIT 1) AS image_path,
           $savedRatingExpr
    FROM saved_units s
    JOIN units u ON u.unit_id = s.unit_id
    LEFT JOIN properties p ON p.property_id = u.property_id
    WHERE s.user_id=$userId
    ORDER BY $orderBy
");
$saved_rooms = [];
while ($row = mysqli_fetch_assoc($res))
    $saved_rooms[] = $row;