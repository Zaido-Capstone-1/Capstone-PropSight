/**
 * datetime.js — PropSight UTC datetime utility
 * Loaded globally via layout_close files before any page script.
 *
 * APIs return datetimes tagged "+00:00" e.g. "2024-06-01 10:30:00+00:00"
 * psDate() normalises any format into a proper JS Date in the user's local tz.
 */

function psDate(str) {
    if (!str) return null;
    let s = String(str).trim().replace(' ', 'T');
    // If no timezone info at all, assume UTC
    if (!/Z$/.test(s) && !/[+-]\d{2}:\d{2}$/.test(s)) {
        s += 'Z';
    }
    const d = new Date(s);
    return isNaN(d.getTime()) ? null : d;
}

function psFmtDate(str, opts) {
    const d = psDate(str);
    if (!d) return '—';
    return d.toLocaleDateString(undefined, opts || { month: 'short', day: 'numeric', year: 'numeric' });
}

function psFmtTime(str) {
    const d = psDate(str);
    if (!d) return '—';
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function psFmtDateTime(str) {
    const d = psDate(str);
    if (!d) return '—';
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function psSameDay(a, b) {
    if (!a || !b) return false;
    return a.toDateString() === b.toDateString();
}
/**
 * Auto-format all elements with class="ps-date" and data-date="YYYY-MM-DD"
 * Anchors to T12:00:00 to prevent UTC midnight timezone rollback.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ps-date[data-date]').forEach(function (el) {
        const raw = el.dataset.date;
        if (!raw) return;
        // Append T12:00:00 so any timezone won't shift the date
        const d = new Date(raw + 'T12:00:00');
        if (!isNaN(d.getTime())) {
            el.textContent = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        }
    });
});