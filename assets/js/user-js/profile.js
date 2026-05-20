// ── MODAL HELPERS ──────────────────────────────────
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}

// ── EDIT PROFILE ──────────────────────────────────
function openEditModal() {
    openModal('editModal');
}

function closeEditModal() {
    closeModal('editModal');
}

function openVerifyEmailModal() {
    openModal('verifyEmailModal');
}

function setupEditProfileSubmitLoading() {
    const editForm = document.querySelector('#editModal form[action="../../api/user/edit_profile.php"]');
    if (!editForm) return;

    editForm.addEventListener('submit', () => {
        const submitBtn = editForm.querySelector('button[type="submit"]');
        if (!submitBtn) return;

        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;animation:spin 0.8s linear infinite;">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
            Saving...
        `;
    });
}

const profilePhotoInput = document.querySelector('#profilePhotoModal input[name="profile_photo"]');
const profilePhotoPreview = document.querySelector('#profilePhotoModal .profile-photo-preview');
if (profilePhotoInput && profilePhotoPreview) {
    profilePhotoInput.addEventListener('change', () => {
        const file = profilePhotoInput.files?. [0];
        if (!file) return;
        if (!file.type.startsWith('image/')) return;
        const img = document.createElement('img');
        img.alt = 'Selected profile photo';
        img.src = URL.createObjectURL(file);
        img.onload = () => URL.revokeObjectURL(img.src);
        profilePhotoPreview.innerHTML = '';
        profilePhotoPreview.appendChild(img);
    });
}

function saveProfile() {
    const first = document.getElementById('edit_first').value.trim();
    const last = document.getElementById('edit_last').value.trim();
    const email = document.getElementById('edit_email').value.trim();
    const phone = document.getElementById('edit_phone').value.trim();
    const errEl = document.getElementById('editError');
    errEl.style.display = 'none';

    if (!first) {
        errEl.textContent = 'First name is required.';
        errEl.style.display = 'block';
        return;
    }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errEl.textContent = 'Please enter a valid email.';
        errEl.style.display = 'block';
        return;
    }

    const btn = document.getElementById('saveProfileBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;animation:spin 0.8s linear infinite;"><polyline points="20 6 9 17 4 12"/></svg> Saving…';

    setTimeout(() => {
        // Update displayed values live
        document.querySelector('.avatar-info h2').textContent = first + ' ' + last;
        document.querySelector('.avatar-info p').textContent = email;
        document.querySelectorAll('.info-row')[0].querySelector('.info-value').textContent = first;
        document.querySelectorAll('.info-row')[1].querySelector('.info-value').textContent = last || '—';
        document.querySelectorAll('.info-row')[2].querySelector('.info-value').textContent = email;
        document.querySelectorAll('.info-row')[3].querySelector('.info-value').textContent = phone;
        closeEditModal();
        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Save Changes';
        showToast('Profile updated successfully!');
    }, 700);
}

// ── UPLOAD ID ──────────────────────────────────────
function handleFileSelect(input) {
    if (input.files[0]) {
        document.getElementById('dropzoneLabel').textContent = input.files[0].name;
        const btn = document.getElementById('uploadIdBtn');
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

function handleFileDrop(e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('dropzoneLabel').textContent = file.name;
        const btn = document.getElementById('uploadIdBtn');
        btn.disabled = false;
        btn.style.opacity = '1';
        document.getElementById('dropzone').style.borderColor = 'var(--blue-200)';
        document.getElementById('dropzone').style.background = '';
    }
}

function submitUpload() {
    const btn = document.getElementById('uploadIdBtn');
    const fileInput = document.getElementById('idFileInput');
    const csrfToken = document.getElementById('uploadIdCsrfToken')?.value || '';
    const file = fileInput?.files?. [0];
    if (!file) {
        showToast('Please select an ID file first.', 'error');
        return;
    }
    btn.disabled = true;
    btn.innerHTML = 'Uploading…';
    const fd = new FormData();
    fd.append('id_document', file);
    fd.append('csrf_token', csrfToken);

    fetch('../../api/user/upload_id.php', {
            method: 'POST',
            body: fd,
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                btn.disabled = false;
                btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Upload ID';
                showToast(data.message || 'Upload failed.', 'error');
                return;
            }
            closeModal('uploadIdModal');
            btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Upload ID';
            document.getElementById('dropzoneLabel').textContent = 'No file selected';
            if (fileInput) fileInput.value = '';
            btn.style.opacity = '0.5';
            btn.disabled = true;
            showToast(data.message || 'ID uploaded successfully!');
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Upload ID';
            showToast('Network error while uploading ID.', 'error');
        });
}

// ── DELETE ACCOUNT ─────────────────────────────────
function confirmDelete() {
    const confirmInput = document.getElementById('deleteConfirmInput');
    const csrfToken = document.getElementById('deleteCsrfToken')?.value || '';
    const btn = document.getElementById('confirmDeleteBtn');
    if (!confirmInput || confirmInput.value !== 'DELETE') {
        showToast('Please type DELETE to confirm.', 'error');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Deleting...';
    const fd = new FormData();
    fd.append('confirm_text', 'DELETE');
    fd.append('csrf_token', csrfToken);

    fetch('../../api/user/delete_account.php', {
            method: 'POST',
            body: fd,
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                btn.disabled = false;
                btn.textContent = 'Yes, Delete';
                showToast(data.message || 'Could not delete account.', 'error');
                return;
            }
            showToast(data.message || 'Account deleted.');
            window.location.href = '../../index.php';
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = 'Yes, Delete';
            showToast('Network error while deleting account.', 'error');
        });
}

function setVerifyError(msg = '') {
    const err = document.getElementById('verifyEmailError');
    if (!err) return;
    err.textContent = msg;
    err.style.display = msg ? 'block' : 'none';
}

function setVerifyButtonLoading(btn, loading, loadingText, defaultText) {
    if (!btn) return;
    btn.disabled = loading;
    btn.textContent = loading ? loadingText : defaultText;
}

function sendVerificationCode() {
    const emailEl = document.getElementById('verifyEmailInput');
    const tokenEl = document.getElementById('verifyCsrfToken');
    const hintEl = document.getElementById('verifyEmailHint');
    const sendBtn = document.getElementById('sendVerifyCodeBtn');
    if (!emailEl || !tokenEl || !sendBtn) return;

    const email = emailEl.value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setVerifyError('Please enter a valid email address.');
        return;
    }

    setVerifyError('');
    setVerifyButtonLoading(sendBtn, true, 'Sending...', 'Send Code');

    fetch('../../api/user/send_verification_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                csrf_token: tokenEl.value,
                email
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (hintEl) hintEl.textContent = 'Code sent. Check your email inbox and spam folder.';
                if (typeof showToast === 'function') showToast(data.message || 'Verification code sent.', 'success');
            } else {
                setVerifyError(data.message || 'Could not send verification code.');
            }
        })
        .catch(() => setVerifyError('Network error while sending code.'))
        .finally(() => setVerifyButtonLoading(sendBtn, false, 'Sending...', 'Send Code'));
}

function confirmEmailVerification() {
    const emailEl = document.getElementById('verifyEmailInput');
    const codeEl = document.getElementById('verifyCodeInput');
    const tokenEl = document.getElementById('verifyCsrfToken');
    const verifyBtn = document.getElementById('confirmVerifyBtn');
    if (!emailEl || !codeEl || !tokenEl || !verifyBtn) return;

    const email = emailEl.value.trim();
    const code = codeEl.value.trim();

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setVerifyError('Please enter a valid email address.');
        return;
    }
    if (!/^\d{6}$/.test(code)) {
        setVerifyError('Please enter the 6-digit code sent to your email.');
        return;
    }

    setVerifyError('');
    setVerifyButtonLoading(verifyBtn, true, 'Verifying...', 'Verify Email');

    fetch('../../api/user/verify_email_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                csrf_token: tokenEl.value,
                email,
                code
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') showToast(data.message || 'Email verified successfully.', 'success');
                // Update verification badge inline — no reload needed
                setTimeout(() => _updateVerificationBadge(true), 200);
                closeModal('verifyEmailModal');
            } else {
                setVerifyError(data.message || 'Invalid verification code.');
            }
        })
        .catch(() => setVerifyError('Network error while verifying email.'))
        .finally(() => setVerifyButtonLoading(verifyBtn, false, 'Verifying...', 'Verify Email'));
}


// ── Update verification badge inline after successful email verification ──
function _updateVerificationBadge(verified) {
    // Header / sidebar badge
    document.querySelectorAll('.sb-badge, .verify-badge, [data-verify-badge]').forEach(el => {
        el.classList.toggle('sb-badge-verified', verified);
        el.classList.toggle('sb-badge-unverified', !verified);
        const label = el.querySelector('.sb-badge-label, .badge-label');
        if (label) label.textContent = verified ? 'Email Verified' : 'Email Not Verified';
        const verifyLink = el.querySelector('.sb-verify-link, .verify-link');
        if (verifyLink) verifyLink.style.display = verified ? 'none' : '';
    });

    // Profile page verification row
    document.querySelectorAll('[data-verify-status]').forEach(el => {
        el.dataset.verifyStatus = verified ? 'verified' : 'unverified';
        el.textContent = verified ? 'Verified ✓' : 'Not Verified';
    });

    // Show/hide "Verify Email" button in profile
    document.querySelectorAll('[data-action="open-verify"], .verify-email-btn, #verifyEmailTrigger').forEach(el => {
        el.style.display = verified ? 'none' : '';
    });

    // Update any text showing verification status
    document.querySelectorAll('.info-value[data-field="verification"]').forEach(el => {
        el.textContent = verified ? 'Verified' : 'Not Verified';
        el.style.color = verified ? '#16a34a' : '#dc2626';
    });
}

// Close modals on backdrop click
['profilePhotoModal', 'editModal', 'uploadIdModal', 'deleteModal', 'verifyEmailModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', e => {
        if (e.target.id === id) closeModal(id);
    });
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        ['profilePhotoModal', 'editModal', 'uploadIdModal', 'deleteModal', 'verifyEmailModal'].forEach(closeModal);
    }
});

// Wire Upload New ID button
document.querySelector('.card.reveal.rd2 .btn-secondary')?.addEventListener('click', () => openModal('uploadIdModal'));

// Wire Delete Account button
document.querySelector('.btn-danger')?.addEventListener('click', () => {
    document.getElementById('deleteConfirmInput').value = '';
    document.getElementById('confirmDeleteBtn').disabled = true;
    document.getElementById('confirmDeleteBtn').style.opacity = '0.5';
    openModal('deleteModal');
});

setupEditProfileSubmitLoading();

function submitProfilePhoto() {
    const fileInput = document.getElementById('profilePhotoFileInput');
    const csrf = document.getElementById('profilePhotoCsrf')?.value || '';
    const btn = document.getElementById('profilePhotoSubmitBtn');
    const msgEl = document.getElementById('profilePhotoMsg');

    const file = fileInput?.files?. [0];
    if (!file) {
        msgEl.textContent = 'Please select a photo first.';
        msgEl.style.color = '#dc2626';
        msgEl.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = 'Uploading…';
    msgEl.style.display = 'none';

    const fd = new FormData();
    fd.append('profile_photo', file);
    fd.append('csrf_token', csrf);

    fetch('../../api/user/update_profile_photo.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeModal('profilePhotoModal');
                if (typeof showToast === 'function') showToast(data.message || 'Profile picture updated!', 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                msgEl.textContent = data.message || 'Upload failed. Please try again.';
                msgEl.style.color = '#dc2626';
                msgEl.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Upload Photo';
            }
        })
        .catch(() => {
            msgEl.textContent = 'Network error. Please try again.';
            msgEl.style.color = '#dc2626';
            msgEl.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg> Upload Photo';
        });
}