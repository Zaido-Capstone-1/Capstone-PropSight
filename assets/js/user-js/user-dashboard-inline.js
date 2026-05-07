window._psToastReady = true;

// ── Top nav active underline for section links ─────────────────
(function () {
    const navLinks = Array.from(document.querySelectorAll('header nav a'));
    const sectionLinks = navLinks.filter(a => (a.getAttribute('href') || '').startsWith('#'));

    function setActiveLinkByHash(hash) {
        navLinks.forEach(a => a.classList.remove('active'));
        const target = navLinks.find(a => a.getAttribute('href') === hash);
        if (target) target.classList.add('active');
    }

    sectionLinks.forEach(a => {
        a.addEventListener('click', function () {
            setActiveLinkByHash(this.getAttribute('href'));
        });
    });

    const sectionIds = sectionLinks
        .map(a => a.getAttribute('href'))
        .filter(Boolean)
        .map(h => h.slice(1));
    const sections = sectionIds
        .map(id => document.getElementById(id))
        .filter(Boolean);

    function updateActiveOnScroll() {
        let current = null;
        const y = window.scrollY + 110; // account for fixed header
        sections.forEach(sec => {
            if (sec.offsetTop <= y) current = sec.id;
        });
        if (current) setActiveLinkByHash('#' + current);
    }

    window.addEventListener('scroll', updateActiveOnScroll, { passive: true });
    updateActiveOnScroll();
})();

// ── Save / unsave room toggle ──────────────────────────────
async function toggleSaveRoom(unitId, btn) {
    btn.classList.add('saving');
    try {
        const fd = new FormData();
        fd.append('unit_id', unitId);
        if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
        const res = await fetch('../../api/user/save_toggle.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            btn.classList.toggle('saved', data.saved);
            btn.setAttribute('aria-label', data.saved ? 'Remove from saved' : 'Save room');
            btn.setAttribute('title', data.saved ? 'Remove from saved' : 'Save to wishlist');
            if (typeof data.saved_count !== 'undefined') {
                const savedCount = Number(data.saved_count) || 0;
                document.querySelectorAll('[data-rt-user="saved_count"]').forEach(el => {
                    el.textContent = String(savedCount);
                });
                document.querySelectorAll('[data-rt-user="saved_count_text"]').forEach(el => {
                    el.textContent = `${savedCount} on wishlist`;
                });
            }
            if (typeof showToast === 'function') {
                showToast(data.saved ? '❤️ Added to your saved rooms!' : 'Removed from saved rooms');
            }
        } else {
            if (typeof showToast === 'function') showToast(data.message || 'Could not update saved rooms. Please try again.', 'error');
        }
    } catch (e) {
        if (typeof showToast === 'function') showToast('Network error. Please try again.');
    }
    btn.classList.remove('saving');
}

// ── Live: active booking status ────────────────────────────
const _dashStatusMap = {
    pending: { text: 'Pending', cls: 'st-pending' },
    confirmed: { text: 'Confirmed', cls: 'st-active' },
    active: { text: 'Active', cls: 'st-active' },
    completed: { text: 'Completed', cls: 'st-done' },
    cancelled: { text: 'Cancelled', cls: 'st-cancelled' },
};
const _lastBookingToastState = {};
const _bookingToastStorageKey = 'ps_booking_status_toasts_seen';
function _getSeenBookingStatusMap() {
    try { return JSON.parse(sessionStorage.getItem(_bookingToastStorageKey) || '{}'); }
    catch (e) { return {}; }
}
function _setSeenBookingStatusMap(map) {
    try { sessionStorage.setItem(_bookingToastStorageKey, JSON.stringify(map)); } catch (e) { }
}
// Seed current visible status so first realtime poll after reload doesn't toast.
(() => {
    const wrap = document.getElementById('rt-active-booking-wrap');
    const pill = document.getElementById('rt-active-booking-status');
    if (!wrap || !pill || !wrap.dataset.bookingId) return;
    const bookingId = String(wrap.dataset.bookingId);
    const status = String(pill.textContent || '').trim().toLowerCase();
    _lastBookingToastState[bookingId] = status;
    const seen = _getSeenBookingStatusMap();
    seen[bookingId] = status;
    _setSeenBookingStatusMap(seen);
})();
window.addEventListener('ps:booking_updates', e => {
    // Helper to format ISO date as "Mon DD, YYYY"
    function _dashFmtDate(iso) {
        if (!iso) return '';
        const d = new Date(iso + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }
    function _dashNights(ci, co) {
        if (!ci || !co) return 0;
        return Math.max(0, Math.round((new Date(co) - new Date(ci)) / 86400000));
    }

    e.detail.forEach(b => {
        const id = String(b.booking_id);
        const statusKey = String(b.status || '').toLowerCase();
        const bookingKey = id;
        const seen = _getSeenBookingStatusMap();

        // ── A. Active booking banner (top of page) ───────────────
        const wrap = document.getElementById('rt-active-booking-wrap');
        if (wrap && String(wrap.dataset.bookingId) === id) {
            const info = _dashStatusMap[b.status] || { text: b.status, cls: '' };

            // Update status pill
            const pill = document.getElementById('rt-active-booking-status');
            if (pill) {
                pill.textContent = info.text;
                pill.className = 'bb-status ' + info.cls;
            }

            // Update dates in the banner if they changed
            const bbDates = wrap.querySelector('.bb-dates');
            if (bbDates && (b.checkin_date || b.checkout_date)) {
                const ci = b.checkin_date || wrap.dataset.checkin;
                const co = b.checkout_date || wrap.dataset.checkout;
                if (ci) wrap.dataset.checkin = ci;
                if (co) wrap.dataset.checkout = co;
                bbDates.innerHTML = `Check-in: ${_dashFmtDate(ci)}<span class="bb-date-sep"> &nbsp;·&nbsp; </span>Check-out: ${_dashFmtDate(co)}`;
            }

            // Sync open Manage Stay modal
            const manageModal = document.getElementById('manageModal');
            const manageRef = document.getElementById('manageBookingRef');
            if (manageModal && manageModal.classList.contains('open') && manageRef) {
                const refId = String(manageRef.textContent || '').replace(/\D/g, '');
                if (refId && Number(refId) === Number(id)) {
                    const managePill = document.getElementById('manageStatusPill');
                    const manageText = document.getElementById('manageStatusText');
                    const manageCancelBtn = document.getElementById('manageCancelBtn');
                    const sk = statusKey.replace(/\s+/g, '');
                    if (managePill) managePill.className = 'mm-status mm-st-' + sk;
                    if (manageText) manageText.textContent = info.text || b.status;
                    if (manageCancelBtn) {
                        manageCancelBtn.style.display =
                            ['completed', 'cancelled'].includes(sk) ? 'none' : '';
                    }
                    // Update dates in modal
                    if (b.checkin_date || b.checkout_date) {
                        const ci = b.checkin_date || wrap.dataset.checkin;
                        const co = b.checkout_date || wrap.dataset.checkout;
                        const n = _dashNights(ci, co);
                        const mCI = document.getElementById('manageCheckin');
                        const mCO = document.getElementById('manageCheckout');
                        const mN = document.getElementById('manageNights');
                        if (mCI) mCI.textContent = _dashFmtDate(ci);
                        if (mCO) mCO.textContent = _dashFmtDate(co);
                        if (mN) mN.textContent = n + ' night' + (n !== 1 ? 's' : '');
                    }
                }
            }

            // Hide banner when cancelled or completed (smooth collapse)
            if (['cancelled', 'completed'].includes(b.status)) {
                wrap.style.transition = 'opacity 0.5s, max-height 0.7s ease, margin 0.7s, padding 0.7s';
                wrap.style.overflow = 'hidden';
                wrap.style.opacity = '0';
                setTimeout(() => {
                    wrap.style.maxHeight = '0';
                    wrap.style.marginTop = '0';
                    wrap.style.marginBottom = '0';
                    wrap.style.paddingTop = '0';
                    wrap.style.paddingBottom = '0';
                }, 520);
                setTimeout(() => { wrap.style.display = 'none'; }, 1300);
            }

            // Toast (deduplicated)
            if (typeof showToast === 'function' &&
                _lastBookingToastState[bookingKey] !== statusKey &&
                seen[bookingKey] !== statusKey) {
                _lastBookingToastState[bookingKey] = statusKey;
                seen[bookingKey] = statusKey;
                _setSeenBookingStatusMap(seen);
                showToast('Your booking is now: ' + (info.text || b.status),
                    b.status === 'cancelled' ? 'warning' : 'success');
            } else {
                _lastBookingToastState[bookingKey] = statusKey;
            }
        }

        // ── B. Booking history list items ────────────────────────
        const histItem = document.querySelector(`.history-item[data-booking-id="${id}"]`);
        if (histItem) {
            // Status badge
            const hsBadge = histItem.querySelector('[data-field="status"]');
            if (hsBadge && hsBadge.dataset.rawStatus !== b.status) {
                const info = _dashStatusMap[b.status] || { text: b.status, cls: '' };
                hsBadge.textContent = info.text;
                hsBadge.className = 'history-status ' + info.cls;
                hsBadge.dataset.rawStatus = b.status;
            }
            // Dates
            if (b.checkin_date) {
                const el = histItem.querySelector('[data-field="checkin"]');
                if (el) el.textContent = _dashFmtDate(b.checkin_date);
                histItem.dataset.checkin = b.checkin_date;
            }
            if (b.checkout_date) {
                const el = histItem.querySelector('[data-field="checkout"]');
                if (el) el.textContent = _dashFmtDate(b.checkout_date);
                histItem.dataset.checkout = b.checkout_date;
            }
            if (b.checkin_date || b.checkout_date) {
                const ci = b.checkin_date || histItem.dataset.checkin;
                const co = b.checkout_date || histItem.dataset.checkout;
                const n = _dashNights(ci, co);
                const nEl = histItem.querySelector('[data-field="nights"]');
                if (nEl) nEl.textContent = n + ' night' + (n !== 1 ? 's' : '');
            }
            // Price
            if (b.total_amount) {
                const pEl = histItem.querySelector('[data-field="price"]');
                if (pEl) pEl.textContent = '₱' + Number(b.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 0 });
            }
        }
    });
});
window.addEventListener('ps:booking_stats', e => {
    const s = e.detail;
    const pending = (parseInt(s.pending) || 0) + (parseInt(s.upcoming) || 0);
    document.querySelectorAll('.sidebar-item-badge').forEach(el => { el.textContent = pending || 0; });
    const ustatNum = document.querySelector('.ustat-num');
    if (ustatNum) ustatNum.textContent = parseInt(s.total) || 0;
});
window.addEventListener('ps:unit_ratings', e => {
    const ratings = Array.isArray(e.detail) ? e.detail : [];
    ratings.forEach(r => {
        const unitId = Number(r.unit_id);
        if (!unitId) return;
        const ratingWrap = document.querySelector(`.room-rating[data-rating-unit-id="${unitId}"]`);
        if (!ratingWrap) return;
        const textEl = ratingWrap.querySelector('[data-rating-value]') || ratingWrap;
        const hasRating = r.avg_rating !== null && r.avg_rating !== '' && !Number.isNaN(Number(r.avg_rating));
        if (hasRating) {
            let star = ratingWrap.querySelector('svg');
            if (!star) {
                star = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                star.setAttribute('viewBox', '0 0 24 24');
                star.innerHTML = '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>';
                ratingWrap.prepend(star);
            }
            textEl.textContent = Number(r.avg_rating).toFixed(1);
        } else {
            const star = ratingWrap.querySelector('svg');
            if (star) star.remove();
            textEl.textContent = 'No ratings yet.';
        }
    });

    // ── After booking from dashboard booking modal ─────────────────
    function _onBookingDoneFromDashboard() {
        if (!window._lastBmBookingData) return;
        const data = window._lastBmBookingData;
        // Delegate to _onBookingSuccess in script.js if available
        if (typeof _onBookingSuccess === 'function') {
            _onBookingSuccess(data);
        } else {
            // Fallback: update banner directly
            const banner = document.getElementById('rt-active-booking-wrap');
            if (banner && data.booking_id) {
                banner.style.display = '';
                banner.dataset.bookingId = data.booking_id;
                const pill = document.getElementById('rt-active-booking-status');
                if (pill) { pill.textContent = 'Pending'; pill.className = 'bb-status st-pending'; }
            }
        }
        window._lastBmBookingData = null;
    }
});

/* ══════════════════════════════════════════════
           MULTI-STEP BOOKING MODAL — integrated into PropSight dashboard
        ══════════════════════════════════════════════ */

let bmCurrentStep = 1;
let bmRoomData = {};
let bmSelectedMethod = 'gcash';
let bmTimerInterval = null;
let bmBookingId = null;

const bmMethodMeta = {
    gcash: { title: 'Pay via GCash', sub: 'Redirecting you to PayMongo to complete payment via GCash...' },
    maya: { title: 'Pay via Maya', sub: 'Redirecting you to PayMongo to complete payment via Maya...' },
    bank: { title: 'Pay via Bank Transfer', sub: 'Redirecting you to PayMongo to complete payment via Bank Transfer...' },
};

function openBookingModal(room) {
    if (window.hasActiveBooking) {
        if (typeof showToast === 'function') showToast('You already have an active booking.', 'warning');
        return;
    }

    bmRoomData = room;
    bmCurrentStep = 1;
    bmSelectedMethod = 'gcash';

    // Pre-fill name/email/phone from session if available (injected below)
    const sf = window._psSessionFields || {};
    if (sf.fname) document.getElementById('bm-fname').value = sf.fname;
    if (sf.lname) document.getElementById('bm-lname').value = sf.lname;
    if (sf.email) document.getElementById('bm-email').value = sf.email;
    if (sf.phone) document.getElementById('bm-phone').value = sf.phone;

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dayAfter = new Date();
    dayAfter.setDate(dayAfter.getDate() + 2);

    document.getElementById('bm-checkin').value = tomorrow.toISOString().split('T')[0];
    document.getElementById('bm-checkin').min = tomorrow.toISOString().split('T')[0];
    document.getElementById('bm-lease').value = dayAfter.toISOString().split('T')[0];
    document.getElementById('bm-lease').min = dayAfter.toISOString().split('T')[0];

    /* Keep checkout min in sync when checkin changes */
    document.getElementById('bm-checkin').onchange = function () {
        const ci = new Date(this.value + 'T12:00');
        ci.setDate(ci.getDate() + 1);
        const minCo = ci.toISOString().split('T')[0];
        const coEl = document.getElementById('bm-lease');
        coEl.min = minCo;
        if (coEl.value <= this.value) coEl.value = minCo;
        bmUpdateSummary();
    };

    // Sidebar unit info
    document.getElementById('bmSbName').textContent = room.name;
    document.getElementById('bmSbLoc').textContent = room.location;
    const img = document.getElementById('bmUnitImg');
    if (room.image) { img.src = room.image; img.style.display = 'block'; }
    else { img.style.display = 'none'; }

    bmUpdateSummary();
    bmRenderStep(1);

    document.getElementById('bmOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    bmStartTimer(30 * 60);
}

function closeBookingModal() {
    document.getElementById('bmOverlay').classList.remove('open');
    document.body.style.overflow = '';
    clearInterval(bmTimerInterval);
}

function bmGetTotal() {
    const priceNum = Number(bmRoomData.priceNum) || 0;
    const checkin = document.getElementById('bm-checkin')?.value || '';
    const checkout = document.getElementById('bm-lease')?.value || '';
    let nights = 0;
    if (checkin && checkout && checkout > checkin) {
        nights = Math.round((new Date(checkout) - new Date(checkin)) / 86400000);
    }
    const stayTotal = priceNum * nights;
    const deposit = stayTotal * 0.5;
    return { priceNum, nights, stayTotal, deposit, total: deposit };
}

function bmFmt(n) {
    return '₱' + Number(n).toLocaleString();
}

function bmUpdateSummary() {
    const { priceNum, nights, stayTotal, deposit, total } = bmGetTotal();
    document.getElementById('sb-rent').textContent = bmFmt(priceNum) + (nights > 0 ? ' × ' + nights + ' night' + (nights !== 1 ? 's' : '') + ' = ' + bmFmt(stayTotal) : '');
    document.getElementById('sb-deposit').textContent = bmFmt(deposit);
    document.getElementById('sb-total').textContent = bmFmt(total);
}

document.getElementById('bm-lease')?.addEventListener('change', bmUpdateSummary);
document.getElementById('bm-checkin')?.addEventListener('change', bmUpdateSummary);

function bmRenderStep(step) {
    bmCurrentStep = step;

    for (let i = 1; i <= 4; i++) {
        const s = document.getElementById('bm-step-' + i);
        const c = document.getElementById('bm-circle-' + i);
        s.classList.remove('active', 'done');
        if (i < step) { s.classList.add('done'); c.innerHTML = '✓'; }
        else if (i === step) { s.classList.add('active'); c.textContent = i; }
        else { c.textContent = i; }
    }

    for (let i = 1; i <= 4; i++) {
        document.getElementById('bm-panel-' + i).classList.toggle('active', i === step);
    }

    const back = document.getElementById('bmBack');
    const next = document.getElementById('bmNext');
    const cfm = document.getElementById('bmConfirmBtn');
    const done = document.getElementById('bmDoneBtn');

    back.style.display = (step > 1 && step < 4) ? '' : 'none';
    next.style.display = step < 3 ? '' : 'none';
    cfm.style.display = step === 3 ? '' : 'none';
    done.style.display = 'none';

    if (step === 2) bmPopulateReview();
    if (step === 3) bmInitPayment();
}

function bmNextStep() {
    if (bmCurrentStep === 1 && !bmValidateStep1()) return;
    if (bmCurrentStep < 4) bmRenderStep(bmCurrentStep + 1);
}
function bmPrevStep() {
    if (bmCurrentStep > 1) bmRenderStep(bmCurrentStep - 1);
}

function bmValidateStep1() {
    let ok = true;
    ['bm-fname', 'bm-lname', 'bm-email', 'bm-phone', 'bm-checkin'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (!el.value.trim()) { el.classList.add('error'); ok = false; }
        else el.classList.remove('error');
    });
    const coEl = document.getElementById('bm-lease');
    const ciEl = document.getElementById('bm-checkin');
    if (coEl && ciEl && (!coEl.value || coEl.value <= ciEl.value)) {
        coEl.classList.add('error'); ok = false;
    } else if (coEl) coEl.classList.remove('error');
    if (!ok) {
        const btn = document.getElementById('bmNext');
        btn.style.animation = 'none';
        setTimeout(() => { btn.style.animation = 'bmShake .35s ease'; }, 10);
    }
    return ok;
}

function bmPopulateReview() {
    const fname = document.getElementById('bm-fname').value.trim();
    const lname = document.getElementById('bm-lname').value.trim();
    const email = document.getElementById('bm-email').value.trim();
    const phone = document.getElementById('bm-phone').value.trim();
    const checkin = document.getElementById('bm-checkin').value;
    const checkout = document.getElementById('bm-lease').value;
    const { priceNum, nights, deposit, total } = bmGetTotal();

    const fmtDate = d => d ? new Date(d + 'T12:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';

    document.getElementById('rv-name').textContent = fname + ' ' + lname;
    document.getElementById('rv-email').textContent = email;
    document.getElementById('rv-phone').textContent = phone;
    document.getElementById('rv-unit').textContent = bmRoomData.name;
    document.getElementById('rv-movein').textContent = fmtDate(checkin);
    document.getElementById('rv-checkout').textContent = fmtDate(checkout);
    document.getElementById('rv-nights').textContent = nights > 0 ? nights + ' night' + (nights !== 1 ? 's' : '') : '—';
    document.getElementById('rv-rent').textContent = bmFmt(priceNum);
    document.getElementById('rv-deposit').textContent = bmFmt(deposit);
    document.getElementById('rv-total').textContent = bmFmt(total);
}

function bmInitPayment() {
    document.querySelectorAll('.bm-pay-option').forEach(opt => {
        // Remove old listeners by cloning
        const fresh = opt.cloneNode(true);
        opt.parentNode.replaceChild(fresh, opt);
    });
    document.querySelectorAll('.bm-pay-option').forEach(opt => {
        if (opt.dataset.method === bmSelectedMethod) opt.classList.add('selected');
        else opt.classList.remove('selected');
        opt.addEventListener('click', () => {
            document.querySelectorAll('.bm-pay-option').forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            bmSelectedMethod = opt.dataset.method;
            bmUpdatePaymentUI();
        });
    });
    bmUpdatePaymentUI();
}

function bmUpdatePaymentUI() {
    const { total } = bmGetTotal();
    const qrBox = document.getElementById('bmQrBox');
    const cashBox = document.getElementById('bmCashBox');
    if (bmSelectedMethod === 'cash') {
        qrBox.style.display = 'none';
        cashBox.style.display = '';
        document.getElementById('bmCashAmount').textContent = bmFmt(total);
    } else {
        qrBox.style.display = '';
        cashBox.style.display = 'none';
        const meta = bmMethodMeta[bmSelectedMethod];
        document.getElementById('bmQrTitle').textContent = meta.title;
        document.getElementById('bmQrSub').innerHTML = meta.sub;
        document.getElementById('bmQrAmount').textContent = bmFmt(total);
    }
}

function bmStartTimer(seconds) {
    clearInterval(bmTimerInterval);
    let remaining = seconds;
    const tick = () => {
        const m = String(Math.floor(remaining / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        const el = document.getElementById('bmCountdown');
        if (el) el.textContent = m + ':' + s;
        if (remaining <= 0) clearInterval(bmTimerInterval);
        remaining--;
    };
    tick();
    bmTimerInterval = setInterval(tick, 1000);
}

function bmSubmitBooking() {
    const btn = document.getElementById('bmConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" style="width:14px;height:14px;stroke-width:2"><polyline points="20 6 9 17 4 12"/></svg> Submitting…';

    const checkin = document.getElementById('bm-checkin').value;
    const checkout = document.getElementById('bm-lease').value;

    const payload = {
        unit_id: bmRoomData.id,
        checkin,
        checkout,
        guests: 2,
        payment_method: bmSelectedMethod,
        first_name: document.getElementById('bm-fname').value.trim(),
        last_name: document.getElementById('bm-lname').value.trim(),
        email: document.getElementById('bm-email').value.trim(),
        phone: document.getElementById('bm-phone').value.trim(),
        csrf_token: (typeof window.psGetCsrfToken === 'function' ? window.psGetCsrfToken() : ''),
    };

    fetch('../../api/user/book_unit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload)
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bmOnBookingSuccess(data);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" style="width:14px;height:14px;stroke-width:2"><polyline points="20 6 9 17 4 12"/></svg> Confirm Payment';
                if (typeof showToast === 'function') showToast(data.message || 'Booking failed. Please try again.', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" style="width:14px;height:14px;stroke-width:2"><polyline points="20 6 9 17 4 12"/></svg> Confirm Payment';
            if (typeof showToast === 'function') showToast('Connection error. Please try again.', 'error');
        });
}

let _bmPaymongoTab = null;
let _bmPaymongoUrl = null;
let _bmPollInterval = null;

function bmReopenPaymongoTab() {
    if (_bmPaymongoUrl) {
        _bmPaymongoTab = window.open(_bmPaymongoUrl, '_blank');
    }
}

function bmStartPaymentPolling(bookingId) {
    let attempts = 0;
    const maxAttempts = 120; // 10 minutes at 5s intervals

    document.getElementById('bmReopenPayBtn').style.display = 'inline-flex';

    _bmPollInterval = setInterval(() => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(_bmPollInterval);
            document.getElementById('bmPaymentPolling').innerHTML =
                '<span style="color:var(--red,#ef4444);">Payment timeout. Please check your bookings page.</span>';
            document.getElementById('bmDoneBtn').style.display = 'inline-flex';
            return;
        }

        fetch('../../api/user/check_payment_status.php?booking_id=' + bookingId)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.payment_status === 'paid') {
                    clearInterval(_bmPollInterval);
                    if (_bmPaymongoTab && !_bmPaymongoTab.closed) {
                        try { _bmPaymongoTab.close(); } catch (e) { }
                    }
                    // Show success state
                    document.getElementById('bm-payment-waiting').style.display = 'none';
                    document.getElementById('bm-payment-success').style.display = '';
                    document.getElementById('bmDoneBtn').style.display = 'inline-flex';
                    window.hasActiveBooking = true;
                }
            })
            .catch(() => { }); // silent — keep polling
    }, 5000);
}

function bmOnBookingSuccess(data) {
    clearInterval(bmTimerInterval);
    bmBookingId = data.booking_id;

    const checkin = document.getElementById('bm-checkin').value;
    const checkout = document.getElementById('bm-lease').value;
    const { total } = bmGetTotal();
    const methodLabels = { gcash: 'GCash', maya: 'Maya', bank: 'Bank Transfer', cash: 'Cash (On-site)' };
    const fmtDate = d => d ? new Date(d + 'T12:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';

    document.getElementById('bmConfirmRef').textContent = 'Ref #BK-' + String(data.booking_id).padStart(4, '0');

    // Hide all sub-states initially
    document.getElementById('bm-payment-waiting').style.display = 'none';
    document.getElementById('bm-payment-success').style.display = 'none';
    document.getElementById('bm-payment-cash').style.display = 'none';

    if (['gcash', 'maya', 'bank'].includes(bmSelectedMethod)) {
        // Online payment — show waiting state, open PayMongo tab, poll
        document.getElementById('bm-payment-waiting').style.display = '';
        bmRenderStep(4);
        window._lastBmBookingData = data;

        const csrf = (typeof window.psGetCsrfToken === 'function' ? window.psGetCsrfToken() : '');
        fetch('../../api/user/create_paymongo_link.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ booking_id: data.booking_id, csrf_token: csrf })
        })
            .then(r => r.json())
            .then(pm => {
                if (pm.success) {
                    _bmPaymongoUrl = pm.checkout_url;
                    _bmPaymongoTab = window.open(pm.checkout_url, '_blank');
                    bmStartPaymentPolling(data.booking_id);
                } else {
                    document.getElementById('bmPaymentPolling').innerHTML =
                        '<span style="color:var(--red,#ef4444);">Failed to create payment link: ' + (pm.message || 'Unknown error') + '</span>';
                    document.getElementById('bmDoneBtn').style.display = 'inline-flex';
                }
            })
            .catch(() => {
                document.getElementById('bmPaymentPolling').innerHTML =
                    '<span style="color:var(--red,#ef4444);">Could not reach payment service. Please try from your bookings page.</span>';
                document.getElementById('bmDoneBtn').style.display = 'inline-flex';
            });
    } else {
        // Cash — show cash confirmation state
        document.getElementById('bm-payment-cash').style.display = '';
        document.getElementById('cf-unit-cash').textContent = bmRoomData.name;
        document.getElementById('cf-movein-cash').textContent = fmtDate(checkin);
        document.getElementById('cf-checkout-cash').textContent = fmtDate(checkout);
        document.getElementById('cf-method-cash').textContent = methodLabels[bmSelectedMethod] || bmSelectedMethod;
        document.getElementById('cf-total-cash').textContent = bmFmt(total);
        bmRenderStep(4);
        window.hasActiveBooking = true;
        window._lastBmBookingData = data;
        document.getElementById('bmDoneBtn').style.display = 'inline-flex';
    }

    // Populate success state details (for when polling confirms)
    document.getElementById('cf-unit').textContent = bmRoomData.name;
    document.getElementById('cf-movein').textContent = fmtDate(checkin);
    document.getElementById('cf-checkout').textContent = fmtDate(checkout);
    document.getElementById('cf-method').textContent = methodLabels[bmSelectedMethod] || bmSelectedMethod;
    document.getElementById('cf-total').textContent = bmFmt(total);
}

// ── Override confirmBooking() to use the new multi-step modal ──
function confirmBooking() {
    const checkin = document.getElementById('modalCheckin').value;
    const checkout = document.getElementById('modalGuests').value;

    if (!checkin) { if (typeof showToast === 'function') showToast('Please select a check-in date.', 'warning'); return; }
    if (!checkout || checkout <= checkin) { if (typeof showToast === 'function') showToast('Please select a valid check-out date.', 'warning'); return; }
    if (new Date(checkin) < new Date(new Date().toDateString())) {
        if (typeof showToast === 'function') showToast('Check-in date cannot be in the past.', 'warning'); return;
    }
    if (window.hasActiveBooking) {
        if (typeof showToast === 'function') showToast('You already have an active booking.', 'warning'); return;
    }

    const unitId = document.getElementById('roomModal').dataset.unitId;
    const priceNum = Number(document.getElementById('roomModal').dataset.pricePerNight || 0);
    const roomName = document.getElementById('modalRoomName').textContent;
    const roomLoc = document.getElementById('modalRoomLoc').textContent;
    const roomImg = (window._pdGalleryImages && window._pdGalleryImages[0]) || '';
    const roomView = (document.getElementById('pdHeroBadge')?.textContent || 'Standard').split('·')[0].trim();

    closeRoomModal();

    openBookingModal({
        id: unitId,
        name: roomName,
        location: roomLoc,
        price: bmFmt(priceNum),
        priceNum: priceNum,
        image: roomImg,
        view: roomView,
    });

    // Pre-fill both check-in AND check-out from the property details modal
    setTimeout(() => {
        const ciEl = document.getElementById('bm-checkin');
        const coEl = document.getElementById('bm-lease');
        ciEl.value = checkin;
        ciEl.min = checkin;
        const minCo = (() => { const d = new Date(checkin + 'T12:00'); d.setDate(d.getDate() + 1); return d.toISOString().split('T')[0]; })();
        coEl.min = minCo;
        coEl.value = checkout; // use the date the user actually picked
        bmUpdateSummary();
    }, 50);
}

// Close on backdrop click / Escape
document.getElementById('bmOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('bmOverlay')) closeBookingModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('bmOverlay').classList.contains('open')) closeBookingModal();
});

(function () {
    /* Leaflet map — initialised once, re-centred on each modal open */
    let _map = null;
    let _marker = null;

    function showMapOverlay(show) {
        const ov = document.getElementById('pdMapLoadingOverlay');
        if (ov) ov.style.display = show ? 'flex' : 'none';
    }

    function initMap(lat, lng, label) {
        const container = document.getElementById('pdLeafletMap');
        if (!container) return;

        if (!_map) {
            _map = L.map('pdLeafletMap', {
                zoomControl: true,
                scrollWheelZoom: false,
                attributionControl: true,
            }).setView([lat, lng], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19,
            }).addTo(_map);
        } else {
            _map.setView([lat, lng], 15);
        }

        /* Custom pin styled to match PropSight's terracotta palette */
        const pinHtml = `
      <div style="
        background:#c9623f;
        width:34px;height:34px;
        border-radius:50% 50% 50% 0;
        transform:rotate(-45deg);
        border:3px solid #fff;
        box-shadow:0 3px 12px rgba(0,0,0,.25);
        display:flex;align-items:center;justify-content:center;
      ">
        <svg viewBox="0 0 24 24" fill="#fff" style="width:16px;height:16px;transform:rotate(45deg)">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/>
        </svg>
      </div>`;

        const icon = L.divIcon({
            html: pinHtml,
            className: '',
            iconSize: [34, 34],
            iconAnchor: [17, 34],
            popupAnchor: [0, -36],
        });

        if (_marker) {
            _marker.setLatLng([lat, lng]);
        } else {
            _marker = L.marker([lat, lng], { icon }).addTo(_map);
        }

        _marker.bindPopup(`<strong style="font-family:serif;font-size:.95rem;">${label}</strong>`, { closeButton: false }).openPopup();

        /* Force map to recalculate size after modal layout settles */
        setTimeout(() => { if (_map) _map.invalidateSize(); }, 200);
        showMapOverlay(false);
    }

    async function geocodeAndShow(address, label) {
        if (!address) { showMapOverlay(false); return; }
        showMapOverlay(true);

        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(address);
            const res = await fetch(url, { headers: { 'Accept-Language': 'en' } });
            const data = await res.json();

            if (data && data.length > 0) {
                initMap(parseFloat(data[0].lat), parseFloat(data[0].lon), label);
            } else {
                /* Fallback: try just the city/location label */
                const cityUrl = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(label + ', Philippines');
                const cityRes = await fetch(cityUrl, { headers: { 'Accept-Language': 'en' } });
                const cityData = await cityRes.json();
                if (cityData && cityData.length > 0) {
                    initMap(parseFloat(cityData[0].lat), parseFloat(cityData[0].lon), label);
                } else {
                    showMapOverlay(false);
                }
            }
        } catch (e) {
            showMapOverlay(false);
            console.warn('Map geocode error:', e);
        }
    }

    /* Patch openRoomModal — works whether load has fired or not */
    function patchOpenRoomModal() {
        const _origOpen = window.openRoomModal;
        if (typeof _origOpen !== 'function') return;

        window.openRoomModal = function (room) {
            _origOpen.call(this, room);

            /* Build best possible address string for geocoding */
            const addr = [room.address, room.city, 'Philippines']
                .filter(Boolean)
                .map(s => s.trim())
                .filter(Boolean)
                .join(', ');

            const label = room.location || room.name || 'Property';

            /* Slight delay so the modal panel is visible before map renders */
            setTimeout(() => geocodeAndShow(addr || label, label), 150);
        };
    }

    /* Try immediately (script.js may already be parsed), then on DOMContentLoaded as fallback */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', patchOpenRoomModal);
    } else {
        patchOpenRoomModal();
    }
})();
