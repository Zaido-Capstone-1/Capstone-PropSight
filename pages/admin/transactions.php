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
require_once '../../lib/admin-queries/transactions_queries.php';

function formatPeso(int $n): string
{
    return '₱ ' . number_format(abs($n));
}
?>

<link rel="stylesheet" href="../../assets/css/admin-css/transaction.css">
<link rel="stylesheet" href="../../assets/css/admin-css/header.css">

<div class="page-inner">

    <div class="dash-page-header">
        <div class="dash-header-left">
            <h1 class="dash-title">Transactions</h1>
            <p class="dash-subtitle">Full ledger of all financial transactions.</p>
        </div>
        <div class="dash-header-actions">
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

    <div class="cards-area">

        <div class="stat-row">
            <div class="stat-card">
                <div>
                    <div class="stat-label">Total Income</div>
                    <div class="stat-value" data-rt-txn="income"><?= formatPeso($totalIncomeYear) ?></div>
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
                    <div class="stat-value" data-rt-txn="expense"><?= formatPeso($totalExpenseYear) ?></div>
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
                    <div class="stat-value" data-rt-txn="net"
                        style="color:var(--<?= $netProfitYear >= 0 ? 'success' : 'danger' ?>);">
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
                    <div class="stat-value" data-rt-txn="count"><?= $totalCountYear ?></div>
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

                    <!-- Month/Year Picker -->
                    <div style="position:relative;" id="txnMonthPickerWrap">
                        <button type="button" id="txnMonthPickerBtn" onclick="toggleTxnMonthPicker()"
                            style="display:flex;align-items:center;gap:7px;padding:7px 12px;border:1.5px solid var(--border);border-radius:var(--radius);background:var(--white);font-size:12.5px;font-weight:600;cursor:pointer;color:var(--text);white-space:nowrap;">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13"
                                height="13">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                            <span id="txnMonthPickerLabel"><?= date('F Y') ?></span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="11"
                                height="11">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <input type="hidden" id="monthFilter" value="<?= $txn_filter_month ?>">

                        <!-- Dropdown Calendar -->
                        <div id="txnMonthPickerDropdown"
                            style="display:none;position:absolute;top:calc(100% + 6px);left:0;z-index:999;background:var(--white);border:1.5px solid var(--border);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.13);padding:16px;min-width:248px;">
                            <div
                                style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                                <button type="button" onclick="changeTxnPickerYear(-1)"
                                    style="border:none;background:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:17px;color:var(--text-soft);line-height:1;">‹</button>
                                <span id="txnPickerYear"
                                    style="font-size:13.5px;font-weight:700;color:var(--text);"><?= $txn_cur_picker_year ?></span>
                                <button type="button" onclick="changeTxnPickerYear(1)"
                                    style="border:none;background:none;cursor:pointer;padding:4px 8px;border-radius:6px;font-size:17px;color:var(--text-soft);line-height:1;">›</button>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:5px;">
                                <?php
                                $txn_months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                foreach ($txn_months as $i => $mon):
                                    $isActive = ($i + 1) === $txn_cur_picker_month;
                                    ?>
                                    <button type="button" data-month="<?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?>"
                                        onclick="selectTxnPickerMonth(this)"
                                        class="txn-picker-month-btn<?= $isActive ? ' txn-picker-active' : '' ?>"
                                        style="padding:6px 4px;border:1.5px solid <?= $isActive ? 'var(--primary,#3b6ef5)' : 'var(--border)' ?>;border-radius:7px;font-size:11.5px;font-weight:<?= $isActive ? '700' : '500' ?>;cursor:pointer;background:<?= $isActive ? 'var(--primary,#3b6ef5)' : 'var(--white)' ?>;color:<?= $isActive ? 'white' : 'var(--text)' ?>;transition:all .15s;">
                                        <?= $mon ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:6px;">
                                <button type="button" onclick="closeTxnMonthPicker()"
                                    style="padding:5px 11px;border:1.5px solid var(--border);border-radius:6px;font-size:12px;background:none;cursor:pointer;color:var(--text-soft);">Cancel</button>
                                <button type="button" onclick="applyTxnMonthPicker()"
                                    style="padding:5px 13px;border:none;border-radius:6px;font-size:12px;font-weight:600;background:var(--primary,#3b6ef5);color:white;cursor:pointer;">Apply</button>
                            </div>
                        </div>
                    </div>

                    <!-- Type filter -->
                    <div class="inv-status-dropdown-wrap" id="typeDropdownWrap">
                        <button type="button" class="inv-status-trigger" id="typeTrigger"
                            onclick="toggleTypeDropdown()">
                            <span id="typeTriggerLabel">All Types</span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12"
                                height="12" id="typeChevron">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <input type="hidden" id="typeFilter" value="">
                        <div class="inv-status-menu" id="typeMenu" style="display:none;">
                            <button type="button" class="inv-status-opt active" data-value=""
                                onclick="selectTypeOpt(this)">All Types</button>
                            <button type="button" class="inv-status-opt" data-value="Income"
                                onclick="selectTypeOpt(this)">
                                <span class="inv-status-dot" style="background:#16a34a;"></span>Income
                            </button>
                            <button type="button" class="inv-status-opt" data-value="Expense"
                                onclick="selectTypeOpt(this)">
                                <span class="inv-status-dot" style="background:#dc2626;"></span>Expense
                            </button>
                        </div>
                    </div>

                    <!-- Category filter -->
                    <div class="inv-status-dropdown-wrap" id="catDropdownWrap">
                        <button type="button" class="inv-status-trigger" id="catTrigger" onclick="toggleCatDropdown()">
                            <span id="catTriggerLabel">All Categories</span>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12"
                                height="12" id="catChevron">
                                <polyline points="6 9 12 15 18 9" />
                            </svg>
                        </button>
                        <input type="hidden" id="categoryFilter" value="">
                        <div class="inv-status-menu" id="catMenu" style="display:none;">
                            <button type="button" class="inv-status-opt active" data-value=""
                                onclick="selectCatOpt(this)">All Categories</button>
                            <?php foreach ($categories as $cat): ?>
                                <button type="button" class="inv-status-opt" data-value="<?= htmlspecialchars($cat) ?>"
                                    onclick="selectCatOpt(this)">
                                    <span class="inv-status-dot" style="background:#6366f1;"></span>
                                    <?= htmlspecialchars($cat) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

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
            </div>

            <div id="emptyState" style="display:none;text-align:center;padding:52px 16px;">
                <svg width="40" height="40" fill="none" stroke="#ccc" stroke-width="1.5" viewBox="0 0 24 24"
                    style="margin:0 auto 12px;display:block;">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <div style="color:#aaa;font-size:14px;">No transactions match your filters.</div>
            </div>

            <!-- Pagination outside table-wrap — never scrolls horizontally -->
            <div id="txnTableFoot" class="pay-pagination-wrap" style="display:none;">
                <div class="txn-pagination">
                    <span class="txn-page-info" id="txnPageInfo"></span>
                    <div class="txn-page-controls" id="txnPageControls" style="display:none;">
                        <button type="button" id="txnPrevBtn" class="txn-chevron-btn"
                            onclick="txnChangePage(-1)" disabled>
                            <svg fill="none" stroke="currentColor" stroke-width="2.2"
                                viewBox="0 0 24 24" width="14" height="14">
                                <polyline points="15 18 9 12 15 6" />
                            </svg>
                        </button>
                        <span id="txnPageNumbers" class="txn-page-numbers"></span>
                        <button type="button" id="txnNextBtn" class="txn-chevron-btn"
                            onclick="txnChangePage(1)" disabled>
                            <svg fill="none" stroke="currentColor" stroke-width="2.2"
                                viewBox="0 0 24 24" width="14" height="14">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>


<script>
    (function () {
        let txnPickerYear = <?= $txn_cur_picker_year ?>;
        let txnSelectedMonth = '<?= str_pad($txn_cur_picker_month, 2, '0', STR_PAD_LEFT) ?>';

        window.toggleTxnMonthPicker = function () {
            const d = document.getElementById('txnMonthPickerDropdown');
            d.style.display = d.style.display === 'none' ? 'block' : 'none';
        };
        window.closeTxnMonthPicker = function () {
            document.getElementById('txnMonthPickerDropdown').style.display = 'none';
        };
        window.changeTxnPickerYear = function (dir) {
            const newYear = txnPickerYear + dir;
            if (newYear < 2000 || newYear > new Date().getFullYear() + 1) return;
            txnPickerYear = newYear;
            document.getElementById('txnPickerYear').textContent = txnPickerYear;
        };
        window.selectTxnPickerMonth = function (btn) {
            document.querySelectorAll('.txn-picker-month-btn').forEach(b => {
                b.style.background = 'var(--white)';
                b.style.borderColor = 'var(--border)';
                b.style.color = 'var(--text)';
                b.style.fontWeight = '500';
            });
            btn.style.background = 'var(--primary,#3b6ef5)';
            btn.style.borderColor = 'var(--primary,#3b6ef5)';
            btn.style.color = 'white';
            btn.style.fontWeight = '700';
            txnSelectedMonth = btn.dataset.month;
        };
        window.applyTxnMonthPicker = function () {
            const val = txnPickerYear + '-' + txnSelectedMonth;
            document.getElementById('monthFilter').value = val;
            const names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            document.getElementById('txnMonthPickerLabel').textContent = names[parseInt(txnSelectedMonth) - 1] + ' ' + txnPickerYear;
            closeTxnMonthPicker();
            document.getElementById('monthFilter').dispatchEvent(new Event('change'));
        };
        document.addEventListener('click', function (e) {
            const wrap = document.getElementById('txnMonthPickerWrap');
            if (wrap && !wrap.contains(e.target)) closeTxnMonthPicker();
        });
    })();
</script>


<script src="../../assets/js/admin/transactions.js"></script>

<?php include '../../includes/layout_close.php'; ?>