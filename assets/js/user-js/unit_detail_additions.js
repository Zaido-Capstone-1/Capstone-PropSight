(function () {
    'use strict';

    /* =========================================================================
    HELPERS
    ========================================================================= */
    const fmt = n => '₱' + Math.round(n).toLocaleString('en-PH');
    const $ = id => document.getElementById(id);
    const set = (id, v) => {
        const e = $(id);
        if (e) e.textContent = v;
    };
    const val = id => ($(id)?.value || '').trim();

    function getSeasonalTotal(checkIn, checkOut, baseRate, seasonality) {
        const start = new Date(checkIn);
        const end = new Date(checkOut);
        const nights = Math.round((end - start) / 86400000);
        if (nights <= 0) return {
            nights: 0,
            total: 0,
            rows: []
        };

        const buckets = {};
        for (let d = new Date(start); d < end; d.setDate(d.getDate() + 1)) {
            const m = d.getMonth();
            const mult = seasonality[m] ?? seasonality[String(m)] ?? 1.0; // ← coerce
            const key = m + '|' + mult;
            if (!buckets[key]) buckets[key] = {
                month: m,
                multiplier: mult,
                nights: 0
            };
            buckets[key].nights++;
        }

        let total = 0;
        const rows = [];
        for (const b of Object.values(buckets)) {
            const rate = Math.round(baseRate * b.multiplier);
            const subtotal = rate * b.nights;
            total += subtotal;
            rows.push({
                month: b.month,
                multiplier: b.multiplier,
                nights: b.nights,
                rate,
                subtotal
            });
        }
        return {
            nights,
            total: Math.round(total),
            rows
        };
    }

    const MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const SEASON_COLOR = {
        Peak: '#E74C3C',
        High: '#deaf37',
        Low: '#2ECC71'
    };

    /* =========================================================================
    SHARED CALENDAR HELPERS
    ========================================================================= */
    function buildCalendarHelpers() {
        const ranges = window.UD_BOOKED_RANGES || [];
        const blocked = window.UD_BLOCKED_DATES || [];

        const disabledSet = new Set();
        ranges.forEach(r => {
            const d = new Date(r.from + 'T12:00:00');
            const end = new Date(r.to + 'T12:00:00');
            while (d < end) {
                disabledSet.add(d.toISOString().split('T')[0]);
                d.setDate(d.getDate() + 1);
            }
        });
        blocked.forEach(date => disabledSet.add(date));

        function isBooked(dateObj) {
            return disabledSet.has(dateObj.toISOString().split('T')[0]);
        }

        function isCheckinBlocked(dateObj) {
            if (isBooked(dateObj)) return true;
            const next = new Date(dateObj);
            next.setDate(next.getDate() + 1);
            return isBooked(next);
        }

        function getNextBookedAfter(fromDateObj) {
            const d = new Date(fromDateObj);
            for (let i = 1; i <= 365; i++) {
                const n = new Date(d);
                n.setDate(d.getDate() + i);
                if (isBooked(n)) return n;
            }
            return null;
        }

        function nextAvailableCheckin(fromDateObj) {
            const d = new Date(fromDateObj);
            for (let i = 1; i <= 365; i++) {
                d.setDate(d.getDate() + 1);
                if (!isCheckinBlocked(d)) return new Date(d);
            }
            return new Date(fromDateObj);
        }

        return {
            isBooked,
            isCheckinBlocked,
            getNextBookedAfter,
            nextAvailableCheckin
        };
    }

    /* =========================================================================
    FLATPICKR YEAR DROPDOWN HELPER
    Call this in onReady for every flatpickr instance
    ========================================================================= */
    function injectYearDropdown(fp) {
        function tryInject() {
            const container = fp.calendarContainer;
            if (!container) {
                setTimeout(tryInject, 50);
                return;
            }

            // Don't inject twice
            if (container.querySelector('.fp-year-select')) return;

            // Hide numInputWrapper via inline style (CSS may not have fired yet)
            const wrapper = container.querySelector('.numInputWrapper');
            if (wrapper) wrapper.style.display = 'none';

            const currentYear = new Date().getFullYear();
            const sel = document.createElement('select');
            sel.className = 'fp-year-select';

            for (let y = currentYear; y <= currentYear + 5; y++) {
                const opt = document.createElement('option');
                opt.value = y;
                opt.textContent = y;
                if (y === fp.currentYear) opt.selected = true;
                sel.appendChild(opt);
            }

            sel.addEventListener('change', () => fp.changeYear(parseInt(sel.value)));

            // Insert after the month dropdown inside .flatpickr-current-month
            const currentMonth = container.querySelector('.flatpickr-current-month');
            if (currentMonth) {
                currentMonth.appendChild(sel);
            }

            // Keep in sync on navigation
            fp.config.onMonthChange.push(() => {
                sel.value = fp.currentYear;
            });
            fp.config.onYearChange.push(() => {
                sel.value = fp.currentYear;
            });
        }

        setTimeout(tryInject, 0);
    }


    /* =========================================================================
    1. GRID GALLERY
    ========================================================================= */
    (function initGridGallery() {
        const imgs = window.UD_IMAGES || [];
        const mainImg = $('udMainGalleryImg');
        if (!mainImg || imgs.length < 3) return;

        const side0 = document.querySelector('#udSideCell0 img');
        const side1 = document.querySelector('#udSideCell1 img');
        const sideCell0 = $('udSideCell0');
        const sideCell1 = $('udSideCell1');
        let cur = 0;

        function go(idx) {
            cur = ((idx % imgs.length) + imgs.length) % imgs.length;
            mainImg.src = imgs[cur];
            mainImg.onclick = () => window.openLb(cur);

            const n1 = (cur + 1) % imgs.length;
            const n2 = (cur + 2) % imgs.length;
            if (side0) side0.src = imgs[n1];
            if (side1) side1.src = imgs[n2];
            if (sideCell0) sideCell0.onclick = () => window.openLb(n1);
            if (sideCell1) sideCell1.onclick = () => window.openLb(n2);
        }

        $('udMainPrev')?.addEventListener('click', e => {
            e.stopPropagation();
            go(cur - 1);
        });
        $('udMainNext')?.addEventListener('click', e => {
            e.stopPropagation();
            go(cur + 1);
        });
    })();


    /* =========================================================================
    2. SLIDER GALLERY
    ========================================================================= */
    (function initSliderGallery() {
        const track = $('udTrack');
        const dots = $('udDots');
        if (!track) return;

        let gCur = 0;
        const gSlides = track.querySelectorAll('.ud-gallery-slide');

        function goTo(idx) {
            if (!gSlides.length) return;
            gCur = ((idx % gSlides.length) + gSlides.length) % gSlides.length;
            track.style.transform = `translateX(-${gCur * 100}%)`;
            dots?.querySelectorAll('.ud-gdot').forEach((d, i) =>
                d.classList.toggle('active', i === gCur));
        }

        $('udPrev')?.addEventListener('click', () => goTo(gCur - 1));
        $('udNext')?.addEventListener('click', () => goTo(gCur + 1));
        dots?.addEventListener('click', e => {
            const b = e.target.closest('.ud-gdot');
            if (b) goTo(+b.dataset.idx);
        });

        let tx = 0;
        track.addEventListener('touchstart', e => {
            tx = e.touches[0].clientX;
        }, {
            passive: true
        });
        track.addEventListener('touchend', e => {
            const dx = e.changedTouches[0].clientX - tx;
            if (Math.abs(dx) > 50) goTo(gCur + (dx < 0 ? 1 : -1));
        });

        window._udSliderGoTo = goTo;
        window._udSliderCur = () => gCur;
    })();


    /* =========================================================================
    3. LIGHTBOX
    ========================================================================= */
    (function initLightbox() {
        const lb = $('udLightbox');
        const lbTrack = $('udLbTrack');
        const lbCurEl = $('udLbCurrent');
        if (!lb) return;

        let lbIdx = 0;
        const lbSlides = lbTrack ? lbTrack.querySelectorAll('.ud-lb-slide') : [];

        function lbGoTo(idx) {
            if (!lbTrack || !lbSlides.length) return;
            lbIdx = ((idx % lbSlides.length) + lbSlides.length) % lbSlides.length;
            lbTrack.style.transform = `translateX(-${lbIdx * 100}%)`;
            if (lbCurEl) lbCurEl.textContent = lbIdx + 1;
        }

        function closeLb() {
            lb.classList.remove('open');
            document.body.style.overflow = '';
        }

        window.openLb = function (i) {
            if (!lb) return;
            lb.classList.add('open');
            lbGoTo(i || 0);
            document.body.style.overflow = 'hidden';
        };

        $('udLbClose')?.addEventListener('click', closeLb);
        $('udLbPrev')?.addEventListener('click', () => lbGoTo(lbIdx - 1));
        $('udLbNext')?.addEventListener('click', () => lbGoTo(lbIdx + 1));
        lb.addEventListener('click', e => {
            if (e.target === lb) closeLb();
        });

        document.addEventListener('keydown', e => {
            if (lb.classList.contains('open')) {
                if (e.key === 'Escape') closeLb();
                if (e.key === 'ArrowLeft') lbGoTo(lbIdx - 1);
                if (e.key === 'ArrowRight') lbGoTo(lbIdx + 1);
                return;
            }
            if ($('bmOverlay')?.classList.contains('active')) return;
            if (e.key === 'ArrowLeft') window._udSliderGoTo?.(window._udSliderCur?.() - 1);
            if (e.key === 'ArrowRight') window._udSliderGoTo?.(window._udSliderCur?.() + 1);
        });
    })();


    /* =========================================================================
    4. MAP (Leaflet) — inline card map + fullscreen modal map
    ========================================================================= */
    (function initMap() {
        const u = window.UD_UNIT || {};
        if (!u.lat || !u.lng || typeof L === 'undefined') return;

        const cardMapEl = $('udLeafletMap');
        if (cardMapEl) {
            const cardMap = L.map('udLeafletMap', {
                zoomControl: true,
                scrollWheelZoom: false,
                attributionControl: false,
                dragging: true,
                doubleClickZoom: true,
            }).setView([u.lat, u.lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(cardMap);
            L.marker([u.lat, u.lng]).addTo(cardMap);
            cardMap.zoomControl.setPosition('bottomright');
        }

        let modalMap = null;

        window.openMapModal = function () {
            const modal = $('udMapModal');
            if (!modal) return;
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            if (!modalMap) {
                modalMap = L.map('udMapModalMap', {
                    zoomControl: true,
                    scrollWheelZoom: true,
                    attributionControl: true,
                }).setView([u.lat, u.lng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(modalMap);
                L.marker([u.lat, u.lng]).addTo(modalMap)
                    .bindPopup(document.title.split('—')[0].trim())
                    .openPopup();
            } else {
                setTimeout(() => modalMap.invalidateSize(), 100);
            }
        };

        window.closeMapModal = function () {
            const modal = $('udMapModal');
            if (!modal) return;
            modal.classList.remove('open');
            document.body.style.overflow = '';
        };

        $('udMapModal')?.addEventListener('click', e => {
            if (e.target === $('udMapModal')) window.closeMapModal();
        });
        $('udMapModalClose')?.addEventListener('click', () => window.closeMapModal());

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && $('udMapModal')?.classList.contains('open')) {
                window.closeMapModal();
            }
        });
    })();


    /* =========================================================================
    5. CARD DATE PICKERS + PRICE BREAKDOWN
    ========================================================================= */
    (function initCardDatePickers() {
        const u = window.UD_UNIT || {};
        const ciEl = $('udCheckin');
        const coEl = $('udCheckout');

        function updateBreakdown() {
            const bd = $('udPriceBreakdown');
            if (!ciEl || !coEl || !bd) return;
            if (!ciEl.value || !coEl.value) {
                bd.style.display = 'none';
                return;
            }
            const a = new Date(ciEl.value),
                b = new Date(coEl.value);
            if (b <= a) {
                bd.style.display = 'none';
                return;
            }

            const {
                nights,
                total,
                rows
            } = getSeasonalTotal(ciEl.value, coEl.value, u.priceNum || 0, u.seasonality || {});

            // season rows
            const seasonRowsEl = $('udSeasonRows');
            if (seasonRowsEl) {
                seasonRowsEl.innerHTML = rows.map(r =>
                    `<div class="ud-pb-row">
                            <span>${MONTH_SHORT[r.month]} · ${r.nights} night${r.nights !== 1 ? 's' : ''} × ${fmt(r.rate)}</span>
                            <span>${fmt(r.subtotal)}</span>
                        </div>`
                ).join('');
            }

            // demand badge for the check-in month
            const checkInMonth = new Date(ciEl.value).getMonth();
            const label = u.seasonLabel?. [checkInMonth] || '';
            const color = SEASON_COLOR[label] || '#6b7280';
            const badgeEl = $('udDemandBadge');
            if (badgeEl) badgeEl.innerHTML = label ?
                `<span style="background:${color}20;color:${color};padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700">${label} Season</span>` :
                '';

            set('udTotalDue', fmt(total));
            bd.style.display = 'block';

            const fd = $('udFloatDates');
            if (fd) {
                const o = {
                    month: 'short',
                    day: 'numeric'
                };
                fd.textContent = `${a.toLocaleDateString('en-PH', o)} – ${b.toLocaleDateString('en-PH', o)} · ${nights}n`;
            }
        }

        function initCardCalendars() {
            if (typeof flatpickr === 'undefined' || !ciEl || !coEl) return;

            const {
                isBooked,
                isCheckinBlocked,
                getNextBookedAfter
            } = buildCalendarHelpers();
            const today = new Date();
            today.setHours(12, 0, 0, 0);
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);

            const fpCi = flatpickr(ciEl, {
                dateFormat: 'Y-m-d',
                minDate: tomorrow,
                disableMobile: false,
                disable: [date => isCheckinBlocked(date)],
                onReady(_, __, fp) {
                    setTimeout(() => injectYearDropdown(fp), 0);
                },
                onDayCreate(dObj, dStr, fp, dayElem) {
                    if (isCheckinBlocked(dayElem.dateObj)) {
                        dayElem.classList.add('fp-booked');
                        dayElem.title = isBooked(dayElem.dateObj) ?
                            'Already booked' :
                            'Not available — next day is booked';
                    }
                },
                onChange([date]) {
                    if (!date) return;
                    const nextBooked = getNextBookedAfter(date);
                    const minCo = new Date(date);
                    minCo.setDate(minCo.getDate() + 1);
                    fpCo.set('minDate', minCo);
                    fpCo.set('maxDate', nextBooked || null);
                    const coVal = fpCo.selectedDates[0];
                    if (!coVal || coVal <= date || (nextBooked && coVal >= nextBooked)) fpCo.clear();
                    updateBreakdown();
                },
            });

            const fpCo = flatpickr(coEl, {
                dateFormat: 'Y-m-d',
                minDate: today,
                disableMobile: false,
                disable: [date => isBooked(date)],
                onReady(_, __, fp) {
                    setTimeout(() => injectYearDropdown(fp), 0);
                },
                onDayCreate(dObj, dStr, fp, dayElem) {
                    if (isBooked(dayElem.dateObj)) {
                        dayElem.classList.add('fp-booked');
                        dayElem.title = 'Already booked';
                    }
                },
                onChange([date]) {
                    if (!date) return;
                    updateBreakdown();
                },
            });

            ciEl._fp = fpCi;
            coEl._fp = fpCo;
        }

        if (typeof flatpickr !== 'undefined') {
            initCardCalendars();
        } else {
            window.addEventListener('load', initCardCalendars);
        }

        window._udGetCheckin = () => ciEl?.value || '';
        window._udGetCheckout = () => coEl?.value || '';
    })();


    /* =========================================================================
    6. GUEST COUNTER
    ========================================================================= */
    (function initGuests() {
        const u = window.UD_UNIT || {};
        let gCount = 2;
        const gCountEl = $('udGCount');
        const gPluralEl = $('udGPlural');

        function updateGuests() {
            if (gCountEl) gCountEl.textContent = gCount;
            if (gPluralEl) gPluralEl.textContent = gCount === 1 ? '' : 's';
        }

        $('udGMinus')?.addEventListener('click', () => {
            if (gCount > 1) {
                gCount--;
                updateGuests();
            }
        });
        $('udGPlus')?.addEventListener('click', () => {
            if (gCount < (u.maxGuests || 6)) {
                gCount++;
                updateGuests();
            }
        });

        window._udGetGuestCount = () => gCount;
    })();


    /* =========================================================================
    7. BOOKING MODAL
    ========================================================================= */
    (function initBookingModal() {

        window.openBookingModal = window.openBookingModal || function (room) {
            if (window.hasActiveBooking) {
                showToast?.('You already have an active booking.');
                return;
            }
            set('bmSbName', room.name || '—');
            set('bmSbLoc', room.location || '—');
            set('sb-rent', fmt(room.priceNum) + ' / night (base)');
            set('sb-deposit', '—');
            set('sb-total', '—');

            const img = $('bmUnitImg');
            if (img && room.image) {
                img.src = room.image;
                img.style.display = 'block';
            }

            const s = window._psSessionFields || {};
            [
                ['bm-fname', s.fname],
                ['bm-lname', s.lname],
                ['bm-email', s.email],
                ['bm-phone', s.phone]
            ]
            .forEach(([id, v]) => {
                const el = $(id);
                if (el) el.value = v || '';
            });

            const ci = $('bm-checkin'),
                co = $('bm-lease');
            if (ci) ci.value = room._prefillCheckin || '';
            if (co) co.value = room._prefillCheckout || '';

            document.querySelectorAll('#bmPayMethods .bm-pay-badge').forEach(b => b.remove());
            const pop = window.PS_POPULAR_PAYMENT || null;
            if (pop) {
                const popEl = document.querySelector(`#bmPayMethods [data-method="${pop}"]`);
                if (popEl && !popEl.querySelector('.bm-pay-badge')) {
                    const b = document.createElement('span');
                    b.className = 'bm-pay-badge';
                    b.textContent = 'Popular';
                    popEl.appendChild(b);
                }
            }

            window._bmRoom = room;
            _goTo(1);

            const ov = $('bmOverlay');
            if (ov) {
                window._bmSavedScroll = window.scrollY || document.documentElement.scrollTop;
                document.body.style.top = `-${window._bmSavedScroll}px`;
                document.body.classList.add('bm-open');
                ov.classList.add('active');
                requestAnimationFrame(() => ov.classList.add('open'));
                ov.onclick = e => {
                    if (e.target === ov) closeBookingModal();
                };
            }
        };

        window.closeBookingModal = window.closeBookingModal || function () {
            const ov = $('bmOverlay');
            if (!ov) return;

            // If we're on the payment-waiting step (step 4) and have a booking ID,
            // cancel the booking silently before closing.
            const currentStep = window._bmCurrentStep || 0;
            const pendingBid = window._bmPendingBookingId || null;
            if (currentStep === 4 && pendingBid && !window.hasActiveBooking) {
                fetch(`../../api/user/check_payment_status.php?booking_id=${pendingBid}`)
                    .then(r => r.json())
                    .then(st => {
                        if (st.payment_status === 'paid' || st.booking_status === 'confirmed') {
                            // Payment went through — don't cancel, mark as active
                            window.hasActiveBooking = true;
                            showToast?.('Your payment was confirmed!');
                            return;
                        }
                        // Safe to cancel — payment never completed
                        const cfd = new FormData();
                        cfd.append('booking_id', pendingBid);
                        window.psAppendCsrf?.(cfd);
                        fetch('../../api/user/cancel_booking.php', {
                                method: 'POST',
                                body: cfd
                            })
                            .then(r => r.json())
                            .then(res => {
                                if (res.success) showToast?.('Booking cancelled — payment was not completed.');
                            })
                            .catch(() => {});
                    })
                    .catch(() => {
                        // Network error — don't cancel, play it safe
                    });
                window._bmPendingBookingId = null;
            }

            ov.classList.remove('open');
            const savedY = window._bmSavedScroll || 0;
            document.body.classList.remove('bm-open');
            document.body.style.top = '';
            window.scrollTo(0, savedY);
            setTimeout(() => ov.classList.remove('active'), 350);
            clearInterval(window._bmPollInterval);
        };

        let _bmGoToRunning = false;

        function _goTo(step) {
            _bmGoToRunning = true;
            step = Math.max(1, Math.min(4, step));
            window._bmCurrentStep = step;
            document.querySelectorAll('.bm-panel').forEach((p, i) =>
                p.classList.toggle('active', i + 1 === step));
            document.querySelectorAll('.bm-step').forEach((s, i) => {
                s.classList.toggle('active', i + 1 === step);
                s.classList.toggle('done', i + 1 < step);
            });
            const back = $('bmBack'),
                next = $('bmNext'),
                conf = $('bmConfirmBtn'),
                done = $('bmDoneBtn');
            if (back) back.style.display = (step > 1 && step < 4) ? '' : 'none';
            if (next) next.style.display = step < 3 ? '' : 'none';
            if (conf) conf.style.display = step === 3 ? '' : 'none';
            if (done) done.style.display = 'none';

            if (step === 2) {
                const room = window._bmRoom || {};
                const ci = val('bm-checkin'),
                    co = val('bm-lease');
                const baseRate = window.UD_UNIT?.priceNum || room.priceNum || 0;
                const {
                    nights,
                    total,
                    rows
                } = getSeasonalTotal(ci, co, baseRate, window.UD_UNIT?.seasonality || {}); // ← add rows
                const nightlyDisplay = nights > 0 ? fmt(Math.round(total / nights)) : fmt(baseRate);
                const fmtDate = iso => {
                    const d = new Date(iso + 'T00:00:00');
                    return d.toLocaleDateString('en-US', {
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });
                };

                set('rv-name', [val('bm-fname'), val('bm-lname')].join(' '));
                set('rv-email', val('bm-email'));
                set('rv-phone', val('bm-phone'));
                set('rv-unit', room.name || '—');
                set('rv-movein', ci ? fmtDate(ci) : '—');
                set('rv-checkout', co ? fmtDate(co) : '—');
                set('rv-nights', nights);
                set('rv-rent', fmt(baseRate));
                set('rv-total', fmt(total));
                window._bmRoom._computedTotal = total; // store for submit

                // ✅ Sync sidebar with the same total computed above
                set('sb-total', fmt(total));
                const bd = $('sb-season-breakdown');
                if (bd) {
                    bd.innerHTML = rows.map(r =>
                        `<div style="display:flex;justify-content:space-between;font-size:0.72rem;color:#6b7280;margin-top:4px">
                                <span>${MONTH_SHORT[r.month]} · ${r.nights}n × ${fmt(r.rate)}</span>
                                <span>${fmt(r.subtotal)}</span>
                            </div>`
                    ).join('');
                }

                const checkInMonth = ci ? new Date(ci).getMonth() : null;
                const label = checkInMonth !== null ? (window.UD_UNIT?.seasonLabel?. [checkInMonth] || '') : '';
                const color = SEASON_COLOR[label] || '#6b7280';
                const rvSeason = $('rv-season'),
                    rvSeasonRow = $('rv-season-row');
                if (rvSeason && label) {
                    rvSeason.innerHTML = `<span style="background:${color}20;color:${color};padding:1px 8px;border-radius:99px;font-size:11px;font-weight:700">${label}</span>`;
                    if (rvSeasonRow) rvSeasonRow.style.display = '';
                }

                _bmGoToRunning = false;
            }
        }

        window.bmNextStep = window.bmNextStep || function () {
            const step = window._bmCurrentStep || 1;
            if (step === 1) {
                if (!val('bm-fname') || !val('bm-lname')) {
                    showToast?.('Please enter your full name.');
                    return;
                }
                if (!val('bm-email')) {
                    showToast?.('Please enter your email.');
                    return;
                }
                if (!val('bm-phone')) {
                    showToast?.('Please enter your contact number.');
                    return;
                }
                if (!val('bm-checkin')) {
                    showToast?.('Please select a check-in date.');
                    return;
                }
                if (!val('bm-lease')) {
                    showToast?.('Please select a check-out date.');
                    return;
                }
                if (val('bm-lease') <= val('bm-checkin')) {
                    showToast?.('Check-out must be after check-in.');
                    return;
                }
            }
            _goTo(step + 1);
        };

        window.bmPrevStep = window.bmPrevStep || function () {
            if ((window._bmCurrentStep || 1) > 1) _goTo((window._bmCurrentStep || 1) - 1);
        };

        window.bmSubmitBooking = window.bmSubmitBooking || function () {
            const room = window._bmRoom || {};
            const method = document.querySelector('#bmPayMethods .bm-pay-option.selected')?.dataset.method || '';
            const ci = val('bm-checkin'),
                co = val('bm-lease');
            const {
                nights,
                total: _recomputed
            } = getSeasonalTotal(
                ci, co,
                room.priceNum || 0,
                window.UD_UNIT?.seasonality || {}
            );
            // Use the total already shown to the user on the review step;
            // fall back to recomputed if not stored.
            const total = room._computedTotal || _recomputed;

            showToast?.('Submitting your booking…');

            const fd = new FormData();
            fd.append('unit_id', room.id || '');
            fd.append('first_name', val('bm-fname'));
            fd.append('last_name', val('bm-lname'));
            fd.append('email', val('bm-email'));
            fd.append('phone', val('bm-phone'));
            fd.append('checkin', ci);
            fd.append('checkout', co);
            fd.append('payment_method', method);
            fd.append('guests', room.guests || 1);
            fd.append('total_amount', total);
            window.psAppendCsrf?.(fd);

            _goTo(4);
            ['bm-payment-waiting', 'bm-payment-success', 'bm-payment-cash', 'bm-payment-failed', 'bm-payment-expired']
            .forEach((id, i) => {
                const el = $(id);
                if (el) el.style.display = i === 0 ? '' : 'none';
            });
            $('bmFooter')?.querySelectorAll('button').forEach(b => b.style.display = 'none');

            fetch('../../api/user/book_unit.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        showToast?.(data.message || 'Booking failed.', 'error');
                        _goTo(3);
                        return;
                    }

                    const bid = data.booking_id;
                    window._bmPendingBookingId = bid; // track so close can cancel
                    set('bmConfirmRef', `Ref #BK-${String(bid).padStart(4, '0')}`);

                    if (method === 'Cash') {
                        $('bm-payment-waiting').style.display = 'none';
                        $('bm-payment-cash').style.display = '';
                        set('cf-unit-cash', room.name || '\u2014');
                        set('cf-movein-cash', ci);
                        set('cf-checkout-cash', co);
                        set('cf-method-cash', method);
                        set('cf-total-cash', fmt(total));
                        $('bmDoneBtn').style.display = '';
                        window.hasActiveBooking = true;
                        window._bmPendingBookingId = null;
                        return;
                    }

                    // ── shared success handler ────────────────────────────────────────
                    function _bmOnPaySuccess() {
                        $('bm-payment-waiting').style.display = 'none';
                        $('bm-payment-success').style.display = '';
                        set('cf-unit', room.name || '\u2014');
                        set('cf-movein', ci);
                        set('cf-checkout', co);
                        set('cf-method', method);
                        set('cf-total', fmt(total));
                        $('bmDoneBtn').style.display = '';
                        window.hasActiveBooking = true;
                        window._bmPendingBookingId = null;
                        showToast?.('Payment confirmed!');
                    }

                    // ── shared poller ─────────────────────────────────────────────────
                    function _bmStartPoll(pollUrl) {
                        let polls = 0;
                        window._bmPollInterval = setInterval(() => {
                            if (++polls > 72) {
                                clearInterval(window._bmPollInterval);
                                $('bm-payment-waiting').style.display = 'none';
                                $('bm-payment-expired').style.display = '';
                                set('bmExpiredRef', `Ref #BK-${String(bid).padStart(4, '0')}`);
                                return;
                            }
                            fetch(pollUrl)
                                .then(r => r.json())
                                .then(st => {
                                    const ps = st.payment_status || '';
                                    const bs = st.booking_status || '';
                                    if (ps === 'paid' || bs === 'confirmed') {
                                        clearInterval(window._bmPollInterval);
                                        _bmOnPaySuccess();
                                    } else if (ps === 'failed' || bs === 'cancelled') {
                                        clearInterval(window._bmPollInterval);
                                        $('bm-payment-waiting').style.display = 'none';
                                        $('bm-payment-failed').style.display = '';
                                        set('bmFailedRef', `Ref #BK-${String(bid).padStart(4, '0')}`);
                                    } else if (ps === 'expired') {
                                        clearInterval(window._bmPollInterval);
                                        $('bm-payment-waiting').style.display = 'none';
                                        $('bm-payment-expired').style.display = '';
                                        set('bmExpiredRef', `Ref #BK-${String(bid).padStart(4, '0')}`);
                                    }
                                }).catch(() => {});
                        }, 5000);
                    }

                    // Card payment via PayMongo Hosted Checkout (PCI-safe)
                    if (method === 'Card') {
                        CardCheckout.start({
                            bookingId: bid,
                            csrfToken: window.psGetCsrfToken?.() || '',
                            onWaiting: () => {
                                setTimeout(() => {
                                    const b = $('bmReopenPayBtn');
                                    if (b) b.style.display = '';
                                }, 4000);
                                window.bmReopenPaymongoTab = () => CardCheckout.reopenTab();
                            },
                            onSuccess: () => _bmOnPaySuccess(),
                            onFailed: () => {
                                $('bm-payment-waiting').style.display = 'none';
                                $('bm-payment-failed').style.display = '';
                                set('bmFailedRef', `Ref #BK-${String(bid).padStart(4, '0')}`);
                            },
                            onExpired: () => {
                                $('bm-payment-waiting').style.display = 'none';
                                $('bm-payment-expired').style.display = '';
                                set('bmExpiredRef', `Ref #BK-${String(bid).padStart(4, '0')}`);
                            },
                            onError: (msg) => {
                                $('bm-payment-waiting').style.display = 'none';
                                $('bm-payment-failed').style.display = '';
                                set('bmFailedRef', `Ref #BK-${String(bid).padStart(4, '0')}`);
                                showToast?.(msg, 'error');
                            },
                        });
                        return;
                    }

                    // ── GCash / Maya / Bank Transfer via PayMongo Link ─────────────────
                    const pmFd = new FormData();
                    pmFd.append('booking_id', bid);
                    pmFd.append('payment_method', method);
                    window.psAppendCsrf?.(pmFd);

                    fetch('../../api/user/create_paymongo_link.php', {
                            method: 'POST',
                            body: pmFd
                        })
                        .then(r => r.json())
                        .then(pmData => {
                            if (pmData.success && pmData.checkout_url) {
                                window._bmPayTab = window.open(pmData.checkout_url, '_blank');
                                window._bmPayUrl = pmData.checkout_url;
                                setTimeout(() => {
                                    const b = $('bmReopenPayBtn');
                                    if (b) b.style.display = '';
                                }, 4000);
                            } else {
                                $('bm-payment-waiting').style.display = 'none';
                                $('bm-payment-failed').style.display = '';
                                set('bmFailedRef', `Ref #BK-${String(bid).padStart(4, '0')}`);
                                showToast?.(pmData.message || 'Could not create payment link.', 'error');
                                clearInterval(window._bmPollInterval);
                                return;
                            }
                        })
                        .catch(() => {
                            showToast?.('Could not reach payment service.', 'error');
                        });

                    _bmStartPoll(`../../api/user/check_payment_status.php?booking_id=${bid}`);

                })
                .catch(err => {
                    showToast?.(err?.message || 'Network error.', 'error');
                    _goTo(3);
                });
        };

        window.bmReopenPaymongoTab = window.bmReopenPaymongoTab || function () {
            if (window._bmPayTab && !window._bmPayTab.closed) window._bmPayTab.focus();
            else if (window._bmPayUrl) window._bmPayTab = window.open(window._bmPayUrl, '_blank');
        };

        document.querySelectorAll('#bmPayMethods .bm-pay-option').forEach(opt => {
            opt.addEventListener('click', () => {
                document.querySelectorAll('#bmPayMethods .bm-pay-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
            });
        });

        window.openBookingModalFromDetail = function (roomData) {
            var prefillCI = window._udGetCheckin?.() || '';
            var prefillCO = window._udGetCheckout?.() || '';
            roomData._prefillCheckin = prefillCI;
            roomData._prefillCheckout = prefillCO;
            roomData.guests = window._udGetGuestCount?.() || 1;
            window.openBookingModal(roomData);
            // _initModalCalendars destroys & recreates flatpickr so we must
            // set the dates AFTER it finishes via nested setTimeout.
            setTimeout(function () {
                _initModalCalendars(prefillCI, prefillCO);
                setTimeout(function () {
                    _updateModalSummary();
                }, 100);
            }, 0);
        };

        function _initModalCalendars(prefillCI, prefillCO) {
            if (typeof flatpickr === 'undefined') return;

            const ciInput = $('bm-checkin');
            const coInput = $('bm-lease');
            if (!ciInput || !coInput) return;

            if (ciInput._fp) {
                ciInput._fp.destroy();
                ciInput._fp = null;
            }
            if (coInput._fp) {
                coInput._fp.destroy();
                coInput._fp = null;
            }

            const {
                isBooked,
                isCheckinBlocked,
                getNextBookedAfter
            } = buildCalendarHelpers();
            const today = new Date();
            today.setHours(12, 0, 0, 0);
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);

            function _bumpMinStay(ciEl, coEl, fpCoInstance) {
                const MIN = 3;
                if (!ciEl.value || !coEl.value) return;
                const nights = Math.round((new Date(coEl.value) - new Date(ciEl.value)) / 86400000);
                if (nights > 0 && nights < MIN) {
                    const minCo = new Date(ciEl.value);
                    minCo.setDate(minCo.getDate() + MIN);
                    const newDate = minCo.toISOString().split('T')[0];
                    showToast?.(`Minimum stay is ${MIN} nights — checkout updated.`);
                    // Use setTimeout to avoid triggering onChange recursively
                    setTimeout(() => {
                        fpCoInstance.setDate(newDate, true);
                    }, 0);
                }
            }

            const ciMinDate = prefillCI ? new Date(prefillCI + 'T12:00:00') : tomorrow;
            const fpCi = flatpickr(ciInput, {
                dateFormat: 'Y-m-d',
                minDate: ciMinDate,
                defaultDate: prefillCI || tomorrow,
                disableMobile: false,
                disable: [date => isCheckinBlocked(date)],
                onReady(_, __, fp) {
                    setTimeout(() => {
                        injectYearDropdown(fp);
                        // set check-out minDate based on prefill or default
                        const ciBase = prefillCI ? new Date(prefillCI + 'T12:00:00') : tomorrow;
                        const minCo = new Date(ciBase);
                        minCo.setDate(minCo.getDate() + 1);
                        fpCo.set('minDate', minCo);
                        if (prefillCO) {
                            fpCo.setDate(prefillCO, false);
                        } else {
                            fpCo.clear();
                        }
                    }, 0);
                },
                onDayCreate(dObj, dStr, fp, dayElem) {
                    if (isCheckinBlocked(dayElem.dateObj)) {
                        dayElem.classList.add('fp-booked');
                        dayElem.title = isBooked(dayElem.dateObj) ?
                            'Already booked' :
                            'Not available — next day is booked';
                    }
                },
                onChange([date]) {
                    if (!date) return;
                    const nextBooked = getNextBookedAfter(date);
                    const minCo = new Date(date);
                    minCo.setDate(minCo.getDate() + 1);
                    fpCo.set('minDate', minCo);
                    fpCo.set('maxDate', nextBooked || null);
                    const coVal = fpCo.selectedDates[0];
                    if (!coVal || coVal <= date || (nextBooked && coVal >= nextBooked)) fpCo.clear();
                    _updateModalSummary();
                    // bump min stay after checkin changes
                    _bumpMinStay(ciInput, coInput, fpCo);
                },
            });

            const fpCo = flatpickr(coInput, {
                dateFormat: 'Y-m-d',
                minDate: today,
                disableMobile: false,
                disable: [date => isBooked(date)],
                onReady(_, __, fp) {
                    setTimeout(() => injectYearDropdown(fp), 0);
                },
                onDayCreate(dObj, dStr, fp, dayElem) {
                    if (isBooked(dayElem.dateObj)) {
                        dayElem.classList.add('fp-booked');
                        dayElem.title = 'Already booked';
                    }
                },
                onChange([date]) {
                    if (!date) return;
                    _bumpMinStay(ciInput, coInput, fpCo);
                    _updateModalSummary();
                },
            });

            ciInput._fp = fpCi;
            coInput._fp = fpCo;
        }

        function _updateModalSummary() {
            const ci = $('bm-checkin')?.value;
            const co = $('bm-lease')?.value;
            const room = window._bmRoom || {};
            const baseRate = room.priceNum || 0;

            if (!ci || !co || !baseRate) {
                set('sb-total', '—');
                const bd = $('sb-season-breakdown');
                if (bd) bd.innerHTML = '';
                return;
            }

            const {
                nights,
                total,
                rows
            } = getSeasonalTotal(ci, co, baseRate, window.UD_UNIT?.seasonality || {});

            if (nights <= 0) {
                set('sb-total', '—');
                const bd = $('sb-season-breakdown');
                if (bd) bd.innerHTML = '';
                return;
            }

            set('sb-total', fmt(total));

            const bd = $('sb-season-breakdown');
            if (bd) {
                bd.innerHTML = rows.map(r =>
                    `<div style="display:flex;justify-content:space-between;font-size:0.72rem;color:#6b7280;margin-top:4px">
                            <span>${MONTH_SHORT[r.month]} · ${r.nights}n × ${fmt(r.rate)}</span>
                            <span>${fmt(r.subtotal)}</span>
                        </div>`
                ).join('');
            }

            const checkInMonth = new Date(ci).getMonth();
            const label = window.UD_UNIT?.seasonLabel?. [checkInMonth] ||
                window.UD_UNIT?.seasonLabel?. [String(checkInMonth)] ||
                '';
            const color = SEASON_COLOR[label] || '#6b7280';
            const sbBadge = $('sb-season-badge');
            if (sbBadge) {
                sbBadge.innerHTML = label ?
                    `<span style="background:${color}20;color:${color};padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700">${label} Season</span>` :
                    '';
            }
        }

        if (new URLSearchParams(location.search).get('book') === '1') {
            function _autoOpenBookingModal() {
                if (typeof window.openBookingModalFromDetail === 'function' && window.UD_ROOM_DATA) {
                    window.openBookingModalFromDetail(window.UD_ROOM_DATA);
                    return;
                }
                const btn = $('udBookBtn');
                if (btn && !btn.disabled) {
                    btn.click();
                    return;
                }
                let attempts = 0;
                const poll = setInterval(function () {
                    attempts++;
                    const b = $('udBookBtn');
                    if (b && !b.disabled) {
                        clearInterval(poll);
                        b.click();
                    } else if (attempts > 20) clearInterval(poll);
                }, 100);
            }

            if (document.readyState === 'complete') {
                _autoOpenBookingModal();
            } else {
                window.addEventListener('load', _autoOpenBookingModal);
            }
        }
    })();


    /* =========================================================================
    8. FLOAT BAR
    ========================================================================= */
    (function initFloatBar() {
        const card = $('udBookingCard');
        if (!card || !('IntersectionObserver' in window)) return;
        new IntersectionObserver(([e]) => {
            document.body.classList.toggle('ud-card-offscreen', !e.isIntersecting);
        }, {
            threshold: 0
        }).observe(card);
    })();


    /* =========================================================================
    9. AMENITIES SHOW-MORE
    ========================================================================= */
    (function initAmenities() {
        const showMoreBtn = $('udShowMoreAmenities');
        if (!showMoreBtn) return;
        document.querySelectorAll('.ud-amenity-chip').forEach((c, i) => {
            if (i >= 8) c.classList.add('ud-am-hidden');
        });
        showMoreBtn.addEventListener('click', () => {
            document.querySelectorAll('.ud-amenity-chip.ud-am-hidden').forEach(c => c.classList.remove('ud-am-hidden'));
            showMoreBtn.style.display = 'none';
        });
    })();


    /* =========================================================================
    10. VIRTUAL TOUR MODAL
    ========================================================================= */
    window.openVirtualTour = function () {
        const modal = $('udTourModal');
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeTour = function () {
        const modal = $('udTourModal');
        if (!modal) return;
        modal.classList.remove('open');
        document.body.style.overflow = '';
    };

    $('udTourModal')?.addEventListener('click', e => {
        if (e.target === $('udTourModal')) window.closeTour();
    });


    /* =========================================================================
    11. SHARE UNIT
    ========================================================================= */
    window.shareUnit = function () {
        const shareData = {
            title: document.title,
            text: 'Check out this unit on PropSight — ' + document.title,
            url: location.href,
        };
        if (navigator.share && navigator.canShare?.(shareData)) {
            navigator.share(shareData).catch(() => {});
        } else if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(location.href)
                .then(() => showToast?.('Link copied to clipboard!'))
                .catch(() => prompt('Copy this link:', location.href));
        } else {
            prompt('Copy this link:', location.href);
        }
    };


    /* =========================================================================
    12. RATING BAR SCROLL ANIMATION
    ========================================================================= */
    (function initRatingBars() {
        const bars = document.querySelectorAll('.ud-rbar-fill');
        if (!bars.length) return;
        const targets = Array.from(bars).map(b => b.style.width);
        bars.forEach(b => {
            b.style.width = '0%';
            b.style.transition = 'width 0.6s cubic-bezier(0.4,0,0.2,1)';
        });
        const summary = document.querySelector('.ud-rating-summary');
        if (summary && 'IntersectionObserver' in window) {
            new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    bars.forEach((b, i) => setTimeout(() => {
                        b.style.width = targets[i];
                    }, i * 80));
                    obs.disconnect();
                });
            }, {
                threshold: 0.3
            }).observe(summary);
        } else {
            bars.forEach((b, i) => {
                b.style.width = targets[i];
            });
        }
    })();


    /* =========================================================================
    13. NEARBY ATTRACTIONS — staggered slide-in
    ========================================================================= */
    (function initNearby() {
        const items = document.querySelectorAll('.ud-nearby-item');
        if (!items.length || !('IntersectionObserver' in window)) return;
        items.forEach((item, i) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-8px)';
            item.style.transition = `opacity 0.35s ease ${i * 0.07}s, transform 0.35s ease ${i * 0.07}s`;
        });
        const list = document.querySelector('.ud-nearby-list');
        if (!list) return;
        new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                items.forEach(item => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                });
                obs.disconnect();
            });
        }, {
            threshold: 0.2
        }).observe(list);
    })();


    /* =========================================================================
    14. SOCIAL PROOF NUDGE — fade in
    ========================================================================= */
    document.querySelectorAll('.ud-social-nudge').forEach(el => {
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.5s ease 0.3s';
        setTimeout(() => {
            el.style.opacity = '1';
        }, 400);
    });


    /* =========================================================================
    15. MINIMUM STAY VALIDATION (3-night minimum)
    ========================================================================= */
    (function initMinStay() {
        const MIN = 3;

        function bump(ciEl, coEl) {
            if (!ciEl?.value || !coEl?.value) return;
            const nights = Math.round((new Date(coEl.value) - new Date(ciEl.value)) / 86400000);
            if (nights > 0 && nights < MIN) {
                const minCo = new Date(ciEl.value);
                minCo.setDate(minCo.getDate() + MIN);
                const newDate = minCo.toISOString().split('T')[0];

                // Update flatpickr instance if it exists (modal or card)
                if (coEl._fp) {
                    coEl._fp.setDate(newDate, true); // true = triggers flatpickr onChange
                } else {
                    coEl.value = newDate;
                    coEl.dispatchEvent(new Event('change'));
                }

                showToast?.(`Minimum stay is ${MIN} nights — checkout updated.`);
            }
        }

        const udCi = $('udCheckin'),
            udCo = $('udCheckout');
        const bmCi = $('bm-checkin'),
            bmCo = $('bm-lease');
        udCi?.addEventListener('change', () => bump(udCi, udCo));
        udCo?.addEventListener('change', () => bump(udCi, udCo));
        bmCi?.addEventListener('change', () => bump(bmCi, bmCo));
        bmCo?.addEventListener('change', () => bump(bmCi, bmCo));
    })();

})();