<?php
/**
 * lib/admin/support_data.php
 * Data layer for pages/admin/support.php
 * Requires: $conn (mysqli), $_SESSION['user_id']
 */

$adminId = (int) $_SESSION['user_id'];
$statusFilter = trim($_GET['status'] ?? 'all');
$search = trim($_GET['search'] ?? '');
$perPage = 15;
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * $perPage;

$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];

// Build WHERE parts
$whereParts = [];
$types = '';
$params = [];

if ($statusFilter !== 'all' && in_array($statusFilter, $allowedStatuses, true)) {
    $whereParts[] = 't.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $whereParts[] = "(t.subject LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ? OR t.category LIKE ?)";
    $types .= 'sss';
    $params = array_merge($params, [$like, $like, $like]);
}

$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Total count for pagination
$countStmt = $conn->prepare(
    "SELECT COUNT(*) AS c FROM support_tickets t JOIN users u ON u.user_id=t.user_id $whereSQL"
);
if ($params)
    $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$ticketTotal = (int) ($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$countStmt->close();
$ticketPages = max(1, (int) ceil($ticketTotal / $perPage));

// Ticket list — append LIMIT/OFFSET as integer literals (safe, not user input)
$listSQL = "SELECT
        t.ticket_id, t.category, t.subject, t.priority, t.status, t.created_at,
        CONCAT(u.first_name,' ',u.last_name) AS user_name,
        u.email AS user_email, u.profile_photo AS user_photo,
        (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id=t.ticket_id) AS msg_count,
        (SELECT sm2.body FROM support_messages sm2 WHERE sm2.ticket_id=t.ticket_id
         ORDER BY sm2.created_at DESC LIMIT 1) AS last_message
     FROM support_tickets t
     JOIN users u ON u.user_id=t.user_id
     $whereSQL
     ORDER BY t.created_at DESC
     LIMIT $perPage OFFSET $offset";

$listStmt = $conn->prepare($listSQL);
if ($params)
    $listStmt->bind_param($types, ...$params);
$listStmt->execute();
$tickets = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();

// Summary stats (no user input)
$ticketStats = $conn->query(
    "SELECT COUNT(*) AS total,
            SUM(status='open')        AS open_cnt,
            SUM(status='in_progress') AS in_progress_cnt,
            SUM(status='resolved')    AS resolved_cnt,
            SUM(status='closed')      AS closed_cnt
     FROM support_tickets"
)->fetch_assoc() ?: [];

function ticketBadge(string $s): array
{
    return match ($s) {
        'open' => ['label' => 'Open', 'cls' => 'badge-open'],
        'in_progress' => ['label' => 'In Progress', 'cls' => 'badge-progress'],
        'resolved' => ['label' => 'Resolved', 'cls' => 'badge-done'],
        'closed' => ['label' => 'Closed', 'cls' => 'badge-done'],
        default => ['label' => ucfirst($s), 'cls' => 'badge-pending'],
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
function buildQS(array $overrides = []): string
{
    $base = array_merge($_GET, $overrides);
    return '?' . htmlspecialchars(http_build_query($base));
}