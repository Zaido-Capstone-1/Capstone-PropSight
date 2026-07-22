/* ── Floating messages bubble ──────────────────────────────────
   Loads on every admin page (via includes/messages_widget.php).
   Renders the thread list lazily (first time the bubble is opened)
   using the same JSON endpoints the full inbox page uses, then
   hands off to the existing functions in messages.js (loadConversation,
   sendMsg, openNewMessage, refreshThreadBadges, etc.) untouched.
─────────────────────────────────────────────────────────────── */
(function () {
    const bubble = document.getElementById('psMsgwBubble');
    const panel = document.getElementById('psMsgwPanel');
    const closeBtn = document.getElementById('psMsgwCloseBtn');
    const threadsEl = document.getElementById('psMsgwThreads');
    const searchInput = document.getElementById('psMsgwSearch');
    if (!bubble || !panel) return;

    let threadsLoaded = false;
    let usersLoaded = false;

    function escHtmlW(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtThreadTime(raw) {
        if (!raw || typeof psDate !== 'function') return '';
        const d = psDate(raw);
        if (!d || isNaN(d)) return '';
        return (Date.now() - d < 86400000)
            ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
    }

    function renderThreads(threads) {
        if (!threads.length) {
            threadsEl.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;">No messages yet.</div>';
            return;
        }
        threadsEl.innerHTML = threads.map(t => {
            const name = t.other_name || 'User';
            const initials = escHtmlW(name.trim().charAt(0).toUpperCase() || '?');
            const preview = escHtmlW((t.last_body || '').slice(0, 50));
            const unread = parseInt(t.unread) || 0;
            const time = fmtThreadTime(t.last_time);
            const rawPhoto = t.other_photo ? String(t.other_photo).trim() : '';
            const photo = rawPhoto
                ? (/^https?:\/\//i.test(rawPhoto) ? rawPhoto : ('../../' + rawPhoto.replace(/^\/+/, '')))
                : '';
            const avatar = photo
                ? `<img src="${escHtmlW(photo)}" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='';"><span style="display:none;">${initials}</span>`
                : initials;
            return `<div class="msg-thread${unread > 0 ? ' has-unread' : ''}" data-user-id="${parseInt(t.other_id)}" data-user-photo="${escHtmlW(photo)}"
                    onclick="loadConversation(${parseInt(t.other_id)}, '${String(name).replace(/'/g, "\\'")}', '${String(t.other_email || '').replace(/'/g, "\\'")}', '${photo.replace(/'/g, "\\'")}')"
                    style="cursor:pointer;">
                <div class="avatar">${avatar}</div>
                <div class="msg-thread-info">
                    <div class="msg-thread-name">${escHtmlW(name)}</div>
                    <div class="msg-thread-preview thread-preview">${preview}</div>
                </div>
                <div class="msg-thread-meta">
                    <div class="msg-thread-time">${time}</div>
                    ${unread > 0 ? `<div class="msg-unread">${unread}</div>` : ''}
                </div>
            </div>`;
        }).join('');
    }

    function loadThreads() {
        threadsEl.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;">Loading…</div>';
        fetch(`${ADMIN_API}?action=threads`)
            .then(r => r.json())
            .then(data => {
                threadsLoaded = true;
                if (data && data.success) renderThreads(data.threads || []);
                else threadsEl.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;">Couldn\'t load messages.</div>';
            })
            .catch(() => {
                threadsEl.innerHTML = '<div style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;">Couldn\'t load messages.</div>';
            });
    }

    function loadUsers() {
        fetch(`${ADMIN_API}?action=users`)
            .then(r => r.json())
            .then(data => {
                usersLoaded = true;
                if (data && data.success) window.__PS_ADMIN_MSG_USERS__ = data.users || [];
            })
            .catch(() => {});
    }

    function openPanel() {
        panel.classList.add('ps-msgw-open');
        bubble.classList.add('ps-msgw-open');
        bubble.setAttribute('aria-expanded', 'true');
        if (!threadsLoaded) loadThreads();
        if (!usersLoaded) loadUsers();
        if (typeof startThreadPoll === 'function') startThreadPoll();
        // Resume polling the open conversation, if one was left open
        if (typeof activeUserId !== 'undefined' && activeUserId && typeof startPoll === 'function') startPoll();
    }

    function closePanel() {
        panel.classList.remove('ps-msgw-open');
        bubble.classList.remove('ps-msgw-open');
        bubble.setAttribute('aria-expanded', 'false');
        // Nothing needs to keep polling while the widget is closed.
        if (typeof stopThreadPoll === 'function') stopThreadPoll();
        if (typeof stopPoll === 'function') stopPoll();
    }

    window.__psMsgwOpenUser = function (userId) {
        userId = parseInt(userId, 10);
        if (!userId) return;
        openPanel();

        function selectThread() {
            const el = threadsEl.querySelector(`.msg-thread[data-user-id="${userId}"]`);
            if (el) { el.click(); return true; }
            return false;
        }

        if (threadsLoaded) {
            selectThread();
        } else {
            const wait = setInterval(() => {
                if (threadsLoaded) {
                    clearInterval(wait);
                    selectThread();
                }
            }, 100);
            setTimeout(() => clearInterval(wait), 8000); // give up after 8s
        }
    };

    bubble.addEventListener('click', () => {
        panel.classList.contains('ps-msgw-open') ? closePanel() : openPanel();
    });

    if (closeBtn) closeBtn.addEventListener('click', closePanel);

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            threadsEl.querySelectorAll('.msg-thread').forEach(el => {
                const name = (el.querySelector('.msg-thread-name')?.textContent || '').toLowerCase();
                const preview = (el.querySelector('.thread-preview')?.textContent || '').toLowerCase();
                el.style.display = (!q || name.includes(q) || preview.includes(q)) ? '' : 'none';
            });
        });
    }

    window.addEventListener('ps:unread_messages', e => {
        const count = e.detail || 0;
        bubble.classList.toggle('ps-msgw-has-unread', count > 0);
    });

    // Keep the badge in sync even while the panel is closed/unopened —
    // realtime.js already updates [data-rt="messages"] on unread events,
    // this just seeds it with the initial count on first page load.
    fetch(`${ADMIN_API}?action=threads`)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) return;
            const total = (data.threads || []).reduce((sum, t) => sum + (parseInt(t.unread) || 0), 0);
            const badge = document.getElementById('psMsgwBadge');
            if (badge) {
                if (total > 0) {
                    badge.textContent = total > 99 ? '99+' : String(total);
                    badge.style.display = 'flex';
                    bubble.classList.add('ps-msgw-has-unread');
                } else {
                    badge.style.display = 'none';
                    bubble.classList.remove('ps-msgw-has-unread');
                }
            }
        })
        .catch(() => {});
})();