(function () {
  function escHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function fmtDate(ts) {
    const d = ts ? new Date(ts) : new Date();
    return d.toLocaleDateString('en-PH', { day: '2-digit', month: 'long', year: 'numeric' });
  }

  function relativeTime(ts) {
    if (!ts) return 'just now';
    const d = new Date(String(ts).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return 'just now';
    const sec = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (sec < 60) return 'just now';
    if (sec < 3600) return `${Math.floor(sec / 60)}m ago`;
    if (sec < 86400) return `${Math.floor(sec / 3600)}h ago`;
    return `${Math.floor(sec / 86400)}d ago`;
  }

  function iconForActivity(name, isExpense) {
    const n = String(name || '').toLowerCase();
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
  const notifState = new Map(
    (window.__PS_RIGHT_PANEL__.notifications).map(n => [String(n.id), n])
  );

  function renderNotifs() {
    const items = [...notifState.values()]
      .sort((a, b) => new Date(b.ts) - new Date(a.ts))
      .slice(0, 5);
    if (!items.length) {
      if (notifDot) notifDot.style.display = 'none';
      if (notifList) notifList.innerHTML = '<div style="padding:14px 12px;color:#94a3b8;font-size:12px;">No new notifications.</div>';
      return;
    }
    if (notifDot) notifDot.style.display = '';
    if (notifList) {
      notifList.innerHTML = items.map(n => `
          <div class="rp-notif-item" data-notif-id="${escHtml(n.id)}" data-path="${escHtml(n.path || '')}" style="padding:10px 12px;border-bottom:1px solid #f8fafc;cursor:pointer;">
            <div style="font-size:12px;color:#0f172a;line-height:1.35;">${escHtml(n.text || '')}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">${escHtml(relativeTime(n.ts))}</div>
          </div>
        `).join('');
    }
  }

  function addNotif(item) {
    if (!item || !item.id) return;
    notifState.set(String(item.id), item);
    while (notifState.size > 20) {
      const oldest = [...notifState.values()].sort((a, b) => new Date(a.ts) - new Date(b.ts))[0];
      if (!oldest) break;
      notifState.delete(String(oldest.id));
    }
    renderNotifs();
  }

  if (notifBtn && notifDrop) {
    notifBtn.addEventListener('click', e => {
      e.stopPropagation();
      notifDrop.style.display = notifDrop.style.display === 'none' ? 'block' : 'none';
      if (notifDrop.style.display === 'block' && notifDot) notifDot.style.display = 'none';
    });
    document.addEventListener('click', () => {
      notifDrop.style.display = 'none';
    });
    notifDrop.addEventListener('click', e => e.stopPropagation());
  }

  notifList?.addEventListener('click', e => {
    const item = e.target.closest('.rp-notif-item');
    if (!item) return;
    const notifId = item.dataset.notifId || '';
    const path = item.dataset.path || '';
    if (notifId) notifState.delete(String(notifId));
    renderNotifs();
    if (path) window.location.href = path;
  });

  notifMarkAll?.addEventListener('click', () => {
    const fd = new FormData();
    fd.append('action', 'mark_all_read');
    fetch('../../api/messages.php', { method: 'POST', body: fd }).catch(() => { });
    notifState.clear();
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
    return {
      id: t.request_id || t.id || `${t.title || t.issue_description || 'task'}-${t.request_date || ''}`,
      title: t.title || t.issue_description || 'Maintenance Task',
      priority: String(t.priority || '').toLowerCase(),
      status: String(t.status || t.request_status || 'pending').toLowerCase(),
      request_date: String(t.request_date || ''),
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
      .slice(0, 4);

    if (!sameDay.length) {
      scheduleWrap.innerHTML = '<div class="schedule-slot"><div class="time-col" style="opacity:.35;">&nbsp;</div><div style="flex:1;" class="empty-slot">No open tasks</div></div>';
      return;
    }

    scheduleWrap.innerHTML = sameDay.map(t => {
      const raw = String(t.request_date || '');
      const d = raw ? new Date(raw.replace(' ', 'T')) : null;
      const time = d && !Number.isNaN(d.getTime())
        ? d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }).toLowerCase()
        : '--';
      const eventClass = (t.priority === 'high' || t.priority === 'urgent' || t.status === 'open')
        ? 'coral'
        : (t.status === 'in_progress' ? 'teal' : 'dark');
      return `
          <div class="schedule-slot">
            <div class="time-col">${escHtml(time)}</div>
            <div class="event-card ${eventClass}">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              ${escHtml(t.title)}
            </div>
          </div>
        `;
    }).join('');
  }

  function renderCalendar() {
    if (!daysEl) return;
    if (monthEl) {
      monthEl.textContent = state.anchor.toLocaleDateString('en-PH', { month: 'long' });
    }

    const hasEvent = new Set(
      state.tasks.map(t => (t.request_date || '').slice(0, 10)).filter(Boolean)
    );

    const days = [];
    for (let i = -3; i <= 3; i++) {
      const d = new Date(state.anchor);
      d.setDate(state.anchor.getDate() + i);
      const ymd = toYmd(d);
      const cls = [
        'cal-day',
        sameDate(d, state.selectedDate) ? 'active' : '',
        hasEvent.has(ymd) ? 'has-event' : ''
      ].filter(Boolean).join(' ');
      days.push(`<div class="${cls}" data-cal-date="${ymd}">${d.getDate()}</div>`);
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
    if (!items.length) {
      wrap.innerHTML = '<div style="padding:14px 6px;color:#94a3b8;font-size:12px;">No recent transactions.</div>';
      return;
    }

    wrap.innerHTML = items.slice(0, 5).map(a => {
      const amount = Number(a.amount || 0);
      const isExpense = String(a.type || '').toLowerCase() === 'expense';
      const sign = isExpense ? '-' : '+';
      const name = String(a.description || 'Transaction').trim();
      return `
          <div class="activity-item">
            <div class="activity-avatar">
              ${iconForActivity(name, isExpense)}
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
  });

  window.addEventListener('ps:new_messages', e => {
    const msgs = Array.isArray(e.detail) ? e.detail : [];
    msgs.forEach(m => {
      addNotif({
        id: `msg-${m.id || m.message_id || Date.now()}`,
        type: 'message',
        text: `New message from ${m.sender_name || 'User'}`,
        ts: m.created_at || new Date().toISOString(),
        path: 'messages.php'
      });
    });
  });

  window.addEventListener('ps:new_bookings', e => {
    const bookings = Array.isArray(e.detail) ? e.detail : [];
    bookings.forEach(b => {
      if (String(b.status || '').toLowerCase() !== 'pending') return;
      addNotif({
        id: `booking-${b.booking_id}`,
        type: 'booking',
        text: `Pending booking #${String(b.booking_id || '').padStart(4, '0')}`,
        ts: b.created_at || new Date().toISOString(),
        path: 'reservations.php?status=pending'
      });
    });
  });

  window.addEventListener('ps:task_summary', e => {
    const tasks = Array.isArray(e.detail) ? e.detail : [];
    tasks.slice(0, 2).forEach(t => {
      if (!['open', 'in_progress'].includes(String(t.status || '').toLowerCase())) return;
      addNotif({
        id: `task-live-${(t.request_id || t.title || '').toString().replace(/\s+/g, '-').toLowerCase()}`,
        type: 'task',
        text: `Task update: ${t.title || 'Maintenance request'}`,
        ts: t.request_date || new Date().toISOString(),
        path: 'task_summary.php?status=open'
      });
    });
  });
})();
