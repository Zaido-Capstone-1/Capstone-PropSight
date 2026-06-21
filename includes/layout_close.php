<?php
/**
 * layout_close.php
 * Include at the BOTTOM of every page (closes .content, .main, body).
 */
?>
</div>
</div>

<script>
  const menuToggle = document.getElementById('menuToggle');
  const sidebarClose = document.getElementById('sidebarClose');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('visible');
    document.body.style.overflow = 'hidden';
    menuToggle.style.display = 'none';
    var _mb = document.getElementById('mobileNotifBtn'); if (_mb) _mb.style.display = 'none';
    var _md = document.getElementById('mobileNotifDropdown'); if (_md) _md.style.display = 'none';
    sidebarClose.focus();
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('visible');
    document.body.style.overflow = '';
    menuToggle.style.display = '';
    var _mb2 = document.getElementById('mobileNotifBtn'); if (_mb2) _mb2.style.display = '';
  }

  menuToggle.addEventListener('click', openSidebar);
  sidebarClose.addEventListener('click', closeSidebar);
  overlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
  });

  let touchStartX = 0;
  sidebar.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
  sidebar.addEventListener('touchend', e => {
    if (touchStartX - e.changedTouches[0].clientX > 60) closeSidebar();
  }, { passive: true });

  document.querySelectorAll('.nav-item.has-sub').forEach(item => {
    item.addEventListener('click', function () {
      const sub = this.nextElementSibling;
      if (!sub || !sub.classList.contains('nav-sub')) return;
      const isOpen = sub.classList.contains('open');
      document.querySelectorAll('.nav-sub').forEach(s => s.classList.remove('open'));
      document.querySelectorAll('.nav-item.has-sub').forEach(n => n.classList.remove('expanded'));
      if (!isOpen) { sub.classList.add('open'); this.classList.add('expanded'); }
    });
  });
</script>
<script src="../../assets/js/admin/admin-actions.js"></script>
<script src="../../assets/js/datetime.js"></script>
<script src="../../assets/js/toast.js"></script>
<script>window._psToastReady = true;</script>
<script>
  /* ── PropSight Real-Time bootstrap ── */
  window.PS_RT_ROLE = 'admin';
  window.PS_RT_API = '../../api/realtime.php';
  /* PS_RT_PAGE is set by each page before this script runs */
</script>
<script src="../../assets/js/realtime.js"></script>

<script>
  /* ── Mobile notification bell ───────────────────────────────── */
  (function () {
    var _bellBtn = document.getElementById('mobileNotifBtn');
    var _bellDrop = document.getElementById('mobileNotifDropdown');
    var _bellDot = document.getElementById('mobileNotifDot');
    var _bellList = document.getElementById('mobileNotifList');
    var _bellMark = document.getElementById('mobileNotifMarkAll');
    var _bellViewMoreWrap = document.getElementById('mobileNotifViewMore');
    var _bellViewMoreBtn = document.getElementById('mobileNotifViewMoreBtn');
    if (!_bellBtn || !_bellDrop) return;

    // Move dropdown to <body> to escape any stacking context from sidebar/layout wrappers
    document.documentElement.appendChild(_bellDrop);

    function _esc(s) {
      return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function _rel(ts) {
      if (!ts) return 'Just now';
      var sec = Math.max(0, Math.floor((Date.now() - new Date(String(ts).replace(' ', 'T')).getTime()) / 1000));
      if (sec < 60) return 'Just now';
      if (sec < 3600) return Math.floor(sec / 60) + 'm ago';
      if (sec < 86400) return Math.floor(sec / 3600) + 'h ago';
      return Math.floor(sec / 86400) + 'd ago';
    }

    if (!window.__mobileNotifs) {
      var _seed = (window.__PS_RIGHT_PANEL__ && window.__PS_RIGHT_PANEL__.notifications) || [];
      window.__mobileNotifs = new Map();
      for (var _si = 0; _si < _seed.length; _si++) window.__mobileNotifs.set(String(_seed[_si].id), _seed[_si]);
    }
    var _st = window.__mobileNotifs;

    // True unread total — independent of _st's list (which is capped at 15
    // for display), so the badge always reflects the real DB count.
    if (typeof window.__mobileNotifUnreadCount !== 'number') {
      window.__mobileNotifUnreadCount = (window.__PS_RIGHT_PANEL__ && window.__PS_RIGHT_PANEL__.notifUnreadCount) || 0;
    }
    // Pagination — "View more" loads additional pages of __mobileNotifPageSize items.
    if (typeof window.__mobileNotifPageSize !== 'number') {
      window.__mobileNotifPageSize = (window.__PS_RIGHT_PANEL__ && window.__PS_RIGHT_PANEL__.notifPageSize) || 10;
    }
    if (typeof window.__mobileNotifOffset !== 'number') {
      window.__mobileNotifOffset = _st.size;
    }
    if (typeof window.__mobileNotifHasMore !== 'boolean') {
      window.__mobileNotifHasMore = !!(window.__PS_RIGHT_PANEL__ && window.__PS_RIGHT_PANEL__.notifHasMore);
    }
    if (typeof window.__mobileNotifLoadingMore !== 'boolean') {
      window.__mobileNotifLoadingMore = false;
    }

    function _render() {
      var _arr = [];
      _st.forEach(function (v) { _arr.push(v); });
      _arr.sort(function (a, b) { return new Date(b.ts) - new Date(a.ts); });
      if (_bellDot) {
        if (window.__mobileNotifUnreadCount > 0) {
          _bellDot.textContent = window.__mobileNotifUnreadCount > 99 ? '99+' : String(window.__mobileNotifUnreadCount);
          _bellDot.style.display = 'flex';
          _bellDot.style.background = '#ef4444';
        } else {
          _bellDot.textContent = '';
          _bellDot.style.display = 'none';
        }
      }

      if (_bellViewMoreWrap) {
        _bellViewMoreWrap.style.display = window.__mobileNotifHasMore ? '' : 'none';
      }
      if (_bellViewMoreBtn) {
        _bellViewMoreBtn.textContent = window.__mobileNotifLoadingMore ? 'Loading…' : 'View more';
        _bellViewMoreBtn.disabled = window.__mobileNotifLoadingMore;
      }

      if (!_bellList) return;
      if (!_arr.length) {
        _bellList.innerHTML = '<div style="padding:24px 14px;text-align:center;color:#94a3b8;font-size:13px;">No notifications yet.</div>';
        return;
      }
      var _html = '';
      for (var _i = 0; _i < _arr.length; _i++) {
        var _n = _arr[_i];
        var _isRead = _n.is_read == 1;
        var _cls = 'mobile-notif-item ' + (_isRead ? 'is-read' : 'is-unread');
        var _dot = _isRead ? '' : '<span class="mobile-notif-item-dot"></span>';
        _html += '<div class="' + _cls + '" data-id="' + _esc(_n.id) + '" data-db-id="' + _esc(String(_n.db_id || '')) + '" data-path="' + _esc(_n.path || '') + '" data-is-read="' + (_isRead ? '1' : '0') + '">' +
          '<div class="mobile-notif-item-row">' + _dot +
          '<div style="flex:1;min-width:0;">' +
          '<div class="mobile-notif-item-text">' + _esc(_n.text || _n.title || '') + '</div>' +
          '<div class="mobile-notif-item-time">' + _esc(_rel(_n.ts)) + '</div>' +
          '</div></div></div>';
      }
      _bellList.innerHTML = _html;
    }

    _render();

    var _initialFetched = !!(window.__PS_RIGHT_PANEL__);

    function _fetchInitial(cb) {
      if (_initialFetched) { if (cb) cb(); return; }
      _initialFetched = true;
      _bellList.innerHTML = '<div style="padding:24px 14px;text-align:center;color:#94a3b8;font-size:13px;">Loading…</div>';
      fetch('../../api/admin/notifications.php?action=list&offset=0&limit=' + (window.__mobileNotifPageSize || 10))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.success) {
            var _list = data.notifications || [];
            _st.clear();
            for (var _k = 0; _k < _list.length; _k++) {
              if (_list[_k] && _list[_k].id) _st.set(String(_list[_k].id), _list[_k]);
            }
            window.__mobileNotifOffset = _list.length;
            window.__mobileNotifHasMore = !!data.has_more;
            if (typeof data.unread_count === 'number') window.__mobileNotifUnreadCount = data.unread_count;
          }
        })
        .catch(function () { })
        .finally(function () { _render(); if (cb) cb(); });
    }

    // Move dropdown to <html> root to escape body's overflow:hidden on mobile
    document.documentElement.appendChild(_bellDrop);

    _bellBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (_bellDrop.style.display !== 'none' && _bellDrop.style.display !== '') {
        _bellDrop.style.display = 'none';
      } else {
        _bellDrop.style.display = 'flex';
        _fetchInitial(null);
        setTimeout(function () {
          document.addEventListener('click', function _out(ev) {
            if (ev.target !== _bellBtn && !_bellDrop.contains(ev.target)) {
              _bellDrop.style.display = 'none';
              document.removeEventListener('click', _out);
            }
          });
        }, 10);
      }
    });

    _bellDrop.addEventListener('click', function (e) { e.stopPropagation(); });

    // Close and reset dropdown when resizing between mobile and desktop
    window.addEventListener('resize', function () {
      _bellDrop.style.display = 'none';
    });

    if (_bellList) {
      _bellList.addEventListener('click', function (e) {
        var _item = e.target.closest ? e.target.closest('.mobile-notif-item') : null;
        if (!_item) return;
        var _id = _item.getAttribute('data-id') || '';
        var _dbId = _item.getAttribute('data-db-id') || '';
        var _path = _item.getAttribute('data-path') || '';
        var _wasRead = _item.getAttribute('data-is-read') === '1';
        if (!_wasRead) {
          if (_dbId) {
            var _fd = new FormData();
            _fd.append('action', 'mark_read');
            _fd.append('id', _dbId);
            fetch('../../api/admin/notifications.php', { method: 'POST', body: _fd, keepalive: true }).catch(function () { });
          }
          if (_id && _st.has(_id)) { _st.get(_id).is_read = 1; }
          window.__mobileNotifUnreadCount = Math.max(0, window.__mobileNotifUnreadCount - 1);
          _render();
        }
        if (_path) window.location.href = _path;
      });
    }

    if (_bellMark) {
      _bellMark.addEventListener('click', function () {
        var _fd = new FormData();
        _fd.append('action', 'mark_all_read');
        fetch('../../api/admin/notifications.php', { method: 'POST', body: _fd }).catch(function () { });
        _st.forEach(function (n) { n.is_read = 1; });
        window.__mobileNotifUnreadCount = 0; _render();
        _bellDrop.style.display = 'none';
      });
    }

    if (_bellViewMoreBtn) {
      _bellViewMoreBtn.addEventListener('click', function () {
        if (window.__mobileNotifLoadingMore || !window.__mobileNotifHasMore) return;
        window.__mobileNotifLoadingMore = true;
        _render();
        fetch('../../api/admin/notifications.php?action=list&offset=' + window.__mobileNotifOffset + '&limit=' + window.__mobileNotifPageSize)
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (data && data.success) {
              var _list = data.notifications || [];
              for (var _k = 0; _k < _list.length; _k++) {
                if (_list[_k] && _list[_k].id) _st.set(String(_list[_k].id), _list[_k]);
              }
              window.__mobileNotifOffset += _list.length;
              window.__mobileNotifHasMore = !!data.has_more;
              if (typeof data.unread_count === 'number') window.__mobileNotifUnreadCount = data.unread_count;
            }
          })
          .catch(function () { })
          .finally(function () {
            window.__mobileNotifLoadingMore = false;
            _render();
          });
      });
    }

    window.addEventListener('ps:admin_notifications', function (e) {
      var _items = (e.detail && Array.isArray(e.detail.items)) ? e.detail.items : [];
      // Poll returns only the first page (most recent __mobileNotifPageSize,
      // read+unread). Replace just that slice so items loaded via "View more"
      // stay intact.
      var _freshIds = {};
      for (var _f = 0; _f < _items.length; _f++) { if (_items[_f] && _items[_f].id) _freshIds[String(_items[_f].id)] = true; }
      var _sorted = [];
      _st.forEach(function (v) { _sorted.push(v); });
      _sorted.sort(function (a, b) { return new Date(b.ts) - new Date(a.ts); });
      var _pageSize = window.__mobileNotifPageSize || 10;
      for (var _p = 0; _p < Math.min(_pageSize, _sorted.length); _p++) {
        var _pid = String(_sorted[_p].id);
        if (!_freshIds[_pid]) _st.delete(_pid);
      }
      var _newCount = 0;
      for (var _j = 0; _j < _items.length; _j++) {
        if (_items[_j] && _items[_j].id) {
          if (!_st.has(String(_items[_j].id))) _newCount++;
          _st.set(String(_items[_j].id), _items[_j]);
        }
      }
      window.__mobileNotifOffset = (window.__mobileNotifOffset || 0) + _newCount;
      window.__mobileNotifUnreadCount = (e.detail && typeof e.detail.count === 'number') ? e.detail.count : window.__mobileNotifUnreadCount;
      _render();
    });
    // ps:new_messages and ps:new_bookings removed — handled by ps:admin_notifications from DB
  })();
</script>
</body>

</html>