(function () {

  // Safe wrapper — psDate may not be loaded yet if datetime.js loads after right-panel.js
  function _psDate(ts) {
    if (!ts) return null;
    if (typeof window.psDate === 'function') return window.psDate(ts);
    // Fallback: strip +00:00 suffix, parse as UTC
    const s = String(ts).replace(' ', 'T');
    const clean = s.replace(/[+-]\d{2}:\d{2}$/, '');
    const d = new Date(clean.includes('T') ? clean + 'Z' : clean);
    return isNaN(d.getTime()) ? null : d;
  }

  function escHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function fmtDate(ts) {
    if (!ts) return '—';
    const s = String(ts);
    // DATE-only values (no T separator) — anchor at noon local to avoid UTC rollback
    const isDateOnly = /^\d{4}-\d{2}-\d{2}([+Z].*)?$/.test(s) && !s.includes('T');
    const d = isDateOnly ? new Date(s.slice(0, 10) + 'T12:00:00') : _psDate(ts);
    if (!d || isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  }

  function relativeTime(ts) {
    if (!ts) return 'Just now';
    // _psDate() from datetime.js correctly converts UTC "+00:00" timestamps to local time
    const d = _psDate(ts);
    if (!d) return 'Just now';
    const sec = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (sec < 60) return 'Just now';
    if (sec < 3600) return `${Math.floor(sec / 60)}m ago`;
    if (sec < 86400) return `${Math.floor(sec / 3600)}h ago`;
    return `${Math.floor(sec / 86400)}d ago`;
  }

  function iconForActivity(name, isExpense, type) {
    const n = String(name || '').toLowerCase();
    if (String(type || '').toLowerCase() === 'refund' || n.includes('refund')) {
      return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3v5h5"/></svg>';
    }
    if (n.includes('booking') || n.includes('reservation')) {
      return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
    }
    if (n.includes('water') || n.includes('electric') || n.includes('bill')) {
      return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3s-6 6.5-6 10a6 6 0 0 0 12 0c0-3.5-6-10-6-10z"/></svg>';
    }
    if (isExpense) {
      return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
    }
    return '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>';
  }

  const notifBtn = document.getElementById('adminNotifBtn');
  const notifDrop = document.getElementById('adminNotifDropdown');
  const notifDot = document.getElementById('adminNotifDot');
  const notifList = document.getElementById('adminNotifList');
  const notifMarkAll = document.getElementById('adminNotifMarkAll');
  const notifViewMoreWrap = document.getElementById('adminNotifViewMore');
  const notifViewMoreBtn = document.getElementById('adminNotifViewMoreBtn');

  // notifState is keyed by ref_id (e.g. 'task-11'), value includes db_id for API calls
  const notifState = new Map(
    (window.__PS_RIGHT_PANEL__.notifications).map(n => [String(n.id), n])
  );

  // True unread total — independent of notifState's list (which is capped),
  // so the badge always reflects the real DB count.
  let unreadCount = window.__PS_RIGHT_PANEL__.notifUnreadCount || 0;

  // Pagination — "View more" loads additional pages of notifPageSize items.
  const notifPageSize = window.__PS_RIGHT_PANEL__.notifPageSize || 10;
  let notifOffset = notifState.size;
  let notifHasMore = !!window.__PS_RIGHT_PANEL__.notifHasMore;
  let notifLoadingMore = false;

  function renderNotifs() {
    const items = [...notifState.values()]
      .sort((a, b) => new Date(b.ts) - new Date(a.ts));
    if (notifDot) {
      if (unreadCount > 0) {
        notifDot.style.display = 'flex';
        notifDot.textContent = unreadCount > 99 ? '99+' : unreadCount;
      } else {
        notifDot.style.display = 'none';
        notifDot.textContent = '';
      }
    }
    if (notifViewMoreWrap) {
      notifViewMoreWrap.style.display = notifHasMore ? '' : 'none';
    }
    if (notifViewMoreBtn) {
      notifViewMoreBtn.textContent = notifLoadingMore ? 'Loading…' : 'View more';
      notifViewMoreBtn.disabled = notifLoadingMore;
    }
    if (!items.length) {
      if (notifList) notifList.innerHTML = `
        <div class="rp-empty-state" style="padding:28px 12px;">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
          </svg>
          <span>No notifications yet</span>
        </div>
      `;
      return;
    }
    if (notifList) {
      notifList.innerHTML = items.map(n => {
        const isRead = n.is_read == 1;
        const itemStyle = isRead
          ? 'padding:10px 12px;border-bottom:1px solid #f8fafc;cursor:pointer;opacity:0.6;'
          : 'padding:10px 12px;border-bottom:1px solid #f8fafc;cursor:pointer;background:#eff6ff;';
        const textStyle = isRead
          ? 'font-size:12px;color:#64748b;font-weight:400;line-height:1.35;'
          : 'font-size:12px;color:#0f172a;font-weight:600;line-height:1.35;';
        return `
          <div class="rp-notif-item"
            data-notif-id="${escHtml(n.id)}"
            data-db-id="${escHtml(String(n.db_id || ''))}"
            data-path="${escHtml(n.path || '')}"
            data-is-read="${isRead ? '1' : '0'}"
            style="${itemStyle}">
            <div style="display:flex;align-items:flex-start;gap:6px;">
              ${isRead ? '' : '<span style="flex:0 0 6px;width:6px;height:6px;border-radius:50%;background:#2563eb;margin-top:5px;"></span>'}
              <div style="flex:1;min-width:0;">
                <div style="${textStyle}">${escHtml(n.text || '')}</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">${escHtml(relativeTime(n.ts))}</div>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }
  }

  function addNotif(item) {
    if (!item || !item.id) return;
    notifState.set(String(item.id), item);
    while (notifState.size > 50) {
      const oldest = [...notifState.values()].sort((a, b) => new Date(a.ts) - new Date(b.ts))[0];
      if (!oldest) break;
      notifState.delete(String(oldest.id));
    }
    renderNotifs();
  }

  if (notifBtn && notifDrop) {
    notifBtn.addEventListener('click', e => {
      e.stopPropagation();
      const isOpen = notifDrop.style.display === 'none' || notifDrop.style.display === '';
      if (isOpen) {
        // Position below the bell button, respecting viewport edges
        const rect = notifBtn.getBoundingClientRect();
        notifDrop.style.top = (rect.bottom + 6) + 'px';
        const dropW = Math.min(300, window.innerWidth - 16);
        const rightEdge = window.innerWidth - rect.right;
        notifDrop.style.right = Math.max(8, rightEdge - (rect.width / 2)) + 'px';
        notifDrop.style.left = 'auto';
      }
      notifDrop.style.display = isOpen ? 'flex' : 'none';
    });
    document.addEventListener('click', () => {
      notifDrop.style.display = 'none';
    });
    notifDrop.addEventListener('click', e => e.stopPropagation());
    window.addEventListener('resize', () => {
      notifDrop.style.display = 'none';
    });
  }

  notifList?.addEventListener('click', e => {
    const item = e.target.closest('.rp-notif-item');
    if (!item) return;
    const notifId = item.dataset.notifId || '';
    const dbId    = item.dataset.dbId || '';
    const path    = item.dataset.path || '';
    const wasRead = item.dataset.isRead === '1';

    if (notifId && !wasRead) {
      // Mark as read in admin_notifications table via DB id
      if (dbId) {
        const fd = new FormData();
        fd.append('action', 'mark_read');
        fd.append('id', dbId);
        // keepalive: true guarantees delivery even during page navigation,
        // and unlike sendBeacon it correctly sends session cookies
        fetch('../../endpoints/admin/notifications.php', {
          method: 'POST', body: fd, keepalive: true
        }).catch(() => {});
      }
      const existing = notifState.get(String(notifId));
      if (existing) existing.is_read = 1;
      unreadCount = Math.max(0, unreadCount - 1);
      renderNotifs();
    }

    if (path) window.location.href = path;
  });

  notifMarkAll?.addEventListener('click', () => {
    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
    fetch('../../endpoints/admin/notifications.php', { method: 'POST', body: fd }).catch(() => {});
    notifState.forEach(n => { n.is_read = 1; });
    unreadCount = 0;
    renderNotifs();
  });

  notifViewMoreBtn?.addEventListener('click', async () => {
    if (notifLoadingMore || !notifHasMore) return;
    notifLoadingMore = true;
    renderNotifs();
    try {
      const res = await fetch(`../../endpoints/admin/notifications.php?action=list&offset=${notifOffset}&limit=${notifPageSize}`);
      const data = await res.json();
      if (data && data.success) {
        (data.notifications || []).forEach(n => {
          if (n && n.id) notifState.set(String(n.id), n);
        });
        notifOffset += (data.notifications || []).length;
        notifHasMore = !!data.has_more;
        if (Number.isFinite(data.unread_count)) unreadCount = data.unread_count;
      }
    } catch (_) {
      // leave hasMore as-is; user can retry
    }
    notifLoadingMore = false;
    renderNotifs();
  });

  const monthEl = document.getElementById('rt-cal-month');
  const daysEl = document.getElementById('rt-cal-days');
  const prevBtn = document.getElementById('rt-cal-prev');
  const nextBtn = document.getElementById('rt-cal-next');
  const scheduleWrap = document.getElementById('rt-right-schedule');
  const initialTasks = window.__PS_RIGHT_PANEL__.tasks;

  const state = {
    anchor: new Date(),
    selectedDate: new Date(),
    tasks: []
  };

  function toYmd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  function normalizeTask(t) {
    const raw = t.title || t.issue_description || 'Maintenance Task';
    const typeMatch = raw.match(/^\[([^\]]+)\]/);
    return {
      id: t.request_id || t.id || `${raw}-${t.request_date || ''}`,
      title: raw,
      task_type: typeMatch ? typeMatch[1].toLowerCase() : 'other',
      property_name: t.property_name || '',
      priority: String(t.priority || '').toLowerCase(),
      status: String(t.status || t.request_status || 'pending').toLowerCase(),
      request_date: String(t.request_date || ''),
      created_at: t.created_at || null,
    };
  }

  function sameDate(a, b) {
    return a.getFullYear() === b.getFullYear() &&
      a.getMonth() === b.getMonth() &&
      a.getDate() === b.getDate();
  }

  function renderScheduleForSelectedDate() {
    if (!scheduleWrap) return;
    const selectedYmd = toYmd(state.selectedDate);
    const sameDay = state.tasks
      .filter(t => (t.request_date || '').slice(0, 10) === selectedYmd)
      .slice(0, 50);

    if (!sameDay.length) {
      scheduleWrap.innerHTML = `
        <div class="rp-empty-state">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
            <path d="M9 16l2 2 4-4" />
          </svg>
          <span>No open tasks for this day</span>
        </div>
      `;
      return;
    }

    scheduleWrap.innerHTML = sameDay.map(t => {
      // Use created_at for time if available, else '--'
      const _tsd = t.created_at ? _psDate(t.created_at) : null;
      const time = _tsd ? _tsd.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '--';
      const eventClass = (t.priority === 'high' || t.priority === 'urgent' || t.status === 'open') ?
        'coral' :
        (t.status === 'in_progress' ? 'teal' : 'dark');
      return `
          <div class="schedule-slot">
            <div class="time-col">${escHtml(time)}</div>
            <div class="event-card ${eventClass}">
              ${({
                'plumbing':         '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3-3a1 1 0 0 0 0-1.4l-1.6-1.6a1 1 0 0 0-1.4 0l-3 3z"/><path d="M3 21l9.3-9.3"/><path d="M9.4 14.6 3 21"/></svg>',
                'electrical':       '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><polyline points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
                'air conditioning': '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><path d="M12 2v20M2 12h20M4.93 4.93l14.14 14.14M19.07 4.93 4.93 19.07"/></svg>',
                'furniture':        '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>',
                'other':            '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
              }[t.task_type] || '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="14" height="14"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>')}
              ${escHtml(t.task_type.charAt(0).toUpperCase() + t.task_type.slice(1))}
              ${t.property_name ? `<span style="opacity:.7;font-size:.8em;">· ${escHtml(t.property_name)}</span>` : ''}
            </div>
          </div>
        `;
    }).join('');
  }

  function renderCalendar() {
    if (!daysEl) return;
    if (monthEl) {
      monthEl.textContent = state.anchor.toLocaleDateString('en-US', {
        month: 'long'
      });
    }

    const hasEvent = new Set(
      state.tasks.map(t => (t.request_date || '').slice(0, 10)).filter(Boolean)
    );

    const now = new Date();
    const days = [];
    for (let i = -3; i <= 3; i++) {
      const d = new Date(state.anchor);
      d.setDate(state.anchor.getDate() + i);
      const ymd = toYmd(d);
      const cls = [
        'cal-day',
        sameDate(d, state.selectedDate) ? 'active' : '',
        sameDate(d, now) ? 'today' : '',
        hasEvent.has(ymd) ? 'has-event' : ''
      ].filter(Boolean).join(' ');
      const weekday = d.toLocaleDateString('en-US', { weekday: 'short' }).slice(0, 1);
      days.push(`
        <div class="cal-day-col">
          <div class="cal-day-label">${weekday}</div>
          <div class="${cls}" data-cal-date="${ymd}">${d.getDate()}</div>
        </div>
      `);
    }
    daysEl.innerHTML = days.join('');
  }

  function setSelectedDate(ymd) {
    const d = new Date(`${ymd}T00:00:00`);
    if (Number.isNaN(d.getTime())) return;
    state.selectedDate = d;
    state.anchor = new Date(d);
    renderCalendar();
    renderScheduleForSelectedDate();
  }

  state.tasks = initialTasks.map(normalizeTask);
  renderCalendar();
  renderScheduleForSelectedDate();

  prevBtn?.addEventListener('click', () => {
    state.anchor.setDate(state.anchor.getDate() - 7);
    state.selectedDate = new Date(state.anchor);
    renderCalendar();
    renderScheduleForSelectedDate();
  });
  nextBtn?.addEventListener('click', () => {
    state.anchor.setDate(state.anchor.getDate() + 7);
    state.selectedDate = new Date(state.anchor);
    renderCalendar();
    renderScheduleForSelectedDate();
  });
  daysEl?.addEventListener('click', e => {
    const target = e.target.closest('.cal-day');
    if (!target || !target.dataset.calDate) return;
    setSelectedDate(target.dataset.calDate);
  });

  window.addEventListener('ps:task_summary', e => {
    const tasks = Array.isArray(e.detail) ? e.detail : [];
    state.tasks = tasks.map(normalizeTask);
    renderCalendar();
    renderScheduleForSelectedDate();
  });

  window.addEventListener('ps:right_panel_activity', e => {
    const items = Array.isArray(e.detail) ? e.detail : [];
    const wrap = document.getElementById('rt-right-activity');
    if (!wrap) return;
    // Transactions list is rarely truly empty; an empty poll result is more
    // likely a transient/incomplete response than an actual change to zero
    // transactions. Avoid flickering a populated list to "No recent
    // transactions" — only show that state on the very first render.
    if (!items.length) {
      if (!wrap.dataset.rendered) {
        wrap.innerHTML = `
          <div class="rp-empty-state">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M14 2H6a2 2 0 0 0-2 2v16l3-2 3 2 3-2 3 2V8z" />
              <line x1="8" y1="8" x2="14" y2="8" />
              <line x1="8" y1="12" x2="14" y2="12" />
            </svg>
            <span>No transactions yet</span>
          </div>
        `;
        wrap.dataset.rendered = '1';
      }
      return;
    }

    wrap.innerHTML = items.slice(0, 6).map(a => {
      const amount = Number(a.amount || 0);
      const typeLower = String(a.type || '').toLowerCase();
      const isExpense = typeLower === 'expense' || typeLower === 'refund';
      const sign = isExpense ? '-' : '+';
      const name = String(a.description || 'Transaction').trim();
      return `
          <div class="activity-item">
            <div class="activity-avatar">
              ${iconForActivity(name, isExpense, a.type)}
            </div>
            <div class="activity-info">
              <div class="activity-name">${escHtml(name)}</div>
              <div class="activity-date">${escHtml(fmtDate(a.transaction_date))}</div>
            </div>
            <div class="activity-amount" style="${isExpense ? 'color:var(--danger);' : ''}">
              ${sign}₱ ${amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
            </div>
          </div>
        `;
    }).join('');
    wrap.dataset.rendered = '1';
  });

  // ps:new_messages — notifications handled exclusively via ps:admin_notifications

  // ps:new_bookings — notifications handled exclusively via ps:admin_notifications

  // ps:task_summary — schedule/calendar only, NOT notifications
  // Notifications come exclusively from admin_notifications DB table via ps:admin_notifications

  // ── Wire realtime admin_notifications poll → bell badge ──────────
  window.addEventListener('ps:admin_notifications', e => {
    const items = Array.isArray(e.detail?.items) ? e.detail.items : [];
    // The poll only returns the first page (most recent `notifPageSize`
    // items, read+unread). Replace just that slice in notifState so any
    // additional pages loaded via "View more" stay intact.
    const freshIds = new Set(items.map(n => String(n.id)));
    const sorted = [...notifState.values()].sort((a, b) => new Date(b.ts) - new Date(a.ts));
    sorted.slice(0, notifPageSize).forEach(n => {
      if (!freshIds.has(String(n.id))) notifState.delete(String(n.id));
    });
    const newCount = items.filter(n => n && n.id && !notifState.has(String(n.id))).length;
    items.forEach(n => {
      if (n && n.id) notifState.set(String(n.id), n);
    });
    // Keep "View more" offset aligned when brand-new items shift the list
    notifOffset += newCount;
    // True unread total from the server — independent of the list's LIMIT
    unreadCount = Number.isFinite(e.detail?.count) ? e.detail.count : unreadCount;
    // renderNotifs() updates both list and badge — single source of truth
    renderNotifs();
  });

  // Render immediately — right-panel.js loads at </body> so DOM is ready
  renderNotifs();
  // Refresh relative timestamps every minute
  setInterval(renderNotifs, 60000);

})();