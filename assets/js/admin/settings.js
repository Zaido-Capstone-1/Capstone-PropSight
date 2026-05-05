function uploadAdminPhoto(input) {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const fd = new FormData();
    fd.append('photo', file);
    fd.append('action', 'upload_photo');
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');

    fetch('../../api/admin/update_profile_photo.php', {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.photo_url) {
                // Update the image
                const img = document.getElementById('settingsAvatarImg');
                if (img) {
                    img.src = '../../' + data.photo_url;
                    img.style.display = 'block';
                }

                // Hide initials
                const initialsSpan = document.getElementById('settingsAvatarInitials');
                if (initialsSpan) initialsSpan.style.display = 'none';

                // Remove gradient background
                const avatarWrap = document.getElementById('settingsAvatarWrap');
                if (avatarWrap) {
                    avatarWrap.style.background = '';
                }

                // Add "Remove" button if it doesn't exist
                if (!document.getElementById('removePhotoBtn')) {
                    const changeBtn = document.querySelector('button.btn-secondary');
                    if (changeBtn && changeBtn.parentNode) {
                        const removeBtn = document.createElement('button');
                        removeBtn.id = 'removePhotoBtn';
                        removeBtn.className = 'btn';
                        removeBtn.type = 'button';
                        removeBtn.style.cssText = 'margin-top:8px;margin-left:6px;padding:5px 12px;font-size:12px;color:var(--danger,#dc2626);border-color:var(--danger,#dc2626);';
                        removeBtn.textContent = 'Remove';
                        removeBtn.onclick = removeAdminPhoto;
                        changeBtn.parentNode.insertBefore(removeBtn, changeBtn.nextSibling);
                    }
                }

                showToast('Profile photo updated successfully', 'success');
            } else {
                showToast(data.message || 'Failed to upload photo', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('An error occurred', 'error');
        });
}

function removeAdminPhoto() {
    if (!confirm('Are you sure you want to remove your profile photo?')) return;

    const fd = new FormData();
    fd.append('action', 'remove_photo');
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');

    fetch('../../api/admin/update_profile_photo.php', {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Hide the image
                const img = document.getElementById('settingsAvatarImg');
                if (img) {
                    img.style.display = 'none';
                    img.src = '';
                }

                // Show the initials
                const initialsSpan = document.getElementById('settingsAvatarInitials');
                if (initialsSpan) initialsSpan.style.display = 'flex';

                // Add gradient background back
                const avatarWrap = document.getElementById('settingsAvatarWrap');
                if (avatarWrap) {
                    avatarWrap.style.background = 'linear-gradient(135deg,var(--blue-300),var(--blue-700))';
                }

                // Remove the "Remove" button
                const removeBtn = document.getElementById('removePhotoBtn');
                if (removeBtn) removeBtn.remove();

                showToast('Profile photo removed successfully', 'success');
            } else {
                showToast(data.message || 'Failed to remove photo', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('An error occurred', 'error');
        });
}

function saveProfile(e) {
    e.preventDefault();
    const fd = new FormData(); fd.append('action', 'update_profile');
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('first_name', document.getElementById('adm_fn')?.value || '');
    fd.append('last_name', document.getElementById('adm_ln')?.value || '');
    fd.append('email', document.getElementById('adm_email')?.value || '');
    fd.append('phone', document.getElementById('adm_phone')?.value || '');
    fd.append('address', document.getElementById('adm_addr')?.value || '');
    fetch('../../api/settings.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            showToast(d.message, d.success ? 'success' : 'error');
        }).catch(() => showToast('An error occurred.', 'error'));
}
function changePassword(e) {
    e.preventDefault();
    const fd = new FormData(); fd.append('action', 'change_password');
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('current_password', document.getElementById('cur_pw')?.value || '');
    fd.append('new_password', document.getElementById('new_pw')?.value || '');
    fd.append('confirm_password', document.getElementById('conf_pw')?.value || '');
    fetch('../../api/settings.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            showToast(d.message, d.success ? 'success' : 'error');
            if (d.success) { document.getElementById('cur_pw').value = ''; document.getElementById('new_pw').value = ''; document.getElementById('conf_pw').value = ''; }
        }).catch(() => showToast('An error occurred.', 'error'));
}
function saveSystemPrefs() {
    const fd = new FormData(); fd.append('action', 'update_system');
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    document.querySelectorAll('[data-setting]').forEach(el => fd.append(el.dataset.setting, el.value));
    fetch('../../api/settings.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            showToast(d.message, d.success ? 'success' : 'error');
        }).catch(() => showToast('An error occurred.', 'error'));
}
