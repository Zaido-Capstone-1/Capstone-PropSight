<?php
/**
 * lib/admin/staff_roles_data.php
 * Data layer for pages/admin/staff_roles.php
 * Requires: $conn (mysqli)
 */

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare(
        "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone,
                u.role, u.created_at, u.profile_photo,
                COALESCE(u.is_active,1) AS is_active, u.last_login
         FROM users u
         WHERE u.role != 'user'
           AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
         ORDER BY FIELD(u.role,'admin','manager','frontdesk','accounting','maintenance'), u.first_name"
    );
    $stmt->bind_param('sss', $like, $like, $like);
} else {
    $stmt = $conn->prepare(
        "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone,
                u.role, u.created_at, u.profile_photo,
                COALESCE(u.is_active,1) AS is_active, u.last_login
         FROM users u
         WHERE u.role != 'user'
         ORDER BY FIELD(u.role,'admin','manager','frontdesk','accounting','maintenance'), u.first_name"
    );
}
$stmt->execute();
$staff = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = ['admin' => 0, 'manager' => 0, 'frontdesk' => 0, 'accounting' => 0, 'maintenance' => 0, 'total' => 0];
foreach ($staff as $s) {
    $counts['total']++;
    $r = strtolower($s['role']);
    if (isset($counts[$r]))
        $counts[$r]++;
}

$role_defs = [
    'admin' => ['Super Admin', 'Full access to all modules', '#0f2744'],
    'manager' => ['Property Manager', 'Properties, bookings, reports', '#1d4ed8'],
    'frontdesk' => ['Front Desk', 'Check-in/out, reservations', '#059669'],
    'accounting' => ['Accounting', 'Financial, invoices, reports', '#b45309'],
    'maintenance' => ['Maintenance', 'Units, amenities, maintenance tickets', '#6b7280'],
];

function roleLabel($role)
{
    global $role_defs;
    return $role_defs[strtolower($role)][0] ?? ucfirst($role);
}
function lastActiveLabel($lastLogin)
{
    if (!$lastLogin)
        return 'Never';
    $diff = time() - strtotime($lastLogin);
    if ($diff < 60)
        return 'Just now';
    if ($diff < 3600)
        return round($diff / 60) . ' min ago';
    if ($diff < 86400)
        return round($diff / 3600) . ' hr' . (round($diff / 3600) > 1 ? 's' : '') . ' ago';
    if ($diff < 604800)
        return round($diff / 86400) . ' day' . (round($diff / 86400) > 1 ? 's' : '') . ' ago';
    return date('M j, Y', strtotime($lastLogin));
}