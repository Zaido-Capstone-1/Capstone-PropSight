const months = ['Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Aug', 'Sep'];
const values = [60, 85, 45, 55, 40, 65, 90, 70];
const activeIdx = 6;
const maxH = 140;
const barChart = document.getElementById('barChart');

months.forEach((m, i) => {
    const h = Math.round((values[i] / 100) * maxH);
    const col = document.createElement('div');
    col.className = 'bar-col';

    const bar = document.createElement('div');
    bar.className = 'bar' + (i === activeIdx ? ' active' : '');
    bar.style.height = '0px';
    bar.style.width = '72%';

    if (i === activeIdx) {
        const tip = document.createElement('div');
        tip.className = 'bar-tooltip';
        tip.textContent = '$7,238.00';
        col.appendChild(tip);
    }

    bar.addEventListener('mouseenter', () => { if (i !== activeIdx) bar.classList.add('active'); });
    bar.addEventListener('mouseleave', () => { if (i !== activeIdx) bar.classList.remove('active'); });

    const lbl = document.createElement('div');
    lbl.className = 'bar-label';
    lbl.textContent = m;

    col.appendChild(bar);
    col.appendChild(lbl);
    barChart.appendChild(col);
    setTimeout(() => { bar.style.height = h + 'px'; }, 100 + i * 60);
});

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
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (diff > 60) closeSidebar();
}, { passive: true });

document.querySelectorAll('.nav-item.has-sub').forEach(item => {
    item.addEventListener('click', function () {
        const sub = this.nextElementSibling;
        if (!sub || !sub.classList.contains('nav-sub')) return;

        const isOpen = sub.classList.contains('open');

        document.querySelectorAll('.nav-sub').forEach(s => s.classList.remove('open'));
        document.querySelectorAll('.nav-item.has-sub').forEach(n => n.classList.remove('expanded'));

        if (!isOpen) {
            sub.classList.add('open');
            this.classList.add('expanded');
        }

        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        this.classList.add('active');
    });
});

document.querySelectorAll('.nav-item:not(.has-sub):not(.logout)').forEach(item => {
    item.addEventListener('click', function () {
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.querySelectorAll('.nav-sub').forEach(s => s.classList.remove('open'));
        document.querySelectorAll('.nav-item.has-sub').forEach(n => n.classList.remove('expanded'));
        this.classList.add('active');
        if (window.innerWidth <= 860) closeSidebar();
    });
});

document.querySelectorAll('.sub-item').forEach(item => {
    item.addEventListener('click', function (e) {
        e.stopPropagation();
        document.querySelectorAll('.sub-item').forEach(s => s.classList.remove('active'));
        this.classList.add('active');
        if (window.innerWidth <= 860) closeSidebar();
    });
});