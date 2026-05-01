showToast("You do not have permission to access this page.", "error", "Unauthorized"); setTimeout(() => history.back(), 2000);

var _bookingJSReady = true;

// ── Real-time live updates for bookings page ──
    window.PS_RT_PAGE = 'bookings';

    const _bkStatusMap = {
        pending:   { label: 'Pending',   cls: 'badge-gold' },
        confirmed: { label: 'Upcoming',  cls: 'badge-blue' },
        active:    { label: 'Active',    cls: 'badge-green' },
        completed: { label: 'Completed', cls: 'badge-green' },
        cancelled: { label: 'Cancelled', cls: 'badge-red'  },
    };

    // SVG icons used inside rebuilt action buttons
    const _svgDoc  = `<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;flex-shrink:0;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>`;
    const _svgStar = `<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`;
    const _svgHome = `<svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;flex-shrink:0;"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>`;

    function _fmtDate(iso) {
        if (!iso) return '';
        const d = new Date(iso + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }
    function _nightsBetween(ci, co) {
        if (!ci || !co) return 0;
        return Math.max(0, Math.round((new Date(co) - new Date(ci)) / 86400000));
    }

    window.addEventListener('ps:booking_updates', e => {
        e.detail.forEach(b => {
            const id   = String(b.booking_id);
            let card   = document.querySelector(`[data-booking-id="${id}"]`);

            // ── NEW BOOKING: not yet in DOM — build and prepend ──────────
            if (!card && b.booking_id) {
                const unitDisplay = b.unit_name || b.unit_number || 'Room';
                const fmtCi = _fmtDate(b.checkin_date);
                const fmtCo = _fmtDate(b.checkout_date);
                const nights = _nightsBetween(b.checkin_date, b.checkout_date);
                const priceFmt = b.total_amount
                    ? '₱' + Number(b.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 0 }) + ' <sub>total</sub>'
                    : '—';
                const bkRef = 'BK-' + String(id).padStart(6, '0');
                const dispSt = ['confirmed','pending'].includes(b.status) ? 'upcoming' : b.status;
                const info = _bkStatusMap[b.status] || { label: b.status, cls: 'badge-blue' };

                const wrapper = document.querySelector('.bookings-list, .bc-list, #bookingsList, .bookings-grid');
                if (wrapper) {
                    const newCard = document.createElement('div');
                    newCard.className = 'booking-card';
                    newCard.dataset.bookingId = id;
                    newCard.dataset.status    = dispSt;
                    newCard.dataset.rawStatus = b.status;
                    newCard.innerHTML = `
                        <div class="bc-header">
                            <div class="bc-room">${unitDisplay}</div>
                            <span class="badge ${info.cls} booking-status-badge" data-status="${b.status}" data-prev-status="${b.status}">${info.label}</span>
                        </div>
                        <div class="bc-meta">
                            <div class="bc-dates">
                                <span data-field="checkin">${fmtCi}</span>
                                <span class="bc-arrow">→</span>
                                <span data-field="checkout">${fmtCo}</span>
                            </div>
                            <div class="bc-nights" data-field="nights">${nights} night${nights !== 1 ? 's' : ''}</div>
                        </div>
                        <div class="bc-ref">${bkRef}</div>
                        <div class="bc-price" data-field="price">${priceFmt}</div>
                        <div class="booking-actions">
                            <button class="bc-btn-danger" data-action="cancel" onclick="openCancelModal(0, '${bkRef}')">Cancel</button>
                        </div>`;
                    newCard.style.background = '#f0fdf4';
                    wrapper.prepend(newCard);
                    setTimeout(() => { newCard.style.transition = 'background 1.2s'; newCard.style.background = ''; }, 100);

                    // Remove empty-state if present
                    wrapper.querySelector('.bookings-empty, [data-empty-state]')?.remove();

                    if (typeof renderPagination === 'function') setTimeout(renderPagination, 80);
                    if (typeof showToast === 'function') showToast(`New booking ${bkRef} added!`, 'success');
                }
                return; // card was just created, rest of loop handles updates
            }

            if (!card) return;

            const info    = _bkStatusMap[b.status] || { label: b.status, cls: 'badge-blue' };
            const badge   = card.querySelector('.booking-status-badge');
            const prevSt  = badge?.dataset.prevStatus || card.dataset.rawStatus || '';
            const changed = prevSt && prevSt !== b.status;

            // ── 1. Status badge ──────────────────────────────────────────
            if (badge) {
                badge.textContent       = info.label;
                badge.className         = `badge ${info.cls} booking-status-badge`;
                badge.dataset.status    = b.status;
                badge.dataset.prevStatus = b.status;
            }
            card.dataset.rawStatus = b.status;

            // ── 2. Tab filter state ──────────────────────────────────────
            const dispSt = ['confirmed','pending'].includes(b.status) ? 'upcoming' : b.status;
            card.dataset.status = dispSt;

            // ── 3. Dates & nights (if admin extended checkout) ───────────
            if (b.checkin_date) {
                const ci = card.querySelector('[data-field="checkin"]');
                if (ci) ci.textContent = _fmtDate(b.checkin_date);
                card.dataset.checkin = b.checkin_date;
            }
            if (b.checkout_date) {
                const co = card.querySelector('[data-field="checkout"]');
                if (co) co.textContent = _fmtDate(b.checkout_date);
                card.dataset.checkout = b.checkout_date;
            }
            if (b.checkin_date || b.checkout_date) {
                const ci  = b.checkin_date  || card.dataset.checkin;
                const co  = b.checkout_date || card.dataset.checkout;
                const n   = _nightsBetween(ci, co);
                const nEl = card.querySelector('[data-field="nights"]');
                if (nEl) nEl.textContent = n + ' night' + (n !== 1 ? 's' : '');
            }

            // ── 4. Price (if total_amount updated) ───────────────────────
            if (b.total_amount) {
                const priceEl = card.querySelector('[data-field="price"]');
                if (priceEl) {
                    const fmt = '₱' + Number(b.total_amount).toLocaleString('en-PH', {minimumFractionDigits: 0});
                    priceEl.innerHTML = fmt + ' <sub>total</sub>';
                }
            }

            // ── 5. Actions area: fully rebuild on status change ──────────
            if (changed) {
                const acts = card.querySelector('.booking-actions');
                if (acts) {
                    const roomName = (card.querySelector('.bc-room')?.textContent || '').trim().replace(/'/g, "\\'");
                    const idx      = card.dataset.idx || 0;
                    const reviewed = card.dataset.reviewed === '1';
                    const rating   = card.dataset.reviewRating || '0';

                    if (b.status === 'cancelled') {
                        acts.innerHTML = `<button class="bc-btn-ghost" style="cursor:default;opacity:0.45;" disabled>Cancelled</button>`;

                    } else if (b.status === 'completed') {
                        const reviewBtn = reviewed
                            ? `<button class="bc-btn-ghost" style="cursor:default;opacity:0.7;" disabled>${_svgStar} Reviewed · ${rating}/5</button>`
                            : `<button class="bc-btn-ghost" onclick="openReviewModal('${roomName}', ${id}, ${idx})">${_svgStar} Leave a Review</button>`;
                        acts.innerHTML =
                            `<button class="bc-btn-receipt" id="receipt-btn-${id}" onclick="downloadReceipt(${id}, this)" title="Download Receipt as PDF">${_svgDoc} Receipt</button>` +
                            reviewBtn +
                            `<button class="bc-btn-primary" onclick="openRebookModal('${roomName}')">${_svgHome} Book Again</button>`;

                    } else if (b.status === 'confirmed') {
                        // Receipt + remove cancel (grace window likely passed when admin confirms)
                        if (!acts.querySelector('.bc-btn-receipt')) {
                            acts.insertAdjacentHTML('afterbegin',
                                `<button class="bc-btn-receipt" id="receipt-btn-${id}" onclick="downloadReceipt(${id}, this)" title="Download Receipt as PDF">${_svgDoc} Receipt</button>`);
                        }
                        const cancelBtn = acts.querySelector('[data-action="cancel"]');
                        if (cancelBtn) cancelBtn.remove();
                        // Remove "No cancellation" ghost labels
                        acts.querySelectorAll('.bc-btn-ghost:not([disabled])').forEach(el => el.remove());

                    } else if (b.status === 'active') {
                        // Active: hide cancel, remove receipt (not finalized yet), show active label
                        acts.querySelectorAll('[data-action="cancel"], .bc-btn-receipt').forEach(el => el.remove());
                        if (!acts.querySelector('.active-stay-lbl')) {
                            acts.insertAdjacentHTML('beforeend',
                                `<span class="bc-btn-ghost active-stay-lbl" style="cursor:default;opacity:0.55;font-size:12px;" title="You are currently checked in">Active stay</span>`);
                        }

                    } else if (b.status === 'pending') {
                        // Pending: remove receipt if shown, add cancel back if it was removed
                        acts.querySelectorAll('.bc-btn-receipt, .active-stay-lbl').forEach(el => el.remove());
                        if (!acts.querySelector('[data-action="cancel"]')) {
                            acts.insertAdjacentHTML('beforeend',
                                `<button class="bc-btn-danger" data-action="cancel" onclick="openCancelModal(${idx}, 'BK-${String(id).padStart(6,'0')}')">Cancel</button>`);
                        }
                    }
                }
            }

            // ── 6. Flash card + toast ────────────────────────────────────
            if (changed) {
                card.style.transition = 'outline 0.3s';
                card.style.outline = '2px solid var(--blue-300, #93c5fd)';
                setTimeout(() => { card.style.outline = ''; }, 2200);

                if (typeof showToast === 'function') {
                    showToast(
                        `Booking #BK-${String(id).padStart(6,'0')} is now ${info.label}.`,
                        b.status === 'cancelled' ? 'warning' : 'success'
                    );
                }
            }

            // ── 7. Re-run pagination (status filter may have changed) ────
            if (changed && typeof renderPagination === 'function') {
                setTimeout(renderPagination, 50);
            }
        });
    });
