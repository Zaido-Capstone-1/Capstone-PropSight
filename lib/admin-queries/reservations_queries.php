<?php
/**
 * lib/admin/reservations_data.php
 * Data layer for pages/admin/reservations.php
 * Requires: $conn (mysqli), $conn from unit_status_sync included before this file.
 */

function autoCompleteExpiredBookings(mysqli $conn): void
{
    // NOTE: Bookings are no longer auto-marked 'completed' just because the
    // checkout date has passed — that requires an explicit admin action
    // (the "Complete" button), same as check-in requires one. This function
    // is intentionally left as a no-op call site in case a future explicit
    // (admin-triggered) sweep is needed.
}

$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$allowedStatuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled'];

$whereParts = [];
$types = '';
$params = [];

if ($statusFilter !== 'all' && in_array($statusFilter, $allowedStatuses, true)) {
    $whereParts[] = 'b.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $whereParts[] = '(u2.first_name LIKE ? OR u2.last_name LIKE ? OR u2.email LIKE ?
                   OR un.unit_name LIKE ? OR un.unit_number LIKE ?
                   OR p.property_name LIKE ? OR b.booking_id LIKE ?)';
    $types .= 'sssssss';
    $params = array_merge($params, array_fill(0, 7, $like));
}
$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

autoCompleteExpiredBookings($conn);

// Stats (no user input)
$statsRes = $conn->query(
    "SELECT COUNT(*) AS total,
            SUM(status='pending')              AS pending,
            SUM(status IN('confirmed','active')) AS confirmed,
            SUM(status='completed')            AS completed,
            SUM(status='cancelled')            AS cancelled
     FROM bookings"
);
$stats = $statsRes->fetch_assoc();

// Bookings list
$stmt = $conn->prepare(
    "SELECT b.booking_id, b.checkin_date, b.checkout_date, b.guests, b.total_amount,
            b.status, b.created_at,
            DATEDIFF(b.checkout_date, b.checkin_date) AS nights,
            CONCAT(u2.first_name,' ',u2.last_name)    AS user_name,
            u2.email                                   AS user_email,
            u2.profile_photo                           AS user_photo,
            un.unit_name, un.unit_number, p.property_name
     FROM   bookings b
     JOIN   users      u2 ON u2.user_id      = b.user_id
     JOIN   units      un ON un.unit_id      = b.unit_id
     LEFT JOIN properties p ON p.property_id = un.property_id
     $whereSQL
     ORDER  BY b.created_at DESC"
);
if ($params)
    $stmt->bind_param($types, ...$params);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function badgeClass($s)
{
    return match ($s) {
        'confirmed', 'active' => 'success',
        'pending' => 'pending',
        'completed' => 'info',
        'cancelled' => 'danger',
        default => 'pending',
    };
}
function badgeLabel($s)
{
    return match ($s) {
        'active' => 'Active',
        'confirmed' => 'Confirmed',
        'pending' => 'Pending',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        default => ucfirst($s),
    };
}
function fmtDate($d)
{
    return $d ? date('M j, Y', strtotime($d)) : '—';
}