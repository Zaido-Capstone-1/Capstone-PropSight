// assets/js/user-js/refund.js
// Handles both booking refunds and invoice refunds.
// Loaded by pages/user/payment.php after payment.js
// Depends on: showToast() and window.PS_CSRF_TOKEN (set by payment.js / layout)

let _refundBookingId = null;
let _refundInvoiceId = null;  // null for booking refunds, integer for invoice refunds

/**
 * Open for a BOOKING refund (existing flow, unchanged)
 */
function openRefundModal(bookingId, label, amount) {
    _refundBookingId = bookingId;
    _refundInvoiceId = null;
    document.getElementById('refundModalDesc').textContent = label + ' · ₱' + amount;
    document.getElementById('refundReason').value = '';
    document.getElementById('refundModal').classList.add('open');
    document.getElementById('refundReason').focus();
}

/**
 * Open for an INVOICE refund (new flow)
 */
function openInvoiceRefundModal(invoiceId, label, amount) {
    _refundInvoiceId = invoiceId;
    _refundBookingId = null;
    document.getElementById('refundModalDesc').textContent = label + ' · ₱' + amount;
    document.getElementById('refundReason').value = '';
    document.getElementById('refundModal').classList.add('open');
    document.getElementById('refundReason').focus();
}

function closeRefundModal() {
    document.getElementById('refundModal').classList.remove('open');
    _refundBookingId = null;
    _refundInvoiceId = null;
}

function submitRefundRequest() {
    const reason = document.getElementById('refundReason').value.trim();
    if (reason.length < 10) {
        document.getElementById('refundReason').focus();
        showToast('Please provide a more detailed reason (at least 10 characters).', true);
        return;
    }

    const btn = document.getElementById('refundSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Submitting…';

    const fd = new FormData();
    fd.append('reason',     reason);
    fd.append('csrf_token', window.PS_CSRF_TOKEN ?? '');

    let endpoint;
    if (_refundInvoiceId) {
        // Invoice refund → new endpoint
        fd.append('invoice_id', _refundInvoiceId);
        endpoint = '../../endpoints/user/request_invoice_refund.php';
    } else {
        // Booking refund → existing endpoint
        fd.append('booking_id', _refundBookingId);
        endpoint = '../../endpoints/user/request_refund.php';
    }

    fetch(endpoint, { method: 'POST', body: fd })
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