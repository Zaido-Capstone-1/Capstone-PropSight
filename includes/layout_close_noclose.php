<?php
/**
 * layout_close_noclose.php
 * For pages that include right_panel.php (like the dashboard).
 * Those pages manually close .content and .main before including right_panel,
 * so this file only supplies the JS + </body></html>.
 */
?>
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
  window.PS_RT_ROLE = 'admin';
  window.PS_RT_API = '../../api/realtime.php';
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
    if (!_bellBtn || !_bellDrop) return;

    function _esc(s) {
      return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function _rel(ts) {
      if (!ts) return 'just now';
      var sec = Math.max(0, Math.floor((Date.now() - new Date(String(ts).replace(' ', 'T')).getTime()) / 1000));
      if (sec < 60) return 'just now';
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

    function _render() {
      var _arr = [];
      _st.forEach(function (v) { _arr.push(v); });
      _arr.sort(function (a, b) { return new Date(b.ts) - new Date(a.ts); });
      _arr = _arr.slice(0, 15);
      if (_bellDot) {
        if (_arr.length) {
          _bellDot.textContent = _arr.length > 99 ? '99+' : String(_arr.length);
          _bellDot.style.display = 'flex';
          _bellDot.style.background = '#ef4444';
        } else {
          _bellDot.textContent = '';
          _bellDot.style.display = 'none';
        }
      }
      if (!_bellList) return;
      if (!_arr.length) {
        _bellList.innerHTML = '<div style="padding:24px 14px;text-align:center;color:#94a3b8;font-size:13px;">No new notifications.</div>';
        return;
      }
      var _html = '';
      for (var _i = 0; _i < _arr.length; _i++) {
        var _n = _arr[_i];
        _html += '<div class="mobile-notif-item" data-id="' + _esc(_n.id) + '" data-db-id="' + _esc(String(_n.db_id || '')) + '" data-path="' + _esc(_n.path || '') + '">' +
          '<div class="mobile-notif-item-text">' + _esc(_n.text || _n.title || '') + '</div>' +
          '<div class="mobile-notif-item-time">' + _esc(_rel(_n.ts)) + '</div></div>';
      }
      _bellList.innerHTML = _html;
    }

    _render();

    _bellBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (_bellDrop.style.display === 'block') {
        _bellDrop.style.display = 'none';
      } else {
        _bellDrop.style.display = 'block';
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

    if (_bellList) {
      _bellList.addEventListener('click', function (e) {
        var _item = e.target.closest ? e.target.closest('.mobile-notif-item') : null;
        if (!_item) return;
        var _id = _item.getAttribute('data-id') || '';
        var _dbId = _item.getAttribute('data-db-id') || '';
        var _path = _item.getAttribute('data-path') || '';
        if (_dbId) {
          var _fd = new FormData();
          _fd.append('action', 'mark_read');
          _fd.append('id', _dbId);
          fetch('../../api/admin/notifications.php', { method: 'POST', body: _fd, keepalive: true }).catch(function () { });
        }
        if (_id) { _st.delete(_id); _render(); }
        if (_path) window.location.href = _path;
      });
    }

    if (_bellMark) {
      _bellMark.addEventListener('click', function () {
        var _fd = new FormData();
        _fd.append('action', 'mark_all_read');
        fetch('../../api/admin/notifications.php', { method: 'POST', body: _fd }).catch(function () { });
        _st.clear(); _render();
        _bellDrop.style.display = 'none';
      });
    }

    window.addEventListener('ps:admin_notifications', function (e) {
      var _items = (e.detail && Array.isArray(e.detail.items)) ? e.detail.items : [];
      // Rebuild from DB — clear first so dismissed items don't return
      _st.clear();
      for (var _j = 0; _j < _items.length; _j++) { if (_items[_j] && _items[_j].id) _st.set(String(_items[_j].id), _items[_j]); }
      _render();
    });
    // ps:new_messages and ps:new_bookings removed — handled by ps:admin_notifications from DB
  })();
</script>
</body>

</html>