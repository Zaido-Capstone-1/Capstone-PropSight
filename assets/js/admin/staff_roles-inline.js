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

const inviteOverlay = document.getElementById('inviteOverlay');

if (inviteOverlay) {
    inviteOverlay.addEventListener('click', e => {
        if (e.target === inviteOverlay) {
            closeInvite();
        }
    });
}

/* =========================
   Submit Invite
========================= */

function submitInvite() {

    const first = document.getElementById('invFirst')?.value.trim();
    const last = document.getElementById('invLast')?.value.trim();
    const email = document.getElementById('invEmail')?.value.trim();
    const role = document.getElementById('invRole')?.value;
    const password = document.getElementById('invPassword')?.value;

    if (!first || !last || !email || !password) {

        showToast(
            'Please fill in all required fields.',
            'warning',
            'Missing Fields'
        );

        return;
    }

    if (password.length < 8) {

        showToast(
            'Password must be at least 8 characters.',
            'warning',
            'Weak Password'
        );

        return;
    }

    showToast('Creating account…', 'info');

    fetch('../../api/staff.php', {
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
            password
        })
    })
        .then(r => r.json())

        .then(data => {

            closeInvite();

            if (data.success) {

                showToast(
                    data.message || 'Account created!',
                    'success',
                    'Account Created!'
                );

                setTimeout(() => {
                    refreshStaffTable();
                }, 800);

            } else {

                showToast(
                    data.message || 'Failed to create account.',
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

    fetch('../../api/staff.php', {
        method: 'POST',

        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },

        body: new URLSearchParams({
            action: 'toggle_active',
            user_id: userId
        })
    })
        .then(r => r.json())

        .then(data => {

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

    fetch('../../api/staff.php', {
        method: 'POST',

        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },

        body: new URLSearchParams({
            action: 'remove_staff', // FIXED
            user_id: userId
        })
    })
        .then(r => r.json())

        .then(data => {

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