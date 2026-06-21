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

async function toggleAdmin2FA(toggleEl) {
    const enabled = toggleEl.checked ? '1' : '0';
    toggleEl.disabled = true;
    const slider = document.getElementById('admin2faSlider');
    const knob = document.getElementById('admin2faKnob');
    try {
        const fd = new FormData();
        fd.append('action', 'toggle_2fa');
        fd.append('enabled', enabled);
        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
        const res = await fetch('../../api/settings.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            const on = !!data.enabled;
            if (slider) slider.style.background = on ? 'var(--blue-500,#3b82f6)' : 'var(--border,#cbd5e1)';
            if (knob) knob.style.left = on ? '23px' : '3px';
            showToast(data.message || (on ? '2FA enabled.' : '2FA disabled.'), 'success');
        } else {
            toggleEl.checked = !toggleEl.checked;
            showToast(data.message || 'Could not update 2FA.', 'error');
        }
    } catch (e) {
        toggleEl.checked = !toggleEl.checked;
        showToast('Network error while updating 2FA.', 'error');
    } finally {
        toggleEl.disabled = false;
    }
}

function saveContactInfo(e) {
    e.preventDefault();
    const fd = new FormData();
    fd.append('action', 'update_contact');
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('contact_address', document.getElementById('contact_address').value.trim());
    fd.append('contact_phone', document.getElementById('contact_phone').value.trim());
    fd.append('contact_phone2', document.getElementById('contact_phone2')?.value.trim() || '');
    fd.append('contact_email', document.getElementById('contact_email').value.trim());
    fetch('../../api/settings.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => showToast(d.message || 'Contact info saved.', d.success ? 'success' : 'error'));
}
// ── Backup & Recovery ─────────────────────────────────────────────────────

const BACKUP_API = '../../api/admin/backup.php';

// ── Confirmation modal (matches PropSight design) ─────────────────────────
function showBackupConfirm({ title, message, sub, confirmLabel, icon, iconBg, iconColor, btnBg, onConfirm }) {
    document.getElementById('backupConfirmModal')?.remove();

    const modal = document.createElement('div');
    modal.id = 'backupConfirmModal';
    modal.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.45);backdrop-filter:blur(3px);';
    modal.innerHTML = `
        <div style="background:#fff;border-radius:20px;padding:36px 32px 28px;width:100%;max-width:380px;box-shadow:0 24px 64px rgba(0,0,0,.18);animation:psModalIn .18s ease;text-align:center;">
            <div style="width:64px;height:64px;border-radius:50%;background:${iconBg};display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                <svg fill="none" stroke="${iconColor}" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">${icon}</svg>
            </div>
            <div style="font-size:17px;font-weight:700;color:#0f172a;margin-bottom:10px;">${title}</div>
            <div style="font-size:13.5px;color:#334155;line-height:1.6;margin-bottom:6px;">${message}</div>
            ${sub ? `<div style="font-size:12.5px;color:#94a3b8;margin-bottom:4px;">${sub}</div>` : ''}
            <div style="display:flex;gap:10px;margin-top:24px;">
                <button id="bkCancelBtn"
                    style="flex:1;padding:11px 0;background:#fff;color:#475569;border:1.5px solid #e2e8f0;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">
                    Cancel
                </button>
                <button id="bkConfirmBtn"
                    style="flex:1;padding:11px 0;background:${btnBg};color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">
                    ${confirmLabel}
                </button>
            </div>
        </div>`;

    document.body.appendChild(modal);
    if (!document.getElementById('psModalKf')) {
        const s = document.createElement('style');
        s.id = 'psModalKf';
        s.textContent = '@keyframes psModalIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}';
        document.head.appendChild(s);
    }

    document.getElementById('bkCancelBtn').onclick  = () => modal.remove();
    document.getElementById('bkConfirmBtn').onclick = () => { modal.remove(); onConfirm(); };
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
}

// ── Load backups ──────────────────────────────────────────────────────────
function loadBackups() {
    fetch(BACKUP_API + '?action=list')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('backupList');
            if (!list) return;

            if (!data.success || !data.backups.length) {
                list.innerHTML = `
                    <div style="text-align:center;padding:32px 16px;color:var(--text-soft);font-size:13px;">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="32" height="32" style="opacity:.25;display:block;margin:0 auto 10px;">
                            <ellipse cx="12" cy="5" rx="9" ry="3"/>
                            <path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/>
                            <path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/>
                        </svg>
                        No backups yet. Click <strong>Generate Backup</strong> to create one.
                    </div>`;
                return;
            }

            const fileIcon = `<svg fill="none" stroke="#3b82f6" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`;
            const dlIcon   = `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12" height="12"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>`;
            const reIcon   = `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12" height="12"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4"/></svg>`;
            const delIcon  = `<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="12" height="12"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>`;

            // Desktop table
            const tableHtml = `
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border,#e2e8f0);">
                            <th style="text-align:left;padding:8px 10px;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--text-soft);">Filename</th>
                            <th style="text-align:left;padding:8px 10px;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--text-soft);">Created</th>
                            <th style="text-align:right;padding:8px 10px;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--text-soft);">Size</th>
                            <th style="text-align:right;padding:8px 10px;font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--text-soft);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.backups.map((b, i) => `
                        <tr style="border-bottom:1px solid var(--border-lt,#f1f5f9);${i === 0 ? 'background:rgba(59,130,246,.03);' : ''}">
                            <td style="padding:10px 10px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="width:30px;height:30px;border-radius:7px;background:rgba(59,130,246,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">${fileIcon}</div>
                                    <div>
                                        <div style="font-weight:500;color:var(--text-dark);">${b.filename}</div>
                                        ${i === 0 ? '<div style="font-size:10px;color:#3b82f6;font-weight:600;margin-top:1px;">Latest</div>' : ''}
                                    </div>
                                </div>
                            </td>
                            <td style="padding:10px 10px;color:var(--text-soft);">${psFmtDateTime(b.created)}</td>
                            <td style="padding:10px 10px;text-align:right;color:var(--text-soft);">${b.size_mb} MB</td>
                            <td style="padding:10px 10px;text-align:right;">
                                <div style="display:inline-flex;align-items:center;gap:6px;">
                                    <button onclick="downloadBackup('${b.filename}')" title="Download"
                                        style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;font-weight:500;cursor:pointer;">
                                        ${dlIcon} Download
                                    </button>
                                    <button onclick="restoreBackup('${b.filename}')" title="Restore"
                                        style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;background:#eff6ff;color:#3b82f6;border:1px solid #bfdbfe;border-radius:7px;font-size:12px;font-weight:500;cursor:pointer;">
                                        ${reIcon} Restore
                                    </button>
                                    <button onclick="deleteBackup('${b.filename}')" title="Delete"
                                        style="display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:#fff1f2;color:#e11d48;border:1px solid #fecdd3;border-radius:7px;font-size:12px;font-weight:500;cursor:pointer;">
                                        ${delIcon} Delete
                                    </button>
                                </div>
                            </td>
                        </tr>`).join('')}
                    </tbody>
                </table>`;

            // Mobile card list (shown via CSS at ≤600px, hidden on desktop)
            const mobileHtml = `
                <div class="backup-mobile-list">
                    ${data.backups.map((b, i) => `
                    <div class="backup-mobile-card${i === 0 ? ' latest' : ''}">
                        <div class="backup-mobile-card-top">
                            <div class="backup-mobile-icon">${fileIcon}</div>
                            <div class="backup-mobile-meta">
                                <div class="backup-mobile-filename">${b.filename}</div>
                                ${i === 0 ? '<span class="backup-mobile-badge">Latest</span>' : ''}
                            </div>
                        </div>
                        <div class="backup-mobile-details">
                            <span>${psFmtDateTime(b.created)}</span>
                            <span>${b.size_mb} MB</span>
                        </div>
                        <div class="backup-mobile-actions">
                            <button class="bk-btn-download" onclick="downloadBackup('${b.filename}')">${dlIcon} Download</button>
                            <button class="bk-btn-restore" onclick="restoreBackup('${b.filename}')">${reIcon} Restore</button>
                            <button class="bk-btn-delete"  onclick="deleteBackup('${b.filename}')">${delIcon} Delete</button>
                        </div>
                    </div>`).join('')}
                </div>`;

            list.innerHTML = tableHtml + mobileHtml;
        })
        .catch(() => {
            const list = document.getElementById('backupList');
            if (list) list.innerHTML = '<div style="padding:16px;color:#ef4444;font-size:13px;">Failed to load backups.</div>';
        });
}

// ── Generate — no confirmation, just do it ────────────────────────────────
function generateBackup() {
    const btn = document.getElementById('generateBackupBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg class="spin" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" width="14" height="14"><path d="M12 2a10 10 0 0 1 10 10"/></svg> Generating…';
    }

    const fd = new FormData();
    fd.append('action', 'generate');
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');

    fetch(BACKUP_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Backup generated: ' + data.filename, 'success');
                loadBackups();
            } else {
                showToast(data.message || 'Failed to generate backup.', 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'))
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" width="14" height="14"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg> Generate Backup';
            }
        });
}

// ── Download — no confirmation, just download ─────────────────────────────
function downloadBackup(filename) {
    window.location.href = BACKUP_API + '?action=download&file=' + encodeURIComponent(filename);
}

// ── Restore — confirmation modal ──────────────────────────────────────────
function restoreBackup(filename) {
    showBackupConfirm({
        title: 'Restore Database?',
        message: 'This will <strong>permanently overwrite</strong> all current data with the selected backup.',
        sub: 'This cannot be undone.',
        confirmLabel: 'Restore',
        icon: '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4"/>',
        iconBg: 'rgba(239,68,68,.1)',
        iconColor: '#ef4444',
        btnBg: '#ef4444',
        onConfirm: () => {
            showToast('Restoring… please wait.', 'info');
            const fd = new FormData();
            fd.append('action', 'restore');
            fd.append('file', filename);
            fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
            fetch(BACKUP_API, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message || (data.success ? 'Restore complete.' : 'Restore failed.'), data.success ? 'success' : 'error');
                })
                .catch(() => showToast('Network error.', 'error'));
        }
    });
}

// ── Delete — confirmation modal ───────────────────────────────────────────
function deleteBackup(filename) {
    showBackupConfirm({
        title: 'Delete Backup?',
        message: 'This backup file will be <strong>permanently removed</strong> from the server.',
        sub: 'This cannot be undone.',
        confirmLabel: 'Delete',
        icon: '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>',
        iconBg: 'rgba(239,68,68,.1)',
        iconColor: '#ef4444',
        btnBg: '#ef4444',
        onConfirm: () => {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('file', filename);
            fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
            fetch(BACKUP_API, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.success ? 'Backup deleted.' : (data.message || 'Failed to delete.'), data.success ? 'success' : 'error');
                    if (data.success) loadBackups();
                })
                .catch(() => showToast('Network error.', 'error'));
        }
    });
}

// Load backups on page load
document.addEventListener('DOMContentLoaded', loadBackups);