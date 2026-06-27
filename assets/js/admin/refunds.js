// assets/js/admin/refunds.js
// Depends on: window.ALL_REFUNDS and window.PS_CSRF_TOKEN (set inline by refunds.php)

(function () {
    'use strict';

    // ── State ─────────────────────────────────────────────────────────────────
    let _filterStatus = 'all';
    let _filterSearch = '';
    let _page         = 1;
    const PER_PAGE    = 10;
    let _refundId     = null;

    // ── Filter ────────────────────────────────────────────────────────────────
    function getFiltered() {
        const q = _filterSearch.toLowerCase();
        return window.ALL_REFUNDS.filter(r => {
            const matchStatus = _filterStatus === 'all' || r.refund_status === _filterStatus;
            const matchSearch = !q ||
                r.guest_name.toLowerCase().includes(q)   ||
                String(r.booking_id ?? '').includes(q)   ||
                String(r.invoice_id ?? '').includes(q)   ||
                String(r.refund_id).includes(q)          ||
                r.refId.toLowerCase().includes(q)        ||
                r.refLabel.toLowerCase().includes(q);    // search by BK- or INV-
            return matchStatus && matchSearch;
        });
    }

    // ── Render ────────────────────────────────────────────────────────────────
    function renderTable() {
        const filtered   = getFiltered();
        const total      = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        _page = Math.min(_page, totalPages);
        const offset = (_page - 1) * PER_PAGE;
        const rows   = filtered.slice(offset, offset + PER_PAGE);

        const tbody = document.getElementById('refundTableBody');

        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9">
                <div class="refund-empty">
                    <svg width="40" height="40" fill="none" stroke="#ccc" stroke-width="1.5"
                        viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                    No refund requests found.
                </div>
            </td></tr>`;
        } else {
            tbody.innerHTML = rows.map(r => {
                const photoHtml = r.profile_photo
                    ? `<img src="../../${r.profile_photo}" alt="${esc(r.initial)}"
                           style="width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;"
                           onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                       <div class="avatar" style="display:none;">${esc(r.initial)}</div>`
                    : `<div class="avatar">${esc(r.initial)}</div>`;

                // Type badge: Invoice vs Booking
                const typeBadge = r.is_invoice
                    ? `<span style="font-size:10px;background:#eff6ff;color:#1d4ed8;padding:1px 6px;border-radius:4px;font-weight:600;">Invoice</span>`
                    : `<span style="font-size:10px;background:#f0fdf4;color:#16a34a;padding:1px 6px;border-radius:4px;font-weight:600;">Booking</span>`;

                const actionsHtml = r.isPending
                    ? `<button class="btn-approve" onclick='openApproveModal(${JSON.stringify(r)})'>
                           <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" width="13" height="13">
                               <polyline points="20 6 9 17 4 12"/>
                           </svg>Approve
                       </button>
                       <button class="btn-reject" onclick='openRejectModal(${JSON.stringify(r)})'>
                           <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" width="13" height="13">
                               <line x1="18" y1="6" x2="6" y2="18"/>
                               <line x1="6" y1="6" x2="18" y2="18"/>
                           </svg>Reject
                       </button>`
                    : r.isProcessing
                    ? `<button class="btn-approve" onclick='markComplete(${r.refund_id})'>
                           <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" width="13" height="13">
                               <polyline points="20 6 9 17 4 12"/>
                           </svg>Mark Complete
                       </button>`
                    : `<span style="font-size:12px;color:var(--text-soft);">${r.processed_date ? fmtDate(r.processed_date) : '—'}</span>`;

                const notesHtml = !r.isPending && r.admin_notes
                    ? `<div style="font-size:11px;color:var(--text-soft);margin-top:2px;"
                            title="${esc(r.admin_notes)}">${esc(r.admin_notes.substring(0, 30))}…</div>`
                    : '';

                return `<tr>
                    <td>
                        <strong>${esc(r.refId)}</strong>
                        <div style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                            ${typeBadge}
                            <span style="font-size:11px;color:var(--text-soft);">${esc(r.refLabel)}</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            ${photoHtml}
                            <div>
                                <div>${esc(r.guest_name)}</div>
                                <div style="font-size:11px;color:var(--text-soft);">${esc(r.email)}</div>
                            </div>
                        </div>
                    </td>
                    <td style="font-weight:700;">₱ ${r.refund_amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                    <td><div class="refund-reason-cell" title="${esc(r.refund_reason)}">${esc(r.refund_reason)}</div></td>
                    <td style="font-size:12px;color:var(--text-soft);">${fmtDate(r.created_at)}</td>
                    <td>${(function(m){
                        const map={'gcash':'#0070e0','maya':'#00b14f','card':'#6366f1','bank transfer':'#374151'};
                        const c=map[(m||'').toLowerCase()]||'#6b7280';
                        return m?'<span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:4px;background:'+c+'18;color:'+c+';">'+esc(m)+'</span>':'<span style="color:var(--text-soft);">&#8212;</span>';
                    })(r.refund_method)}</td>
                    <td>
                        <span class="badge ${esc(r.badgeClass)}">${esc(r.statusLabel)}</span>
                        ${notesHtml}
                    </td>
                    <td><div style="display:flex;gap:6px;justify-content:center;">${actionsHtml}</div></td>
                </tr>`;
            }).join('');
        }

        // Pagination info
        document.getElementById('refPageInfo').textContent =
            total === 0 ? 'No requests' :
            `Showing ${offset + 1}–${Math.min(offset + PER_PAGE, total)} of ${total} request(s)`;

        // Pagination controls
        const ctrl = document.getElementById('refPageControls');
        if (totalPages <= 1) { ctrl.innerHTML = ''; return; }

        let btns = '';
        if (_page > 1) {
            btns += `<button class="txn-chevron-btn" onclick="goPage(${_page - 1})">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14" height="14">
                    <polyline points="15 18 9 12 15 6"/>
                </svg></button>`;
        }
        for (let i = 1; i <= totalPages; i++) {
            btns += `<button class="txn-page-btn ${i === _page ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
        }
        if (_page < totalPages) {
            btns += `<button class="txn-chevron-btn" onclick="goPage(${_page + 1})">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14" height="14">
                    <polyline points="9 18 15 12 9 6"/>
                </svg></button>`;
        }
        ctrl.innerHTML = btns;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtDate(s) {
        if (!s) return '—';
        return new Date(s).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    // ── Status dropdown ───────────────────────────────────────────────────────
    window.toggleRefStatus = function () {
        const m = document.getElementById('refStatusMenu');
        m.style.display = m.style.display === 'none' ? 'block' : 'none';
    };

    window.selectRefStatus = function (btn) {
        document.querySelectorAll('#refStatusMenu .inv-status-opt').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        _filterStatus = btn.dataset.value;
        document.getElementById('refStatusLabel').textContent = btn.textContent.trim();
        document.getElementById('refStatusMenu').style.display = 'none';
        _page = 1;
        renderTable();
    };

    document.addEventListener('click', e => {
        const wrap = document.getElementById('refStatusWrap');
        if (wrap && !wrap.contains(e.target))
            document.getElementById('refStatusMenu').style.display = 'none';
    });

    // ── Search (debounced 220 ms) ─────────────────────────────────────────────
    let _searchTimer;
    document.getElementById('refSearchInput').addEventListener('input', function () {
        clearTimeout(_searchTimer);
        _searchTimer = setTimeout(() => {
            _filterSearch = this.value.trim();
            _page = 1;
            renderTable();
        }, 220);
    });

    // ── Pagination ────────────────────────────────────────────────────────────
    window.goPage = function (p) { _page = p; renderTable(); };

    // ── Approve modal ─────────────────────────────────────────────────────────
    window.openApproveModal = function (data) {
        _refundId = data.refund_id;
        document.getElementById('approveAmount').textContent =
            '₱' + parseFloat(data.refund_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        document.getElementById('approveGuest').textContent  = data.guest_name;
        document.getElementById('approveBkRef').textContent  = data.refLabel; // shows INV- or BK-
        // Show correct subtext depending on whether PayMongo can process it automatically
        const method = (data.refund_method || '').toLowerCase();
        const nonApi = ['bank transfer', 'qrph', 'dob', 'online_banking', 'bank_transfer', 'grabpay'];
        const isManual = nonApi.includes(method);
        document.getElementById('approveSubtext').textContent = isManual
            ? 'This refund will be marked for manual processing. Please return the amount to the guest directly and notify them by email.'
            : 'This will trigger the PayMongo refund and notify the guest by email.';
        document.getElementById('approveModal').style.display = 'flex';
    };

    window.markComplete = function (refundId) {
        if (!confirm('Mark this refund as completed? This confirms you have manually returned the amount to the guest.')) return;
        const fd = new FormData();
        fd.append('action',    'complete');
        fd.append('refund_id', refundId);
        fd.append('csrf_token', window.PS_CSRF_TOKEN);
        fetch('../../api/admin/process_refund.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message ?? 'Refund marked complete.', false);
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message ?? 'Something went wrong.', true);
                }
            })
            .catch(() => showToast('Network error. Please try again.', true));
    };

    window.closeApproveModal = function () {
        document.getElementById('approveModal').style.display = 'none';
        _refundId = null;
    };

    window.submitApprove = function () {
        const btn = document.getElementById('approveSubmitBtn');
        btn.disabled = true; btn.textContent = 'Processing…';

        const fd = new FormData();
        fd.append('refund_id',  _refundId);
        fd.append('action',     'approve');
        fd.append('csrf_token', window.PS_CSRF_TOKEN ?? '');

        fetch('../../api/admin/process_refund.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeApproveModal();
                    showToast(data.message ?? 'Refund approved.', false);
                    setTimeout(() => location.reload(), 1800);
                } else {
                    showToast(data.message ?? 'Something went wrong.', true);
                }
            })
            .catch(() => showToast('Network error. Please try again.', true))
            .finally(() => { btn.disabled = false; btn.textContent = 'Confirm Approve'; });
    };

    // ── Reject modal ──────────────────────────────────────────────────────────
    window.openRejectModal = function (data) {
        _refundId = data.refund_id;
        document.getElementById('rejectAmount').textContent =
            '₱' + parseFloat(data.refund_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        document.getElementById('rejectGuest').textContent  = data.guest_name;
        document.getElementById('rejectBkRef').textContent  = data.refLabel; // shows INV- or BK-
        document.getElementById('rejectReason').value = '';
        document.getElementById('rejectModal').style.display = 'flex';
    };

    window.closeRejectModal = function () {
        document.getElementById('rejectModal').style.display = 'none';
        _refundId = null;
    };

    window.submitReject = function () {
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) { document.getElementById('rejectReason').focus(); return; }

        const btn = document.getElementById('rejectSubmitBtn');
        btn.disabled = true; btn.textContent = 'Processing…';

        const fd = new FormData();
        fd.append('refund_id',  _refundId);
        fd.append('action',     'reject');
        fd.append('reason',     reason);
        fd.append('csrf_token', window.PS_CSRF_TOKEN ?? '');

        fetch('../../api/admin/process_refund.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeRejectModal();
                    showToast(data.message ?? 'Refund rejected.', false);
                    setTimeout(() => location.reload(), 1800);
                } else {
                    showToast(data.message ?? 'Something went wrong.', true);
                }
            })
            .catch(() => showToast('Network error. Please try again.', true))
            .finally(() => { btn.disabled = false; btn.textContent = 'Confirm Reject'; });
    };

    // ── Init ──────────────────────────────────────────────────────────────────
    renderTable();

})();