// ── SECTION SWITCHING ──────────────────────────────
function showSection(id, el, evt) {
    if (evt) evt.preventDefault();
    document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.sn-item').forEach(i => i.classList.remove('active'));
    document.getElementById('sec-' + id).classList.add('active');
    el.classList.add('active');
    return false;
}

const SETTINGS_API_URL = '../../endpoints/user/settings.php';

async function postSettings(formData) {
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(formData);
    const res = await fetch(SETTINGS_API_URL, { method: 'POST', body: formData });
    return res.json();
}

function withLoading(btn, text) {
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = text;
    return () => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    };
}

function updateSecurityWidget(opts = {}) {
    if (typeof opts.twoFactorEnabled !== 'undefined') {
        const twoFaEl = document.getElementById('security2faStatus');
        if (twoFaEl) {
            const on = !!opts.twoFactorEnabled;
            twoFaEl.textContent = on ? '✓ On' : 'Off';
            twoFaEl.style.color = on ? '#16a34a' : '#94a3b8';
        }
    }
    if (typeof opts.activeSessionsCount !== 'undefined') {
        const countEl = document.getElementById('activeSessionsCount');
        if (countEl) countEl.textContent = String(opts.activeSessionsCount);
    }
}

async function savePushNotifications(btn) {
    const done = withLoading(btn, 'Saving…');
    try {
        const fd = new FormData();
        fd.append('action', 'update_notifications');
        ['notif_booking_confirm', 'notif_checkin_remind', 'notif_promotions', 'notif_loyalty', 'notif_newsletter'].forEach((key) => {
            const el = document.getElementById(key);
            fd.append(key, el && el.checked ? '1' : '0');
        });
        ['push_inapp_alerts', 'push_checkout_reminder', 'push_room_availability'].forEach((key) => {
            const el = document.getElementById(key);
            fd.append(key, el && el.checked ? '1' : '0');
        });
        const data = await postSettings(fd);
        showToast(data.message || (data.success ? 'Notification settings saved.' : 'Could not save notifications.'), data.success ? 'success' : 'error');
    } catch (e) {
        showToast('Network error while saving notifications.', 'error');
    } finally {
        done();
    }
}

// ── UPDATE PASSWORD ────────────────────────────────
async function updatePassword(btn) {
    const current = document.getElementById('currentPw')?.value || '';
    const newPw = document.getElementById('newPw').value;
    const confirm = document.getElementById('confirmPw')?.value || '';

    document.querySelectorAll('.pw-error').forEach(e => e.remove());

    if (!current) { showPwError('Current password is required.'); return; }
    if (newPw.length < 8) { showPwError('New password must be at least 8 characters.'); return; }
    if (newPw !== confirm) { showPwError('Passwords do not match.'); return; }

    const done = withLoading(btn, 'Updating…');
    try {
        const fd = new FormData();
        fd.append('action', 'change_password');
        fd.append('current_password', current);
        fd.append('new_password', newPw);
        fd.append('confirm_password', confirm);
        const data = await postSettings(fd);
        if (data.success) {
            document.querySelectorAll('#sec-security input[type="password"]').forEach(i => i.value = '');
            document.getElementById('pwBar').style.width = '0';
            document.getElementById('pwHint').textContent = '';
        }
        showToast(data.message || (data.success ? 'Password updated.' : 'Could not update password.'), data.success ? 'success' : 'error');
    } catch (e) {
        showToast('Network error while updating password.', 'error');
    } finally {
        done();
    }
}
function showPwError(msg) {
    const div = document.createElement('div');
    div.className = 'pw-error';
    div.style.cssText = 'color:#ef4444;font-size:0.78rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;margin-bottom:12px;';
    div.textContent = msg;
    const btn = document.getElementById('updatePasswordBtn');
    if (btn) btn.before(div);
}

// ── PASSWORD STRENGTH ──────────────────────────────
function checkStrength(v) {
    const bar = document.getElementById('pwBar');
    const hint = document.getElementById('pwHint');
    if (!v) { bar.style.width = '0'; hint.textContent = ''; return; }
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^a-zA-Z0-9]/.test(v)) score++;
    const map = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    const col = ['', '#ef4444', '#f59e0b', '#3b82f6', '#16a34a'];
    bar.style.width = (score * 25) + '%';
    bar.style.background = col[score];
    hint.textContent = map[score];
    hint.style.color = col[score];
}

async function toggle2FA(toggleEl) {
    const wanted = toggleEl.checked ? '1' : '0';
    toggleEl.disabled = true;
    try {
        const fd = new FormData();
        fd.append('action', 'toggle_2fa');
        fd.append('enabled', wanted);
        const data = await postSettings(fd);
        if (!data.success) {
            toggleEl.checked = !toggleEl.checked;
        } else {
            updateSecurityWidget({ twoFactorEnabled: !!data.enabled });
        }
        showToast(data.message || (data.success ? '2FA updated.' : 'Could not update 2FA.'), data.success ? 'success' : 'error');
    } catch (e) {
        toggleEl.checked = !toggleEl.checked;
        showToast('Network error while updating 2FA.', 'error');
    } finally {
        toggleEl.disabled = false;
    }
}

async function savePrivacy(btn) {
    const done = withLoading(btn, 'Saving…');
    try {
        const fd = new FormData();
        fd.append('action', 'update_privacy');
        const profilePublic = document.getElementById('privacy_profile')?.checked;
        fd.append('privacy_profile', profilePublic ? 'public' : 'private');
        ['privacy_share_history', 'privacy_recommendations', 'privacy_analytics'].forEach((key) => {
            const el = document.getElementById(key);
            fd.append(key, el && el.checked ? '1' : '0');
        });
        const data = await postSettings(fd);
        showToast(data.message || (data.success ? 'Privacy settings saved.' : 'Could not save privacy settings.'), data.success ? 'success' : 'error');
    } catch (e) {
        showToast('Network error while saving privacy settings.', 'error');
    } finally {
        done();
    }
}

async function requestMyData(btn) {
    const done = withLoading(btn, 'Requesting…');
    try {
        const fd = new FormData();
        fd.append('action', 'request_data_export');
        const data = await postSettings(fd);
        showToast(data.message || (data.success ? 'Data export requested.' : 'Could not request data export.'), data.success ? 'success' : 'error');
    } catch (e) {
        showToast('Network error while requesting your data export.', 'error');
    } finally {
        done();
    }
}

async function revokeSession(btn) {
    const item = btn.closest('.session-item');
    const done = withLoading(btn, 'Revoking…');
    try {
        const fd = new FormData();
        fd.append('action', 'revoke_session');
        const data = await postSettings(fd);
        if (data.success && item) {
            item.style.transition = 'opacity 0.3s, transform 0.3s';
            item.style.opacity = '0';
            item.style.transform = 'translateX(8px)';
            setTimeout(() => item.remove(), 300);
        }
        if (typeof data.active_sessions_count !== 'undefined') {
            updateSecurityWidget({ activeSessionsCount: data.active_sessions_count });
        }
        showToast(data.message || (data.success ? 'Session revoked successfully.' : 'Could not revoke session.'), data.success ? 'success' : 'error');
    } catch (e) {
        showToast('Network error while revoking session.', 'error');
    } finally {
        done();
    }
}

async function signOutOtherDevices(btn) {
    const done = withLoading(btn, 'Signing out…');
    try {
        const fd = new FormData();
        fd.append('action', 'signout_other_devices');
        const data = await postSettings(fd);
        if (typeof data.active_sessions_count !== 'undefined') {
            updateSecurityWidget({ activeSessionsCount: data.active_sessions_count });
        }
        if (data.success) {
            document.querySelectorAll('#sec-sessions .session-item').forEach((item, idx) => {
                if (idx > 0) item.remove();
            });
        }
        showToast(data.message || (data.success ? 'All other sessions signed out.' : 'Could not sign out other devices.'), data.success ? 'success' : 'error');
    } catch (e) {
        showToast('Network error while signing out other devices.', 'error');
    } finally {
        done();
    }
}
