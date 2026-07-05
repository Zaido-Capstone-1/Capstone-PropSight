showToast("You do not have permission to access this page.", "error", "Unauthorized"); setTimeout(() => history.back(), 2000);

// Keep this handler in-page because checkboxes call saveNotifications() inline.
function saveNotifications() {
    const keys = ['notif_booking_confirm', 'notif_checkin_remind', 'notif_promotions', 'notif_loyalty', 'notif_newsletter'];
    const fd = new FormData();
    fd.append('action', 'update_notifications');
    keys.forEach(k => {
        const el = document.getElementById(k);
        if (el) fd.append(k, el.checked ? '1' : '0');
    });
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
    fetch('../../endpoints/user/settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (typeof showToast === 'function') {
                showToast(d.message || (d.success ? 'Settings saved.' : 'Could not save settings.'), d.success ? 'success' : 'error');
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Network error while saving settings.', 'error');
        });
}

window.PS_RT_PAGE = 'settings';
