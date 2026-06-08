/**
 * invoice_billings.js
 * Features:
 *  - Month picker + search + status filtering (all client-side)
 *  - Client-side pagination with tfoot (Showing X–Y of Z, chevron nav, page pills)
 *  - New invoice modal + form
 *  - View invoice modal
 *  - Send invoice preview modal + email dispatch
 *  - Status dropdown (mark as / delete)
 *  - Live stat card refresh
 *  - Toast notifications
 */

(() => {
    'use strict';

    const API = '../../api/admin/invoice.php';

    /* ─── DOM shortcuts ──────────────────────────────────────────────────── */
    const $ = (id) => document.getElementById(id);
    const $$ = (sel) => [...document.querySelectorAll(sel)];

    /* ─── State ──────────────────────────────────────────────────────────── */
    let rows = $$('#invoiceTableBody tr[data-id]');
    let activeId = null;
    let sendingId = null;

    /* ─── Pagination state ───────────────────────────────────────────────── */
    let invCurrentPage = 1;
    const INV_PER_PAGE = 10;

    /* ─── API helper ─────────────────────────────────────────────────────── */
    async function post(params) {
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                csrf_token: window.PS_CSRF_TOKEN || '',
                ...params
            }),
        });
        return res.json();
    }

    /* ══════════════════════════════════════════════════════════════════════
       PAGINATION
    ══════════════════════════════════════════════════════════════════════ */

    /**
     * Call after applyFilters() has set row visibility.
     * Reads the currently visible rows, paginates them, updates tfoot.
     */
    function paginateInvoices() {
        // Visible = rows the filter says should show (data-filter-visible="1")
        // Never read style.display here — pagination itself sets that
        const visible = rows.filter(r => r.dataset.filterVisible !== '0');

        const total = visible.length;
        const totalPages = Math.max(1, Math.ceil(total / INV_PER_PAGE));
        invCurrentPage = Math.min(invCurrentPage, totalPages);

        const startIdx = (invCurrentPage - 1) * INV_PER_PAGE;
        const endIdx = startIdx + INV_PER_PAGE;

        visible.forEach((r, i) => {
            if (i >= startIdx && i < endIdx) {
                r.style.display = '';
                r.classList.remove('inv-paginated-hidden');
            } else {
                r.classList.add('inv-paginated-hidden');
                r.style.display = 'none';
            }
        });

        renderInvPaginationFooter(total, totalPages, startIdx, endIdx);
    }

    function renderInvPaginationFooter(total, totalPages, startIdx, endIdx) {
        const foot = $('invTableFoot');
        const info = $('invPageInfo');
        const controls = $('invPageControls');
        const prevBtn = $('invPrevBtn');
        const nextBtn = $('invNextBtn');

        if (!foot) return;

        if (total === 0) {
            foot.style.display = 'none';
            return;
        }

        foot.style.display = '';
        const from = startIdx + 1;
        const to = Math.min(endIdx, total);
        if (info) info.innerHTML = `Showing <strong>${from}–${to}</strong> of <strong>${total}</strong> invoice(s)`;

        if (totalPages <= 1) {
            if (controls) controls.style.display = 'none';
            return;
        }

        if (controls) controls.style.display = 'flex';
        if (prevBtn) prevBtn.disabled = invCurrentPage <= 1;
        if (nextBtn) nextBtn.disabled = invCurrentPage >= totalPages;

        renderInvPageNumbers(totalPages);
    }

    function renderInvPageNumbers(totalPages) {
        const wrap = $('invPageNumbers');
        if (!wrap) return;
        wrap.innerHTML = '';

        const cur = invCurrentPage;
        const nums = new Set([1, totalPages, cur, cur - 1, cur + 1].filter(n => n >= 1 && n <= totalPages));
        const sorted = [...nums].sort((a, b) => a - b);

        sorted.forEach((n, idx) => {
            if (idx > 0 && n > sorted[idx - 1] + 1) {
                const el = document.createElement('span');
                el.className = 'inv-pg-ellipsis';
                el.textContent = '…';
                wrap.appendChild(el);
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'inv-pg-num' + (n === cur ? ' active' : '');
            btn.textContent = n;
            btn.onclick = () => { invCurrentPage = n; paginateInvoices(); };
            wrap.appendChild(btn);
        });
    }

    // Called by tfoot chevron buttons (wired via onclick in PHP)
    window.invChangePage = function (dir) {
        invCurrentPage += dir;
        paginateInvoices();
    };

    /* ══════════════════════════════════════════════════════════════════════
       FILTER  (search + status + month)
    ══════════════════════════════════════════════════════════════════════ */
    function applyFilters() {
        const noDataRow = $('noDataRow');
        const q = ($('searchFilter')?.value ?? '').toLowerCase().trim();
        const status = $('statusFilter')?.value ?? '';
        const month = $('invMonthFilter')?.value ?? '';
        let count = 0;

        rows.forEach(row => {
            // Clear pagination state so filter sees all rows cleanly
            row.classList.remove('inv-paginated-hidden');
            row.style.display = '';

            const show =
                (!q || (row.dataset.search ?? '').includes(q)) &&
                (!status || row.dataset.status === status) &&
                (!month || row.dataset.month === month);

            // Mark filter visibility on a data attribute — pagination reads this
            row.dataset.filterVisible = show ? '1' : '0';
            row.style.display = show ? '' : 'none';
            if (show) count++;
        });

        const empty = $('emptyState');

        if (rows.length === 0) {
            // Truly no invoices at all — show "No invoices yet"
            if (noDataRow) noDataRow.style.display = '';
            if (empty) empty.style.display = 'none';
        } else if (count === 0) {
            // Invoices exist but hidden by filters — show "No match"
            if (noDataRow) noDataRow.style.display = 'none';
            if (empty) empty.style.display = 'block';
        } else {
            if (noDataRow) noDataRow.style.display = 'none';
            if (empty) empty.style.display = 'none';
        }

        const foot = $('invTableFoot');
        if (foot) foot.style.display = count === 0 ? 'none' : '';

        invCurrentPage = 1;
        paginateInvoices();
    }

    $('searchFilter')?.addEventListener('input', applyFilters);
    $('statusFilter')?.addEventListener('change', applyFilters);
    $('invMonthFilter')?.addEventListener('change', applyFilters);

    /* ── Custom status dropdown ──────────────────────────────────────────── */
    window.toggleStatusDropdown = function () {
        const menu = $('statusMenu');
        const chevron = $('statusChevron');
        const wrap = $('statusDropdownWrap');
        if (!menu) return;
        const isOpen = menu.style.display !== 'none';
        menu.style.display = isOpen ? 'none' : 'block';
        if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
        if (wrap) wrap.classList.toggle('open', !isOpen);
    };

    window.selectStatusOpt = function (btn) {
        const val = btn.dataset.value;
        const label = btn.textContent.trim();

        // Update hidden input + trigger change
        const hidden = $('statusFilter');
        if (hidden) { hidden.value = val; hidden.dispatchEvent(new Event('change')); }

        // Update trigger label
        const triggerLabel = $('statusTriggerLabel');
        if (triggerLabel) triggerLabel.textContent = label;

        // Update active state on options
        document.querySelectorAll('.inv-status-opt').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Close
        const menu = $('statusMenu');
        const chevron = $('statusChevron');
        const wrap = $('statusDropdownWrap');
        if (menu) menu.style.display = 'none';
        if (chevron) chevron.style.transform = '';
        if (wrap) wrap.classList.remove('open');
    };

    // Close when clicking outside
    document.addEventListener('click', function (e) {
        const wrap = $('statusDropdownWrap');
        if (wrap && !wrap.contains(e.target)) {
            const menu = $('statusMenu');
            const chevron = $('statusChevron');
            if (menu) menu.style.display = 'none';
            if (chevron) chevron.style.transform = '';
            wrap.classList.remove('open');
        }
    });

    // Initial run
    applyFilters();

    /* ══════════════════════════════════════════════════════════════════════
       LIVE STATS
    ══════════════════════════════════════════════════════════════════════ */
    async function refreshStats() {
        try {
            const data = await post({ action: 'get_stats' });
            if (!data.success) return;
            animateCount('stat-total', data.stats.total);
            animateCount('stat-paid', data.stats.paid);
            animateCount('stat-pending', data.stats.pending);
            animateCount('stat-overdue', data.stats.overdue);
        } catch { /* silent */ }
    }

    function animateCount(id, target) {
        const el = $(id);
        if (!el) return;
        const start = parseInt(el.textContent) || 0;
        const diff = target - start;
        const startTs = performance.now();
        el.classList.add('counting');
        function step(ts) {
            const progress = Math.min((ts - startTs) / 400, 1);
            const ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(start + diff * ease);
            if (progress < 1) requestAnimationFrame(step);
            else { el.textContent = target; el.classList.remove('counting'); }
        }
        requestAnimationFrame(step);
    }

    /* ══════════════════════════════════════════════════════════════════════
       NEW INVOICE MODAL
    ══════════════════════════════════════════════════════════════════════ */
    const newModal = $('newInvoiceModal');
    const newForm = $('newInvoiceForm');

    function openNewModal() { newModal?.classList.add('open'); }
    function closeNewModal() {
        newModal?.classList.remove('open');
        newForm?.reset();
        newForm?.querySelectorAll('.inv-field-error').forEach(el => el.remove());
        newForm?.querySelectorAll('.inv-input-error').forEach(el => el.classList.remove('inv-input-error'));
    }

    $('openNewInvoiceBtn')?.addEventListener('click', openNewModal);
    $('closeNewInvoice')?.addEventListener('click', closeNewModal);
    $('cancelNewInvoice')?.addEventListener('click', closeNewModal);
    newModal?.addEventListener('click', e => { if (e.target === newModal) closeNewModal(); });

    $('f_tenant')?.addEventListener('change', function () {
        const unit = this.options[this.selectedIndex]?.dataset?.unit ?? '';
        const el = $('f_unit');
        if (el) el.value = unit;
    });

    newForm?.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Clear previous errors
    newForm.querySelectorAll('.inv-field-error').forEach(el => el.remove());
    newForm.querySelectorAll('.inv-input-error').forEach(el => el.classList.remove('inv-input-error'));

    // Validate
    const fields = [
        { name: 'tenant_id',   label: 'Tenant' },
        { name: 'unit',        label: 'Unit' },
        { name: 'issued_date', label: 'Issued Date' },
        { name: 'due_date',    label: 'Due Date' },
        { name: 'items',       label: 'Items / Description' },
        { name: 'total',       label: 'Total Amount' },
    ];

    let hasError = false;
    fields.forEach(({ name, label }) => {
        const el = newForm.elements[name];
        if (!el || !el.value.trim()) {
            hasError = true;
            const wrap = el?.closest('.inv-form-group');
            if (wrap) {
                el.classList.add('inv-input-error');
                const msg = document.createElement('span');
                msg.className = 'inv-field-error';
                msg.textContent = `${label} is required.`;
                wrap.appendChild(msg);
            }
        }
    });

    if (hasError) return;

    const btn = $('submitNewInvoice');
    setBtnState(btn, true, 'Creating…');

    try {
        const data = await post({
            action: 'create',
            tenant_id: newForm.tenant_id.value,
            unit: newForm.unit.value,
            issued_date: newForm.issued_date.value,
            due_date: newForm.due_date.value,
            items: newForm.items.value,
            total: newForm.total.value,
            status: newForm.status.value,
        });

        if (data.success) {
            toast(`Invoice ${data.invoice_no} created!`, 'success');
            closeNewModal();
            await refreshStats();
            try {
                const invData = await post({ action: 'get_invoice', id: data.invoice_id || data.id });
                if (invData.success && invData.invoice) {
                    prependInvoiceRow(invData.invoice);
                } else {
                    setTimeout(() => location.reload(), 900);
                }
            } catch { setTimeout(() => location.reload(), 900); }
        } else {
            toast(data.message || 'Failed to create invoice.', 'error');
        }
    } catch {
        toast('Network error. Please try again.', 'error');
    } finally {
        setBtnState(btn, false, 'Create Invoice');
    }
});

    /* ══════════════════════════════════════════════════════════════════════
       VIEW INVOICE MODAL
    ══════════════════════════════════════════════════════════════════════ */
    const viewModal = $('viewInvoiceModal');

    function openViewModal(row) {
        let inv;
        try { inv = JSON.parse(row.dataset.inv); } catch { return; }

        // Branded header
        const headNo = document.getElementById('viewInvTitle');
        if (headNo) headNo.textContent = inv.invoice_no || `#${inv.id}`;

        const badgeHeader = $('vi_badge_header');
        if (badgeHeader) { badgeHeader.textContent = inv.status; badgeHeader.className = 'inv-badge inv-badge--header ' + statusClass(inv.status); }

        // Avatar initials
        const avatar = $('vi_avatar_initials');
        if (avatar) {
            const words = (inv.tenant || '?').trim().split(/\s+/);
            avatar.textContent = words.length >= 2
                ? (words[0][0] + words[words.length - 1][0]).toUpperCase()
                : (inv.tenant || '?')[0].toUpperCase();
        }

        setText('vi_tenant', inv.tenant || '—');
        setText('vi_email', inv.email || '(no email on file)');
        setText('vi_unit', inv.unit || '—');
        setText('vi_items', inv.items || '—');
        setText('vi_total', '₱ ' + parseFloat(inv.total).toLocaleString('en-PH', { minimumFractionDigits: 2 }));
        setText('vi_issued', inv.issued || '—');
        setText('vi_due', inv.due || '—');

        const sendBtn = $('vi_sendBtn');
        if (sendBtn) sendBtn.onclick = () => { closeViewModal(); openSendModal(inv.id); };

        viewModal?.classList.add('open');
    }

    function closeViewModal() { viewModal?.classList.remove('open'); }

    $('closeViewInvoice')?.addEventListener('click', closeViewModal);
    $('closeViewInvoice2')?.addEventListener('click', closeViewModal);
    viewModal?.addEventListener('click', e => { if (e.target === viewModal) closeViewModal(); });

    function bindViewBtns() {
        $$('.view-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');
                if (row) openViewModal(row);
            });
        });
    }
    bindViewBtns();

    /* ══════════════════════════════════════════════════════════════════════
       SEND MODAL
    ══════════════════════════════════════════════════════════════════════ */
    const sendModal = $('sendModal');

    async function openSendModal(id) {
        sendingId = id;
        show('sendModalLoading');
        hide('sendModalContent');
        hideSendError();

        const confirmBtn = $('confirmSendBtn');
        if (confirmBtn) {
            confirmBtn.style.display = 'none';
            confirmBtn.disabled = false;
            confirmBtn.lastChild.textContent = ' Send Invoice';
        }

        sendModal?.classList.add('open');

        try {
            const data = await post({ action: 'get_invoice', id });
            if (!data.success || !data.invoice) { showSendError(data.message || 'Could not load invoice.'); return; }

            const inv = data.invoice;
            setText('si_invoice_no', inv.invoice_no || `#${inv.id}`);
            setText('si_tenant', inv.full_name || '—');
            setText('si_email', inv.email || '(no email on file)');
            setText('si_unit', inv.unit || '—');
            setText('si_items', inv.items || '—');
            setText('si_total', '₱ ' + parseFloat(inv.total).toLocaleString('en-PH', { minimumFractionDigits: 2 }));
            setText('si_due', inv.due_label || '—');

            hide('sendModalLoading');
            show('sendModalContent');
            if (confirmBtn) confirmBtn.style.display = 'inline-flex';
            if (!inv.email) showSendError('⚠ This tenant has no email address on file. Sending will fail.');
        } catch {
            showSendError('Network error. Please try again.');
        }
    }

    function closeSendModal() { sendModal?.classList.remove('open'); sendingId = null; }

    $('closeSendModal')?.addEventListener('click', closeSendModal);
    $('cancelSendModal')?.addEventListener('click', closeSendModal);
    sendModal?.addEventListener('click', e => { if (e.target === sendModal) closeSendModal(); });

    $('confirmSendBtn')?.addEventListener('click', async () => {
        if (!sendingId) return;
        const btn = $('confirmSendBtn');
        setBtnState(btn, true, 'Sending…');
        hideSendError();
        try {
            const data = await post({ action: 'send', id: sendingId });
            if (data.success) {
                const justSentId = sendingId;
                closeSendModal();
                // Update the table row badge in-place immediately
                updateRowBadge(justSentId, 'Sent');
                // Update the view modal badge if it is currently open for this invoice
                const vi_badge = $('vi_badge_header');
                if (vi_badge && viewModal?.classList.contains('open')) {
                    const openRow = document.querySelector(`tr[data-id="${justSentId}"]`);
                    if (openRow) {
                        vi_badge.textContent = 'Sent';
                        vi_badge.className = 'inv-badge inv-badge--header ' + statusClass('Sent');
                    }
                }
                // Re-apply filters so row visibility is consistent with active filter,
                // but only after the badge dataset has already been updated above
                applyFilters();
                await refreshStats();
                toast('Invoice sent! Email with payment links delivered to tenant.', 'success');
                // Start polling so the badge auto-updates once tenant pays
                if (data.invoice_id) {
                    startInvoicePaymentPoll(justSentId, data.invoice_id);
                }
            } else {
                showSendError(data.message || 'Failed to send invoice.');
                setBtnState(btn, false, 'Retry Send');
            }
        } catch {
            showSendError('Network error. Please try again.');
            setBtnState(btn, false, 'Retry Send');
        }
    });

    /* Poll api/admin/invoice.php?action=check_paid until invoice flips to Paid.
       Uses the existing admin endpoint so no session mismatch. */
    function startInvoicePaymentPoll(rowId, invoiceId) {
        if (!invoiceId) return;
        // Stop any existing poll for the same invoice
        if (window._invPollTimers && window._invPollTimers[invoiceId]) {
            clearInterval(window._invPollTimers[invoiceId]);
        }
        window._invPollTimers = window._invPollTimers || {};
        let attempts = 0;
        const MAX = 72; // 6 minutes at 5s
        window._invPollTimers[invoiceId] = setInterval(async () => {
            attempts++;
            if (attempts > MAX) {
                clearInterval(window._invPollTimers[invoiceId]);
                return;
            }
            try {
                const fd = new FormData();
                fd.append('action', 'check_paid');
                fd.append('id', invoiceId);
                fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
                const res = await fetch('../../api/admin/invoice.php', {
                    method: 'POST', credentials: 'same-origin', body: fd
                });
                const d = await res.json();
                if (d.is_paid) {
                    clearInterval(window._invPollTimers[invoiceId]);
                    updateRowBadge(rowId, 'Paid');
                    applyFilters();
                    await refreshStats();
                    toast('Payment confirmed! Invoice marked as Paid.', 'success');
                }
            } catch { /* network hiccup, keep polling */ }
        }, 5000);
    }

    function bindSendBtns() {
        $$('.send-btn').forEach(btn => {
            btn.addEventListener('click', () => openSendModal(btn.dataset.id));
        });
    }
    bindSendBtns();

    /* ══════════════════════════════════════════════════════════════════════
       STATUS DROPDOWN
    ══════════════════════════════════════════════════════════════════════ */
    const dropdown = $('statusDropdown');

    function bindMoreBtns() {
        $$('.more-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                activeId = btn.dataset.id;
                const rect = btn.getBoundingClientRect();
                dropdown.style.top = `${rect.bottom + window.scrollY + 6}px`;
                dropdown.style.left = `${rect.left + window.scrollX - 150}px`;
                dropdown.style.display = 'block';
            });
        });
    }
    bindMoreBtns();

    document.addEventListener('click', () => { if (dropdown) dropdown.style.display = 'none'; });

    $$('#statusDropdown [data-status]').forEach(btn => {
        btn.addEventListener('click', () => updateStatus(btn.dataset.status));
    });

    async function updateStatus(status) {
        if (!activeId) return;
        dropdown.style.display = 'none';
        try {
            const data = await post({ action: 'update_status', id: activeId, status });
            if (data.success) {
                updateRowBadge(activeId, status);
                applyFilters();
                await refreshStats();
                toast(`Marked as ${status}.`, 'success');
            } else {
                toast(data.message || 'Failed to update status.', 'error');
            }
        } catch { toast('Network error. Please try again.', 'error'); }
    }

    const deleteInvModal = $('deleteInvoiceModal');
    const deleteInvRef = $('deleteInvoiceRef');
    const confirmDelBtn = $('confirmDeleteInvoice');

    function openDeleteInvModal() {
        if (!activeId) return;
        dropdown.style.display = 'none';
        const row = document.querySelector(`tr[data-id="${activeId}"]`);
        const inv = row ? (() => { try { return JSON.parse(row.dataset.inv); } catch { return {}; } })() : {};
        if (deleteInvRef) deleteInvRef.textContent = `"${inv.invoice_no || '#' + activeId}"`;
        deleteInvModal?.classList.add('open');
    }

    function closeDeleteInvModal() {
        deleteInvModal?.classList.remove('open');
    }

    $('deleteInvoiceBtn')?.addEventListener('click', openDeleteInvModal);
    $('cancelDeleteInvoice')?.addEventListener('click', closeDeleteInvModal);
    deleteInvModal?.addEventListener('click', e => { if (e.target === deleteInvModal) closeDeleteInvModal(); });

    confirmDelBtn?.addEventListener('click', async () => {
        if (!activeId) return;
        confirmDelBtn.disabled = true;
        confirmDelBtn.textContent = 'Deleting…';
        try {
            const data = await post({ action: 'delete', id: activeId });
            if (data.success) {
                const row = document.querySelector(`tr[data-id="${activeId}"]`);
                if (row) {
                    row.style.transition = 'opacity .2s, transform .2s';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(8px)';
                    setTimeout(() => {
                        row.remove();
                        rows = $$('#invoiceTableBody tr[data-id]');
                        // If no invoices left, re-inject the "no invoices yet" row
                        if (rows.length === 0) {
                            const tbody = $('invoiceTableBody');
                            if (tbody && !$('noDataRow')) {
                                const emptyTr = document.createElement('tr');
                                emptyTr.id = 'noDataRow';
                                emptyTr.innerHTML = `<td colspan="9" style="text-align:center;padding:48px;color:#aab;">
                No invoices yet. Create your first one!
            </td>`;
                                tbody.appendChild(emptyTr);
                            }
                        }
                        applyFilters();
                    }, 220);
                }
                await refreshStats();
                closeDeleteInvModal();
                toast('Invoice deleted.', 'info');
            } else {
                toast(data.message || 'Failed to delete.', 'error');
            }
        } catch { toast('Network error. Please try again.', 'error'); }
        finally {
            confirmDelBtn.disabled = false;
            confirmDelBtn.textContent = 'Delete';
        }
    });

    /* ══════════════════════════════════════════════════════════════════════
       HELPERS
    ══════════════════════════════════════════════════════════════════════ */
    function updateRowBadge(id, status) {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (!row) return;
        row.dataset.status = status;
        const badge = row.querySelector('.inv-badge');
        if (badge) { badge.textContent = status; badge.className = 'inv-badge ' + statusClass(status); }
        try {
            const inv = JSON.parse(row.dataset.inv);
            inv.status = status;
            row.dataset.inv = JSON.stringify(inv);
        } catch { /* noop */ }
    }

    function statusClass(s) {
        return { Paid: 'success', Pending: 'warning', Overdue: 'danger', Sent: 'info' }[s] ?? 'warning';
    }

    function prependInvoiceRow(inv) {
        const tbody = $('invoiceTableBody');
        if (!tbody) { location.reload(); return; }

        const sc = statusClass(inv.status || 'Pending');
        const tot = parseFloat(inv.total || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        const unit = inv.unit || '—';
        const items = inv.items || '—';
        const monthVal = (inv.issued_date || '').substring(0, 7) || (inv.issued || '').substring(0, 7) || '';
        const search = ((inv.invoice_no || '') + ' ' + (inv.full_name || inv.tenant || '') + ' ' + unit).toLowerCase();

        const emptyRow = tbody.querySelector('td[colspan]')?.closest('tr');
        if (emptyRow) emptyRow.remove();

        const tr = document.createElement('tr');
        tr.dataset.id = inv.id;
        tr.dataset.status = inv.status || 'Pending';
        tr.dataset.month = monthVal;
        tr.dataset.search = search;
        tr.dataset.inv = JSON.stringify({
            id: inv.id, invoice_no: inv.invoice_no,
            tenant: inv.full_name || inv.tenant || '—', email: inv.email || '',
            unit, items, total: inv.total,
            issued: inv.issued_label || inv.issued || '—',
            due: inv.due_label || inv.due || '—',
            status: inv.status || 'Pending',
        });
        tr.innerHTML = `
          <td class="td-no">${inv.invoice_no || '—'}</td>
          <td class="td-name">${inv.full_name || inv.tenant || '—'}</td>
          <td class="td-soft">${unit}</td>
          <td class="td-soft">${inv.issued_label || inv.issued || '—'}</td>
          <td class="td-soft">${inv.due_label || inv.due || '—'}</td>
          <td class="td-items" title="${items}">${items}</td>
          <td class="td-total">₱ ${tot}</td>
          <td><span class="inv-badge ${sc}">${inv.status || 'Pending'}</span></td>
          <td><div class="inv-actions">
            <button class="inv-btn secondary view-btn" data-id="${inv.id}">View</button>
            <button class="inv-btn primary send-btn"   data-id="${inv.id}">Send</button>
            <button class="inv-btn ghost more-btn"     data-id="${inv.id}" title="More">⋯</button>
          </div></td>`;

        tbody.prepend(tr);
        rows = $$('#invoiceTableBody tr[data-id]');
        bindViewBtns();
        bindSendBtns();
        bindMoreBtns();

        // AFTER
        // AFTER
        const monthInput = $('invMonthFilter');
        const monthLabel = $('invMonthPickerLabel');
        if (monthInput && monthVal) {
            monthInput.value = monthVal;
            if (monthLabel) {
                const [yr, mn] = monthVal.split('-');
                const names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                monthLabel.textContent = names[parseInt(mn) - 1] + ' ' + yr;
            }
            window._invSyncPickerState && window._invSyncPickerState(monthVal);
            // dispatch AFTER updating value so applyFilters reads the new month
            monthInput.dispatchEvent(new Event('change'));
        } else {
            applyFilters();
        }

        tr.style.background = '#f0fdf4';
        setTimeout(() => { tr.style.transition = 'background 1.2s'; tr.style.background = ''; }, 100);
    }

    function setText(id, text) { const el = $(id); if (el) el.textContent = text; }
    function show(id) { const el = $(id); if (el) el.style.display = ''; }
    function hide(id) { const el = $(id); if (el) el.style.display = 'none'; }

    function setBtnState(btn, disabled, label) {
        if (!btn) return;
        btn.disabled = disabled;
        const last = btn.lastChild;
        if (last?.nodeType === Node.TEXT_NODE) last.textContent = ' ' + label;
        else btn.textContent = label;
    }

    function showSendError(msg) {
        hide('sendModalLoading');
        const el = $('sendModalError');
        if (el) { el.textContent = msg; el.style.display = 'block'; }
    }
    function hideSendError() { const el = $('sendModalError'); if (el) el.style.display = 'none'; }

    /* ══════════════════════════════════════════════════════════════════════
       TOAST
    ══════════════════════════════════════════════════════════════════════ */
    function toast(message, type = 'success') {
        document.querySelectorAll('.inv-toast').forEach(t => t.remove());
        const icons = {
            success: '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            error: '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            info: '<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        };
        const el = document.createElement('div');
        el.className = `inv-toast ${type}`;
        el.innerHTML = (icons[type] ?? '') + `<span>${message}</span>`;
        document.body.appendChild(el);
        requestAnimationFrame(() => { el.style.opacity = '1'; el.style.transform = 'translateY(0)'; });
        setTimeout(() => {
            el.style.opacity = '0'; el.style.transform = 'translateY(10px)';
            setTimeout(() => el.remove(), 300);
        }, 3500);
    }

})();