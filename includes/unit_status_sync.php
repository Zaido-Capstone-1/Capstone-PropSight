<?php
/**
 * Sync unit status from booking dates and statuses.
 *
 * Rules:
 * - booked:      has a CONFIRMED booking and check-in date is in the future
 * - occupied:    has an ACTIVE booking (guest manually checked in by admin) — stays
 *                occupied regardless of checkout_date, until the admin manually checks
 *                the guest out (booking status becomes 'completed'). A CONFIRMED
 *                booking (not yet checked in) is auto-treated as occupied while
 *                today is within its checkin/checkout window, INCLUSIVE of the
 *                checkout date itself — it only flips the day *after* checkout.
 * - vacant:      no active bookings (or all completed/cancelled/pending)
 * - maintenance: never touched by this function
 * NOTE: pending bookings never affect unit status or tenant display.
 * NOTE: checkout_date passing does NOT auto-vacate an 'active' booking — only an
 * explicit admin checkout action (see endpoints/checkin.php) does that.
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

    // Check if there's an occupying booking:
    // - status='active' (admin manually checked the guest in) stays occupied
    //   NO MATTER the checkout_date -- only a manual admin checkout ends it.
    // - status='confirmed' (not yet checked in) counts as occupied through
    //   the *entire* checkout day itself (checkout_date >= today) — it should
    //   not flip to available at midnight before the guest has actually left.
    $occupiedRes = mysqli_query($conn, "
        SELECT COUNT(*) AS c
        FROM bookings
        WHERE unit_id = $unitId
          AND checkin_date <= CURDATE()
          AND (
                status = 'active'
                OR (status = 'confirmed' AND checkout_date >= CURDATE())
              )
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
              AND status = 'confirmed'
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