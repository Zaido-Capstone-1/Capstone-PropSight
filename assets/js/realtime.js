/**
 * realtime.js — PropSight Real-Time Engine
 * ─────────────────────────────────────────
 * Polls /api/realtime.php every PS_RT_INTERVAL ms.
 * Emits custom events on window so each page can react independently.
 *
 * Events emitted (window.dispatchEvent):
 *   ps:booking_stats   — { detail: statsObj }
 *   ps:unit_stats      — { detail: statsObj }
 *   ps:new_bookings    — { detail: bookingsArray }
 *   ps:booking_updates — { detail: updatesArray }   (user-side)
 *   ps:notifications   — { detail: { items, count } }
 *   ps:unread_messages — { detail: count }
 *   ps:new_messages    — { detail: messagesArray }
 *   ps:checkin_updates — { detail: updatesArray }
 *   ps:recent_activity — { detail: activityArray }
 *   ps:total_revenue   — { detail: number }
 *   ps:unit_ratings    — { detail: ratingsArray }
 *   ps:manage_stay_booking - { detail: bookingObj|null } (user dashboard)
 */

(function () {
    'use strict';

    /* ── Config ─────────────────────────────────────── */
    const INTERVAL = window.PS_RT_INTERVAL || 8000; // ms between polls
    const API_BASE = window.PS_RT_API || '../../api/realtime.php';
    const PAGE = window.PS_RT_PAGE || 'dashboard';
    const ROLE = window.PS_RT_ROLE || 'user';

    /* ── State ──────────────────────────────────────── */
    // Initialize to current time so first poll only fetches NEW messages going forward,
    // not all historical messages (which would re-add unread badges on every page load).
    let lastTs = (function() {
        const d = new Date();
        return d.getFullYear() + '-' +
            String(d.getMonth()+1).padStart(2,'0') + '-' +
            String(d.getDate()).padStart(2,'0') + ' ' +
            String(d.getHours()).padStart(2,'0') + ':' +
            String(d.getMinutes()).padStart(2,'0') + ':' +
            String(d.getSeconds()).padStart(2,'0');
    })();
    let pollTimer = null;
    let failCount = 0;
    let paused = false;

    /* ── Helpers ────────────────────────────────────── */
    function emit(name, detail) {
        window.dispatchEvent(new CustomEvent('ps:' + name, {
            detail
        }));
    }

    function fmtCurrency(v) {
        return '₱' + Number(v).toLocaleString('en-PH', {
            minimumFractionDigits: 0
        });
    }

    function statusLabel(s) {
        const map = {
            pending: {
                text: 'Pending',
                cls: 'badge-pending'
            },
            confirmed: {
                text: 'Confirmed',
                cls: 'badge-confirmed'
            },
            active: {
                text: 'Active',
                cls: 'badge-active'
            },
            completed: {
                text: 'Completed',
                cls: 'badge-completed'
            },
            cancelled: {
                text: 'Cancelled',
                cls: 'badge-cancelled'
            },
        };
        return map[s] || {
            text: s,
            cls: ''
        };
    }

    // ── Dedup sets — declared here so ALL listeners below can safely reference them ──

    // Seen notification IDs persisted in localStorage — survives page navigation,
    // so the same notification never toasts again on a different page.
    const _seenNotifs = (function () {
        try {
            return new Set(JSON.parse(localStorage.getItem('ps_seen_notifs') || '[]'));
        } catch (e) {
            return new Set();
        }
    })();

    function _persistSeenNotifs() {
        try {
            localStorage.setItem('ps_seen_notifs', JSON.stringify([..._seenNotifs].slice(-200)));
        } catch (e) {}
    }

    // Admin booking IDs — seeded from the DOM after page load so existing
    // rows never trigger a "new booking" toast when navigating between pages.
    const _adminKnownIds = new Set();
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-booking-id]').forEach(el => {
            _adminKnownIds.add(String(el.dataset.bookingId));
        });
    });

    // Seen message IDs — session-scoped (fine to reset on browser close)
    const _seenMsgIds = (function () {
        try {
            return new Set(JSON.parse(sessionStorage.getItem('ps_seen_msg_ids') || '[]'));
        } catch (e) {
            return new Set();
        }
    })();

    function _persistSeenMsgIds() {
        try {
            sessionStorage.setItem('ps_seen_msg_ids', JSON.stringify([..._seenMsgIds].slice(-500)));
        } catch (e) {}
    }

    /* ── Core poll ──────────────────────────────────── */
    function poll() {
        if (paused || document.hidden) return;

        const url = `${API_BASE}?since=${encodeURIComponent(lastTs)}&page=${PAGE}&role=${ROLE}&_=${Date.now()}`;

        fetch(url, {
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                failCount = 0;
                lastTs = data.ts || lastTs;

                /* ── Admin events ────────────────── */
                if (data.booking_stats) emit('booking_stats', data.booking_stats);
                if (data.unit_stats) emit('unit_stats', data.unit_stats);
                if (data.new_bookings && data.new_bookings.length)
                    emit('new_bookings', data.new_bookings);
                if (typeof data.unread_messages === 'number')
                    emit('unread_messages', data.unread_messages);
                if (data.new_messages && data.new_messages.length)
                    emit('new_messages', data.new_messages);
                if (data.checkin_updates && data.checkin_updates.length)
                    emit('checkin_updates', data.checkin_updates);
                if (data.recent_activity && data.recent_activity.length)
                    emit('recent_activity', data.recent_activity);
                if (typeof data.total_revenue === 'number')
                    emit('total_revenue', data.total_revenue);
                if (data.dashboard_metrics) emit('dashboard_metrics', data.dashboard_metrics);
                if (data.financial_series) emit('financial_series', data.financial_series);
                if (data.top_properties) emit('top_properties', data.top_properties);
                if (data.task_summary) emit('task_summary', data.task_summary);
                if (data.right_panel_activity) emit('right_panel_activity', data.right_panel_activity);
                if (data.admin_notifications) emit('admin_notifications', {
                    items: data.admin_notifications,
                    count: data.admin_notif_count || 0,
                });

                /* ── User events ─────────────────── */
                if (data.booking_updates && data.booking_updates.length)
                    emit('booking_updates', data.booking_updates);
                if (data.booking_stats) emit('booking_stats', data.booking_stats);
                if (data.notifications) emit('notifications', {
                    items: data.notifications,
                    count: data.unread_notif_count || 0,
                });
                if (data.profile_sync) emit('profile_sync', data.profile_sync);
                if (data.user_metrics) emit('user_metrics', data.user_metrics);
                if (data.unit_ratings && data.unit_ratings.length)
                    emit('unit_ratings', data.unit_ratings);
                if (Object.prototype.hasOwnProperty.call(data, 'manage_stay_booking'))
                    emit('manage_stay_booking', data.manage_stay_booking);
            })
            .catch(() => {
                failCount++;
                // Back-off: after 5 failures slow to 30 s
                if (failCount >= 5) reschedule(30000);
            });
    }

    function reschedule(delay) {
        clearInterval(pollTimer);
        pollTimer = setInterval(poll, delay || INTERVAL);
    }

    /* ── Visibility API — pause when tab hidden ─────── */
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            paused = true;
        } else {
            paused = false;
            failCount = 0;
            reschedule(INTERVAL);
            poll(); // immediate catch-up poll
        }
    });

    /* ── Start ──────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        poll(); // first call immediately
        pollTimer = setInterval(poll, INTERVAL);

        fetch(`${API_BASE}?since=2000-01-01 00:00:00&page=${PAGE}&role=${ROLE}&_=${Date.now()}`, {
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                if (data.booking_stats) emit('booking_stats', data.booking_stats);
                if (typeof data.unread_messages === 'number') emit('unread_messages', data.unread_messages);
                if (data.booking_updates && data.booking_updates.length) emit('booking_updates', data.booking_updates);
                if (data.user_metrics) emit('user_metrics', data.user_metrics);
            })
            .catch(() => {});
    });

    /* ── Public API ─────────────────────────────────── */
    window.PSRealtime = {
        pause: () => {
            paused = true;
            clearInterval(pollTimer);
        },
        resume: () => {
            paused = false;
            reschedule(INTERVAL);
            poll();
        },
        poll,
        fmtCurrency,
        statusLabel,
    };

    /* ═══════════════════════════════════════════════════
     *  PAGE HANDLERS — each page sets window.PS_RT_PAGE
     *  and these listeners wire up their own DOM updates.
     * ═══════════════════════════════════════════════════ */

    /* ────────────────────────────────────────────────────
     *  ADMIN: SIDEBAR BADGES
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:booking_stats', e => {
        const s = e.detail;
        const pending = parseInt(s.pending) || 0;

        // Update "Bookings" badge in admin sidebar
        document.querySelectorAll('.nav-badge[data-rt="bookings"]').forEach(el => {
            el.textContent = pending;
            el.style.display = pending > 0 ? 'inline-flex' : 'none';
        });

        // Hide/show chevron based on badge visibility
        document.querySelectorAll('[data-bookings-chevron]').forEach(chevron => {
            chevron.style.display = pending > 0 ? 'none' : 'inline-flex';
        });
    });

    window.addEventListener('ps:unread_messages', e => {
        const count = e.detail || 0;

        // Update "Messages" badge in admin sidebar
        document.querySelectorAll('.nav-badge[data-rt="messages"]').forEach(el => {
            el.textContent = count;
            el.style.display = count > 0 ? 'inline-flex' : 'none';
        });

        // Hide/show chevron based on badge visibility
        document.querySelectorAll('[data-msg-chevron]').forEach(chevron => {
            chevron.style.display = count > 0 ? 'none' : 'inline-flex';
        });
    });

    /* ────────────────────────────────────────────────────
     *  USER: NOTIFICATION BELL
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:notifications', e => {
        const { items, count } = e.detail;

        // Always sync badge to real DB count
        document.querySelectorAll('[data-rt="notif-count"]').forEach(el => {
            el.textContent = count;
            el.style.display = count > 0 ? 'flex' : 'none';
        });

        if (!items.length) return;

        // Live-inject into open dropdown
        const drop = document.getElementById('notifDropdown');
        if (drop && drop.style.display === 'block') {
            const list = document.getElementById('rt-notif-list');
            const empty = document.getElementById('notifEmptyState');
            if (list) {
                if (empty) empty.style.display = 'none';
                items.forEach(n => {
                    if (list.querySelector(`[data-notif-id="${n.id}"]`)) return;
                    const div = _buildNotifItem(n);
                    list.prepend(div);
                });
            }
        }

        // Toast for truly new notifications
        if (typeof showToast === 'function') {
            items.forEach(n => {
                if (n.type === 'booking') return;
                if (!_seenNotifs.has(String(n.id))) {
                    _seenNotifs.add(String(n.id));
                    _persistSeenNotifs();
                    showToast(n.title + (n.body ? ': ' + n.body : ''), 'info');
                }
            });
        }
    });

    /* ────────────────────────────────────────────────────
     *  USER: PROFILE SYNC (name/email/photo/verification)
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:profile_sync', e => {
        const p = e.detail || {};
        const first = String(p.first_name || '').trim();
        const last = String(p.last_name || '').trim();
        const fullName = `${first} ${last}`.trim() || first || 'Guest';
        const email = String(p.email || '').trim();
        const verified = String(p.verification_status || '').toLowerCase() === 'verified';
        const rawPhoto = String(p.profile_photo || '').trim();
        const photoSrc = rawPhoto ? `../../${rawPhoto.replace(/^\/+/, '')}` : '';
        const initials = ((first[0] || '') + (last[0] || '')).toUpperCase() || (first[0] || 'G').toUpperCase();

        // Sidebar + profile header text
        document.querySelectorAll('.sb-name').forEach(el => {
            el.textContent = fullName;
        });
        document.querySelectorAll('.sb-email, .avatar-info p').forEach(el => {
            el.textContent = email;
        });

        // Verification badge text/state
        document.querySelectorAll('.sb-badge').forEach(el => {
            el.classList.toggle('sb-badge-verified', verified);
            el.classList.toggle('sb-badge-unverified', !verified);
            const label = el.querySelector('.sb-badge-label');
            if (label) label.textContent = verified ? 'Email Verified' : 'Email Not Verified';
            const verifyLink = el.querySelector('.sb-verify-link');
            if (verifyLink) verifyLink.style.display = verified ? 'none' : '';
        });

        // Header avatar (small circle)
        document.querySelectorAll('.btn-profile').forEach(btn => {
            const img = btn.querySelector('img');
            const init = btn.querySelector('.profile-initials');
            if (photoSrc) {
                if (img) {
                    if (img.getAttribute('src') !== photoSrc) img.setAttribute('src', photoSrc);
                    img.style.display = 'block';
                }
                if (init) init.style.display = 'none';
            } else {
                if (img) img.style.display = 'none';
                if (init) {
                    init.textContent = initials;
                    init.style.display = 'inline';
                }
            }
        });

        // Sidebar large avatar
        document.querySelectorAll('.sb-avatar').forEach(avatar => {
            const img = avatar.querySelector('img');
            if (photoSrc) {
                if (img) {
                    if (img.getAttribute('src') !== photoSrc) img.setAttribute('src', photoSrc);
                    img.style.display = 'block';
                } else {
                    const newImg = document.createElement('img');
                    newImg.src = photoSrc;
                    newImg.alt = 'Profile photo';
                    avatar.textContent = '';
                    avatar.appendChild(newImg);
                }
            } else {
                if (img) img.style.display = 'none';
                avatar.textContent = initials;
            }
        });
    });

    /* ────────────────────────────────────────────────────
     *  USER: METRICS SYNC (points/saved/tier)
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:user_metrics', e => {
        const m = e.detail || {};
        const points = parseInt(m.loyalty_points, 10);
        const saved = parseInt(m.saved_count, 10);
        const tier = String(m.loyalty_tier || '').trim();

        if (!Number.isNaN(points)) {
            const fmtPoints = Number(points).toLocaleString('en-PH');
            document.querySelectorAll('[data-rt-user="loyalty_points"]').forEach(el => {
                el.textContent = fmtPoints;
            });
            document.querySelectorAll('[data-rt-user="loyalty_points_text"]').forEach(el => {
                el.textContent = `${fmtPoints} points`;
            });
        }

        if (!Number.isNaN(saved)) {
            document.querySelectorAll('[data-rt-user="saved_count"]').forEach(el => {
                el.textContent = String(saved);
            });
            document.querySelectorAll('[data-rt-user="saved_count_text"]').forEach(el => {
                el.textContent = `${saved} on wishlist`;
            });
        }

        if (tier) {
            document.querySelectorAll('[data-rt-user="loyalty_tier"]').forEach(el => {
                el.textContent = tier;
            });
        }
    });

    /* ────────────────────────────────────────────────────
     *  USER: BOOKINGS PAGE — status badge live update
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:booking_updates', e => {
        const updates = e.detail;
        updates.forEach(b => {
            const id = String(b.booking_id);
            const lbl = statusLabel(b.status);

            const badge = document.querySelector(`[data-booking-id="${id}"] .booking-status-badge`);
            if (badge) {
                badge.textContent = lbl.text;
                badge.className = 'booking-status-badge ' + lbl.cls;
                badge.setAttribute('data-status', b.status);
            }

            const card = document.querySelector(`[data-booking-id="${id}"]`);
            if (card) card.dataset.status = b.status;

            const prev = _knownStatuses.get(id);
            if (prev && prev !== b.status && typeof showToast === 'function') {
                showToast(
                    `Booking #BK-${id.padStart(4, '0')} is now ${lbl.text}.`,
                    b.status === 'cancelled' ? 'warning' : 'success'
                );
            }
            _knownStatuses.set(id, b.status);

            const actionsWrap = document.querySelector(`[data-booking-id="${id}"] .booking-actions`);
            if (actionsWrap) {
                const cancelBtn = actionsWrap.querySelector('[data-action="cancel"]');
                if (cancelBtn && ['cancelled', 'completed', 'active'].includes(b.status)) {
                    cancelBtn.style.display = 'none';
                }
            }
        });

        _updateUserStatPills(e);
    });

    const _knownStatuses = new Map();

    /* ────────────────────────────────────────────────────
     *  USER: STAT PILLS (upcoming / active / completed)
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:booking_stats', e => {
        _updateUserStatPills(e);
    });

    function _updateUserStatPills(e) {
        // Works for both booking_stats and booking_updates payloads
        const detail = e.detail;
        const s = detail.booking_stats || detail;
        if (!s || typeof s !== 'object') return;

        const map = {
            'upcoming': s.upcoming || 0,
            'active': s.active_cnt || 0,
            'completed': s.completed || 0,
            'cancelled': s.cancelled || 0,
        };
        Object.entries(map).forEach(([k, v]) => {
            document.querySelectorAll(`[data-rt-stat="${k}"]`).forEach(el => {
                el.textContent = v;
            });
        });
    }

    /* ────────────────────────────────────────────────────
     *  ADMIN: RESERVATIONS PAGE — live row injection
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:new_bookings', e => {
        const bookings = e.detail;
        if (!bookings.length) return;

        // Update the "new booking" banner if it exists
        const banner = document.getElementById('newBookingBanner');
        const bannerText = document.getElementById('newBookingText');

        const truly = bookings.filter(b => !_adminKnownIds.has(String(b.booking_id)));
        if (!truly.length) {
            // Only status changes — silently update rows
            bookings.forEach(_updateAdminRow);
            return;
        }

        truly.forEach(b => _adminKnownIds.add(String(b.booking_id)));

        // On the reservations page, the inline NEW badge handles notification.
        // On other pages, the notification bell badge count is sufficient —
        // no toast needed here.
        if (banner && bannerText) {
            bannerText.textContent =
                `${truly.length} new booking${truly.length > 1 ? 's' : ''} received! Click to refresh the table.`;
            banner.classList.add('show');
        }

        // Also silently update existing rows that changed status
        bookings.filter(b => _adminKnownIds.has(String(b.booking_id))).forEach(_updateAdminRow);
    });

    /** Patch a row's status badge and action buttons in-place */
    function _updateAdminRow(b) {
        const id = String(b.booking_id);
        const row = document.querySelector(`tr[data-booking-id="${id}"]`);
        if (!row) return;

        const lbl = statusLabel(b.status);
        const prev = row.dataset.status;

        if (prev === b.status) return; // no change
        row.dataset.status = b.status;

        // Status badge cell
        const badgeCell = row.querySelector('.status-badge, .badge, [data-rt-badge]');
        if (badgeCell) {
            badgeCell.textContent = lbl.text;
            badgeCell.className = badgeCell.className.replace(/badge-\w+/g, '') + ' ' + lbl.cls;
        }

        // Flash the row
        row.style.transition = 'background 0.6s';
        row.style.background = 'var(--blue-50, #eff6ff)';
        setTimeout(() => {
            row.style.background = '';
        }, 1800);

        if (typeof showToast === 'function' && prev) {
            showToast(`Booking #BK-${id.padStart(4, '0')} updated to ${lbl.text}.`, 'info');
        }
    }

    /* ────────────────────────────────────────────────────
     *  ADMIN: DASHBOARD KPIs
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:booking_stats', e => {
        const s = e.detail;
        _setInnerText('[data-rt-kpi="total_bookings"]', s.total || 0);
        _setInnerText('[data-rt-kpi="pending_bookings"]', s.pending || 0);
        _setInnerText('[data-rt-kpi="confirmed_bookings"]', (parseInt(s.confirmed) || 0));
        _setInnerText('[data-rt-kpi="completed_bookings"]', s.completed || 0);
        _setInnerText('[data-rt-kpi="cancelled_bookings"]', s.cancelled || 0);
    });

    window.addEventListener('ps:unit_stats', e => {
        const u = e.detail;
        const total = parseInt(u.total) || 1;
        const occupied = parseInt(u.occupied) || 0;
        const rate = Math.round((occupied / total) * 100);
        _setInnerText('[data-rt-kpi="occupancy_rate"]', rate + '%');
        _setInnerText('[data-rt-kpi="occupied_units"]', occupied);
        _setInnerText('[data-rt-kpi="vacant_units"]', u.vacant || 0);
        _setInnerText('[data-rt-kpi="maintenance_units"]', u.maintenance || 0);

        // Update donut chart if chart.js instance stored
        const inst = window._psOccupancyChart;
        if (inst) {
            inst.data.datasets[0].data = [occupied, u.vacant || 0, u.maintenance || 0];
            inst.update('none');
        }
    });

    window.addEventListener('ps:total_revenue', e => {
        const rev = e.detail;
        _setInnerText('[data-rt-kpi="total_revenue"]',
            '₱ ' + Math.round(rev / 1000) + 'K');
    });

    /* ────────────────────────────────────────────────────
     *  ADMIN: DASHBOARD RECENT ACTIVITY FEED
     * ──────────────────────────────────────────────────── */
    // window.addEventListener('ps:recent_activity', e => {
    //     const items = e.detail;
    //     const feed = document.getElementById('rt-activity-feed');
    //     if (!feed) return;

    //     items.forEach(b => {
    //         const key = `act-${b.booking_id}-${b.status}`;
    //         if (feed.querySelector(`[data-act-key="${key}"]`)) return;

    //         const lbl = statusLabel(b.status);
    //         const div = document.createElement('div');
    //         div.className = 'rt-activity-item';
    //         div.dataset.actKey = key;
    //         div.innerHTML = `
    //             <div class="rt-act-dot" style="background:${_statusColor(b.status)}"></div>
    //             <div class="rt-act-body">
    //                 <span class="rt-act-name">${_escHtml(b.user_name)}</span>
    //                 booked <strong>${_escHtml(b.unit_name)}</strong>
    //                 — <span class="rt-act-badge ${lbl.cls}">${lbl.text}</span>
    //             </div>
    //             <div class="rt-act-time">${_relativeTime(b.created_at)}</div>`;
    //         feed.prepend(div);

    //         // Keep max 8 items
    //         while (feed.children.length > 8) feed.lastElementChild.remove();
    //     });
    // });

    /* ────────────────────────────────────────────────────
     *  ADMIN: MESSAGES — unread thread highlight
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:new_messages', e => {
        const msgs = e.detail;
        msgs.forEach(m => {
            if (_seenMsgIds.has(String(m.id))) return;
            _seenMsgIds.add(String(m.id));
            _persistSeenMsgIds();

            // Highlight the thread item in the sidebar list
            const thread = document.querySelector(`.msg-thread[data-user-id="${m.sender_id}"]`);
            if (thread) {
                thread.classList.add('has-unread');
                const preview = thread.querySelector('.thread-preview');
                if (preview) preview.textContent = m.body || m.subject || 'New message';
            }

            if (typeof showToast === 'function') {
                showToast(
                    `New message from ${_escHtml(m.sender_name || 'a guest')}`,
                    'info'
                );
            }
        });
    });

    /* ────────────────────────────────────────────────────
     *  ADMIN: CHECK-IN/OUT updates
     * ──────────────────────────────────────────────────── */
    window.addEventListener('ps:checkin_updates', e => {
        const updates = e.detail;
        updates.forEach(b => {
            const id = String(b.booking_id);
            const row = document.querySelector(`tr[data-booking-id="${id}"], [data-booking-id="${id}"]`);
            if (!row) return;

            const lbl = statusLabel(b.status);
            const badge = row.querySelector('.badge, .status-badge, [data-rt-badge]');
            if (badge) {
                badge.textContent = lbl.text;
                badge.className = badge.className.replace(/badge-\w+/g, '') + ' ' + lbl.cls;
            }
            row.style.transition = 'background 0.5s';
            row.style.background = 'var(--blue-50, #eff6ff)';
            setTimeout(() => {
                row.style.background = '';
            }, 1500);
        });
    });

    /* ────────────────────────────────────────────────────
     *  USER: NOTIFICATION BELL — dropdown panel
     * ──────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        const bellBtn = document.getElementById('notifBellBtn');
        if (!bellBtn) return;

        // Build dropdown panel
        const wrap = bellBtn.closest('div') || bellBtn.parentElement;
        wrap.style.position = 'relative';

        const drop = document.createElement('div');
        drop.id = 'notifDropdown';
        drop.style.cssText = [
            'display:none;position:absolute;top:calc(100% + 8px);right:0;',
            'width:320px;max-height:420px;overflow-y:auto;',
            'background:#fff;border:1px solid #e2e8f0;border-radius:12px;',
            'box-shadow:0 8px 32px rgba(0,0,0,0.13);z-index:9999;',
            'font-family:inherit;'
        ].join('');

        function placeNotifDropdown() {
            const rect = bellBtn.getBoundingClientRect();
            const isMobile = window.innerWidth <= 640;
            if (isMobile) {
                const top = Math.min(rect.bottom + 8, window.innerHeight - 220);
                drop.style.position = 'fixed';
                drop.style.top = `${Math.max(10, top)}px`;
                drop.style.right = '12px';
                drop.style.left = '12px';
                drop.style.width = 'auto';
                drop.style.maxHeight = `calc(100vh - ${Math.max(10, top) + 12}px)`;
                drop.style.borderRadius = '14px';
            } else {
                drop.style.position = 'absolute';
                drop.style.top = 'calc(100% + 8px)';
                drop.style.right = '0';
                drop.style.left = 'auto';
                drop.style.width = '320px';
                drop.style.maxHeight = '420px';
                drop.style.borderRadius = '12px';
            }
        }

        drop.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding:14px 16px 10px;border-bottom:1px solid #f1f5f9;">
                <span style="font-weight:700;font-size:0.95rem;color:#1e293b;">Notifications</span>
                <button id="notifMarkAllBtn"
                    style="font-size:0.75rem;color:#2563eb;background:none;border:none;
                           cursor:pointer;padding:2px 6px;border-radius:6px;
                           transition:background 0.15s;"
                    onmouseenter="this.style.background='#eff6ff'"
                    onmouseleave="this.style.background='none'">
                    Mark all read
                </button>
            </div>
            <div id="rt-notif-list" style="padding:4px 0;">
                <div id="notifEmptyState"
                     style="padding:32px 16px;text-align:center;color:#94a3b8;font-size:0.85rem;">
                    No new notifications
                </div>
            </div>`;

        wrap.appendChild(drop);

        // Add CSS for notif items dynamically
        const style = document.createElement('style');
        style.textContent = `
            .rt-notif-item {
                padding: 12px 16px;
                border-bottom: 1px solid #f8fafc;
                cursor: pointer;
                transition: background 0.15s;
            }
            .rt-notif-item:hover { background: #f8fafc; }
            .rt-notif-item:last-child { border-bottom: none; }
            .rt-notif-title {
                font-size: 0.83rem;
                font-weight: 600;
                color: #1e293b;
                margin-bottom: 2px;
            }
            .rt-notif-body {
                font-size: 0.78rem;
                color: #64748b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .rt-notif-time {
                font-size: 0.72rem;
                color: #94a3b8;
                margin-top: 4px;
            }
            #notifDropdown::-webkit-scrollbar { width: 4px; }
            #notifDropdown::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 99px; }
            @media (max-width: 640px) {
                .rt-notif-body {
                    white-space: normal;
                    overflow: visible;
                    text-overflow: initial;
                    line-height: 1.4;
                }
            }
        `;
        document.head.appendChild(style);

        // Toggle open/close
        bellBtn.addEventListener('click', e => {
            e.stopPropagation();
            const isOpen = drop.style.display === 'block';
            drop.style.display = isOpen ? 'none' : 'block';
            if (!isOpen) {
                placeNotifDropdown();

                // Load fresh notifications when opening
                fetch('../../api/user/notifications.php?limit=20')
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) return;
                        const list = document.getElementById('rt-notif-list');
                        const empty = document.getElementById('notifEmptyState');
                        const items = data.notifications || [];

                        // Clear existing items (keep empty state node)
                        list.querySelectorAll('.rt-notif-item').forEach(n => n.remove());

                        if (!items.length) {
                            if (empty) empty.style.display = '';
                            return;
                        }
                        if (empty) empty.style.display = 'none';

                        items.forEach(n => {
                            const div = _buildNotifItem(n);
                            list.appendChild(div);
                        });

                        // Set badge to real unread count from DB (don't zero it)
                        document.querySelectorAll('[data-rt="notif-count"]').forEach(el => {
                            el.textContent = data.unread_count;
                            el.style.display = data.unread_count > 0 ? 'flex' : 'none';
                        });
                    })
                    .catch(() => {});
            }
        });

        window.addEventListener('resize', () => {
            if (drop.style.display === 'block') placeNotifDropdown();
        });

        // Mark all read button
        const markAllBtn = drop.querySelector('#notifMarkAllBtn');
        markAllBtn?.addEventListener('click', e => {
            e.preventDefault();
            e.stopPropagation();
            const fd = new FormData();
            fd.append('action', 'mark_all_read');
            if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
            fetch('../../api/user/notifications.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then((data) => {
                    if (!data || data.success !== true) return;
                    document.querySelectorAll('.rt-notif-item').forEach(el => {
                        el.style.opacity = '0.6';
                    });
                    document.querySelectorAll('[data-rt="notif-count"]').forEach(el => {
                        el.textContent = '0';
                        el.style.display = 'none';
                    });
                    // Also clear localStorage seen set so new notifs will show toasts
                    try {
                        localStorage.removeItem('ps_seen_notifs');
                    } catch (e) {}
                    _seenNotifs.clear();
                })
                .catch(() => {});
        });

        // Close when clicking outside
        document.addEventListener('click', () => {
            drop.style.display = 'none';
        });
        drop.addEventListener('click', e => e.stopPropagation());
    });

    function _escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function _setInnerText(selector, value) {
        document.querySelectorAll(selector).forEach(el => {
            el.textContent = value;
        });
    }

    function _statusColor(s) {
        const c = {
            pending: '#f59e0b',
            confirmed: '#2563c4',
            active: '#16a34a',
            completed: '#6b7280',
            cancelled: '#dc2626'
        };
        return c[s] || '#94a3b8';
    }

    function _relativeTime(ts) {
        if (!ts) return '';
        const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    /* ── Expose helpers globally ────────────────────── */
    window.PS = window.PS || {};
    window.PS.escHtml = _escHtml;
    window.PS.relativeTime = _relativeTime;
    window.PS.statusLabel = statusLabel;
    window.PS.fmtCurrency = fmtCurrency;

    function _buildNotifItem(n) {
        const div = document.createElement('div');
        div.className = 'rt-notif-item';
        div.dataset.notifId = n.id;
        if (n.is_read == 1) {
            div.style.opacity = '0.6';
        } else {
            div.style.background = '#f0f7ff';
        }
        div.innerHTML = `
        <div class="rt-notif-title">${_escHtml(n.title)}</div>
        <div class="rt-notif-body">${_escHtml(n.body || '')}</div>
        <div class="rt-notif-time">${_relativeTime(n.created_at)}</div>`;
        div.addEventListener('click', () => {
            if (n.is_read != 1) {
                const fd = new FormData();
                fd.append('action', 'mark_read');
                fd.append('id', n.id);
                if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
                fetch('../../api/user/notifications.php', {
                    method: 'POST',
                    body: fd
                }).catch(() => {});
                div.style.opacity = '0.6';
                div.style.background = '';
                n.is_read = 1;
                document.querySelectorAll('[data-rt="notif-count"]').forEach(el => {
                    const cur = parseInt(el.textContent) || 0;
                    const next = Math.max(0, cur - 1);
                    el.textContent = next;
                    el.style.display = next > 0 ? 'flex' : 'none';
                });
            }
            if (n.link) window.location.href = '../../' + n.link;
        });
        return div;
    }


})();