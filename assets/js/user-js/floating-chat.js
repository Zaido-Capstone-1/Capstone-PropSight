/* ════════════════════════════════════════════════════════════
   Floating Chat Widget
   Chat icon beside the bell → dropdown of conversations → click
   a conversation to open a small popup window, bottom-right,
   stacking side by side (Messenger-style chat heads).

   Reuses the existing endpoints/user/messages.php endpoints:
     GET  ?action=threads
     GET  ?action=conversation&admin_id=X
     GET  ?action=poll&admin_id=X&since=...
     GET  ?action=mark_read&admin_id=X
     POST action=send
   ════════════════════════════════════════════════════════════ */
(function () {
    const API = '../../endpoints/user/messages.php';
    const MY_ID = parseInt(window.PS_USER_ID || (window.__PS_USER_MSG__ && window.__PS_USER_MSG__.userId) || 0, 10);

    // Open popups, keyed by admin_id → { el, adminId, adminName, adminPhoto, lastTs, pollTimer, minimized }
    const popups = new Map();
    let dropdownOpen = false;
    let _threadsCache = [];
    let _adminsCache = [];

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // The API (fmt_dt in includes/db.php) returns timestamps as either
    // "2026-06-25 10:30:00" (no zone) or "2026-06-25 10:30:00+00:00" /
    // "...Z" (zone already present). Swap the space for "T" and only
    // append "Z" when no zone/offset is already there.
    function toIso(s) {
        if (!s) return '';
        let str = String(s).trim();
        if (!str.includes('T')) str = str.replace(' ', 'T');
        if (!/[zZ]$|[+-]\d{2}:?\d{2}$/.test(str)) str += 'Z';
        return str;
    }

    function relTime(ts) {
        if (!ts) return '';
        const t = new Date(toIso(ts)).getTime();
        if (isNaN(t)) return '';
        const diff = Math.floor((Date.now() - t) / 1000);
        if (diff < 60) return 'now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        return Math.floor(diff / 86400) + 'd';
    }

    function psDate(s) {
        if (!s) return new Date(NaN);
        return new Date(toIso(s));
    }

    function resolvePhoto(p) {
        if (!p) return '';
        if (/^https?:\/\//.test(p) || p.startsWith('../')) return p;
        return '../../' + String(p).replace(/^\/+/, '');
    }

    // ── Persist which popups are open (and minimized state) across reloads ──
    const STORAGE_KEY = `ps_open_chats_${MY_ID || 'anon'}`;

    function savePopupState() {
        try {
            const list = [];
            popups.forEach(state => {
                list.push({
                    adminId: state.adminId,
                    adminName: state.adminName,
                    adminPhoto: state.adminPhoto || '',
                    minimized: !!state.minimized,
                });
            });
            localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        } catch (e) { /* storage unavailable (private mode, quota, etc.) — fail silently */ }
    }

    function loadPopupState() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            const list = JSON.parse(raw);
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function restorePopups() {
        const saved = loadPopupState();
        saved.forEach(entry => {
            if (!entry || !entry.adminId) return;
            openPopup(entry.adminId, entry.adminName, entry.adminPhoto, { minimized: entry.minimized, skipSave: true });
        });
        savePopupState();
    }

    // Matches the PHP convention used everywhere else (e.g. pages/user/*.php):
    // first letter of first name + first letter of last name, uppercased.
    function getInitials(fullName) {
        const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        const first = parts[0].charAt(0);
        const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        return (first + last).toUpperCase();
    }

    function csrfAppend(fd) {
        if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);
        return fd;
    }

    /* ── Build the chat icon + dropdown shell (once per page) ── */
    function init() {
        const chatBtn = document.getElementById('chatBellBtn');
        if (!chatBtn || chatBtn._fcWired) return;
        chatBtn._fcWired = true;

        buildDropdown(chatBtn);
        buildPopupRail();

        chatBtn.addEventListener('click', e => {
            e.stopPropagation();
            toggleDropdown(chatBtn);
        });
    }

    function buildPopupRail() {
        if (document.getElementById('fc-popup-rail')) return;
        const rail = document.createElement('div');
        rail.id = 'fc-popup-rail';
        document.body.appendChild(rail);
    }

    function buildDropdown(chatBtn) {
        const drop = document.createElement('div');
        drop.id = 'chatDropdown';
        drop.innerHTML = `
            <div class="fc-drop-header">
                <span class="fc-drop-title">Messages</span>
                <button class="fc-drop-new" id="fcNewBtn" type="button">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New
                </button>
            </div>
            <div class="fc-thread-search-wrap">
                <input type="text" id="fcThreadSearch" placeholder="Search conversations…">
            </div>
            <div id="fc-thread-list">
                <div class="fc-thread-empty" id="fcThreadLoading">Loading…</div>
            </div>
            <div class="fc-new-panel" id="fcNewPanel" style="display:none;">
                <label>Send to</label>
                <select id="fcNewAdmin"></select>
                <label>Message</label>
                <textarea id="fcNewBody" placeholder="Type your message here…"></textarea>
                <div class="fc-new-actions">
                    <button class="fc-new-cancel" id="fcNewCancel" type="button">Cancel</button>
                    <button class="fc-new-send" id="fcNewSend" type="button">Send</button>
                </div>
            </div>`;
        document.body.appendChild(drop);

        function place() {
            const rect = chatBtn.getBoundingClientRect();
            const isMobile = window.innerWidth <= 640;
            drop.style.top = (rect.bottom + 8) + 'px';
            if (isMobile) {
                drop.style.right = '8px';
                drop.style.left = 'auto';
            } else {
                drop.style.right = (window.innerWidth - rect.right) + 'px';
                drop.style.left = 'auto';
            }
        }

        drop._place = place;

        drop.querySelector('#fcThreadSearch').addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            drop.querySelectorAll('.fc-thread-item').forEach(el => {
                el.style.display = (!q || el.dataset.search.includes(q)) ? '' : 'none';
            });
        });

        drop.querySelector('#fcNewBtn').addEventListener('click', () => {
            drop.querySelector('#fcNewPanel').style.display = 'block';
            drop.querySelector('#fc-thread-list').style.display = 'none';
            drop.querySelector('.fc-thread-search-wrap').style.display = 'none';
        });
        drop.querySelector('#fcNewCancel').addEventListener('click', () => {
            drop.querySelector('#fcNewPanel').style.display = 'none';
            drop.querySelector('#fc-thread-list').style.display = '';
            drop.querySelector('.fc-thread-search-wrap').style.display = '';
        });
        drop.querySelector('#fcNewSend').addEventListener('click', () => sendNewThread(drop));

        drop.addEventListener('click', e => e.stopPropagation());
        window.addEventListener('resize', () => { if (dropdownOpen) place(); });
        document.addEventListener('click', () => closeDropdown(drop));
    }

    function toggleDropdown(chatBtn) {
        const drop = document.getElementById('chatDropdown');
        if (!drop) return;
        if (dropdownOpen) { closeDropdown(drop); return; }
        dropdownOpen = true;
        drop.style.display = 'block';
        drop._place();
        loadThreads(drop);
    }

    function closeDropdown(drop) {
        if (!drop) return;
        dropdownOpen = false;
        drop.style.display = 'none';
        const panel = drop.querySelector('#fcNewPanel');
        if (panel) panel.style.display = 'none';
        const list = drop.querySelector('#fc-thread-list');
        const search = drop.querySelector('.fc-thread-search-wrap');
        if (list) list.style.display = '';
        if (search) search.style.display = '';
    }

    function loadThreads(drop) {
        fetch(`${API}?action=threads`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                _threadsCache = data.threads || [];
                _adminsCache = data.admins || [];
                renderThreads(drop);
                populateAdminSelect(drop);
            })
            .catch(() => {
                const list = drop.querySelector('#fc-thread-list');
                if (list) list.innerHTML = '<div class="fc-thread-empty">Couldn\'t load conversations.</div>';
            });
    }

    function populateAdminSelect(drop) {
        const sel = drop.querySelector('#fcNewAdmin');
        if (!sel) return;
        sel.innerHTML = _adminsCache.map(a =>
            `<option value="${a.user_id}" data-photo="${esc(a.profile_photo ? resolvePhoto(a.profile_photo) : '')}">${esc(a.first_name + ' ' + a.last_name)}</option>`
        ).join('');
    }

    function renderThreads(drop) {
        const list = drop.querySelector('#fc-thread-list');
        if (!list) return;

        if (!_threadsCache.length) {
            list.innerHTML = `
                <div class="fc-thread-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                    </svg><br>No conversations yet.<br>Tap <strong>New</strong> to message the team.
                </div>`;
            return;
        }

        list.innerHTML = _threadsCache.map(t => {
            const initials = esc(getInitials(t.admin_name));
            const preview = esc((t.last_body || '').slice(0, 46));
            const unread = parseInt(t.unread) || 0;
            const photo = t.admin_photo ? resolvePhoto(t.admin_photo) : '';
            const timeStr = t.last_time ? relTime(t.last_time) : '';
            return `
            <div class="fc-thread-item${unread ? ' has-unread' : ''}"
                 data-admin-id="${t.admin_id}"
                 data-admin-name="${esc(t.admin_name)}"
                 data-admin-photo="${esc(photo)}"
                 data-search="${esc((t.admin_name || '').toLowerCase())}">
                <div class="fc-thread-avatar">
                    ${photo ? `<img src="${photo}" alt="" onerror="this.style.display='none'">` : initials}
                </div>
                <div class="fc-thread-body">
                    <div class="fc-thread-name">${esc(t.admin_name)}</div>
                    <div class="fc-thread-preview">${preview}</div>
                </div>
                <div class="fc-thread-meta">
                    <div class="fc-thread-time">${timeStr}</div>
                    ${unread ? `<div class="fc-thread-badge">${unread > 9 ? '9+' : unread}</div>` : ''}
                </div>
            </div>`;
        }).join('');

        list.querySelectorAll('.fc-thread-item').forEach(el => {
            el.addEventListener('click', () => {
                openPopup(
                    parseInt(el.dataset.adminId),
                    el.dataset.adminName,
                    el.dataset.adminPhoto
                );
                closeDropdown(drop);
            });
        });
    }

    function sendNewThread(drop) {
        const adminSel = drop.querySelector('#fcNewAdmin');
        const bodyEl = drop.querySelector('#fcNewBody');
        const sendBtn = drop.querySelector('#fcNewSend');
        const adminId = parseInt(adminSel.value);
        const body = bodyEl.value.trim();
        if (!adminId || !body) return;

        sendBtn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('to_admin', adminId);
        fd.append('body', body);
        csrfAppend(fd);

        fetch(API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                sendBtn.disabled = false;
                if (!data.success) {
                    if (window.showToast) window.showToast(data.message || 'Failed to send.', 'error');
                    return;
                }
                bodyEl.value = '';
                drop.querySelector('#fcNewPanel').style.display = 'none';
                drop.querySelector('#fc-thread-list').style.display = '';
                drop.querySelector('.fc-thread-search-wrap').style.display = '';
                const selectedOpt = adminSel.options[adminSel.selectedIndex];
                openPopup(adminId, selectedOpt.textContent, selectedOpt.dataset.photo || '');
            })
            .catch(() => { sendBtn.disabled = false; });
    }

    /* ── Popup windows ─────────────────────────────────────── */
    function openPopup(adminId, adminName, adminPhoto, opts) {
        opts = opts || {};
        if (!adminId) return;
        if (popups.has(adminId)) {
            const existing = popups.get(adminId);
            if (!opts.minimized) {
                existing.minimized = false;
                existing.el.classList.remove('minimized');
                const ta = existing.el.querySelector('.fc-popup-compose textarea');
                if (ta) ta.focus();
            }
            if (!opts.skipSave) savePopupState();
            return;
        }

        const rail = document.getElementById('fc-popup-rail');
        const el = document.createElement('div');
        el.className = 'fc-popup';
        const photo = adminPhoto ? resolvePhoto(adminPhoto) : '';
        const initials = esc(getInitials(adminName));

        el.innerHTML = `
            <div class="fc-popup-header">
                <div class="fc-popup-avatar">${photo ? `<img src="${photo}" onerror="this.style.display='none'">` : initials}</div>
                <div class="fc-popup-name">${esc(adminName)}</div>
                <div class="fc-popup-header-actions">
                    <button class="fc-min-btn" title="Minimize" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    <button class="fc-close-btn" title="Close" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>
            <div class="fc-popup-body"><div class="fc-popup-empty">Loading…</div></div>
            <div class="fc-popup-attach-preview" style="display:none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span class="fc-popup-attach-name"></span>
                <button class="fc-popup-attach-clear" type="button" title="Remove">×</button>
            </div>
            <div class="fc-popup-compose">
                <label class="fc-popup-attach-btn" title="Attach photo or file" tabindex="0">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    <input type="file" accept="image/*,.pdf,.doc,.docx,.txt" style="display:none;">
                </label>
                <textarea rows="1" placeholder="Type a message…"></textarea>
                <button class="fc-popup-send" title="Send" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </div>`;

        rail.appendChild(el);

        const state = { el, adminId, adminName, adminPhoto: photo, lastTs: null, pollTimer: null, minimized: !!opts.minimized, pendingFile: null };
        popups.set(adminId, state);
        if (state.minimized) el.classList.add('minimized');

        const header = el.querySelector('.fc-popup-header');
        const minBtn = el.querySelector('.fc-min-btn');
        const closeBtn = el.querySelector('.fc-close-btn');
        const textarea = el.querySelector('textarea');
        const sendBtn = el.querySelector('.fc-popup-send');
        const fileInput = el.querySelector('.fc-popup-attach-btn input[type="file"]');
        const attachPreview = el.querySelector('.fc-popup-attach-preview');
        const attachNameEl = el.querySelector('.fc-popup-attach-name');
        const attachClearBtn = el.querySelector('.fc-popup-attach-clear');

        header.addEventListener('click', e => {
            if (e.target.closest('.fc-popup-header-actions')) return;
            toggleMinimize(adminId);
        });
        minBtn.addEventListener('click', e => { e.stopPropagation(); toggleMinimize(adminId); });
        closeBtn.addEventListener('click', e => { e.stopPropagation(); closePopup(adminId); });

        textarea.addEventListener('input', () => {
            textarea.style.height = '';
            textarea.style.height = Math.min(textarea.scrollHeight, 70) + 'px';
        });
        textarea.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendPopupMsg(adminId);
            }
        });
        sendBtn.addEventListener('click', () => sendPopupMsg(adminId));

        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            state.pendingFile = file || null;
            if (file) {
                attachNameEl.textContent = file.name;
                attachPreview.style.display = 'flex';
            } else {
                attachPreview.style.display = 'none';
            }
        });
        attachClearBtn.addEventListener('click', () => {
            state.pendingFile = null;
            fileInput.value = '';
            attachPreview.style.display = 'none';
        });

        loadPopupConversation(adminId);
        startPopupPoll(adminId);

        // Mark read right away
        fetch(`${API}?action=mark_read&admin_id=${adminId}`).catch(() => {});
        decrementMessagesBadge(currentThreadUnread(adminId));

        if (!opts.skipSave) savePopupState();
    }

    function currentThreadUnread(adminId) {
        const t = _threadsCache.find(x => parseInt(x.admin_id) === adminId);
        return t ? (parseInt(t.unread) || 0) : 0;
    }

    function toggleMinimize(adminId) {
        const state = popups.get(adminId);
        if (!state) return;
        state.minimized = !state.minimized;
        state.el.classList.toggle('minimized', state.minimized);
        savePopupState();
    }

    function closePopup(adminId) {
        const state = popups.get(adminId);
        if (!state) return;
        if (state.pollTimer) clearInterval(state.pollTimer);
        state.el.remove();
        popups.delete(adminId);
        savePopupState();
    }

    function loadPopupConversation(adminId) {
        const state = popups.get(adminId);
        if (!state) return;
        fetch(`${API}?action=conversation&admin_id=${adminId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    renderPopupError(adminId, data.message || 'Failed to load.');
                    return;
                }
                const msgs = data.messages || [];
                renderPopupMessages(adminId, msgs, true);
                state.lastTs = msgs.length
                    ? msgs[msgs.length - 1].created_at
                    : new Date().toISOString().slice(0, 19).replace('T', ' ');

                // Fill in / correct the header avatar once we know the admin's real photo.
                if (data.admin && data.admin.profile_photo) {
                    updatePopupAvatar(adminId, resolvePhoto(data.admin.profile_photo));
                }
            })
            .catch(() => renderPopupError(adminId, 'Network error.'));
    }

    function updatePopupAvatar(adminId, photoUrl) {
        const state = popups.get(adminId);
        if (!state || state.adminPhoto === photoUrl) return;
        state.adminPhoto = photoUrl;
        const avatarEl = state.el.querySelector('.fc-popup-avatar');
        if (avatarEl) {
            avatarEl.innerHTML = `<img src="${photoUrl}" onerror="this.style.display='none'">`;
        }
        savePopupState();
    }

    function renderPopupError(adminId, msg) {
        const state = popups.get(adminId);
        if (!state) return;
        const body = state.el.querySelector('.fc-popup-body');
        if (body) body.innerHTML = `<div class="fc-popup-empty">${esc(msg)}</div>`;
    }

    function renderPopupMessages(adminId, msgs, clearFirst) {
        const state = popups.get(adminId);
        if (!state) return;
        const body = state.el.querySelector('.fc-popup-body');
        if (!body) return;

        if (clearFirst) body.innerHTML = '';
        if (clearFirst && !msgs.length) {
            body.innerHTML = '<div class="fc-popup-empty">No messages yet — say hello! 👋</div>';
            return;
        }

        let lastDate = clearFirst ? null : body.dataset.lastDate;

        msgs.forEach(m => {
            // Already rendered (e.g. this is our own just-sent message and the
            // optimistic "sending" bubble already resolved to it, or an earlier
            // poll already added it) — skip to avoid a duplicate bubble.
            if (m.message_id && body.querySelector(`.fc-bubble[data-msg-id="${m.message_id}"]`)) return;

            const mine = parseInt(m.from_user) === MY_ID;
            const d = psDate(m.created_at);
            const dateStr = d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
            const timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            if (dateStr !== lastDate) {
                const div = document.createElement('div');
                div.className = 'fc-date-divider';
                div.textContent = dateStr;
                body.appendChild(div);
                lastDate = dateStr;
            }

            const bubble = document.createElement('div');
            bubble.className = `fc-bubble ${mine ? 'me' : 'them'}`;
            bubble.dataset.msgId = m.message_id;
            const bodyText = m.body && m.body !== '📎 Attachment' ? esc(m.body) : '';
            const attachHtml = m.attachment_url
                ? renderAttachment(m.attachment_url, m.message_id)
                : '';
            bubble.innerHTML = `${bodyText}${attachHtml}<div class="fc-bubble-time">${timeStr}</div>`;
            body.appendChild(bubble);
        });

        body.dataset.lastDate = lastDate;
        body.scrollTop = body.scrollHeight;
    }

    function renderAttachment(url, messageId) {
        const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(url);
        const proxyUrl = `../../endpoints/view_message_attachment.php?message_id=${messageId}`;
        if (isImage) {
            return `<img src="${proxyUrl}" loading="lazy" onclick="window.__fcOpenImage && window.__fcOpenImage('${proxyUrl}')" title="Click to enlarge">`;
        }
        const fname = esc(String(url).split('/').pop());
        return `<a href="${proxyUrl}" download class="fc-bubble-file">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            ${fname}</a>`;
    }

    function openImageLightbox(src) {
        let modal = document.getElementById('fc-img-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'fc-img-modal';
            modal.style.cssText = 'position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.85);cursor:zoom-out;';
            modal.innerHTML = `
                <button style="position:absolute;top:18px;right:22px;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:24px;width:40px;height:40px;border-radius:50%;cursor:pointer;">&times;</button>
                <img style="max-width:90vw;max-height:88vh;border-radius:10px;object-fit:contain;display:block;" onclick="event.stopPropagation()">`;
            modal.addEventListener('click', () => { modal.style.display = 'none'; });
            document.body.appendChild(modal);
        }
        modal.querySelector('img').src = src;
        modal.style.display = 'flex';
    }
    window.__fcOpenImage = openImageLightbox;

    function sendPopupMsg(adminId) {
        const state = popups.get(adminId);
        if (!state) return;
        const textarea = state.el.querySelector('textarea');
        const sendBtn = state.el.querySelector('.fc-popup-send');
        const fileInput = state.el.querySelector('.fc-popup-attach-btn input[type="file"]');
        const attachPreview = state.el.querySelector('.fc-popup-attach-preview');
        const body = textarea.value.trim();
        const file = state.pendingFile || null;
        if (!body && !file) return;

        const bodyEl = state.el.querySelector('.fc-popup-body');
        const placeholder = bodyEl.querySelector('.fc-popup-empty');
        if (placeholder) placeholder.remove();

        const tempId = 'tmp_' + Date.now();
        const bubble = document.createElement('div');
        bubble.className = 'fc-bubble me sending';
        bubble.id = tempId;
        const now = new Date();
        let innerHtml = body ? esc(body) : '';
        let tempImgUrl = null;
        if (file) {
            if (file.type.startsWith('image/')) {
                tempImgUrl = URL.createObjectURL(file);
                innerHtml += `<img src="${tempImgUrl}" loading="lazy">`;
            } else {
                innerHtml += `<div class="fc-bubble-file" style="opacity:.7;">${esc(file.name)}</div>`;
            }
        }
        bubble.innerHTML = `${innerHtml}<div class="fc-bubble-time">${now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>`;
        bodyEl.appendChild(bubble);
        bodyEl.scrollTop = bodyEl.scrollHeight;

        textarea.value = '';
        textarea.style.height = '';
        sendBtn.disabled = true;

        // Clear the attach UI immediately — the message is already "sent" optimistically.
        state.pendingFile = null;
        if (fileInput) fileInput.value = '';
        if (attachPreview) attachPreview.style.display = 'none';

        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('to_admin', adminId);
        fd.append('body', body);
        if (file) fd.append('attachment', file);
        csrfAppend(fd);

        fetch(API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                sendBtn.disabled = false;
                const tmp = document.getElementById(tempId);
                if (data.success) {
                    if (tmp) {
                        if (data.message_id && bodyEl.querySelector(`.fc-bubble[data-msg-id="${data.message_id}"]:not(#${tempId})`)) {
                            // A poll already rendered the real bubble while this
                            // request was in flight — drop the optimistic one.
                            tmp.remove();
                            if (tempImgUrl) URL.revokeObjectURL(tempImgUrl);
                        } else {
                            tmp.classList.remove('sending');
                            if (data.message_id) {
                                tmp.dataset.msgId = data.message_id;
                                if (tempImgUrl) {
                                    const img = tmp.querySelector(`img[src="${tempImgUrl}"]`);
                                    if (img) {
                                        const proxyUrl = `../../endpoints/view_message_attachment.php?message_id=${data.message_id}`;
                                        URL.revokeObjectURL(tempImgUrl);
                                        img.src = proxyUrl;
                                        img.setAttribute('onclick', `window.__fcOpenImage && window.__fcOpenImage('${proxyUrl}')`);
                                        img.title = 'Click to enlarge';
                                    }
                                }
                            }
                        }
                    }
                    state.lastTs = data.ts || state.lastTs;
                } else {
                    if (tmp) tmp.remove();
                    if (tempImgUrl) URL.revokeObjectURL(tempImgUrl);
                    if (window.showToast) window.showToast(data.message || 'Failed to send.', 'error');
                }
            })
            .catch(() => {
                sendBtn.disabled = false;
                const tmp = document.getElementById(tempId);
                if (tmp) tmp.remove();
                if (tempImgUrl) URL.revokeObjectURL(tempImgUrl);
            });
    }

    /* ── Polling for each open popup ───────────────────────── */
    function startPopupPoll(adminId) {
        const state = popups.get(adminId);
        if (!state) return;
        state.pollTimer = setInterval(() => {
            if (document.hidden || !state.lastTs) return;
            fetch(`${API}?action=poll&admin_id=${adminId}&since=${encodeURIComponent(state.lastTs)}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const msgs = data.messages || [];
                    if (msgs.length) {
                        const incoming = msgs.filter(m => parseInt(m.from_user) !== MY_ID);
                        if (incoming.length) renderPopupMessages(adminId, incoming, false);
                        state.lastTs = msgs[msgs.length - 1].created_at;
                    }
                })
                .catch(() => {});
        }, 3000);
    }

    function decrementMessagesBadge(by) {
        if (!by) return;
        document.querySelectorAll('[data-rt="messages"], [data-rt="messages-count"]').forEach(el => {
            const cur = parseInt(el.textContent) || 0;
            const next = Math.max(0, cur - by);
            el.textContent = next || '';
            if (el.style.display !== 'none') el.style.display = next > 0 ? '' : 'none';
        });
    }

    function openPopupByAdminId(adminId, opts) {
        adminId = parseInt(adminId, 10);
        if (!adminId) return;

        const cached = _threadsCache.find(t => parseInt(t.admin_id) === adminId);
        if (cached) {
            openPopup(adminId, cached.admin_name, cached.admin_photo || '', opts);
            return;
        }

        // Threads not loaded yet (dropdown never opened this session) — fetch once
        fetch(`${API}?action=threads`)
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    _threadsCache = data.threads || [];
                    _adminsCache = data.admins || [];
                }
                const t = _threadsCache.find(x => parseInt(x.admin_id) === adminId);
                openPopup(adminId, t ? t.admin_name : 'Admin', t ? (t.admin_photo || '') : '', opts);
            })
            .catch(() => openPopup(adminId, 'Admin', '', opts));
    }
    window.__fcOpenPopupByAdminId = openPopupByAdminId;

    /* ── Intercept every "Messages" link sitewide so the popup
         system replaces full-page navigation entirely ────────── */
    function interceptMessageLinks() {
        document.addEventListener('click', e => {
            const link = e.target.closest('a[href="messages.php"]');
            if (!link) return;
            e.preventDefault();
            // If the mobile profile sidebar is open, close it first so the
            // chat dropdown isn't competing with it for attention.
            const sidebarEl = document.getElementById('profileSidebar');
            const wasOpen = !!(sidebarEl && sidebarEl.classList.contains('open'));
            if (wasOpen && typeof window.closeSidebar === 'function') window.closeSidebar();

            const chatBtn = document.getElementById('chatBellBtn');
            if (chatBtn) setTimeout(() => toggleDropdown(chatBtn), wasOpen ? 200 : 0);
        });
    }

    /* ── Hook into realtime.js events for live badge + message delivery ── */
    function wireRealtime() {
        window.addEventListener('ps:new_messages', e => {
            (e.detail || []).forEach(m => {
                const senderId = parseInt(m.sender_id || m.from_user);
                if (!senderId || senderId === MY_ID) return;
                if (popups.has(senderId)) {
                    const msgId = m.id || m.message_id;
                    const state = popups.get(senderId);
                    const already = msgId && state.el.querySelector(`.fc-bubble[data-msg-id="${msgId}"]`);
                    if (!already) {
                        renderPopupMessages(senderId, [Object.assign({}, m, {
                            message_id: msgId,
                            from_user: senderId,
                        })], false);
                        if (m.ts) state.lastTs = m.ts;
                    }
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        init();
        interceptMessageLinks();
        wireRealtime();
        restorePopups();

        // Support old bookmarked links to messages.php (now redirected here with this flag)
        try {
            const params = new URLSearchParams(window.location.search);
            if (params.get('openMessages') === '1') {
                const chatBtn = document.getElementById('chatBellBtn');
                if (chatBtn) setTimeout(() => toggleDropdown(chatBtn), 150);
                params.delete('openMessages');
                const newQuery = params.toString();
                const newUrl = window.location.pathname + (newQuery ? '?' + newQuery : '') + window.location.hash;
                window.history.replaceState({}, '', newUrl);
            }
        } catch (e) {}
    });
})();