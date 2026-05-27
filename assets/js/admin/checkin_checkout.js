function processAction(bookingId, type) {
    const isCi = type === 'checkin';
    const label = isCi ? 'Check In' : 'Check Out';
    const rowId = (isCi ? 'ci' : 'co') + '-row-' + bookingId;
    const btnId = (isCi ? 'ci' : 'co') + '-btn-' + bookingId;

    // populate modal
    document.getElementById('cicoModalTitle').textContent = label + ' Guest';
    document.getElementById('cicoModalBody').textContent =
        `Mark booking #BK-${String(bookingId).padStart(4, '0')} as ${label.toLowerCase()}?`;
    const confirmBtn = document.getElementById('cicoModalConfirm');
    confirmBtn.textContent = 'Yes, ' + label;
    confirmBtn.className = 'cico-modal-btn cico-btn-confirm ' + (isCi ? 'cico-green' : 'cico-amber');

    // wire confirm click
    confirmBtn.onclick = function () {
        closeCicoModal();
        fetch('../../api/checkin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    booking_id: bookingId,
                    action: type,
                    csrf_token: window.__PS_CHECKIN__?.csrfToken ?? '',
                })
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
    };

    document.getElementById('cicoModal').style.display = 'flex';
}

function closeCicoModal() {
    document.getElementById('cicoModal').style.display = 'none';
}

// close on backdrop click
document.getElementById('cicoModal').addEventListener('click', function (e) {
    if (e.target === this) closeCicoModal();
});

window.closeCicoModal = closeCicoModal;

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
    if (calMonth < 1) {
        calMonth = 12;
        calYear--;
    }
    if (calMonth > 12) {
        calMonth = 1;
        calYear++;
    }
    fetchMonthActivity(calYear, calMonth, () => renderCalDrop(calYear, calMonth));
}

const activityCache = {};
activityCache[window.__PS_CHECKIN__.calKey] = {
    ci: ciDays,
    co: coDays
};

function fetchMonthActivity(year, month, cb) {
    const key = `${year}-${month}`;
    if (activityCache[key]) {
        cb();
        return;
    }
    fetch(`?ajax_activity=1&year=${year}&month=${month}`)
        .then(r => r.json())
        .then(data => {
            activityCache[key] = data;
            cb();
        })
        .catch(() => {
            activityCache[key] = {
                ci: [],
                co: []
            };
            cb();
        });
}

function renderCalDrop(year, month) {
    const key = `${year}-${month}`;
    const activity = activityCache[key] || {
        ci: [],
        co: []
    };
    const months = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
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
            setTimeout(() => {
                row.style.background = '';
            }, 2000);
        });

        // Disable button if booking completed/cancelled
        if (['completed', 'cancelled'].includes(b.status)) {
            const ciBtn = document.getElementById('ci-btn-' + b.booking_id);
            const coBtn = document.getElementById('co-btn-' + b.booking_id);
            [ciBtn, coBtn].forEach(btn => {
                if (btn) {
                    btn.disabled = true;
                    btn.style.opacity = '0.4';
                }
            });
        }
    });
});

// ── Extend Stay ────────────────────────────────────────
let _extendBookingId = null;

function openExtendModal(bookingId, guestName, currentCheckout) {
    _extendBookingId = bookingId;
    document.getElementById('extendGuestName').textContent = guestName + ' · #BK-' + String(bookingId).padStart(4, '0');
    const fmt = new Date(currentCheckout + 'T00:00:00').toLocaleDateString('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
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
    if (!newDate) {
        showToast('Please pick a new check-out date.', 'error');
        return;
    }
    fetch('../../api/extend_stay.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                booking_id: _extendBookingId,
                new_checkout: newDate,
                csrf_token: window.__PS_CHECKIN__?.csrfToken ?? ''
            })
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
            'stat-pending': 'pending',
            'stat-total': 'total',
            'stat-confirmed': 'confirmed',
            'stat-cancelled': 'cancelled'
        };
        const key = map[id];
        if (key && s[key] !== undefined) el.textContent = parseInt(s[key]) || 0;
    });
});

function refreshCheckinTable() {
    window.location.href = '?date=' + selectedDate;
}

function openVoucherModal() {
    document.getElementById('voucherModal').style.display = 'flex';
    document.getElementById('voucherLookupInput').value = '';
    document.getElementById('voucherLookupResult').style.display = 'none';
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('voucherLookupInput').focus(), 100);
}

function closeVoucherModal() {
    document.getElementById('voucherModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close on backdrop click
document.getElementById('voucherModal').addEventListener('click', function (e) {
    if (e.target === this) closeVoucherModal();
});

// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeVoucherModal();
});

function lookupVoucher() {
    const input = document.getElementById('voucherLookupInput');
    const code = input.value.trim().toUpperCase();
    const resultEl = document.getElementById('voucherLookupResult');
    if (!code) return;

    resultEl.style.display = 'block';
    resultEl.innerHTML = '<p style="color:#8a94a6;font-size:.82rem;">Looking up…</p>';

    const fd = new FormData();
    fd.append('action', 'check');
    fd.append('voucher_code', code);

    fetch('../../api/admin/mark_voucher_used.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                const v = data.voucher;
                resultEl.innerHTML = `<div style="padding:12px 16px;background:#fff5f5;border:1px solid #fecaca;border-radius:8px;font-size:.82rem;color:#c62828;">
                    <strong>✗ ${data.message}</strong>
                    ${v ? `<div style="margin-top:6px;color:#6b7280;">Guest: <strong>${v.first_name} ${v.last_name}</strong> &nbsp;·&nbsp; ${v.reward_name}</div>` : ''}
                </div>`;
                return;
            }

            const v = data.voucher;
            const date = new Date(v.created_at).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
            resultEl.innerHTML = `<div style="padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;">
                <div style="font-weight:700;color:#15803d;margin-bottom:10px;font-size:.88rem;">✓ Valid Voucher</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;color:#374151;">
                    <div><div style="color:#8a94a6;font-size:.72rem;margin-bottom:2px;">GUEST</div><strong>${v.first_name} ${v.last_name}</strong></div>
                    <div><div style="color:#8a94a6;font-size:.72rem;margin-bottom:2px;">EMAIL</div><strong>${v.email}</strong></div>
                    <div><div style="color:#8a94a6;font-size:.72rem;margin-bottom:2px;">REWARD</div><strong>${v.reward_name}</strong></div>
                    <div><div style="color:#8a94a6;font-size:.72rem;margin-bottom:2px;">POINTS USED</div><strong>${Number(v.points_used).toLocaleString()} pts</strong></div>
                    <div><div style="color:#8a94a6;font-size:.72rem;margin-bottom:2px;">REDEEMED ON</div><strong>${date}</strong></div>
                </div>
                <div style="margin-top:14px;padding-top:12px;border-top:1px solid #bbf7d0;display:flex;justify-content:flex-end;">
                    <button onclick="useVoucher('${v.voucher_code ?? code}')"
                        id="markUsedBtn"
                        style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:7px;border:none;background:#1e2533;color:#fff;font-size:.82rem;font-weight:600;cursor:pointer;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:13px;height:13px;">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Mark as Used
                    </button>
                </div>
            </div>`;
        })
        .catch(() => {
            resultEl.innerHTML = '<p style="color:#c62828;font-size:.82rem;">Network error. Please try again.</p>';
        });
}

function useVoucher(code) {
    const btn = document.getElementById('markUsedBtn');
    btn.disabled = true;
    btn.innerHTML = 'Processing…';

    const fd = new FormData();
    fd.append('action', 'use');
    fd.append('voucher_code', code);

    fetch('../../api/admin/mark_voucher_used.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            const resultEl = document.getElementById('voucherLookupResult');
            if (!data.success) {
                resultEl.innerHTML = `<div style="padding:12px 16px;background:#fff5f5;border:1px solid #fecaca;border-radius:8px;font-size:.82rem;color:#c62828;">
                    <strong>✗ ${data.message}</strong>
                </div>`;
                return;
            }
            const rewardInstructions = {
                'Free Night Stay': 'Create a new booking for the guest with total amount set to ₱0, or waive the payment in the billing panel.',
                'Room Upgrade': 'Go to Reservations and reassign the guest to the next room tier before check-in.',
                'Free Breakfast': "Add a 'Free Breakfast' note to the guest's booking and inform kitchen/front desk staff.",
                'Late Check-out': 'Go to Check-in/Check-out and use the Extend Stay option to set checkout to 2:00 PM.',
                'Spa Voucher': 'Inform the guest to present this voucher code to the partner spa. No further action needed.',
                'Airport Transfer': 'Coordinate with the transport partner and inform the guest of pickup details.',
            };

            const v = data.voucher;
            resultEl.innerHTML = `<div style="padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;">
                <div style="font-weight:700;color:#15803d;font-size:.88rem;margin-bottom:8px;">✓ Voucher successfully marked as used</div>
                <div style="color:#374151;margin-bottom:10px;">
                    <strong>${v.first_name} ${v.last_name}</strong> &nbsp;·&nbsp; ${v.reward_name} &nbsp;·&nbsp; ${Number(v.points_used).toLocaleString()} pts
                </div>
                <div style="padding:10px 12px;background:#fff;border:1px solid #bbf7d0;border-radius:6px;color:#374151;">
                    <div style="font-size:.7rem;font-weight:700;color:#8a94a6;margin-bottom:4px;letter-spacing:.06em;">NEXT STEP</div>
                    ${rewardInstructions[v.reward_name] || "Apply the reward manually based on the guest's request."}
                </div>
            </div>`;
            document.getElementById('voucherLookupInput').value = '';
        })
        .catch(() => {
            document.getElementById('voucherLookupResult').innerHTML =
                '<p style="color:#c62828;font-size:.82rem;">Network error. Please try again.</p>';
        });
}

window.processAction = processAction;
window.openExtendModal = openExtendModal;
window.closeExtendModal = closeExtendModal;
window.submitExtend = submitExtend;
window.toggleCalPicker = toggleCalPicker;
window.calNavMonth = calNavMonth;
window.pickDate = pickDate