<?php
require_once '../../includes/db.php';

$userId = (int) $_SESSION['user_id'];

$activeBookingRes = mysqli_query($conn, "
    SELECT b.booking_id, b.unit_id, b.checkin_date, b.checkout_date,
           u.unit_name
    FROM bookings b
    JOIN units u ON u.unit_id = b.unit_id
    WHERE b.user_id = $userId
      AND b.status IN ('confirmed', 'active')
      AND b.checkout_date >= CURDATE()
    LIMIT 1
");
$activeBooking = $activeBookingRes ? mysqli_fetch_assoc($activeBookingRes) : null;
$hasActiveBooking = !empty($activeBooking);
$activeRoom = $hasActiveBooking ? htmlspecialchars($activeBooking['unit_name'] ?? '') : '';
$checkOut   = $hasActiveBooking ? ($activeBooking['checkout_date'] ?? '') : '';

$maintRes = $hasActiveBooking ? mysqli_query($conn, "
    SELECT request_id, issue_description, request_status, priority, request_date
    FROM maintenance_requests
    WHERE unit_id = " . (int) $activeBooking['unit_id'] . "
    ORDER BY request_date DESC
    LIMIT 10
") : null;
$myMaintenance = [];
if ($maintRes) {
    while ($mr = mysqli_fetch_assoc($maintRes))
        $myMaintenance[] = $mr;
}

$ticketsPerPage = 5;
$ticketPage = max(1, (int) ($_GET['ticket_page'] ?? 1));
$ticketOffset = ($ticketPage - 1) * $ticketsPerPage;

$ticketsCountRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM support_tickets WHERE user_id=$userId");
$ticketsTotal = (int) (mysqli_fetch_assoc($ticketsCountRes)['c'] ?? 0);
$ticketsTotalPages = max(1, (int) ceil($ticketsTotal / $ticketsPerPage));
if ($ticketPage > $ticketsTotalPages) {
    $ticketPage = $ticketsTotalPages;
    $ticketOffset = ($ticketPage - 1) * $ticketsPerPage;
}

$ticketsRes = mysqli_query($conn, "
    SELECT t.ticket_id, t.category, t.subject, t.status, t.created_at,
           (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id=t.ticket_id) AS msg_count
    FROM support_tickets t
    WHERE t.user_id=$userId ORDER BY t.created_at DESC LIMIT $ticketsPerPage OFFSET $ticketOffset
");
$myTickets = [];
while ($tk = mysqli_fetch_assoc($ticketsRes))
    $myTickets[] = $tk;