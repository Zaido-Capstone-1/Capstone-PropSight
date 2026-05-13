<?php
// Bookings data fetching functions

/**
 * Get booking statistics for a user
 */
function getBookingStats($conn, $user_id)
{
    $uid = (int) $user_id;

    $query = "SELECT
        SUM(status IN('confirmed','pending'))                AS upcoming,
        SUM(status='active')                                 AS active_cnt,
        SUM(status='completed')                              AS completed,
        SUM(status='cancelled')                              AS cancelled,
        COALESCE(SUM(CASE WHEN status NOT IN('cancelled') THEN total_amount END),0) AS total_spent
    FROM bookings 
    WHERE user_id=$uid";

    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

/**
 * Get all bookings for a user with full details
 */
function getUserBookings($conn, $user_id)
{
    $uid = (int) $user_id;

    $query = "SELECT 
        b.booking_id, 
        b.checkin_date, 
        b.checkout_date, 
        b.guests,
        b.total_amount, 
        b.status, 
        b.payment_method, 
        b.created_at, 
        b.updated_at, 
        b.confirmed_at,
        DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
        COALESCE(u.unit_name, u.unit_number, 'Unit') AS room_name,
        COALESCE(p.property_name,'') AS property_name,
        u.floor,
        br.rating AS review_rating,
        br.comment AS review_comment,
        (SELECT ui.image_path 
         FROM unit_images ui
         WHERE ui.unit_id=u.unit_id 
         ORDER BY ui.sort_order, ui.image_id 
         LIMIT 1) AS img_path
    FROM bookings b
    JOIN units u ON u.unit_id=b.unit_id
    LEFT JOIN properties p ON p.property_id=u.property_id
    LEFT JOIN booking_reviews br ON br.booking_id=b.booking_id AND br.user_id=$uid
    WHERE b.user_id=$uid
    ORDER BY b.created_at DESC";

    $result = mysqli_query($conn, $query);

    $bookings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $status = $row['status'];

        // Normalize status for display
        if (in_array($status, ['confirmed', 'pending', 'active'])) {
            $status = 'upcoming';
        }

        $row['_display_status'] = $status;
        $row['_raw_status'] = $row['status'];
        $bookings[] = $row;
    }

    return $bookings;
}