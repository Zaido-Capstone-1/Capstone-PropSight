<?php
include '../includes/session.php';
include '../includes/db.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'user') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Verify user has an active booking
$activeRes = mysqli_query($conn, "
    SELECT booking_id, unit_id, tenant_id
    FROM bookings
    WHERE user_id = $userId
      AND status IN ('confirmed', 'active')
      AND checkout_date >= CURDATE()
    LIMIT 1
");
$activeBooking = $activeRes ? mysqli_fetch_assoc($activeRes) : null;

if (!$activeBooking) {
    echo json_encode(['success' => false, 'message' => 'No active booking found.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$issueType = trim($input['issue_type'] ?? '');
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');
$priority = trim($input['priority'] ?? 'normal');
$unitId = (int) $activeBooking['unit_id'];
$tenantId = (int) $activeBooking['tenant_id'];

if (!$subject || !$message) {
    echo json_encode(['success' => false, 'message' => 'Subject and details are required.']);
    exit;
}

// Map form priority to DB enum (low/medium/high — 'normal' → 'medium', 'urgent' → 'high')
$priorityMap = [
    'low' => 'low',
    'normal' => 'medium',
    'high' => 'high',
    'urgent' => 'high',
];
$dbPriority = $priorityMap[$priority] ?? 'medium';

$fullDescription = mysqli_real_escape_string($conn, "[{$issueType}] {$subject}\n\n{$message}");
$dbPriority = mysqli_real_escape_string($conn, $dbPriority);
// Use client's local date if provided (avoids UTC offset issues for non-UTC users)
$clientDate = trim($input['client_date'] ?? '');
if ($clientDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $clientDate)) {
    $today = $clientDate;
} else {
    $today = gmdate('Y-m-d');
}

$insert = mysqli_query($conn, "
    INSERT INTO maintenance_requests (tenant_id, unit_id, issue_description, request_status, priority, request_date)
    VALUES ($tenantId, $unitId, '$fullDescription', 'open', '$dbPriority', '$today')
");

if ($insert) {
    $requestId = mysqli_insert_id($conn);
    require_once __DIR__ . '/../includes/admin_notif_helpers.php';
    $_ar = mysqli_query($conn, "SELECT user_id FROM users WHERE role='admin' AND is_active=1");
    while ($_adm = mysqli_fetch_assoc($_ar)) {
        upsert_notif(
            $conn,
            (int) $_adm['user_id'],
            'maintenance',
            'task-' . $requestId,
            'Task: ' . mb_substr($fullDescription, 0, 80),
            'task_summary.php?status=open',
            gmdate('Y-m-d H:i:s')
        );
    }
    echo json_encode(['success' => true, 'message' => 'Maintenance request submitted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}