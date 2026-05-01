function processAction(bookingId, type) {
    const isCi = type === 'checkin';
    const label = isCi ? 'Check In' : 'Check Out';
    const color = isCi ? '#16a34a' : '#b45309';
    const rowId = (isCi ? 'ci' : 'co') + '-row-' + bookingId;
    const btnId = (isCi ? 'ci' : 'co') + '-btn-' + bookingId;

    if (!confirm(`Mark booking #BK-${String(bookingId).padStart(4, '0')} as ${label.toLowerCase()}?`)) return;

    showToast('Processing…', 'info');
    fetch('../../api/checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ booking_id: bookingId, action: type })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Done!', 'success');
                setTimeout(() => refreshCheckinTable(), 600);
            } else {
                showToast(data.message, 'error', 'Failed');
            }
        })
        .catch(() => showToast('Server unreachable.', 'error'));
}

const selectedDate = window.__PS_CHECKIN__.selectedDate;
const ciDays = window.__PS_CHECKIN__.ciDays;
const coDays = window.__PS_CHECKIN__.coDays;
const todayStr = window.__PS_CHECKIN__.todayStr;

let calYear = window.__PS_CHECKIN__.calYear;
let calMonth = window.__PS_CHECKIN__.calMonth;

function toggleCalPicker() {
    const trigger = document.getElementById('calTrigger');
    const dropdown = document.getElementById('calDropdown');
    const isOpen = dropdown.classList.contains('open');
    if (isOpen) {
        dropdown.classList.remove('open');
        trigger.classList.remove('open');
    } else {
        renderCalDrop(calYear, calMonth);
        dropdown.classList.add('open');
        trigger.classList.add('open');
    }
}

function calNavMonth(dir) {
    calMonth += dir;
    if (calMonth < 1) { calMonth = 12; calYear--; }
    if (calMonth > 12) { calMonth = 1; calYear++; }
    fetchMonthActivity(calYear, calMonth, () => renderCalDrop(calYear, calMonth));
}

const activityCache = {};
activityCache[window.__PS_CHECKIN__.calKey] = { ci: ciDays, co: coDays };

function fetchMonthActivity(year, month, cb) {
    const key = `${year}-${month}`;
    if (activityCache[key]) { cb(); return; }
    fetch(`?ajax_activity=1&year=${year}&month=${month}`)
        .then(r => r.json())
        .then(data => {
            activityCache[key] = data;
            cb();
        })
        .catch(() => { activityCache[key] = { ci: [], co: [] }; cb(); });
}

function renderCalDrop(year, month) {
    const key = `${year}-${month}`;
    const activity = activityCache[key] || { ci: [], co: [] };
    const months = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('calDropMonth').textContent = `${months[month - 1]} ${year}`;

    const firstDow = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const grid = document.getElementById('calDropGrid');
    let html = '';

    for (let i = 0; i < firstDow; i++) {
        html += `<div class="cal-drop-day empty"></div>`;
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const padM = String(month).padStart(2, '0');
        const padD = String(d).padStart(2, '0');
        const dateStr = `${year}-${padM}-${padD}`;
        const isToday = dateStr === todayStr;
        const isSel = dateStr === selectedDate;
        const hasCi = activity.ci.includes(d);
        const hasCo = activity.co.includes(d);

        let cls = 'cal-drop-day';
        if (isToday) cls += ' today';
        if (isSel) cls += ' selected';

        let dots = '';
        if (hasCi || hasCo) {
            dots = `<div class="cal-drop-dots">
                ${hasCi ? '<span class="cal-drop-dot ci"></span>' : ''}
                ${hasCo ? '<span class="cal-drop-dot co"></span>' : ''}
            </div>`;
        }

        html += `<div class="${cls}" onclick="pickDate('${dateStr}')">${d}${dots}</div>`;
    }

    grid.innerHTML = html;
}

function pickDate(dateStr) {
    window.location = '?date=' + dateStr;
}

document.addEventListener('click', e => {
    const wrap = document.querySelector('.cal-picker-wrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('calDropdown').classList.remove('open');
        document.getElementById('calTrigger').classList.remove('open');
    }
});

// ── Real-time: live status flash on check-in/out rows ──
window.addEventListener('ps:checkin_updates', e => {
    e.detail.forEach(b => {
        const ciRow = document.getElementById('ci-row-' + b.booking_id);
        const coRow = document.getElementById('co-row-' + b.booking_id);
        [ciRow, coRow].forEach(row => {
            if (!row) return;
            row.style.transition = 'background 0.5s';
            row.style.background = 'var(--blue-50,#eff6ff)';
            setTimeout(() => { row.style.background = ''; }, 2000);
        });

        // Disable button if booking completed/cancelled
        if (['completed', 'cancelled'].includes(b.status)) {
            const ciBtn = document.getElementById('ci-btn-' + b.booking_id);
            const coBtn = document.getElementById('co-btn-' + b.booking_id);
            [ciBtn, coBtn].forEach(btn => {
                if (btn) { btn.disabled = true; btn.style.opacity = '0.4'; }
            });
        }
    });
});

// ── Extend Stay ────────────────────────────────────────
let _extendBookingId = null;

function openExtendModal(bookingId, guestName, currentCheckout) {
    _extendBookingId = bookingId;
    document.getElementById('extendGuestName').textContent = guestName + ' · #BK-' + String(bookingId).padStart(4, '0');
    const fmt = new Date(currentCheckout + 'T00:00:00').toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
    document.getElementById('extendCurrentDate').textContent = fmt;
    // Min new date = day after current checkout
    const minDate = new Date(currentCheckout + 'T00:00:00');
    minDate.setDate(minDate.getDate() + 1);
    const minStr = minDate.toISOString().split('T')[0];
    const inp = document.getElementById('extendNewDate');
    inp.min = minStr;
    inp.value = '';
    const modal = document.getElementById('extendModal');
    modal.style.removeProperty('display');
    modal.style.display = 'flex';
}

function closeExtendModal() {
    document.getElementById('extendModal').style.display = 'none';
    _extendBookingId = null;
}

function submitExtend() {
    const newDate = document.getElementById('extendNewDate').value;
    if (!newDate) { showToast('Please pick a new check-out date.', 'error'); return; }
    showToast('Saving extension…', 'info');
    fetch('../../api/extend_stay.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ booking_id: _extendBookingId, new_checkout: newDate })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Stay extended!', 'success');
                closeExtendModal();
                setTimeout(() => refreshCheckinTable(), 600);
            } else {
                showToast(data.message || 'Failed to extend stay.', 'error', 'Error');
            }
        })
        .catch(() => showToast('Server unreachable.', 'error'));
}

document.getElementById('extendModal').addEventListener('click', function (e) {
    if (e.target === this) closeExtendModal();
});

window.addEventListener('ps:booking_stats', e => {
    const s = e.detail;
    // Update stat counters if they have IDs
    ['stat-pending', 'stat-total', 'stat-confirmed', 'stat-cancelled'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const map = {
            'stat-pending': 'pending', 'stat-total': 'total',
            'stat-confirmed': 'confirmed', 'stat-cancelled': 'cancelled'
        };
        const key = map[id];
        if (key && s[key] !== undefined) el.textContent = parseInt(s[key]) || 0;
    });
});

function refreshCheckinTable() {
    window.location.href = '?date=' + selectedDate;
}

window.processAction = processAction;
window.openExtendModal = openExtendModal;
window.closeExtendModal = closeExtendModal;
window.submitExtend = submitExtend;
window.toggleCalPicker = toggleCalPicker;
window.calNavMonth = calNavMonth;
window.pickDate = pickDate