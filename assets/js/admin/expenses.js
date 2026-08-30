/**
 * expenses.js — Expenses module JS
 * Depends on: Chart.js 4, SweetAlert2
 */

// ─────────────────────────────────────────────────────────
//  CONFIG
// ─────────────────────────────────────────────────────────
const EXPENSES = {
    apiUrl: '../../endpoints/expenses.php',

    catColours: {
        Maintenance: '#E74C3C',
        Utilities: '#2563c4',
        Salaries: '#2ECC71',
        Admin: '#deaf37',
        Insurance: '#8B5CF6',
        Other: '#94a3b8',
    },

    /** Derive current month from URL param or today */
    get currentMonth() {
        return new URLSearchParams(location.search).get('month') || new Date().toISOString().slice(0, 7);
    },
};

// ─────────────────────────────────────────────────────────
//  DOM REFS
// ─────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);

const DOM = {
    // Table
    searchInput: $('searchInput'),
    categoryFilter: $('categoryFilter'),
    expensesBody: $('expensesBody'),
    emptyState: $('emptyState'),
    tableFooter: $('tableFooter'),
    recordCount: $('recordCount'),
    footerTotal: $('footerTotal'),

    // Charts
    legendContainer: $('legendContainer'),
    trendCanvas: $('expTrendChart'),
    donutCanvas: $('catDonut'),

    // Stats
    statTotal: $('statTotal'),
    statPercentage: $('statPercentage'),
    statMaintenance: $('statMaintenance'),
    statMaintenancePercent: $('statMaintenancePercent'),
    statUtilities: $('statUtilities'),
    statUtilitiesPercent: $('statUtilitiesPercent'),
    statAdmin: $('statAdmin'),
    statAdminPercent: $('statAdminPercent'),

    // Modal
    modal: $('expenseModal'),
    modalTitle: $('modalTitle'),
    editId: $('editId'),
    btnOpenAdd: $('btnOpenAdd'),
    btnSave: $('btnSave'),
    btnExport: $('btnExportCSV'),
    toast: $('toast'),

    // Form fields
    fProperty: $('fProperty'),
    fUnit: $('fUnit'),
    fCategory: $('fCategory'),
    fDate: $('fDate'),
    fDescription: $('fDescription'),
    fAmount: $('fAmount'),
};

function filterUnitOptions(propertyId = '') {
    if (!DOM.fUnit) return;

    Array.from(DOM.fUnit.options).forEach(opt => {
        if (!opt.value) {
            opt.hidden = false;
            return;
        }
        opt.hidden = Boolean(propertyId && opt.dataset.propertyId !== propertyId);
    });

    if (propertyId) {
        DOM.fUnit.value = '';
    }
}


// ─────────────────────────────────────────────────────────
//  CHARTS  (module-level so we can destroy/recreate)
// ─────────────────────────────────────────────────────────
let trendChart = null;
let donutChart = null;

let expCurrentPage = 1;
const expRowsPerPage = 10;
let allExpensesData = [];

/* ══════════════════════════════════════════════════════════════════════
   PAGINATION  (matches transactions / invoice_billings design)
══════════════════════════════════════════════════════════════════════ */

function paginateExpenses(expenses) {
    allExpensesData = expenses;

    const total = expenses.length;
    const totalPages = Math.max(1, Math.ceil(total / expRowsPerPage));
    expCurrentPage = Math.min(expCurrentPage, totalPages);

    const startIdx = (expCurrentPage - 1) * expRowsPerPage;
    const endIdx = startIdx + expRowsPerPage;

    renderTable(expenses.slice(startIdx, endIdx));
    renderExpPaginationFooter(total, totalPages, startIdx, endIdx);
}

function renderExpPaginationFooter(total, totalPages, startIdx, endIdx) {
    const foot = $('expTableFoot');
    const info = $('expPageInfo');
    const controls = $('expPageControls');
    const prevBtn = $('expPrevBtn');
    const nextBtn = $('expNextBtn');

    if (!foot) return;

    if (total === 0) {
        foot.style.display = 'none';
        return;
    }

    foot.style.display = '';
    const from = startIdx + 1;
    const to = Math.min(endIdx, total);
    if (info) info.innerHTML = `Showing <strong>${from}–${to}</strong> of <strong>${total}</strong> expense(s)`;

    if (totalPages <= 1) {
        if (controls) controls.style.display = 'none';
        return;
    }

    if (controls) controls.style.display = 'flex';
    if (prevBtn) prevBtn.disabled = expCurrentPage <= 1;
    if (nextBtn) nextBtn.disabled = expCurrentPage >= totalPages;

    renderExpPageNumbers(totalPages);
}

function renderExpPageNumbers(totalPages) {
    const wrap = $('expPageNumbers');
    if (!wrap) return;
    wrap.innerHTML = '';

    const cur = expCurrentPage;
    const nums = new Set([1, totalPages, cur, cur - 1, cur + 1].filter(n => n >= 1 && n <= totalPages));
    const sorted = [...nums].sort((a, b) => a - b);

    sorted.forEach((n, idx) => {
        if (idx > 0 && n > sorted[idx - 1] + 1) {
            const el = document.createElement('span');
            el.className = 'exp-pg-ellipsis';
            el.textContent = '…';
            wrap.appendChild(el);
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'exp-pg-num' + (n === cur ? ' active' : '');
        btn.textContent = n;
        btn.onclick = () => { expCurrentPage = n; paginateExpenses(allExpensesData); };
        wrap.appendChild(btn);
    });
}

// Called by tfoot chevron buttons
window.expChangePage = function (dir) {
    expCurrentPage += dir;
    paginateExpenses(allExpensesData);
};

// ─────────────────────────────────────────────────────────
//  UTILITIES
// ─────────────────────────────────────────────────────────
function esc(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[c]);
}

function fmt(n, decimals = 2) {
    return parseFloat(n || 0).toLocaleString('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function fmtDate(dateStr) {
    // expense_date comes as YYYY-MM-DD; avoid timezone shift with manual parse
    const [y, m, d] = (dateStr || '').split('-');
    if (!y) return '—';
    const dt = new Date(+y, +m - 1, +d);
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function catColour(cat) {
    return EXPENSES.catColours[cat] || '#94a3b8';
}

function showToast(msg, type = 'success') {
    /* Delegate to the global PropSight toast system */
    if (typeof window.showToast === 'function' && window.showToast !== showToast) {
        window.showToast(msg, type);
        return;
    }
    /* Fallback: legacy inline toast element */
    if (DOM.toast) {
        DOM.toast.textContent = msg;
        DOM.toast.className = `toast ${type} show`;
        clearTimeout(DOM.toast._timer);
        DOM.toast._timer = setTimeout(() => DOM.toast.classList.remove('show'), 3000);
    }
}

// ─────────────────────────────────────────────────────────
//  RENDER: TABLE
// ─────────────────────────────────────────────────────────
function renderTable(expenses) {
    DOM.expensesBody.innerHTML = '';

    if (!expenses.length) {
        DOM.emptyState.style.display = 'flex';
        return;
    }

    DOM.emptyState.style.display = 'none';

    let total = 0;

    expenses.forEach(e => {
        const col = catColour(e.expense_category);
        const tr = document.createElement('tr');
        tr.dataset.id = e.expense_id;
        tr.dataset.category = e.expense_category;

        tr.innerHTML = `
            <td style="font-weight:600;">${esc(e.description)}</td>
            <td style="color:var(--text-soft);font-size:13px;">${esc(e.property_name)}</td>
            <td style="color:var(--text-soft);font-size:13px;">${esc(e.unit_name)}</td>
            <td style="color:var(--text-soft);font-size:12px;">${fmtDate(e.expense_date)}</td>
            <td>
                <span class="badge" style="background:${col}22;color:${col};">
                    ${esc(e.expense_category)}
                </span>
            </td>
            <td style="font-weight:700;color:var(--danger);">₱ ${fmt(e.amount, 0)}</td>
            <td>
                <div class="tbl-actions">
                    <button class="btn-icon btn-edit" data-action="edit" data-id="${e.expense_id}" title="Edit">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="btn-icon btn-delete" data-action="delete" data-id="${e.expense_id}" title="Delete">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                            <path d="M10 11v6M14 11v6"/>
                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                        </svg>
                    </button>
                </div>
            </td>
        `;

        DOM.expensesBody.appendChild(tr);
        total += parseFloat(e.amount || 0);
    });

    if (DOM.recordCount) DOM.recordCount.textContent = expenses.length;
    if (DOM.footerTotal) DOM.footerTotal.textContent = fmt(total, 0);

    // Store expense data on rows for edit lookup
    _expenseCache = allExpensesData;
}

let _expenseCache = [];

// ─────────────────────────────────────────────────────────
//  RENDER: STATS
// ─────────────────────────────────────────────────────────
function renderStats(stats) {
    const total = stats.total || 0;

    DOM.statTotal.textContent = fmt(total, 0);
    DOM.statMaintenance.textContent = fmt(stats.maintenance, 0);
    DOM.statUtilities.textContent = fmt(stats.utilities, 0);
    DOM.statAdmin.textContent = fmt(stats.admin, 0);

    const pct = (val) => total > 0 ? Math.round(val / total * 100) : 0;

    DOM.statMaintenancePercent.textContent = pct(stats.maintenance) + '%';
    DOM.statUtilitiesPercent.textContent = pct(stats.utilities) + '%';
    DOM.statAdminPercent.textContent = pct(stats.admin) + '%';
}

// ─────────────────────────────────────────────────────────
//  RENDER: LEGEND
// ─────────────────────────────────────────────────────────
function renderLegend(categories) {
    DOM.legendContainer.innerHTML = '';
    const total = categories.reduce((s, c) => s + c.total, 0);

    categories.forEach(c => {
        const pct = total > 0 ? Math.round(c.total / total * 100) : 0;
        const col = catColour(c.category);
        const div = document.createElement('div');
        div.className = 'legend-item';
        div.innerHTML = `
            <div class="legend-dot" style="background:${col};"></div>
            <span class="legend-label">${esc(c.category)}</span>
            <span class="legend-val">${pct}%</span>
        `;
        DOM.legendContainer.appendChild(div);
    });
}

// ─────────────────────────────────────────────────────────
//  RENDER: CHARTS
// ─────────────────────────────────────────────────────────
function renderCharts(trends, categories) {
    if (trendChart) { trendChart.destroy(); trendChart = null; }
    if (donutChart) { donutChart.destroy(); donutChart = null; }

    // ── Trend line ──────────────────────────────
    trendChart = new Chart(DOM.trendCanvas, {
        type: 'line',
        data: {
            labels: trends.map(t => t.label),
            datasets: [{
                label: 'Total Expenses',
                data: trends.map(t => t.amount),
                borderColor: '#2563c4',
                backgroundColor: 'rgba(37,99,196,.08)',
                borderWidth: 2.5,
                tension: 0.4,
                fill: true,
                pointRadius: 5,
                pointBackgroundColor: '#2563c4',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } },
                },
                y: {
                    grid: { color: 'rgba(0,0,0,.04)' },
                    ticks: {
                        font: { size: 11 },
                        callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v),
                    },
                },
            },
        },
    });

    // ── Donut ───────────────────────────────────
    if (categories.length) {
        donutChart = new Chart(DOM.donutCanvas, {
            type: 'doughnut',
            data: {
                labels: categories.map(c => c.category),
                datasets: [{
                    data: categories.map(c => c.total),
                    backgroundColor: categories.map(c => catColour(c.category)),
                    borderColor: '#fff',
                    borderWidth: 2,
                    hoverOffset: 10,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } },
            },
        });
    }
}

// ─────────────────────────────────────────────────────────
//  DATA LOADING
// ─────────────────────────────────────────────────────────
function showExpTableSkeleton() {
    if (!DOM.expensesBody) return;
    DOM.emptyState.style.display = 'none';
    const foot = $('expTableFoot');
    if (foot) foot.style.display = 'none';

    const cols = 7;
    const widths = [70, 55, 40, 50, 45, 35, 60]; // % width per skeleton bar, roughly matches column content
    let rows = '';
    for (let r = 0; r < 5; r++) {
        let cells = '';
        for (let c = 0; c < cols; c++) {
            cells += `<td><div class="ps-skel-block" style="height:12px;width:${widths[c]}%;"></div></td>`;
        }
        rows += `<tr class="ts-skel-row">${cells}</tr>`;
    }
    DOM.expensesBody.innerHTML = rows;
}

async function loadExpenses() {
    showExpTableSkeleton();

    const params = new URLSearchParams({
        month: EXPENSES.currentMonth,
    });

    const search = DOM.searchInput?.value.trim() || '';
    const category = DOM.categoryFilter?.value || '';

    if (search) params.set('q', search);
    if (category) params.set('category', category);

    try {
        const res = await fetch(`${EXPENSES.apiUrl}?${params}`);
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Failed to load expenses.', 'error');
            return;
        }

        expCurrentPage = 1;
        paginateExpenses(data.expenses || []);
        renderStats(data.stats || {});
        renderCharts(data.trends || [], data.categories || []);
        renderLegend(data.categories || []);

        // Return the fresh expenses so callers can use it
        return data.expenses || [];

    } catch (err) {
        console.error('loadExpenses error:', err);
        showToast('Error loading expenses.', 'error');
        return [];
    }
}

// ─────────────────────────────────────────────────────────
//  MODAL
// ─────────────────────────────────────────────────────────
const ExpenseModal = {
    open() {
        DOM.modalTitle.textContent = 'Log Expense';
        DOM.editId.value = '';
        DOM.fProperty.value = '';
        filterUnitOptions('');
        DOM.fUnit.value = '';
        DOM.fCategory.value = '';
        DOM.fDescription.value = '';
        DOM.fAmount.value = '';
        DOM.fDate.value = new Date().toISOString().split('T')[0];
        DOM.modal.classList.add('open');
    },

    openEdit(expense) {
        DOM.modalTitle.textContent = 'Edit Expense';
        DOM.editId.value = expense.expense_id;
        DOM.fProperty.value = expense.property_id || '';
        filterUnitOptions(DOM.fProperty.value);
        DOM.fUnit.value = expense.unit_id || '';
        DOM.fCategory.value = expense.expense_category || '';
        DOM.fDescription.value = expense.description || '';
        DOM.fAmount.value = expense.amount || '';
        DOM.fDate.value = expense.expense_date || '';
        DOM.modal.classList.add('open');
    },

    close() {
        DOM.modal.classList.remove('open');
    },
};

// ─────────────────────────────────────────────────────────
//  CATEGORY DROPDOWN HELPERS
// ─────────────────────────────────────────────────────────
function refreshCatDropdown(newCat) {
    if (!newCat) return;
    const menu = document.getElementById('expCatMenu');
    if (!menu) return;
    // Check if option already exists
    const exists = Array.from(menu.querySelectorAll('.exp-cat-opt'))
        .some(btn => btn.dataset.value === newCat);
    if (exists) return;
    // Add new option
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'exp-cat-opt';
    btn.dataset.value = newCat;
    btn.onclick = function () { selectExpCatOpt(this); };
    btn.innerHTML = `<span class="exp-cat-dot" data-cat="${newCat}"></span>${newCat}`;
    menu.appendChild(btn);
}

// Removes a category from the filter dropdown only if no
// expenses in the freshly-loaded list still use it.
function removeCatFromDropdownIfEmpty(deletedCat, freshExpenses) {
    if (!deletedCat) return;
    const list = Array.isArray(freshExpenses) ? freshExpenses : _expenseCache;
    const stillUsed = list.some(e => e.expense_category === deletedCat);
    if (stillUsed) return;
    const menu = document.getElementById('expCatMenu');
    if (!menu) return;
    const btn = Array.from(menu.querySelectorAll('.exp-cat-opt'))
        .find(b => b.dataset.value === deletedCat);
    if (btn) btn.remove();
}

// ─────────────────────────────────────────────────────────
//  FORM SAVE
// ─────────────────────────────────────────────────────────
async function saveExpense() {
    const expense_id = DOM.editId.value;
    const category = DOM.fCategory.value;
    const description = DOM.fDescription.value.trim();
    const amount = DOM.fAmount.value;
    const date = DOM.fDate.value;

    if (!category || !description || !amount || !date) {
        showToast('Please fill in all required fields.', 'error');
        return;
    }

    DOM.btnSave.disabled = true;
    DOM.btnSave.textContent = 'Saving…';

    try {
        const fd = new FormData();
        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
        fd.append('action', expense_id ? 'update' : 'create');
        if (expense_id) fd.append('expense_id', expense_id);
        fd.append('property_id', DOM.fProperty.value);
        fd.append('unit_id', DOM.fUnit.value);
        fd.append('expense_category', category);
        fd.append('description', description);
        fd.append('amount', amount);
        fd.append('expense_date', date);

        const res = await fetch(EXPENSES.apiUrl, { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            showToast(expense_id ? 'Expense updated!' : 'Expense logged!');
            _psChannel.postMessage({ type: 'transaction_saved' });
            sessionStorage.setItem('txn_needs_refresh', '1');
            ExpenseModal.close();
            await loadExpenses();
            refreshCatDropdown(category);
        } else {
            showToast(json.message || 'Something went wrong.', 'error');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    } finally {
        DOM.btnSave.disabled = false;
        DOM.btnSave.textContent = 'Save Expense';
    }
}

// ─────────────────────────────────────────────────────────
//  EXPORT CSV
// ─────────────────────────────────────────────────────────
function exportCSV() {
    if (!_expenseCache.length) {
        showToast('No data to export.', 'error');
        return;
    }

    const headers = ['Description', 'Property', 'Unit', 'Date', 'Category', 'Amount'];
    const rows = _expenseCache.map(e => [
        e.description,
        e.property_name,
        e.unit_name,
        e.expense_date,
        e.expense_category,
        e.amount,
    ]);

    const csvContent = [headers, ...rows]
        .map(r => r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','))
        .join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `expenses_${EXPENSES.currentMonth}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

// ─────────────────────────────────────────────────────────
//  TABLE DELEGATION (edit / delete buttons)
// ─────────────────────────────────────────────────────────
function handleTableClick(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    const id = parseInt(btn.dataset.id, 10);
    const action = btn.dataset.action;

    if (action === 'delete') {
        deleteExpense(id);
        return;
    }

    if (action === 'edit') {
        const expense = _expenseCache.find(x => +x.expense_id === id);
        if (expense) ExpenseModal.openEdit(expense);
        return;
    }
}

// ─────────────────────────────────────────────────────────
//  INIT
// ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Search & filter
    DOM.searchInput?.addEventListener('input', loadExpenses);
    DOM.categoryFilter?.addEventListener('change', loadExpenses);

    // Modal open / close
    DOM.btnOpenAdd?.addEventListener('click', () => ExpenseModal.open());
    DOM.modal?.addEventListener('click', e => { if (e.target === DOM.modal) ExpenseModal.close(); });
    DOM.fProperty?.addEventListener('change', () => filterUnitOptions(DOM.fProperty.value));
    filterUnitOptions(DOM.fProperty.value);

    // Save button
    DOM.btnSave?.addEventListener('click', saveExpense);

    // Export
    DOM.btnExport?.addEventListener('click', exportCSV);

    // Table row actions (delegated)
    DOM.expensesBody?.addEventListener('click', handleTableClick);

    // Initial data load — use server-rendered data if available (no network round-trip),
    // otherwise fall back to fetching (e.g. if PHP couldn't provide it for some reason)
    if (window.PS_EXPENSES_INITIAL) {
        const initial = window.PS_EXPENSES_INITIAL;
        expCurrentPage = 1;
        paginateExpenses(initial.expenses || []);
        renderStats(initial.stats || {});
        renderCharts(initial.trends || [], initial.categories || []);
        renderLegend(initial.categories || []);
    } else {
        loadExpenses();
    }
});

// ─────────────────────────────────────────────────────────
//  BROADCAST CHANNEL — real-time sync with transactions page
// ─────────────────────────────────────────────────────────
const _psChannel = new BroadcastChannel('propsight_data');
_psChannel.onmessage = (e) => {
    if (['payment_saved', 'payment_deleted', 'transaction_saved', 'transaction_deleted'].includes(e.data?.type)) {
        loadExpenses();
    }
};

/* ══════════════════════════════════════════════════════════════════════
   MONTH PICKER  (moved from inline PHP script)
══════════════════════════════════════════════════════════════════════ */
(function () {
    const _initVal = document.getElementById('expMonthFilter')?.value || '';
    const _initParts = _initVal.split('-');
    let expPickerYear = _initParts[0] ? parseInt(_initParts[0]) : new Date().getFullYear();
    let expSelectedMonth = _initParts[1] || String(new Date().getMonth() + 1).padStart(2, '0');

    function _highlightActive() {
        document.querySelectorAll('.exp-picker-month-btn').forEach(b => {
            const isActive = b.dataset.month === expSelectedMonth;
            b.classList.toggle('exp-picker-active', isActive);
            b.style.background = isActive ? 'var(--primary,#3b6ef5)' : 'var(--white)';
            b.style.borderColor = isActive ? 'var(--primary,#3b6ef5)' : 'var(--border)';
            b.style.color = isActive ? 'white' : 'var(--text)';
            b.style.fontWeight = isActive ? '700' : '500';
        });
    }

    window.toggleExpMonthPicker = function () {
        const d = document.getElementById('expMonthPickerDropdown');
        const isOpen = d.style.display !== 'none';
        d.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) _highlightActive();
    };

    window.closeExpMonthPicker = function () {
        document.getElementById('expMonthPickerDropdown').style.display = 'none';
    };

    window.changeExpPickerYear = function (dir) {
        const newYear = expPickerYear + dir;
        if (newYear < 2000 || newYear > new Date().getFullYear() + 1) return;
        expPickerYear = newYear;
        document.getElementById('expPickerYear').textContent = expPickerYear;
    };

    window.selectExpPickerMonth = function (btn) {
        expSelectedMonth = btn.dataset.month;
        _highlightActive();
    };

    window.applyExpMonthPicker = function () {
        const val = expPickerYear + '-' + expSelectedMonth;
        const names = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        const label = names[parseInt(expSelectedMonth) - 1] + ' ' + expPickerYear;
        document.getElementById('expMonthFilter').value = val;
        document.getElementById('expMonthPickerLabel').textContent = label;
        document.getElementById('expMonthPickerLabel2').textContent = label;
        closeExpMonthPicker();
        const url = new URL(location.href);
        url.searchParams.set('month', val);
        history.replaceState(null, '', url);
        if (typeof loadExpenses === 'function') loadExpenses();
    };

    document.addEventListener('click', function (e) {
        const wrap = document.getElementById('expMonthPickerWrap');
        if (wrap && !wrap.contains(e.target)) closeExpMonthPicker();
    });
})();


/* ══════════════════════════════════════════════════════════════════════
   CATEGORY DROPDOWN  (moved from inline PHP script)
══════════════════════════════════════════════════════════════════════ */
(function () {
    const CAT_COLOURS = {
        Maintenance: '#E74C3C',
        Utilities: '#2563c4',
        Salaries: '#2ECC71',
        Admin: '#deaf37',
        Insurance: '#8B5CF6',
        Other: '#94a3b8',
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.exp-cat-dot').forEach(dot => {
            const col = CAT_COLOURS[dot.dataset.cat] || '#94a3b8';
            dot.style.background = col;
        });
    });

    window.toggleExpCatDropdown = function () {
        const menu = document.getElementById('expCatMenu');
        const chevron = document.getElementById('expCatChevron');
        const wrap = document.getElementById('expCatDropdownWrap');
        if (!menu) return;
        const isOpen = menu.style.display !== 'none';
        menu.style.display = isOpen ? 'none' : 'block';
        chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
        wrap.classList.toggle('open', !isOpen);
    };

    window.selectExpCatOpt = function (btn) {
        const val = btn.dataset.value;

        const hidden = document.getElementById('categoryFilter');
        if (hidden) { hidden.value = val; hidden.dispatchEvent(new Event('change')); }

        document.getElementById('expCatTriggerLabel').textContent = btn.textContent.trim();

        document.querySelectorAll('.exp-cat-opt').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const menu = document.getElementById('expCatMenu');
        const chevron = document.getElementById('expCatChevron');
        const wrap = document.getElementById('expCatDropdownWrap');
        if (menu) menu.style.display = 'none';
        if (chevron) chevron.style.transform = '';
        if (wrap) wrap.classList.remove('open');
    };

    document.addEventListener('click', function (e) {
        const wrap = document.getElementById('expCatDropdownWrap');
        if (wrap && !wrap.contains(e.target)) {
            const menu = document.getElementById('expCatMenu');
            const chevron = document.getElementById('expCatChevron');
            if (menu) menu.style.display = 'none';
            if (chevron) chevron.style.transform = '';
            wrap.classList.remove('open');
        }
    });
})();


/* ══════════════════════════════════════════════════════════════════════
   DELETE CONFIRMATION MODAL
══════════════════════════════════════════════════════════════════════ */
let _pendingDeleteId = null;

function deleteExpense(expense_id) {
    _pendingDeleteId = expense_id;
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.classList.add('open');
}

function closeDeleteModal() {
    _pendingDeleteId = null;
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.classList.remove('open');
}

async function _doDeleteExpense() {
    const expense_id = _pendingDeleteId;
    if (!expense_id) return;
    closeDeleteModal();

    // Capture the category BEFORE removing the row
    const row = document.querySelector(`tr[data-id="${expense_id}"]`);
    const deletedCat = row?.dataset.category || '';

    try {
        const fd = new FormData();
        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
        fd.append('action', 'delete');
        fd.append('expense_id', expense_id);

        const res = await fetch(EXPENSES.apiUrl, { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            showToast('Expense deleted.');
            _psChannel.postMessage({ type: 'transaction_deleted' });
            sessionStorage.setItem('txn_needs_refresh', '1');

            if (row) {
                row.style.transition = 'opacity .3s';
                row.style.opacity = '0';
                setTimeout(async () => {
                    row.remove();
                    // loadExpenses returns the fresh list — pass it to the
                    // dropdown cleaner so it checks up-to-date data
                    const freshList = await loadExpenses();
                    removeCatFromDropdownIfEmpty(deletedCat, freshList);
                }, 350);
            } else {
                const freshList = await loadExpenses();
                removeCatFromDropdownIfEmpty(deletedCat, freshList);
            }
        } else {
            showToast(json.message || 'Delete failed.', 'error');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const confirmBtn = document.getElementById('btnConfirmDelete');
    if (confirmBtn) confirmBtn.addEventListener('click', _doDeleteExpense);

    const overlay = document.getElementById('deleteConfirmModal');
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeDeleteModal();
        });
    }
});