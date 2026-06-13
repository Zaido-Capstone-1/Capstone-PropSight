function toggleFaq(id) {
    const item = document.getElementById(id);
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item').forEach(i => {
        i.classList.remove('open');
        const a = i.querySelector('.faq-a');
        if (a) a.style.maxHeight = '0';
    });
    if (!isOpen) {
        item.classList.add('open');
        const a = item.querySelector('.faq-a');
        a.style.maxHeight = a.scrollHeight + 'px';
    }
}

function selectTicketType(el) {
    document.querySelectorAll('.ticket-type').forEach(t => t.classList.remove('selected'));
    el.classList.add('selected');
}

function escHtml(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getBookingIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const raw = String(params.get('booking_id') || '').trim();
    if (!/^\d+$/.test(raw)) return null;
    const id = Number(raw);
    return Number.isFinite(id) && id > 0 ? id : null;
}

function applyBookingContextPrefill() {
    const bookingId = getBookingIdFromUrl();
    if (!bookingId) return;

    const subjectEl = document.getElementById('contact_subject');
    const msgEl = document.getElementById('contact_message');
    if (!subjectEl || !msgEl) return;

    const bookingRef = 'BK-' + String(bookingId).padStart(6, '0');
    if (!subjectEl.value.trim()) {
        subjectEl.value = `[${bookingRef}] Booking concern`;
    }
    if (!msgEl.value.trim()) {
        msgEl.value = `Hi Support Team,\n\nI need help regarding booking ${bookingRef}.\n\nConcern:\n`;
    }

    const bookingTopic = Array.from(document.querySelectorAll('.ticket-type'))
        .find(btn => btn.textContent.trim().toLowerCase() === 'booking inquiry');
    if (bookingTopic) selectTicketType(bookingTopic);
}

function wireTicketItemClick(item) {
    if (!item) return;
    item.style.cursor = 'pointer';
    item.addEventListener('click', () => {
        const ticketId = Number(item.dataset.ticketId || 0);
        if (!ticketId) return;
        loadAndOpenTicket(ticketId);
    });
}

function prependTicketToList(subject, ticketRef) {
    const list = document.getElementById('myTicketsList');
    if (!list) return;
    const currentPage = Number(list.dataset.currentPage || 1);
    if (currentPage !== 1) return;

    const hint = document.getElementById('myTicketsHint');
    const empty = document.getElementById('myTicketsEmpty');
    if (hint) hint.style.display = '';
    if (empty) empty.style.display = 'none';

    const newTicket = document.createElement('div');
    newTicket.dataset.ticketId = '';
    newTicket.className = 'ticket-item';
    newTicket.style.cssText = 'animation:fadeIn 0.35s ease both;';
    newTicket.innerHTML = `
        <div>
            <div class="ticket-subject">${escHtml(subject)}</div>
            <div class="ticket-meta">Submitted Just now · <span class="ticket-num">${escHtml(ticketRef)}</span></div>
        </div>
        <span class="badge badge-gold" style="margin-left:auto;">Open</span>`;

    list.prepend(newTicket);
    wireTicketItemClick(newTicket);

    const items = list.querySelectorAll('.ticket-item');
    items.forEach((it, idx) => {
        if (idx >= 5) it.remove();
    });
}

function submitTicket() {
    const name = document.getElementById('contact_name').value.trim();
    const email = document.getElementById('contact_email').value.trim();
    const subject = document.getElementById('contact_subject').value.trim();
    const message = document.getElementById('contact_message').value.trim();
    const errEl = document.getElementById('contactError');
    errEl.style.display = 'none';

    if (!name) { errEl.textContent = 'Full name is required.'; errEl.style.display = 'block'; return; }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { errEl.textContent = 'Please enter a valid email address.'; errEl.style.display = 'block'; return; }
    if (!subject) { errEl.textContent = 'Subject is required.'; errEl.style.display = 'block'; return; }
    if (message.length < 10) { errEl.textContent = 'Please write a more detailed message (at least 10 characters).'; errEl.style.display = 'block'; return; }

    const selectedTopic = document.querySelector('.ticket-type.selected')?.textContent || 'General';
    const btn = document.getElementById('sendMsgBtn');
    const startedAt = Date.now();
    const minLoadingMs = 1000;
    btn.disabled = true;
    btn.textContent = 'Sending...';
    if (typeof showToast === 'function') {
        showToast('Sending your ticket...', 'info');
    }

    const waitForMinLoading = () => {
        const elapsed = Date.now() - startedAt;
        const remaining = Math.max(0, minLoadingMs - elapsed);
        return new Promise(resolve => setTimeout(resolve, remaining));
    };

    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('category', selectedTopic);
    fd.append('subject', subject);
    fd.append('body', message);
    fd.append('priority', 'medium');
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    fetch('../../api/user/support.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(async (data) => {
            await waitForMinLoading();
            if (!data.success) {
                errEl.textContent = data.message || 'Could not send your message right now.';
                errEl.style.display = 'block';
                return;
            }

            const ticketRef = data.ticket_ref ? '#' + data.ticket_ref : ('#TKT-' + String(data.ticket_id || '').padStart(5, '0'));
            const formCard = document.getElementById('contactFormCard');
            formCard.dataset.defaultName = name;
            formCard.dataset.defaultEmail = email;
            prependTicketToList(subject, ticketRef);
            const topTicket = document.querySelector('#myTicketsList .ticket-item:first-child');
            if (topTicket) {
                topTicket.dataset.ticketId = String(data.ticket_id || '');
            }
            formCard.innerHTML = `
                <div style="text-align:center;padding:32px 20px;">
                    <div style="width:64px;height:64px;border-radius:50%;background:rgba(34,197,94,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
                        <svg viewBox="0 0 24 24" style="width:30px;height:30px;stroke:#16a34a;fill:none;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:var(--text-dark);margin-bottom:8px;">Message Sent!</div>
                    <p style="font-size:0.85rem;color:var(--text-soft);line-height:1.7;margin-bottom:6px;">
                        Your message has been received. Our team will reply to <strong>${escHtml(email)}</strong> within 2 hours.
                    </p>
                    <p style="font-size:0.78rem;color:var(--text-soft);margin-bottom:24px;">
                        Ticket ID: <strong style="color:var(--blue-500);">${escHtml(ticketRef)}</strong> · Topic: <strong>${escHtml(selectedTopic)}</strong>
                    </p>
                    <button class="btn-secondary" onclick="resetContactForm()">Send Another Message</button>
                </div>`;

            if (typeof showToast === 'function') {
                showToast('Message sent! Ticket ' + ticketRef + ' created.', 'success');
            }
        })
        .catch(async () => {
            await waitForMinLoading();
            errEl.textContent = 'Network error. Please try again.';
            errEl.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Send Message';
        });
}

function resetContactForm() {
    const card = document.getElementById('contactFormCard');
    const defaultName = card.dataset.defaultName || '';
    const defaultEmail = card.dataset.defaultEmail || '';
    card.innerHTML = `
        <div class="card-title">
            <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:var(--blue-500);fill:none;stroke-width:2;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            Send Us a Message
        </div>
        <p style="font-size:0.84rem;color:var(--text-soft);margin-bottom:16px;">Tell us what you need and we'll get back to you within 2 hours.</p>
        <div style="margin-bottom:14px;">
            <div style="font-size:0.72rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-mid);margin-bottom:8px;">Topic</div>
            <div class="ticket-types">
                ${['Booking Inquiry', 'Payment Issue', 'Room Request', 'Feedback', 'Other'].map((t, i) => `<button class="ticket-type${i === 0 ? ' selected' : ''}" onclick="selectTicketType(this)">${t}</button>`).join('')}
            </div>
        </div>
        <div class="form-grid" style="margin-bottom:14px;">
            <div class="form-field"><label>Full Name</label><input type="text" id="contact_name" value="${escHtml(defaultName)}"></div>
            <div class="form-field"><label>Email</label><input type="email" id="contact_email" value="${escHtml(defaultEmail)}"></div>
        </div>
        <div class="form-grid cols-1" style="margin-bottom:14px;">
            <div class="form-field"><label>Subject</label><input type="text" id="contact_subject" placeholder="Brief description of your concern"></div>
        </div>
        <div class="form-field" style="margin-bottom:18px;">
            <label>Message</label>
            <textarea id="contact_message" placeholder="Describe your concern in detail. Include your booking ID if applicable."></textarea>
        </div>
        <div id="contactError" style="display:none;color:#ef4444;font-size:0.78rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:9px 12px;margin-bottom:12px;"></div>
        <button class="btn-primary" id="sendMsgBtn" onclick="submitTicket()">
            <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Send Message
        </button>`;
    applyBookingContextPrefill();
}

function closeTicketModal() {
    const modal = document.getElementById('ticketViewModal');
    if (!modal) return;
    modal.classList.remove('open');
    document.body.style.overflow = '';
}

function openTicketModal() {
    const modal = document.getElementById('ticketViewModal');
    if (!modal) return;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function loadAndOpenTicket(ticketId) {
    if (!ticketId) return;

    fetch(`../../api/user/support.php?action=messages&ticket_id=${encodeURIComponent(String(ticketId))}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                if (typeof showToast === 'function') showToast(data.message || 'Could not load ticket.', 'error');
                return;
            }

            const ticket = data.ticket || {};
            const messages = Array.isArray(data.messages) ? data.messages : [];
            const ticketRef = '#TKT-' + String(ticket.ticket_id || ticketId).padStart(5, '0');
            const status = String(ticket.status || 'open').replace(/_/g, ' ');
            const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);

            const titleEl = document.getElementById('ticketModalTitle');
            const subEl = document.getElementById('ticketModalSub');
            const bodyEl = document.getElementById('ticketModalBody');

            if (titleEl) titleEl.textContent = ticket.subject || 'Ticket Details';
            if (subEl) {
                subEl.innerHTML = `
                    <span class="ticket-chip ref">${escHtml(ticketRef)}</span>
                    <span class="ticket-chip status">${escHtml(statusLabel)}</span>
                    <span class="ticket-chip category">${escHtml(ticket.category || 'General')}</span>
                `;
            }
            if (bodyEl) {
                bodyEl.innerHTML = messages.length
                    ? messages.map(msg => `
                        <div class="ticket-msg ${Number(msg.is_admin) ? 'is-admin' : 'is-user'}">
                            <div class="ticket-msg-meta">
                                <span class="ticket-msg-role">${Number(msg.is_admin) ? 'Support' : 'You'}</span>
                                <span>${escHtml(msg.sender_name || (Number(msg.is_admin) ? 'Support Team' : 'You'))}</span>
                                <span>·</span>
                                <span>${psFmtDateTime(msg.created_at)}</span>
                            </div>
                            <div class="ticket-msg-body">${escHtml(msg.body || '')}</div>
                        </div>
                    `).join('')
                    : '<div class="ticket-msg is-admin"><div class="ticket-msg-meta"><span class="ticket-msg-role">Support</span></div><div class="ticket-msg-body">No messages yet.</div></div>';
            }
            openTicketModal();
        })
        .catch(() => {
            if (typeof showToast === 'function') showToast('Network error while loading ticket.', 'error');
        });
}

document.querySelectorAll('.contact-cta').forEach(btn => {
    const text = btn.textContent.trim();
    if (text.startsWith('Call')) {
        btn.addEventListener('click', () => { window.location.href = 'tel:+63331234567'; });
    } else if (text.startsWith('Send')) {
        btn.addEventListener('click', () => {
            document.getElementById('contactFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    } else if (text.startsWith('Start')) {
        btn.addEventListener('click', () => {
            btn.disabled = true; btn.textContent = 'Connecting…';
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = 'Start Chat <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;display:inline;"><polyline points="9 18 15 12 9 6"/></svg>';
                showToast('Live chat is currently unavailable. Please send us an email.');
            }, 1200);
        });
    }
});

document.querySelectorAll('.ticket-item').forEach(wireTicketItemClick);
applyBookingContextPrefill();

/* ── Auto-open modal + prefill when coming from Manage Stay ── */
(function autoOpenTicketModal() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('open_ticket') !== '1') return;

    const bookingId = params.get('booking_id');
    const unit      = params.get('unit') || '';
    const property  = params.get('property') || '';

    function tryOpen() {
        const modal = document.getElementById('newTicketModal');
        if (!modal) { setTimeout(tryOpen, 100); return; }

        openNewTicketModal();

        // Remove open_ticket params from URL so reload won't reopen the modal
        const cleanParams = new URLSearchParams(window.location.search);
        cleanParams.delete('open_ticket');
        cleanParams.delete('booking_id');
        cleanParams.delete('unit');
        cleanParams.delete('property');
        const cleanUrl = window.location.pathname + (cleanParams.toString() ? '?' + cleanParams.toString() : '');
        history.replaceState(null, '', cleanUrl);

        // Prefill subject
        const subjectEl = document.getElementById('modal_contact_subject');
        if (subjectEl && !subjectEl.value.trim()) {
            const ref = bookingId ? 'BK-' + String(bookingId).padStart(6, '0') : '';
            subjectEl.value = ref ? `[${ref}] Booking concern` : 'Booking concern';
        }

        // Prefill message
        const msgEl = document.getElementById('modal_contact_message');
        if (msgEl && !msgEl.value.trim()) {
            const ref  = bookingId ? 'BK-' + String(bookingId).padStart(6, '0') : '';
            const room = unit ? `Room/Unit: ${unit}` : '';
            const prop = property ? `Property: ${property}` : '';
            const lines = ['Hi Support Team,', ''];
            if (ref)  lines.push(`Booking Reference: ${ref}`);
            if (room) lines.push(room);
            if (prop) lines.push(prop);
            lines.push('', 'Concern:', '');
            msgEl.value = lines.join('\n');
        }

        // Pre-select "Booking Inquiry" topic
        const allChips = Array.from(document.querySelectorAll('#modalTicketTypes .ticket-type'));
        if (allChips.length) {
            allChips.forEach(t => t.classList.remove('selected'));
            const target = allChips.find(b => b.textContent.trim().toLowerCase() === 'booking inquiry');
            (target || allChips[0]).classList.add('selected');
        }

        // Focus message so user can type their concern immediately
        if (msgEl) {
            msgEl.focus();
            msgEl.setSelectionRange(msgEl.value.length, msgEl.value.length);
        }
    }

    tryOpen();
})();

document.getElementById('ticketViewModal')?.addEventListener('click', (e) => {
    if (e.target.id === 'ticketViewModal') closeTicketModal();
});

// ── Real-time: new admin reply to open ticket ─────────────────────────────
window.addEventListener('ps:new_messages', function (e) {
    const msgs = Array.isArray(e.detail) ? e.detail : [];
    if (!msgs.length) return;

    msgs.forEach(msg => {
        if (!msg.ticket_id) return;
        const ticketId = String(msg.ticket_id);
        const modalBody = document.getElementById('ticketModalBody');
        const isModalOpen = document.getElementById('ticketViewModal')?.classList.contains('open');

        if (isModalOpen && modalBody) {
            const currentTicketId = document.getElementById('ticketViewModal')?.dataset.ticketId;
            if (!currentTicketId || currentTicketId === ticketId) {
                const msgEl = document.createElement('div');
                msgEl.className = 'ticket-msg is-admin';
                msgEl.innerHTML = `
                    <div class="ticket-msg-meta">
                        <span class="ticket-msg-role">Support</span>
                        <span>${escHtml(msg.sender_name || 'Support Team')}</span>
                        <span>·</span>
                        <span>${msg.created_at ? psFmtDateTime(msg.created_at) : 'Just now'}</span>
                    </div>
                    <div class="ticket-msg-body">${escHtml(msg.body || msg.message || '')}</div>`;
                msgEl.style.background = '#eff6ff';
                modalBody.appendChild(msgEl);
                msgEl.scrollIntoView({ behavior: 'smooth', block: 'end' });
                setTimeout(() => { msgEl.style.transition = 'background 1s'; msgEl.style.background = ''; }, 200);
            }
        }

        const listItem = document.querySelector(`.ticket-item[data-ticket-id="${ticketId}"]`);
        if (listItem) {
            let badge = listItem.querySelector('.ticket-unread-dot');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'ticket-unread-dot';
                badge.style.cssText = 'display:inline-block;width:8px;height:8px;background:#3b82f6;border-radius:50%;margin-left:6px;vertical-align:middle;';
                const subject = listItem.querySelector('.ticket-subject, .ticket-item-title');
                if (subject) subject.appendChild(badge);
            }
            listItem.style.transition = 'background 0.3s';
            listItem.style.background = '#eff6ff';
            setTimeout(() => { listItem.style.background = ''; }, 2000);
        }

        if (typeof showToast === 'function') {
            const preview = (msg.body || msg.message || '').slice(0, 60);
            showToast(`Support replied: "${preview}${preview.length >= 60 ? '…' : ''}"`, 'info', 'Support Reply', 5000);
        }
    });
});

function selectMaintenanceType(btn) {
    btn.closest('.ticket-types').querySelectorAll('.ticket-type').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
}
function submitMaintenance() {
    const subject = document.getElementById('maint_subject')?.value.trim();
    const message = document.getElementById('maint_message')?.value.trim();
    const priority = document.getElementById('maint_priority')?.value;
    const errBox = document.getElementById('maintenanceError');
    const issueType = document.querySelector('#maintenanceFormCard .ticket-type.selected')?.textContent?.trim() ?? 'Other';

    errBox.style.display = 'none';

    if (!subject) { errBox.textContent = 'Issue summary is required.'; errBox.style.display = 'block'; return; }
    if (!message || message.length < 10) { errBox.textContent = 'Please write a more detailed description (at least 10 characters).'; errBox.style.display = 'block'; return; }

    const btn = document.getElementById('sendMaintBtn');
    const startedAt = Date.now();
    const minLoadingMs = 1000;
    btn.disabled = true;
    btn.textContent = 'Submitting...';
    if (typeof showToast === 'function') showToast('Submitting your maintenance request...', 'info');

    const waitForMinLoading = () => {
        const elapsed = Date.now() - startedAt;
        const remaining = Math.max(0, minLoadingMs - elapsed);
        return new Promise(resolve => setTimeout(resolve, remaining));
    };

    // Pass client's local date so server stores the correct date regardless of UTC offset
    const clientDate = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD in local tz

    fetch('../../api/submit_maintenance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ issue_type: issueType, subject, message, priority, client_date: clientDate })
    })
        .then(r => r.json())
        .then(async (data) => {
            await waitForMinLoading();
            if (!data.success) {
                errBox.textContent = data.message || 'Failed to submit. Please try again.';
                errBox.style.display = 'block';
                return;
            }

            const formCard = document.getElementById('maintenanceFormCard');
            formCard.innerHTML = `
            <div style="text-align:center;padding:32px 20px;">
                <div style="width:64px;height:64px;border-radius:50%;background:rgba(34,197,94,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;">
                    <svg viewBox="0 0 24 24" style="width:30px;height:30px;stroke:#16a34a;fill:none;stroke-width:2.5;"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <div style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:var(--text-dark);margin-bottom:8px;">Request Submitted!</div>
                <p style="font-size:0.85rem;color:var(--text-soft);line-height:1.7;margin-bottom:6px;">
                    Your maintenance request has been received. Our team will attend to it promptly.
                </p>
                <p style="font-size:0.78rem;color:var(--text-soft);margin-bottom:24px;">
                    Issue: <strong>${escHtml(issueType)}</strong> · Priority: <strong>${escHtml(priority)}</strong>
                </p>
                <button class="btn-secondary" onclick="location.reload()">Submit Another Request</button>
            </div>`;

            if (typeof showToast === 'function') showToast('Maintenance request submitted! We\'ll get on it soon.', 'success');
        })
        .catch(async () => {
            await waitForMinLoading();
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg> Submit Request`;
        });
}

/* ═══════════════════════════════════════════════════
   PropSight User — Support modals
   New Ticket + New Maintenance Request
   ═══════════════════════════════════════════════════ */

/* ── Helpers ──────────────────────────────────────── */
function _openModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.add('open');
    document.body.style.overflow = 'hidden';
    // close on backdrop click
    m.onclick = (e) => { if (e.target === m) _closeModal(id); };
}

function _closeModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('open');
    document.body.style.overflow = '';
}

function _showModalError(elId, msg) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.textContent = msg;
    el.style.display = 'flex';
}

function _hideModalError(elId) {
    const el = document.getElementById(elId);
    if (el) el.style.display = 'none';
}

/* ── New Ticket Modal ─────────────────────────────── */
function openNewTicketModal() { _openModal('newTicketModal'); }
function closeNewTicketModal() { _closeModal('newTicketModal'); }

function selectModalTicketType(el) {
    el.closest('#modalTicketTypes')
      .querySelectorAll('.ticket-type')
      .forEach(t => t.classList.remove('selected'));
    el.classList.add('selected');
}

function submitModalTicket() {
    const name    = document.getElementById('modal_contact_name')?.value.trim();
    const email   = document.getElementById('modal_contact_email')?.value.trim();
    const subject = document.getElementById('modal_contact_subject')?.value.trim();
    const message = document.getElementById('modal_contact_message')?.value.trim();
    const type    = document.querySelector('#modalTicketTypes .ticket-type.selected')?.textContent?.trim() ?? 'Other';

    _hideModalError('modalTicketError');

    if (!subject) return _showModalError('modalTicketError', 'Please enter a subject.');
    if (!message) return _showModalError('modalTicketError', 'Please enter a message.');

    const btn = document.getElementById('modalSendMsgBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><path d="M12 2a10 10 0 0110 10"/></svg> Sending…';

    const fd = new FormData();
    fd.append('action', 'create');
    fd.append('category', type);
    fd.append('subject', subject);
    fd.append('body', message);
    fd.append('priority', 'medium');
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    const apiUrl = window.__PS_USER_SUPPORT_API__ ?? '../../api/user/support.php';

    fetch(apiUrl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeNewTicketModal();
                if (typeof showToast === 'function') showToast('Ticket submitted successfully!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                _showModalError('modalTicketError', data.message ?? 'Something went wrong. Please try again.');
            }
        })
        .catch(() => _showModalError('modalTicketError', 'Network error. Please check your connection.'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2" fill="none"/></svg> Send Message';
        });
}

/* ── New Maintenance Modal ────────────────────────── */
function openNewMaintenanceModal() { _openModal('newMaintenanceModal'); }
function closeNewMaintenanceModal() { _closeModal('newMaintenanceModal'); }

function selectModalMaintenanceType(el) {
    el.closest('#modalMaintenanceTypes')
      .querySelectorAll('.ticket-type')
      .forEach(t => t.classList.remove('selected'));
    el.classList.add('selected');
}

function submitModalMaintenance() {
    const name     = document.getElementById('modal_maint_name')?.value.trim();
    const room     = document.getElementById('modal_maint_room')?.value.trim();
    const subject  = document.getElementById('modal_maint_subject')?.value.trim();
    const priority = document.getElementById('modal_maint_priority')?.value;
    const message  = document.getElementById('modal_maint_message')?.value.trim();
    const issueType = document.querySelector('#modalMaintenanceTypes .ticket-type.selected')?.textContent?.trim() ?? 'Other';

    _hideModalError('modalMaintenanceError');

    if (!subject) return _showModalError('modalMaintenanceError', 'Please enter an issue summary.');
    if (!message) return _showModalError('modalMaintenanceError', 'Please describe the issue in detail.');

    const btn = document.getElementById('modalSendMaintBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><path d="M12 2a10 10 0 0110 10"/></svg> Submitting…';

    const fd = new FormData();
    fd.append('action', 'submit_maintenance');
    fd.append('name', name);
    fd.append('room', room);
    fd.append('subject', subject);
    fd.append('priority', priority);
    fd.append('message', message);
    fd.append('issue_type', issueType);
    // Pass client's local date so server stores the correct date regardless of UTC offset
    fd.append('client_date', new Date().toLocaleDateString('en-CA')); // YYYY-MM-DD in local tz
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    const apiUrl = window.__PS_USER_SUPPORT_API__ ?? '../../api/user/support.php';

    fetch(apiUrl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeNewMaintenanceModal();
                if (typeof showToast === 'function') showToast('Maintenance request submitted!', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                _showModalError('modalMaintenanceError', data.message ?? 'Something went wrong. Please try again.');
            }
        })
        .catch(() => _showModalError('modalMaintenanceError', 'Network error. Please check your connection.'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg> Submit Request';
        });
}