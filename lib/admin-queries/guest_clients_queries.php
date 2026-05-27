<?php
/**
 * lib/admin/guests_clients_data.php
 * Data layer for pages/admin/guests_clients.php
 * Requires: $conn (mysqli)
 * All queries here have no user input — static queries are safe.
 */

// Full guest directory
$guestRes = $conn->query(
    "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.created_at, u.profile_photo,
            COALESCE(u.is_blacklisted,0) AS is_blacklisted,
            COALESCE(u.is_active,0)      AS is_active,
            COUNT(DISTINCT b.booking_id) AS total_stays,
            (SELECT COALESCE(NULLIF(TRIM(un.unit_name),''), CONCAT(p2.property_name,' — ',un.unit_number))
             FROM bookings bx
             JOIN units un ON un.unit_id = bx.unit_id
             LEFT JOIN properties p2 ON p2.property_id = un.property_id
             WHERE bx.user_id=u.user_id AND bx.status IN('confirmed','active','completed')
             ORDER BY bx.checkin_date DESC LIMIT 1
            ) AS current_unit
     FROM users u
     LEFT JOIN bookings b ON b.user_id=u.user_id AND b.status NOT IN('cancelled')
     WHERE u.role='user'
     GROUP BY u.user_id ORDER BY u.created_at DESC"
);
$guests = $guestRes->fetch_all(MYSQLI_ASSOC);

// Stat counts
$total = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user'")->fetch_assoc()['c'];
$active_tenants = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user' AND is_active=1")->fetch_assoc()['c'];
$new_month = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetch_assoc()['c'];
$blacklisted = (int) $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='user' AND is_blacklisted=1")->fetch_assoc()['c'];

// Pending ID verifications
$pidRes = $conn->query(
    "SELECT user_id, first_name, last_name, email, id_document_path, created_at
     FROM users WHERE id_verified='pending' AND id_document_path IS NOT NULL
     ORDER BY created_at ASC"
);
$pendingIds = $pidRes->fetch_all(MYSQLI_ASSOC);

function guestStatus($row)
{
    if ($row['is_blacklisted'])
        return ['Blacklisted', 'danger'];
    if ($row['is_active'])
        return ['Active', 'success'];
    if ($row['total_stays'] > 0)
        return ['Guest', 'info'];
    return ['New', 'pending'];
}