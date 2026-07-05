let st2;

/* =========================
   Invite Modal
========================= */

function openInvite() {
    const overlay = document.getElementById('inviteOverlay');

    if (overlay) {
        overlay.classList.add('open');
    }
}

function closeInvite() {
    const overlay = document.getElementById('inviteOverlay');

    if (overlay) {
        overlay.classList.remove('open');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('open-invite-modal')?.addEventListener('click', openInvite);

    const inviteOverlay = document.getElementById('inviteOverlay');
    if (inviteOverlay) {
        inviteOverlay.addEventListener('click', e => {
            if (e.target === inviteOverlay) closeInvite();
        });
    }
});

/* =========================
   Submit Invite
========================= */

function submitInvite() {

    const first = document.getElementById('invFirst')?.value.trim();
    const last = document.getElementById('invLast')?.value.trim();
    const email = document.getElementById('invEmail')?.value.trim();
    const role = document.getElementById('invRole')?.value;

    if (!first || !last || !email) {

        showToast(
            'Please fill in all required fields.',
            'warning',
            'Missing Fields'
        );

        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {

        showToast(
            'Please enter a valid email address.',
            'warning',
            'Invalid Email'
        );

        return;
    }

    showToast('Sending invite…', 'info');

    const btn = document.querySelector('#inviteOverlay .btn-primary');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Sending…';
    }

    fetch('../../endpoints/staff.php', {
        method: 'POST',

        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },

        body: new URLSearchParams({
            action: 'invite',
            first_name: first,
            last_name: last,
            email,
            role,
            csrf_token: window.PS_CSRF_TOKEN ?? ''
        })
    })
        .then(r => r.json())

        .then(data => {

            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Send Invite';
            }

            closeInvite();

            if (data.success) {

                showToast(
                    data.message || 'Invite sent!',
                    'success',
                    'Invite Sent!'
                );

                setTimeout(() => {
                    refreshStaffTable();
                }, 800);

            } else {

                showToast(
                    data.message || 'Failed to send invite.',
                    'error',
                    'Failed'
                );
            }
        })

        .catch(() => {

            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Send Invite';
            }

            showToast(
                'Server unreachable.',
                'error'
            );
        });
}

/* =========================
   Activate / Deactivate
========================= */

function toggleActive(userId, name, current) {

    const activate = current == 0;

    if (!confirm(
        `${activate ? 'Activate' : 'Deactivate'} ${name}?`
    )) {
        return;
    }

    showToast('Updating…', 'info');

    fetch('../../endpoints/staff.php', {
        method: 'POST',

        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },

        body: new URLSearchParams({
            action: 'toggle_active',
            user_id: userId,
            csrf_token: window.PS_CSRF_TOKEN ?? ''
        })
    })
        .then(r => r.json())

        .then(data => {

            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Send Invite';
            }

            if (data.success) {

                showToast(
                    data.message || 'Done!',
                    'success'
                );

                setTimeout(() => {
                    refreshStaffTable();
                }, 600);

            } else {

                showToast(
                    data.message || 'Failed',
                    'error',
                    'Failed'
                );
            }
        })

        .catch(() => {

            showToast(
                'Server unreachable.',
                'error'
            );
        });
}

/* =========================
   Remove Staff
========================= */

function removeStaff(userId, name) {

    if (!confirm(
        `Remove ${name}? This will permanently remove this staff member.`
    )) {
        return;
    }

    showToast('Removing…', 'info');

    fetch('../../endpoints/staff.php', {
        method: 'POST',

        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },

        body: new URLSearchParams({
            action: 'remove_staff', // FIXED
            user_id: userId,
            csrf_token: window.PS_CSRF_TOKEN ?? ''
        })
    })
        .then(r => r.json())

        .then(data => {

            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Send Invite';
            }

            if (data.success) {

                showToast(
                    data.message || 'Removed!',
                    'success',
                    'Removed!'
                );

                setTimeout(() => {
                    refreshStaffTable();
                }, 600);

            } else {

                showToast(
                    data.message || 'Failed',
                    'error',
                    'Failed'
                );
            }
        })

        .catch(() => {

            showToast(
                'Server unreachable.',
                'error'
            );
        });
}