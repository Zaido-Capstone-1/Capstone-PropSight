let activeUserId = null;
let activeUserName = '';
let lastMsgTs = null;
let pollTimer = null;

function escHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function closeMsgPane() {
    const layout = document.querySelector('.msg-layout');
    if (layout) layout.classList.remove('pane-open');
    // Reset active user so the same thread can be reopened
    activeUserId = null;
    stopPoll();
    // Restore empty state
    const pane = document.getElementById('msgPane');
    if (pane) {
        pane.innerHTML = `<div id="msgPaneEmpty" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:#94a3b8;padding:40px;">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" width="48" height="48" style="color:#cbd5e1;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <div style="font-size:15px;font-weight:600;color:#64748b;">Select a conversation</div>
            <div style="font-size:13px;text-align:center;max-width:220px;">Click on a message thread to view and reply</div>
        </div>`;
    }
    document.querySelectorAll('.msg-thread').forEach(t => t.classList.remove('active'));
}

function loadConversation(userId, name, email) {
    userId = parseInt(userId);
    if (activeUserId === userId) return;
    activeUserId = userId;
    activeUserName = name;

    // Mobile: slide to pane view
    const layout = document.querySelector('.msg-layout');
    if (layout) layout.classList.add('pane-open');

    document.querySelectorAll('.msg-thread').forEach(t => t.classList.remove('active'));
    const thread = document.querySelector(`.msg-thread[data-user-id="${userId}"]`);
    if (thread) {
        thread.classList.add('active');
        thread.classList.remove('has-unread');
        const badge = thread.querySelector('.msg-unread');
        if (badge) badge.remove();
    }

    const pane = document.getElementById('msgPane');
    pane.innerHTML = `
        <div class="msg-pane-header">
            <button class="msg-back-btn" onclick="closeMsgPane()" aria-label="Back to conversations">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Back
            </button>
            <div class="avatar">${name[0].toUpperCase()}</div>
            <div><div class="msg-pane-title">${escHtml(name)}</div>
            <div class="msg-pane-sub">${escHtml(email || '')}</div></div>
        </div>
        <div class="msg-pane-body" id="msgBody">
            <div style="padding:30px;text-align:center;color:#94a3b8;font-size:13px;">Loading…</div>
        </div>
        <div class="msg-compose">
            <label title="Attach file" style="cursor:pointer;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;flex-shrink:0;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18" height="18"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                <input type="file" id="msgFileInput" accept="image/*,.pdf,.doc,.docx,.txt" style="display:none;" onchange="handleAdminAttach(this)">
            </label>
            <div style="flex:1;display:flex;flex-direction:column;gap:4px;">
                <div id="adminAttachPreview" style="display:none;padding:4px 8px;background:#f1f5f9;border-radius:6px;font-size:12px;color:#475569;align-items:center;gap:6px;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13" height="13"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span id="adminAttachName" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                    <button onclick="clearAdminAttach()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;line-height:1;padding:0;">×</button>
                </div>
                <input type="text" id="msgInput" placeholder="Type a message…"
                       onkeydown="if(event.key==='Enter')sendMsg()" style="width:100%;box-sizing:border-box;"/>
            </div>
            <button class="btn btn-primary" style="padding:10px 16px;flex-shrink:0;" onclick="sendMsg()">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </div>`;

    fetch(`${ADMIN_API}?action=conversation&user_id=${userId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { document.getElementById('msgBody').innerHTML = `<p style="padding:20px;color:red;">${escHtml(data.message)}</p>`; return; }
            const msgs = data.messages || [];
            renderMsgs(msgs, true);
            lastMsgTs = msgs.length ? msgs[msgs.length - 1].created_at : new Date().toISOString().slice(0, 19).replace('T', ' ');
            startPoll();
        })
        .catch(() => { const b = document.getElementById('msgBody'); if (b) b.innerHTML = '<p style="padding:20px;color:red;">Network error.</p>'; });
}

function renderMsgs(msgs, clearFirst) {
    const body = document.getElementById('msgBody');
    if (!body) return;
    if (clearFirst) { body.innerHTML = ''; body.dataset.lastDate = ''; }
    if (clearFirst && !msgs.length) {
        body.innerHTML = '<p style="padding:20px;text-align:center;color:#aaa;font-size:13px;">No messages yet. Say hello!</p>';
        return;
    }
    let lastDate = body.dataset.lastDate || '';
    msgs.forEach(m => {
        const mine = parseInt(m.from_user) === ADMIN_ID;
        // MySQL returns "YYYY-MM-DD HH:MM:SS" — replace space with T so all browsers parse it correctly
        const d = new Date((m.created_at || '').replace(' ', 'T'));
        const dateStr = isNaN(d) ? '' : d.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' });
        const timeStr = isNaN(d) ? '' : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        if (dateStr !== lastDate) {
            const div = document.createElement('div');
            div.style.cssText = 'text-align:center;font-size:11px;color:#94a3b8;font-weight:600;letter-spacing:.5px;text-transform:uppercase;margin:8px 0;';
            div.textContent = dateStr;
            body.appendChild(div);
            lastDate = dateStr;
        }
        const bub = document.createElement('div');
        bub.className = `msg-bubble ${mine ? 'me' : 'them'}`;
        bub.innerHTML = `<div class="bubble">${escHtml(m.body)}</div>${m.attachment_url ? renderAdminAttachment(m.attachment_url, m.message_id) : ''}<div class="btime">${timeStr}</div>`;
        body.appendChild(bub);
    });
    body.dataset.lastDate = lastDate;
    body.scrollTop = body.scrollHeight;
}

function renderAdminAttachment(url, messageId) {
    const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(url);
    const proxyUrl = `../../api/view_message_attachment.php?message_id=${messageId}`;
    if (isImage) {
        return `<img src="${proxyUrl}" style="max-width:220px;max-height:200px;border-radius:8px;margin-top:6px;display:block;cursor:pointer;" onclick="openImageModal('${proxyUrl}')" title="Click to enlarge">`;
    }
    const fname = url.split('/').pop();
    return `<a href="${proxyUrl}" download style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;padding:6px 10px;background:rgba(0,0,0,.06);border-radius:8px;font-size:12px;color:inherit;text-decoration:none;">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        ${escHtml(fname)}</a>`;
}

function openImageModal(src) {
    let modal = document.getElementById('ps-img-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'ps-img-modal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.85);backdrop-filter:blur(4px);cursor:zoom-out;';
        modal.innerHTML = `
            <button onclick="event.stopPropagation();document.getElementById('ps-img-modal').style.display='none'"
                style="position:absolute;top:18px;right:22px;background:rgba(255,255,255,0.15);border:none;color:#fff;font-size:24px;width:40px;height:40px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2;">&times;</button>
            <div id="ps-img-modal-wrap" style="position:relative;display:inline-flex;cursor:default;" onclick="event.stopPropagation()">
                <img id="ps-img-modal-img" src="" style="max-width:90vw;max-height:88vh;border-radius:10px;box-shadow:0 8px 40px rgba(0,0,0,0.6);object-fit:contain;display:block;">
                <div id="ps-img-modal-overlay" style="position:absolute;inset:0;border-radius:10px;background:linear-gradient(to top,rgba(0,0,0,0.55) 0%,transparent 45%);opacity:0;transition:opacity .22s ease;display:flex;align-items:flex-end;justify-content:flex-end;padding:14px;">
                    <button onclick="event.stopPropagation();psDownloadImage()"
                        style="display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,0.2);color:#fff;padding:9px 18px;border-radius:9px;border:1.5px solid rgba(255,255,255,0.35);font-size:13px;font-weight:600;cursor:pointer;backdrop-filter:blur(6px);transition:background .18s;"
                        onmouseenter="this.style.background='rgba(255,255,255,0.32)'"
                        onmouseleave="this.style.background='rgba(255,255,255,0.2)'">
                        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download
                    </button>
                </div>
            </div>`;

        const wrap = modal.querySelector('#ps-img-modal-wrap');
        const overlay = modal.querySelector('#ps-img-modal-overlay');
        const isTouchDevice = () => window.matchMedia('(hover: none) and (pointer: coarse)').matches;

        if (isTouchDevice()) {
            // Always show overlay on touch devices
            overlay.style.opacity = '1';
        } else {
            // Hover show/hide on desktop
            wrap.addEventListener('mouseenter', () => overlay.style.opacity = '1');
            wrap.addEventListener('mouseleave', () => overlay.style.opacity = '0');
        }

        modal.addEventListener('click', () => { modal.style.display = 'none'; });
        document.body.appendChild(modal);
    }
    document.getElementById('ps-img-modal-img').src = src;
    modal.dataset.src = src;
    modal.style.display = 'flex';
}

function psDownloadImage() {
    const src = document.getElementById('ps-img-modal').dataset.src;
    if (!src) return;
    const a = document.createElement('a');
    a.href = src;
    a.download = src.split('/').pop() || 'image';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function handleAdminAttach(input) {
    const file = input.files[0];
    const preview = document.getElementById('adminAttachPreview');
    const nameEl = document.getElementById('adminAttachName');
    if (file) { nameEl.textContent = file.name; preview.style.display = 'flex'; }
}

function clearAdminAttach() {
    document.getElementById('msgFileInput').value = '';
    document.getElementById('adminAttachPreview').style.display = 'none';
    document.getElementById('adminAttachName').textContent = '';
}

function sendMsg() {
    const input = document.getElementById('msgInput');
    const fileInput = document.getElementById('msgFileInput');
    const bodyTxt = input ? input.value.trim() : '';
    const file = fileInput && fileInput.files[0];
    if ((!bodyTxt && !file) || !activeUserId) return;
    if (input) input.value = '';

    // Optimistic bubble
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const msgBody = document.getElementById('msgBody');
    const ph = msgBody?.querySelector('p[style*="text-align:center"]');
    if (ph) ph.remove();
    const bub = document.createElement('div');
    bub.className = 'msg-bubble me';
    bub.style.opacity = '.65';
    let previewHtml = bodyTxt ? `<div class="bubble">${escHtml(bodyTxt)}</div>` : '';
    if (file) {
        if (file.type.startsWith('image/')) {
            const tmpUrl = URL.createObjectURL(file);
            previewHtml += `<img src="${tmpUrl}" style="max-width:220px;max-height:200px;border-radius:8px;margin-top:6px;display:block;opacity:.7;">`;
        } else {
            previewHtml += `<div style="font-size:12px;margin-top:6px;opacity:.7;">📎 ${escHtml(file.name)}</div>`;
        }
    }
    previewHtml += `<div class="btime">${timeStr}</div>`;
    bub.innerHTML = previewHtml;
    if (msgBody) { msgBody.appendChild(bub); msgBody.scrollTop = msgBody.scrollHeight; }

    const fd = new FormData();
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('action', 'send');
    fd.append('to_user', activeUserId);
    fd.append('body', bodyTxt);
    if (file) fd.append('attachment', file);

    fetch(ADMIN_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            bub.style.opacity = '';
            if (d.success) {
                lastMsgTs = d.ts || lastMsgTs;
                updateThreadPreview(activeUserId, bodyTxt || '📎 Attachment');
                clearAdminAttach();
            } else {
                bub.remove();
                showToast(d.message || 'Failed to send.', 'error');
            }
        })
        .catch(() => { bub.remove(); showToast('Network error.', 'error'); });
}

function startPoll() {
    stopPoll();
    pollTimer = setInterval(() => {
        if (!activeUserId || !lastMsgTs || document.hidden) return;
        fetch(`${ADMIN_API}?action=poll&user_id=${activeUserId}&since=${encodeURIComponent(lastMsgTs)}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const incoming = (data.messages || []).filter(m => parseInt(m.from_user) !== ADMIN_ID);
                if (incoming.length) {
                    renderMsgs(incoming, false);
                    updateThreadPreview(activeUserId, incoming[incoming.length - 1].body);
                }
                lastMsgTs = data.ts || lastMsgTs;
            }).catch(() => { });
    }, 4000);
}
function stopPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

function updateThreadPreview(userId, body) {
    const item = document.querySelector(`.msg-thread[data-user-id="${userId}"]`);
    if (item) {
        const preview = item.querySelector('.thread-preview');
        if (preview) preview.textContent = body.slice(0, 50);
        const time = item.querySelector('.msg-thread-time');
        if (time) time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const list = document.querySelector('.msg-threads');
        if (list) list.prepend(item);
    }
}

function openNewMessage() {
    const users = window.__PS_ADMIN_MSG_USERS__ || [];
    const opts = users.map(u => `<option value="${u.user_id}">${u.first_name} ${u.last_name} (${u.email})</option>`).join('');
    let modal = document.getElementById('ps-msg-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'ps-msg-modal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);backdrop-filter:blur(3px);';
        document.body.appendChild(modal);
    }
    modal.innerHTML = `
        <div style="background:#fff;border-radius:14px;padding:28px 24px;width:100%;max-width:440px;box-shadow:0 24px 48px rgba(0,0,0,0.18);">
            <h3 style="margin:0 0 18px;font-size:1.1rem;font-weight:700;color:#1e293b;">New Message</h3>
            <select id="nm_to" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:10px;font-size:0.9rem;">${opts}</select>
            <input id="nm_subj" placeholder="Subject (optional)" style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:10px;font-size:0.9rem;">
            <textarea id="nm_body" placeholder="Message…" style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;height:110px;font-size:0.9rem;resize:vertical;"></textarea>
            <p id="nm_err" style="color:#dc2626;font-size:0.82rem;margin:6px 0 0;display:none;"></p>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button onclick="document.getElementById('ps-msg-modal').style.display='none'" style="flex:1;padding:10px;border:1px solid #e2e8f0;background:#fff;border-radius:8px;cursor:pointer;color:#64748b;">Cancel</button>
                <button id="nm_send" style="flex:2;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Send</button>
            </div>
        </div>`;
    modal.style.display = 'flex';

    document.getElementById('nm_send').onclick = () => {
        const to = document.getElementById('nm_to').value;
        const subj = document.getElementById('nm_subj').value;
        const body = document.getElementById('nm_body').value;
        const errEl = document.getElementById('nm_err');
        if (!body.trim()) { errEl.textContent = 'Message is required.'; errEl.style.display = 'block'; return; }
        errEl.style.display = 'none';
        document.getElementById('nm_send').textContent = 'Sending…';
        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('to_user', to);
        fd.append('subject', subj);
        fd.append('body', body);
        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
        fetch(ADMIN_API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                modal.style.display = 'none';
                if (data.success) {
                    showToast('Message sent!', 'success');
                    const sel = document.getElementById('nm_to');
                    const uname = sel.options[sel.selectedIndex]?.text.split(' (')[0] || 'User';
                    const uid = parseInt(to);
                    const existing = document.querySelector(`.msg-thread[data-user-id="${uid}"]`);
                    if (!existing) {
                        const el = document.createElement('div');
                        el.className = 'msg-thread';
                        el.dataset.userId = uid;
                        el.setAttribute('onclick', `loadConversation(${uid},'${uname.replace(/'/g, "\\'")}','')`);
                        el.innerHTML = `<div class="avatar">${uname[0].toUpperCase()}</div>
                            <div class="msg-thread-info">
                                <div class="msg-thread-name">${escHtml(uname)}</div>
                                <div class="msg-thread-preview thread-preview">${escHtml(body.slice(0, 50))}</div>
                            </div>
                            <div class="msg-thread-meta"><div class="msg-thread-time">now</div></div>`;
                        document.querySelector('.msg-threads')?.prepend(el);
                    }
                    loadConversation(uid, uname, '');
                } else {
                    showToast(data.message || 'Failed to send.', 'error');
                    document.getElementById('nm_send').textContent = 'Send';
                    modal.style.display = 'flex';
                }
            })
            .catch(() => { showToast('Network error.', 'error'); modal.style.display = 'flex'; });
    };
}

// No auto-load: user must click a thread to open it

// Realtime: incoming messages from realtime.js polling
window.addEventListener('ps:new_messages', e => {
    (e.detail || []).forEach(m => {
        const fromId = parseInt(m.from_user);
        const thread = document.querySelector(`.msg-thread[data-user-id="${fromId}"]`);
        if (thread) {
            const preview = thread.querySelector('.thread-preview');
            if (preview) preview.textContent = (m.body || '').slice(0, 50);
            const time = thread.querySelector('.msg-thread-time');
            if (time) time.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            // Only show badge if this conversation is NOT currently open
            if (fromId !== activeUserId) {
                thread.classList.add('has-unread');
                const badge = thread.querySelector('.msg-unread');
                if (badge) badge.textContent = (parseInt(badge.textContent) || 0) + 1;
                else {
                    const meta = thread.querySelector('.msg-thread-meta');
                    if (meta) { const b = document.createElement('div'); b.className = 'msg-unread'; b.textContent = '1'; meta.appendChild(b); }
                }
            }
        }
    });
});

document.addEventListener('visibilitychange', () => {
    if (!activeUserId) return;
    document.hidden ? stopPoll() : startPoll();
});
