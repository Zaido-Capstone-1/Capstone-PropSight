'use strict';

let currentTaskId = null;

// ── Filters ──────────────────────────────────────────────────────

let tsCurrentPage = 1;
const tsRowsPerPage = 10;

function applyTsFilters() {
    const q = (document.getElementById('tsSearch')?.value || '').toLowerCase().trim();
    const status = document.getElementById('tsStatusVal')?.value || '';

    document.querySelectorAll('#tsTableBody tr[data-id]').forEach(row => {
        row.classList.remove('ts-pg-hidden');
        const show =
            (!q || (row.dataset.search || '').includes(q)) &&
            (!status || row.dataset.status === status);
        row.style.display = show ? '' : 'none';
    });

    const empty = document.getElementById('tsEmpty');
    const allRows = document.querySelectorAll('#tsTableBody tr[data-id]');
    const visible = Array.from(allRows).filter(r => r.style.display !== 'none');
    if (empty) empty.style.display = (allRows.length > 0 && visible.length === 0) ? 'block' : 'none';

    tsCurrentPage = 1;
    paginateTs();
}

function paginateTs() {
    const visible = Array.from(document.querySelectorAll('#tsTableBody tr[data-id]'))
        .filter(r => r.style.display !== 'none' && !r.classList.contains('ts-pg-hidden'));
    const total = visible.length;
    const totalPages = Math.max(1, Math.ceil(total / tsRowsPerPage));
    tsCurrentPage = Math.min(tsCurrentPage, totalPages);

    const start = (tsCurrentPage - 1) * tsRowsPerPage;
    const end = start + tsRowsPerPage;

    visible.forEach((row, i) => {
        if (i >= start && i < end) {
            row.style.display = '';
            row.classList.remove('ts-pg-hidden');
        } else {
            row.classList.add('ts-pg-hidden');
            row.style.display = 'none';
        }
    });

    renderTsFoot(total, totalPages, start, end);
}

function renderTsFoot(total, totalPages, start, end) {
    const foot = document.getElementById('tsFoot');
    const info = document.getElementById('tsPageInfo');
    const controls = document.getElementById('tsPageControls');
    const prevBtn = document.getElementById('tsPrevBtn');
    const nextBtn = document.getElementById('tsNextBtn');
    if (!foot) return;

    if (total === 0) {
        foot.style.display = 'none';
        return;
    }
    foot.style.display = '';

    info.innerHTML = `Showing <strong>${start + 1}–${Math.min(end, total)}</strong> of <strong>${total}</strong> maintenance request${total !== 1 ? 's' : ''}`;

    if (totalPages <= 1) {
        if (controls) controls.style.display = 'none';
        return;
    }
    if (controls) controls.style.display = 'flex';
    if (prevBtn) prevBtn.disabled = tsCurrentPage <= 1;
    if (nextBtn) nextBtn.disabled = tsCurrentPage >= totalPages;

    const wrap = document.getElementById('tsPageNumbers');
    if (!wrap) return;
    wrap.innerHTML = '';
    const cur = tsCurrentPage;
    const nums = [...new Set([1, totalPages, cur, cur - 1, cur + 1].filter(n => n >= 1 && n <= totalPages))].sort((a, b) => a - b);
    nums.forEach((n, i) => {
        if (i > 0 && n > nums[i - 1] + 1) {
            const el = document.createElement('span');
            el.className = 'txn-pg-ellipsis';
            el.textContent = '…';
            wrap.appendChild(el);
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'txn-pg-num' + (n === cur ? ' active' : '');
        btn.textContent = n;
        btn.onclick = () => {
            tsCurrentPage = n;
            paginateTs();
        };
        wrap.appendChild(btn);
    });
}

window.tsChangePage = function (dir) {
    tsCurrentPage += dir;
    paginateTs();
};

document.getElementById('tsSearch')?.addEventListener('input', applyTsFilters);
applyTsFilters();

// ── Status dropdown ───────────────────────────────────────────────

function toggleUrDrop(wrapId) {
    const wrap = document.getElementById(wrapId);
    const menu = wrap.querySelector('.ur-drop-menu');
    const isOpen = menu.style.display !== 'none';
    document.querySelectorAll('.ur-drop-wrap').forEach(w => {
        w.querySelector('.ur-drop-menu').style.display = 'none';
        w.classList.remove('open');
    });
    if (!isOpen) {
        menu.style.display = 'block';
        wrap.classList.add('open');
    }
}

function selectTsStatus(btn) {
    document.getElementById('tsStatusVal').value = btn.dataset.value;
    document.getElementById('tsStatusLabel').textContent = btn.textContent.trim();
    document.querySelectorAll('#tsStatusMenu .ur-drop-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tsStatusMenu').style.display = 'none';
    document.getElementById('tsStatusWrap').classList.remove('open');
    applyTsFilters();
}

document.addEventListener('click', e => {
    document.querySelectorAll('.ur-drop-wrap').forEach(wrap => {
        if (!wrap.contains(e.target)) {
            wrap.querySelector('.ur-drop-menu').style.display = 'none';
            wrap.classList.remove('open');
        }
    });
});

// ── Modal ─────────────────────────────────────────────────────────

function openTaskModal(taskId, data) {
    currentTaskId = taskId;
 
    // ── Header ───────────────────────────────────────────────────
    document.getElementById('taskModalTitle').textContent =
        'Task #' + String(taskId).padStart(4, '0');
    document.getElementById('taskModalSub').textContent =
        data.property_name ? '📍 ' + data.property_name : '';
 
    // ── Colour maps ───────────────────────────────────────────────
    const priorityColors = {
        urgent: '#ef4444', high: '#ef4444',
        medium: '#d97706', normal: '#d97706',
        low: '#16a34a'
    };
    const statusLabels = {
        open: 'Open', in_progress: 'In Progress',
        pending: 'Pending', completed: 'Done', closed: 'Closed'
    };
    const statusColors = {
        open: '#ef4444', in_progress: '#3b82f6',
        pending: '#d97706', completed: '#16a34a', closed: '#6b7280'
    };
 
    const sv  = data.request_status || 'pending';
    const pri = (data.priority || 'normal').toLowerCase();
    const priColor  = priorityColors[pri] || '#d97706';
    const statColor = statusColors[sv]   || '#6b7280';
 
    // Format date — request_date is YYYY-MM-DD (date only, no time)
    // Append T12:00:00 to avoid UTC-midnight timezone rollback issues
    const reqDate = data.request_date
        ? new Date(data.request_date + 'T12:00:00').toLocaleDateString(undefined,
            { month: 'short', day: 'numeric', year: 'numeric' })
        : '—';
 
    // ── 4-column meta strip ───────────────────────────────────────
    document.getElementById('taskDetailGrid').innerHTML = `
        <div class="sm-meta-item">
            <div class="sm-meta-label">Property</div>
            <div class="sm-meta-value">${esc(data.property_name || '—')}</div>
        </div>
        <div class="sm-meta-item">
            <div class="sm-meta-label">Priority</div>
            <div class="sm-meta-value" style="color:${priColor};">
                ${esc(ucFirst(pri))}
            </div>
        </div>
        <div class="sm-meta-item">
            <div class="sm-meta-label">Status</div>
            <div class="sm-meta-value" style="color:${statColor};">
                ${statusLabels[sv] || sv}
            </div>
        </div>
        <div class="sm-meta-item">
            <div class="sm-meta-label">Requested</div>
            <div class="sm-meta-value">${esc(reqDate)}</div>
        </div>
    `;
 
    // ── Description block ─────────────────────────────────────────
    const desc = data.issue_description || '';
    const descBlock = document.getElementById('taskDescBlock');
    const descText  = document.getElementById('taskDescText');
    if (desc) {
        descText.textContent = desc;
        descBlock.style.display = '';
    } else {
        descBlock.style.display = 'none';
    }
 
    // ── Status select ─────────────────────────────────────────────
    document.getElementById('taskStatusSelect').value = sv;
 
    // ── Open modal ────────────────────────────────────────────────
    document.getElementById('taskModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeTaskModal() {
    document.getElementById('taskModal').classList.remove('open');
    document.body.style.overflow = '';
    currentTaskId = null;
}

document.getElementById('taskModal').addEventListener('click', e => {
    if (e.target === document.getElementById('taskModal')) closeTaskModal();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeTaskModal();
});

async function updateTaskStatus() {
    if (!currentTaskId) return;
    const status = document.getElementById('taskStatusSelect').value;

    // Capture old status from the row before the API call
    const row = document.querySelector(`#tsTableBody tr[data-id="${currentTaskId}"]`);
    const oldStatus = row?.dataset.status || null;

    const fd = new FormData();
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('action', 'update_status');
    fd.append('request_id', currentTaskId);
    fd.append('status', status);

    try {
        const res = await fetch('../../endpoints/admin/update_maintenance.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {

            // ── 1. Update badge in row ───────────────────────────────────
            const badge = document.getElementById('badge-' + currentTaskId);
            const clsMap = {
                open: 'tbadge-open',
                in_progress: 'tbadge-progress',
                pending: 'tbadge-pending',
                completed: 'tbadge-done',
                closed: 'tbadge-done'
            };
            const lblMap = {
                open: 'Open',
                in_progress: 'In Progress',
                pending: 'Pending',
                completed: 'Done',
                closed: 'Closed'
            };
            if (badge) {
                badge.className = 'badge ' + (clsMap[status] || 'tbadge-pending');
                badge.textContent = lblMap[status] || status;
            }
            if (row) row.dataset.status = status;

            // ── 2. Update stat cards ─────────────────────────────────────
            const statMap = {
                open: 'rt-task-open',
                in_progress: 'rt-task-progress',
                completed: 'rt-task-done',
                closed: 'rt-task-done',
                pending: null, // no dedicated card
            };

            if (oldStatus && oldStatus !== status) {
                // Decrement old card
                const oldId = statMap[oldStatus];
                if (oldId) {
                    const el = document.getElementById(oldId);
                    if (el) el.textContent = Math.max(0, parseInt(el.textContent, 10) - 1);
                }
                // Increment new card
                const newId = statMap[status];
                if (newId) {
                    const el = document.getElementById(newId);
                    if (el) el.textContent = parseInt(el.textContent, 10) + 1;
                }
                // Total stays unchanged
            }

            showToast('Status updated successfully.');
            closeTaskModal();
            applyTsFilters();
        } else {
            showToast(data.message || 'Failed to update.', 'error');
        }
    } catch (e) {
        showToast('Network error.', 'error');
    }
}

async function deleteTask(taskId) {
    if (!confirm('Delete this maintenance request? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('action', 'delete');
    fd.append('request_id', taskId);

    try {
        const res = await fetch('../../endpoints/admin/update_maintenance.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            const row = document.querySelector(`#tsTableBody tr[data-id="${taskId}"]`);
            if (row) {
                // Decrement the relevant stat card before removing the row
                const deletedStatus = row.dataset.status || null;
                const statMap = {
                    open: 'rt-task-open',
                    in_progress: 'rt-task-progress',
                    completed: 'rt-task-done',
                    closed: 'rt-task-done'
                };
                const cardId = statMap[deletedStatus];
                if (cardId) {
                    const el = document.getElementById(cardId);
                    if (el) el.textContent = Math.max(0, parseInt(el.textContent, 10) - 1);
                }
                // Always decrement total
                const totalEl = document.getElementById('rt-task-total');
                if (totalEl) totalEl.textContent = Math.max(0, parseInt(totalEl.textContent, 10) - 1);

                row.style.transition = 'opacity .3s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    applyTsFilters();
                }, 300);
            }
            showToast('Request deleted.');
        } else {
            showToast(data.message || 'Failed to delete.', 'error');
        }
    } catch (e) {
        showToast('Network error.', 'error');
    }
}

// ── Helpers ───────────────────────────────────────────────────────

function esc(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function ucFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}