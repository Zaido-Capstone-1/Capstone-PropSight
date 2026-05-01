/**
 * expenses.js — Expenses module JS
 * Depends on: Chart.js 4, SweetAlert2
 */

// ─────────────────────────────────────────────────────────
//  CONFIG
// ─────────────────────────────────────────────────────────
const EXPENSES = {
    apiUrl: '../../api/expenses.php',

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
        DOM.tableFooter.style.display = 'none';
        return;
    }

    DOM.emptyState.style.display = 'none';
    DOM.tableFooter.style.display = 'block';

    let total = 0;

    expenses.forEach(e => {
        const col = catColour(e.expense_category);
        const tr = document.createElement('tr');
        tr.dataset.id = e.expense_id;

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
            <td style="font-weight:700;color:var(--danger);">₱ ${fmt(e.amount)}</td>
            <td>
                <div class="tbl-actions">
                    <button class="btn-icon btn-edit" data-action="edit" data-id="${e.expense_id}">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Edit
                    </button>
                    <button class="btn-icon btn-delete" data-action="delete" data-id="${e.expense_id}">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                            <path d="M10 11v6M14 11v6"/>
                            <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                        </svg>
                        Delete
                    </button>
                </div>
            </td>
        `;

        DOM.expensesBody.appendChild(tr);
        total += parseFloat(e.amount || 0);
    });

    DOM.recordCount.textContent = expenses.length;
    DOM.footerTotal.textContent = fmt(total);

    // Store expense data on rows for edit lookup
    _expenseCache = expenses;
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
async function loadExpenses() {
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

        renderTable(data.expenses || []);
        renderStats(data.stats || {});
        renderCharts(data.trends || [], data.categories || []);
        renderLegend(data.categories || []);

    } catch (err) {
        console.error('loadExpenses error:', err);
        showToast('Error loading expenses.', 'error');
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
            ExpenseModal.close();
            await loadExpenses();
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
//  DELETE
// ─────────────────────────────────────────────────────────
async function deleteExpense(expense_id) {
    if (!confirm('Delete this expense? This cannot be undone.')) return;

    try {
        const fd = new FormData();
        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
        fd.append('action', 'delete');
        fd.append('expense_id', expense_id);

        const res = await fetch(EXPENSES.apiUrl, { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            showToast('Expense deleted.');
            const row = document.querySelector(`tr[data-id="${expense_id}"]`);
            if (row) {
                row.style.transition = 'opacity .3s';
                row.style.opacity = '0';
                setTimeout(() => { row.remove(); loadExpenses(); }, 350);
            } else {
                await loadExpenses();
            }
        } else {
            showToast(json.message || 'Delete failed.', 'error');
        }
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
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

    // Initial data load
    loadExpenses();
});