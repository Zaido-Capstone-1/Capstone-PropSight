/* ─── normaliseBooking ───────────────────────────────────────────────────────
 * The API returns exact SQL aliases. Map them to one consistent shape.
 * ─────────────────────────────────────────────────────────────────────────── */

function confirmAction(message, options = {}) {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const title = document.getElementById('confirmModalTitle');
        const messageEl = document.getElementById('confirmModalMessage');
        const cancelBtn = document.getElementById('confirmModalCancel');
        const confirmBtn = document.getElementById('confirmModalConfirm');

        // Set content
        title.textContent = options.title || 'Confirm Action';
        messageEl.textContent = message;

        // Set button styles
        confirmBtn.className = 'confirm-modal-btn confirm-btn-confirm';
        if (options.danger) {
            confirmBtn.classList.add('danger');
        }
        confirmBtn.textContent = options.confirmText || 'Confirm';
        cancelBtn.textContent = options.cancelText || 'No, Go Back';

        // Show modal
        modal.classList.add('active');
        confirmBtn.focus();

        // Handle confirm
        const handleConfirm = () => {
            cleanup();
            resolve(true);
        };

        // Handle cancel
        const handleCancel = () => {
            cleanup();
            resolve(false);
        };

        // Handle escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                cleanup();
                resolve(false);
            }
        };

        // Handle overlay click
        const handleOverlayClick = (e) => {
            if (e.target === modal) {
                cleanup();
                resolve(false);
            }
        };

        // Cleanup function
        const cleanup = () => {
            modal.classList.remove('active');
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            document.removeEventListener('keydown', handleEscape);
            modal.removeEventListener('click', handleOverlayClick);
        };

        // Add event listeners
        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
        document.addEventListener('keydown', handleEscape);
        modal.addEventListener('click', handleOverlayClick);
    });
}

function normaliseBooking(b) {
    const unitLabel = b.unit_name ||
        ((b.property_name || '') + (b.unit_number ? ' — ' + b.unit_number : ''));
    const id = parseInt(b.booking_id, 10);
    if (!id) return null;   // skip malformed records
    return {
        booking_id: id,
        user_name: b.user_name || '',
        user_email: b.user_email || '',
        user_photo: b.user_photo || '',   // ← ADD THIS
        unit_name: unitLabel,
        unit_number: b.unit_number || '',
        property_name: b.property_name || '',
        checkin_date: b.checkin_date || '',
        checkout_date: b.checkout_date || '',
        nights: Number(b.nights) || 0,
        total_amount: Number(b.total_amount) || 0,
        status: b.status || 'pending',
    };
}

/* ─── Page-level state ───────────────────────────────────────────────────── */
const currentStatus = window.__PS_RESERVATIONS__.currentStatus;
const currentSearch = window.__PS_RESERVATIONS__.currentSearch;

let knownIds = new Set();   // booking IDs already known to the page
const highlightedOnce = new Set(); // IDs that have already been green-flashed
let allRows = [];           // flat in-memory list (source of truth)
const PER_PAGE = 10;
let currentPage = 1;

/* ─── Badge maps ─────────────────────────────────────────────────────────── */
const BADGE_CLS = {
    pending: 'res-badge-pending',
    confirmed: 'res-badge-success',
    active: 'res-badge-success',
    completed: 'res-badge-info',
    cancelled: 'res-badge-danger',
};
const BADGE_LBL = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    active: 'Active',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

/* ─── Utilities ──────────────────────────────────────────────────────────── */
function escHtml(s) {
    if (!s) return '';
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ─── Action buttons HTML ────────────────────────────────────────────────── */
function _actionButtons(bookingId, status) {
    const chk = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
    const x = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
    const rcpt = `<a href="../../api/user/booking_receipt.php?booking_id=${bookingId}" target="_blank" class="action-btn btn-receipt" style="text-decoration:none;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:11px;height:11px;flex-shrink:0;">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
        </svg>
        Receipt
    </a>`;

    if (status === 'pending') {
        return `<button class="action-btn btn-confirm" onclick="updateStatus(${bookingId},'confirmed',this)">${chk}Confirm</button>
                <button class="action-btn btn-cancel"  onclick="updateStatus(${bookingId},'cancelled',this)">${x}Cancel</button>`;
    }
    if (status === 'confirmed') {
        return `<button class="action-btn btn-complete" onclick="updateStatus(${bookingId},'completed',this)">${chk}Complete</button>
                <button class="action-btn btn-cancel"   onclick="updateStatus(${bookingId},'cancelled',this)">${x}Cancel</button>${rcpt}`;
    }
    if (status === 'completed') return rcpt;
    return `<span style="font-size:12px;color:#cbd5e1;">—</span>`;
}

async function updateStatus(bookingId, newStatus, btn) {
    const verbs = {
        confirmed: {
            action: 'confirm',
            danger: false,
            confirmText: 'Yes, Confirm',
            cancelText: 'Close'
        },
        cancelled: {
            action: 'cancel',
            danger: true,
            confirmText: 'Yes, Cancel Booking',
            cancelText: 'Close'
        },
        completed: {
            action: 'mark as completed',
            danger: false,
            confirmText: 'Yes, Complete',
            cancelText: 'Close'
        }
    };

    const verb = verbs[newStatus] || { action: newStatus, danger: false };
    const bookingLabel = `#BK-${String(bookingId).padStart(4, '0')}`;

    const confirmed = await confirmAction(
        `Are you sure you want to ${verb.action} booking ${bookingLabel}?`,
        {
            title: verb.action.charAt(0).toUpperCase() + verb.action.slice(1) + ' Booking',
            confirmText: verb.confirmText || 'Confirm',
            cancelText: verb.cancelText || 'Go Back',
            danger: verb.danger
        }
    );

    if (!confirmed) return;

    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }
    // if (typeof showToast === 'function') showToast('Updating…', 'info');

    fetch('../../api/reservations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'update_status', booking_id: bookingId, status: newStatus,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content ?? ''
        }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message || 'Updated!', 'success');
                _patchRow(bookingId, newStatus);
                _refreshStatsOnly();
            } else {
                if (typeof showToast === 'function') showToast(data.message || 'Could not update booking.', 'error');
                if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = orig; }
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Server unreachable. Please try again.', 'error');
            if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = orig; }
        });
}

/* ─── _patchRow — update one row in-place, no re-render ─────────────────── */
function _patchRow(bookingId, newStatus) {
    const id = String(bookingId);
    const row = document.querySelector(`#reservationsTbody tr[data-id="${id}"]`);
    if (!row) return;
    if (row.dataset.status === newStatus) return;

    row.dataset.status = newStatus;

    const badge = row.querySelector('.res-badge');
    if (badge) {
        badge.textContent = BADGE_LBL[newStatus] || newStatus;
        badge.className = 'res-badge ' + (BADGE_CLS[newStatus] || 'res-badge-pending');
    }

    const actionDiv = row.querySelector('td:last-child div');
    if (actionDiv) actionDiv.innerHTML = _actionButtons(bookingId, newStatus);

    // Keep allRows in sync
    const idx = allRows.findIndex(b => b.booking_id === Number(bookingId));
    if (idx !== -1) allRows[idx].status = newStatus;
}

/* ─── Stats ──────────────────────────────────────────────────────────────── */
function _refreshStatsOnly() {
    fetch('../../api/reservations.php?stats_only=1', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(d => { if (d.stats) _applyStats(d.stats); })
        .catch(() => { });
}

function _applyStats(s) {
    _animCount('stat-total', parseInt(s.total) || 0);
    _animCount('stat-pending', parseInt(s.pending) || 0);
    _animCount('stat-confirmed', parseInt(s.confirmed) || 0);
    _animCount('stat-cancelled', parseInt(s.cancelled) || 0);
}

function _animCount(id, target) {
    const el = document.getElementById(id);
    if (!el) return;
    const start = parseInt(el.textContent) || 0;
    if (start === target) return;
    let i = 0; const steps = 30;
    const t = setInterval(() => {
        i++;
        el.textContent = Math.round(start + (target - start) * (i / steps));
        if (i >= steps) { el.textContent = target; clearInterval(t); }
    }, 16);
}

/* ─── rowHtml ────────────────────────────────────────────────────────────── */
function rowHtml(b, isNew) {
    const id = b.booking_id;
    const padId = String(id).padStart(4, '0');
    const nameParts = (b.user_name || '?').trim().split(/\s+/).slice(0, 2);
    const init = nameParts.map(w => w[0].toUpperCase()).join('');
    const avatarHtml = b.user_photo
        ? `<img src="${(window.APP_BASE || '')}/${b.user_photo}" class="guest-avatar-img"
            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
       <div class="guest-avatar" style="display:none;">${init}</div>`
        : `<div class="guest-avatar">${init}</div>`;
    const badge = isNew ? '<span class="new-booking-badge">NEW</span>' : '';

    return `<tr data-id="${id}" data-status="${escHtml(b.status)}">
    <td><span class="booking-id">#BK-${padId}${badge}</span></td>
    <td><div class="guest-cell">
        ${avatarHtml}
        <div>
            <div class="guest-name">${escHtml(b.user_name)}</div>
            <div class="guest-email">${escHtml(b.user_email)}</div>
        </div>
    </div></td>
    <td>
        <div class="unit-name">${escHtml(b.unit_name)}</div>
        <div class="unit-prop">${escHtml(b.property_name)}</div>
    </td>
    <td>${escHtml(b.checkin_date)}</td>
    <td>${escHtml(b.checkout_date)}</td>
    <td style="text-align:center;font-weight:700;">${b.nights}</td>
    <td><span class="amount-cell">₱${Number(b.total_amount).toLocaleString('en-PH', { maximumFractionDigits: 0 })}</span></td>
    <td><span class="res-badge ${BADGE_CLS[b.status] || 'res-badge-pending'}">${BADGE_LBL[b.status] || b.status}</span></td>
    <td><div style="display:flex;gap:6px;flex-wrap:wrap;">${_actionButtons(id, b.status)}</div></td>
</tr>`;
}

/* ─── renderPage ─────────────────────────────────────────────────────────── */
function renderPage() {
    const tbody = document.getElementById('reservationsTbody');
    const total = allRows.length;
    const pages = Math.max(1, Math.ceil(total / PER_PAGE));
    currentPage = Math.min(currentPage, pages);
    const start = (currentPage - 1) * PER_PAGE;
    const slice = allRows.slice(start, start + PER_PAGE);

    if (total === 0) {
        tbody.innerHTML = `<tr><td colspan="9"><div class="res-empty">
            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/></svg>
            <p>No reservations found.</p></div></td></tr>`;
        document.getElementById('footerCount').innerHTML = 'Showing <strong>0</strong> reservations';
        document.getElementById('paginationBtns').innerHTML = '';
        return;
    }

    tbody.innerHTML = slice.map(b => rowHtml(b, false)).join('');
    _buildPagination(total, pages, start);
}

/* ─── _injectNewRows — prepend new rows into live DOM ───────────────────── */
function _injectNewRows(newOnes) {
    const tbody = document.getElementById('reservationsTbody');
    // Remove "no reservations" placeholder if present
    const empty = tbody.querySelector('tr:not([data-id])');
    if (empty) empty.remove();

    newOnes.forEach(b => {
        const tmp = document.createElement('tbody');
        tmp.innerHTML = rowHtml(b, true);
        const row = tmp.firstElementChild;

        if (!highlightedOnce.has(String(b.booking_id))) {
            highlightedOnce.add(String(b.booking_id));
            row.style.background = '#dcfce7';
            row.style.transition = 'background 1.5s';
            tbody.prepend(row);
            requestAnimationFrame(() => requestAnimationFrame(() => { row.style.background = ''; }));
            const newBadge = row.querySelector('.new-booking-badge');
            if (newBadge) setTimeout(() => newBadge.remove(), 5 * 60 * 1000);
        } else {
            tbody.prepend(row);
        }
    });

    // Trim to PER_PAGE visible rows
    const visible = tbody.querySelectorAll('tr[data-id]');
    for (let i = PER_PAGE; i < visible.length; i++) visible[i].remove();
}

/* ─── _buildPagination ───────────────────────────────────────────────────── */
function _buildPagination(total, pages, start) {
    const from = start + 1;
    const to = Math.min(start + PER_PAGE, total);
    document.getElementById('footerCount').innerHTML =
        `Showing <strong>${from}–${to}</strong> of <strong>${total}</strong> reservation${total !== 1 ? 's' : ''}`;

    const btns = document.getElementById('paginationBtns');
    if (pages <= 1) { btns.innerHTML = ''; return; }

    const svgL = `<svg viewBox="0 0 24 24" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>`;
    const svgR = `<svg viewBox="0 0 24 24" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>`;

    let pg = [];
    if (pages <= 5) {
        pg = Array.from({ length: pages }, (_, i) => i + 1);
    } else {
        pg = [1];
        if (currentPage > 3) pg.push('…');
        for (let i = Math.max(2, currentPage - 1); i <= Math.min(pages - 1, currentPage + 1); i++) pg.push(i);
        if (currentPage < pages - 2) pg.push('…');
        pg.push(pages);
    }

    let html = `<button class="pg-btn" onclick="goPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>${svgL}</button>`;
    pg.forEach(p => {
        html += p === '…'
            ? `<span class="pg-btn" style="cursor:default;border:none;">…</span>`
            : `<button class="pg-btn ${p === currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
    });
    html += `<button class="pg-btn" onclick="goPage(${currentPage + 1})" ${currentPage === pages ? 'disabled' : ''}>${svgR}</button>`;
    btns.innerHTML = html;
}

function goPage(p) {
    const pages = Math.ceil(allRows.length / PER_PAGE);
    if (p < 1 || p > pages) return;
    currentPage = p;
    renderPage();
    document.getElementById('reservationsTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ─── Expose updateStatus + goPage globally so inline onclick always works ── */
window.updateStatus = updateStatus;
window.goPage = goPage;

/* ─── Search debounce ────────────────────────────────────────────────────── */
const searchInput = document.getElementById('searchInput');
let searchTimer;
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => searchInput.closest('form').submit(), 500);
});

/* ═══════════════════════════════════════════════════════════════════════════
 *  BOOTSTRAP
 * ═══════════════════════════════════════════════════════════════════════════ */
allRows = (window.__PS_RESERVATIONS__.allRows || []).map(normaliseBooking).filter(Boolean);
knownIds = new Set(allRows.map(b => String(b.booking_id)));
allRows.forEach(b => highlightedOnce.add(String(b.booking_id))); // no flash on first load
renderPage();

/* ─── Self-contained polling loop (primary auto-update) ─────────────────── */
(function startPolling() {
    const POLL_MS = 8000;

    function poll() {
        if (document.hidden) return;

        // Call the same API the page uses — limit=200 to always get full list
        const url = '../../api/reservations.php?' + new URLSearchParams({
            status: currentStatus,
            search: currentSearch,
            limit: '200',
            _: Date.now(),
        });

        fetch(url, { credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(data => {
                if (!data.success) return;

                const incoming = (data.bookings || []).map(normaliseBooking).filter(Boolean);
                const newOnes = incoming.filter(b => !knownIds.has(String(b.booking_id)));
                const existing = incoming.filter(b => knownIds.has(String(b.booking_id)));

                // Silently patch status changes on already-visible rows
                existing.forEach(b => _patchRow(b.booking_id, b.status));

                if (newOnes.length) {
                    newOnes.forEach(b => {
                        knownIds.add(String(b.booking_id));
                        allRows.unshift(b);
                    });

                    if (currentPage === 1) _injectNewRows(newOnes);

                    // Rebuild footer + pagination without wiping tbody
                    const total = allRows.length;
                    _buildPagination(total, Math.max(1, Math.ceil(total / PER_PAGE)), (currentPage - 1) * PER_PAGE);
                }

                if (data.stats) _applyStats(data.stats);
            })
            .catch(() => { });
    }

    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    poll();
    setInterval(poll, POLL_MS);
})();

/* ─── Secondary channel: realtime.js ps: events ─────────────────────────── */
window.addEventListener('ps:booking_stats', e => _applyStats(e.detail));

window.addEventListener('ps:new_bookings', e => {
    const incoming = (e.detail || []).map(normaliseBooking).filter(Boolean);
    const newOnes = incoming.filter(b => !knownIds.has(String(b.booking_id)));
    const existing = incoming.filter(b => knownIds.has(String(b.booking_id)));

    existing.forEach(b => _patchRow(b.booking_id, b.status));
    if (!newOnes.length) return;

    newOnes.forEach(b => { knownIds.add(String(b.booking_id)); allRows.unshift(b); });
    if (currentPage === 1) _injectNewRows(newOnes);

    const total = allRows.length;
    _buildPagination(total, Math.max(1, Math.ceil(total / PER_PAGE)), (currentPage - 1) * PER_PAGE);
    _refreshStatsOnly();
});

window.confirmAction = function (message, options = {}) {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const title = document.getElementById('confirmModalTitle');
        const messageEl = document.getElementById('confirmModalMessage');
        const cancelBtn = document.getElementById('confirmModalCancel');
        const confirmBtn = document.getElementById('confirmModalConfirm');

        // Set content
        title.textContent = options.title || 'Confirm Action';
        messageEl.textContent = message;

        // Set button styles
        confirmBtn.className = 'confirm-modal-btn confirm-btn-confirm';
        if (options.danger) {
            confirmBtn.classList.add('danger');
        }
        confirmBtn.textContent = options.confirmText || 'Confirm';
        cancelBtn.textContent = options.cancelText || 'Cancel';

        // Show modal
        modal.classList.add('active');
        confirmBtn.focus();

        // Handle confirm
        const handleConfirm = () => {
            cleanup();
            resolve(true);
        };

        // Handle cancel
        const handleCancel = () => {
            cleanup();
            resolve(false);
        };

        // Handle escape key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                cleanup();
                resolve(false);
            }
        };

        // Handle overlay click
        const handleOverlayClick = (e) => {
            if (e.target === modal) {
                cleanup();
                resolve(false);
            }
        };

        // Cleanup function
        const cleanup = () => {
            modal.classList.remove('active');
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            document.removeEventListener('keydown', handleEscape);
            modal.removeEventListener('click', handleOverlayClick);
        };

        // Add event listeners
        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
        document.addEventListener('keydown', handleEscape);
        modal.addEventListener('click', handleOverlayClick);
    });
};