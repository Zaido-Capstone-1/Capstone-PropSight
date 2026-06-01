let activeAdminId = null;
let activeAdminName = '';
let pollTimer = null;
let lastMsgTs = null; // timestamp of newest message loaded
let isMobile = window.innerWidth <= 680;
let seenCheckTimer = null;

// ── Thread search ────────────────────────────────────────────
function filterThreads(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.msg-thread-item').forEach(el => {
        el.style.display = (!q || el.dataset.search.includes(q)) ? '' : 'none';
    });
}

// ── Open conversation ────────────────────────────────────────
function openConversation(adminId, adminName, adminPhoto) {
    adminId = parseInt(adminId);
    if (activeAdminId === adminId) return;

    activeAdminId = adminId;
    activeAdminName = adminName;
    // Save active conversation to restore on reload
    sessionStorage.setItem('ps_active_admin', JSON.stringify({ adminId, adminName, adminPhoto }));
    // Fire mark-as-read immediately so badge clears on next page load
    fetch(`${window.__PS_USER_MSG__.apiUrl}?action=mark_read&admin_id=${adminId}`).catch(() => {});

    // Highlight thread
    document.querySelectorAll('.msg-thread-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.adminId) === adminId);
        if (parseInt(el.dataset.adminId) === adminId) {
            el.classList.remove('has-unread');
            const badge = el.querySelector('.ti-badge');
            if (badge) badge.remove();
        }
    });

    // Mobile: hide sidebar, show chat
    if (isMobile) {
        document.getElementById('msgSidebar').classList.add('hidden');
        document.getElementById('msgChat').classList.add('active');
    }

    const avatarHtml = adminPhoto
        ? `<img src="${escHtml(adminPhoto)}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.insertAdjacentText('afterend','${adminName[0].toUpperCase()}')">`
        : adminName[0].toUpperCase();

    // Render chat shell
    const chat = document.getElementById('msgChat');
    chat.innerHTML = `
            <div class="msg-chat-header">
                <button class="msg-back-btn" onclick="goBack()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>Back</button>
                <div class="chat-avatar">${avatarHtml}</div>
                <div class="chat-header-info">
                    <div class="chat-header-name">${escHtml(adminName)}</div>
                    <div class="chat-header-sub"><span class="chat-online-dot"></span>Property Team</div>
                </div>
            </div>
            <div class="msg-chat-body" id="chatBody">
                <div style="text-align:center;padding:30px;color:#94a3b8;font-size:13px;">Loading…</div>
            </div>
            <div class="msg-compose">
                <label class="msg-attach-btn" title="Attach file" style="cursor:pointer;display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;border:1.5px solid var(--border,#e2e8f0);background:var(--white,#fff);flex-shrink:0;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="18" height="18"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    <input type="file" id="attachInput" accept="image/*,.pdf,.doc,.docx,.txt" style="display:none;" onchange="handleAttachPreview(this)">
                </label>
                <div style="flex:1;display:flex;flex-direction:column;gap:4px;">
                    <div id="attachPreview" style="display:none;padding:4px 8px;background:#f1f5f9;border-radius:6px;font-size:12px;color:#475569;align-items:center;gap:6px;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="13" height="13"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span id="attachName" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        <button onclick="clearAttach()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;line-height:1;padding:0;">×</button>
                    </div>
                    <textarea class="msg-compose-input" id="composeInput" rows="1"
                        placeholder="Type a message…"
                        oninput="autoResize(this)"
                        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg();}"></textarea>
                </div>
                <button class="msg-send-btn" id="sendBtn" onclick="sendMsg()" title="Send">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                </button>
            </div>`;

    loadMessages();
    startPolling();
}

// ── Load full conversation ───────────────────────────────────
function loadMessages() {
    fetch(`${window.__PS_USER_MSG__.apiUrl}?action=conversation&admin_id=${activeAdminId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                renderChatError(data.message);
                return;
            }
            const msgs = data.messages || [];
            renderMessages(msgs, true);
            lastMsgTs = msgs.length ? msgs[msgs.length - 1].created_at : new Date().toISOString().slice(0, 19).replace('T', ' ');
            // Clear unread badge for this thread now that messages are marked read in DB
            const t = document.querySelector(`.msg-thread-item[data-admin-id="${activeAdminId}"]`);
            if (t) {
                t.classList.remove('has-unread');
                t.querySelectorAll('.ti-badge').forEach(b => b.remove());
            }
            // Check seen status of last sent message
            checkSeen();
        })
        .catch(() => renderChatError('Failed to load messages.'));
}

// ── Render messages into chat body ──────────────────────────
function renderMessages(msgs, clearFirst = false) {
    const body = document.getElementById('chatBody');
    if (!body) return;

    if (clearFirst) body.innerHTML = '';

    if (clearFirst && msgs.length === 0) {
        body.innerHTML = '<div style="text-align:center;padding:40px 20px;color:#94a3b8;font-size:13px;">No messages yet — say hello! 👋</div>';
        return;
    }

    let lastDate = clearFirst ? null : body.dataset.lastDate;

    msgs.forEach(m => {
        const mine = parseInt(m.from_user) === window.__PS_USER_MSG__.userId;
        const d = new Date((m.created_at || '').replace(' ', 'T') + 'Z');
        const dateStr = d.toLocaleDateString('en-PH', {
            weekday: 'long',
            month: 'long',
            day: 'numeric'
        });
        const timeStr = d.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });

        if (dateStr !== lastDate) {
            const div = document.createElement('div');
            div.className = 'msg-date-divider';
            div.textContent = dateStr;
            body.appendChild(div);
            lastDate = dateStr;
        }

        const bubble = document.createElement('div');
        bubble.className = `msg-bubble ${mine ? 'me' : 'them'}`;
        bubble.dataset.msgId = m.message_id;
        const bodyText = m.body && m.body !== '📎 Attachment' ? `<div class="bubble-text">${escHtml(m.body)}</div>` : '';
        bubble.innerHTML = `
            ${bodyText}
            ${m.attachment_url ? renderAttachment(m.attachment_url, m.message_id) : ''}
            <div class="bubble-time">${timeStr}</div>`;
        body.appendChild(bubble);
    });

    // Show seen indicator only on the last sent bubble
    updateSeenIndicator();

    body.dataset.lastDate = lastDate;
    body.scrollTop = body.scrollHeight;
}
function renderAttachment(url, messageId) {
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

function handleAttachPreview(input) {
    const file = input.files[0];
    const preview = document.getElementById('attachPreview');
    const nameEl = document.getElementById('attachName');
    if (file) {
        nameEl.textContent = file.name;
        preview.style.display = 'flex';
    }
}

function clearAttach() {
    document.getElementById('attachInput').value = '';
    document.getElementById('attachPreview').style.display = 'none';
    document.getElementById('attachName').textContent = '';
}

// ── Send message ─────────────────────────────────────────────
function sendMsg() {
    const input = document.getElementById('composeInput');
    const btn = document.getElementById('sendBtn');
    const fileInput = document.getElementById('attachInput');
    if (!input || !btn) return;
    const body = input.value.trim();
    const file = fileInput && fileInput.files[0];
    if (!body && !file) return;
    if (!activeAdminId) return;

    // Optimistic bubble
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
    const chatBody = document.getElementById('chatBody');
    const tempId = 'tmp_' + Date.now();

    const placeholder = chatBody.querySelector('div[style*="text-align:center"]');
    if (placeholder) placeholder.remove();

    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble me sending';
    bubble.id = tempId;
    let previewHtml = body ? `<div class="bubble-text">${escHtml(body)}</div>` : '';
    if (file) {
        if (file.type.startsWith('image/')) {
            const tmpUrl = URL.createObjectURL(file);
            previewHtml += `<img src="${tmpUrl}" style="max-width:220px;max-height:200px;border-radius:8px;margin-top:6px;display:block;opacity:.7;">`;
        } else {
            previewHtml += `<div style="font-size:12px;margin-top:6px;opacity:.7;">📎 ${escHtml(file.name)}</div>`;
        }
    }
    previewHtml += `<div class="bubble-time">${timeStr}</div>`;
    bubble.innerHTML = previewHtml;
    chatBody.appendChild(bubble);
    chatBody.scrollTop = chatBody.scrollHeight;

    input.value = '';
    input.style.height = '';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('to_admin', activeAdminId);
    fd.append('body', body);
    if (file) fd.append('attachment', file);
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    fetch(window.__PS_USER_MSG__.apiUrl, {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            const tmp = document.getElementById(tempId);
            if (data.success) {
                if (tmp) {
                    tmp.classList.remove('sending');
                    if (data.message_id) tmp.dataset.msgId = data.message_id;
                }
                lastMsgTs = data.ts || lastMsgTs;
                updateThreadPreview(activeAdminId, body || '📎 Attachment');
                clearAttach();
                updateSeenIndicator();
            } else {
                if (tmp) tmp.remove();
                showToast(data.message || 'Failed to send.', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            const tmp = document.getElementById(tempId);
            if (tmp) tmp.remove();
            showToast('Network error.', 'error');
        });
}

// ── Real-time polling for new messages ──────────────────────
function startPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    pollTimer = setInterval(pollNew, 2000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    stopSeenCheck();
}

// ── Seen / delivered indicator ───────────────────────────────
function updateSeenIndicator() {
    const chatBody = document.getElementById('chatBody');
    if (!chatBody) return;
    // Check if already marked as Seen before removing
    const existing = chatBody.querySelector('.seen-indicator');
    const alreadySeen = existing && existing.classList.contains('seen');
    chatBody.querySelectorAll('.seen-indicator').forEach(el => el.remove());
    // Only show if the very last bubble in chat is mine
    const allBubbles = [...chatBody.querySelectorAll('.msg-bubble')];
    if (!allBubbles.length) return;
    const lastBubble = allBubbles[allBubbles.length - 1];
    if (!lastBubble.classList.contains('me')) return;
    const seenEl = document.createElement('div');
    seenEl.className = alreadySeen ? 'seen-indicator seen' : 'seen-indicator';
    seenEl.textContent = alreadySeen ? 'Seen' : 'Delivered';
    lastBubble.appendChild(seenEl);
}

function markLastBubbleSeen() {
    const chatBody = document.getElementById('chatBody');
    if (!chatBody) return;
    const seenEl = chatBody.querySelector('.seen-indicator');
    if (seenEl) {
        seenEl.textContent = 'Seen';
        seenEl.classList.add('seen');
    }
}

function startSeenCheck() {
    stopSeenCheck();
    seenCheckTimer = setInterval(checkSeen, 2000);
}

function stopSeenCheck() {
    if (seenCheckTimer) { clearInterval(seenCheckTimer); seenCheckTimer = null; }
}

function checkSeen() {
    if (!activeAdminId) return;
    fetch(`${window.__PS_USER_MSG__.apiUrl}?action=check_seen&admin_id=${activeAdminId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            // Always re-evaluate: if last bubble is mine and it's been read, show Seen
            updateSeenIndicator();
            if (data.is_read) {
                markLastBubbleSeen();
            }
        })
        .catch(() => {});
}

let isPolling = false;
function pollNew() {
    if (!activeAdminId || !lastMsgTs || document.hidden || isPolling) return;
    isPolling = true;
    const since = encodeURIComponent(lastMsgTs);
    fetch(`${window.__PS_USER_MSG__.apiUrl}?action=poll&admin_id=${activeAdminId}&since=${since}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            // Always advance the timestamp regardless of whether messages arrived,
            // so we never re-fetch the same window on the next poll tick.
            if (data.ts) lastMsgTs = data.ts;
            if (data.messages && data.messages.length) {
                // Filter out own optimistic bubbles already rendered on send.
                // Dedup by message_id so a message the server confirmed won't
                // appear twice alongside its optimistic bubble.
                const incoming = data.messages.filter(m => {
                    if (m.message_id && document.querySelector(`.msg-bubble[data-msg-id="${m.message_id}"]`)) return false;
                    return true;
                });
                if (incoming.length) {
                    renderMessages(incoming, false);
                    updateThreadPreview(activeAdminId, incoming[incoming.length - 1].body);
                }
            }
            // Check seen status on every poll tick
            checkSeen();
        })
        .catch(() => { }) // Silent - don't disrupt UX on poll fail
        .finally(() => { isPolling = false; });
}

// ── Update thread list preview ───────────────────────────────
function updateThreadPreview(adminId, body) {
    const item = document.querySelector(`.msg-thread-item[data-admin-id="${adminId}"]`);
    if (item) {
        const preview = item.querySelector('.ti-preview');
        if (preview) preview.textContent = body.slice(0, 48);
        const time = item.querySelector('.ti-time');
        if (time) time.textContent = new Date().toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });
        // Move to top
        const list = document.getElementById('threadList');
        list.prepend(item);
    }
}

// ── New message modal ────────────────────────────────────────
function openNewMsg() {
    document.getElementById('nm_err').style.display = 'none';
    document.getElementById('nm_body').value = '';
    document.getElementById('nm_subject').value = '';
    document.getElementById('nm_sendBtn').textContent = 'Send Message';
    document.getElementById('nm_sendBtn').disabled = false;
    document.getElementById('newMsgModal').classList.add('open');
    setTimeout(() => document.getElementById('nm_body').focus(), 200);
}

function closeNewMsg() {
    document.getElementById('newMsgModal').classList.remove('open');
}

function sendNewMsg() {
    const adminId = document.getElementById('nm_admin').value;
    const subject = document.getElementById('nm_subject').value.trim();
    const body = document.getElementById('nm_body').value.trim();
    const err = document.getElementById('nm_err');
    const btn = document.getElementById('nm_sendBtn');
    if (!body) {
        err.textContent = 'Message cannot be empty.';
        err.style.display = 'block';
        return;
    }
    err.style.display = 'none';
    btn.textContent = 'Sending…';
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('to_admin', adminId);
    fd.append('subject', subject);
    fd.append('body', body);
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    fetch(window.__PS_USER_MSG__.apiUrl, {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(data => {
            closeNewMsg();
            if (data.success) {
                showToast('Message sent!', 'success');
                // Find admin name
                const sel = document.getElementById('nm_admin');
                const adminName = sel.options[sel.selectedIndex]?.text || 'Admin';
                const aid = parseInt(adminId);

                // Add thread to list if not already there
                const existing = document.querySelector(`.msg-thread-item[data-admin-id="${aid}"]`);
                if (!existing) {
                    const initial = adminName[0].toUpperCase();
                    const now = new Date().toLocaleTimeString([], {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    const el = document.createElement('div');
                    el.className = 'msg-thread-item active';
                    el.dataset.adminId = aid;
                    el.dataset.adminName = adminName;
                    el.dataset.search = adminName.toLowerCase();
                    el.setAttribute('onclick', `openConversation(${aid},'${adminName.replace(/'/g, "\\'")}')`);
                    el.innerHTML = `
                            <div class="ti-avatar">${initial}</div>
                            <div class="ti-body">
                                <div class="ti-name">${escHtml(adminName)}</div>
                                <div class="ti-preview">${escHtml(body.slice(0, 48))}</div>
                            </div>
                            <div class="ti-meta"><div class="ti-time">${now}</div></div>`;
                    const list = document.getElementById('threadList');
                    const empty = list.querySelector('.msg-empty-threads');
                    if (empty) empty.remove();
                    list.prepend(el);
                }
                openConversation(aid, adminName);
            } else {
                showToast(data.message || 'Failed to send.', 'error');
                btn.textContent = 'Send Message';
                btn.disabled = false;
                document.getElementById('newMsgModal').classList.add('open');
            }
        })
        .catch(() => {
            btn.textContent = 'Send Message';
            btn.disabled = false;
            document.getElementById('newMsgModal').classList.add('open');
            showToast('Network error.', 'error');
        });
}

// ── Mobile back button ───────────────────────────────────────
function goBack() {
    stopPolling();
    activeAdminId = null;
    sessionStorage.removeItem('ps_active_admin');
    document.getElementById('msgSidebar').classList.remove('hidden');
    document.getElementById('msgChat').classList.remove('active');
    document.getElementById('msgChat').innerHTML = `
            <div class="msg-chat-placeholder" id="chatPlaceholder">
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                </svg>
                <span>Select a conversation</span>
            </div>`;
    document.querySelectorAll('.msg-thread-item').forEach(el => el.classList.remove('active'));
}

// ── Helpers ──────────────────────────────────────────────────
function autoResize(el) {
    el.style.height = '';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function escHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function renderChatError(msg) {
    const body = document.getElementById('chatBody');
    if (body) body.innerHTML = `<div style="padding:20px;color:#ef4444;font-size:13px;">${escHtml(msg)}</div>`;
}

// ── Respond to incoming realtime events from realtime.js ─────
window.addEventListener('ps:new_messages', e => {
    (e.detail || []).forEach(m => {
        // realtime.php aliases from_user as sender_id in the new_messages payload
        const senderId = m.sender_id || m.from_user;
        if (!senderId) return;
        // Skip messages sent by this user (shouldn't appear here, but guard anyway)
        if (parseInt(senderId) === window.__PS_USER_MSG__.userId) return;

        // If this admin's conversation is currently open, inject the message
        // directly into the chat body instead of waiting for the next poll tick.
        if (parseInt(senderId) === activeAdminId) {
            const msgId = m.id || m.message_id;
            const alreadyRendered = msgId && document.querySelector(`.msg-bubble[data-msg-id="${msgId}"]`);
            if (!alreadyRendered) {
                // Normalise field names: realtime payload uses `id`, poll uses `message_id`
                const normalised = Object.assign({}, m, {
                    message_id: msgId,
                    from_user: senderId,
                });
                renderMessages([normalised], false);
                if (m.ts) lastMsgTs = m.ts;
            }
        }

        // Highlight the thread item in the sidebar (whether or not chat is open)
        const tid = document.querySelector(`.msg-thread-item[data-admin-id="${senderId}"]`);
        if (tid) {
            // Only add has-unread badge when this admin's chat is NOT currently active
            if (parseInt(senderId) !== activeAdminId) {
                tid.classList.add('has-unread');
                const badge = tid.querySelector('.ti-badge');
                if (!badge) {
                    const b = document.createElement('span');
                    b.className = 'ti-badge';
                    b.textContent = '•';
                    tid.querySelector('.ti-meta')?.prepend(b);
                }
            }
            const p = tid.querySelector('.ti-preview');
            if (p) p.textContent = (m.body || '').slice(0, 48);
            // Bubble thread to top
            const list = document.getElementById('threadList');
            if (list) list.prepend(tid);
        }
    });
});

// Stop polling when page hidden, resume when visible
document.addEventListener('visibilitychange', () => {
    if (!activeAdminId) return;
    document.hidden ? stopPolling() : startPolling();
});

// Auto-open first thread on desktop
document.addEventListener('DOMContentLoaded', () => {
    // Restore last open conversation on reload
    const saved = sessionStorage.getItem('ps_active_admin');
    if (saved) {
        try {
            const { adminId, adminName, adminPhoto } = JSON.parse(saved);
            // Wait for thread list to be ready then open
            setTimeout(() => openConversation(adminId, adminName, adminPhoto), 100);
        } catch(e) { sessionStorage.removeItem('ps_active_admin'); }
    }
});

window.addEventListener('ps:unread_messages', e => {
    // Could update nav badge if needed
});
/* ── Thread-list badge poller ───────────────────────────────
   Polls ?action=threads every 3 s to keep unread badges and
   previews current for ALL threads in real time.
──────────────────────────────────────────────────────────── */
let threadPollTimer = null;

function startThreadPoll() {
    if (threadPollTimer) return;
    threadPollTimer = setInterval(refreshThreadBadges, 2000);
}

function stopThreadPoll() {
    if (threadPollTimer) { clearInterval(threadPollTimer); threadPollTimer = null; }
}

function refreshThreadBadges() {
    if (document.hidden) return;
    const api = window.__PS_USER_MSG__.apiUrl;
    fetch(`${api}?action=threads`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.threads) return;
            data.threads.forEach(t => {
                const aid = parseInt(t.admin_id);
                const thread = document.querySelector(`.msg-thread-item[data-admin-id="${aid}"]`);
                if (!thread) return;

                const unread = parseInt(t.unread) || 0;
                const isActive = aid === activeAdminId;

                // Update preview
                const preview = thread.querySelector('.ti-preview');
                if (preview && t.last_body) preview.textContent = (t.last_body || '').slice(0, 48);

                // Update timestamp
                const time = thread.querySelector('.ti-time');
                if (time && t.last_time) {
                    const d = new Date((t.last_time || '').replace(' ', 'T') + 'Z');
                    time.textContent = isNaN(d) ? '' :
                        (Date.now() - d < 86400000)
                            ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                            : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
                }

                // Skip badge update if this thread is currently open
                if (isActive) return;

                const meta = thread.querySelector('.ti-meta');
                let badge = thread.querySelector('.ti-badge');

                if (unread > 0) {
                    thread.classList.add('has-unread');
                    if (badge) {
                        badge.textContent = unread;
                    } else if (meta) {
                        const b = document.createElement('div');
                        b.className = 'ti-badge';
                        b.textContent = unread;
                        meta.appendChild(b);
                    }
                } else {
                    thread.classList.remove('has-unread');
                    if (badge) badge.remove();
                }
            });
        })
        .catch(() => {});
}

document.addEventListener('DOMContentLoaded', startThreadPoll);
document.addEventListener('visibilitychange', () => {
    document.hidden ? stopThreadPoll() : startThreadPoll();
});