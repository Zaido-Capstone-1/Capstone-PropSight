/* ================================================================
   support_maintenance.js
   Place at: assets/js/admin/support_maintenance.js
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

    document.getElementById('ticketStatusSelect').value = data.status || 'open';
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
    const fd = new FormData();
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('action', 'update_status');
    fd.append('ticket_id', currentTicketId);
    fd.append('status', status);

    try {
        const res = await fetch('../../api/admin/support.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            const btn = document.querySelector(`button[onclick*="openTicketModal(${currentTicketId},"]`);
            const row = btn?.closest('tr');
            if (row) {
                const badge = row.querySelector('td:nth-child(6) .badge');
                if (badge) {
                    const map = {
                        open: 'badge-open',
                        in_progress: 'badge-progress',
                        resolved: 'badge-done',
                        closed: 'badge-done',
                    };
                    badge.className = 'badge ' + (map[status] || 'badge-pending');
                    badge.textContent = status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
                }
            }
            showToast('Status updated to: ' + status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()));
        } else {
            showToast(data.message || 'Failed to update status.', 'error');
        }
    } catch (e) {
        showToast('Network error.', 'error')
    }
}