window._psToastReady = true;
    document.addEventListener("DOMContentLoaded", function() {
        showToast("You do not have permission to access this page.", "error", "Unauthorized");
    });
    setTimeout(() => history.back(), 2000);

function uploadAdminPhoto(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        if (!file.type.startsWith('image/')) { showToast('Please select an image file.', 'error'); return; }
        if (file.size > 5 * 1024 * 1024) { showToast('Image must be under 5MB.', 'error'); return; }
        const fd = new FormData();
        fd.append('action', 'upload_photo');
        fd.append('photo', file);
        fetch('../../endpoints/admin/update_profile_photo.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                if (d.success && d.photo_url) {
                    const img = document.getElementById('settingsAvatarImg');
                    const initEl = document.getElementById('settingsAvatarInitials');
                    img.src = '../../' + d.photo_url;
                    img.style.display = '';
                    if (initEl) initEl.style.display = 'none';
                    // Show remove btn if not present
                    if (!document.getElementById('removePhotoBtn')) {
                        const btn = document.createElement('button');
                        btn.id = 'removePhotoBtn';
                        btn.className = 'btn';
                        btn.type = 'button';
                        btn.style.cssText = 'margin-top:8px;margin-left:6px;padding:5px 12px;font-size:12px;color:var(--danger,#dc2626);border-color:var(--danger,#dc2626);';
                        btn.textContent = 'Remove';
                        btn.onclick = removeAdminPhoto;
                        document.querySelector('[onclick="document.getElementById(\'adminPhotoInput\').click();"]')
                            ?.parentElement?.appendChild(btn);
                    }
                    showToast('Profile photo updated.', 'success');
                } else {
                    showToast(d.message || 'Upload failed.', 'error');
                }
            }).catch(() => showToast('Upload failed.', 'error'));
        input.value = '';
    }

    function removeAdminPhoto() {
        if (!confirm('Remove your profile photo?')) return;
        const fd = new FormData();
        fd.append('action', 'remove_photo');
        fetch('../../endpoints/admin/update_profile_photo.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                if (d.success) {
                    const img = document.getElementById('settingsAvatarImg');
                    const initEl = document.getElementById('settingsAvatarInitials');
                    if (img) img.style.display = 'none';
                    if (initEl) initEl.style.display = 'flex';
                    document.getElementById('removePhotoBtn')?.remove();
                    showToast('Profile photo removed.', 'success');
                } else {
                    showToast(d.message || 'Failed to remove photo.', 'error');
                }
            }).catch(() => showToast('Failed to remove photo.', 'error'));
    }

    function saveProfile(e) {
        e.preventDefault();
        const fd = new FormData(); fd.append('action', 'update_profile');
        fd.append('first_name', document.getElementById('adm_fn')?.value || '');
        fd.append('last_name', document.getElementById('adm_ln')?.value || '');
        fd.append('email', document.getElementById('adm_email')?.value || '');
        fd.append('phone', document.getElementById('adm_phone')?.value || '');
        fd.append('address', document.getElementById('adm_addr')?.value || '');
        fetch('../../endpoints/settings.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                showToast(d.message, d.success ? 'success' : 'error');
            }).catch(() => showToast('An error occurred.', 'error'));
    }
    function changePassword(e) {
        e.preventDefault();
        const fd = new FormData(); fd.append('action', 'change_password');
        fd.append('current_password', document.getElementById('cur_pw')?.value || '');
        fd.append('new_password', document.getElementById('new_pw')?.value || '');
        fd.append('confirm_password', document.getElementById('conf_pw')?.value || '');
        fetch('../../endpoints/settings.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                showToast(d.message, d.success ? 'success' : 'error');
                if (d.success) { document.getElementById('cur_pw').value = ''; document.getElementById('new_pw').value = ''; document.getElementById('conf_pw').value = ''; }
            }).catch(() => showToast('An error occurred.', 'error'));
    }
    function saveSystemPrefs() {
        const fd = new FormData(); fd.append('action', 'update_system');
        document.querySelectorAll('[data-setting]').forEach(el => fd.append(el.dataset.setting, el.value));
        fetch('../../endpoints/settings.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => {
                showToast(d.message, d.success ? 'success' : 'error');
            }).catch(() => showToast('An error occurred.', 'error'));
    }
