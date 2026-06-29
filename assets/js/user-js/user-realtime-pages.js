/**
 * user-realtime-pages.js — PropSight User Page Realtime Handlers
 * ---------------------------------------------------------------
 * Listens to events emitted by realtime.js and applies live DOM
 * updates specific to each user-facing page.
 *
 * Pages covered:
 *   bookings   — stat pills, booking card status badges, cancel button hide
 *   loyalty    — points balance, tier name, progress bar, summary card
 *   profile    — name/email/photo/verification (sidebar + header)
 *   saved      — saved count badge
 *   settings   — no dynamic data; just profile sync (shared handler)
 *   support    — unread badge, ticket status updates
 *   payment    — unread messages badge (shared handler)
 *   messages   — unread count badge (shared handler)
 *   dashboard  — manage-stay banner & modal live status update
 *
 * All handlers are no-ops when the target elements don't exist,
 * so this file is safe to include on every user page.
 */

(function () {
    'use strict';

    /* ── helpers (mirrors realtime.js internals) ─────────── */
    function _esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function _setText(sel, val) {
        document.querySelectorAll(sel).forEach(el => {
            el.textContent = val;
        });
    }

    function _fmtDate(iso) {
        if (!iso) return '';
        // Strip any timezone suffix (e.g. "+00:00") — fmt_dt_rows() in the API
        // appends "+00:00" to DATE columns, which breaks new Date(iso + 'T00:00:00').
        const datePart = String(iso).slice(0, 10);
        const d = new Date(datePart + 'T00:00:00');
        if (isNaN(d.getTime())) return String(iso);
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
            year: 'numeric'
        });
    }

    function _fmtCurrency(val) {
        return '₱' + Number(val).toLocaleString('en-PH', {
            minimumFractionDigits: 0
        });
    }

    function _stBadge(status) {
        const m = {
            pending: { text: 'Pending', cls: 'badge-pending' },
            confirmed: { text: 'Confirmed', cls: 'badge-confirmed' },
            active: { text: 'Active', cls: 'badge-active' },
            completed: { text: 'Completed', cls: 'badge-completed' },
            cancelled: { text: 'Cancelled', cls: 'badge-cancelled' },
        };
        return m[status] || { text: status, cls: '' };
    }

    // Status → css class used by .mm-status and .bb-status
    const _mmStMap = {
        pending: 'mm-st-pending',
        confirmed: 'mm-st-confirmed',
        active: 'mm-st-active',
        completed: 'mm-st-done',
        cancelled: 'mm-st-cancelled',
    };
    const _bbStMap = {
        pending: 'st-pending',
        confirmed: 'st-active',
        active: 'st-active',
        completed: 'st-done',
        cancelled: 'st-cancelled',
    };

    /* ═══════════════════════════════════════════════════════
     *  PROFILE BADGE — declared at top so all sections can use it
     * ═══════════════════════════════════════════════════════ */
    var _profileBadgeCounts = { messages: 0, support: 0 };

    function _updateProfileBadge() {
        var count = _profileBadgeCounts.messages;
        var badge = document.getElementById('chatMsgBadge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // Seed profile badge on load — fetch unread count directly from the realtime API
    // so the badge is populated on first page load, not just after the next poll fires.
    document.addEventListener('DOMContentLoaded', function () {
        var apiBase = window.PS_RT_API || '../../api/realtime.php';
        var page = window.PS_RT_PAGE || 'dashboard';
        fetch(apiBase + '?since=2000-01-01+00:00:00&page=' + page + '&role=user&_=' + Date.now(), {
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            var count = parseInt(data.unread_messages, 10) || 0;
            if (count > 0) {
                _profileBadgeCounts.messages = count;
                _updateProfileBadge();
            }
        })
        .catch(function () {});

        // Show live-indicator dot in header if element exists
        var liveDot = document.getElementById('rt-live-dot');
        if (liveDot) {
            liveDot.style.display = 'inline-block';
            liveDot.title = 'Live updates active';
        }
    });

    /* ═══════════════════════════════════════════════════════
     *  1. BOOKINGS PAGE — stat pills + card status badges
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:booking_updates', function (e) {
        const updates = e.detail || [];
        updates.forEach(function (b) {
            const id = String(b.booking_id);
            const lbl = _stBadge(b.status);

            document.querySelectorAll('[data-booking-id="' + id + '"]').forEach(function (el) {
                const badge = el.querySelector(
                    '.booking-status-badge, .badge, [data-field="status"], .history-status'
                );
                if (badge) {
                    badge.className = badge.className
                        .replace(/\bbadge-\w+/g, '')
                        .replace(/\bst-\w+/g, '')
                        .trim();
                    badge.classList.add(lbl.cls);
                    badge.textContent = lbl.text;
                    badge.setAttribute('data-status', b.status);
                }

                // Also update the image overlay badge (bc-img-badge)
                const imgBadge = el.querySelector('.bc-img-badge');
                if (imgBadge) {
                    imgBadge.className = imgBadge.className
                        .replace(/\bbadge-\w+/g, '')
                        .trim();
                    imgBadge.classList.add(lbl.cls);
                    imgBadge.textContent = lbl.text;
                    imgBadge.dataset.status = b.status;
                }

                const cancelBtn = el.querySelector('[data-action="cancel"], .bc-btn-cancel');
                if (cancelBtn && ['cancelled', 'completed', 'active'].includes(b.status)) {
                    cancelBtn.style.display = 'none';
                }
            });
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  2. DASHBOARD — manage-stay banner + modal live update
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:booking_updates', function (e) {
        const updates = e.detail || [];

        updates.forEach(function (b) {
            const id = String(b.booking_id);

            /* ── Active Booking Banner ─────────────────────────── */
            var banner = document.getElementById('rt-active-booking-wrap');
            if (banner && String(banner.dataset.bookingId) === id) {

                var bannerBadge = document.getElementById('rt-active-booking-status');
                if (bannerBadge) {
                    var stCls = _bbStMap[b.status] || '';
                    bannerBadge.className = bannerBadge.className
                        .replace(/\bst-\w+/g, '')
                        .trim() + ' ' + stCls;
                    bannerBadge.textContent = _stBadge(b.status).text;
                }

                if (b.checkin_date || b.checkout_date) {
                    var ci = b.checkin_date || banner.dataset.checkin;
                    var co = b.checkout_date || banner.dataset.checkout;
                    if (ci) banner.dataset.checkin = ci;
                    if (co) banner.dataset.checkout = co;
                    var bbDates = banner.querySelector('.bb-dates');
                    if (bbDates) {
                        bbDates.innerHTML = 'Check-in: ' + _fmtDate(ci) +
                            '<span class="bb-date-sep"> &mdash; </span>Check-out: ' + _fmtDate(co);
                    }
                }

                if (['cancelled', 'completed'].includes(b.status)) {
                    banner.style.transition = 'opacity 0.5s, max-height 0.7s ease, margin 0.7s, padding 0.7s';
                    banner.style.overflow = 'hidden';
                    banner.style.opacity = '0';
                    setTimeout(function () {
                        banner.style.maxHeight = '0';
                        banner.style.marginTop = '0';
                        banner.style.marginBottom = '0';
                        banner.style.paddingTop = '0';
                        banner.style.paddingBottom = '0';
                    }, 520);
                    setTimeout(function () {
                        banner.style.display = 'none';
                    }, 1300);

                    if (typeof showToast === 'function') {
                        showToast(
                            'Your booking has been ' + b.status + ' by the property manager.',
                            b.status === 'cancelled' ? 'warning' : 'info'
                        );
                    }
                }
            }

            /* ── Manage Stay Modal (if open for this booking) ──── */
            var modal = document.getElementById('manageModal');
            var isOpen = modal && modal.classList.contains('open');
            var matchId = typeof window.currentBookingId !== 'undefined' &&
                String(window.currentBookingId) === id;

            if (modal && isOpen && matchId) {

                var pill = document.getElementById('manageStatusPill');
                if (pill) {
                    pill.className = 'mm-status ' + (_mmStMap[b.status] || '');
                    var pillText = document.getElementById('manageStatusText');
                    if (pillText) pillText.textContent = _stBadge(b.status).text;
                }

                if (b.checkin_date) {
                    var ciEl = document.getElementById('manageCheckin');
                    if (ciEl) ciEl.textContent = _fmtDate(b.checkin_date);
                }
                if (b.checkout_date) {
                    var coEl = document.getElementById('manageCheckout');
                    if (coEl) coEl.textContent = _fmtDate(b.checkout_date);
                }

                if (b.nights !== undefined) {
                    var nEl = document.getElementById('manageNightsNum');
                    if (nEl) nEl.textContent = b.nights;
                }

                if (b.total_amount) {
                    var totEl = document.getElementById('manageTotal');
                    if (totEl) {
                        totEl.textContent = '₱' + Number(b.total_amount)
                            .toLocaleString('en-PH', { minimumFractionDigits: 0 });
                    }
                }

                if (b.checkin_date && b.checkout_date) {
                    var inD = new Date(b.checkin_date + 'T12:00:00');
                    var outD = new Date(b.checkout_date + 'T12:00:00');
                    var now = new Date();
                    var total = outD - inD;
                    var elapsed = now - inD;
                    var pct = Math.max(0, Math.min(100, Math.round((elapsed / total) * 100)));
                    var fill = document.getElementById('manageProgressFill');
                    var pTxt = document.getElementById('manageProgressText');
                    var pWrap = document.getElementById('manageProgressWrap');
                    if (fill) fill.style.width = pct + '%';
                    if (pTxt) pTxt.textContent = pct + '%';
                    if (pWrap) pWrap.style.display = (pct > 0 && pct < 100) ? '' : 'none';
                }

                var cancelBtn = document.getElementById('manageCancelBtn');
                if (cancelBtn) {
                    cancelBtn.style.display = ['completed', 'cancelled'].includes(b.status) ? 'none' : '';
                }

                var hero = modal.querySelector('.mm-hero-content');
                if (hero) {
                    hero.style.transition = 'background 0.4s';
                    hero.style.background = 'rgba(59,130,246,0.07)';
                    setTimeout(function () { hero.style.background = ''; }, 1200);
                }

                if (typeof showToast === 'function') {
                    showToast(
                        'Your booking status updated to ' + _stBadge(b.status).text + '.',
                        b.status === 'cancelled' ? 'warning' : 'success'
                    );
                }
            }

            /* ── Update Room Card Availability ──────────────────── */
            /* Only mark as Booked — never flip to Available from a single user's booking status
               because another user may still have an active booking on the same unit. */
            if (b.unit_id && ['confirmed', 'active', 'pending'].includes(b.status)) {
                var roomCard = document.querySelector('.room-card[data-unit-id="' + String(b.unit_id) + '"]');
                if (roomCard) {
                    roomCard.dataset.status = 'occupied';
                    var availBadge = roomCard.querySelector('[data-avail-status]');
                    if (availBadge) {
                        availBadge.className = 'room-avail avail-no';
                        availBadge.textContent = 'Booked';
                    }
                    var bookBtn = roomCard.querySelector('[data-book-btn]');
                    if (bookBtn) {
                        var unitId = parseInt(roomCard.dataset.unitId);
                        var isMyBooking = window.PS_BOOKED_UNIT_IDS && window.PS_BOOKED_UNIT_IDS.includes(unitId);
                        if (isMyBooking) {
                            bookBtn.disabled = true;
                            bookBtn.textContent = 'Already Booked';
                            bookBtn.style.opacity = '0.6';
                            bookBtn.style.cursor = 'default';
                            bookBtn.onclick = null;
                        } else {
                            bookBtn.disabled = false;
                            bookBtn.textContent = 'Reserve Date';
                            bookBtn.removeAttribute('aria-disabled');
                            bookBtn.style.opacity = '';
                            bookBtn.style.cursor = '';
                            bookBtn.onclick = function(ev) {
                                if (ev) ev.stopPropagation();
                                if (unitId) window.location.href = 'unit_detail.php?id=' + unitId;
                            };
                        }
                    }
                }
            }
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  2.5. DASHBOARD — Update Stats & Booking History
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:booking_updates', function (e) {
        var updates = e.detail || [];
        var unitStatusMap = {};

        updates.forEach(function (b) {
            if (!b || !b.unit_id) return;
            var unitId = String(b.unit_id);
            var isOccupied = ['pending', 'confirmed', 'active'].includes(b.status);
            var priority = isOccupied ? 2 : 1;

            if (!unitStatusMap[unitId] || priority > unitStatusMap[unitId].priority) {
                unitStatusMap[unitId] = { status: b.status, priority: priority };
            }

            if (b.status === 'pending' || b.status === 'confirmed') {
                var bookingCountEl = document.querySelector('[data-rt-user="booking_total"]');
                if (bookingCountEl) {
                    var currentCount = parseInt(bookingCountEl.textContent, 10) || 0;
                    if (!document.querySelector('[data-booking-id="' + b.booking_id + '"]')) {
                        bookingCountEl.textContent = currentCount + 1;
                        bookingCountEl.style.transition = 'transform 0.3s ease';
                        bookingCountEl.style.transform = 'scale(1.15)';
                        setTimeout(function () { bookingCountEl.style.transform = ''; }, 300);
                    }
                }
            }
        });

        /* Only mark as Booked — never flip to Available from user's own booking status */
        Object.keys(unitStatusMap).forEach(function (unitId) {
            var status = unitStatusMap[unitId].status;
            if (!['confirmed', 'active', 'pending'].includes(status)) return;
            var roomCard = document.querySelector('.room-card[data-unit-id="' + String(unitId) + '"]');
            if (!roomCard) return;

            roomCard.dataset.status = 'occupied';
            var availBadge = roomCard.querySelector('[data-avail-status]');
            if (availBadge) {
                availBadge.className = 'room-avail avail-no';
                availBadge.textContent = 'Booked';
            }
            var bookBtn = roomCard.querySelector('[data-book-btn]');
            if (bookBtn) {
                var unitId = parseInt(roomCard.dataset.unitId);
                var isMyBooking = window.PS_BOOKED_UNIT_IDS && window.PS_BOOKED_UNIT_IDS.includes(unitId);
                if (isMyBooking) {
                    bookBtn.disabled = true;
                    bookBtn.textContent = 'Already Booked';
                    bookBtn.style.opacity = '0.6';
                    bookBtn.style.cursor = 'default';
                    bookBtn.onclick = null;
                } else {
                    bookBtn.disabled = false;
                    bookBtn.textContent = 'Reserve Date';
                    bookBtn.removeAttribute('aria-disabled');
                    bookBtn.style.opacity = '';
                    bookBtn.style.cursor = '';
                    bookBtn.onclick = function(ev) {
                        if (ev) ev.stopPropagation();
                        if (unitId) window.location.href = 'unit_detail.php?id=' + unitId;
                    };
                }
            }
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  3. DASHBOARD & LOYALTY — stats & user metrics
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:user_metrics', function (e) {
        var m = e.detail || {};

        if (m.booking_total !== undefined) {
            var bookCountEl = document.querySelector('[data-rt-user="booking_total"]');
            if (bookCountEl) {
                var newCount = parseInt(m.booking_total, 10);
                var oldCount = parseInt(bookCountEl.textContent, 10);
                bookCountEl.textContent = newCount;
                if (newCount !== oldCount) {
                    bookCountEl.style.transition = 'transform 0.3s ease';
                    bookCountEl.style.transform = 'scale(1.15)';
                    setTimeout(function () { bookCountEl.style.transform = ''; }, 300);
                }
            }
        }

        if (m.saved_count !== undefined) {
            var savedCountEl = document.querySelector('[data-rt-user="saved_count"]');
            if (savedCountEl) savedCountEl.textContent = parseInt(m.saved_count, 10);
            var savedTextEl = document.querySelector('[data-rt-user="saved_count_text"]');
            if (savedTextEl) savedTextEl.textContent = parseInt(m.saved_count, 10) + ' on wishlist';
        }

        var points = parseInt(m.loyalty_points, 10);
        var tier = String(m.loyalty_tier || '').trim();

        if (!isNaN(points)) {
            var fmtPts = points.toLocaleString('en-PH');

            var dashLoyaltyEl = document.querySelector('[data-rt-user="loyalty_points"]');
            if (dashLoyaltyEl) dashLoyaltyEl.textContent = fmtPts;
            var dashLoyaltyTextEl = document.querySelector('[data-rt-user="loyalty_points_text"]');
            if (dashLoyaltyTextEl) dashLoyaltyTextEl.textContent = fmtPts + ' points';
            var dashTierEl = document.querySelector('[data-rt-user="loyalty_tier"]');
            if (dashTierEl) dashTierEl.textContent = tier;

            var numEl = document.getElementById('loyaltyPointsNum');
            if (numEl) numEl.textContent = fmtPts;
            var sumEl = document.getElementById('summaryBalance');
            if (sumEl) sumEl.textContent = fmtPts + ' pts';
            var progLeft = document.getElementById('loyaltyProgressLeft');
            if (progLeft && tier) progLeft.textContent = tier + ' (' + fmtPts + ' pts)';

            var tierDefs = [
                { name: 'Silver', min: 0 },
                { name: 'Gold', min: 500 },
                { name: 'Platinum', min: 2000 },
                { name: 'Diamond', min: 5000 },
            ];
            var curIdx = 0;
            tierDefs.forEach(function (t, i) { if (points >= t.min) curIdx = i; });
            var nextTd = tierDefs[curIdx + 1];
            var nextTier = nextTd ? nextTd.name : 'Diamond';
            var ptsToNext = nextTd ? Math.max(0, nextTd.min - points) : 0;
            var subEl = document.getElementById('loyaltyPointsSub');
            if (subEl) subEl.textContent = 'points · ' + ptsToNext + ' pts to ' + nextTier;

            if (nextTd) {
                var tierBase = tierDefs[curIdx].min;
                var tierTotal = nextTd.min;
                var pct = Math.max(0, Math.min(100, Math.round(
                    ((points - tierBase) / (tierTotal - tierBase)) * 100
                )));
                var bar = document.getElementById('loyaltyProgressBar');
                if (bar) bar.style.width = pct + '%';
                var pctLabel = document.getElementById('loyaltyProgressPct');
                if (pctLabel) pctLabel.textContent = pct + '%';
            }
        }

        if (tier) {
            var tierEl = document.getElementById('loyaltyTierName');
            if (tierEl) tierEl.textContent = tier;

            document.querySelectorAll('.tier-card').forEach(function (tc) {
                var nameEl = tc.querySelector('.tier-name');
                if (!nameEl) return;
                var isActive = nameEl.textContent.trim() === tier;
                tc.classList.toggle('active-tier', isActive);
                var badge = tc.querySelector('.tier-current-badge');
                if (badge) badge.style.display = isActive ? '' : 'none';
            });
        }
    });

    /* ═══════════════════════════════════════════════════════
     *  4. SAVED PAGE — saved count badge in header area
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:user_metrics', function (e) {
        var m = e.detail || {};
        var saved = parseInt(m.saved_count, 10);
        if (!isNaN(saved)) {
            _setText('[data-rt-saved="count"]', saved);
            _setText('[data-rt-saved="count-text"]', saved + ' saved');
        }
    });

    /* ═══════════════════════════════════════════════════════
     *  5. SUPPORT PAGE — new admin reply + ticket status
     *     (no toast — badge only via profile badge)
     * ═══════════════════════════════════════════════════════ */

    // Track seen reply IDs so we don't double-badge
    var _seenReplyIds = (function () {
        try {
            return new Set(JSON.parse(sessionStorage.getItem('ps_seen_reply_ids') || '[]'));
        } catch (e) {
            return new Set();
        }
    })();

    window.addEventListener('ps:new_messages', function (e) {
        var msgs = e.detail || [];
        if (!Array.isArray(msgs)) return;

        msgs.forEach(function (msg) {
            // ticket_replies arrive with ticket_id
            if (!msg.ticket_id) return;
            var key = 'reply_' + msg.message_id;
            if (_seenReplyIds.has(key)) return;
            _seenReplyIds.add(key);
            try {
                sessionStorage.setItem('ps_seen_reply_ids', JSON.stringify([..._seenReplyIds].slice(-200)));
            } catch (e) {}

            // Live-inject reply into open ticket modal if currently viewing that ticket
            var modalBody = document.getElementById('ticketModalBody');
            var activeTkId = modalBody && modalBody.dataset.ticketId;
            if (modalBody && String(activeTkId) === String(msg.ticket_id)) {
                var div = document.createElement('div');
                div.className = 'ticket-reply admin-reply';
                div.style.cssText = 'padding:10px 14px;border-radius:10px;background:var(--blue-50,#eff6ff);margin:8px 0;font-size:0.88rem;';
                div.innerHTML =
                    '<strong style="font-size:0.78rem;color:var(--ink-faint,#6b7280);">Support · Just now</strong>' +
                    '<p style="margin:4px 0 0;">' + _esc(msg.body || '') + '</p>';
                modalBody.appendChild(div);
                modalBody.scrollTop = modalBody.scrollHeight;
            }

            // Update unread badge on support tab if it exists
            var badge = document.querySelector('[data-rt="support-badge"]');
            if (badge) {
                var cur = parseInt(badge.textContent, 10) || 0;
                badge.textContent = cur + 1;
                badge.style.display = '';
            }

            // Increment profile badge for support reply
            _profileBadgeCounts.support = (_profileBadgeCounts.support || 0) + 1;
            _updateProfileBadge();
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  6. ALL PAGES — unread messages badge in nav + profile badge
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:unread_messages', function (e) {
        var count = parseInt(e.detail, 10) || 0;

        // Nav badge (sidebar messages link)
        document.querySelectorAll('.nav-badge[data-rt="messages"], [data-rt-user="unread_messages"]')
            .forEach(function (el) {
                el.textContent = count;
                el.style.display = count > 0 ? '' : 'none';
            });

        // Messages link icon dot
        document.querySelectorAll('[data-rt="msg-dot"]').forEach(function (el) {
            el.style.display = count > 0 ? '' : 'none';
        });

        // Message icon badge in header
        _profileBadgeCounts.messages = count;
        _updateProfileBadge();
    });

    /* ═══════════════════════════════════════════════════════
     *  7. BOOKINGS PAGE — PS_RT_PAGE='bookings' specific
     *     Refresh stat pills from booking_stats payload
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:booking_stats', function (e) {
        var s = e.detail || {};

        // Safe update — only overwrite if new value > 0, or DOM is already showing 0
        // Prevents the first realtime poll from zeroing out correct PHP-rendered values
        function safeSet(sel, newVal) {
            document.querySelectorAll(sel).forEach(function (el) {
                var cur = parseInt(el.textContent, 10) || 0;
                if (newVal > 0 || cur === 0) el.textContent = newVal;
            });
        }

        safeSet('[data-rt-stat="upcoming"]',  parseInt(s.upcoming,   10) || 0);
        safeSet('[data-rt-stat="active"]',    parseInt(s.active_cnt, 10) || 0);
        safeSet('[data-rt-stat="completed"]', parseInt(s.completed,  10) || 0);
        safeSet('[data-rt-stat="cancelled"]', parseInt(s.cancelled,  10) || 0);

        var total = parseInt(s.total, 10) || 0;
        document.querySelectorAll('[data-rt-user="booking_total"]').forEach(function (el) {
            var cur = parseInt(el.textContent, 10) || 0;
            if (total > 0 || cur === 0) el.textContent = total;
        });

        // Update total_spent stat card
        if (s.total_spent !== undefined) {
            var spent = parseFloat(s.total_spent) || 0;
            document.querySelectorAll('[data-rt-stat="total_spent"]').forEach(function (el) {
                el.textContent = '₱' + spent.toLocaleString('en-PH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            });
        }
    });

    /* ═══════════════════════════════════════════════════════
     *  8. PROFILE / SETTINGS — profile sync (name/photo)
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:profile_sync', function (e) {
        var p = e.detail || {};
        var first = String(p.first_name || '').trim();
        var last = String(p.last_name || '').trim();
        var full = (first + ' ' + last).trim() || 'Guest';

        _setText('#profileFullName, [data-rt-profile="full_name"]', full);
        _setText('[data-rt-profile="first_name"]', first);
        _setText('[data-rt-profile="last_name"]', last);
        _setText('[data-rt-profile="email"]', String(p.email || ''));

        var vStatus = String(p.verification_status || '').toLowerCase() === 'verified';
        document.querySelectorAll('[data-rt-profile="verification_badge"]').forEach(function (el) {
            el.classList.toggle('verified', vStatus);
            el.classList.toggle('unverified', !vStatus);
            el.textContent = vStatus ? '✓ Verified' : 'Unverified';
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  10. DASHBOARD — full manage-stay banner sync from API
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:manage_stay_booking', function (e) {
        var bk = e.detail;
        var banner = document.getElementById('rt-active-booking-wrap');
        if (!banner) return;

        if (!bk) {
            if (banner.style.display === 'none') return;

            /* Unit availability not updated here — another user may still have an active booking */
            var unitId = banner.dataset.unitId;
            void unitId; /* intentionally unused */
            window.hasActiveBooking = false;

            banner.style.transition = 'opacity 0.5s, max-height 0.7s ease, margin 0.7s, padding 0.7s';
            banner.style.overflow = 'hidden';
            banner.style.opacity = '0';
            setTimeout(function () {
                banner.style.maxHeight = '0';
                banner.style.marginTop = '0';
                banner.style.marginBottom = '0';
                banner.style.paddingTop = '0';
                banner.style.paddingBottom = '0';
            }, 520);
            setTimeout(function () { banner.style.display = 'none'; }, 1300);
            return;
        }

        var id = String(bk.booking_id);
        var sameBooking = banner.dataset.bookingId === id;
        banner.dataset.bookingId = id;
        if (bk.unit_id) banner.dataset.unitId = String(bk.unit_id);

        /* Only update room card badge if booking changed */
        if (!sameBooking && bk.unit_id) {
            var roomCard = document.querySelector('.room-card[data-unit-id="' + String(bk.unit_id) + '"]');
            if (roomCard) {
                roomCard.dataset.status = 'occupied';
                var availBadge = roomCard.querySelector('[data-avail-status]');
                if (availBadge) {
                    availBadge.className = 'room-avail avail-no';
                    availBadge.textContent = 'Booked';
                }
                var bookBtn = roomCard.querySelector('[data-book-btn]');
                if (bookBtn) {
                    var unitId = parseInt(roomCard.dataset.unitId);
                    var isMyBooking = window.PS_BOOKED_UNIT_IDS && window.PS_BOOKED_UNIT_IDS.includes(unitId);
                    if (isMyBooking) {
                        bookBtn.disabled = true;
                        bookBtn.textContent = 'Already Booked';
                        bookBtn.style.opacity = '0.6';
                        bookBtn.style.cursor = 'default';
                        bookBtn.onclick = null;
                    } else {
                        bookBtn.disabled = false;
                        bookBtn.textContent = 'Reserve Date';
                        bookBtn.removeAttribute('aria-disabled');
                        bookBtn.style.opacity = '';
                        bookBtn.style.cursor = '';
                        bookBtn.onclick = function(ev) {
                            if (ev) ev.stopPropagation();
                            if (unitId) window.location.href = 'unit_detail.php?id=' + unitId;
                        };
                    }
                }
            }
        }

        if (banner.style.display === 'none' || parseFloat(banner.style.opacity) === 0) {
            banner.style.display = '';
            banner.style.opacity = '';
            banner.style.maxHeight = '';
            banner.style.overflow = '';
            banner.style.marginTop = '';
            banner.style.marginBottom = '';
            banner.style.paddingTop = '';
            banner.style.paddingBottom = '';
        }

        var bannerBadge = document.getElementById('rt-active-booking-status');
        if (bannerBadge) {
            var newStCls = _bbStMap[bk.status] || '';
            var newStText = _stBadge(bk.status).text;
            if (bannerBadge.textContent !== newStText) {
                bannerBadge.className = bannerBadge.className
                    .replace(/\bst-\w+/g, '').trim() + ' ' + newStCls;
                bannerBadge.textContent = newStText;
            }
        }

        var roomEl = banner.querySelector('.bb-room');
        if (roomEl) {
            var unitLabel = bk.unit_name || bk.unit_number || 'Unit';
            var newRoomText = unitLabel + (bk.property_name ? ' \u2014 ' + bk.property_name : '');
            var currentRoomText = roomEl.textContent.replace(/\s+/g, ' ').trim();
            var normalizedNew = newRoomText.replace(/\s+/g, ' ').trim();
            if (currentRoomText !== normalizedNew) {
                roomEl.textContent = newRoomText;
            }
        }

        if (bk.checkin_date || bk.checkout_date) {
            var bbDates = banner.querySelector('.bb-dates');
            if (bbDates) {
                var newDates =
                    'Check-in: ' + _fmtDate(bk.checkin_date) +
                    '<span class="bb-date-sep"> &mdash; </span>' +
                    'Check-out: ' + _fmtDate(bk.checkout_date);
                if (bbDates.innerHTML !== newDates) {
                    bbDates.innerHTML = newDates;
                }
            }
        }

        var manageBtn = banner.querySelector('.btn-manage');
        if (manageBtn) {
            var nights = bk.nights !== undefined ? bk.nights :
                (bk.checkin_date && bk.checkout_date ?
                    Math.max(0, Math.round(
                        (new Date(bk.checkout_date) - new Date(bk.checkin_date)) / 86400000)) : 0);
            var imgSrc = bk.image_path ?
                '../../' + String(bk.image_path).replace(/^\/+/, '') : '';
            var modalPayload = {
                booking_id: bk.booking_id,
                unit_name: bk.unit_name || bk.unit_number || 'Unit',
                property_name: bk.property_name || '',
                address: bk.address || '',
                latitude: parseFloat(bk.latitude || 0),
                longitude: parseFloat(bk.longitude || 0),
                checkin: _fmtDate(bk.checkin_date),
                checkout: _fmtDate(bk.checkout_date),
                nights: nights,
                status: _stBadge(bk.status).text,
                total_amount: 'PHP ' + Number(bk.total_amount || 0)
                    .toLocaleString('en-PH', { minimumFractionDigits: 0 }),
                guests: parseInt(bk.guests || 2, 10),
                image: imgSrc,
            };
            manageBtn.setAttribute('onclick',
                'openManageModal(' + JSON.stringify(modalPayload)
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'") + ')');
        }

        var modal = document.getElementById('manageModal');
        var isOpen = modal && modal.classList.contains('open');
        if (isOpen &&
            typeof window.currentBookingId !== 'undefined' &&
            String(window.currentBookingId) === id) {

            var pill = document.getElementById('manageStatusPill');
            if (pill) {
                pill.className = 'mm-status ' + (_mmStMap[bk.status] || '');
                var pillTxt = document.getElementById('manageStatusText');
                if (pillTxt) pillTxt.textContent = _stBadge(bk.status).text;
            }
        }
    });

})();