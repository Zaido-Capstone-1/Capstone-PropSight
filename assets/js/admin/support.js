/* ================================================================
   support.js
   Place at: assets/js/admin/support.js
   Depends on: ADMIN_ID being set inline by the PHP page
   ================================================================ */

'use strict';

// ── Modal helpers ────────────────────────────────────────────────

function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}

// Close on backdrop click
document.querySelectorAll('.sm-modal-overlay').forEach(el => {
    el.addEventListener('click', e => {
        if (e.target === el) closeModal(el.id);
    });
});

// Close on Escape key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.sm-modal-overlay.open').forEach(el => closeModal(el.id));
    }
});

// ── XSS-safe escape ─────────────────────────────────────────────

function esc(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ════════════════════════════════════════════════════════════════
//  TICKET MODAL
// ════════════════════════════════════════════════════════════════

let currentTicketId = null;

function openTicketModal(ticketId, data) {
    currentTicketId = ticketId;

    document.getElementById('ticketModalTitle').textContent =
        'Ticket #TKT-' + String(ticketId).padStart(5, '0');
    document.getElementById('ticketModalSub').textContent = data.subject || '';

    document.getElementById('ticketDetailGrid').innerHTML = `
        <div class="sm-detail-item">
            <div class="sm-field-label">Guest</div>
            <div class="val">${esc(data.user_name || '—')}</div>
            <div style="font-size:0.78rem;color:var(--text-soft,#6b7280);">${esc(data.user_email || '')}</div>
        </div>
        <div class="sm-detail-item">
            <div class="sm-field-label">Category</div>
            <div class="val">${esc(data.category || '—')}</div>
        </div>
        <div class="sm-detail-item">
            <div class="sm-field-label">Priority</div>
            <div class="val">${esc(data.priority || 'medium')}</div>
        </div>
        <div class="sm-detail-item">
            <div class="sm-field-label">Submitted</div>
            <div class="val">${esc(data.created_at ? data.created_at.slice(0, 10) : '—')}</div>
        </div>
    `;

    // Set status select
    const statusValue = data.status || 'open';
    document.getElementById('ticketStatusSelect').value = statusValue;

    document.getElementById('ticketReplyBody').value = '';
    document.getElementById('ticketMsgThread').innerHTML =
        '<div style="text-align:center;color:#94a3b8;padding:20px;font-size:0.84rem;">Loading…</div>';

    openModal('ticketModal');
    loadTicketMessages(ticketId);
}

async function loadTicketMessages(ticketId) {
    const wrap = document.getElementById('ticketMsgThread');
    try {
        const res = await fetch(`../../api/admin/support.php?action=messages&ticket_id=${ticketId}`);
        const data = await res.json();

        if (!data.success || !data.messages?.length) {
            wrap.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:16px;font-size:0.84rem;">No messages yet.</div>';
            return;
        }

        wrap.innerHTML = data.messages.map(m => {
            const isAdmin = m.is_admin == 1;
            return `
                <div>
                    <div class="sm-msg-bubble ${isAdmin ? 'admin' : 'user'}">${esc(m.body || '')}</div>
                    <div class="sm-msg-meta" style="text-align:${isAdmin ? 'right' : 'left'};color:var(--text-soft,#6b7280);">
                        ${esc(m.sender_name || (isAdmin ? 'Admin' : 'Guest'))} · ${m.created_at ? m.created_at.slice(0, 16) : ''}
                    </div>
                </div>`;
        }).join('');

        wrap.scrollTop = wrap.scrollHeight;
    } catch (e) {
        wrap.innerHTML = '<div style="color:#ef4444;padding:12px;font-size:0.83rem;">Failed to load messages.</div>';
    }
}

async function sendTicketReply() {
    if (!currentTicketId) return;

    const body = document.getElementById('ticketReplyBody').value.trim();
    if (!body) { showToast('Please enter a reply.', 'warning'); return; }

    const fd = new FormData();
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('action', 'admin_reply');
    fd.append('ticket_id', currentTicketId);
    fd.append('body', body);

    try {
        const res = await fetch('../../api/admin/support.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('ticketReplyBody').value = '';
            loadTicketMessages(currentTicketId);
            showToast('Reply sent successfully.');
        } else {
            showToast(data.message || 'Failed to send reply.', 'error');
        }
    } catch (e) {
        showToast('Network error.', 'error');
    }
}

async function updateTicketStatus() {
    if (!currentTicketId) return;

    const status = document.getElementById('ticketStatusSelect').value;

    // Capture the old status from the row before updating
    const btn = document.querySelector(`button[onclick*="openTicketModal(${currentTicketId},"]`);
    const row = btn?.closest('tr');
    const oldStatus = row?.dataset.status || null;

    const fd = new FormData();
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('action', 'update_status');
    fd.append('ticket_id', currentTicketId);
    fd.append('status', status);

    try {
        const res = await fetch('../../api/admin/support.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {

            // ── 1. Update badge in table row ─────────────────────────────
            if (row) {
                const badge = row.querySelector('td:nth-child(6) .badge');
                if (badge) {
                    const map = {
                        open:        'badge-open',
                        in_progress: 'badge-progress',
                        resolved:    'badge-done',
                        closed:      'badge-done',
                    };
                    badge.className = 'badge ' + (map[status] || 'badge-pending');
                    badge.textContent = status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
                }
                // Update data-status so filters still work correctly
                row.dataset.status = status;
            }

            // ── 2. Update stat cards ─────────────────────────────────────
            const statMap = {
                open:        'stat-spt-open',
                in_progress: 'stat-spt-progress',
                resolved:    'stat-spt-resolved',
                closed:      null,   // no dedicated card
            };

            if (oldStatus && oldStatus !== status) {
                // Decrement old status card
                const oldId = statMap[oldStatus];
                if (oldId) {
                    const el = document.getElementById(oldId);
                    if (el) el.textContent = Math.max(0, parseInt(el.textContent, 10) - 1);
                }

                // Increment new status card
                const newId = statMap[status];
                if (newId) {
                    const el = document.getElementById(newId);
                    if (el) el.textContent = parseInt(el.textContent, 10) + 1;
                }

                // Total stays the same (status change, not deletion)
            }

            showToast('Status updated to: ' + status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()));
        } else {
            showToast(data.message || 'Failed to update status.', 'error');
        }
    } catch (e) {
        showToast('Network error.', 'error');
    }
}

let sptCurrentPage = 1;
const sptRowsPerPage = 15;

function applySptFilters() {
    const q = (document.getElementById('sptSearch')?.value || '').toLowerCase().trim();
    const status = document.getElementById('sptStatusVal')?.value || '';

    let total = 0, count = 0;
    document.querySelectorAll('#sptTableBody tr').forEach(row => {
        if (row.querySelector('td[colspan]')) return;
        total++;
        row.classList.remove('spt-pg-hidden');
        const show =
            (!q || (row.dataset.search || '').includes(q)) &&
            (!status || status === 'all' || row.dataset.status === status);
        row.style.display = show ? '' : 'none';
        if (show) count++;
    });

    const staticEmpty = document.querySelector('#sptTableBody tr td[colspan]');
    if (staticEmpty) staticEmpty.closest('tr').style.display = 'none';

    const empty = document.getElementById('sptEmptyState');
    const emptyText = document.getElementById('sptEmptyText');
    if (empty) empty.style.display = count === 0 ? 'block' : 'none';
    if (emptyText) {
        emptyText.textContent = total === 0
            ? 'No tickets yet.'
            : 'No tickets match your filters.';
    }

    const countEl = document.getElementById('sptTicketCount');
    if (countEl) countEl.textContent = count;

    sptCurrentPage = 1;
    paginateSpt();
}

function paginateSpt() {
    const visible = Array.from(document.querySelectorAll('#sptTableBody tr'))
        .filter(r => !r.querySelector('td[colspan]') && r.style.display !== 'none' && !r.classList.contains('spt-pg-hidden'));
    const total = visible.length;
    const totalPages = Math.max(1, Math.ceil(total / sptRowsPerPage));
    sptCurrentPage = Math.min(sptCurrentPage, totalPages);

    const start = (sptCurrentPage - 1) * sptRowsPerPage;
    const end = start + sptRowsPerPage;

    visible.forEach((row, i) => {
        if (i >= start && i < end) { row.style.display = ''; row.classList.remove('spt-pg-hidden'); }
        else { row.classList.add('spt-pg-hidden'); row.style.display = 'none'; }
    });

    renderSptFoot(total, totalPages, start, end);
}   

function renderSptFoot(total, totalPages, start, end) {
    const foot = document.getElementById('sptTableFoot');
    const info = document.getElementById('sptPageInfo');
    const controls = document.getElementById('sptPageControls');
    const prevBtn = document.getElementById('sptPrevBtn');
    const nextBtn = document.getElementById('sptNextBtn');
    if (!foot) return;

    if (total === 0) { foot.style.display = 'none'; return; }
    foot.style.display = '';
    info.innerHTML = `Showing <strong>${start + 1}–${Math.min(end, total)}</strong> of <strong>${total}</strong> ticket(s)`;

    if (totalPages <= 1) { if (controls) controls.style.display = 'none'; return; }
    if (controls) controls.style.display = 'flex';
    if (prevBtn) prevBtn.disabled = sptCurrentPage <= 1;
    if (nextBtn) nextBtn.disabled = sptCurrentPage >= totalPages;

    const wrap = document.getElementById('sptPageNumbers');
    if (!wrap) return;
    wrap.innerHTML = '';
    const cur = sptCurrentPage;
    const nums = [...new Set([1, totalPages, cur, cur - 1, cur + 1].filter(n => n >= 1 && n <= totalPages))].sort((a, b) => a - b);
    nums.forEach((n, i) => {
        if (i > 0 && n > nums[i - 1] + 1) {
            const el = document.createElement('span');
            el.className = 'txn-pg-ellipsis'; el.textContent = '…';
            wrap.appendChild(el);
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'txn-pg-num' + (n === cur ? ' active' : '');
        btn.textContent = n;
        btn.onclick = () => { sptCurrentPage = n; paginateSpt(); };
        wrap.appendChild(btn);
    });
}

window.sptChangePage = function (dir) { sptCurrentPage += dir; paginateSpt(); };

/* ── Status dropdown (filter bar) ── */
window.toggleSptStatus = function () {
    const menu = document.getElementById('sptStatusMenu');
    const chevron = document.getElementById('sptStatusChevron');
    const wrap = document.getElementById('sptStatusWrap');
    const isOpen = menu.style.display !== 'none';
    menu.style.display = isOpen ? 'none' : 'block';
    chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
    wrap.classList.toggle('open', !isOpen);
};

window.selectSptStatus = function (btn) {
    document.getElementById('sptStatusVal').value = btn.dataset.value;
    document.getElementById('sptStatusLabel').textContent = btn.textContent.trim();
    document.querySelectorAll('#sptStatusMenu .inv-status-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('sptStatusMenu').style.display = 'none';
    document.getElementById('sptStatusChevron').style.transform = '';
    document.getElementById('sptStatusWrap').classList.remove('open');
    applySptFilters();
};

document.addEventListener('click', function (e) {
    const wrap = document.getElementById('sptStatusWrap');
    if (wrap && !wrap.contains(e.target)) {
        const menu = document.getElementById('sptStatusMenu');
        const chevron = document.getElementById('sptStatusChevron');
        if (menu) menu.style.display = 'none';
        if (chevron) chevron.style.transform = '';
        wrap.classList.remove('open');
    }
});

document.getElementById('sptSearch')?.addEventListener('input', applySptFilters);

applySptFilters();