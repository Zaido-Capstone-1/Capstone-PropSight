<?php
// ── Properties + Units for modal dropdowns (no user input) ────────────────────
$properties = $conn->query(
    "SELECT property_id, property_name FROM properties ORDER BY property_name"
)->fetch_all(MYSQLI_ASSOC);

$units = $conn->query(
    "SELECT unit_id, unit_name, unit_number, property_id FROM units ORDER BY unit_name"
)->fetch_all(MYSQLI_ASSOC);

// ── Distinct Category List for filter dropdown (no user input) ────────────────
$all_cats = $conn->query(
    "SELECT DISTINCT expense_category FROM expenses ORDER BY expense_category"
)->fetch_all(MYSQLI_ASSOC);

// ── 6-month Expense Trend (no user input) ────────────────────────────────────
$trendRows = $conn->query(
    "SELECT
         DATE_FORMAT(expense_date, '%b %Y')                                    AS label,
         DATE_FORMAT(expense_date, '%Y-%m')                                    AS month_val,
         COALESCE(SUM(amount), 0)                                              AS total,
         COALESCE(SUM(CASE WHEN expense_category = 'Maintenance' THEN amount END), 0) AS maintenance,
         COALESCE(SUM(CASE WHEN expense_category = 'Utilities'   THEN amount END), 0) AS utilities,
         COALESCE(SUM(CASE WHEN expense_category = 'Salaries'    THEN amount END), 0) AS salaries,
         COALESCE(SUM(CASE WHEN expense_category = 'Admin'       THEN amount END), 0) AS admin_amt
     FROM expenses
     WHERE expense_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
     GROUP BY month_val, label
     ORDER BY month_val ASC"
)->fetch_all(MYSQLI_ASSOC);

// ── Expenses for selected month — $yr and $mo come from URL ──────────────────
// Additional optional filters: $category_filter, $search (both from $_GET)
$where = ['YEAR(e.expense_date) = ?', 'MONTH(e.expense_date) = ?'];
$types = 'ii';
$params = [(int) $yr, (int) $mo];

if (!empty($category_filter)) {
    $where[] = 'e.expense_category = ?';
    $types .= 's';
    $params[] = $category_filter;
}
if (!empty($search)) {
    $where[] = '(e.description LIKE ? OR p.property_name LIKE ? OR u.unit_name LIKE ? OR u.unit_number LIKE ?)';
    $types .= 'ssss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$expStmt = $conn->prepare(
    "SELECT
         e.expense_id, e.description, e.expense_category, e.amount,
         DATE_FORMAT(e.expense_date, '%b %d, %Y') AS expense_date_label,
         e.expense_date,
         p.property_name,
         COALESCE(u.unit_name, u.unit_number, '') AS unit_label
     FROM expenses e
     LEFT JOIN properties p ON p.property_id = e.property_id
     LEFT JOIN units      u ON u.unit_id     = e.unit_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY e.expense_date DESC"
);
$expStmt->bind_param($types, ...$params);
$expStmt->execute();
$expenses = $expStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$expStmt->close();