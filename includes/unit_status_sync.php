<?php
/**
 * Sync unit availability from booking statuses.
 * Rule:
 * - occupied: unit has at least one pending/confirmed/active booking
 * - vacant:   otherwise (unless currently maintenance)
 */
function syncUnitAvailabilityFromBookings(mysqli $conn, int $unitId): bool
{
    if ($unitId <= 0) {
        return false;
    }

    $unitRes = mysqli_query($conn, "SELECT status FROM units WHERE unit_id=$unitId LIMIT 1");
    $unitRow = $unitRes ? mysqli_fetch_assoc($unitRes) : null;
    if (!$unitRow) {
        return false;
    }

    $busyRes = mysqli_query($conn, "
        SELECT COUNT(*) AS c
        FROM bookings
        WHERE unit_id = $unitId
          AND status IN ('pending', 'confirmed', 'active')
          AND checkout_date >= CURDATE()
    ");
    $busyCount = (int)(mysqli_fetch_assoc($busyRes)['c'] ?? 0);

    $current = (string)($unitRow['status'] ?? '');
    $target = $busyCount > 0 ? 'occupied' : ($current === 'maintenance' ? 'maintenance' : 'vacant');

    if ($current === $target) {
        return true;
    }

    $targetEsc = mysqli_real_escape_string($conn, $target);
    return (bool)mysqli_query($conn, "UPDATE units SET status='$targetEsc' WHERE unit_id=$unitId");
}

