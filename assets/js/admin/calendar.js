const dayData = window.__PS_CALENDAR__.dayData;
const bookingsByDay = window.__PS_CALENDAR__.bookingsByDay;
const blockedDates = window.__PS_CALENDAR__.blockedDates;
const monthShort = window.__PS_CALENDAR__.monthShort;
const monthNum = window.__PS_CALENDAR__.monthNum;
const yearNum = window.__PS_CALENDAR__.yearNum;
const dows = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const startDow = window.__PS_CALENDAR__.startDow;
const statusMap = { booked: 'Fully Booked', partial: 'Partially Booked', free: 'Available', blocked: 'Blocked' };
let selectedDay = window.__PS_CALENDAR__.selectedDay;

function selectDay(day, el) {
    document.querySelectorAll('.cal-day-cell.selected').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedDay = day;

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

    if (window.innerWidth <= 960) openDrawer();
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

    fetch('../../api/admin/block_date.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'block', date, reason })
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
    fetch('../../api/admin/block_date.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'unblock', date })
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
    window.location.reload();
}
