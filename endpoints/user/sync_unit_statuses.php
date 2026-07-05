<?php
/**
 * API: Auto-sync unit statuses based on today's date
 * Path: endpoints/user/sync_unit_statuses.php
 *
 * Call this on any page load to keep unit statuses fresh:
 *   fetch('../../endpoints/user/sync_unit_statuses.php');
 *
 * Rules applied:
 *   booked   → has future pending/confirmed booking (checkin > today)
 *   occupied → has confirmed/active booking where checkin <= today < checkout
 *   vacant   → no active bookings
 *   maintenance → never touched
 */
header('Content-Type: application/json');
include '../../includes/session.php';
require_once '../../includes/db.php';
require_once '../../includes/unit_status_sync.php';

// Only allow logged-in users or internal calls
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

syncAllUnitStatuses($conn);
echo json_encode(['success' => true]);