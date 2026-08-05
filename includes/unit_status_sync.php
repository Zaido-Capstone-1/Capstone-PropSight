<?php

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

    $occupiedRes = mysqli_query($conn, "
        SELECT COUNT(*) AS c
        FROM bookings
        WHERE unit_id = $unitId
          AND checkin_date <= CURDATE()
          AND status IN ('active', 'confirmed')
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

    if ($current === $target) {
        if ($target === 'vacant') {
            mysqli_query($conn, "UPDATE units SET tenant_id=NULL, tenant_name='' WHERE unit_id=$unitId AND (tenant_id IS NOT NULL OR tenant_name != '')");
        }
        return true;
    }

    $targetEsc = mysqli_real_escape_string($conn, $target);
    $ok = (bool) mysqli_query($conn, "UPDATE units SET status='$targetEsc' WHERE unit_id=$unitId");

    if ($target === 'vacant') {
        mysqli_query($conn, "UPDATE units SET tenant_id=NULL, tenant_name='' WHERE unit_id=$unitId");
    }

    return $ok;
}

function syncAllUnitStatuses(mysqli $conn): void
{
    $res = mysqli_query($conn, "SELECT unit_id FROM units WHERE status != 'maintenance'");
    while ($row = mysqli_fetch_assoc($res)) {
        syncUnitAvailabilityFromBookings($conn, (int) $row['unit_id']);
    }
}