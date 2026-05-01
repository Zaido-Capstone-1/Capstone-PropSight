window._psToastReady = true;
    document.addEventListener("DOMContentLoaded", function() {
        showToast("You do not have permission to access this page.", "error", "Unauthorized");
    });
    setTimeout(() => history.back(), 2000);

let st2;

    function openInvite() { document.getElementById('inviteOverlay').classList.add('open'); }
    function closeInvite() { document.getElementById('inviteOverlay').classList.remove('open'); }
    document.getElementById('inviteOverlay').addEventListener('click', e => {
        if (e.target === document.getElementById('inviteOverlay')) closeInvite();
    });

    function submitInvite() {
        const first = document.getElementById('invFirst').value.trim();
        const last = document.getElementById('invLast').value.trim();
        const email = document.getElementById('invEmail').value.trim();
        const role = document.getElementById('invRole').value;
        const password = document.getElementById('invPassword').value;

        if (!first || !last || !email || !password) {
            showToast('Please fill in all required fields.', 'warning', 'Missing Fields');
            return;
        }
        if (password.length < 8) {
            showToast('Password must be at least 8 characters.', 'warning', 'Weak Password');
            return;
        }

        showToast('Creating account…', 'info');

        fetch('../../api/staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'invite', first_name: first, last_name: last, email, role, password })
        })
            .then(r => r.json())
            .then(data => {
                closeInvite();
                if (data.success) {
                    showToast(data.message || 'Account created!', 'success', 'Account Created!');
                    setTimeout(() => refreshStaffTable(), 800);
                } else {
                    showToast(data.message, 'error', 'Failed');
                }
            })
            .catch(() => showToast('Server unreachable.', 'error'));
    }

    function toggleActive(userId, name, current) {
        const activate = current == 0;
        if (!confirm(`${activate ? 'Activate' : 'Deactivate'} ${name}?`)) return;
        showToast('Updating…', 'info');
        fetch('../../api/staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'toggle_active', user_id: userId })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Done!', 'success');
                    setTimeout(() => refreshStaffTable(), 600);
                } else {
                    showToast(data.message, 'error', 'Failed');
                }
            })
            .catch(() => showToast('Server unreachable.', 'error'));
    }

    function removeStaff(userId, name) {
        if (!confirm(`Remove ${name}? This will permanently remove this staff member.`)) return;
        showToast('Removing…', 'info');
        fetch('../../api/staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'toggle_active', user_id: userId })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Removed!', 'success', 'Removed!');
                    setTimeout(() => refreshStaffTable(), 600);
                } else {
                    showToast(data.message, 'error', 'Failed');
                }
            })
            .catch(() => showToast('Server unreachable.', 'error'));
        });
    }
