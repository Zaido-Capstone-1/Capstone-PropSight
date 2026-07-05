window._psToastReady = true;
    document.addEventListener("DOMContentLoaded", function() {
        showToast("You do not have permission to access this page.", "error", "Unauthorized");
    });
    setTimeout(() => history.back(), 2000);

function refreshCalendarView() {
    // For calendar, we need to refresh blocked dates and re-render
    // Fetch current month's blocked dates
    const params = new URLSearchParams(window.location.search);
    fetch('../../endpoints/admin/block_date.php?action=list&' + params.toString() + '&_=' + Date.now(), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) { location.reload(); return; }
            // Update blocked day cells
            const blocked = new Set((data.blocked_dates || []).map(d => d.date || d));
            document.querySelectorAll('.cal-day').forEach(cell => {
                const cellDate = cell.dataset.date;
                if (!cellDate) return;
                if (blocked.has(cellDate)) {
                    cell.classList.add('blocked');
                } else {
                    cell.classList.remove('blocked');
                }
            });
        })
        .catch(() => location.reload());
}
