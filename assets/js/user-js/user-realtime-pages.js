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
 *   support    — unread badge, ticket status updates, new reply toast
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
        document.querySelectorAll(sel).forEach(el => { el.textContent = val; });
    }
    function _fmtDate(iso) {
        if (!iso) return '';
        const d = new Date(iso + 'T00:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }
    function _fmtCurrency(val) {
        return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 0 });
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
     *  1. BOOKINGS PAGE — stat pills + card status badges
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:booking_updates', function (e) {
        const updates = e.detail || [];
        updates.forEach(function (b) {
            const id = String(b.booking_id);
            const lbl = _stBadge(b.status);

            // Update every element that carries data-booking-id
            document.querySelectorAll('[data-booking-id="' + id + '"]').forEach(function (el) {
                // Status badge
                const badge = el.querySelector(
                    '.booking-status-badge, .badge, [data-field="status"], .history-status'
                );
                if (badge) {
                    // Remove old badge- and st- colour classes
                    badge.className = badge.className
                        .replace(/\bbadge-\w+/g, '')
                        .replace(/\bst-\w+/g, '')
                        .trim();
                    badge.classList.add(lbl.cls);
                    badge.textContent = lbl.text;
                    badge.setAttribute('data-status', b.status);
                }

                // Cancel button — hide when no longer cancellable
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

                // Update inline status badge
                var bannerBadge = document.getElementById('rt-active-booking-status');
                if (bannerBadge) {
                    var stCls = _bbStMap[b.status] || '';
                    bannerBadge.className = bannerBadge.className
                        .replace(/\bst-\w+/g, '')
                        .trim() + ' ' + stCls;
                    bannerBadge.textContent = _stBadge(b.status).text;
                }

                // Update dates if changed
                if (b.checkin_date || b.checkout_date) {
                    var ci = b.checkin_date || banner.dataset.checkin;
                    var co = b.checkout_date || banner.dataset.checkout;
                    if (ci) banner.dataset.checkin = ci;
                    if (co) banner.dataset.checkout = co;
                    var bbDates = banner.querySelector('.bb-dates');
                    if (bbDates) {
                        bbDates.innerHTML = 'Check-in: ' + _fmtDate(ci) +
                            '<span class="bb-date-sep"> &nbsp;·&nbsp; </span>Check-out: ' + _fmtDate(co);
                    }
                }

                // Collapse banner when booking ends
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
                    setTimeout(function () { banner.style.display = 'none'; }, 1300);

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
            // currentBookingId is declared in script.js
            var matchId = typeof window.currentBookingId !== 'undefined' &&
                String(window.currentBookingId) === id;

            if (modal && isOpen && matchId) {

                // Status pill
                var pill = document.getElementById('manageStatusPill');
                if (pill) {
                    pill.className = 'mm-status ' + (_mmStMap[b.status] || '');
                    var pillText = document.getElementById('manageStatusText');
                    if (pillText) pillText.textContent = _stBadge(b.status).text;
                }

                // Dates
                if (b.checkin_date) {
                    var ciEl = document.getElementById('manageCheckin');
                    if (ciEl) ciEl.textContent = _fmtDate(b.checkin_date);
                }
                if (b.checkout_date) {
                    var coEl = document.getElementById('manageCheckout');
                    if (coEl) coEl.textContent = _fmtDate(b.checkout_date);
                }

                // Nights
                if (b.nights !== undefined) {
                    var nEl = document.getElementById('manageNightsNum');
                    if (nEl) nEl.textContent = b.nights;
                }

                // Total amount
                if (b.total_amount) {
                    var totEl = document.getElementById('manageTotal');
                    if (totEl) {
                        totEl.textContent = '₱' + Number(b.total_amount)
                            .toLocaleString('en-PH', { minimumFractionDigits: 0 });
                    }
                }

                // Progress bar update
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

                // Cancel button visibility
                var cancelBtn = document.getElementById('manageCancelBtn');
                if (cancelBtn) {
                    cancelBtn.style.display =
                        ['completed', 'cancelled'].includes(b.status) ? 'none' : '';
                }

                // Flash modal header subtly
                var hero = modal.querySelector('.mm-hero-content');
                if (hero) {
                    hero.style.transition = 'background 0.4s';
                    hero.style.background = 'rgba(59,130,246,0.07)';
                    setTimeout(function () { hero.style.background = ''; }, 1200);
                }

                // Toast
                if (typeof showToast === 'function') {
                    showToast(
                        'Your booking status updated to ' + _stBadge(b.status).text + '.',
                        b.status === 'cancelled' ? 'warning' : 'success'
                    );
                }
            }

            /* ── Update Room Card Availability ──────────────────── */
            if (b.unit_id) {
                // Use .room-card to avoid matching the booking banner which also has data-unit-id
                var roomCard = document.querySelector('.room-card[data-unit-id="' + String(b.unit_id) + '"]');
                if (roomCard) {
                    if (['cancelled', 'completed'].includes(b.status)) {
                        roomCard.dataset.status = 'vacant';
                        var availBadge = roomCard.querySelector('[data-avail-status]');
                        if (availBadge) {
                            availBadge.className = 'room-avail avail-yes';
                            availBadge.textContent = 'AVAILABLE';
                        }
                        var bookBtn = roomCard.querySelector('[data-book-btn]');
                        if (bookBtn) {
                            bookBtn.disabled = false;
                            bookBtn.textContent = 'Book Now';
                            bookBtn.onclick = function (ev) {
                                if (ev) ev.stopPropagation();
                                if (typeof openBookingModal !== 'function') return;
                                try {
                                    openBookingModal(JSON.parse(roomCard.dataset.roomPayload || '{}'));
                                } catch (err) { }
                            };
                        }
                    } else if (['confirmed', 'active', 'pending'].includes(b.status)) {
                        roomCard.dataset.status = 'occupied';
                        var availBadge = roomCard.querySelector('[data-avail-status]');
                        if (availBadge) {
                            availBadge.className = 'room-avail avail-no';
                            availBadge.textContent = 'BOOKED';
                        }
                        var bookBtn = roomCard.querySelector('[data-book-btn]');
                        if (bookBtn) {
                            bookBtn.disabled = true;
                            bookBtn.textContent = 'Unavailable';
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
                unitStatusMap[unitId] = {
                    status: b.status,
                    priority: priority,
                };
            }

            // Update booking count when new booking is created
            if (b.status === 'pending' || b.status === 'confirmed') {
                var bookingCountEl = document.querySelector('[data-rt-user="booking_total"]');
                if (bookingCountEl) {
                    var currentCount = parseInt(bookingCountEl.textContent, 10) || 0;
                    // Increment if this is a new booking (optimistic update)
                    if (!document.querySelector('[data-booking-id="' + b.booking_id + '"]')) {
                        bookingCountEl.textContent = currentCount + 1;
                        bookingCountEl.style.transition = 'transform 0.3s ease';
                        bookingCountEl.style.transform = 'scale(1.15)';
                        setTimeout(function () { bookingCountEl.style.transform = ''; }, 300);
                    }
                }
            }
        });

        Object.keys(unitStatusMap).forEach(function (unitId) {
            var status = unitStatusMap[unitId].status;
            var roomCard = document.querySelector('.room-card[data-unit-id="' + String(unitId) + '"]');
            if (!roomCard) return;

            if (['cancelled', 'completed'].includes(status)) {
                roomCard.dataset.status = 'vacant';
                var availBadge = roomCard.querySelector('[data-avail-status]');
                if (availBadge) {
                    availBadge.className = 'room-avail avail-yes';
                    availBadge.textContent = 'AVAILABLE';
                }
                var bookBtn = roomCard.querySelector('[data-book-btn]');
                if (bookBtn) {
                    bookBtn.disabled = false;
                    bookBtn.textContent = 'Book Now';
                    bookBtn.onclick = function (ev) {
                        if (ev) ev.stopPropagation();
                        if (typeof openBookingModal !== 'function') return;
                        try {
                            openBookingModal(JSON.parse(roomCard.dataset.roomPayload || '{}'));
                        } catch (err) { }
                    };
                }
            } else if (['confirmed', 'active', 'pending'].includes(status)) {
                roomCard.dataset.status = 'occupied';
                var availBadge = roomCard.querySelector('[data-avail-status]');
                if (availBadge) {
                    availBadge.className = 'room-avail avail-no';
                    availBadge.textContent = 'BOOKED';
                }
                var bookBtn = roomCard.querySelector('[data-book-btn]');
                if (bookBtn) {
                    bookBtn.disabled = true;
                    bookBtn.textContent = 'Unavailable';
                }
            }
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  3. DASHBOARD & LOYALTY — stats & user metrics
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:user_metrics', function (e) {
        var m = e.detail || {};

        // Update booking count on dashboard header stat
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

        // Update saved count on dashboard header stat
        if (m.saved_count !== undefined) {
            var savedCountEl = document.querySelector('[data-rt-user="saved_count"]');
            if (savedCountEl) {
                savedCountEl.textContent = parseInt(m.saved_count, 10);
            }
            var savedTextEl = document.querySelector('[data-rt-user="saved_count_text"]');
            if (savedTextEl) {
                savedTextEl.textContent = parseInt(m.saved_count, 10) + ' on wishlist';
            }
        }

        var points = parseInt(m.loyalty_points, 10);
        var tier = String(m.loyalty_tier || '').trim();

        if (!isNaN(points)) {
            var fmtPts = points.toLocaleString('en-PH');

            // Update dashboard loyalty stats
            var dashLoyaltyEl = document.querySelector('[data-rt-user="loyalty_points"]');
            if (dashLoyaltyEl) {
                dashLoyaltyEl.textContent = fmtPts;
            }
            var dashLoyaltyTextEl = document.querySelector('[data-rt-user="loyalty_points_text"]');
            if (dashLoyaltyTextEl) {
                dashLoyaltyTextEl.textContent = fmtPts + ' points';
            }
            var dashTierEl = document.querySelector('[data-rt-user="loyalty_tier"]');
            if (dashTierEl) {
                dashTierEl.textContent = tier;
            }

            // Hero card big number
            var numEl = document.getElementById('loyaltyPointsNum');
            if (numEl) numEl.textContent = fmtPts;

            // Summary panel
            var sumEl = document.getElementById('summaryBalance');
            if (sumEl) sumEl.textContent = fmtPts + ' pts';

            // Progress left label
            var progLeft = document.getElementById('loyaltyProgressLeft');
            if (progLeft && tier) {
                progLeft.textContent = tier + ' (' + fmtPts + ' pts)';
            }

            // Sub-label — recalculate pts to next tier
            var tierDefs = [
                { name: 'Silver', min: 0 },
                { name: 'Gold', min: 500 },
                { name: 'Platinum', min: 2000 },
                { name: 'Diamond', min: 5000 },
            ];
            var curIdx = 0;
            tierDefs.forEach(function (t, i) {
                if (points >= t.min) curIdx = i;
            });
            var nextTd = tierDefs[curIdx + 1];
            var nextTier = nextTd ? nextTd.name : 'Diamond';
            var ptsToNext = nextTd ? Math.max(0, nextTd.min - points) : 0;
            var subEl = document.getElementById('loyaltyPointsSub');
            if (subEl) {
                subEl.textContent = 'points · ' + ptsToNext + ' pts to ' + nextTier;
            }

            // Progress bar (bar id="loyaltyProgressBar" if present)
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

            // Active tier card highlight
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
            // Saved page header stat
            _setText('[data-rt-saved="count"]', saved);
            _setText('[data-rt-saved="count-text"]', saved + ' saved');
        }
    });

    /* ═══════════════════════════════════════════════════════
     *  5. SUPPORT PAGE — new admin reply toast + ticket status
     * ═══════════════════════════════════════════════════════ */

    // Track seen reply IDs so we don't double-toast
    var _seenReplyIds = (function () {
        try { return new Set(JSON.parse(sessionStorage.getItem('ps_seen_reply_ids') || '[]')); }
        catch (e) { return new Set(); }
    })();

    window.addEventListener('ps:new_messages', function (e) {
        var msgs = e.detail || [];
        if (!Array.isArray(msgs)) return;

        msgs.forEach(function (msg) {
            // ticket_replies arrive with is_admin=1 and ticket_id
            if (!msg.ticket_id) return;
            var key = 'reply_' + msg.message_id;
            if (_seenReplyIds.has(key)) return;
            _seenReplyIds.add(key);
            try { sessionStorage.setItem('ps_seen_reply_ids', JSON.stringify([..._seenReplyIds].slice(-200))); } catch (e) { }

            // Toast notification
            if (typeof showToast === 'function') {
                showToast('New reply on ticket #' + msg.ticket_id + ': ' + _esc(msg.body || '').slice(0, 60), 'info');
            }

            // Live-inject reply into open ticket modal if currently viewing that ticket
            var modalBody = document.getElementById('ticketModalBody');
            var activeTkId = modalBody && modalBody.dataset.ticketId;
            if (modalBody && String(activeTkId) === String(msg.ticket_id)) {
                var div = document.createElement('div');
                div.className = 'ticket-reply admin-reply';
                div.style.cssText = 'padding:10px 14px;border-radius:10px;background:var(--blue-50,#eff6ff);margin:8px 0;font-size:0.88rem;';
                div.innerHTML =
                    '<strong style="font-size:0.78rem;color:var(--ink-faint,#6b7280);">Support · just now</strong>' +
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
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  6. ALL PAGES — unread messages badge in nav
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:unread_messages', function (e) {
        var count = parseInt(e.detail, 10) || 0;
        // User layout uses data-rt="messages" on nav badge
        document.querySelectorAll('.nav-badge[data-rt="messages"], [data-rt-user="unread_messages"]')
            .forEach(function (el) {
                el.textContent = count;
                el.style.display = count > 0 ? '' : 'none';
            });
        // Messages link icon dot
        document.querySelectorAll('[data-rt="msg-dot"]').forEach(function (el) {
            el.style.display = count > 0 ? '' : 'none';
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  7. BOOKINGS PAGE — PS_RT_PAGE='bookings' specific
     *     Refresh stat pills from booking_stats payload
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:booking_stats', function (e) {
        var s = e.detail || {};
        var map = {
            upcoming: parseInt(s.upcoming, 10) || 0,
            active: parseInt(s.active_cnt, 10) || 0,
            completed: parseInt(s.completed, 10) || 0,
            cancelled: parseInt(s.cancelled, 10) || 0,
            total: parseInt(s.total, 10) || 0,
        };
        // data-rt-stat="upcoming|active|completed|cancelled"
        Object.keys(map).forEach(function (k) {
            document.querySelectorAll('[data-rt-stat="' + k + '"]').forEach(function (el) {
                el.textContent = map[k];
            });
        });
        // data-rt-user="booking_total"
        document.querySelectorAll('[data-rt-user="booking_total"]').forEach(function (el) {
            el.textContent = map.total;
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  8. PROFILE / SETTINGS — profile sync (name/photo)
     *     (already handled in realtime.js; this adds extra
     *      profile-page-specific targets)
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:profile_sync', function (e) {
        var p = e.detail || {};
        var first = String(p.first_name || '').trim();
        var last = String(p.last_name || '').trim();
        var full = (first + ' ' + last).trim() || 'Guest';

        // Profile page heading
        _setText('#profileFullName, [data-rt-profile="full_name"]', full);
        _setText('[data-rt-profile="first_name"]', first);
        _setText('[data-rt-profile="last_name"]', last);
        _setText('[data-rt-profile="email"]', String(p.email || ''));

        // Verification badge on profile page
        var vStatus = String(p.verification_status || '').toLowerCase() === 'verified';
        document.querySelectorAll('[data-rt-profile="verification_badge"]').forEach(function (el) {
            el.classList.toggle('verified', vStatus);
            el.classList.toggle('unverified', !vStatus);
            el.textContent = vStatus ? '✓ Verified' : 'Unverified';
        });
    });

    /* ═══════════════════════════════════════════════════════
     *  9. PAGE-TITLE INDICATOR — show a subtle "live" dot
     *     when realtime is active (cosmetic, optional)
     * ═══════════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', function () {
        // Stamp each user page with its PS_RT_PAGE so realtime.js
        // can send the right `page` query param.
        // (Pages set window.PS_RT_PAGE before _layout_end loads this file.)

        // Show live-indicator dot in header if element exists
        var liveDot = document.getElementById('rt-live-dot');
        if (liveDot) {
            liveDot.style.display = 'inline-block';
            liveDot.title = 'Live updates active';
        }
    });

    /* ═══════════════════════════════════════════════════════
     *  10. DASHBOARD — full manage-stay banner sync from API
     *      Fires on every poll with the current active booking
     *      snapshot (or null when there's no active booking).
     * ═══════════════════════════════════════════════════════ */
    window.addEventListener('ps:manage_stay_booking', function (e) {
        var bk = e.detail;            // null = no active booking
        var banner = document.getElementById('rt-active-booking-wrap');
        if (!banner) return;              // not on dashboard

        // ── No active booking → collapse banner ────────────
        if (!bk) {
            if (banner.style.display === 'none') return;  // already hidden

            // Reset the room card before collapsing
            var unitId = banner.dataset.unitId;
            if (unitId) {
                var roomCard = document.querySelector('.room-card[data-unit-id="' + unitId + '"]');
                if (roomCard) {
                    roomCard.dataset.status = 'vacant';
                    var availBadge = roomCard.querySelector('[data-avail-status]');
                    if (availBadge) {
                        availBadge.className = 'room-avail avail-yes';
                        availBadge.textContent = 'AVAILABLE';
                    }
                    var bookBtn = roomCard.querySelector('[data-book-btn]');
                    if (bookBtn) {
                        bookBtn.disabled = false;
                        bookBtn.textContent = 'Book Now';
                        bookBtn.onclick = function (ev) {
                            if (ev) ev.stopPropagation();
                            if (typeof openBookingModal !== 'function') return;
                            try { openBookingModal(JSON.parse(roomCard.dataset.roomPayload || '{}')); } catch (err) { }
                        };
                    }
                }
            }
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

        // ── Active booking → refresh banner fields ─────────
        var id = String(bk.booking_id);
        banner.dataset.bookingId = id;
        if (bk.unit_id) banner.dataset.unitId = String(bk.unit_id);

        if (bk.unit_id) {
            var roomCard = document.querySelector('.room-card[data-unit-id="' + String(bk.unit_id) + '"]');
            if (roomCard) {
                roomCard.dataset.status = 'occupied';
                var availBadge = roomCard.querySelector('[data-avail-status]');
                if (availBadge) {
                    availBadge.className = 'room-avail avail-no';
                    availBadge.textContent = 'BOOKED';
                }
                var bookBtn = roomCard.querySelector('[data-book-btn]');
                if (bookBtn) {
                    bookBtn.disabled = true;
                    bookBtn.textContent = 'Unavailable';
                    bookBtn.setAttribute('aria-disabled', 'true');
                }
            }
        }

        // Restore banner visibility if it was previously collapsed
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

        // Status badge
        var bannerBadge = document.getElementById('rt-active-booking-status');
        if (bannerBadge) {
            var stCls = _bbStMap[bk.status] || '';
            bannerBadge.className = bannerBadge.className
                .replace(/\bst-\w+/g, '').trim() + ' ' + stCls;
            bannerBadge.textContent = _stBadge(bk.status).text;
        }

        // Room + property label
        var roomEl = banner.querySelector('.bb-room');
        if (roomEl) {
            var unitLabel = bk.unit_name || bk.unit_number || 'Unit';
            roomEl.textContent = unitLabel + (bk.property_name ? ' \u2014 ' + bk.property_name : '');
        }

        // Dates row
        if (bk.checkin_date || bk.checkout_date) {
            var bbDates = banner.querySelector('.bb-dates');
            if (bbDates) {
                bbDates.innerHTML =
                    'Check-in: ' + _fmtDate(bk.checkin_date) +
                    '<span class="bb-date-sep"> &nbsp;\u00b7&nbsp; </span>' +
                    'Check-out: ' + _fmtDate(bk.checkout_date);
            }
        }

        // Keep the Manage Stay button's onclick payload fresh
        var manageBtn = banner.querySelector('.btn-manage');
        if (manageBtn) {
            var nights = bk.nights !== undefined ? bk.nights
                : (bk.checkin_date && bk.checkout_date
                    ? Math.max(0, Math.round(
                        (new Date(bk.checkout_date) - new Date(bk.checkin_date)) / 86400000))
                    : 0);
            var imgSrc = bk.image_path
                ? '../../' + String(bk.image_path).replace(/^\/+/, '') : '';
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

        // If the manage modal is already open for this booking, refresh it too
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
