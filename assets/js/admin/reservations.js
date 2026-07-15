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
let currentStatus = window.__PS_RESERVATIONS__.currentStatus || 'all';
let currentSearch = window.__PS_RESERVATIONS__.currentSearch || '';

let knownIds = new Set();   // booking IDs already known to the page
const highlightedOnce = new Set(); // IDs that have already been green-flashed
let sourceRows = [];        // ALL rows unfiltered (master list)
let allRows = [];           // filtered rows (what renderPage uses)
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

function fmtDate(iso) {
    if (!iso) return '—';
    const d = new Date(String(iso).slice(0, 10) + 'T00:00:00');
    if (isNaN(d.getTime())) return String(iso);
    return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
}


/* ─── Action buttons HTML: one primary button + a "⋯" dropdown ──────────── */
function _actionButtons(bookingId, status) {
    const chk = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
    const dots = `<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>`;

    const icoView = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
    const icoCopy = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>`;
    const icoReceipt = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`;
    const icoMail = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/><polyline points="22 6 16 12 13 9"/><path d="M3 6l9 6 4-2.7"/></svg>`;
    const icoX = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
    const icoTrash = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>`;

    const viewBtn = `<button class="action-btn btn-view" onclick="openDetail(${bookingId})">View</button>`;
    const viewItem = `<button class="res-menu-item" onclick="openDetail(${bookingId});_closeRowMenus();">${icoView}View Details</button>`;
    const copyItem = `<button class="res-menu-item" onclick="_copyBookingRef(${bookingId},this)">${icoCopy}Copy Reference</button>`;
    const receiptItem = `<button class="res-menu-item" onclick="_closeRowMenus();openReceiptModal(${bookingId})">${icoReceipt}View Receipt</button>`;
    const resendItem = `<button class="res-menu-item" onclick="_resendConfirmation(${bookingId},this)">${icoMail}Resend Confirmation</button>`;
    const cancelItem = `<button class="res-menu-item res-menu-item-danger" onclick="_closeRowMenus();updateStatus(${bookingId},'cancelled',this)">${icoX}Cancel Booking</button>`;
    const deleteItem = `<button class="res-menu-item res-menu-item-danger" onclick="_closeRowMenus();deleteBooking(${bookingId},this)">${icoTrash}Delete Booking</button>`;
    const sep = `<div class="res-menu-sep"></div>`;

    let primary;
    let menuItems;

    if (status === 'pending') {
        // Forward action (Confirm) is primary; details/cancel live in the dropdown.
        primary = `<button class="action-btn btn-confirm" onclick="updateStatus(${bookingId},'confirmed',this)">${chk}Confirm</button>`;
        menuItems = [viewItem, copyItem, sep, cancelItem];
    } else if (status === 'confirmed') {
        // Forward action (Complete) is primary; details/receipt/resend/cancel live in the dropdown.
        primary = `<button class="action-btn btn-complete" onclick="updateStatus(${bookingId},'completed',this)">${chk}Complete</button>`;
        menuItems = [viewItem, copyItem, receiptItem, resendItem, sep, cancelItem];
    } else if (status === 'completed' || status === 'active') {
        // No forward action — View is primary.
        primary = viewBtn;
        menuItems = [copyItem, receiptItem, resendItem];
    } else {
        // cancelled, or any other status — View only, plus a guarded Delete.
        primary = viewBtn;
        menuItems = [copyItem, sep, deleteItem];
    }

    const menuHtml = menuItems.length
        ? `<div class="res-menu">
             <button class="res-menu-trigger" onclick="_toggleRowMenu(this)" aria-label="More actions">${dots}</button>
           </div>`
        : '';

    if (menuItems.length) {
        _pendingMenuItems.set(bookingId, menuItems.join(''));
    }

    return `<div class="res-action-cell">${primary}${menuHtml}</div>`;
}

/* ─── Row "⋯" dropdown — single shared element, fixed-positioned ─────────
 * Rendered once and appended to <body> so it can never be clipped by the
 * table's `overflow-x: auto` scroll container. Position is computed from
 * the trigger button's on-screen rect and clamped to the viewport.        */
const _pendingMenuItems = new Map(); // bookingId -> menu items HTML, set by _actionButtons just before use

function _getSharedMenuEl() {
    let el = document.getElementById('resSharedMenu');
    if (!el) {
        el = document.createElement('div');
        el.id = 'resSharedMenu';
        el.className = 'res-menu-dropdown';
        document.body.appendChild(el);
    }
    return el;
}

function _closeRowMenus() {
    const el = document.getElementById('resSharedMenu');
    if (el) { el.classList.remove('open'); el.innerHTML = ''; }
}

function _toggleRowMenu(btn) {
    const el = _getSharedMenuEl();
    const wasOpenForThisBtn = el.classList.contains('open') && el._ownerBtn === btn;
    _closeRowMenus();
    if (wasOpenForThisBtn) return;

    // Find which booking this trigger belongs to via the row's data-id
    const row = btn.closest('tr[data-id]');
    const bookingId = row ? Number(row.dataset.id) : null;
    const itemsHtml = bookingId !== null ? (_pendingMenuItems.get(bookingId) || '') : '';
    el.innerHTML = itemsHtml;
    el._ownerBtn = btn;

    const rect = btn.getBoundingClientRect();
    el.classList.add('open'); // make visible so offsetWidth is measurable
    const menuWidth = el.offsetWidth || 180;
    const viewportW = document.documentElement.clientWidth;
    const viewportH = document.documentElement.clientHeight;

    let left = rect.right - menuWidth; // right-align to the trigger by default
    if (left < 8) left = 8;
    if (left + menuWidth > viewportW - 8) left = viewportW - menuWidth - 8;

    let top = rect.bottom + 4;
    const menuHeight = el.offsetHeight || 0;
    if (top + menuHeight > viewportH - 8) {
        top = rect.top - menuHeight - 4; // flip above the trigger if no room below
    }

    el.style.left = `${left}px`;
    el.style.top = `${top}px`;
}

document.addEventListener('click', e => {
    const el = document.getElementById('resSharedMenu');
    if (!el) return;
    if (e.target.closest('#resSharedMenu') || e.target.closest('.res-menu-trigger')) return;
    _closeRowMenus();
});
window.addEventListener('resize', _closeRowMenus);
document.addEventListener('scroll', (e) => {
    // Close unless the scroll originated from inside the dropdown itself
    if (!e.target.closest || !e.target.closest('#resSharedMenu')) _closeRowMenus();
}, true);

/* ─── Booking detail modal ───────────────────────────────────────────────── */
function _fmtMoney(n) {
    return '₱' + Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 });
}

function _fmtDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(String(iso).replace(' ', 'T'));
    if (isNaN(d.getTime())) return String(iso);
    return d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function openDetail(bookingId) {
    const modal = document.getElementById('detailModal');
    const body = document.getElementById('detailModalBody');
    const title = document.getElementById('detailModalTitle');
    if (!modal || !body) return;

    title.textContent = `Booking #BK-${String(bookingId).padStart(4, '0')}`;
    body.innerHTML = _detailSkeletonHtml();
    modal.classList.add('active');
    _loadDetail(bookingId, body);
}
window.openDetail = openDetail;

function _loadDetail(bookingId, body) {
    fetch(`../../endpoints/reservations.php?detail=${bookingId}`, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.booking) {
                body.innerHTML = _detailErrorHtml(bookingId, data.message || 'Could not load booking.');
                return;
            }
            body.innerHTML = _detailHtml(data.booking, data.payments || []);
        })
        .catch(() => {
            body.innerHTML = _detailErrorHtml(bookingId, 'Server unreachable. Please check your connection.');
        });
}
window._retryDetail = (bookingId) => {
    const body = document.getElementById('detailModalBody');
    if (!body) return;
    body.innerHTML = _detailSkeletonHtml();
    _loadDetail(bookingId, body);
};

function _detailSkeletonHtml() {
    return `
    <div class="res-detail-grid res-detail-skeleton">
        <div class="res-detail-hero" style="background:#f1f5f9;">
            <div class="res-detail-hero-top">
                <div class="res-skel res-skel-line" style="width:90px;height:20px;border-radius:6px;"></div>
                <div class="res-skel res-skel-line" style="width:70px;height:20px;border-radius:999px;"></div>
            </div>
            <div class="res-skel-guest">
                <div class="res-skel res-skel-avatar" style="width:52px;height:52px;"></div>
                <div style="flex:1;">
                    <div class="res-skel res-skel-line" style="width:50%;"></div>
                    <div class="res-skel res-skel-line" style="width:70%;margin-top:6px;"></div>
                </div>
            </div>
        </div>
        <div class="res-detail-body-pad">
            <div class="res-detail-card">
                <div class="res-skel res-skel-line" style="width:30%;"></div>
                <div class="res-skel res-skel-line" style="width:60%;margin-top:8px;"></div>
            </div>
            <div class="res-detail-card">
                <div class="res-skel res-skel-line" style="width:25%;"></div>
                <div class="res-skel res-skel-line" style="width:45%;margin-top:8px;"></div>
            </div>
            <div class="res-detail-card">
                <div class="res-skel res-skel-line" style="width:35%;"></div>
                <div class="res-skel res-skel-block" style="margin-top:8px;height:40px;"></div>
            </div>
        </div>
    </div>`;
}

function _detailErrorHtml(bookingId, message) {
    return `
    <div class="res-detail-error">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="width:32px;height:32px;color:#fca5a5;margin-bottom:10px;">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p>${escHtml(message)}</p>
        <button type="button" class="action-btn btn-view" onclick="_retryDetail(${bookingId})">Try Again</button>
    </div>`;
}

/* Status → accent color for the modal header strip */
const STATUS_ACCENT = {
    pending: { bg: '#fef3e2', fg: '#b45309', bar: '#deaf37' },
    confirmed: { bg: '#eaf6ed', fg: '#1a7a3d', bar: '#2ECC71' },
    active: { bg: '#eaf6ed', fg: '#1a7a3d', bar: '#2ECC71' },
    completed: { bg: '#eef2fb', fg: '#1e50a2', bar: '#1e50a2' },
    cancelled: { bg: '#fdecea', fg: '#b91c1c', bar: '#E74C3C' },
};

function _detailHtml(b, payments) {
    const unitLabel = b.unit_name || `${b.property_name || ''} — ${b.unit_number || ''}`;
    const guestInitials = (b.user_name || '?').trim().split(/\s+/).slice(0, 2)
        .map(w => w[0].toUpperCase()).join('');
    const accent = STATUS_ACCENT[b.status] || STATUS_ACCENT.pending;

    const checkinStatus = b.checkin_status === 'done'
        ? `<span class="res-stay-tag res-stay-tag-done">✓ Checked in${b.checkin_actual ? ' · ' + _fmtDateTime(b.checkin_actual) : ''}</span>`
        : `<span class="res-stay-tag">Not checked in yet</span>`;
    const checkoutStatus = b.checkout_status === 'done'
        ? `<span class="res-stay-tag res-stay-tag-done">✓ Checked out${b.checkout_actual ? ' · ' + _fmtDateTime(b.checkout_actual) : ''}</span>`
        : `<span class="res-stay-tag">Not checked out yet</span>`;

    const paymentsHtml = payments.length
        ? `<div class="res-payment-list">${payments.map(p => `
            <div class="res-payment-row">
                <div class="res-payment-row-icon ${p.payment_status === 'paid' ? 'is-paid' : ''}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                </div>
                <div class="res-payment-row-main">
                    <div class="res-payment-row-date">${fmtDate(p.payment_date)}</div>
                    <div class="res-payment-row-method">${escHtml(p.payment_method || '—')}</div>
                </div>
                <div class="res-payment-row-right">
                    <div class="res-payment-row-amt">${_fmtMoney(p.amount_paid)}</div>
                    <span class="res-badge ${p.payment_status === 'paid' ? 'res-badge-success' : 'res-badge-pending'}">${escHtml(p.payment_status || '')}</span>
                </div>
            </div>`).join('')}</div>`
        : `<div class="res-payment-empty">No payment records yet.</div>`;

    return `
    <div class="res-detail-grid">
        <div class="res-detail-hero" style="background:${accent.bg};">
            <div class="res-detail-hero-top">
                <div class="res-detail-summary-ref" onclick="_copyBookingRef(${b.booking_id}, this)" title="Click to copy">
                    BK-${String(b.booking_id).padStart(6, '0')}
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                </div>
                <span class="res-badge ${BADGE_CLS[b.status] || 'res-badge-pending'}">${BADGE_LBL[b.status] || b.status}</span>
            </div>
            <div class="res-detail-guest">
                ${b.user_photo
            ? `<img src="../../${b.user_photo}" class="guest-avatar-img res-detail-avatar-lg" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                       <div class="guest-avatar res-detail-avatar-lg" style="display:none;">${guestInitials}</div>`
            : `<div class="guest-avatar res-detail-avatar-lg">${guestInitials}</div>`
        }
                <div>
                    <div class="res-detail-guest-name">${escHtml(b.user_name)}</div>
                    <div class="res-detail-guest-sub">${escHtml(b.user_email)}</div>
                    ${b.user_phone ? `<div class="res-detail-guest-sub">${escHtml(b.user_phone)}</div>` : ''}
                </div>
            </div>
        </div>

        <div class="res-detail-body-pad">
            <div class="res-detail-card">
                <div class="res-detail-card-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                    Unit &amp; Property
                </div>
                <div class="res-detail-value">${escHtml(unitLabel)}</div>
                ${b.property_address ? `<div class="res-detail-muted">${escHtml(b.property_address)}</div>` : ''}
            </div>

            <div class="res-detail-card">
                <div class="res-stay-grid">
                    <div class="res-stay-col">
                        <div class="res-detail-card-label">Check-in</div>
                        <div class="res-detail-value">${fmtDate(b.checkin_date)}</div>
                        ${checkinStatus}
                    </div>
                    <div class="res-stay-divider"></div>
                    <div class="res-stay-col">
                        <div class="res-detail-card-label">Check-out</div>
                        <div class="res-detail-value">${fmtDate(b.checkout_date)}</div>
                        ${checkoutStatus}
                    </div>
                </div>
                <div class="res-stay-meta">
                    <span><strong>${b.nights}</strong> night${b.nights !== 1 ? 's' : ''}</span>
                    <span class="res-stay-meta-sep">·</span>
                    <span><strong>${b.guests}</strong>${b.max_guests ? ' / ' + b.max_guests + ' max' : ''} guest${b.guests !== 1 ? 's' : ''}</span>
                </div>
            </div>

            <div class="res-detail-card res-detail-card-amount">
                <div class="res-amount-row">
                    <div>
                        <div class="res-detail-card-label">Total Amount</div>
                        <div class="res-detail-amount">${_fmtMoney(b.total_amount)}</div>
                    </div>
                    <div class="res-amount-meta">
                        <div><span class="res-detail-muted">Method</span> ${escHtml(b.payment_method || '—')}</div>
                        <div><span class="res-detail-muted">Booked</span> ${fmtDate(b.created_at)}</div>
                    </div>
                </div>
            </div>

            ${b.special_requests ? `
            <div class="res-detail-card">
                <div class="res-detail-card-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Special Requests
                </div>
                <div class="res-detail-note">${escHtml(b.special_requests)}</div>
            </div>` : ''}

            ${b.payment_ref || b.payment_notes ? `
            <div class="res-detail-card">
                <div class="res-detail-card-label">Payment Reference</div>
                <div class="res-detail-value">${escHtml(b.payment_ref || '—')}</div>
                ${b.payment_notes ? `<div class="res-detail-muted">${escHtml(b.payment_notes)}</div>` : ''}
            </div>` : ''}

            <div class="res-detail-card">
                <div class="res-detail-card-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    Payment History
                </div>
                ${paymentsHtml}
            </div>
        </div>

        <div class="res-detail-footer-actions">
            ${_detailFooterButtons(b)}
        </div>
    </div>`;
}

/* ─── Contextual footer buttons for the detail modal ─────────────────────── */
function _detailFooterButtons(b) {
    const id = b.booking_id;
    const receiptBtn = `<button type="button" class="action-btn btn-receipt" onclick="openReceiptModal(${id})">Receipt</button>`;
    const resendBtn = `<button type="button" class="action-btn btn-view" onclick="_resendConfirmation(${id}, this)">Resend Email</button>`;
    const deleteBtn = `<button type="button" class="action-btn btn-cancel" onclick="_deleteFromDetail(${id}, this)">Delete</button>`;

    let leftBtns = '';
    if (b.status === 'pending') {
        leftBtns = `<button type="button" class="action-btn btn-confirm" onclick="_updateStatusFromDetail(${id},'confirmed',this)">Confirm Booking</button>
                    <button type="button" class="action-btn btn-cancel" onclick="_updateStatusFromDetail(${id},'cancelled',this)">Cancel Booking</button>`;
    } else if (b.status === 'confirmed') {
        leftBtns = `<button type="button" class="action-btn btn-complete" onclick="_updateStatusFromDetail(${id},'completed',this)">Mark Completed</button>
                    <button type="button" class="action-btn btn-cancel" onclick="_updateStatusFromDetail(${id},'cancelled',this)">Cancel Booking</button>`;
    }

    let rightBtns = receiptBtn;
    if (['confirmed', 'active', 'completed'].includes(b.status)) rightBtns += resendBtn;
    if (['pending', 'cancelled'].includes(b.status)) rightBtns += deleteBtn;

    return `<div class="res-detail-footer-left">${leftBtns}</div><div class="res-detail-footer-right">${rightBtns}</div>`;
}

/* ─── Status change / delete triggered from inside the modal ────────────── */
async function _updateStatusFromDetail(bookingId, newStatus, btn) {
    const result = await updateStatus(bookingId, newStatus, btn);
    // Refresh the modal body to reflect the new status, without closing it.
    // Skip the refresh if the user cancelled the confirm dialog (result is undefined)
    // or the request failed (handled by updateStatus's own toast already).
    if (!result || !result.success) return;
    const modal = document.getElementById('detailModal');
    if (modal && modal.classList.contains('active')) {
        _retryDetail(bookingId);
    }
}
window._updateStatusFromDetail = _updateStatusFromDetail;

async function _deleteFromDetail(bookingId, btn) {
    await deleteBooking(bookingId, btn);
    // deleteBooking() already closes the modal on success; nothing else to do here.
}
window._deleteFromDetail = _deleteFromDetail;

const detailModal = document.getElementById('detailModal');
const detailModalClose = document.getElementById('detailModalClose');
if (detailModal && detailModalClose) {
    function _closeDetailModal() { detailModal.classList.remove('active'); }
    detailModalClose.addEventListener('click', _closeDetailModal);
    detailModal.addEventListener('click', e => { if (e.target === detailModal) _closeDetailModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') _closeDetailModal(); });
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

    return fetch('../../endpoints/reservations.php', {
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
            return data;
        })
        .catch((err) => {
            if (typeof showToast === 'function') showToast('Server unreachable. Please try again.', 'error');
            if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = orig; }
            return { success: false };
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

    const actionCell = row.querySelector('td:last-child');
    if (actionCell) actionCell.innerHTML = _actionButtons(bookingId, newStatus);

    // Keep allRows in sync
    const idx = allRows.findIndex(b => b.booking_id === Number(bookingId));
    if (idx !== -1) allRows[idx].status = newStatus;
}

/* ─── Copy Reference ─────────────────────────────────────────────────────── */
function _copyBookingRef(bookingId, btn) {
    const ref = `BK-${String(bookingId).padStart(6, '0')}`;
    const done = () => {
        if (typeof showToast === 'function') showToast(`Copied ${ref} to clipboard.`, 'success');
        _closeRowMenus();
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(ref).then(done).catch(() => {
            if (typeof showToast === 'function') showToast('Could not copy to clipboard.', 'error');
        });
    } else {
        // Fallback for non-secure contexts / older browsers
        const ta = document.createElement('textarea');
        ta.value = ref;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); }
        catch (e) { if (typeof showToast === 'function') showToast('Could not copy to clipboard.', 'error'); }
        document.body.removeChild(ta);
    }
}
window._copyBookingRef = _copyBookingRef;

/* ─── Resend Confirmation Email ──────────────────────────────────────────── */
async function _resendConfirmation(bookingId, btn) {
    _closeRowMenus();
    const bookingLabel = `#BK-${String(bookingId).padStart(4, '0')}`;
    const confirmed = await confirmAction(
        `Send the booking confirmation email for ${bookingLabel} to the guest again?`,
        { title: 'Resend Confirmation Email', confirmText: 'Yes, Resend', cancelText: 'Cancel', danger: false }
    );
    if (!confirmed) return;

    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

    fetch('../../endpoints/reservations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'resend_confirmation', booking_id: bookingId,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content ?? ''
        }),
    })
        .then(r => r.json())
        .then(data => {
            if (typeof showToast === 'function') {
                showToast(data.message || (data.success ? 'Email sent!' : 'Could not send email.'), data.success ? 'success' : 'error');
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Server unreachable. Please try again.', 'error');
        })
        .finally(() => {
            if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = orig; }
        });
}
window._resendConfirmation = _resendConfirmation;

/* ─── Delete Booking ─────────────────────────────────────────────────────── */
async function deleteBooking(bookingId, btn) {
    const bookingLabel = `#BK-${String(bookingId).padStart(4, '0')}`;
    const confirmed = await confirmAction(
        `Permanently delete booking ${bookingLabel}? This cannot be undone.`,
        { title: 'Delete Booking', confirmText: 'Yes, Delete', cancelText: 'Go Back', danger: true }
    );
    if (!confirmed) return;

    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

    return fetch('../../endpoints/reservations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'delete', booking_id: bookingId,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content ?? ''
        }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message || 'Booking deleted.', 'success');
                const row = document.querySelector(`#reservationsTbody tr[data-id="${bookingId}"]`);
                if (row) row.remove();
                const idx = allRows.findIndex(b => b.booking_id === Number(bookingId));
                if (idx !== -1) allRows.splice(idx, 1);
                const srcIdx = sourceRows.findIndex(b => b.booking_id === Number(bookingId));
                if (srcIdx !== -1) sourceRows.splice(srcIdx, 1);
                _refreshStatsOnly();
                const detailModalEl = document.getElementById('detailModal');
                if (detailModalEl) detailModalEl.classList.remove('active');
            } else {
                if (typeof showToast === 'function') showToast(data.message || 'Could not delete booking.', 'error');
                if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = orig; }
            }
            return data;
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Server unreachable. Please try again.', 'error');
            if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = orig; }
            return { success: false };
        });
}
window.deleteBooking = deleteBooking;

/* ─── Stats ──────────────────────────────────────────────────────────────── */
function _refreshStatsOnly() {
    fetch('../../endpoints/reservations.php?stats_only=1', { credentials: 'same-origin' })
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
        ? `<img src="../../${b.user_photo}" class="guest-avatar-img"
            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
       <div class="guest-avatar" style="display:none;">${init}</div>`
        : `<div class="guest-avatar">${init}</div>`;
    const badge = isNew ? '<span class="new-booking-badge">NEW</span>' : '';

    return `<tr data-id="${id}" data-status="${escHtml(b.status)}" class="res-row-clickable" onclick="openDetail(${id})">
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
    <td>${fmtDate(b.checkin_date)}</td>
    <td>${fmtDate(b.checkout_date)}</td>
    <td style="text-align:center;font-weight:700;">${b.nights}</td>
    <td><span class="amount-cell">₱${Number(b.total_amount).toLocaleString('en-PH', { maximumFractionDigits: 0 })}</span></td>
    <td><span class="res-badge ${BADGE_CLS[b.status] || 'res-badge-pending'}">${BADGE_LBL[b.status] || b.status}</span></td>
    <td onclick="event.stopPropagation()">${_actionButtons(id, b.status)}</td>
</tr>`;
}

/* ─── applyFilter — client-side filter, no reload ───────────────────────── */
function applyFilter() {
    const q = currentSearch.toLowerCase().trim();
    allRows = sourceRows.filter(b => {
        const statusMatch = currentStatus === 'all' || b.status === currentStatus;
        const searchMatch = !q || [b.user_name, b.user_email, b.unit_name, b.property_name,
            String(b.booking_id)].some(v => (v || '').toLowerCase().includes(q));
        return statusMatch && searchMatch;
    });
    currentPage = 1;
    renderPage();
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

/* ─── Search & Status — dynamic, no reload ──────────────────────────────── */
const searchInput = document.getElementById('searchInput');
let searchTimer;
if (searchInput) {
    // Pre-fill from URL param if any
    searchInput.value = currentSearch;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentSearch = searchInput.value;
            applyFilter();
        }, 250);
    });
}

const statusSelect = document.getElementById('statusSelect');
if (statusSelect) {
    statusSelect.value = currentStatus;
    statusSelect.onchange = null;
    statusSelect.addEventListener('change', () => {
        currentStatus = statusSelect.value;
        applyFilter();
    });
}

/* ── Custom status dropdown ── */
const resStatusWrap    = document.getElementById('resStatusWrap');
const resStatusTrigger = document.getElementById('resStatusTrigger');
const resStatusMenu    = document.getElementById('resStatusMenu');
const resStatusLabel   = document.getElementById('resStatusLabel');

if (resStatusWrap && resStatusTrigger && resStatusMenu) {
    // Set initial active from currentStatus
    resStatusMenu.querySelectorAll('.res-status-opt').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.val === currentStatus);
        if (opt.dataset.val === currentStatus) resStatusLabel.textContent = opt.textContent.trim();
    });

    resStatusTrigger.addEventListener('click', e => {
        e.stopPropagation();
        const isOpen = resStatusWrap.classList.toggle('open');
        resStatusTrigger.setAttribute('aria-expanded', isOpen);
    });

    resStatusMenu.querySelectorAll('.res-status-opt').forEach(opt => {
        opt.addEventListener('click', () => {
            currentStatus = opt.dataset.val;
            resStatusLabel.textContent = opt.textContent.trim();
            resStatusMenu.querySelectorAll('.res-status-opt').forEach(o => o.classList.remove('active'));
            opt.classList.add('active');
            resStatusWrap.classList.remove('open');
            resStatusTrigger.setAttribute('aria-expanded', 'false');
            applyFilter();
        });
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('#resStatusWrap')) {
            resStatusWrap.classList.remove('open');
            resStatusTrigger.setAttribute('aria-expanded', 'false');
        }
    });
}

// Hide the clear button and prevent form submission
const resForm = document.querySelector('.res-controls form');
if (resForm) {
    resForm.addEventListener('submit', e => e.preventDefault());
}

const clearBtn = document.querySelector('.res-clear-btn');
if (clearBtn) {
    clearBtn.addEventListener('click', e => {
        e.preventDefault();
        currentSearch = '';
        if (searchInput) searchInput.value = '';
        applyFilter();
    });
}

/* ═══════════════════════════════════════════════════════════════════════════
 *  BOOTSTRAP
 * ═══════════════════════════════════════════════════════════════════════════ */
allRows = (window.__PS_RESERVATIONS__.allRows || []).map(normaliseBooking).filter(Boolean);
sourceRows = [...allRows];
knownIds = new Set(allRows.map(b => String(b.booking_id)));
allRows.forEach(b => highlightedOnce.add(String(b.booking_id))); // no flash on first load
applyFilter();

/* ─── Self-contained polling loop (primary auto-update) ─────────────────── */
(function startPolling() {
    const POLL_MS = 8000;

    function poll() {
        if (document.hidden) return;

        // Call the same API the page uses — limit=200 to always get full list
        const url = '../../endpoints/reservations.php?' + new URLSearchParams({
            status: 'all',
            search: '',
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
                        sourceRows.unshift(b);
                    });

                    applyFilter();   // re-renders full table — don't also call _injectNewRows
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

    newOnes.forEach(b => { knownIds.add(String(b.booking_id)); sourceRows.unshift(b); });
    applyFilter();   // re-renders the full table — don't also call _injectNewRows (would duplicate the row)
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