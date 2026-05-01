showToast("You do not have permission to access this page.", "error", "Unauthorized"); setTimeout(() => history.back(), 2000);

function removePaymentMethod(id, type, btn) {
        if (!confirm('Remove this payment method?')) return;
        const fd = new FormData();
        fd.append('action', 'remove');
        fd.append('id', id);
        fd.append('type', type);
        if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
        fetch('../../api/user/payment_methods.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json()).then(d => {
                if (d.success) {
                    const card = btn.closest('.payment-card,.ewallet-row,tr');
                    card.style.opacity = '0';
                    card.style.transition = 'opacity .3s';
                    setTimeout(() => card.remove(), 300);
                    showToast('Payment method removed.');
                } else showToast(d.message || 'Error', true);
            }).catch(() => showToast('Network error.', true));
    }

    function setDefault(id, type) {
        const fd = new FormData();
        fd.append('action', 'set_default');
        fd.append('id', id);
        fd.append('type', type);
        if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
        fetch('../../api/user/payment_methods.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json()).then(d => showToast(d.message || 'Updated', !d.success))
            .catch(() => showToast('Network error.', true));
    }

window.PS_RT_PAGE = 'payment';
