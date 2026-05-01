const currentStatus = window.__PS_RESERVATIONS__.currentStatus;
    const currentSearch = window.__PS_RESERVATIONS__.currentSearch;

    let knownIds = new Set(
        [...document.querySelectorAll('#reservationsTbody tr[data-id]')]
            .map(r => r.dataset.id)
    );
    let lastKnownCount = knownIds.size;

    function updateStatus(bookingId, newStatus, btn) {
        const labels = { confirmed: 'confirm', cancelled: 'cancel', completed: 'mark as completed' };

        if (!confirm(`Are you sure you want to ${labels[newStatus]} booking #BK-${String(bookingId).padStart(4, '0')}?`)) return;

        const originalText = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }
        showToast('Updating…', 'info');

        fetch('../../api/reservations.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'update_status', booking_id: bookingId, status: newStatus })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Updated!', 'success', 'Updated!');

                    // ── Update row in-place ──────────────────────────
                    const id  = String(bookingId);
                    const row = document.querySelector(`#reservationsTbody tr[data-id="${id}"]`);
                    if (row) {
                        row.dataset.status = newStatus;
                        const badge = row.querySelector('.res-badge, [class*="badge-"]');
                        if (badge) {
                            const lbl = { pending:'Pending', confirmed:'Confirmed', active:'Active',
                                          completed:'Completed', cancelled:'Cancelled' };
                            const cls = { pending:'res-badge-pending', confirmed:'res-badge-success',
                                          active:'res-badge-success', completed:'res-badge-info',
                                          cancelled:'res-badge-danger' };
                            badge.textContent = lbl[newStatus] || newStatus;
                            badge.className   = 'res-badge ' + (cls[newStatus] || 'res-badge-pending');
                        }
                        // Remove action buttons that no longer apply
                        if (newStatus !== 'pending') {
                            row.querySelectorAll('.btn-confirm,.btn-cancel').forEach(b => b.remove());
                        }
                        if (['completed','cancelled'].includes(newStatus)) {
                            row.querySelectorAll('.btn-complete').forEach(b => b.remove());
                        }
                        row.style.transition = 'background 0.5s';
                        row.style.background = 'var(--blue-50,#eff6ff)';
                        setTimeout(() => { row.style.background = ''; }, 2000);
                    }

                    // Also refresh stats counters
                    setTimeout(() => refreshTable(), 1500);
                } else {
                    showToast(data.message || 'Could not update booking.', 'error', 'Failed');
                    if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = originalText; }
                }
            })
            .catch(() => {
                showToast('Server unreachable. Please try again.', 'error');
                if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = originalText; }
            });
    }

    function refreshTable() {
        const banner = document.getElementById('newBookingBanner');
        if (banner) banner.classList.remove('show');
        const params = new URLSearchParams({ ajax: '1', status: currentStatus, search: currentSearch });
        fetch('../../api/reservations.php?' + params)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                renderRows(data.bookings);
                updateStats(data.stats);
            })
            .catch(() => { });
    }

    function rowHtml(b) {
        const unitLabel = b.unit_name || ((b.property_name || '') + ' — Unit ' + (b.unit_number || ''));
        const initials = (b.user_name || '?').charAt(0).toUpperCase();
        const padId = String(b.booking_id).padStart(4, '0');
        const isNew = !knownIds.has(String(b.booking_id));

        let actions = '<span style="font-size:12px;color:#cbd5e1;">—</span>';
        if (b.status === 'pending') {
            actions = `
            <button class="action-btn btn-confirm" onclick="updateStatus(${b.booking_id},'confirmed',this)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Confirm
            </button>
            <button class="action-btn btn-cancel" onclick="updateStatus(${b.booking_id},'cancelled',this)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Cancel
            </button>`;
        } else if (b.status === 'confirmed') {
            actions = `
            <button class="action-btn btn-complete" onclick="updateStatus(${b.booking_id},'completed',this)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Complete
            </button>
            <button class="action-btn btn-cancel" onclick="updateStatus(${b.booking_id},'cancelled',this)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Cancel
            </button>
            <a href="../../api/user/booking_receipt.php?booking_id=${b.booking_id}" target="_blank" class="action-btn" style="text-decoration:none;">🧾</a>`;
        } else if (b.status === 'completed') {
            actions = `<a href="../../api/user/booking_receipt.php?booking_id=${b.booking_id}" target="_blank" class="action-btn" style="text-decoration:none;">🧾 Receipt</a>`;
        }

        const badgeMap = { confirmed: 'success', active: 'success', pending: 'pending', completed: 'info', cancelled: 'danger' };
        const badgeLabelMap = { active: 'Active', confirmed: 'Confirmed', pending: 'Pending', completed: 'Completed', cancelled: 'Cancelled' };
        const badgeCls = badgeMap[b.status] || 'pending';
        const badgeTxt = badgeLabelMap[b.status] || b.status;

        return `<tr data-id="${b.booking_id}"${isNew ? ' class="row-new"' : ''}>
        <td><span class="booking-id">#BK-${padId}${isNew ? '<span class="new-booking-badge">NEW</span>' : ''}</span></td>
        <td>
            <div class="guest-cell">
                <div class="guest-avatar">${initials}</div>
                <div>
                    <div class="guest-name">${escHtml(b.user_name)}</div>
                    <div class="guest-email">${escHtml(b.user_email)}</div>
                </div>
            </div>
        </td>
        <td>
            <div class="unit-name">${escHtml(unitLabel)}</div>
            <div class="unit-prop">${escHtml(b.property_name || '')}</div>
        </td>
        <td>${escHtml(b.checkin_date)}</td>
        <td>${escHtml(b.checkout_date)}</td>
        <td style="text-align:center;font-weight:700;">${b.nights}</td>
        <td><span class="amount-cell">₱${Number(b.total_amount).toLocaleString('en-PH', { maximumFractionDigits: 0 })}</span></td>
        <td><span class="res-badge res-badge-${badgeCls}">${badgeTxt}</span></td>
        <td><div style="display:flex;gap:6px;flex-wrap:wrap;">${actions}</div></td>
    </tr>`;
    }

    function renderRows(bookings) {
        knownIds = new Set(bookings.map(b => String(b.booking_id)));
        paginateRows(bookings);
    }

    function updateStats(stats) {
        if (!stats) return;
        animateCount('stat-total', parseInt(stats.total) || 0);
        animateCount('stat-pending', parseInt(stats.pending) || 0);
        animateCount('stat-confirmed', parseInt(stats.confirmed) || 0);
        animateCount('stat-cancelled', parseInt(stats.cancelled) || 0);
    }

    function animateCount(id, target) {
        const el = document.getElementById(id);
        if (!el) return;
        const start = parseInt(el.textContent) || 0;
        if (start === target) return;
        const duration = 600, step = 16;
        const steps = duration / step;
        const inc = (target - start) / steps;
        let current = start, count = 0;
        const timer = setInterval(() => {
            count++;
            current += inc;
            el.textContent = Math.round(current);
            if (count >= steps) { el.textContent = target; clearInterval(timer); }
        }, step);
    }

    function pollForNewBookings() {
        fetch('../../api/reservations.php?ajax=1&status=' + currentStatus + '&search=' + encodeURIComponent(currentSearch))
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const newOnes = data.bookings.filter(b => !knownIds.has(String(b.booking_id)));
                renderRows(data.bookings, false);
                updateStats(data.stats);
                // Badge the newly appeared rows
                newOnes.forEach(b => {
                    knownIds.add(String(b.booking_id));
                    const row = document.querySelector(`#reservationsTbody tr[data-id="${b.booking_id}"]`);
                    if (row) {
                        const idCell = row.querySelector('.booking-id');
                        if (idCell && !idCell.querySelector('.new-booking-badge')) {
                            const badge = document.createElement('span');
                            badge.className = 'new-booking-badge';
                            badge.textContent = 'NEW';
                            idCell.appendChild(badge);
                            setTimeout(() => badge.remove(), 5 * 60 * 1000);
                        }
                    }
                });
            })
            .catch(() => { });
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    const searchInput = document.getElementById('searchInput');
    let searchTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => searchInput.closest('form').submit(), 500);
    });

    const PER_PAGE = 10;
    let currentPage = 1;
    let allRows = [];

    function paginateRows(rows) {
        allRows = rows;
        currentPage = 1;
        renderPage();
    }

    function renderPage() {
        const tbody = document.getElementById('reservationsTbody');
        const total = allRows.length;
        const pages = Math.max(1, Math.ceil(total / PER_PAGE));
        currentPage = Math.min(currentPage, pages);
        const start = (currentPage - 1) * PER_PAGE;
        const slice = allRows.slice(start, start + PER_PAGE);

        if (total === 0) {
            tbody.innerHTML = `<tr><td colspan="9">
            <div class="res-empty">
                <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="#cbd5e1" stroke-width="1.5">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <p>No reservations found.</p>
            </div>
        </td></tr>`;
            document.getElementById('footerCount').innerHTML = 'Showing <strong>0</strong> reservations';
            document.getElementById('paginationBtns').innerHTML = '';
            return;
        }
        tbody.innerHTML = slice.map(rowHtml).join('');

        const from = total === 0 ? 0 : start + 1;
        const to = Math.min(start + PER_PAGE, total);
        document.getElementById('footerCount').innerHTML =
            `Showing <strong>${from}–${to}</strong> of <strong>${total}</strong> reservation${total !== 1 ? 's' : ''}`;

        const btns = document.getElementById('paginationBtns');
        if (pages <= 1) { btns.innerHTML = ''; return; }

        let html = `<button class="pg-btn" onclick="goPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
        <svg viewBox="0 0 24 24" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
    </button>`;

        let pagesToShow = [];
        if (pages <= 5) {
            pagesToShow = Array.from({ length: pages }, (_, i) => i + 1);
        } else {
            pagesToShow = [1];
            if (currentPage > 3) pagesToShow.push('…');
            for (let i = Math.max(2, currentPage - 1); i <= Math.min(pages - 1, currentPage + 1); i++) {
                pagesToShow.push(i);
            }
            if (currentPage < pages - 2) pagesToShow.push('…');
            pagesToShow.push(pages);
        }

        pagesToShow.forEach(p => {
            if (p === '…') {
                html += `<span class="pg-btn" style="cursor:default;border:none;">…</span>`;
            } else {
                html += `<button class="pg-btn ${p === currentPage ? 'active' : ''}" onclick="goPage(${p})">${p}</button>`;
            }
        });

        html += `<button class="pg-btn" onclick="goPage(${currentPage + 1})" ${currentPage === pages ? 'disabled' : ''}>
        <svg viewBox="0 0 24 24" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
    </button>`;

        btns.innerHTML = html;
    }

    function goPage(p) {
        const pages = Math.ceil(allRows.length / PER_PAGE);
        if (p < 1 || p > pages) return;
        currentPage = p;
        renderPage();
        document.getElementById('reservationsTable').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    allRows = window.__PS_RESERVATIONS__.allRows;
    knownIds = new Set(allRows.map(b => String(b.booking_id)));

    // ── seed realtime.js admin known IDs ──────────────────
    document.addEventListener('DOMContentLoaded', () => {
        if (window.PSRealtime) {
            allRows.forEach(b => window._psAdminKnownIds && window._psAdminKnownIds.add(String(b.booking_id)));
        }
    });

    renderPage();

    // ── Wire realtime.js events ───────────────────────────
    window.addEventListener('ps:booking_stats', e => {
        updateStats(e.detail);
    });

    window.addEventListener('ps:new_bookings', e => {
        const incoming = e.detail;
        const newOnes  = incoming.filter(b => !knownIds.has(String(b.booking_id)));
        const changed  = incoming.filter(b =>  knownIds.has(String(b.booking_id)));

        // ── Silently patch rows whose status changed ──────────────────────
        changed.forEach(b => {
            const id  = String(b.booking_id);
            const row = document.querySelector(`#reservationsTbody tr[data-id="${id}"]`);
            if (!row) return;
            const prevStatus = row.dataset.status;
            if (prevStatus === b.status) return;
            row.dataset.status = b.status;
            const badge = row.querySelector('.res-badge, .badge, [class*="badge-"]');
            if (badge) {
                const lbl = { pending:'Pending', confirmed:'Confirmed', active:'Active',
                              completed:'Completed', cancelled:'Cancelled' };
                const cls = { pending:'res-badge-pending', confirmed:'res-badge-success',
                              active:'res-badge-success', completed:'res-badge-info',
                              cancelled:'res-badge-danger' };
                badge.textContent = lbl[b.status] || b.status;
                badge.className = 'res-badge ' + (cls[b.status] || 'res-badge-pending');
            }
            const confirmBtn  = row.querySelector('.btn-confirm');
            const cancelBtn   = row.querySelector('.btn-cancel');
            const completeBtn = row.querySelector('.btn-complete');
            if (b.status !== 'pending') {
                if (confirmBtn) confirmBtn.remove();
                if (cancelBtn)  cancelBtn.remove();
            }
            if (['completed','cancelled'].includes(b.status) && completeBtn) completeBtn.remove();
            row.style.transition = 'background 0.5s';
            row.style.background = 'var(--blue-50,#eff6ff)';
            setTimeout(() => { row.style.background = ''; }, 2000);
        });

        // ── Inject brand-new rows into the table live ─────────────────────
        if (!newOnes.length) return;

        // Only show if not filtered out by current status/search filter
        const tbody = document.getElementById('reservationsTbody');
        if (!tbody) return;

        // Remove the "no reservations" empty row if present
        const emptyRow = document.getElementById('emptyRow');
        if (emptyRow) emptyRow.remove();

        newOnes.forEach(b => {
            knownIds.add(String(b.booking_id));
            allRows.unshift(b); // keep allRows in sync

            // Build and prepend the row
            const html = rowHtml(b); // rowHtml checks knownIds — mark as known first
            const tmp = document.createElement('tbody');
            tmp.innerHTML = html;
            const row = tmp.firstElementChild;

            // Add the NEW badge inside the booking-id cell
            const idCell = row.querySelector('.booking-id');
            if (idCell) {
                const badge = document.createElement('span');
                badge.className = 'new-booking-badge';
                badge.textContent = 'NEW';
                idCell.appendChild(badge);
                setTimeout(() => badge.remove(), 5 * 60 * 1000);
            }

            // Flash green on entry
            row.style.background = '#dcfce7';
            row.style.transition = 'background 1s';
            tbody.prepend(row);
            requestAnimationFrame(() => {
                requestAnimationFrame(() => { row.style.background = ''; });
            });
        });

        // Update the showing X–Y of Z counter
        const showingEl = document.querySelector('.res-showing, [id*="showing"]');
        if (showingEl) {
            const total = knownIds.size;
            showingEl.textContent = `Showing 1–${Math.min(total, 25)} of ${total} reservation${total !== 1 ? 's' : ''}`;
        }

        // Refresh stats counters from server
        fetch('../../api/reservations.php?ajax=1&stats_only=1')
            .then(r => r.json())
            .then(data => { if (data.stats) updateStats(data.stats); })
            .catch(() => {});
    });
