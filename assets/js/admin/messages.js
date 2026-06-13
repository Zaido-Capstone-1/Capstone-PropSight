let activeUserId = null;
let activeUserName = '';
let lastMsgTs = null;
let pollTimer = null;
let seenCheckTimer = null;

function escHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function closeMsgPane() {
    const layout = document.querySelector('.msg-layout');
    if (layout) layout.classList.remove('pane-open');
    // Reset active user so the same thread can be reopened
    activeUserId = null;
    sessionStorage.removeItem('ps_active_user');
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

function loadConversation(userId, name, email, photo) {
    userId = parseInt(userId);
    if (activeUserId === userId) return;
    activeUserId = userId;
    activeUserName = name;
    // Save active conversation to restore on reload
    sessionStorage.setItem('ps_active_user', JSON.stringify({
        userId,
        name,
        email,
        photo
    }));
    // Fire mark-as-read immediately so badge clears on next page load
    fetch(`${ADMIN_API}?action=mark_read&user_id=${userId}`).catch(() => {});

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

    const avatarHtml = photo ?
        `<img src="${escHtml(photo)}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.outerHTML='<div class=\'avatar\'>${escHtml(name[0].toUpperCase())}</div>'">` :
        name[0].toUpperCase();

    const pane = document.getElementById('msgPane');
    pane.innerHTML = `
        <div class="msg-pane-header">
            <button class="msg-back-btn" onclick="closeMsgPane()" aria-label="Back to conversations">
                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Back
            </button>
            <div class="avatar">${avatarHtml}</div>
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
            if (!data.success) {
                document.getElementById('msgBody').innerHTML = `<p style="padding:20px;color:red;">${escHtml(data.message)}</p>`;
                return;
            }
            const msgs = data.messages || [];
            renderMsgs(msgs, true);
            lastMsgTs = msgs.length ? msgs[msgs.length - 1].created_at : new Date().toISOString().slice(0, 19).replace('T', ' ');
            // Clear unread badge for this thread now that messages are marked read in DB
            const t = document.querySelector(`.msg-thread[data-user-id="${userId}"]`);
            if (t) {
                t.classList.remove('has-unread');
                t.querySelectorAll('.msg-unread').forEach(b => b.remove());
            }
            checkSeen();
            startPoll();
        })
        .catch(() => {
            const b = document.getElementById('msgBody');
            if (b) b.innerHTML = '<p style="padding:20px;color:red;">Network error.</p>';
        });
}

function renderMsgs(msgs, clearFirst) {
    const body = document.getElementById('msgBody');
    if (!body) return;
    if (clearFirst) {
        body.innerHTML = '';
        body.dataset.lastDate = '';
    }
    if (clearFirst && !msgs.length) {
        body.innerHTML = '<p style="padding:20px;text-align:center;color:#aaa;font-size:13px;">No messages yet. Say hello!</p>';
        return;
    }
    let lastDate = body.dataset.lastDate || '';
    msgs.forEach(m => {
        const mine = parseInt(m.from_user) === ADMIN_ID;
        // MySQL returns "YYYY-MM-DD HH:MM:SS" — replace space with T so all browsers parse it correctly
        const d = psDate(m.created_at);
        const dateStr = d ? d.toLocaleDateString('en-PH', {
            weekday: 'long',
            month: 'long',
            day: 'numeric'
        }) : '';
        const timeStr = d ? d.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        }) : '';
        if (dateStr !== lastDate) {
            const div = document.createElement('div');
            div.className = 'msg-date-label';
            div.textContent = dateStr;
            body.appendChild(div);
            lastDate = dateStr;
        }
        const bub = document.createElement('div');
        bub.className = `msg-bubble ${mine ? 'me' : 'them'}`;
        bub.dataset.msgId = m.message_id;
        bub.dataset.body = m.body || '';

        // Quote block for replied messages
        const quoteHtml = m.parent_body ?
            `<div class="bubble-reply-quote">↩ ${escHtml(m.parent_sender_name ? m.parent_sender_name.split(' ')[0] + ': ' : '')}${escHtml(String(m.parent_body).slice(0, 80))}${String(m.parent_body).length > 80 ? '…' : ''}</div>` :
            '';
        const bodyText = m.body && m.body !== '📎 Attachment' ? `<div class="bubble">${escHtml(m.body)}</div>` : '';

        bub.innerHTML = `
            ${quoteHtml}
            ${bodyText}
            ${m.attachment_url ? renderAdminAttachment(m.attachment_url, m.message_id) : ''}
            <div class="btime">${timeStr}</div>
            <div class="bubble-menu-btn" onclick="toggleBubbleMenu(this, ${m.message_id}, ${mine}, '${escHtml(m.body || '')}')">&#8942;</div>`;
        body.appendChild(bub);
    });
    // Show seen indicator only on last sent bubble
    updateSeenIndicator();
    body.dataset.lastDate = lastDate;
    body.scrollTop = body.scrollHeight;
}

// ── Bubble context menu (reply / unsent) ────────────────────
function toggleBubbleMenu(btn, msgId, isMine, bodyText) {
    document.querySelectorAll('.bubble-menu').forEach(m => m.remove());
    const existing = btn.querySelector('.bubble-menu');
    if (existing) return;

    const menu = document.createElement('div');
    menu.className = 'bubble-menu';
    menu.innerHTML = `
        <div class="bubble-menu-item" onclick="replyToMessage(${msgId}, '${bodyText.replace(/'/g, "\'")}')">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
            Reply
        </div>
        ${isMine ? `<div class="bubble-menu-item unsent" onclick="unsentMessage(${msgId})">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
            Unsend
        </div>` : ''}`;
    btn.appendChild(menu);

    setTimeout(() => {
        document.addEventListener('click', function closeMenu(e) {
            if (!menu.contains(e.target) && e.target !== btn) {
                menu.remove();
                document.removeEventListener('click', closeMenu);
            }
        });
    }, 0);
}

function replyToMessage(msgId, bodyText) {
    document.querySelectorAll('.bubble-menu').forEach(m => m.remove());
    const existing = document.getElementById('replyPreview');
    if (existing) existing.remove();

    const compose = document.querySelector('.msg-compose');
    const preview = document.createElement('div');
    preview.id = 'replyPreview';
    preview.dataset.replyTo = msgId;
    preview.style.cssText = 'padding:8px 12px;background:#f1f5f9;border-left:3px solid #3b82f6;border-radius:6px;font-size:12px;color:#475569;display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;';
    preview.innerHTML = `<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;">↩ ${escHtml(bodyText.slice(0, 60))}${bodyText.length > 60 ? '...' : ''}</span>
        <button onclick="cancelReply()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;padding:0 0 0 8px;">×</button>`;
    compose.insertBefore(preview, compose.firstChild);
    document.getElementById('msgInput').focus();
}

function cancelReply() {
    const preview = document.getElementById('replyPreview');
    if (preview) preview.remove();
}

function unsentMessage(msgId) {
    document.querySelectorAll('.bubble-menu').forEach(m => m.remove());
    if (!confirm('Unsend this message?')) return;
    const fd = new FormData();
    fd.append('action', 'unsend');
    fd.append('message_id', msgId);
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fetch(ADMIN_API, {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const bub = document.querySelector(`.msg-bubble[data-msg-id="${msgId}"]`);
                if (bub) bub.remove();
                updateSeenIndicator();
            } else {
                showToast(data.message || 'Failed to unsend.', 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'));
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

        modal.addEventListener('click', () => {
            modal.style.display = 'none';
        });
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
    if (file) {
        nameEl.textContent = file.name;
        preview.style.display = 'flex';
    }
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
    const timeStr = now.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
    const msgBody = document.getElementById('msgBody');
    const ph = msgBody?.querySelector('p[style*="text-align:center"]');
    if (ph) ph.remove();
    // Capture reply quote before cancelling
    const replyPreviewEl = document.getElementById('replyPreview');
    const replyQuoteHtml = replyPreviewEl ?
        `<div class="bubble-reply-quote">${replyPreviewEl.querySelector('span')?.textContent || ''}</div>` :
        '';

    const bub = document.createElement('div');
    bub.className = 'msg-bubble me';
    bub.style.opacity = '.65';
    let innerHtml = replyQuoteHtml;
    innerHtml += bodyTxt ? `<div class="bubble">${escHtml(bodyTxt)}</div>` : '';
    if (file) {
        if (file.type.startsWith('image/')) {
            const tmpUrl = URL.createObjectURL(file);
            innerHtml += `<img src="${tmpUrl}" style="max-width:220px;max-height:200px;border-radius:8px;margin-top:6px;display:block;opacity:.5;">`;
        } else {
            innerHtml += `<div style="font-size:12px;margin-top:6px;opacity:.5;">📎 ${escHtml(file.name)}</div>`;
        }
    }
    bub.innerHTML = `${innerHtml}<div class="btime">${timeStr}</div>`;
    if (msgBody) {
        msgBody.appendChild(bub);
        msgBody.scrollTop = msgBody.scrollHeight;
    }

    const fd = new FormData();
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fd.append('action', 'send');
    fd.append('to_user', activeUserId);
    fd.append('body', bodyTxt);
    if (file) fd.append('attachment', file);
    const replyPreview = document.getElementById('replyPreview');
    if (replyPreview) {
        fd.append('reply_to', replyPreview.dataset.replyTo);
        cancelReply();
    }

    fetch(ADMIN_API, {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(d => {
            bub.style.opacity = '';
            if (d.success) {
                if (d.message_id) {
                    bub.dataset.msgId = d.message_id;
                    // Replace blob URL with real proxy URL and remove opacity
                    const proxyUrl = `../../api/view_message_attachment.php?message_id=${d.message_id}`;
                    bub.querySelectorAll('img[src^="blob:"]').forEach(el => {
                        URL.revokeObjectURL(el.src);
                        el.src = proxyUrl;
                        el.style.opacity = '1';
                        el.onclick = () => openImageModal(proxyUrl);
                        el.title = 'Click to enlarge';
                        el.style.cursor = 'pointer';
                    });
                }
                bub.querySelectorAll('img').forEach(el => el.style.opacity = '1');
                bub.querySelectorAll('div[style]').forEach(el => el.style.opacity = '1');
                lastMsgTs = d.ts || lastMsgTs;
                updateThreadPreview(activeUserId, bodyTxt || '📎 Attachment');
                clearAdminAttach();
                updateSeenIndicator();
            } else {
                bub.remove();
                showToast(d.message || 'Failed to send.', 'error');
            }
        })
        .catch(() => {
            bub.remove();
            showToast('Network error.', 'error');
        });
}

let isPolling = false;

function startPoll() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    pollTimer = setInterval(() => {
        if (!activeUserId || !lastMsgTs || document.hidden || isPolling) return;
        isPolling = true;
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
                // Check seen status on every poll tick
                checkSeen();
            })
            .catch(() => {})
            .finally(() => {
                isPolling = false;
            });
    }, 2000);
}

function stopPoll() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    stopSeenCheck();
}

// ── Seen / delivered indicator ───────────────────────────────
function updateSeenIndicator() {
    const msgBody = document.getElementById('msgBody');
    if (!msgBody) return;
    // Check if already marked as Seen before removing
    const existing = msgBody.querySelector('.seen-indicator');
    const alreadySeen = existing && existing.classList.contains('seen');
    msgBody.querySelectorAll('.seen-indicator').forEach(el => el.remove());
    // Only show if the very last bubble in chat is mine
    const allBubbles = [...msgBody.querySelectorAll('.msg-bubble')];
    if (!allBubbles.length) return;
    const lastBubble = allBubbles[allBubbles.length - 1];
    if (!lastBubble.classList.contains('me')) return;
    const seenEl = document.createElement('div');
    seenEl.className = alreadySeen ? 'seen-indicator seen' : 'seen-indicator';
    seenEl.textContent = alreadySeen ? 'Seen' : 'Delivered';
    lastBubble.appendChild(seenEl);
}

function markLastBubbleSeen() {
    const msgBody = document.getElementById('msgBody');
    if (!msgBody) return;
    const seenEl = msgBody.querySelector('.seen-indicator');
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
    if (seenCheckTimer) {
        clearInterval(seenCheckTimer);
        seenCheckTimer = null;
    }
}

function checkSeen() {
    if (!activeUserId) return;
    fetch(`${ADMIN_API}?action=check_seen&user_id=${activeUserId}`)
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

function updateThreadPreview(userId, body) {
    const item = document.querySelector(`.msg-thread[data-user-id="${userId}"]`);
    if (item) {
        const preview = item.querySelector('.thread-preview');
        if (preview) preview.textContent = body.slice(0, 50);
        const time = item.querySelector('.msg-thread-time');
        if (time) time.textContent = new Date().toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });
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
        modal.className = 'msg-modal-overlay';
        modal.addEventListener('click', e => {
            if (e.target === modal) modal.classList.remove('open');
        });
        document.body.appendChild(modal);
    }
    modal.innerHTML = `
        <div class="msg-modal" onclick="event.stopPropagation()">
            <h3>New Message</h3>
            <label>Send to</label>
            <select id="nm_to">${opts}</select>
            <label>Subject <span style="color:#94a8bb;font-weight:400;">(optional)</span></label>
            <input id="nm_subj" type="text" placeholder="e.g. Maintenance request">
            <label>Message</label>
            <textarea id="nm_body" placeholder="Type your message here…"></textarea>
            <p id="nm_err" style="color:#c0694a;font-size:12px;margin:8px 0 0;display:none;"></p>
            <div class="msg-modal-actions">
                <button class="msg-modal-cancel" onclick="document.getElementById('ps-msg-modal').classList.remove('open')">Cancel</button>
                <button id="nm_send" class="msg-modal-send">Send Message</button>
            </div>
        </div>`;
    modal.classList.add('open');

    document.getElementById('nm_send').onclick = () => {
        const to = document.getElementById('nm_to').value;
        const subj = document.getElementById('nm_subj').value;
        const body = document.getElementById('nm_body').value;
        const errEl = document.getElementById('nm_err');
        if (!body.trim()) {
            errEl.textContent = 'Message is required.';
            errEl.style.display = 'block';
            return;
        }
        errEl.style.display = 'none';
        document.getElementById('nm_send').textContent = 'Sending…';
        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('to_user', to);
        fd.append('subject', subj);
        fd.append('body', body);
        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
        fetch(ADMIN_API, {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                modal.classList.remove('open');
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
            .catch(() => {
                showToast('Network error.', 'error');
                modal.style.display = 'flex';
            });
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
            if (time) time.textContent = new Date().toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
            // Only show badge if this conversation is NOT currently open
            if (fromId !== activeUserId) {
                thread.classList.add('has-unread');
                const badge = thread.querySelector('.msg-unread');
                if (badge) badge.textContent = (parseInt(badge.textContent) || 0) + 1;
                else {
                    const meta = thread.querySelector('.msg-thread-meta');
                    if (meta) {
                        const b = document.createElement('div');
                        b.className = 'msg-unread';
                        b.textContent = '1';
                        meta.appendChild(b);
                    }
                }
            }
        }
    });
});

document.addEventListener('visibilitychange', () => {
    if (!activeUserId) return;
    document.hidden ? stopPoll() : startPoll();
});
// On load: ensure no thread starts with active class (no conversation pre-selected)
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.msg-thread').forEach(t => t.classList.remove('active'));
    // Restore last open conversation on reload
    const saved = sessionStorage.getItem('ps_active_user');
    if (saved) {
        try {
            const {
                userId,
                name,
                email,
                photo
            } = JSON.parse(saved);
            setTimeout(() => loadConversation(userId, name, email, photo), 100);
        } catch (e) {
            sessionStorage.removeItem('ps_active_user');
        }
    }
});
/* ── Thread-list badge poller ───────────────────────────────
   Polls ?action=threads every 3 s to keep unread badges and
   previews up to date for ALL threads, not just the open one.
   This is separate from the conversation poll (startPoll) which
   only fetches new bubbles for the currently active thread.
──────────────────────────────────────────────────────────── */
let threadPollTimer = null;

function startThreadPoll() {
    if (threadPollTimer) return;
    threadPollTimer = setInterval(refreshThreadBadges, 2000);
}

function stopThreadPoll() {
    if (threadPollTimer) {
        clearInterval(threadPollTimer);
        threadPollTimer = null;
    }
}

function refreshThreadBadges() {
    if (document.hidden) return;
    fetch(`${ADMIN_API}?action=threads`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.threads) return;
            data.threads.forEach(t => {
                const uid = parseInt(t.other_id);
                const thread = document.querySelector(`.msg-thread[data-user-id="${uid}"]`);
                if (!thread) return;

                const unread = parseInt(t.unread) || 0;
                const isActive = uid === activeUserId;

                // Update preview text
                const preview = thread.querySelector('.thread-preview');
                if (preview && t.last_body) preview.textContent = (t.last_body || '').slice(0, 50);

                // Update timestamp
                const time = thread.querySelector('.msg-thread-time');
                if (time && t.last_time) {
                    const d = psDate(t.last_time);
                    time.textContent = isNaN(d) ? '' :
                        (Date.now() - d < 86400000) ?
                        d.toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) :
                        d.toLocaleDateString('en-PH', {
                            month: 'short',
                            day: 'numeric'
                        });
                }

                // Badge — skip if this thread is currently open (mark_read already fired)
                if (isActive) return;

                const meta = thread.querySelector('.msg-thread-meta');
                let badge = thread.querySelector('.msg-unread');

                if (unread > 0) {
                    thread.classList.add('has-unread');
                    if (badge) {
                        badge.textContent = unread;
                    } else if (meta) {
                        const b = document.createElement('div');
                        b.className = 'msg-unread';
                        b.textContent = unread;
                        meta.appendChild(b);
                    }
                } else {
                    thread.classList.remove('has-unread');
                    if (badge) badge.remove();
                }
            });
        })
        .catch(() => {}); // silent — never disrupt UX
}

// Start on load, pause when tab is hidden
document.addEventListener('DOMContentLoaded', startThreadPoll);
document.addEventListener('visibilitychange', () => {
    document.hidden ? stopThreadPoll() : startThreadPoll();
});