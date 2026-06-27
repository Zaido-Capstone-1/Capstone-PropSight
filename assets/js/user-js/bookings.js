let bookingsData = [];
let currentInvoiceId = null;
let selectedRating = 0;
let cancelBookingId = null;
let cancelBookingRef = null;
let reviewBookingId = null;
let reviewBookingIdx = null;

function escHtml(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

window.addEventListener('DOMContentLoaded', () => {
    bookingsData = window.__bookings || [];
    console.log('Bookings loaded:', bookingsData);
});


function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

function openDetailsModal(idx) {
    const b = (window.__bookings || [])[idx];
    if (!b) {
        console.error('No booking at index', idx);
        return;
    }

    currentInvoiceId = b.id; // e.g. "BK-000001"

    document.getElementById('detailsRoomName').textContent = b.room || 'Room';
    document.getElementById('detailsBookingId').textContent = 'Booking ID: ' + b.id;

    document.getElementById('detailsBody').innerHTML = `
        <div style="width:100%;height:160px;border-radius:12px;overflow:hidden;margin-bottom:18px;background:linear-gradient(145deg,#93c5fd,#2563c4);">
            <img src="${escHtml(b.img || '')}"
                 alt="${escHtml(b.room || '')}"
                 style="width:100%;height:100%;object-fit:cover;display:block;"
                 onerror="this.style.display='none'">
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
            <tr style="border-bottom:1px solid var(--blue-50);">
                <td style="padding:10px 0;color:var(--text-soft);font-weight:600;font-size:0.72rem;letter-spacing:0.06em;text-transform:uppercase;width:140px;">Location</td>
                <td style="padding:10px 0;color:var(--text-dark);">${escHtml(b.floor || '—')}</td>
            </tr>
            <tr style="border-bottom:1px solid var(--blue-50);">
                <td style="padding:10px 0;color:var(--text-soft);font-weight:600;font-size:0.72rem;letter-spacing:0.06em;text-transform:uppercase;">Check-in</td>
                <td style="padding:10px 0;color:var(--text-dark);font-weight:600;">${escHtml(b.checkin || '—')}</td>
            </tr>
            <tr style="border-bottom:1px solid var(--blue-50);">
                <td style="padding:10px 0;color:var(--text-soft);font-weight:600;font-size:0.72rem;letter-spacing:0.06em;text-transform:uppercase;">Check-out</td>
                <td style="padding:10px 0;color:var(--text-dark);font-weight:600;">${escHtml(b.checkout || '—')}</td>
            </tr>
            <tr style="border-bottom:1px solid var(--blue-50);">
                <td style="padding:10px 0;color:var(--text-soft);font-weight:600;font-size:0.72rem;letter-spacing:0.06em;text-transform:uppercase;">Duration</td>
                <td style="padding:10px 0;color:var(--text-dark);">${b.nights} night${b.nights !== 1 ? 's' : ''}</td>
            </tr>
            <tr style="border-bottom:1px solid var(--blue-50);">
                <td style="padding:10px 0;color:var(--text-soft);font-weight:600;font-size:0.72rem;letter-spacing:0.06em;text-transform:uppercase;">Status</td>
                <td style="padding:10px 0;text-transform:capitalize;">${escHtml(b.status)}</td>
            </tr>
            <tr>
                <td style="padding:10px 0;color:var(--text-soft);font-weight:600;font-size:0.72rem;letter-spacing:0.06em;text-transform:uppercase;">Total Amount</td>
                <td style="padding:10px 0;font-size:1.1rem;font-weight:700;color:var(--blue-500);">₱${Number(b.total).toLocaleString()}</td>
            </tr>
        </table>`;

    openModal('detailsModal');
}

function downloadInvoice(bookingId) {
    showToast('Invoice for ' + bookingId + ' downloaded!');
}

function downloadInvoiceFromModal() {
    closeModal('detailsModal');
    showToast('Invoice for ' + currentInvoiceId + ' downloaded!');
}

function openCancelModal(idx, ref) {
    cancelBookingId = (window.__bookings || [])[idx]?.booking_id || null;
    cancelBookingRef = ref;
    if (!confirm(`Cancel booking ${ref}? This action cannot be undone.`)) return;
    doCancel();
}

function doCancel() {
    if (!cancelBookingId) return;
    const fd = new FormData();
    fd.append('booking_id', cancelBookingId);
    fd.append('reason', 'User-requested cancellation');
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
    showToast('Cancelling booking…', 'info');
    fetch('../../api/user/cancel_booking.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(d.message, 'success', 'Booking Cancelled');

                // ── Update card in-place — no reload ──────────
                const id = String(cancelBookingId);
                const card = document.querySelector(`[data-booking-id="${id}"]`);
                if (card) {
                    // Update status badge
                    const badge = card.querySelector('.booking-status-badge');
                    if (badge) {
                        badge.textContent = 'Cancelled';
                        badge.className = 'badge badge-red booking-status-badge';
                        badge.dataset.status = 'cancelled';
                    }
                    // Update card filter state
                    card.dataset.status = 'cancelled';
                    card.classList.add('cancelled');

                    // Swap action buttons to disabled state
                    const actions = card.querySelector('.booking-actions');
                    if (actions) {
                        actions.innerHTML = '<button class="bc-btn-ghost" style="cursor:default;opacity:0.45;" disabled>Cancelled</button>';
                    }

                    // Flash outline
                    card.style.transition = 'outline 0.3s';
                    card.style.outline = '2px solid #fca5a5';
                    setTimeout(() => {
                        card.style.outline = '';
                    }, 2500);
                }

                // Update stat pills
                const upcomingEl = document.querySelector('[data-rt-stat="upcoming"]');
                const cancelledEl = document.querySelector('[data-rt-stat="cancelled"]');
                if (upcomingEl) upcomingEl.textContent = Math.max(0, (parseInt(upcomingEl.textContent) || 1) - 1);
                if (cancelledEl) cancelledEl.textContent = (parseInt(cancelledEl.textContent) || 0) + 1;

            } else {
                showToast(d.message, 'error', 'Cannot Cancel');
            }
        })
        .catch(() => showToast('Network error. Please try again.', 'error'));
}

function openReviewModal(roomName, bookingId, bookingIdx) {
    selectedRating = 0;
    reviewBookingId = Number(bookingId) || null;
    reviewBookingIdx = Number.isFinite(Number(bookingIdx)) ? Number(bookingIdx) : null;
    document.getElementById('reviewText').value = '';
    document.getElementById('reviewRoomName').textContent = roomName;
    document.getElementById('reviewError').style.display = 'none';
    updateStars(0);
    openModal('reviewModal');
}

function setRating(val) {
    selectedRating = val;
    updateStars(val);
}

function hoverRating(val) {
    updateStars(val);
}

function resetHover() {
    updateStars(selectedRating);
}


function updateStars(val) {
    document.querySelectorAll('#starRating svg').forEach((s, i) => {
        s.style.fill = i < val ? 'var(--gold)' : 'var(--blue-100)';
        s.style.stroke = i < val ? 'var(--gold-dk)' : 'var(--blue-200)';
        s.style.transform = i < val ? 'scale(1.1)' : 'scale(1)';
    });
}

function submitReview() {
    const errEl = document.getElementById('reviewError');
    errEl.style.display = 'none';
    if (!reviewBookingId) {
        errEl.textContent = 'Invalid booking selected for review.';
        errEl.style.display = 'block';
        return;
    }
    if (!selectedRating) {
        errEl.textContent = 'Please select a star rating.';
        errEl.style.display = 'block';
        return;
    }
    if (!document.getElementById('reviewText').value.trim()) {
        errEl.textContent = 'Please write a short review.';
        errEl.style.display = 'block';
        return;
    }
    const btn = document.getElementById('submitReviewBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting…';
    const fd = new FormData();
    fd.append('booking_id', String(reviewBookingId));
    fd.append('rating', String(selectedRating));
    fd.append('comment', document.getElementById('reviewText').value.trim());
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    fetch('../../api/user/submit_review.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                errEl.textContent = d.message || 'Could not submit review right now.';
                errEl.style.display = 'block';
                return;
            }
            closeModal('reviewModal');
            showToast('Review submitted! Thank you 🌟', 'success');

            if (reviewBookingIdx !== null && window.__bookings?. [reviewBookingIdx]) {
                window.__bookings[reviewBookingIdx].reviewed = true;
                window.__bookings[reviewBookingIdx].review_rating = selectedRating;
            }
            const card = document.querySelector(`[data-booking-id="${reviewBookingId}"]`);
            const actions = card?.querySelector('.booking-actions');
            if (actions) {
                const leaveBtn = actions.querySelector('.bc-btn-ghost');
                if (leaveBtn) {
                    leaveBtn.textContent = `Reviewed · ${selectedRating}/5`;
                    leaveBtn.disabled = true;
                    leaveBtn.style.cursor = 'default';
                    leaveBtn.style.opacity = '0.7';
                    leaveBtn.removeAttribute('onclick');
                }
            }
        })
        .catch(() => {
            errEl.textContent = 'Network error while submitting review.';
            errEl.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Submit Review';
        });
}

// =========================
// TAB FILTER
// =========================
function filterBookings(status, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.booking-card').forEach(card => {
        card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
}

// =========================
// BACKDROP & ESC
// =========================
document.addEventListener('DOMContentLoaded', () => {
    ['detailsModal', 'reviewModal'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', e => {
            if (e.target.id === id) closeModal(id);
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            ['detailsModal', 'reviewModal'].forEach(closeModal);
            if (typeof closeSidebar === 'function') closeSidebar();
        }
    });
});