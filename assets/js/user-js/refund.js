// ── Refund Request Modal ──────────────────────────────────────────────────────
// Loaded by pages/user/payment.php after payment.js
// Depends on: showToast() and window.PS_CSRF_TOKEN (both set by payment.js / layout)

let _refundBookingId = null;

function openRefundModal(bookingId, label, amount) {
    _refundBookingId = bookingId;
    document.getElementById('refundModalDesc').textContent =
        label + ' · ₱' + amount;
    document.getElementById('refundReason').value = '';
    document.getElementById('refundModal').classList.add('open');
    document.getElementById('refundReason').focus();
}

function closeRefundModal() {
    document.getElementById('refundModal').classList.remove('open');
    _refundBookingId = null;
}

function submitRefundRequest() {
    const reason = document.getElementById('refundReason').value.trim();
    if (!reason) {
        document.getElementById('refundReason').focus();
        return;
    }

    const btn = document.getElementById('refundSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting…';

    const fd = new FormData();
    fd.append('booking_id', _refundBookingId);
    fd.append('reason', reason);
    fd.append('csrf_token', window.PS_CSRF_TOKEN ?? '');

    fetch('../../api/user/request_refund.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeRefundModal();
                showToast(data.message ?? 'Refund request submitted.', false);
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast(data.message ?? 'Something went wrong.', true);
            }
        })
        .catch(() => showToast('Network error. Please try again.', true))
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Submit Request';
        });
}

document.addEventListener('DOMContentLoaded', () => {
    const backdrop = document.getElementById('refundModal');
    if (backdrop) {
        backdrop.addEventListener('click', e => {
            if (e.target === backdrop) closeRefundModal();
        });
    }
});