let currentPage = 1;
const rowsPerPage = 10;

/* ── Pagination ─────────────────────────────────────────────────────────── */

function paginateTable() {
    const allRows = Array.from(document.querySelectorAll('#tableBody tr'));
    // Rows passing the filter (not hidden by applyFilters, not already paginated-hidden)
    const visibleRows = allRows.filter(row =>
        row.style.display !== 'none' && !row.classList.contains('paginated-hidden')
    );

    const total = visibleRows.length;
    const totalPages = Math.max(1, Math.ceil(total / rowsPerPage));
    currentPage = Math.min(currentPage, totalPages);

    const startIdx = (currentPage - 1) * rowsPerPage;
    const endIdx = startIdx + rowsPerPage;

    visibleRows.forEach((row, idx) => {
        if (idx >= startIdx && idx < endIdx) {
            row.style.display = '';
            row.classList.remove('paginated-hidden');
        } else {
            row.classList.add('paginated-hidden');
            row.style.display = 'none';
        }
    });

    renderPaginationFooter(total, totalPages, startIdx, endIdx);
}

function renderPaginationFooter(total, totalPages, startIdx, endIdx) {
    const foot = document.getElementById('txnTableFoot');
    const info = document.getElementById('txnPageInfo');
    const controls = document.getElementById('txnPageControls');
    const prevBtn = document.getElementById('txnPrevBtn');
    const nextBtn = document.getElementById('txnNextBtn');

    if (!foot) return;

    // No data → hide entire tfoot
    if (total === 0) {
        foot.style.display = 'none';
        return;
    }

    // Has data → show tfoot + info text
    foot.style.display = '';
    const from = startIdx + 1;
    const to = Math.min(endIdx, total);
    info.innerHTML = `Showing <strong>${from}–${to}</strong> of <strong>${total}</strong> transaction(s)`;

    // Nav controls only needed when there is more than one page
    if (totalPages <= 1) {
        if (controls) controls.style.display = 'none';
        return;
    }

    if (controls) controls.style.display = 'flex';
    if (prevBtn) prevBtn.disabled = currentPage <= 1;
    if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

    renderPageNumbers(totalPages);
}

function renderPageNumbers(totalPages) {
    const wrap = document.getElementById('txnPageNumbers');
    if (!wrap) return;
    wrap.innerHTML = '';

    const cur = currentPage;
    const nums = new Set([1, totalPages, cur, cur - 1, cur + 1].filter(n => n >= 1 && n <= totalPages));
    const sorted = [...nums].sort((a, b) => a - b);

    sorted.forEach((n, idx) => {
        // Ellipsis gap
        if (idx > 0 && n > sorted[idx - 1] + 1) {
            const el = document.createElement('span');
            el.className = 'txn-pg-ellipsis';
            el.textContent = '…';
            wrap.appendChild(el);
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'txn-pg-num' + (n === cur ? ' active' : '');
        btn.textContent = n;
        btn.onclick = () => { currentPage = n; paginateTable(); };
        wrap.appendChild(btn);
    });
}

// Called by the chevron buttons in the tfoot
window.txnChangePage = function (dir) {
    currentPage += dir;
    paginateTable();
};

// Legacy alias kept for any existing callers
window.changePage = function (page) {
    currentPage = page;
    paginateTable();
};

/* ── Session-restore refresh ────────────────────────────────────────────── */
if (sessionStorage.getItem('txn_needs_refresh') === '1') {
    sessionStorage.removeItem('txn_needs_refresh');
    setTimeout(refreshTransactionsTable, 300);
}

/* ── Filters ────────────────────────────────────────────────────────────── */
(function () {
    const typeFilter = document.getElementById('typeFilter');
    const catFilter = document.getElementById('categoryFilter');
    const monthFilter = document.getElementById('monthFilter');
    const filterCount = document.getElementById('filterCount');
    const clearBtn = document.getElementById('clearFiltersBtn');
    const emptyState = document.getElementById('emptyState');

    const storedMonth = sessionStorage.getItem('transactionsMonthFilter');
    const navEntry = performance.getEntriesByType('navigation')[0];
    const isReload = navEntry ? navEntry.type === 'reload' : false;

    if (monthFilter && storedMonth && /^\d{4}-\d{2}$/.test(storedMonth) && !isReload) {
        monthFilter.value = storedMonth;
    }

    window.applyFilters = function () {
        const type = typeFilter?.value || '';
        const cat = catFilter?.value || '';
        const month = monthFilter?.value || '';
        let n = 0;

        document.querySelectorAll('#tableBody tr').forEach(function (row) {
            row.classList.remove('paginated-hidden');
            const show =
                (!type || row.dataset.type === type) &&
                (!cat || row.dataset.category === cat) &&
                (!month || row.dataset.month === month);

            row.style.display = show ? '' : 'none';
            if (show) n++;
        });

        currentPage = 1;
        paginateTable();

        if (filterCount) filterCount.textContent = n + ' result' + (n !== 1 ? 's' : '');
        if (emptyState) emptyState.style.display = n === 0 ? 'block' : 'none';
        if (clearBtn) clearBtn.style.display = (type || cat) ? 'inline-block' : 'none';
    };

    window.clearFilters = function () {
        if (typeFilter) typeFilter.value = '';
        if (catFilter) catFilter.value = '';
        window.applyFilters();
    };

    document.getElementById('exportCsvBtn')?.addEventListener('click', function () {
        const visible = Array.from(document.querySelectorAll('#tableBody tr'))
            .filter(r => r.style.display !== 'none');
        const headers = ['Date', 'Reference', 'Description', 'Category', 'Property', 'Type', 'Amount'];
        const lines = [headers.join(',')];
        visible.forEach(function (row) {
            const cells = Array.from(row.querySelectorAll('td')).map(function (td) {
                return '"' + td.innerText.replace(/"/g, '""').trim() + '"';
            });
            lines.push(cells.join(','));
        });
        const blob = new Blob([lines.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = Object.assign(document.createElement('a'), { href: url, download: 'transactions.csv' });
        a.click();
        URL.revokeObjectURL(url);
    });

    typeFilter?.addEventListener('change', window.applyFilters);
    catFilter?.addEventListener('change', window.applyFilters);
    monthFilter?.addEventListener('change', function () {
        if (monthFilter.value) sessionStorage.setItem('transactionsMonthFilter', monthFilter.value);
        window.applyFilters();
    });

    window.applyFilters();
})();

/* ── Table rendering helpers ────────────────────────────────────────────── */
function fmtPesoTxn(v) {
    return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderTransactionsTable(rows) {
    const tbody = document.querySelector('#tableBody');
    if (!tbody) { location.reload(); return; }

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;">No transactions found.</td></tr>';
        window.applyFilters();
        return;
    }

    tbody.innerHTML = rows.map(t => {
        const isIncome = t.type === 'Income';
        const amtFmt = fmtPesoTxn(parseFloat(t.amount || 0));
        const sign = isIncome ? '+' : '−';
        const monthVal = t.month_val || (t.transaction_date ? t.transaction_date.substring(0, 7) : '');
        return '<tr data-month="' + monthVal + '" data-type="' + (t.type || '') + '" data-category="' + (t.category || '') + '" data-amount="' + (t.amount || 0) + '">'
            + '<td style="color:var(--text-soft);font-size:12px;">' + (t.date_label || '—') + '</td>'
            + '<td><strong>' + (t.reference_no || '—') + '</strong></td>'
            + '<td>' + (t.description || '—') + '</td>'
            + '<td><span class="badge badge-blue">' + (t.category || '—') + '</span></td>'
            + '<td style="font-size:12px;color:var(--text-soft);">' + (t.property_name || '—') + '</td>'
            + '<td><span class="badge ' + (isIncome ? 'badge-green' : 'badge-red') + '">' + t.type + '</span></td>'
            + '<td style="font-weight:700;color:' + (isIncome ? '#16a34a' : '#dc2626') + '">' + sign + amtFmt + '</td>'
            + '</tr>';
    }).join('');

    window.applyFilters();
}

function updateTransactionStats(stats) {
    const incomeEl = document.querySelector('[data-rt-txn="income"]');
    const expenseEl = document.querySelector('[data-rt-txn="expense"]');
    const netEl = document.querySelector('[data-rt-txn="net"]');
    const countEl = document.querySelector('[data-rt-txn="count"]');
    if (incomeEl) incomeEl.textContent = fmtPesoTxn(parseFloat(stats.total_income || 0));
    if (expenseEl) expenseEl.textContent = fmtPesoTxn(parseFloat(stats.total_expense || 0));
    if (netEl) netEl.textContent = fmtPesoTxn(parseFloat(stats.net_profit || 0));
    if (countEl) countEl.textContent = stats.total_count || 0;
}

/* ── Real-time refresh ──────────────────────────────────────────────────── */
function refreshTransactionsTable() {
    const year = new Date().getFullYear();
    fetch('../../api/transactions.php?year=' + year + '&_=' + Date.now(), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) { location.reload(); return; }
            renderTransactionsTable(data.rows || []);
            updateTransactionStats(data.stats || {});
        })
        .catch(() => location.reload());
}

const _psChannel = new BroadcastChannel('propsight_data');
_psChannel.onmessage = (e) => {
    const valid = ['payment_saved', 'payment_deleted', 'transaction_saved', 'transaction_deleted'];
    if (valid.includes(e.data?.type)) refreshTransactionsTable();
};