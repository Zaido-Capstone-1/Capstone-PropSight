showToast("You do not have permission to access this page.", "error", "Unauthorized"); setTimeout(() => history.back(), 2000);

window.PS_RT_PAGE = 'profile';

// ── Live profile sync: update profile page fields when ps:profile_sync fires ──
window.addEventListener('ps:profile_sync', function(e) {
    const p = e.detail || {};
    const first = (p.first_name || '').trim();
    const last  = (p.last_name  || '').trim();
    const full  = [first, last].filter(Boolean).join(' ') || first || 'Guest';
    const email = (p.email || '').trim();

    // Header h2 and email
    const nameEl = document.getElementById('profile-full-name');
    if (nameEl && full) nameEl.textContent = full;
    const emailHeaderEl = document.getElementById('profile-header-email');
    if (emailHeaderEl && email) emailHeaderEl.textContent = email;

    // Info rows
    const fieldsMap = { first_name: first, last_name: last || '—', email, phone: p.phone || '' };
    Object.entries(fieldsMap).forEach(([field, val]) => {
        if (!val) return;
        document.querySelectorAll(`[data-profile-field="${field}"]`).forEach(el => {
            el.textContent = val;
        });
    });

    // Verification badge
    const verified = String(p.verification_status || '').toLowerCase() === 'verified';
    const badgeEls = document.querySelectorAll('.info-row-email .badge');
    badgeEls.forEach(el => {
        el.className = `badge ${verified ? 'badge-green' : 'badge-gold'}`;
        el.textContent = verified ? '✓ Verified' : '⚠ Not Verified';
    });
    // Show/hide "Verify now" button
    const verifyBtn = document.querySelector('.btn-verify-now');
    if (verifyBtn) verifyBtn.style.display = verified ? 'none' : '';

    // Profile photo in avatar circle
    const photoSrc = (p.profile_photo || '').trim();
    const avatarWrap = document.querySelector('.profile-avatar-wrap, .user-avatar-wrap, .avatar-circle');
    if (avatarWrap && photoSrc) {
        let img = avatarWrap.querySelector('img.profile-photo-img, img.user-photo');
        if (!img) {
            img = document.createElement('img');
            img.className = 'profile-photo-img';
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
            avatarWrap.prepend(img);
        }
        const src = '../../' + photoSrc.replace(/^\/+/, '');
        if (img.getAttribute('src') !== src) img.src = src;
        img.style.display = 'block';
        const initEl = avatarWrap.querySelector('.avatar-initials, .initials-text');
        if (initEl) initEl.style.display = 'none';
    }
});
