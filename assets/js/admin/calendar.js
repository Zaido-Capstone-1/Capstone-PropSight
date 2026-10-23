let dayData = window.__PS_CALENDAR__.dayData;
let bookingsByDay = window.__PS_CALENDAR__.bookingsByDay;
let blockedDates = window.__PS_CALENDAR__.blockedDates;
let monthShort = window.__PS_CALENDAR__.monthShort;
let monthNum = window.__PS_CALENDAR__.monthNum;
let yearNum = window.__PS_CALENDAR__.yearNum;
const dows = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
let startDow = window.__PS_CALENDAR__.startDow;
let daysInMonth = window.__PS_CALENDAR__.daysInMonth;
let todayDay = window.__PS_CALENDAR__.todayDay;
const statusMap = { booked: 'Fully Booked', partial: 'Partially Booked', free: 'Available', blocked: 'Blocked' };
let selectedDay = window.__PS_CALENDAR__.selectedDay;
let isLoadingMonth = false;

// Normalize the initial history entry so browser back/forward after a
// month switch can restore the month the page was first opened on.
history.replaceState({ year: yearNum, month: monthNum }, '', `?year=${yearNum}&month=${monthNum}`);

/* =========================
   Dynamic month switching
========================= */

function navMonth(delta) {
    let m = monthNum + delta;
    let y = yearNum;
    if (m < 1) { m = 12; y--; }
    if (m > 12) { m = 1; y++; }
    loadMonth(y, m);
}

function goToday() {
    loadMonth(); // no params = server resolves the current month
}

function loadMonth(y, m) {
    if (isLoadingMonth) return;
    isLoadingMonth = true;
    document.querySelectorAll('.cal-nav-btn, .cal-today-btn').forEach(b => b.classList.add('disabled'));

    const qs = (y && m) ? `?year=${y}&month=${m}` : '';
    fetch(`../../endpoints/admin/calendar_month.php${qs}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showToast(data.message || 'Could not load that month.', 'error');
                return;
            }
            applyMonthData(data);
            const url = `?year=${data.year}&month=${data.month_num}`;
            history.pushState({ year: data.year, month: data.month_num }, '', url);
        })
        .catch(() => showToast('Server unreachable.', 'error'))
        .finally(() => {
            isLoadingMonth = false;
            document.querySelectorAll('.cal-nav-btn, .cal-today-btn').forEach(b => b.classList.remove('disabled'));
        });
}

function applyMonthData(data) {
    dayData = data.day_data;
    bookingsByDay = data.bookings_by_day;
    blockedDates = data.blocked_dates;
    monthShort = data.month_short;
    monthNum = data.month_num;
    yearNum = data.year;
    startDow = data.start_dow;
    daysInMonth = data.days_in_month;
    todayDay = data.today_day;
    selectedDay = todayDay > 0 ? todayDay : 1;

    document.getElementById('calMonthTitle').textContent = data.month_name;
    document.getElementById('calYearBadge').textContent = data.year;
    document.getElementById('statBooked').textContent = data.total_booked;
    document.getElementById('statPartial').textContent = data.total_partial;
    document.getElementById('statFree').textContent = data.total_free;
    document.getElementById('statRate').textContent = data.occ_rate;

    buildDayGrid();

    // Re-apply whichever property filter was active before the switch
    const activePill = document.querySelector('.prop-filter-pill.active');
    if (activePill) {
        const propId = activePill.dataset.propId === 'all' ? 'all' : Number(activePill.dataset.propId);
        setFilter(activePill, propId);
    }

    renderDayDetail(selectedDay);
}

function buildDayGrid() {
    let html = '';
    for (let i = 0; i < startDow; i++) {
        html += '<div class="cal-day-cell empty"></div>';
    }
    for (let d = 1; d <= daysInMonth; d++) {
        const info = dayData[d] || { status: 'free', count: 0, total: 0 };
        const s = info.status;
        const isToday = d === todayDay;
        const isSelected = d === selectedDay;
        const classes = `cal-day-cell ${s}` + (isToday ? ' today' : '') + (isSelected ? ' selected' : '');
        const props = (info.props || []).join(',');

        let pill;
        if (s === 'booked' || s === 'partial') pill = `${info.count}/${info.total}`;
        else if (s === 'free') pill = 'Open';
        else pill = '—';

        let dots = '';
        if (s === 'booked' || s === 'partial') {
            dots = `<div class="cal-day-dots">${'<span></span>'.repeat(Math.min(info.count, 3))}</div>`;
        }

        html += `<div class="${classes}" onclick="selectDay(${d}, this)" data-day="${d}" data-props="${props}">
                <div class="cal-day-num">${d}</div>
                <div class="cal-day-pill">${pill}</div>
                ${dots}
            </div>`;
    }
    document.getElementById('calGrid').innerHTML = html;
}

// Support browser back/forward after a month switch
window.addEventListener('popstate', e => {
    if (e.state && e.state.year && e.state.month) {
        loadMonth(e.state.year, e.state.month);
    } else {
        loadMonth();
    }
});

function selectDay(day, el) {
    document.querySelectorAll('.cal-day-cell.selected').forEach(c => c.classList.remove('selected'));
    if (el) el.classList.add('selected');
    selectedDay = day;
    renderDayDetail(day);
    if (window.innerWidth <= 960) openDrawer();
}

function renderDayDetail(day) {
    const info = dayData[day] || { status: 'free', count: 0, total: 0 };
    const dow = dows[(startDow + day - 1) % 7];
    const bookings = bookingsByDay[day] || [];
    const blocked = blockedDates[day];

    document.getElementById('detailDayNum').textContent = day;
    document.getElementById('detailDayDow').textContent = dow;
    document.getElementById('detailBookCount').textContent =
        bookings.length ? bookings.length + ' Booking' + (bookings.length > 1 ? 's' : '') : 'No Bookings';
    document.getElementById('detailStatus').textContent = statusMap[info.status] || 'Available';

    const body = document.getElementById('dayDetailBody');
    if (info.status === 'blocked') {
        body.innerHTML = `
            <div class="day-detail-empty">
                <svg width="36" height="36" fill="none" stroke="#f87171" stroke-width="1.5" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                </svg>
                <p>This date is blocked</p>
                ${blocked?.reason ? `<div class="blocked-reason">${escHtml(blocked.reason)}</div>` : ''}
            </div>`;
    } else if (!bookings.length) {
        body.innerHTML = `
            <div class="day-detail-empty">
                <svg width="36" height="36" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <p>No bookings on ${monthShort} ${day}</p>
            </div>`;
    } else {
        body.innerHTML = bookings.map(b => `
            <div class="booking-entry ${b.status}">
                <div class="be-top">
                    <span class="be-ref">#BK-${String(b.booking_id).padStart(4, '0')}</span>
                    <span class="be-badge ${b.status}">${b.status.charAt(0).toUpperCase() + b.status.slice(1)}</span>
                </div>
                <div class="be-name">${escHtml(b.guest_name)}</div>
                <div class="be-unit">${escHtml(b.unit_label)}</div>
                <div class="be-time">
                    <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2;">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    ${b.checkin_date} → ${b.checkout_date}
                </div>
            </div>`).join('');
    }
}

function openBlockModal(date = null) {
    const overlay = document.getElementById('blockModalOverlay');
    const input = document.getElementById('blockDateInput');
    const reason = document.getElementById('blockReasonInput');
    const title = document.getElementById('blockModalTitle');
    const blockBtn = document.getElementById('blockBtn');
    const unblockBtn = document.getElementById('unblockBtn');

    if (date) {
        input.value = date;
        input.readOnly = true;
        const padM = String(monthNum).padStart(2, '0');
        const padD = String(date.split('-')[2]).padStart(2, '0');
        const d = parseInt(padD);
        const isBlocked = blockedDates[d];
        title.textContent = isBlocked ? 'Unblock Date' : 'Block Date';
        reason.value = isBlocked?.reason || '';
        blockBtn.style.display = isBlocked ? 'none' : '';
        unblockBtn.style.display = isBlocked ? '' : 'none';
    } else {
        input.readOnly = false;
        title.textContent = 'Block Date';
        blockBtn.style.display = '';
        unblockBtn.style.display = 'none';
        reason.value = '';
        const t = new Date();
        input.value = t.toISOString().split('T')[0];
    }
    overlay.classList.add('open');
}

function openBlockForSelected() {
    const padM = String(monthNum).padStart(2, '0');
    const padD = String(selectedDay).padStart(2, '0');
    openBlockModal(`${yearNum}-${padM}-${padD}`);
}

function closeBlockModal() {
    document.getElementById('blockModalOverlay').classList.remove('open');
}

function submitBlock() {
    const date = document.getElementById('blockDateInput').value;
    const reason = document.getElementById('blockReasonInput').value;
    if (!date) { showToast('Please select a date.', 'warning'); return; }

    fetch('../../endpoints/admin/block_date.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'block', date, reason, csrf_token: window.PS_CSRF_TOKEN ?? '' })
    })
        .then(r => r.json())
        .then(data => {
            closeBlockModal();
            if (data.success) {
                showToast("Date Blocked", "success", "Date Blocked");
                setTimeout(() => refreshCalendarView(), 600);
            } else {
                showToast(data.message, 'error', 'Error');
            }
        })
        .catch(() => showToast('Server unreachable.', 'error', 'Error'));
}

function submitUnblock() {
    const date = document.getElementById('blockDateInput').value;
    fetch('../../endpoints/admin/block_date.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'unblock', date, csrf_token: window.PS_CSRF_TOKEN ?? '' })
    })
        .then(r => r.json())
        .then(data => {
            closeBlockModal();
            if (data.success) {
                showToast("Date Unblocked", "success", "Date Unblocked");
                setTimeout(() => refreshCalendarView(), 600);
            } else {
                showToast(data.message, 'error', 'Error');
            }
        })
        .catch(() => showToast('Server unreachable.', 'error', 'Error'));
}

function exportMonthReport() {
    const rows = [['Booking ID', 'Guest', 'Unit', 'Check-in', 'Check-out', 'Status', 'Amount']];
    const allBks = Object.values(bookingsByDay).flat();
    const seen = new Set();
    allBks.forEach(b => {
        if (seen.has(b.booking_id)) return;
        seen.add(b.booking_id);
        rows.push([
            '#BK-' + String(b.booking_id).padStart(4, '0'),
            b.guest_name,
            b.unit_label,
            b.checkin_date,
            b.checkout_date,
            b.status,
            '₱' + Number(b.total_amount).toLocaleString('en-PH')
        ]);
    });

    if (rows.length === 1) {
        showToast('No bookings this month to export.', 'info', 'No Data');
        return;
    }

    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bookings-${yearNum}-${String(monthNum).padStart(2, '0')}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

const drawerPanel = document.getElementById('calDetailPanel');
const drawerOverlay = document.getElementById('drawerOverlay');

function openDrawer() {
    drawerPanel.classList.add('drawer-open');
    drawerOverlay.classList.add('visible');
    document.body.style.overflow = 'hidden';
}
function closeDrawer() {
    drawerPanel.classList.remove('drawer-open');
    drawerOverlay.classList.remove('visible');
    document.body.style.overflow = '';
}

let touchY = 0;
drawerPanel.addEventListener('touchstart', e => { touchY = e.touches[0].clientY; }, { passive: true });
drawerPanel.addEventListener('touchend', e => { if (e.changedTouches[0].clientY - touchY > 60) closeDrawer(); }, { passive: true });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDrawer(); closeBlockModal(); } });

function setFilter(el, propId) {
    document.querySelectorAll('.prop-filter-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');

    document.querySelectorAll('.cal-day-cell:not(.empty)').forEach(cell => {
        if (propId === 'all') {
            cell.style.opacity = '1';
            cell.style.pointerEvents = '';
            cell.classList.remove('dimmed');
            return;
        }
        const props = (cell.dataset.props || '').split(',').filter(Boolean).map(Number);
        const isFreeOrBlocked = cell.classList.contains('free') || cell.classList.contains('blocked');
        const hasProperty = props.includes(Number(propId));
        if (isFreeOrBlocked || hasProperty) {
            cell.style.opacity = '1';
            cell.style.pointerEvents = '';
            cell.classList.remove('dimmed');
        } else {
            cell.style.opacity = '0.25';
            cell.style.pointerEvents = 'none';
            cell.classList.add('dimmed');
        }
    });
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.getElementById('blockModalOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('blockModalOverlay')) closeBlockModal();
});

function refreshCalendarView() {
    loadMonth(yearNum, monthNum);
}