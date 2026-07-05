showToast("You do not have permission to access this page.", "error", "Unauthorized"); setTimeout(() => history.back(), 2000);

/* ══════════════════════════════════════════════
           MULTI-STEP BOOKING MODAL — integrated into PropSight dashboard
        ══════════════════════════════════════════════ */

        let bmCurrentStep = 1;
        let bmRoomData = {};
        let bmSelectedMethod = 'gcash';
        let bmTimerInterval = null;
        let bmBookingId = null;

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
            done.style.display = step === 4 ? '' : 'none';

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
                    // Show card form only for Card method
                    const cardForm = document.getElementById('bmCardForm');
                    if (cardForm) cardForm.style.display = bmSelectedMethod === 'Card' ? '' : 'none';
                    bmUpdatePaymentUI();
                });
            });
            // Reset card form visibility
            const cardForm = document.getElementById('bmCardForm');
            if (cardForm) cardForm.style.display = bmSelectedMethod === 'Card' ? '' : 'none';
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
            } else if (bmSelectedMethod === 'Card') {
                qrBox.style.display = 'none';
                cashBox.style.display = 'none';
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

            fetch('../../endpoints/user/book_unit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(payload)
            })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        btn.disabled = false;
                        btn.innerHTML = '<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" style="width:14px;height:14px;stroke-width:2"><polyline points="20 6 9 17 4 12"/></svg> Confirm Payment';
                        if (typeof showToast === 'function') showToast(data.message || 'Booking failed. Please try again.', 'error');
                        return;
                    }

                    const bid = data.booking_id;
                    const { total } = bmGetTotal();

                    // ── Card: Payment Intent flow ─────────────────────────────────
                    if (bmSelectedMethod === 'Card') {
                        const cardNum  = (document.getElementById('bmCardNumber')?.value || '').replace(/\s/g, '');
                        const expMonth = parseInt(document.getElementById('bmCardExpMonth')?.value || '0');
                        const expYear  = parseInt(document.getElementById('bmCardExpYear')?.value || '0');
                        const cvc      = (document.getElementById('bmCardCvc')?.value || '').trim();
                        const holder   = (document.getElementById('bmCardHolder')?.value || '').trim();
                        const cardErrEl = document.getElementById('bmCardError');

                        function _showCardErr(msg) {
                            if (cardErrEl) { cardErrEl.textContent = msg; cardErrEl.style.display = ''; }
                            btn.disabled = false;
                            btn.innerHTML = '<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" style="width:14px;height:14px;stroke-width:2"><polyline points="20 6 9 17 4 12"/></svg> Confirm Payment';
                            if (typeof showToast === 'function') showToast(msg, 'error');
                        }

                        if (!cardNum || cardNum.length < 13) { _showCardErr('Please enter a valid card number.'); return; }
                        if (!expMonth || !expYear)           { _showCardErr('Please enter the expiry date.'); return; }
                        if (!cvc)                            { _showCardErr('Please enter the CVC.'); return; }
                        if (!holder)                         { _showCardErr('Cardholder name is required.'); return; }

                        const cardFd = new FormData();
                        cardFd.append('booking_id',  bid);
                        cardFd.append('card_number', cardNum);
                        cardFd.append('exp_month',   expMonth);
                        cardFd.append('exp_year',    expYear);
                        cardFd.append('cvc',         cvc);
                        cardFd.append('holder_name', holder);
                        cardFd.append('return_url',  window.location.href);
                        cardFd.append('csrf_token',  typeof window.psGetCsrfToken === 'function' ? window.psGetCsrfToken() : '');

                        fetch('../../api/user/create_card_payment.php', { method: 'POST', body: cardFd })
                            .then(r => r.json())
                            .then(cd => {
                                if (!cd.success) { _showCardErr(cd.message || 'Card payment failed.'); return; }
                                const intentId = cd.intent_id;

                                // If 3DS required open redirect in new tab
                                if (cd.intent_status === 'awaiting_next_action' && cd.redirect_url) {
                                    window.open(cd.redirect_url, '_blank');
                                }

                                // Show waiting state on step 4
                                bmRenderStep(4);
                                const waitEl = document.getElementById('bm-payment-waiting');
                                if (waitEl) waitEl.style.display = '';

                                // Poll until paid/failed
                                let polls = 0;
                                const pollIv = setInterval(() => {
                                    if (++polls > 72) {
                                        clearInterval(pollIv);
                                        const expEl = document.getElementById('bm-payment-expired');
                                        if (expEl) { document.getElementById('bm-payment-waiting').style.display = 'none'; expEl.style.display = ''; }
                                        return;
                                    }
                                    fetch(`../../api/user/check_card_payment_status.php?booking_id=${bid}&intent_id=${encodeURIComponent(intentId)}`)
                                        .then(r => r.json())
                                        .then(st => {
                                            if (st.payment_status === 'paid' || st.booking_status === 'confirmed') {
                                                clearInterval(pollIv);
                                                bmOnCardPaySuccess(data, total);
                                            } else if (st.payment_status === 'failed' || st.booking_status === 'cancelled') {
                                                clearInterval(pollIv);
                                                const failEl = document.getElementById('bm-payment-failed');
                                                if (failEl) { document.getElementById('bm-payment-waiting').style.display = 'none'; failEl.style.display = ''; }
                                            }
                                        }).catch(() => {});
                                }, 5000);
                            })
                            .catch(() => { _showCardErr('Could not reach payment service.'); });
                        return;
                    }

                    // ── All other methods: direct booking success ─────────────────
                    bmOnBookingSuccess(data);
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<svg viewBox="0 0 24 24" stroke="currentColor" fill="none" style="width:14px;height:14px;stroke-width:2"><polyline points="20 6 9 17 4 12"/></svg> Confirm Payment';
                    if (typeof showToast === 'function') showToast('Connection error. Please try again.', 'error');
                });
        }

        function bmOnCardPaySuccess(data, total) {
            clearInterval(bmTimerInterval);
            bmBookingId = data.booking_id;
            const checkin  = document.getElementById('bm-checkin').value;
            const checkout = document.getElementById('bm-lease').value;
            const fmtDate  = d => d ? new Date(d + 'T12:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';
            document.getElementById('bmConfirmRef').textContent = 'Ref #BK-' + String(data.booking_id).padStart(4, '0');
            const waitEl = document.getElementById('bm-payment-waiting');
            const succEl = document.getElementById('bm-payment-success');
            if (waitEl) waitEl.style.display = 'none';
            if (succEl) succEl.style.display = '';
            document.getElementById('cf-unit').textContent    = bmRoomData.name;
            document.getElementById('cf-movein').textContent  = fmtDate(checkin);
            document.getElementById('cf-checkout').textContent = fmtDate(checkout);
            document.getElementById('cf-method').textContent  = 'Credit / Debit Card';
            document.getElementById('cf-total').textContent   = bmFmt(total);
            const doneBtn = document.getElementById('bmDoneBtn');
            if (doneBtn) doneBtn.style.display = '';
            window.hasActiveBooking = true;
            window._lastBmBookingData = data;
            if (typeof showToast === 'function') showToast('Payment confirmed!');
        }

        function bmOnBookingSuccess(data) {
            clearInterval(bmTimerInterval);
            bmBookingId = data.booking_id;

            const checkin = document.getElementById('bm-checkin').value;
            const checkout = document.getElementById('bm-lease').value;
            const { total } = bmGetTotal();
            const methodLabels = { gcash: 'GCash', maya: 'Maya', bank: 'Bank Transfer', cash: 'Cash (On-site)', Card: 'Credit / Debit Card' };

            const fmtDate = d => d ? new Date(d + 'T12:00').toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';

            document.getElementById('bmConfirmRef').textContent = 'Ref #BK-' + String(data.booking_id).padStart(4, '0');
            document.getElementById('cf-unit').textContent = bmRoomData.name;
            document.getElementById('cf-movein').textContent = fmtDate(checkin);
            document.getElementById('cf-checkout').textContent = fmtDate(checkout);
            document.getElementById('cf-method').textContent = methodLabels[bmSelectedMethod] || bmSelectedMethod;
            document.getElementById('cf-total').textContent = bmFmt(total);

            bmRenderStep(4);
            window.hasActiveBooking = true;
            window._lastBmBookingData = data; // Store for Done button handler
        }

    // Close on backdrop click or Escape
    document.getElementById('bmOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeBookingModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeBookingModal();
    });

function removeSaved(savedId, unitId, btn) {
        const fd = new FormData();
        fd.append('action', 'remove');
        fd.append('unit_id', unitId);
        if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
        fetch('../../endpoints/user/saved.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json()).then(d => {
                if (d.success) {
                    const card = btn.closest('.saved-card');
                    card.style.opacity = '0';
                    card.style.transition = 'opacity .3s';
                    setTimeout(() => {
                        card.remove();
                        const count = document.querySelectorAll('.saved-card').length;
                        const el = document.querySelector('.saved-count strong');
                        if (el) el.textContent = count;
                    }, 300);
                    showToast('Removed from wishlist.');
                } else showToast(d.message || 'Error', true);
            }).catch(() => showToast('Network error.', true));
    }

window.PS_RT_PAGE = 'saved';

// Live-update saved count in header when realtime pushes user_metrics
window.addEventListener('ps:user_metrics', function(e) {
    const saved = parseInt((e.detail || {}).saved_count, 10);
    if (!isNaN(saved)) {
        const el = document.querySelector('.saved-count strong, [data-rt-user="saved_count"]');
        if (el) el.textContent = saved;
    }
});

function _onBookingDoneFromSaved() {
    // After booking from saved page, update the booking modal trigger button
    // and the "Book Now" button on the saved unit card to reflect pending state
    if (window._lastBmBookingData) {
        const data = window._lastBmBookingData;
        const unitId = String(data.unit_id || data.unitId || '');

        // Flash the saved card to show booking was placed
        if (unitId) {
            const card = document.querySelector(`[data-unit-id="${unitId}"]`);
            if (card) {
                const bookBtn = card.querySelector('.book-btn, .bm-trigger, [data-action="book"]');
                if (bookBtn) {
                    bookBtn.textContent = 'Booking Pending…';
                    bookBtn.disabled = true;
                    bookBtn.style.opacity = '0.7';
                }
                card.style.transition = 'outline 0.3s';
                card.style.outline = '2px solid #86efac';
                setTimeout(() => { card.style.outline = ''; }, 2500);
            }
        }

        // Update stat counter
        document.querySelectorAll('[data-rt-stat="upcoming"], [data-rt-user="upcoming"]').forEach(el => {
            el.textContent = (parseInt(el.textContent) || 0) + 1;
        });

        // Toast confirmation
        if (typeof showToast === 'function') {
            const ref = data.booking_id ? `#BK-${String(data.booking_id).padStart(4,'0')}` : '';
            showToast(`Booking${ref ? ' '+ref : ''} submitted! Check your bookings page for updates.`, 'success', 'Booking Placed!', 5000);
        }

        window.hasActiveBooking = true;
        window._lastBmBookingData = null;
    }
}

// Card number auto-formatter
window.bmFormatCardNumber = window.bmFormatCardNumber || function(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 16);
    input.value = v.replace(/(.{4})/g, '$1 ').trim();
};