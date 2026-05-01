</div><!-- /page-content -->
</div><!-- /page-shell -->

<script>
// ── MODAL TELEPORT — move all .modal-overlay elements to <body> so fixed positioning works correctly ──
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal-overlay').forEach(m => {
        if (m.parentElement !== document.body) document.body.appendChild(m);
    });
});
</script>

<script>
// ── SIDEBAR ──────────────────────────────────
function openSidebar() {
    document.getElementById('sidebarOverlay').classList.add('open');
    document.getElementById('profileSidebar').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebarOverlay').classList.remove('open');
    document.getElementById('profileSidebar').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('profileBtn').addEventListener('click', openSidebar);
document.getElementById('sidebarClose').addEventListener('click', closeSidebar);

// ── HEADER SCROLL ────────────────────────────
const hdr = document.getElementById('hdr');
window.addEventListener('scroll', () => hdr.classList.toggle('scrolled', scrollY > 20));

// ── HAMBURGER ────────────────────────────────
const burger = document.getElementById('hamburger');
const mob    = document.getElementById('mobileNav');
let mobOpen  = false;
burger.addEventListener('click', () => {
    mobOpen = !mobOpen;
    mob.classList.toggle('open', mobOpen);
    const s = burger.querySelectorAll('span');
    if (mobOpen) {
        s[0].style.transform = 'translateY(6.5px) rotate(45deg)';
        s[1].style.opacity   = '0';
        s[2].style.transform = 'translateY(-6.5px) rotate(-45deg)';
    } else resetB();
});
function resetB() { burger.querySelectorAll('span').forEach(s => { s.style.transform=''; s.style.opacity=''; }); }
function closeMob() { mobOpen=false; mob.classList.remove('open'); resetB(); }

// ── TOAST — delegates to global toast.js ─────
function showToast(msg, type) {
    /* type can be 'error'|'success'|'warning'|'info', or legacy boolean */
    if (type === true)  type = 'error';
    if (type === false || !type) type = 'success';
    if (window._psToastReady) { window.showToast(msg, type); return; }
    /* If toast.js not yet loaded, queue and retry */
    setTimeout(() => showToast(msg, type), 80);
}

// ── SCROLL REVEAL ────────────────────────────
const revObs = new IntersectionObserver(entries => {
    entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('visible'); revObs.unobserve(e.target); }});
}, { threshold: 0.08, rootMargin: '0px 0px -28px 0px' });
document.querySelectorAll('.reveal').forEach(el => revObs.observe(el));

// ── ESC KEY ──────────────────────────────────
document.addEventListener('keydown', e => { if(e.key==='Escape') closeSidebar(); });
</script>
<script src="../../assets/js/toast.js"></script>
<script>window._psToastReady = true;</script>
<script>
window.PS_RT_ROLE = 'user';
window.PS_RT_API  = '../../api/realtime.php';
</script>
<script src="../../assets/js/realtime.js"></script>
<script src="../../assets/js/user-js/user-realtime-pages.js"></script>
</body>
</html>
