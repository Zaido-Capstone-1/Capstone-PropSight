<?php
/**
 * Sync unit status from booking dates and statuses.
 *
 * Rules:
 * - booked:      has a confirmed/pending booking but check-in date is in the future
 * - occupied:    has an active/confirmed booking and today >= checkin_date
 * - vacant:      no active bookings (or all completed/cancelled)
 * - maintenance: never touched by this function
 */
function syncUnitAvailabilityFromBookings(mysqli $conn, int $unitId): bool
{
    if ($unitId <= 0)
        return false;

    $unitRes = mysqli_query($conn, "SELECT status FROM units WHERE unit_id=$unitId LIMIT 1");
    $unitRow = $unitRes ? mysqli_fetch_assoc($unitRes) : null;
    if (!$unitRow)
        return false;

    $current = (string) ($unitRow['status'] ?? '');

    // Never touch maintenance units
    if ($current === 'maintenance')
        return true;

    // Check if there's an active booking where today >= checkin_date (occupied)
    $occupiedRes = mysqli_query($conn, "
        SELECT COUNT(*) AS c
        FROM bookings
        WHERE unit_id = $unitId
          AND status IN ('confirmed', 'active')
          AND checkin_date <= CURDATE()
          AND checkout_date > CURDATE()
    ");
    $occupiedCount = (int) (mysqli_fetch_assoc($occupiedRes)['c'] ?? 0);

    if ($occupiedCount > 0) {
        $target = 'occupied';
    } else {
        // Check if there's a future booking (booked but not yet checked in)
        $bookedRes = mysqli_query($conn, "
            SELECT COUNT(*) AS c
            FROM bookings
            WHERE unit_id = $unitId
              AND status IN ('pending', 'confirmed')
              AND checkin_date > CURDATE()
              AND checkout_date > CURDATE()
        ");
        $bookedCount = (int) (mysqli_fetch_assoc($bookedRes)['c'] ?? 0);
        $target = $bookedCount > 0 ? 'booked' : 'vacant';
    }

    if ($current === $target)
        return true;

    $targetEsc = mysqli_real_escape_string($conn, $target);
    return (bool) mysqli_query($conn, "UPDATE units SET status='$targetEsc' WHERE unit_id=$unitId");
}

/**
 * Run sync across ALL units — call this on admin dashboard load
 * or via a cron to keep statuses fresh.
 */
function syncAllUnitStatuses(mysqli $conn): void
{
    $res = mysqli_query($conn, "SELECT unit_id FROM units WHERE status != 'maintenance'");
    while ($row = mysqli_fetch_assoc($res)) {
        syncUnitAvailabilityFromBookings($conn, (int) $row['unit_id']);
    }
}