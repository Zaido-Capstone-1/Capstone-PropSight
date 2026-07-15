/* ═══════════════════════════════════════════════════════════════════════════
   skeleton.js — hides the #psSkeleton overlay once the page is ready.
   Included right after the overlay markup on every page.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
  var MIN_VISIBLE_MS = 220;   // avoid a flash on fast/cached loads
  var MAX_WAIT_MS = 4000;  // safety net if 'load' never fires (slow map tiles, etc.)
  var shown = Date.now();
  var done = false;

  function hide() {
    if (done) return;
    done = true;
    var el = document.getElementById('psSkeleton');
    if (!el) return;
    var elapsed = Date.now() - shown;
    var wait = Math.max(0, MIN_VISIBLE_MS - elapsed);
    setTimeout(function () {
      el.classList.add('ps-skel-hide');
      setTimeout(function () {
        if (el && el.parentNode) el.parentNode.removeChild(el);
      }, 400);
    }, wait);
  }

  window.psHideSkeleton = hide;

  if (document.readyState === 'complete') {
    hide();
  } else {
    window.addEventListener('load', hide);
  }
  setTimeout(hide, MAX_WAIT_MS);

  // Pages restored from bfcache (back/forward) should never show a stuck overlay.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) hide();
  });
})();