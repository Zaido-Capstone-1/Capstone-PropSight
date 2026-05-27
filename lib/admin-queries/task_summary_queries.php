<?php
/**
 * lib/admin/task_summary_data.php
 * Data layer for pages/admin/task_summary.php
 * Requires: $conn (mysqli)
 * Both queries have no user input — static queries, $conn->query() is safe.
 */

$tasksRes = $conn->query(
    "SELECT m.request_id, m.issue_description, m.priority, m.request_status, m.request_date, p.property_name
     FROM maintenance_requests m
     LEFT JOIN units u ON u.unit_id = m.unit_id
     LEFT JOIN properties p ON p.property_id = u.property_id
     ORDER BY m.request_date DESC"
);
$tasks = [];
while ($tasksRes && ($r = $tasksRes->fetch_assoc()))
    $tasks[] = $r;

$statsRes = $conn->query(
    "SELECT COUNT(*) AS total,
            SUM(request_status='open')        AS open_cnt,
            SUM(request_status='in_progress') AS in_progress_cnt,
            SUM(request_status='pending')     AS pending_cnt,
            SUM(request_status IN('completed','closed')) AS done_cnt
     FROM maintenance_requests"
);
$stats = $statsRes->fetch_assoc() ?? ['total' => 0, 'open_cnt' => 0, 'in_progress_cnt' => 0, 'pending_cnt' => 0, 'done_cnt' => 0];

function taskBadge(string $status): array
{
    return match ($status) {
        'open' => ['label' => 'Open', 'cls' => 'tbadge-open'],
        'in_progress' => ['label' => 'In Progress', 'cls' => 'tbadge-progress'],
        'completed' => ['label' => 'Done', 'cls' => 'tbadge-done'],
        'closed' => ['label' => 'Closed', 'cls' => 'tbadge-done'],
        default => ['label' => 'Pending', 'cls' => 'tbadge-pending'],
    };
}
function priorityBadge(string $p): array
{
    return match (strtolower($p)) {
        'urgent', 'high' => ['label' => ucfirst($p), 'cls' => 'pri-high'],
        'medium', 'normal' => ['label' => 'Medium', 'cls' => 'pri-med'],
        default => ['label' => 'Low', 'cls' => 'pri-low'],
    };
}