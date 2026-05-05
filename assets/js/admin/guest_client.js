/* ── guest_client.js — PropSight Guests & Clients Page ── */
(function () {
    'use strict';

    let allRows = [];
    let activeFilter = 'all';
    let searchQuery = '';

    const pills = document.querySelectorAll('#filterPills .filter-pill-sm');
    const searchInput = document.getElementById('guestSearch');
    const countEl = document.getElementById('visibleCount');
    const noResults = document.getElementById('noResults');
    const tbody = document.getElementById('guestTableBody');

    function seedRows() {
        allRows = Array.from(tbody.querySelectorAll('tr[data-user-id]'));
    }

    function applyFilters() {
        let visible = 0;
        allRows.forEach(row => {
            const filterOk = activeFilter === 'all' || row.dataset.status === activeFilter;
            const searchOk = searchQuery === '' || (row.dataset.search || '').includes(searchQuery);
            const show = filterOk && searchOk;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        countEl.textContent = visible;
        noResults.style.display = visible === 0 ? 'block' : 'none';
    }

    pills.forEach(pill => {
        pill.addEventListener('click', () => {
            pills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            activeFilter = pill.dataset.filter;
            applyFilters();
        });
    });

    searchInput.addEventListener('input', () => {
        searchQuery = searchInput.value.trim().toLowerCase();
        applyFilters();
    });

    seedRows();

    function guestStatus(g) {
        if (parseInt(g.is_blacklisted)) return ['Blacklisted', 'danger', 'blacklisted'];
        if (parseInt(g.is_active)) return ['Active', 'success', 'active'];
        if (parseInt(g.total_stays) > 0) return ['Guest', 'info', 'inactive'];
        return ['New', 'pending', 'inactive'];
    }

    function esc(s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function buildRow(g) {
        const [statusLabel, statusCls, filterStatus] = guestStatus(g);
        const fullName = esc((g.first_name + ' ' + g.last_name).trim());
        const initials = ((g.first_name || '').charAt(0) + (g.last_name || '').charAt(0)).toUpperCase();
        const photo = g.profile_photo || '';
        const searchIdx = [g.first_name, g.last_name, g.email, g.phone || ''].join(' ').toLowerCase();
        const memberSince = new Date(g.created_at).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        const actionBtn = parseInt(g.is_blacklisted)
            ? `<button class="tbl-btn" onclick="toggleBlacklist(${g.user_id}, '${fullName}', 0)">Unblock</button>`
            : `<button class="tbl-btn danger" onclick="toggleBlacklist(${g.user_id}, '${fullName}', 1)">Block</button>`;

        const tr = document.createElement('tr');
        tr.dataset.userId = g.user_id;
        tr.dataset.status = filterStatus;
        tr.dataset.search = searchIdx;
        tr.innerHTML = `
          <td><div style="display:flex;align-items:center;gap:9px;">
              ${photo
                ? `<img src="../../${esc(photo)}" class="guest-avatar-img"
            onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
       <div class="guest-avatar" style="display:none;">${initials}</div>`
                : `<div class="guest-avatar">${initials}</div>`
            }
              <strong>${fullName}</strong>
          </div></td>
          <td style="font-size:.82rem;">${esc(g.email)}</td>
          <td style="font-size:.82rem;color:#64748b;">${esc(g.phone || '—')}</td>
          <td style="font-size:.82rem;">${g.current_unit ? esc(g.current_unit) : '<span style="color:#cbd5e1;">—</span>'}</td>
          <td style="font-size:.82rem;color:#64748b;">${memberSince}</td>
          <td style="text-align:center;font-weight:700;">${parseInt(g.total_stays) || 0}</td>
          <td><span class="badge badge-${statusCls}">${statusLabel}</span></td>
          <td><div class="action-wrap">${actionBtn}</div></td>`;
        return tr;
    }

    function refreshGuestTable() {
        fetch('../../api/guests.php', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                tbody.innerHTML = '';
                if (!data.guests.length) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">No guests found.</td></tr>';
                } else {
                    data.guests.forEach(g => tbody.appendChild(buildRow(g)));
                }
                updateGuestStats(data.stats);
                seedRows();
                applyFilters();
            }).catch(() => { });
    }

    function patchGuestRow(g) {
        const existing = tbody.querySelector(`tr[data-user-id="${g.user_id}"]`);
        const newRow = buildRow(g);
        if (existing) {
            existing.style.transition = 'background 0.4s';
            existing.style.background = '#fefce8';
            setTimeout(() => {
                tbody.replaceChild(newRow, existing);
                seedRows(); applyFilters();
            }, 350);
        } else {
            newRow.style.background = '#f0fdf4';
            const emptyRow = tbody.querySelector('td[colspan]')?.closest('tr');
            if (emptyRow) emptyRow.remove();
            tbody.prepend(newRow);
            setTimeout(() => { newRow.style.transition = 'background 1.2s'; newRow.style.background = ''; }, 100);
            seedRows(); applyFilters();
            if (typeof showToast === 'function') showToast('New guest registered!', 'success', 'New Guest');
        }
    }

    function updateGuestStats(stats) {
        if (!stats) return;
        const map = {
            'guest-stat-total': stats.total,
            'guest-stat-active': stats.active,
            'guest-stat-new-month': stats.new_month,
            'guest-stat-blacklisted': stats.blacklisted,
        };
        Object.entries(map).forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el && val !== undefined) el.textContent = val;
        });
    }

    window.toggleBlacklist = function (userId, name, blacklist) {
        const action = blacklist ? 'block' : 'unblock';
        if (!confirm(`Are you sure you want to ${action} ${name}?`)) return;
        showToast(`${blacklist ? 'Blocking' : 'Unblocking'} guest…`, 'info');
        fetch('../../api/guests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ user_id: userId, action: blacklist ? 'blacklist' : 'unblacklist' })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success', 'Done!');
                    refreshGuestTable();
                } else {
                    showToast(data.message, 'error', 'Failed');
                }
            })
            .catch(() => showToast('Server unreachable.', 'error'));
    };

    // Real-time event listeners
    window.addEventListener('ps:new_guests', e => { if (Array.isArray(e.detail)) e.detail.forEach(g => patchGuestRow(g)); });
    window.addEventListener('ps:guest_stats', e => updateGuestStats(e.detail));

    /* ─── Block / Unblock Modal ─────────────────────────────────────────────── */
    let _blockUserId = null;
    let _blockAction = null;

    const blockModal = document.getElementById('blockModal');
    const blockModalTitle = document.getElementById('blockModalTitle');
    const blockModalDesc = document.getElementById('blockModalDesc');
    const blockReasonWrap = document.getElementById('blockReasonWrap');
    const blockReasonInput = document.getElementById('blockReasonInput');
    const blockConfirmBtn = document.getElementById('blockModalConfirmBtn');
    const blockCancelBtn = document.getElementById('blockModalCancelBtn');

    function openBlockModal(userId, name, blacklist) {
        _blockUserId = userId;
        _blockAction = blacklist;
        blockModalTitle.textContent = blacklist ? 'Block Guest' : 'Unblock Guest';
        blockModalDesc.innerHTML = blacklist
            ? `Are you sure you want to block <strong>${esc(name)}</strong>? They will lose access to their account.`
            : `Are you sure you want to unblock <strong>${esc(name)}</strong>? Their account will be reactivated.`;
        blockReasonWrap.style.display = blacklist ? 'block' : 'none';
        blockReasonInput.value = '';
        blockConfirmBtn.textContent = blacklist ? 'Block' : 'Unblock';
        blockConfirmBtn.classList.toggle('danger', !!blacklist);
        blockModal.classList.add('active');
    }

    function closeBlockModal() {
        blockModal.classList.remove('active');
        blockReasonInput.value = '';
        _blockUserId = null;
        _blockAction = null;
    }

    blockCancelBtn.addEventListener('click', closeBlockModal);
    blockModal.addEventListener('click', e => { if (e.target === blockModal) closeBlockModal(); });

    blockConfirmBtn.addEventListener('click', function () {
        if (!_blockUserId) return;
        const reason = blockReasonInput.value.trim();
        const action = _blockAction ? 'blacklist' : 'unblacklist';
        this.disabled = true;
        this.textContent = 'Processing…';
        showToast(_blockAction ? 'Blocking guest…' : 'Unblocking guest…', 'info');

        fetch('../../api/guests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ user_id: _blockUserId, action, reason, csrf_token: window.PS_CSRF_TOKEN ?? '' })
        })
            .then(r => r.json())
            .then(data => {
                closeBlockModal();
                if (data.success) {
                    showToast(data.message, 'success', 'Done!');
                    refreshGuestTable();
                } else {
                    showToast(data.message, 'error', 'Failed');
                }
            })
            .catch(() => {
                closeBlockModal();
                showToast('Server unreachable.', 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.textContent = _blockAction ? 'Block' : 'Unblock';
            });
    });

    window.toggleBlacklist = (userId, name, blacklist) => openBlockModal(userId, name, blacklist);

    document.addEventListener('ps:refresh_guests', () => refreshGuestTable());

})();

