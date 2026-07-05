/**
 * assets/js/admin/staff_roles.js
 */

let st2;

/* =========================
   Role Definitions
========================= */

const _roleDefs = {
    admin: ['Admin', 'role-admin'],
    manager: ['Manager', 'role-manager'],
    frontdesk: ['Front Desk', 'role-frontdesk'],
    accounting: ['Accounting', 'role-accounting'],
    maintenance: ['Maintenance', 'role-maintenance'],
};

/* =========================
   Helpers
========================= */

function lastActiveLabel(ts) {
    if (!ts) return 'Never';
    const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

function buildStaffRow(s) {
    const currentUserId = window.__PS_STAFF__?.currentUserId ?? 0;
    const isSelf = parseInt(s.user_id) === parseInt(currentUserId);
    const fullName = (s.first_name + ' ' + s.last_name).trim();
    const initials = (s.first_name || '').charAt(0).toUpperCase() +
        (s.last_name || '').charAt(0).toUpperCase();
    const roleDef = _roleDefs[s.role] || [s.role, ''];
    const isActive = parseInt(s.is_active);
    const safeName = fullName.replace(/'/g, "\\'");

    const photo = s.profile_photo ?
        `<img src="../../${s.profile_photo}" class="staff-avatar-img"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
           <div class="staff-avatar" style="display:none;">${initials}</div>` :
        `<div class="staff-avatar">${initials}</div>`;

    const actionButtons = isSelf
        ? `<span style="font-size:0.75rem;color:#94a3b8;font-style:italic;padding:4px 6px;">You</span>`
        : `<button class="tbl-btn" onclick="openToggleModal(${s.user_id}, '${safeName}', ${isActive})">
            ${isActive ? 'Deactivate' : 'Activate'}
          </button>
          <button class="tbl-btn danger" onclick="openRemoveModal(${s.user_id}, '${safeName}')">Remove</button>`;

    return `<tr data-user-id="${s.user_id}" data-active="${isActive}">
      <td>
        <div style="display:flex;align-items:center;gap:9px;">
          ${photo}
          <div>
            <div style="font-weight:700;font-size:.84rem;">${fullName}</div>
            <div style="font-size:.72rem;color:#94a3b8;">${s.email}</div>
          </div>
        </div>
      </td>
      <td><span class="role-pill ${roleDef[1]}">${roleDef[0]}</span></td>
      <td style="font-size:.78rem;color:#94a3b8;">${lastActiveLabel(s.last_login)}</td>
      <td><span class="badge badge-${isActive ? 'success' : 'gray'}">${isActive ? 'Active' : 'Inactive'}</span></td>
      <td>
        <div class="action-wrap">
          ${actionButtons}
        </div>
      </td>
    </tr>`;
}

function refreshStaffTable(search, role) {
    const q = (search ?? '').trim();
    const r = (role ?? '').trim();
    const params = new URLSearchParams();
    if (q) params.set('search', q);
    if (r) params.set('role', r);
    const qs = params.toString();
    const url = '../../endpoints/staff.php' + (qs ? '?' + qs : '');

    fetch(url, { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const tbody = document.getElementById('staffTableBody');
            if (!tbody) return;
            tbody.innerHTML = data.staff.length ?
                data.staff.map(buildStaffRow).join('') :
                '<tr><td colspan="5" style="text-align:center;padding:40px;color:#94a3b8;">No staff found.</td></tr>';

            // Update footer count
            const countEl = document.getElementById('staff-count');
            if (countEl) countEl.textContent = data.staff.length;

            // Only update stat cards when not filtering
            if (!q && !r) {
                const totalCard = document.getElementById('stat-total');
                if (totalCard) totalCard.textContent = data.staff.length;

                if (data.counts) {
                    Object.entries(data.counts).forEach(([role, cnt]) => {
                        const el = document.getElementById('role-count-' + role);
                        if (el) el.textContent = cnt;
                    });
                    const field = (data.counts['frontdesk'] ?? 0) + (data.counts['maintenance'] ?? 0);
                    const fieldEl = document.getElementById('role-count-field');
                    if (fieldEl) fieldEl.textContent = field;
                }
            }
        })
        .catch(() => {});
}

/* =========================
   Inject Modals into DOM
========================= */

function injectModals() {
    const html = `
    <!-- Toggle Active Modal -->
    <div id="toggleActiveOverlay" class="modal-overlay">
        <div class="modal-box" style="max-width:400px;">
            <div style="padding:28px 24px 20px;text-align:center;">
                <div id="toggleActiveIconWrap" style="margin:0 auto 16px;"></div>
                <h3 id="toggleActiveTitle" style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 6px;"></h3>
                <p id="toggleActiveName" style="color:#64748b;font-size:13.5px;font-weight:600;margin:0 0 4px;"></p>
                <p id="toggleActiveNote" style="color:#94a3b8;font-size:12px;margin:0;line-height:1.5;"></p>
            </div>
            <div style="display:flex;gap:10px;justify-content:center;padding:0 24px 24px;">
                <button class="inv-btn-cancel" onclick="closeToggleModal()">Cancel</button>
                <button id="toggleActiveConfirmBtn" onclick="confirmToggleActive()" style="padding:9px 22px;border-radius:10px;border:none;font-size:0.83rem;font-weight:600;cursor:pointer;color:#fff;transition:opacity .15s;">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Remove Staff Modal -->
    <div id="removeStaffOverlay" class="modal-overlay">
        <div class="modal-box" style="max-width:400px;">
            <div style="padding:28px 24px 20px;text-align:center;">
                <div id="toggleActiveIconWrap"></div>
                <h3 style="font-size:1rem;font-weight:700;color:#1e293b;margin:0 0 6px;">Remove Staff Member?</h3>
                <p id="removeStaffName" style="color:#64748b;font-size:13.5px;font-weight:600;margin:0 0 4px;"></p>
                <p style="color:#94a3b8;font-size:12px;margin:0;line-height:1.5;">This will permanently remove this staff member and cannot be undone.</p>
            </div>
            <div style="display:flex;gap:10px;justify-content:center;padding:0 24px 24px;">
                <button class="inv-btn-cancel" onclick="closeRemoveModal()">Cancel</button>
                <button id="removeStaffConfirmBtn" onclick="confirmRemoveStaff()" style="padding:9px 22px;border-radius:10px;border:none;background:#dc2626;color:#fff;font-size:0.83rem;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(220,38,38,.25);transition:opacity .15s;">Remove</button>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', html);

    // Close on overlay click
    document.getElementById('toggleActiveOverlay').addEventListener('click', e => {
        if (e.target === e.currentTarget) closeToggleModal();
    });
    document.getElementById('removeStaffOverlay').addEventListener('click', e => {
        if (e.target === e.currentTarget) closeRemoveModal();
    });
}

/* =========================
   Toggle Active Modal
========================= */

let _toggleUserId = null,
    _toggleNewState = null;

function openToggleModal(userId, name, currentActive) {
    _toggleUserId   = userId;
    _toggleNewState = currentActive ? 0 : 1;

    const isActivating = _toggleNewState === 1;

    const iconWrap = document.getElementById('toggleActiveIcon'); // ← was toggleActiveIconWrap
    const title    = document.getElementById('toggleActiveTitle');
    const nameEl   = document.getElementById('toggleActiveName');
    const note     = document.getElementById('toggleActiveNote');
    const btn      = document.getElementById('toggleActiveConfirmBtn');

    if (isActivating) {
        iconWrap.style.background = '#dcfce7';
        iconWrap.innerHTML = `
            <svg fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">
                <circle cx="12" cy="12" r="9"/>
                <polyline points="9 12 11 14 15 10"/>
            </svg>`;
        title.textContent    = 'Activate Staff Member?';
        note.textContent     = 'This staff member will regain access to the system.';
        btn.style.background = '#16a34a';
        btn.style.boxShadow  = '0 2px 8px rgba(22,163,74,.25)';
        btn.textContent      = 'Activate';
    } else {
        iconWrap.style.background = '#fef3c7';
        iconWrap.innerHTML = `
            <svg fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">
                <circle cx="12" cy="12" r="9"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
            </svg>`;
        title.textContent    = 'Deactivate Staff Member?';
        note.textContent     = 'This staff member will lose system access until reactivated.';
        btn.style.background = '#d97706';
        btn.style.boxShadow  = '0 2px 8px rgba(217,119,6,.25)';
        btn.textContent      = 'Deactivate';
    }

    nameEl.textContent = name;
    btn.disabled       = false;
    btn.style.opacity  = '1';
    document.getElementById('toggleActiveOverlay').classList.add('open');
}

// Keep old name working (called from PHP-rendered rows)
function toggleActive(userId, name, current) {
    openToggleModal(userId, name, current);
}

function closeToggleModal() {
    document.getElementById('toggleActiveOverlay').classList.remove('open');
    _toggleUserId = null;
    _toggleNewState = null;
}

function confirmToggleActive() {
    if (_toggleUserId === null) return;
    const btn = document.getElementById('toggleActiveConfirmBtn');
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.textContent = 'Saving…';

    fetch('../../endpoints/staff.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'toggle_active',
                user_id: _toggleUserId,
                csrf_token: window.PS_CSRF_TOKEN ?? ''
            })
        })
        .then(r => r.json())
        .then(data => {
            closeToggleModal();
            if (data.success) {
                showToast(data.message || 'Done!', 'success');
                setTimeout(refreshStaffTable, 600);
            } else {
                showToast(data.message || 'Failed', 'error', 'Failed');
            }
        })
        .catch(() => {
            closeToggleModal();
            showToast('Server unreachable.', 'error');
        });
}

/* =========================
   Remove Staff Modal
========================= */

let _removeUserId = null;

function openRemoveModal(userId, name) {
    _removeUserId = userId;
    document.getElementById('removeStaffName').textContent = name;
    const btn = document.getElementById('removeStaffConfirmBtn');
    btn.disabled = false;
    btn.style.opacity = '1';
    btn.textContent = 'Remove';
    document.getElementById('removeStaffOverlay').classList.add('open');
}

// Keep old name working (called from PHP-rendered rows)
function removeStaff(userId, name) {
    openRemoveModal(userId, name);
}

function closeRemoveModal() {
    document.getElementById('removeStaffOverlay').classList.remove('open');
    _removeUserId = null;
}

function confirmRemoveStaff() {
    if (_removeUserId === null) return;
    const btn = document.getElementById('removeStaffConfirmBtn');
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.textContent = 'Removing…';

    fetch('../../endpoints/staff.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'remove_staff',
                user_id: _removeUserId,
                csrf_token: window.PS_CSRF_TOKEN ?? ''
            })
        })
        .then(r => r.json())
        .then(data => {
            closeRemoveModal();
            if (data.success) {
                showToast(data.message || 'Removed!', 'success', 'Removed!');
                setTimeout(refreshStaffTable, 600);
            } else {
                showToast(data.message || 'Failed', 'error', 'Failed');
            }
        })
        .catch(() => {
            closeRemoveModal();
            showToast('Server unreachable.', 'error');
        });
}

/* =========================
   Invite Modal
========================= */

function openInvite() {
    document.getElementById('inviteOverlay')?.classList.add('open');
}

function closeInvite() {
    document.getElementById('inviteOverlay')?.classList.remove('open');
    ['invFirst', 'invLast', 'invEmail'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const role = document.getElementById('invRole');
    if (role) role.value = 'frontdesk';
}

function submitInvite() {
    const first = document.getElementById('invFirst')?.value.trim();
    const last = document.getElementById('invLast')?.value.trim();
    const email = document.getElementById('invEmail')?.value.trim();
    const role = document.getElementById('invRole')?.value;

    if (!first || !last || !email) {
        showToast('Please fill in all required fields.', 'warning', 'Missing Fields');
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showToast('Please enter a valid email address.', 'warning', 'Invalid Email');
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
                showToast(data.message || 'Invite sent!', 'success', 'Invite Sent!');
                setTimeout(refreshStaffTable, 800);
            } else {
                showToast(data.message || 'Failed to send invite.', 'error', 'Failed');
            }
        })
        .catch(() => {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Send Invite';
            }
            showToast('Server unreachable.', 'error');
        });
}

/* =========================
   Real-time (ps:staff_list)
========================= */

window.addEventListener('ps:staff_list', e => {
    const {
        list,
        counts
    } = e.detail || {};
    if (!list) return;

    const tbody = document.getElementById('staffTableBody');
    if (!tbody) return;

    list.forEach(s => {
        const existing = tbody.querySelector(`tr[data-user-id="${s.user_id}"]`);
        const html = buildStaffRow(s);

        if (existing) {
            if (existing.dataset.active !== String(parseInt(s.is_active))) {
                existing.outerHTML = html;
                const updated = tbody.querySelector(`tr[data-user-id="${s.user_id}"]`);
                if (updated) {
                    updated.style.transition = 'background 0.5s';
                    updated.style.background = '#eff6ff';
                    setTimeout(() => {
                        updated.style.background = '';
                    }, 1600);
                }
            }
        } else {
            tbody.querySelector('td[colspan]')?.closest('tr')?.remove();
            tbody.insertAdjacentHTML('beforeend', html);
            const newRow = tbody.querySelector(`tr[data-user-id="${s.user_id}"]`);
            if (newRow) {
                newRow.style.background = '#f0fdf4';
                setTimeout(() => {
                    newRow.style.transition = 'background 1.2s';
                    newRow.style.background = '';
                }, 100);
            }
            if (typeof showToast === 'function')
                showToast('New staff member added!', 'success', 'Staff Added');
        }
    });

    const countEl = document.getElementById('staff-count');
    if (countEl) countEl.textContent = list.length;

    if (counts) {
        Object.entries(counts).forEach(([role, cnt]) => {
            const el = document.getElementById('role-count-' + role);
            if (el) el.textContent = cnt;
        });
    }
});

/* =========================
   Role Filter Dropdown
========================= */

window.toggleRoleFilter = function () {
    const menu    = document.getElementById('roleFilterMenu');
    const chevron = document.getElementById('roleFilterChevron');
    const wrap    = document.getElementById('roleFilterWrap');
    const isOpen  = menu.style.display !== 'none';
    menu.style.display       = isOpen ? 'none' : 'block';
    chevron.style.transform  = isOpen ? '' : 'rotate(180deg)';
    wrap.classList.toggle('open', !isOpen);
};

window.selectRoleFilter = function (btn) {
    document.getElementById('roleFilter').value = btn.dataset.value;
    document.getElementById('roleFilterLabel').textContent = btn.textContent.trim();
    document.querySelectorAll('#roleFilterMenu .inv-status-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('roleFilterMenu').style.display = 'none';
    document.getElementById('roleFilterChevron').style.transform = '';
    document.getElementById('roleFilterWrap').classList.remove('open');
    // trigger refresh
    const search = document.getElementById('searchStaff')?.value ?? '';
    const role   = btn.dataset.value;
    refreshStaffTable(search, role);
};

/* =========================
   Init
========================= */

document.addEventListener('DOMContentLoaded', () => {
    injectModals();

    document.getElementById('open-invite-modal')?.addEventListener('click', openInvite);

    document.getElementById('inviteOverlay')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) closeInvite();
    });

    // ── Dynamic search (no page reload) ──────────────────
    let _staffSearchTimer;
    const searchInput = document.getElementById('searchStaff');

    function getFilters() {
        return {
            search: searchInput ? searchInput.value : '',
            role: document.getElementById('roleFilter')?.value ?? '',
        };
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(_staffSearchTimer);
            _staffSearchTimer = setTimeout(() => {
                const f = getFilters();
                refreshStaffTable(f.search, f.role);
            }, 300);
        });
    }

    // ── Custom role dropdown ──────────────────────────────
    document.addEventListener('click', function (e) {
        const wrap = document.getElementById('roleFilterWrap');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('roleFilterMenu').style.display = 'none';
            document.getElementById('roleFilterChevron').style.transform = '';
            wrap.classList.remove('open');
        }
    });
});