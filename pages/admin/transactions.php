<?php
include '../../includes/session.php';

if ($_SESSION['role'] !== 'admin') {
    echo '<!DOCTYPE html>
<html>
<head><meta http-equiv="refresh" content="2;url=javascript:history.back()"></head>
<body>
<script src="../../assets/js/toast.js"></script>

  <script src="../../assets/js/responsive.js"></script>
<script src="../../assets/js/admin/transactions-inline.js"></script>
</body>
</html>';
    exit;
}

$page_title = 'Transactions';
$active_page = 'transactions';
include '../../includes/db.php';
include '../../includes/layout_open.php';

$year = (int) date('Y');

// ── Total Income (full year) ──────────────────────────────────────────────────
$stmtIncome = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(amount), 0)
    FROM transactions
    WHERE type = 'Income' AND YEAR(transaction_date) = ?
");
mysqli_stmt_bind_param($stmtIncome, 'i', $year);
mysqli_stmt_execute($stmtIncome);
mysqli_stmt_bind_result($stmtIncome, $totalIncomeYear);
mysqli_stmt_fetch($stmtIncome);
mysqli_stmt_close($stmtIncome);
$totalIncomeYear = (int) $totalIncomeYear;

// ── Total Expenses (full year) ────────────────────────────────────────────────
$stmtExpense = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(ABS(amount)), 0)
    FROM transactions
    WHERE type = 'Expense' AND YEAR(transaction_date) = ?
");
mysqli_stmt_bind_param($stmtExpense, 'i', $year);
mysqli_stmt_execute($stmtExpense);
mysqli_stmt_bind_result($stmtExpense, $totalExpenseYear);
mysqli_stmt_fetch($stmtExpense);
mysqli_stmt_close($stmtExpense);
$totalExpenseYear = (int) $totalExpenseYear;

$netProfitYear = $totalIncomeYear - $totalExpenseYear;

// ── Transaction count (full year) ─────────────────────────────────────────────
$stmtCount = mysqli_prepare($conn, "
    SELECT COUNT(*) FROM transactions WHERE YEAR(transaction_date) = ?
");
mysqli_stmt_bind_param($stmtCount, 'i', $year);
mysqli_stmt_execute($stmtCount);
mysqli_stmt_bind_result($stmtCount, $totalCountYear);
mysqli_stmt_fetch($stmtCount);
mysqli_stmt_close($stmtCount);
$totalCountYear = (int) $totalCountYear;

// ── All rows for the table (JS handles client-side filtering) ─────────────────
$stmtAll = mysqli_prepare($conn, "
    SELECT
        t.id,
        DATE_FORMAT(t.transaction_date, '%b %d') AS date_label,
        DATE_FORMAT(t.transaction_date, '%Y-%m')  AS month_val,
        t.reference_no,
        t.description,
        t.category,
        p.property_name   AS property_name,
        t.type,
        t.amount
    FROM transactions t
    LEFT JOIN properties p ON p.property_id = t.property_id
    WHERE YEAR(t.transaction_date) = ?
    ORDER BY t.transaction_date DESC
");
mysqli_stmt_bind_param($stmtAll, 'i', $year);
mysqli_stmt_execute($stmtAll);
$result = mysqli_stmt_get_result($stmtAll);
$txns = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmtAll);

// Dynamic category list for the dropdown
$categories = array_values(array_unique(array_column($txns, 'category')));
sort($categories);

function formatPeso(int $n): string
{
    return '₱ ' . number_format(abs($n));
}
?>

<link rel="stylesheet" href="../../assets/css/admin-css/transaction.css">

<div class="page-header">
    <div class="top-header">
        <h2>Transactions</h2>
        <div class="page-header-sub">Full ledger of all financial transactions</div>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="btn btn-primary" onclick="openAddTxn()">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Add Transaction
        </button>
        <button class="btn btn-secondary" id="exportCsvBtn">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            Export CSV
        </button>
    </div>
</div>

<div class="page-inner">
    <div class="cards-area">

        <div class="stat-row">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Income</div>
                    <div class="stat-value"><?= formatPeso($totalIncomeYear) ?></div>
                    <div class="stat-sub">This year</div>
                </div>
                <div class="stat-icon-wrap green">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                        <polyline points="17 6 23 6 23 12" />
                    </svg>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Expenses</div>
                    <div class="stat-value"><?= formatPeso($totalExpenseYear) ?></div>
                    <div class="stat-sub">This year</div>
                </div>
                <div class="stat-icon-wrap red">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6" />
                        <polyline points="17 18 23 18 23 12" />
                    </svg>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Net Profit</div>
                    <div class="stat-value" style="color:var(--<?= $netProfitYear >= 0 ? 'success' : 'danger' ?>);">
                        <?= ($netProfitYear < 0 ? '−' : '') . formatPeso($netProfitYear) ?>
                    </div>
                    <div class="stat-sub">This year</div>
                </div>
                <div class="stat-icon-wrap blue">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="12" y1="1" x2="12" y2="23" />
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <div class="stat-label">Transactions</div>
                    <div class="stat-value"><?= $totalCountYear ?></div>
                    <div class="stat-sub">This year</div>
                </div>
                <div class="stat-icon-wrap gold">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="2" y="3" width="20" height="14" rx="2" />
                        <line x1="8" y1="21" x2="16" y2="21" />
                        <line x1="12" y1="17" x2="12" y2="21" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="txn-card-header">
                <span class="card-title">Transaction Ledger</span>

                <div class="txn-filters">
                    <select id="typeFilter">
                        <option value="">All Types</option>
                        <option value="Income">Income</option>
                        <option value="Expense">Expense</option>
                    </select>

                    <select id="categoryFilter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="month" id="monthFilter" value="<?= date('Y-m') ?>" />

                    <!-- <span class="filter-badge" id="filterCount"></span>

                    <button class="btn-clear" id="clearFiltersBtn" onclick="clearFilters()" style="display:none;">
                        ✕ Clear
                    </button> -->
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Property</th>
                            <th>Type</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($txns as $t):
                            $isIncome = $t['type'] === 'Income';
                            $amountDisplay = ($isIncome ? '+' : '−') . '₱' . number_format(abs((int) $t['amount']));
                            ?>
                            <tr data-month="<?= htmlspecialchars($t['month_val']) ?>"
                                data-type="<?= htmlspecialchars($t['type']) ?>"
                                data-category="<?= htmlspecialchars($t['category']) ?>"
                                data-amount="<?= (int) $t['amount'] ?>">
                                <td style="color:var(--text-soft);font-size:12px;"><?= htmlspecialchars($t['date_label']) ?>
                                </td>
                                <td><strong><?= htmlspecialchars($t['reference_no']) ?></strong></td>
                                <td><?= htmlspecialchars($t['description']) ?></td>
                                <td><span class="badge badge-blue"><?= htmlspecialchars($t['category']) ?></span></td>
                                <td style="font-size:12px;color:var(--text-soft);">
                                    <?= htmlspecialchars($t['property_name'] ?? '—') ?>
                                </td>
                                <td>
                                    <span class="badge <?= $isIncome ? 'badge-green' : 'badge-red' ?>">
                                        <?= htmlspecialchars($t['type']) ?>
                                    </span>
                                </td>
                                <td style="font-weight:700;color:var(--<?= $isIncome ? 'success' : 'danger' ?>);">
                                    <?= $amountDisplay ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div id="emptyState" style="display:none;text-align:center;padding:52px 16px;">
                    <svg width="40" height="40" fill="none" stroke="#ccc" stroke-width="1.5" viewBox="0 0 24 24"
                        style="margin:0 auto 12px;display:block;">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <div style="color:#aaa;font-size:14px;">No transactions match your filters.</div>
                </div>
            </div>
        </div>

    </div>
</div>

<div id="addTxnModal"
    style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div
        style="background:#fff;border-radius:12px;padding:28px;width:460px;max-width:95vw;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="margin:0;font-size:16px;">Add Transaction</h3>
            <button onclick="closeAddTxn()"
                style="background:none;border:none;font-size:20px;cursor:pointer;color:#888;">&times;</button>
        </div>
        <div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div class="form-group" style="grid-column:span 2">
                <label>Type</label>
                <select id="txn_type"
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);">
                    <option value="Income">Income</option>
                    <option value="Expense">Expense</option>
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" id="txn_date"
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);"
                    value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Amount (₱)</label>
                <input type="number" id="txn_amount" placeholder="0.00" min="0.01" step="0.01"
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);">
            </div>
            <div class="form-group" style="grid-column:span 2">
                <label>Description</label>
                <input type="text" id="txn_desc" placeholder="Brief description"
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);">
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" id="txn_cat" list="cat_list" placeholder="Room Revenue, Utilities..."
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);">
                <datalist id="cat_list">
                    <option>Room Revenue</option>
                    <option>Utilities</option>
                    <option>Maintenance</option>
                    <option>Salaries</option>
                    <option>Supplies</option>
                    <option>Marketing</option>
                    <option>Other</option>
                </datalist>
            </div>
            <div class="form-group">
                <label>Property</label>
                <select id="txn_prop"
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);">
                    <option value="">— All Properties —</option>
                    <?php
                    $pRes = mysqli_query($conn, "SELECT property_id, property_name FROM properties ORDER BY property_name");
                    while ($p = mysqli_fetch_assoc($pRes))
                        echo "<option value='{$p['property_id']}'>" . htmlspecialchars($p['property_name']) . "</option>";
                    ?>
                </select>
            </div>
            <div class="form-group" style="grid-column:span 2">
                <label>Reference No (optional)</label>
                <input type="text" id="txn_ref" placeholder="Auto-generated if blank"
                    style="width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius);">
            </div>
        </div>
        <div id="txnError"
            style="display:none;color:#ef4444;font-size:12px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:9px;margin-top:12px;">
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
            <button class="btn btn-secondary" onclick="closeAddTxn()">Cancel</button>
            <button class="btn btn-primary" id="saveTxnBtn" onclick="saveTransaction()">Save Transaction</button>
        </div>
    </div>
</div>
<script src="../../assets/js/admin/transactions.js"></script>

<?php include '../../includes/layout_close.php'; ?>