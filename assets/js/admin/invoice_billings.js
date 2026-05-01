/**
 * invoices_billings.js
 * Features:
 *  - Search & status filtering
 *  - New invoice modal + form
 *  - View invoice modal (data from row's data-inv attribute)
 *  - Send invoice preview modal + email dispatch
 *  - Status dropdown (mark as / delete)
 *  - Live stat card refresh after every create / delete / status change
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
    let activeId = null;   // for status dropdown / delete
    let sendingId = null;   // for send modal

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
       FILTER
    ══════════════════════════════════════════════════════════════════════ */
    function applyFilters() {
        const q = ($('searchFilter')?.value ?? '').toLowerCase().trim();
        const status = $('statusFilter')?.value ?? '';
        let count = 0;

        rows.forEach(row => {
            const show =
                (!q || (row.dataset.search ?? '').includes(q)) &&
                (!status || row.dataset.status === status);
            row.style.display = show ? '' : 'none';
            if (show) count++;
        });

        const empty = $('emptyState');
        if (empty) empty.style.display = count === 0 && rows.length > 0 ? 'block' : 'none';
    }

    $('searchFilter')?.addEventListener('input', applyFilters);
    $('statusFilter')?.addEventListener('change', applyFilters);
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
        } catch { /* silent fail — stats are non-critical */ }
    }

    function animateCount(id, target) {
        const el = $(id);
        if (!el) return;

        const start = parseInt(el.textContent) || 0;
        const diff = target - start;
        const duration = 400;
        const startTs = performance.now();

        el.classList.add('counting');

        function step(ts) {
            const progress = Math.min((ts - startTs) / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3); // ease-out cubic
            el.textContent = Math.round(start + diff * ease);
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target;
                el.classList.remove('counting');
            }
        }
        requestAnimationFrame(step);
    }

    /* ══════════════════════════════════════════════════════════════════════
       NEW INVOICE MODAL
    ══════════════════════════════════════════════════════════════════════ */
    const newModal = $('newInvoiceModal');
    const newForm = $('newInvoiceForm');

    function openNewModal() { newModal?.classList.add('open'); }
    function closeNewModal() { newModal?.classList.remove('open'); newForm?.reset(); }

    $('openNewInvoiceBtn')?.addEventListener('click', openNewModal);
    $('closeNewInvoice')?.addEventListener('click', closeNewModal);
    $('cancelNewInvoice')?.addEventListener('click', closeNewModal);
    newModal?.addEventListener('click', e => { if (e.target === newModal) closeNewModal(); });

    /* Auto-fill unit from selected tenant */
    $('f_tenant')?.addEventListener('change', function () {
        const unit = this.options[this.selectedIndex]?.dataset?.unit ?? '';
        const el = $('f_unit');
        if (el) el.value = unit;
    });

    /* Submit */
    newForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
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
                // Fetch the new invoice data and prepend row without reload
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

        setText('vi_invoice_no', inv.invoice_no || `#${inv.id}`);
        setText('vi_tenant', inv.tenant || '—');
        setText('vi_email', inv.email || '(no email on file)');
        setText('vi_unit', inv.unit || '—');
        setText('vi_items', inv.items || '—');
        setText('vi_total', '₱ ' + parseFloat(inv.total).toLocaleString('en-PH', { minimumFractionDigits: 2 }));
        setText('vi_issued', inv.issued || '—');
        setText('vi_due', inv.due || '—');

        // Badge
        const badge = $('vi_badge');
        if (badge) {
            badge.textContent = inv.status;
            badge.className = 'inv-badge ' + statusClass(inv.status);
        }

        // Wire the Send button inside the view modal
        const sendBtn = $('vi_sendBtn');
        if (sendBtn) {
            sendBtn.onclick = () => {
                closeViewModal();
                openSendModal(inv.id);
            };
        }

        viewModal?.classList.add('open');
    }

    function closeViewModal() { viewModal?.classList.remove('open'); }

    $('closeViewInvoice')?.addEventListener('click', closeViewModal);
    $('closeViewInvoice2')?.addEventListener('click', closeViewModal);
    viewModal?.addEventListener('click', e => { if (e.target === viewModal) closeViewModal(); });

    /* Attach view buttons */
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
        if (confirmBtn) { confirmBtn.style.display = 'none'; confirmBtn.disabled = false; confirmBtn.childNodes[confirmBtn.childNodes.length - 1].textContent = ' Send Invoice'; }

        sendModal?.classList.add('open');

        try {
            const data = await post({ action: 'get_invoice', id });

            if (!data.success || !data.invoice) {
                showSendError(data.message || 'Could not load invoice.');
                return;
            }

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

    function closeSendModal() {
        sendModal?.classList.remove('open');
        sendingId = null;
    }

    $('closeSendModal')?.addEventListener('click', closeSendModal);
    $('cancelSendModal')?.addEventListener('click', closeSendModal);
    sendModal?.addEventListener('click', e => { if (e.target === sendModal) closeSendModal(); });

    /* Confirm send */
    $('confirmSendBtn')?.addEventListener('click', async () => {
        if (!sendingId) return;
        const btn = $('confirmSendBtn');
        setBtnState(btn, true, 'Sending…');
        hideSendError();

        try {
            const data = await post({ action: 'send', id: sendingId });

            if (data.success) {
                closeSendModal();
                updateRowBadge(sendingId, 'Sent');
                applyFilters();
                await refreshStats();
                toast('Invoice sent successfully!', 'success');
            } else {
                showSendError(data.message || 'Failed to send invoice.');
                setBtnState(btn, false, 'Retry Send');
            }
        } catch {
            showSendError('Network error. Please try again.');
            setBtnState(btn, false, 'Retry Send');
        }
    });

    /* Attach send buttons in table */
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

    // Status change buttons inside dropdown
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
        } catch {
            toast('Network error. Please try again.', 'error');
        }
    }

    /* Delete */
    $('deleteInvoiceBtn')?.addEventListener('click', async () => {
        if (!activeId) return;
        dropdown.style.display = 'none';
        if (!confirm('Delete this invoice? This cannot be undone.')) return;

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
                        applyFilters();
                    }, 220);
                }
                await refreshStats();
                toast('Invoice deleted.', 'info');
            } else {
                toast(data.message || 'Failed to delete.', 'error');
            }
        } catch {
            toast('Network error. Please try again.', 'error');
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
        if (badge) {
            badge.textContent = status;
            badge.className = 'inv-badge ' + statusClass(status);
        }

        // Keep data-inv in sync so the view modal shows the correct status
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
        const invObj = JSON.stringify(inv).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
        const search = ((inv.invoice_no || '') + ' ' + (inv.full_name || inv.tenant || '') + ' ' + unit).toLowerCase();

        const tr = document.createElement('tr');
        tr.dataset.id = inv.id;
        tr.dataset.status = inv.status || 'Pending';
        tr.dataset.search = search;
        tr.dataset.inv = JSON.stringify({
            id: inv.id, invoice_no: inv.invoice_no,
            tenant: inv.full_name || inv.tenant || '—', email: inv.email || '',
            unit, items, total: inv.total,
            issued: inv.issued_label || inv.issued || '—',
            due: inv.due_label || inv.due || '—', status: inv.status || 'Pending',
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
            <button class="inv-btn primary send-btn" data-id="${inv.id}">Send</button>
            <button class="inv-btn ghost more-btn" data-id="${inv.id}" title="More">⋯</button>
          </div></td>`;

        // Remove empty-state row if present
        const emptyRow = tbody.querySelector('td[colspan]')?.closest('tr');
        if (emptyRow) emptyRow.remove();

        tbody.prepend(tr);
        rows = $$('#invoiceTableBody tr[data-id]');
        bindViewBtns();
        bindSendBtns();
        bindMoreBtns();
        applyFilters();

        // Flash highlight
        tr.style.background = '#f0fdf4';
        setTimeout(() => { tr.style.transition = 'background 1.2s'; tr.style.background = ''; }, 100);
    }

    function setText(id, text) {
        const el = $(id);
        if (el) el.textContent = text;
    }

    function show(id) {
        const el = $(id);
        if (el) el.style.display = '';
    }

    function hide(id) {
        const el = $(id);
        if (el) el.style.display = 'none';
    }

    function setBtnState(btn, disabled, label) {
        if (!btn) return;
        btn.disabled = disabled;
        // Preserve SVG child — only update text node
        const last = btn.lastChild;
        if (last?.nodeType === Node.TEXT_NODE) {
            last.textContent = ' ' + label;
        } else {
            btn.textContent = label;
        }
    }

    function showSendError(msg) {
        hide('sendModalLoading');
        const el = $('sendModalError');
        if (el) { el.textContent = msg; el.style.display = 'block'; }
    }

    function hideSendError() {
        const el = $('sendModalError');
        if (el) el.style.display = 'none';
    }

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

        requestAnimationFrame(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        });

        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(10px)';
            setTimeout(() => el.remove(), 300);
        }, 3500);
    }

})();