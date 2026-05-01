<?php
include '../../includes/session.php';
include '../../includes/db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit;
}

$filter_status = $_GET['status'] ?? 'all';
$filter_month = $_GET['month'] ?? date('Y-m');
$search = trim($_GET['q'] ?? '');

[$y, $m] = explode('-', $filter_month . '-01');
$y = (int) $y;
$m = (int) $m;

$where = ['YEAR(p.payment_date) = ?', 'MONTH(p.payment_date) = ?'];
$types = 'ii';
$params = [$y, $m];

if ($filter_status !== 'all') {
    $where[] = 'p.payment_status = ?';
    $types .= 's';
    $params[] = $filter_status;
}
if ($search !== '') {
    $where[] = '(t.full_name LIKE ? OR u.unit_number LIKE ?)';
    $types .= 'ss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$where_sql = implode(' AND ', $where);

$sql = "
    SELECT
        CONCAT('#PAY-', LPAD(p.payment_id, 3, '0')) AS ref_number,
        COALESCE(t.full_name, '')                    AS tenant,
        COALESCE(u.unit_number, '')                  AS unit,
        p.payment_date,
        p.amount_paid,
        COALESCE(p.payment_method, '')               AS payment_method,
        p.payment_status,
        COALESCE(p.notes, '')                        AS notes,
        p.created_at
    FROM payments p
    LEFT JOIN bookings b ON b.booking_id = p.booking_id
    LEFT JOIN tenants  t ON t.tenant_id  = b.tenant_id
    LEFT JOIN units    u ON u.unit_id    = b.unit_id
    WHERE $where_sql
    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$filename = 'payments_' . $filter_month . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Ref #', 'Tenant', 'Unit', 'Payment Date', 'Amount Paid (PHP)', 'Method', 'Status', 'Notes', 'Created At']);

while ($row = $result->fetch_assoc()) {
    fputcsv($out, [
        $row['ref_number'],
        $row['tenant'],
        $row['unit'],
        $row['payment_date'],
        $row['amount_paid'],
        $row['payment_method'],
        ucfirst($row['payment_status']),
        $row['notes'],
        $row['created_at'],
    ]);
}

fclose($out);
exit;