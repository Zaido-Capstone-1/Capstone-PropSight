function updateStatus(bookingId, newStatus, btn) {
    const labels = { confirmed: 'confirm', cancelled: 'cancel', completed: 'mark as completed' };

    if (!confirm(`Are you sure you want to ${labels[newStatus]} booking #BK-${String(bookingId).padStart(4, '0')}?`)) return;

    showToast('Updating booking status…', 'info');

    fetch('../../api/reservations.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_status', booking_id: bookingId, status: newStatus })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success', 'Updated!');
            setTimeout(() => refreshTable(true), 1200);
        } else {
            showToast(data.message || 'Could not update booking.', 'error', 'Failed');
        }
    })
    .catch(() => showToast('Server unreachable. Please try again.', 'error'));
}

