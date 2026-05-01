showToast("You do not have permission to access this page.", "error", "Unauthorized"); setTimeout(() => history.back(), 2000);

var _bookingJSReady = true;

// ── Real-time status map for user bookings ──
    window.PS_RT_PAGE = 'bookings';

    // Badge class map matching PHP $status_map
    const _bkStatusMap = {
        pending: {
            label: 'Pending',
            cls: 'badge-blue'
        },
        confirmed: {
            label: 'Upcoming',
            cls: 'badge-blue'
        },
        active: {
            label: 'Active',
            cls: 'badge-green'
        },
        completed: {
            label: 'Completed',
            cls: 'badge-green'
        },
        cancelled: {
            label: 'Cancelled',
            cls: 'badge-red'
        },
    };

    window.addEventListener('ps:booking_updates', e => {
        e.detail.forEach(b => {
            const id = String(b.booking_id);
            const card = document.querySelector(`[data-booking-id="${id}"]`);
            if (!card) return;

            const info = _bkStatusMap[b.status] || {
                label: b.status,
                cls: 'badge-blue'
            };

            // Update status badge
            const badge = card.querySelector('.booking-status-badge');
            if (badge) {
                badge.textContent = info.label;
                badge.className = `badge ${info.cls} booking-status-badge`;
                badge.dataset.status = b.status;
            }

            // Update card's data-status for tab filter
            const dispSt = (b.status === 'confirmed' || b.status === 'pending') ? 'upcoming' : b.status;
            card.dataset.status = dispSt;

            // Hide cancel button when no longer cancellable
            if (['completed', 'cancelled'].includes(b.status)) {
                const cancelBtn = card.querySelector('[data-action="cancel"]');
                if (cancelBtn) cancelBtn.style.display = 'none';
            }

            const prevBadge = badge?.dataset.prevStatus;
            const changed = prevBadge && prevBadge !== b.status;

            // Flash only when status actually changed.
            if (changed) {
                card.style.transition = 'box-shadow 0.4s, outline 0.4s';
                card.style.outline = '2px solid var(--blue-300, #93c5fd)';
                setTimeout(() => {
                    card.style.outline = '';
                }, 2200);
            }

            // Toast only on meaningful status changes
            if (changed) {
                showToast(`Your booking is now ${info.label}.`,
                    b.status === 'cancelled' ? 'warning' : 'success');
            }
            if (badge) badge.dataset.prevStatus = b.status;
        });
    });
