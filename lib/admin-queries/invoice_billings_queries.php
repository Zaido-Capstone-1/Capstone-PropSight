<?php
/**
 * lib/admin/invoices_billing_data.php
 */

$statsResult = $conn->query(
    "SELECT COUNT(*) AS total,
            SUM(status='Paid')    AS paid,
            SUM(status='Pending') AS pending,
            SUM(status='Overdue') AS overdue
     FROM invoices"
);
$stats = $statsResult->fetch_assoc();

$allResult = $conn->query(
    "SELECT i.id, i.invoice_no,
            t.full_name                              AS tenant_name,
            t.email                                  AS tenant_email,
            i.unit,
            DATE_FORMAT(i.issued_date,'%b %d, %Y')  AS issued_label,
            DATE_FORMAT(i.issued_date,'%Y-%m')       AS month_val,
            DATE_FORMAT(i.due_date,   '%b %d, %Y')  AS due_label,
            i.items, i.total, i.status
     FROM invoices i
     LEFT JOIN tenants t ON t.tenant_id = i.tenant_id
     ORDER BY i.issued_date DESC"
);
$invoices = $allResult->fetch_all(MYSQLI_ASSOC);

$tenantsResult = $conn->query(
    "SELECT t.tenant_id, t.full_name, u.unit_number, u.unit_name
     FROM tenants t
     LEFT JOIN units u ON u.tenant_id = t.tenant_id
     ORDER BY t.full_name ASC"
);
$tenants = $tenantsResult->fetch_all(MYSQLI_ASSOC);

function badge_class(string $status): string
{
    return match ($status) {
        'Paid' => 'success',
        'Pending' => 'warning',
        'Overdue' => 'danger',
        'Sent' => 'info',
        default => 'warning',
    };
}

$inv_cur_picker_month = (int) date('m');
$inv_cur_picker_year = (int) date('Y');
$inv_default_month_val = date('Y-m');