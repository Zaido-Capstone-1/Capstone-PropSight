const _psChannel = new BroadcastChannel('propsight_data');

/* ── Chart ── */
const _psCollectionChart = new Chart(document.getElementById('collectionChart'), {
    type: 'bar',
    data: {
        labels: window.__PS_PAYMENTS__.trendLabels,
        datasets: [{
            label: 'Collected',
            data: window.__PS_PAYMENTS__.trendCollected,
            backgroundColor: 'rgba(46,204,113,0.7)',
            borderRadius: 8,
            borderSkipped: false
        }, {
            label: 'Pending + Overdue',
            data: window.__PS_PAYMENTS__.trendOutstanding,
            backgroundColor: 'rgba(231,76,60,0.4)',
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'left',
                labels: { usePointStyle: true, font: { size: 11 } }
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
            y: {
                grid: { color: 'rgba(0,0,0,.05)' },
                ticks: { callback: v => '₱' + v.toLocaleString(), font: { size: 11 } }
            }
        }
    }
});

/* ── Client-side filter & pagination ── */
let payCurrentPage = 1;
const payRowsPerPage = 10;

function applyPayFilters() {
    const q = (document.querySelector('input[name="q"]')?.value || '').toLowerCase().trim();
    const status = document.getElementById('payStatusInput')?.value || '';
    const month = document.getElementById('monthPickerValue')?.value || '';

    document.querySelectorAll('.table-wrap tbody tr').forEach(row => {
        const show =
            (!q || (row.dataset.search || '').includes(q)) &&
            (!status || status === 'all' || row.dataset.status === status) &&
            (!month || month === 'all' || row.dataset.month === month);
        row.dataset.payVisible = show ? '1' : '0';
        if (!show) row.style.display = 'none';
    });

    const count = document.querySelectorAll('.table-wrap tbody tr[data-pay-visible="1"]').length;
    const empty = document.getElementById('payEmptyState');
    if (empty) empty.style.display = count === 0 ? 'block' : 'none';

    payCurrentPage = 1;
    paginatePay();
}

function paginatePay() {
    const visible = Array.from(document.querySelectorAll('.table-wrap tbody tr'))
        .filter(r => r.dataset.payVisible !== '0');
    const total = visible.length;
    const totalPages = Math.max(1, Math.ceil(total / payRowsPerPage));
    payCurrentPage = Math.min(payCurrentPage, totalPages);

    const start = (payCurrentPage - 1) * payRowsPerPage;
    const end = start + payRowsPerPage;

    // Show/hide all rows based on filter + page
    document.querySelectorAll('.table-wrap tbody tr').forEach(row => {
        if (row.dataset.payVisible === '0') {
            row.style.display = 'none';
        }
    });

    visible.forEach((row, i) => {
        row.style.display = (i >= start && i < end) ? '' : 'none';
    });

    renderPayFoot(total, totalPages, start, end);
}

function renderPayFoot(total, totalPages, start, end) {
    const foot = document.getElementById('payTableFoot');
    const info = document.getElementById('payPageInfo');
    const controls = document.getElementById('payPageControls');
    const prevBtn = document.getElementById('payPrevBtn');
    const nextBtn = document.getElementById('payNextBtn');
    if (!foot) return;

    if (total === 0) { foot.style.display = 'none'; return; }

    foot.style.display = '';
    info.innerHTML = `Showing <strong>${start + 1}–${Math.min(end, total)}</strong> of <strong>${total}</strong> payment(s)`;

    if (totalPages <= 1) {
        if (controls) controls.style.display = 'none';
    } else {
        if (controls) controls.style.display = 'flex';
        if (prevBtn) prevBtn.disabled = payCurrentPage <= 1;
        if (nextBtn) nextBtn.disabled = payCurrentPage >= totalPages;
        renderPayPageNumbers(totalPages);
    }
}

function renderPayPageNumbers(totalPages) {
    const wrap = document.getElementById('payPageNumbers');
    if (!wrap) return;
    wrap.innerHTML = '';
    const cur = payCurrentPage;
    const nums = [...new Set([1, totalPages, cur, cur - 1, cur + 1].filter(n => n >= 1 && n <= totalPages))].sort((a, b) => a - b);
    nums.forEach((n, i) => {
        if (i > 0 && n > nums[i - 1] + 1) {
            const el = document.createElement('span');
            el.className = 'txn-pg-ellipsis';
            el.textContent = '…';
            wrap.appendChild(el);
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'txn-pg-num' + (n === cur ? ' active' : '');
        btn.textContent = n;
        btn.onclick = () => { payCurrentPage = n; paginatePay(); };
        wrap.appendChild(btn);
    });
}

window.payChangePage = function (dir) { payCurrentPage += dir; paginatePay(); };

/* ── Custom status dropdown ── */
window.togglePayStatusDropdown = function () {
    const menu = document.getElementById('payStatusMenu');
    const chevron = document.getElementById('payStatusChevron');
    const wrap = document.getElementById('payStatusDropdownWrap');
    const isOpen = menu.style.display !== 'none';
    menu.style.display = isOpen ? 'none' : 'block';
    chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
    wrap.classList.toggle('open', !isOpen);
};

window.selectPayStatusOpt = function (btn) {
    document.getElementById('payStatusInput').value = btn.dataset.value;
    document.getElementById('payStatusTriggerLabel').textContent = btn.textContent.trim();
    document.querySelectorAll('#payStatusMenu .inv-status-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('payStatusMenu').style.display = 'none';
    document.getElementById('payStatusChevron').style.transform = '';
    document.getElementById('payStatusDropdownWrap').classList.remove('open');
    applyPayFilters();
};

document.addEventListener('click', function (e) {
    const wrap = document.getElementById('payStatusDropdownWrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('payStatusMenu').style.display = 'none';
        document.getElementById('payStatusChevron').style.transform = '';
        wrap.classList.remove('open');
    }
});

/* ── Search input ── */
document.querySelector('input[name="q"]')?.addEventListener('input', applyPayFilters);

/* ── Modal ── */
function openModal(mode, data = null) {
    document.getElementById('modalTitle').textContent = mode === 'edit' ? 'Edit Payment' : 'Record Payment';
    document.getElementById('formAction').value = mode;
    document.getElementById('submitBtn').textContent = mode === 'edit' ? 'Save Changes' : 'Save Payment';

    const dropdown = document.getElementById('formBookingId');
    const editDisplay = document.getElementById('editTenantDisplay');
    const manualWrap = document.getElementById('manualTenantWrap');
    const manualInput = document.getElementById('formManualTenant');
    const manualUnit = document.getElementById('formManualUnit');
    const manualToggleBtn = document.getElementById('manualTenantToggle');

    // Always reset manual-entry state when the modal opens
    manualWrap.style.display = 'none';
    manualInput.removeAttribute('required');
    manualInput.value = '';
    manualUnit.value = '';
    if (manualToggleBtn) manualToggleBtn.textContent = 'Enter name manually';

    if (mode === 'edit' && data) {
        const isManual = !data.booking_id && (data.manual_tenant_name || data.manual_unit_id);

        document.getElementById('formPaymentId').value = data.payment_id;
        document.getElementById('formPaymentDate').value = data.payment_date;
        document.getElementById('formAmountPaid').value = data.amount_paid;
        document.getElementById('formPaymentMethod').value = data.payment_method ?? '';
        document.getElementById('formPaymentStatus').value = data.payment_status;
        document.getElementById('formNotes').value = data.notes ?? '';

        let hiddenBooking = document.getElementById('hiddenBookingId');

        if (isManual) {
            // Manual (walk-in) payment — editable name/unit fields, no booking link
            dropdown.style.display = 'none';
            dropdown.removeAttribute('required');
            dropdown.value = '';
            if (manualToggleBtn) manualToggleBtn.style.display = 'none';
            editDisplay.style.display = 'none';

            if (hiddenBooking) hiddenBooking.remove();
            manualWrap.style.display = 'flex';
            manualInput.setAttribute('required', 'required');
            manualInput.value = data.manual_tenant_name || data.full_name || '';
            manualUnit.value = data.manual_unit_id || '';
        } else {
            dropdown.style.display = 'none';
            dropdown.removeAttribute('required');
            if (manualToggleBtn) manualToggleBtn.style.display = 'none';
            editDisplay.style.display = 'flex';

            if (!hiddenBooking) {
                hiddenBooking = Object.assign(document.createElement('input'), { type: 'hidden', name: 'booking_id', id: 'hiddenBookingId' });
                document.getElementById('paymentForm').appendChild(hiddenBooking);
            }
            hiddenBooking.value = data.booking_id;

            const name = data.full_name || '—';
            document.getElementById('editTenantName').textContent = name + (data.unit_number ? ' — ' + data.unit_number : '');
            document.getElementById('editTenantInitial').textContent = name.charAt(0).toUpperCase();

            const photoEl = document.getElementById('editTenantPhoto');
            if (data.profile_photo) {
                photoEl.src = '../../' + data.profile_photo;
                photoEl.style.display = 'block';
                document.getElementById('editTenantInitial').style.display = 'none';
                photoEl.onerror = function () {
                    photoEl.style.display = 'none';
                    document.getElementById('editTenantInitial').style.display = 'flex';
                };
            } else {
                photoEl.style.display = 'none';
                document.getElementById('editTenantInitial').style.display = 'flex';
            }

            dropdown.value = data.booking_id;
        }
    } else {
        dropdown.style.display = '';
        dropdown.setAttribute('required', 'required');
        if (manualToggleBtn) manualToggleBtn.style.display = '';
        editDisplay.style.display = 'none';
        document.getElementById('paymentForm').reset();
        document.getElementById('formPaymentDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('formPaymentStatus').value = 'paid';
        const hiddenBooking = document.getElementById('hiddenBookingId');
        if (hiddenBooking) hiddenBooking.remove();
    }

    document.getElementById('paymentModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('paymentModal').style.display = 'none';
    document.body.style.overflow = '';
}

/* ── Manual tenant name entry (Record Payment mode only) ── */
window.toggleManualTenant = function () {
    const dropdown = document.getElementById('formBookingId');
    const manualWrap = document.getElementById('manualTenantWrap');
    const manualInput = document.getElementById('formManualTenant');
    const manualUnit = document.getElementById('formManualUnit');
    const toggleBtn = document.getElementById('manualTenantToggle');
    const showingManual = manualWrap.style.display !== 'none';

    if (showingManual) {
        manualWrap.style.display = 'none';
        manualInput.removeAttribute('required');
        manualInput.value = '';
        manualUnit.value = '';
        dropdown.style.display = '';
        dropdown.setAttribute('required', 'required');
        toggleBtn.textContent = 'Enter name manually';
    } else {
        dropdown.style.display = 'none';
        dropdown.removeAttribute('required');
        dropdown.value = '';
        manualWrap.style.display = 'flex';
        manualInput.setAttribute('required', 'required');
        manualInput.focus();
        toggleBtn.textContent = 'Select from bookings instead';
    }
};

document.getElementById('paymentForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerText = 'Saving...';

    const editingId = document.getElementById('formPaymentId').value;

    fetch(this.getAttribute('action'), { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerText = 'Save Payment';
            if (data.success) {
                _psChannel.postMessage({ type: 'payment_saved' });
                showToast(data.message || 'Saved successfully', 'success');
                closeModal();
                const highlightId = data.payment_id || editingId;
                setTimeout(() => refreshPaymentsData(highlightId), 400);
            } else {
                showToast(data.message || 'Operation failed', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerText = 'Save Payment';
            showToast('Server error occurred', 'error');
        });
});

/* ── Delete ── */
function confirmDelete(id) {
    document.getElementById('deletePaymentId').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = '';
}

function deletePayment() {
    const id = document.getElementById('deletePaymentId').value;
    if (!id) return;

    const row = document.querySelector(`.table-wrap tbody tr[data-payment-id="${id}"]`);
    const deleteBtn = document.querySelector('#deleteModal .btn-danger');
    if (deleteBtn) { deleteBtn.disabled = true; deleteBtn.textContent = 'Deleting...'; }

    fetch('../../endpoints/payments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `form_action=delete&payment_id=${id}&csrf_token=${encodeURIComponent(window.PS_CSRF_TOKEN)}`
    })
        .then(r => r.json())
        .then(data => {
            if (deleteBtn) { deleteBtn.disabled = false; deleteBtn.textContent = 'Delete'; }
            if (data.success) {
                _psChannel.postMessage({ type: 'payment_deleted' });
                showToast(data.message || 'Deleted successfully', 'success');
                closeDeleteModal();

                if (row) {
                    row.classList.add('pay-row-deleting');
                    setTimeout(() => {
                        row.classList.add('pay-row-collapsing');
                        setTimeout(() => {
                            row.remove();
                            refreshPaymentsData();
                        }, 220);
                    }, 280);
                } else {
                    refreshPaymentsData();
                }
            } else {
                showToast(data.message || 'Delete failed', 'error');
            }
        })
        .catch(() => {
            if (deleteBtn) { deleteBtn.disabled = false; deleteBtn.textContent = 'Delete'; }
            showToast('Server error', 'error');
        });
}

/* ── Refresh: fetch all rows unfiltered, re-render table + stats + chart ── */
function fmtPeso(v) {
    return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function updatePaymentStats(stats) {
    if (!stats) return;
    const collected = parseFloat(stats.collected) || 0;
    const pending = parseFloat(stats.pending_amt) || 0;
    const overdue = parseFloat(stats.overdue_amt) || 0;
    const totalCnt = parseInt(stats.total_cnt) || 0;
    const paidCnt = parseInt(stats.paid_cnt) || 0;
    const rate = totalCnt > 0 ? Math.round((paidCnt / totalCnt) * 100) : 0;

    const elCollected = document.getElementById('statCollected');
    const elPending = document.getElementById('statPending');
    const elPendingCnt = document.getElementById('statPendingCnt');
    const elOverdue = document.getElementById('statOverdue');
    const elOverdueCnt = document.getElementById('statOverdueCnt');
    const elRate = document.getElementById('statCollectionRate');

    if (elCollected) elCollected.textContent = '₱ ' + Math.round(collected).toLocaleString();
    if (elPendingCnt) elPendingCnt.textContent = (parseInt(stats.pending_cnt) || 0) + ' tenants';
    if (elPending) elPending.childNodes[0].textContent = '₱ ' + Math.round(pending).toLocaleString() + ' ';
    if (elOverdueCnt) elOverdueCnt.textContent = (parseInt(stats.overdue_cnt) || 0) + ' tenants';
    if (elOverdue) elOverdue.childNodes[0].textContent = '₱ ' + Math.round(overdue).toLocaleString() + ' ';
    if (elRate) elRate.textContent = rate + '%';
}

function updateCollectionChart(trend) {
    if (!trend || !_psCollectionChart) return;
    _psCollectionChart.data.labels = trend.map(t => t.mo);
    _psCollectionChart.data.datasets[0].data = trend.map(t => parseFloat(t.collected) || 0);
    _psCollectionChart.data.datasets[1].data = trend.map(t => parseFloat(t.outstanding) || 0);
    _psCollectionChart.update();
}

function refreshPaymentsData(highlightId) {
    fetch('../../endpoints/payments.php?status=all&month=all&_=' + Date.now(), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) { location.reload(); return; }
            renderPaymentsTable(data.records || [], highlightId);
            updatePaymentStats(data.stats);
            updateCollectionChart(data.trend);
            applyPayFilters();
        })
        .catch(() => location.reload());
}

// Kept for backward compatibility with existing call sites
function refreshPaymentsTable(highlightId) {
    refreshPaymentsData(highlightId);
}

function renderPaymentsTable(payments, highlightId) {
    const tbody = document.querySelector('.table-wrap tbody');
    if (!tbody) return;

    if (!payments.length) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-soft);">No payment records found.</td></tr>';
        applyPayFilters();
        return;
    }

    const badgeMap = { paid: ['success', 'Paid'], pending: ['pending', 'Pending'], late: ['danger', 'Overdue'] };
    const avatarStyle = 'width:36px;height:36px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#0f2744,#1a3a6b);color:#e8c86a;font-weight:700;font-size:0.82rem;display:flex;align-items:center;justify-content:center;';

    tbody.innerHTML = payments.map(p => {
        const [badgeCls, badgeLabel] = badgeMap[p.payment_status] || ['pending', p.payment_status];
        const displayName = p.full_name || p.tenant_name || '—';
        const parts = displayName.trim().split(' ').filter(Boolean);
        const initial = (parts[0] ? parts[0][0].toUpperCase() : '') + (parts.length > 1 ? parts[parts.length - 1][0].toUpperCase() : '');
        const monthVal = (p.payment_date || '').substring(0, 7);
        const searchVal = (p.payment_id + ' ' + displayName + ' ' + (p.unit_number || '')).toLowerCase();
        const hasPhoto = p.profile_photo && p.profile_photo !== 'null' && p.profile_photo.trim() !== '';
        const photoHtml = hasPhoto
            ? `<img src="../../${p.profile_photo}" alt="${initial}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;display:block;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
               <div style="${avatarStyle}display:none;">${initial}</div>`
            : `<div style="${avatarStyle}">${initial}</div>`;
        const payDate = p.payment_date ? new Date(p.payment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
        const payNum = '#PAY-' + String(p.payment_id).padStart(3, '0');
        const dataJson = JSON.stringify(p).replace(/"/g, '&quot;');

        return `<tr data-payment-id="${p.payment_id}"
                    data-status="${p.payment_status}"
                    data-month="${monthVal}"
                    data-search="${searchVal}">
          <td><strong>${payNum}</strong></td>
          <td><div style="display:flex;align-items:center;gap:8px;">${photoHtml}${displayName}</div></td>
          <td>${p.unit_number || '—'}</td>
          <td>${payDate}</td>
          <td style="font-weight:700;">${p.amount_paid ? fmtPeso(parseFloat(p.amount_paid)) : '—'}</td>
          <td>${p.payment_method || '—'}</td>
          <td><span class="badge badge-${badgeCls}">${badgeLabel}</span></td>
          <td style="color:var(--text-soft);font-size:12px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${(p.notes || '').replace(/"/g, '&quot;')}">${p.notes || '—'}</td>
          <td><div style="display:flex;gap:6px;justify-content:center;">
            <button class="btn-icon btn-edit" title="Edit" onclick="openModal('edit',${dataJson})">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="15" height="15"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn-icon btn-delete" title="Delete" onclick="confirmDelete(${p.payment_id})">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="15" height="15"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div></td>
        </tr>`;
    }).join('');

    if (highlightId) {
        const newRow = tbody.querySelector(`tr[data-payment-id="${highlightId}"]`);
        if (newRow) newRow.classList.add('pay-row-new');
    }

    applyPayFilters();
}

/* ── Misc ── */
function showToast(message, type = 'success') {
    Toast.fire({ icon: type, title: message });
}

document.querySelectorAll('.flash').forEach(f => {
    setTimeout(() => {
        f.style.transition = 'opacity .4s';
        f.style.opacity = '0';
        setTimeout(() => f.remove(), 400);
    }, 4000);
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal(); closeDeleteModal(); }
});

const observer = new MutationObserver(() => {
    const modal = document.getElementById('paymentModal');
    if (modal && modal.style.display === 'flex') {
        const first = modal.querySelector('select, input:not([type=hidden])');
        if (first) setTimeout(() => first.focus(), 100);
    }
});
observer.observe(document.getElementById('paymentModal'), { attributes: true, attributeFilter: ['style'] });

_psChannel.onmessage = (e) => {
    const valid = ['payment_saved', 'payment_deleted'];
    if (valid.includes(e.data?.type)) refreshPaymentsTable();
};

/* ── Initial run ── */
applyPayFilters();