
let currentPage = 1;
const rowsPerPage = 10;

function paginateTable() {
    const allRows = Array.from(document.querySelectorAll('#tableBody tr'));
    const visibleRows = allRows.filter(row => row.style.display !== 'none' && !row.classList.contains('paginated-hidden'));

    const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
    const startIdx = (currentPage - 1) * rowsPerPage;
    const endIdx = startIdx + rowsPerPage;

    // Hide all rows first
    visibleRows.forEach((row, idx) => {
        if (idx >= startIdx && idx < endIdx) {
            row.style.display = '';
            row.classList.remove('paginated-hidden');
        } else {
            row.classList.add('paginated-hidden');
            row.style.display = 'none';
        }
    });

    // Update or create pagination controls
    updatePaginationControls(visibleRows.length, totalPages);
}

function updatePaginationControls(totalVisible, totalPages) {
    let paginationDiv = document.getElementById('txnPagination');

    if (totalPages <= 1) {
        if (paginationDiv) paginationDiv.remove();
        return;
    }

    if (!paginationDiv) {
        paginationDiv = document.createElement('div');
        paginationDiv.id = 'txnPagination';
        paginationDiv.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--border);';
        document.querySelector('.table-wrap').appendChild(paginationDiv);
    }

    const startIdx = (currentPage - 1) * rowsPerPage + 1;
    const endIdx = Math.min(currentPage * rowsPerPage, totalVisible);

    let html = `
        <div style="font-size:13px;color:var(--text-soft);">
            Showing ${startIdx} - ${endIdx} of ${totalVisible}
        </div>
        <div style="display:flex;gap:4px;">
    `;

    if (currentPage > 1) {
        html += `<button onclick="changePage(${currentPage - 1})" style="padding:6px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:transparent;color:var(--text);cursor:pointer;">‹ Prev</button>`;
    }

    for (let i = 1; i <= totalPages; i++) {
        const isActive = i === currentPage;
        html += `<button onclick="changePage(${i})" style="padding:6px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:${isActive ? 'var(--primary)' : 'transparent'};color:${isActive ? 'white' : 'var(--text)'};cursor:pointer;">${i}</button>`;
    }

    if (currentPage < totalPages) {
        html += `<button onclick="changePage(${currentPage + 1})" style="padding:6px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:13px;background:transparent;color:var(--text);cursor:pointer;">Next ›</button>`;
    }

    html += '</div>';
    paginationDiv.innerHTML = html;
}

window.changePage = function (page) {
    currentPage = page;
    paginateTable();
};

if (sessionStorage.getItem('txn_needs_refresh') === '1') {
    sessionStorage.removeItem('txn_needs_refresh');
    // Small delay to let the page fully render first
    setTimeout(refreshTransactionsTable, 300);
}

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
