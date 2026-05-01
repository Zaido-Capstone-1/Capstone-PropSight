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
    sidebarClose.focus();
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('visible');
    document.body.style.overflow = '';
    menuToggle.style.display = '';
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
<script src="../../assets/js/toast.js"></script>
<script>window._psToastReady = true;</script>
<script>
window.PS_RT_ROLE = 'admin';
window.PS_RT_API  = '../../api/realtime.php';
</script>
<script src="../../assets/js/realtime.js"></script>
</body>

</html>