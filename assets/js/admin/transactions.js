(function () {
        const rows = Array.from(document.querySelectorAll('#tableBody tr'));
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

        function applyFilters() {
            const type = typeFilter.value;
            const cat = catFilter.value;
            const month = monthFilter.value;
            let n = 0;

            rows.forEach(function (row) {
                const show =
                    (!type || row.dataset.type === type) &&
                    (!cat || row.dataset.category === cat) &&
                    (!month || row.dataset.month === month);

                row.style.display = show ? '' : 'none';
                if (show) n++;
            });

            filterCount.textContent = n + ' result' + (n !== 1 ? 's' : '');
            emptyState.style.display = n === 0 ? 'block' : 'none';
            clearBtn.style.display = (type || cat) ? 'inline-block' : 'none';
        }

        window.clearFilters = function () {
            typeFilter.value = '';
            catFilter.value = '';
            applyFilters();
        };

        document.getElementById('exportCsvBtn').addEventListener('click', function () {
            const visible = rows.filter(r => r.style.display !== 'none');
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

        typeFilter.addEventListener('change', applyFilters);
        catFilter.addEventListener('change', applyFilters);
        monthFilter.addEventListener('change', function () {
            if (monthFilter && monthFilter.value) {
                sessionStorage.setItem('transactionsMonthFilter', monthFilter.value);
            }
            applyFilters();
        });

        applyFilters();
    })();

function openAddTxn() { document.getElementById('addTxnModal').style.display = 'flex'; }
    function closeAddTxn() { document.getElementById('addTxnModal').style.display = 'none'; }

    function saveTransaction() {
    const err = document.getElementById('txnError');
    const btn = document.getElementById('saveTxnBtn');
    const date = document.getElementById('txn_date').value;
    const amt = parseFloat(document.getElementById('txn_amount').value);
    const desc = document.getElementById('txn_desc').value.trim();
    const type = document.getElementById('txn_type').value;
    const cat = document.getElementById('txn_cat').value.trim();
    const prop = document.getElementById('txn_prop').value;
    const ref = document.getElementById('txn_ref').value.trim();

    if (!date || !amt || amt <= 0 || !desc) {
        err.textContent = 'Date, amount and description are required.';
        err.style.display = 'block';
        return;
    }

    err.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const fd = new FormData();
    fd.append('action', 'add');
    fd.append('transaction_date', date);
    fd.append('amount', amt);
    fd.append('description', desc);
    fd.append('type', type);
    fd.append('category', cat);
    fd.append('property_id', prop);
    if (ref) fd.append('reference_no', ref);

    fetch('../../api/transactions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.textContent = 'Save Transaction';

            if (d.success) {
                closeAddTxn();

                showToast("Transaction saved!", "success", "Transaction saved!");
                setTimeout(() => refreshTransactionsTable(), 600);
            } else {
                err.textContent = d.message || 'Error saving.';
                err.style.display = 'block';
            }
        })
        .catch(() => {
            err.textContent = 'Network error.';
            err.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Save Transaction';
        });
}

function refreshTransactionsTable() {
    const params = new URLSearchParams(window.location.search);
    fetch('../../api/transactions.php?' + params.toString() + '&_=' + Date.now(), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) { location.reload(); return; }
            renderTransactionsTable(data.transactions || []);
            updateTransactionStats(data);
        })
        .catch(() => location.reload());
}

function fmtPesoTxn(v) {
    return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderTransactionsTable(transactions) {
    // Try to find the main transactions tbody
    const tbody = document.querySelector('#transactionsTable tbody, .transactions-table tbody, table tbody');
    if (!tbody) { location.reload(); return; }

    if (!transactions.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">No transactions found.</td></tr>';
        return;
    }

    tbody.innerHTML = transactions.map(t => {
        const typeCls = t.type === 'Income' ? 'success' : 'danger';
        const amtFmt  = fmtPesoTxn(parseFloat(t.amount || 0));
        const sign    = t.type === 'Income' ? '+' : '-';
        return `<tr data-txn-id="${t.id}">
          <td style="font-size:.8rem;color:#64748b;">${t.date_label || t.transaction_date || '—'}</td>
          <td>${t.description || '—'}</td>
          <td style="font-size:.8rem;">${t.category || '—'}</td>
          <td style="font-size:.8rem;">${t.property_name || '—'}</td>
          <td style="font-size:.8rem;color:#64748b;">${t.reference_no || '—'}</td>
          <td><span class="badge badge-${typeCls}">${t.type}</span></td>
          <td style="font-weight:700;color:${t.type==='Income'?'#16a34a':'#dc2626'}">${sign}${amtFmt}</td>
          <td><button class="tbl-btn danger" onclick="deleteTxn(${t.id})">Delete</button></td>
        </tr>`;
    }).join('');
}

function updateTransactionStats(data) {
    const incomeEl  = document.querySelector('[data-rt-txn="income"]');
    const expenseEl = document.querySelector('[data-rt-txn="expense"]');
    const netEl     = document.querySelector('[data-rt-txn="net"]');
    if (incomeEl)  incomeEl.textContent  = fmtPesoTxn(parseFloat(data.income  || 0));
    if (expenseEl) expenseEl.textContent = fmtPesoTxn(parseFloat(data.expense || 0));
    if (netEl)     netEl.textContent     = fmtPesoTxn(parseFloat((data.income||0) - (data.expense||0)));
}
