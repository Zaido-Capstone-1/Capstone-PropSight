const API = '../../api/admin/loyalty_rewards.php';
let _deleteId = null;

function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ══════════════════════════════════════════════════
   CUSTOM STATUS DROPDOWN (filter bar)
══════════════════════════════════════════════════ */
window.toggleRwStatusDropdown = function () {
    const menu    = document.getElementById('rwStatusMenu');
    const chevron = document.getElementById('rwStatusChevron');
    const wrap    = document.getElementById('rwStatusDropdownWrap');
    if (!menu) return;
    const isOpen = menu.style.display !== 'none';
    menu.style.display      = isOpen ? 'none' : 'block';
    chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
    wrap.classList.toggle('open', !isOpen);
};

window.selectRwStatusOpt = function (btn) {
    const val = btn.dataset.value;

    // Update hidden input and re-filter
    const hidden = document.getElementById('rwStatusFilter');
    if (hidden) { hidden.value = val; }
    filterTable();

    // Update trigger label — plain text only, no dot
    const label = document.getElementById('rwStatusTriggerLabel');
    if (val === '') {
        label.textContent = 'All Status';
    } else {
        label.textContent = val === '1' ? 'Active' : 'Inactive';
    }

    // Mark active option
    document.querySelectorAll('#rwStatusMenu .rw-status-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Close dropdown
    const menu    = document.getElementById('rwStatusMenu');
    const chevron = document.getElementById('rwStatusChevron');
    const wrap    = document.getElementById('rwStatusDropdownWrap');
    if (menu)    menu.style.display      = 'none';
    if (chevron) chevron.style.transform = '';
    if (wrap)    wrap.classList.remove('open');
};

// Close filter dropdown on outside click
document.addEventListener('click', function (e) {
    const wrap = document.getElementById('rwStatusDropdownWrap');
    if (wrap && !wrap.contains(e.target)) {
        const menu    = document.getElementById('rwStatusMenu');
        const chevron = document.getElementById('rwStatusChevron');
        if (menu)    menu.style.display      = 'none';
        if (chevron) chevron.style.transform = '';
        wrap.classList.remove('open');
    }
});

/* Helper: set modal select value */
function setModalStatusDropdown(val) {
    const el = document.getElementById('rwStatus');
    if (el) el.value = val;
}

/* ══════════════════════════════════════════════════
   CLIENT-SIDE SEARCH / FILTER
══════════════════════════════════════════════════ */
function filterTable() {
    const search = document.getElementById('rwSearchInput').value.toLowerCase();
    const status = document.getElementById('rwStatusFilter').value;
    const rows   = document.querySelectorAll('#rwTable tbody tr[data-id]');
    let visible  = 0;

    rows.forEach(row => {
        const name   = (row.dataset.name || '').toLowerCase();
        const desc   = (row.dataset.desc || '').toLowerCase();
        const active = row.dataset.active;

        const matchSearch = !search || name.includes(search) || desc.includes(search);
        const matchStatus = status === '' || active === status;

        row.style.display = matchSearch && matchStatus ? '' : 'none';
        if (matchSearch && matchStatus) visible++;
    });

    const noResults = document.getElementById('rwNoResults');
    if (noResults) noResults.style.display = (rows.length > 0 && visible === 0) ? 'block' : 'none';

    const footer = document.getElementById('rwFooter');
    if (footer) footer.textContent = `Showing ${visible} of ${rows.length} reward${rows.length !== 1 ? 's' : ''}`;
}

/* ══════════════════════════════════════════════════
   MODALS
══════════════════════════════════════════════════ */
function openAddModal() {
    document.getElementById('rwModalTitle').textContent = 'Add Reward';
    document.getElementById('rwRewardId').value = '';
    document.getElementById('rwName').value = '';
    document.getElementById('rwDesc').value = '';
    document.getElementById('rwPoints').value = '';
    setModalStatusDropdown('1');
    document.getElementById('rwErr').style.display = 'none';
    document.getElementById('rwSaveBtn').textContent = 'Save Reward';
    document.getElementById('rwModal').classList.add('open');
}

function openEditModal(id) {
    const row = document.querySelector(`#rwTable tbody tr[data-id="${id}"]`);
    if (!row) return;
    document.getElementById('rwModalTitle').textContent = 'Edit Reward';
    document.getElementById('rwRewardId').value = id;
    document.getElementById('rwName').value    = row.dataset.name;
    document.getElementById('rwDesc').value    = row.dataset.desc;
    document.getElementById('rwPoints').value  = row.dataset.pts;
    setModalStatusDropdown(row.dataset.active);
    document.getElementById('rwErr').style.display = 'none';
    document.getElementById('rwSaveBtn').textContent = 'Update Reward';
    document.getElementById('rwModal').classList.add('open');
}

function closeModal() {
    document.getElementById('rwModal').classList.remove('open');
    // Also close modal status dropdown if open
    const menu = document.getElementById('rwModalStatusMenu');
    const wrap = document.getElementById('rwModalStatusWrap');
    if (menu) menu.style.display = 'none';
    if (wrap) wrap.classList.remove('open');
}

function openDeleteModal(id, name) {
    _deleteId = id;
    document.getElementById('rwDeleteName').textContent = `"${name}" will be permanently removed.`;
    document.getElementById('rwDeleteModal').classList.add('open');
}

function closeDeleteModal() {
    document.getElementById('rwDeleteModal').classList.remove('open');
    _deleteId = null;
}

async function confirmDelete() {
    if (!_deleteId) return;
    const btn = document.getElementById('rwDeleteBtn');
    btn.disabled    = true;
    btn.textContent = 'Deleting…';

    const fd = new FormData();
    fd.append('action',     'delete');
    fd.append('reward_id',  _deleteId);
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');

    try {
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        closeDeleteModal();
        if (typeof showToast === 'function') showToast(data.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        if (typeof showToast === 'function') showToast(e.message, 'error');
        btn.disabled    = false;
        btn.textContent = 'Delete';
    }
}

/* ── Save / Update ── */
async function saveReward() {
    const id     = document.getElementById('rwRewardId').value;
    const name   = document.getElementById('rwName').value.trim();
    const desc   = document.getElementById('rwDesc').value.trim();
    const pts    = parseInt(document.getElementById('rwPoints').value);
    const status = document.getElementById('rwStatus').value;
    const errEl  = document.getElementById('rwErr');
    const btn    = document.getElementById('rwSaveBtn');

    if (!name)           { showErr('Reward name is required.'); return; }
    if (!pts || pts < 1) { showErr('Points cost must be at least 1.'); return; }

    errEl.style.display = 'none';
    btn.disabled    = true;
    btn.textContent = 'Saving…';

    const fd = new FormData();
    fd.append('action',      id ? 'update' : 'create');
    if (id) fd.append('reward_id', id);
    fd.append('name',        name);
    fd.append('description', desc);
    fd.append('points_cost', pts);
    fd.append('is_active',   status);
    fd.append('csrf_token',  window.PS_CSRF_TOKEN || '');

    try {
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        if (typeof showToast === 'function') showToast(data.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        showErr(e.message);
        btn.disabled    = false;
        btn.textContent = id ? 'Update Reward' : 'Save Reward';
    }
}

/* ── Toggle Reward Modal ── */
let _toggleRewardId = null, _toggleRewardNewActive = null;

function toggleReward(id, newActive) {
    _toggleRewardId        = id;
    _toggleRewardNewActive = newActive;

    const isActivating = newActive == 1;
    const icon  = document.getElementById('rwToggleIcon');
    const title = document.getElementById('rwToggleTitle');
    const name  = document.querySelector(`#rwTable tbody tr[data-id="${id}"]`)?.dataset.name || '';
    const note  = document.getElementById('rwToggleNote');
    const btn   = document.getElementById('rwToggleBtn');

    if (isActivating) {
        icon.style.background = '#dcfce7';
        icon.innerHTML = `<svg fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">
            <circle cx="12" cy="12" r="9"/><polyline points="9 12 11 14 15 10"/></svg>`;
        title.textContent    = 'Activate Reward?';
        note.textContent     = 'This reward will become visible and redeemable by guests.';
        btn.textContent      = 'Activate';
        btn.style.background = '#16a34a';
        btn.style.boxShadow  = '0 2px 8px rgba(22,163,74,.25)';
        btn.className        = 'btn';
    } else {
        icon.style.background = '#fef3c7';
        icon.innerHTML = `<svg fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24" width="26" height="26">
            <circle cx="12" cy="12" r="9"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>`;
        title.textContent    = 'Deactivate Reward?';
        note.textContent     = 'This reward will be hidden and cannot be redeemed by guests.';
        btn.textContent      = 'Deactivate';
        btn.style.background = '#d97706';
        btn.style.boxShadow  = '0 2px 8px rgba(217,119,6,.25)';
        btn.className        = 'btn';
    }

    document.getElementById('rwToggleRewardName').textContent = name;
    btn.disabled      = false;
    btn.style.opacity = '1';
    btn.style.color   = '#fff';
    document.getElementById('rwToggleModal').classList.add('open');
}

function closeToggleRewardModal() {
    document.getElementById('rwToggleModal').classList.remove('open');
    _toggleRewardId        = null;
    _toggleRewardNewActive = null;
}

async function confirmToggleReward() {
    if (_toggleRewardId === null) return;
    const btn = document.getElementById('rwToggleBtn');
    btn.disabled      = true;
    btn.style.opacity = '0.7';
    btn.textContent   = 'Saving…';

    const fd = new FormData();
    fd.append('action',     'toggle');
    fd.append('reward_id',  _toggleRewardId);
    fd.append('is_active',  _toggleRewardNewActive);
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');

    try {
        const res  = await fetch(API, { method: 'POST', body: fd });
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        closeToggleRewardModal();
        if (typeof showToast === 'function') showToast(data.message, 'success');
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        if (typeof showToast === 'function') showToast(e.message, 'error');
        btn.disabled      = false;
        btn.style.opacity = '1';
        btn.textContent   = _toggleRewardNewActive == 1 ? 'Activate' : 'Deactivate';
    }
}

function showErr(msg) {
    const el = document.getElementById('rwErr');
    el.textContent   = msg;
    el.style.display = 'block';
}

// Overlay click to close
document.getElementById('rwToggleModal').addEventListener('click', e => {
    if (e.target.id === 'rwToggleModal') closeToggleRewardModal();
});
document.getElementById('rwModal').addEventListener('click', e => {
    if (e.target.id === 'rwModal') closeModal();
});
document.getElementById('rwDeleteModal').addEventListener('click', e => {
    if (e.target.id === 'rwDeleteModal') closeDeleteModal();
});