const _roleDefs = {
    admin:       ['Admin',         'role-admin'],
    manager:     ['Manager',       'role-manager'],
    frontdesk:   ['Front Desk',    'role-frontdesk'],
    accounting:  ['Accounting',    'role-accounting'],
    maintenance: ['Maintenance',   'role-maintenance'],
};

function lastActiveLabel(ts) {
    if (!ts) return 'Never';
    const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 1000);
    if (diff < 60)   return 'Just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
}

function buildStaffRow(s) {
    const fullName = (s.first_name + ' ' + s.last_name).trim();
    const initials = (s.first_name || '').charAt(0).toUpperCase();
    const roleDef  = _roleDefs[s.role] || [s.role, ''];
    const isActive = parseInt(s.is_active);
    const removeBtn = isSelf ? '' : `<button class="tbl-btn danger" onclick="removeStaff(${s.user_id}, '${fullName.replace(/'/g,"\'")}')">Remove</button>`;
    return `<tr data-user-id="${s.user_id}" data-active="${isActive}">
      <td><div style="display:flex;align-items:center;gap:9px;">
        <div class="staff-avatar">${initials}</div>
        <div>
          <div style="font-weight:700;font-size:.84rem;">${fullName}</div>
          <div style="font-size:.72rem;color:#94a3b8;">${s.email}</div>
        </div>
      </div></td>
      <td><span class="role-pill ${roleDef[1]}">${roleDef[0]}</span></td>
      <td style="font-size:.78rem;color:#94a3b8;">${lastActiveLabel(s.last_login)}</td>
      <td><span class="badge badge-${isActive ? 'success' : 'gray'}">${isActive ? 'Active' : 'Inactive'}</span></td>
      <td><div class="action-wrap">
        <button class="tbl-btn" onclick="toggleActive(${s.user_id}, '${fullName.replace(/'/g,"\'")}', ${isActive})">${isActive ? 'Deactivate' : 'Activate'}</button>
        ${removeBtn}
      </div></td>
    </tr>`;
}

function refreshStaffTable() {
    fetch('../../api/staff.php', { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const tbody = document.getElementById('staffTableBody');
            if (!tbody) return;
            if (!data.staff.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#94a3b8;">No staff found.</td></tr>';
            } else {
                tbody.innerHTML = data.staff.map(buildStaffRow).join('');
            }
            const countEl = document.getElementById('staff-count');
            if (countEl) countEl.textContent = data.staff.length;
            // Update role counts
            if (data.counts) {
                Object.entries(data.counts).forEach(([role, cnt]) => {
                    const el = document.getElementById('role-count-' + role);
                    if (el) el.textContent = cnt;
                });
            }
        }).catch(() => {});
}

// Real-time: listen for staff list updates pushed by realtime.js
window.addEventListener('ps:staff_list', e => {
    const { list, counts } = e.detail || {};
    if (!list) return;
    const tbody = document.getElementById('staffTableBody');
    if (!tbody) return;
    list.forEach(s => {
        const existing = tbody.querySelector(`tr[data-user-id="${s.user_id}"]`);
        const html = buildStaffRow(s);
        if (existing) {
            const prev = existing.dataset.active;
            if (prev !== String(parseInt(s.is_active))) {
                existing.outerHTML = html;
                const newRow = tbody.querySelector(`tr[data-user-id="${s.user_id}"]`);
                if (newRow) { newRow.style.transition='background 0.5s'; newRow.style.background='#eff6ff'; setTimeout(()=>{ newRow.style.background=''; }, 1600); }
            }
        } else {
            // New staff member added
            const emptyRow = tbody.querySelector('td[colspan]')?.closest('tr');
            if (emptyRow) emptyRow.remove();
            tbody.insertAdjacentHTML('beforeend', html);
            const newRow = tbody.querySelector(`tr[data-user-id="${s.user_id}"]`);
            if (newRow) { newRow.style.background='#f0fdf4'; setTimeout(()=>{ newRow.style.transition='background 1.2s'; newRow.style.background=''; }, 100); }
            if (typeof showToast === 'function') showToast('New staff member added!', 'success', 'Staff Added');
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
