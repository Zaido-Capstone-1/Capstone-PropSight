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

// ── Stats for stat cards (current filtered month) ────────────────────────────
$statsStmt = $conn->prepare(
    "SELECT
         COALESCE(SUM(amount), 0) AS total,
         COALESCE(SUM(CASE WHEN expense_category = 'Maintenance' THEN amount END), 0) AS maintenance,
         COALESCE(SUM(CASE WHEN expense_category = 'Utilities'   THEN amount END), 0) AS utilities,
         COALESCE(SUM(CASE WHEN expense_category = 'Salaries'    THEN amount END), 0) AS salaries,
         COALESCE(SUM(CASE WHEN expense_category = 'Admin'       THEN amount END), 0) AS admin_amt,
         COALESCE(SUM(CASE WHEN expense_category = 'Insurance'   THEN amount END), 0) AS insurance,
         COUNT(*) AS cnt
     FROM expenses
     WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?"
);
$statsStmt->bind_param('ii', $yr, $mo);
$statsStmt->execute();
$statsRow = $statsStmt->get_result()->fetch_assoc() ?: [];
$statsStmt->close();

$stats = [
    'total' => (float) ($statsRow['total'] ?? 0),
    'maintenance' => (float) ($statsRow['maintenance'] ?? 0),
    'utilities' => (float) ($statsRow['utilities'] ?? 0),
    'salaries' => (float) ($statsRow['salaries'] ?? 0),
    'admin' => (float) ($statsRow['admin_amt'] ?? 0),
    'insurance' => (float) ($statsRow['insurance'] ?? 0),
    'count' => (int) ($statsRow['cnt'] ?? 0),
];

// ── Category totals for this month (donut chart + legend) ────────────────────
$catTotalStmt = $conn->prepare(
    "SELECT expense_category, COALESCE(SUM(amount),0) AS total
     FROM expenses
     WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?
     GROUP BY expense_category
     ORDER BY total DESC"
);
$catTotalStmt->bind_param('ii', $yr, $mo);
$catTotalStmt->execute();
$categories = array_map(fn($r) => [
    'category' => $r['expense_category'],
    'total' => (float) $r['total'],
], $catTotalStmt->get_result()->fetch_all(MYSQLI_ASSOC));
$catTotalStmt->close();

// ── Trend reshaped to {label, amount} to match what the chart JS expects ─────
$trends = array_map(fn($r) => [
    'label' => explode(' ', $r['label'])[0],
    'amount' => (float) $r['total'],
], $trendRows);
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