// ── Data ──────────────────────────────────────────────────────────────────────
const properties = window.__AMENITY_DATA__.properties;
const ICONS = [
    { key: 'pool', label: 'Pool', svg: '<path d="M2 12h20M2 17c2-2 4-2 6 0s4 2 6 0 4-2 6 0M7 12V7a5 5 0 0 1 10 0v5"/>' },
    { key: 'gym', label: 'Gym', svg: '<path d="M6 4v16M18 4v16M4 8h4M16 8h4M4 16h4M16 16h4M8 12h8"/>' },
    { key: 'parking', label: 'Parking', svg: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>' },
    { key: 'security', label: 'Security', svg: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>' },
    { key: 'wifi', label: 'WiFi', svg: '<path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/>' },
    { key: 'cafe', label: 'Café', svg: '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>' },
    { key: 'gameroom', label: 'Game Room', svg: '<line x1="6" y1="11" x2="10" y2="11"/><line x1="8" y1="9" x2="8" y2="13"/><line x1="15" y1="12" x2="15.01" y2="12"/><line x1="17" y1="10" x2="17.01" y2="10"/><rect x="2" y="6" width="20" height="12" rx="2"/>' },
    { key: 'storage', label: 'Storage', svg: '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>' },
    { key: 'garden', label: 'Garden', svg: '<path d="M12 22V12M12 12C12 7 7 3 3 3c0 4 2 8 9 9M12 12c0-5 5-9 9-9-1 4-4 8-9 9"/>' },
    { key: 'laundry', label: 'Laundry', svg: '<rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="13" r="5"/><circle cx="12" cy="13" r="2"/><path d="M8 6h.01M11 6h.01"/>' },
    { key: 'elevator', label: 'Elevator', svg: '<rect x="3" y="2" width="18" height="20" rx="2"/><path d="M9 2v20M15 7l-3-3-3 3M15 17l-3 3-3-3"/>' },
    { key: 'playground', label: 'Playground', svg: '<circle cx="12" cy="8" r="3"/><path d="M5 20a7 7 0 0 1 14 0"/><line x1="12" y1="11" x2="12" y2="14"/>' },
    { key: 'bbq', label: 'BBQ', svg: '<path d="M4 11h16M12 11V4M6 11l-2 9M18 11l2 9M9 20h6"/><circle cx="12" cy="4" r="1"/>' },
    { key: 'cctv', label: 'CCTV', svg: '<path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/>' },
    { key: 'rooftop', label: 'Rooftop', svg: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>' },
    { key: 'clubhouse', label: 'Clubhouse', svg: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>' },
    { key: 'spa', label: 'Spa', svg: '<path d="M12 22c-4.97 0-9-2.69-9-6 0-1.5.75-2.87 2-3.9C6.56 10.85 9.12 10 12 10s5.44.85 7 2.1C20.25 13.13 21 14.5 21 16c0 3.31-4.03 6-9 6z"/><path d="M12 10C9 7 7 4 9 2c1 2 4 3 3 8M12 10c3-3 5-6 3-8-1 2-4 3-3 8"/>' },
    { key: 'generator', label: 'Generator', svg: '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>' },
    { key: 'trash', label: 'Waste Area', svg: '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/>' },
    { key: 'water', label: 'Water', svg: '<path d="M12 2c0 6-8 10-8 14a8 8 0 0 0 16 0c0-4-8-8-8-14z"/>' },
    { key: 'balcony', label: 'Balcony', svg: '<rect x="3" y="11" width="18" height="10" rx="1"/><path d="M3 11V7a9 9 0 0 1 18 0v4M9 21v-4a3 3 0 0 1 6 0v4"/>' },
    { key: 'ac', label: 'Air Con', svg: '<rect x="2" y="5" width="20" height="8" rx="2"/><path d="M7 13v4M12 13v4M17 13v4M7 17H5M12 17h-2M17 17h-2"/>' },
    { key: 'shower', label: 'Shower', svg: '<path d="M4 4h2a8 8 0 0 1 16 0v2"/><path d="M6.5 13.5c1 1 1 2.5 0 3.5s-2.5.5-3 2"/><line x1="18" y1="6" x2="6" y2="18"/>' },
];

// ── Helpers ───────────────────────────────────────────────────────────────────
function makeSvg(path, size) {
    size = size || 20;
    return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:' + size + 'px;height:' + size + 'px;flex-shrink:0;">' + path + '</svg>';
}
function getIconSvg(key, size) {
    var icon = ICONS.find(function (i) { return i.key === key; }) || ICONS[0];
    return makeSvg(icon.svg, size || 20);
}
function statusLabel(s) {
    return { available: 'Available', unavailable: 'Unavailable', maintenance: 'Under Maintenance' }[s] || s;
}
function animateStat(el, val) {
    if (!el) return;
    el.style.transition = 'opacity .2s';
    el.style.opacity = '0';
    setTimeout(function () { el.textContent = val; el.style.opacity = '1'; }, 200);
}

// ── Stats ─────────────────────────────────────────────────────────────────────
async function refreshStats() {
    try {
        var data = await fetch('../../api/admin/get_amenity_stats.php').then(function (r) { return r.json(); });
        if (data.status !== 'success') return;
        animateStat(document.getElementById('stat-total'), data.stats.total);
        animateStat(document.getElementById('stat-available'), data.stats.available);
        animateStat(document.getElementById('stat-unavailable'), data.stats.unavailable);
        animateStat(document.getElementById('stat-maintenance'), data.stats.maintenance);
    } catch (e) { console.error(e); }
}

// ── Filters ───────────────────────────────────────────────────────────────────
function applyFilters() {
    var status = document.getElementById('am-filter-status').value;
    var property = document.getElementById('am-filter-property').value;
    var cards = document.querySelectorAll('.amenity-card');
    var sections = document.querySelectorAll('.prop-section');
    var visible = 0;

    var countEl = document.getElementById('am-count');
    if (countEl) countEl.textContent = 'Showing ' + visible + ' amenit' + (visible !== 1 ? 'ies' : 'y');
}

document.getElementById('am-filter-status').addEventListener('change', applyFilters);
document.getElementById('am-filter-property').addEventListener('change', applyFilters);
applyFilters();

// ── Icon picker ───────────────────────────────────────────────────────────────
function iconPickerHtml(selected) {
    selected = selected || 'pool';
    var buttons = ICONS.map(function (ic) {
        var active = ic.key === selected;
        return '<button type="button" class="icon-opt" data-key="' + ic.key + '" title="' + ic.label + '" '
            + 'style="height:48px;border-radius:9px;border:1.5px solid ' + (active ? '#3b82f6' : 'var(--border)') + ';'
            + 'background:' + (active ? '#eff6ff' : 'var(--bg,#f8fafc)') + ';cursor:pointer;'
            + 'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;'
            + 'transition:border-color .12s,background .12s;padding:4px 2px;" '
            + 'onclick="document.querySelectorAll(\'.icon-opt\').forEach(function(b){'
            + 'b.style.borderColor=\'var(--border)\';b.style.background=\'var(--bg,#f8fafc)\';});'
            + 'this.style.borderColor=\'#3b82f6\';this.style.background=\'#eff6ff\';'
            + 'document.getElementById(\'m-icon\').value=this.dataset.key;">'
            + makeSvg(ic.svg, 17).replace('stroke="currentColor"', 'stroke="#64748b"')
            + '<span style="font-size:9px;color:var(--text-soft);line-height:1;">' + ic.label + '</span>'
            + '</button>';
    }).join('');
    return '<div style="display:grid;grid-template-columns:repeat(6,1fr);gap:6px;margin-top:6px;">'
        + buttons + '</div>'
        + '<input type="hidden" id="m-icon" value="' + selected + '">';
}

// ── Add modal ─────────────────────────────────────────────────────────────────
document.querySelectorAll('.open-add-amenity').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openAddModal(this.dataset.pid, this.dataset.pname);
    });
});

function openAddModal(pid, pname) {
    PS.openModal(
        '<div class="ps-modal-title">Add Amenity \u2014 ' + pname + '</div>'
        + '<div class="ps-modal-grid">'
        + '<div class="form-group full"><label>Name <span style="color:var(--danger)">*</span></label>'
        + '<input type="text" id="m-name" placeholder="e.g. Swimming Pool"></div>'
        + '<div class="form-group full"><label>Status</label>'
        + '<select id="m-status" style="width:100%;">'
        + '<option value="available">Available</option>'
        + '<option value="unavailable">Unavailable</option>'
        + '<option value="maintenance">Under Maintenance</option>'
        + '</select></div>'
        + '<div class="form-group full"><label>Icon</label>' + iconPickerHtml('pool') + '</div>'
        + '</div>'
        + '<div class="ps-modal-footer">'
        + '<button class="btn btn-secondary" data-ps-cancel>Cancel</button>'
        + '<button class="btn btn-primary" id="m-save">Add Amenity</button>'
        + '</div>',
        {
            onMount: function (bd) {
                bd.querySelector('#m-save').addEventListener('click', async function () {
                    var name = bd.querySelector('#m-name').value.trim();
                    var status = bd.querySelector('#m-status').value;
                    var icon = bd.querySelector('#m-icon').value || 'pool';
                    if (!name) { PS.toast('Name is required.', 'error'); return; }
                    var btn = bd.querySelector('#m-save');
                    btn.disabled = true; btn.textContent = 'Saving...';
                    try {
                        var fd = new FormData();
                        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
                        fd.append('property_id', pid);
                        fd.append('name', name);
                        fd.append('status', status);
                        fd.append('icon', icon);
                        var data = await fetch('../../api/admin/add_amenity.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
                        if (data.status === 'success') {
                            PS.toast(data.message, 'success');
                            bd.classList.remove('open');
                            setTimeout(function () { bd.remove(); }, 220);
                            insertCard(data.amenity, pid);
                            await refreshStats();
                            applyFilters();
                        } else {
                            PS.toast(data.message, 'error');
                            btn.disabled = false; btn.textContent = 'Add Amenity';
                        }
                    } catch (e) {
                        console.error(e);
                        PS.toast('Server error.', 'error');
                        btn.disabled = false; btn.textContent = 'Add Amenity';
                    }
                });
            }
        }
    );
}

// ── Edit modal ────────────────────────────────────────────────────────────────
function openEditModal(amenityId, name, icon, status, pid) {
    // Log for debugging
    console.log('Edit modal opened — amenity_id:', amenityId, 'name:', name);

    PS.openModal(
        '<div class="ps-modal-title">Edit Amenity</div>'
        + '<div class="ps-modal-grid">'
        + '<div class="form-group full"><label>Name <span style="color:var(--danger)">*</span></label>'
        + '<input type="text" id="m-name" value="' + name.replace(/"/g, '&quot;') + '"></div>'
        + '<div class="form-group full"><label>Status</label>'
        + '<select id="m-status" style="width:100%;">'
        + '<option value="available"' + (status === 'available' ? ' selected' : '') + '>Available</option>'
        + '<option value="unavailable"' + (status === 'unavailable' ? ' selected' : '') + '>Unavailable</option>'
        + '<option value="maintenance"' + (status === 'maintenance' ? ' selected' : '') + '>Under Maintenance</option>'
        + '</select></div>'
        + '<div class="form-group full"><label>Icon</label>' + iconPickerHtml(icon) + '</div>'
        + '</div>'
        + '<div class="ps-modal-footer">'
        + '<button class="btn btn-secondary" data-ps-cancel>Cancel</button>'
        + '<button class="btn btn-primary" id="m-save">Save Changes</button>'
        + '</div>',
        {
            onMount: function (bd) {
                bd.querySelector('#m-save').addEventListener('click', async function () {
                    var newName = bd.querySelector('#m-name').value.trim();
                    var newStatus = bd.querySelector('#m-status').value;
                    var newIcon = bd.querySelector('#m-icon').value || 'pool';
                    if (!newName) { PS.toast('Name is required.', 'error'); return; }
                    var btn = bd.querySelector('#m-save');
                    btn.disabled = true; btn.textContent = 'Saving...';
                    try {
                        var fd = new FormData();
                        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
                        fd.append('amenity_id', amenityId); // direct closure variable — never touches dataset again
                        fd.append('name', newName);
                        fd.append('status', newStatus);
                        fd.append('icon', newIcon);

                        console.log('Sending amenity_id:', amenityId); // verify in browser console

                        var data = await fetch('../../api/admin/edit_amenity.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
                        if (data.status === 'success') {
                            PS.toast(data.message, 'success');
                            bd.classList.remove('open');
                            setTimeout(function () { bd.remove(); }, 220);
                            updateCard(amenityId, newName, newStatus, newIcon);
                            await refreshStats();
                            applyFilters();
                        } else {
                            PS.toast(data.message, 'error');
                            btn.disabled = false; btn.textContent = 'Save Changes';
                        }
                    } catch (e) {
                        console.error(e);
                        PS.toast('Server error.', 'error');
                        btn.disabled = false; btn.textContent = 'Save Changes';
                    }
                });
            }
        }
    );
}

// ── Card HTML ─────────────────────────────────────────────────────────────────
function cardHTML(am) {
    var safeName = am.name.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
    return '<div class="am-icon-wrap ' + am.status + '">' + getIconSvg(am.icon) + '</div>'
        + '<div class="am-info">'
        + '<div class="am-name">' + am.name + '</div>'
        + '<div class="am-status ' + am.status + '">\u25cf ' + statusLabel(am.status) + '</div>'
        + '</div>'
        + '<div class="am-actions">'
        + '<button class="am-btn edit-btn" title="Edit"'
        + ' data-id="' + am.amenity_id + '"'
        + ' data-name="' + safeName + '"'
        + ' data-icon="' + am.icon + '"'
        + ' data-status="' + am.status + '"'
        + ' data-pid="' + am.property_id + '">'
        + '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;">'
        + '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>'
        + '<path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'
        + '</svg></button>'
        + '<button class="am-btn del delete-btn" title="Delete"'
        + ' data-id="' + am.amenity_id + '"'
        + ' data-name="' + safeName + '"'
        + ' data-pid="' + am.property_id + '">'
        + '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;">'
        + '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/>'
        + '</svg></button>'
        + '</div>';
}

// ── Insert card ───────────────────────────────────────────────────────────────
function insertCard(am, pid) {
    var grid = document.getElementById('grid-' + pid);
    var empty = document.getElementById('empty-' + pid);
    if (!grid) return;
    if (empty) empty.remove();

    var card = document.createElement('div');
    card.className = 'amenity-card';
    card.dataset.id = String(am.amenity_id);
    card.dataset.status = am.status;
    card.dataset.propertyId = String(pid);
    card.style.cssText = 'opacity:0;transform:scale(.97);transition:box-shadow .2s,transform .15s,opacity .22s;';
    card.innerHTML = cardHTML(am);

    grid.insertBefore(card, grid.firstChild);
    bindCard(card);
    requestAnimationFrame(function () { card.style.opacity = '1'; card.style.transform = 'scale(1)'; });

    var countEl = grid.closest('.prop-section') && grid.closest('.prop-section').querySelector('.prop-count');
    if (countEl) {
        var n = grid.querySelectorAll('.amenity-card').length;
        countEl.textContent = n + ' amenit' + (n !== 1 ? 'ies' : 'y');
    }
}

// ── Update card ───────────────────────────────────────────────────────────────
function updateCard(amenityId, name, status, icon) {
    var card = document.querySelector('.amenity-card[data-id="' + amenityId + '"]');
    if (!card) return;
    card.dataset.status = status;
    var wrap = card.querySelector('.am-icon-wrap');
    wrap.className = 'am-icon-wrap ' + status;
    wrap.innerHTML = getIconSvg(icon);
    card.querySelector('.am-name').textContent = name;
    var statusEl = card.querySelector('.am-status');
    statusEl.className = 'am-status ' + status;
    statusEl.textContent = '\u25cf ' + statusLabel(status);
    var editBtn = card.querySelector('.edit-btn');
    if (editBtn) { editBtn.dataset.name = name; editBtn.dataset.icon = icon; editBtn.dataset.status = status; }
}

// ── Bind card handlers ────────────────────────────────────────────────────────
function bindCard(card) {
    var editBtn = card.querySelector('.edit-btn');
    if (editBtn) {
        editBtn.addEventListener('click', function () {
            // Read directly from button's data attributes
            var id = this.dataset.id;
            var name = this.dataset.name;
            var icon = this.dataset.icon;
            var status = this.dataset.status;
            var pid = this.dataset.pid;
            console.log('Edit clicked — id:', id); // debug
            openEditModal(id, name, icon, status, pid);
        });
    }

    var delBtn = card.querySelector('.delete-btn');
    if (delBtn) {
        delBtn.addEventListener('click', function () {
            var id = this.dataset.id;
            var name = this.dataset.name;
            var pid = this.dataset.pid;
            var el = this.closest('.amenity-card');

            PS.confirm('Remove <strong>' + name + '</strong>? This cannot be undone.', async function () {
                try {
                    var fd = new FormData();
                    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
                    fd.append('amenity_id', id);
                    var data = await fetch('../../api/admin/delete_amenity.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
                    if (data.status === 'success') {
                        PS.toast(data.message, 'success');
                        el.style.transition = 'opacity .3s,transform .3s';
                        el.style.opacity = '0';
                        el.style.transform = 'scale(.95)';
                        setTimeout(async function () {
                            el.remove();
                            var grid = document.getElementById('grid-' + pid);
                            var remaining = grid ? grid.querySelectorAll('.amenity-card').length : 0;
                            if (remaining === 0 && grid) {
                                var e = document.createElement('div');
                                e.id = 'empty-' + pid;
                                e.className = 'am-empty';
                                e.textContent = 'No amenities added yet.';
                                grid.appendChild(e);
                            }
                            var countEl = grid && grid.closest('.prop-section') && grid.closest('.prop-section').querySelector('.prop-count');
                            if (countEl) countEl.textContent = remaining + ' amenit' + (remaining !== 1 ? 'ies' : 'y');
                            await refreshStats();
                            applyFilters();
                        }, 300);
                    } else {
                        PS.toast(data.message, 'error');
                    }
                } catch (e) {
                    console.error(e);
                    PS.toast('Server error.', 'error');
                }
            }, { title: 'Remove Amenity', confirmLabel: 'Remove', confirmClass: 'btn btn-danger' });
        });
    }
}

document.querySelectorAll('.amenity-card').forEach(bindCard);