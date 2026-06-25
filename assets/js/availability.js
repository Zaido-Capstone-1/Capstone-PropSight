/* ═══════════════════════════════════════════════════════════════════════════
   availability.js  —  Landing Page Availability Section
   Path: assets/js/availability.js
   ═══════════════════════════════════════════════════════════════════════════ */

(function () {
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const DAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayIso = _iso(today.getFullYear(), today.getMonth(), today.getDate());

    // ── Calendar state ─────────────────────────────────────────────────────
    let _cal = {
        in: {
            year: today.getFullYear(),
            month: today.getMonth(),
            selected: null
        },
        out: {
            year: today.getFullYear(),
            month: today.getMonth(),
            selected: null
        }
    };

    // ── Open / close calendars ─────────────────────────────────────────────
    window.lpOpenCal = function (which) {
        const other = which === 'in' ? 'out' : 'in';
        _closeAll();
        document.getElementById('lpCal' + _cap(which)).classList.add('open');
        document.getElementById('lp' + _cap(which) + 'Trigger').classList.add('active');
        _renderCal(which);
    };

    function _closeAll() {
        ['in', 'out'].forEach(w => {
            document.getElementById('lpCal' + _cap(w)).classList.remove('open');
            document.getElementById('lp' + _cap(w) + 'Trigger').classList.remove('active');
        });
        document.getElementById('lpPropDropdown').classList.remove('open');
        document.getElementById('lpPropTrigger').classList.remove('open');
    }

    // ── Calendar navigation ────────────────────────────────────────────────
    window.lpCalNav = function (which, delta) {
        const s = _cal[which];
        s.month += delta;
        if (s.month > 11) {
            s.month = 0;
            s.year++;
        }
        if (s.month < 0) {
            s.month = 11;
            s.year--;
        }
        _renderCal(which);
    };

    // ── Render calendar grid ───────────────────────────────────────────────
    function _renderCal(which) {
        const s = _cal[which];
        const grid = document.getElementById('lpCal' + _cap(which) + 'Grid');
        const lbl = document.getElementById('lpCal' + _cap(which) + 'Label');
        lbl.textContent = MONTHS[s.month] + ' ' + s.year;
        grid.innerHTML = '';

        const firstDay = new Date(s.year, s.month, 1).getDay();
        const daysInMonth = new Date(s.year, s.month + 1, 0).getDate();
        const selIn = _cal.in.selected;
        const selOut = _cal.out.selected;

        // Empty leading cells
        for (let i = 0; i < firstDay; i++) {
            const blank = document.createElement('div');
            blank.className = 'lp-cal-cell empty';
            grid.appendChild(blank);
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const iso = _iso(s.year, s.month, d);
            const date = new Date(s.year, s.month, d);
            const isPast = date < today && iso !== todayIso;
            const isToday = iso === todayIso;
            const isSelIn = iso === selIn;
            const isSelOut = iso === selOut;
            const inRange = selIn && selOut && iso > selIn && iso < selOut;

            let cls = 'lp-cal-cell';
            if (isPast) cls += ' past';
            if (isToday) cls += ' today';
            if (isSelIn && isSelOut && selIn === selOut) cls += ' selected';
            else if (isSelIn) cls += ' range-start';
            else if (isSelOut) cls += ' range-end';
            else if (inRange) cls += ' in-range';

            const cell = document.createElement('div');
            cell.className = cls;
            cell.textContent = d;
            cell.dataset.iso = iso;

            if (!isPast) {
                cell.addEventListener('click', function () {
                    const clickedIso = this.dataset.iso;
                    if (which === 'in') {
                        _cal.in.selected = clickedIso;
                        // Clear checkout if it's before new checkin
                        if (_cal.out.selected && _cal.out.selected <= clickedIso) {
                            _cal.out.selected = null;
                            _updateDisplay('out');
                        }
                        _updateDisplay('in');
                        // Auto-open checkout calendar
                        setTimeout(() => lpOpenCal('out'), 180);
                    } else {
                        if (_cal.in.selected && clickedIso <= _cal.in.selected) {
                            // If clicked before check-in, swap roles
                            _cal.out.selected = _cal.in.selected;
                            _cal.in.selected = clickedIso;
                            _updateDisplay('in');
                            _updateDisplay('out');
                        } else {
                            _cal.out.selected = clickedIso;
                            _updateDisplay('out');
                        }
                        _closeAll();
                    }
                    _updateClearBtn();
                    _renderCal(which);
                });
            }
            grid.appendChild(cell);
        }
    }

    // ── Display update ─────────────────────────────────────────────────────
    function _updateDisplay(which) {
        const el = document.getElementById('lp' + _cap(which) + 'Display');
        const iso = _cal[which].selected;
        if (iso) {
            el.textContent = _fmtIso(iso);
            el.classList.remove('placeholder');
        } else {
            el.textContent = 'Add date';
            el.classList.add('placeholder');
        }
    }

    // ── Clear date ─────────────────────────────────────────────────────────
    window.lpClearDate = function (which) {
        _cal[which].selected = null;
        _updateDisplay(which);
        _renderCal(which);
        _updateClearBtn();
    };

    // ── Today shortcut ─────────────────────────────────────────────────────
    window.lpGoToday = function (which) {
        _cal[which].year = today.getFullYear();
        _cal[which].month = today.getMonth();
        _cal[which].selected = todayIso;
        // If setting check-out to today, make sure it's after check-in
        if (which === 'out' && _cal.in.selected && todayIso <= _cal.in.selected) {
            _cal.out.selected = null;
        } else {
            _updateDisplay(which);
        }
        // If setting check-in to today, clear check-out if it's now invalid
        if (which === 'in' && _cal.out.selected && _cal.out.selected <= todayIso) {
            _cal.out.selected = null;
            _updateDisplay('out');
        }
        _renderCal(which);
        _updateClearBtn();
    };

    // ── Property dropdown ──────────────────────────────────────────────────
    window.lpToggleProp = function () {
        const dd = document.getElementById('lpPropDropdown');
        const tr = document.getElementById('lpPropTrigger');
        const isOpen = dd.classList.contains('open');
        _closeAll();
        if (!isOpen) {
            dd.classList.add('open');
            tr.classList.add('open');
        }
    };

    window.lpSelectProp = function (id, name, el) {
        document.getElementById('lpPropertyId').value = id;
        const valEl = document.getElementById('lpPropVal');
        valEl.textContent = name;
        valEl.classList.toggle('placeholder', !id);
        document.querySelectorAll('.lp-prop-option').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('lpPropDropdown').classList.remove('open');
        document.getElementById('lpPropTrigger').classList.remove('open');
        _updateClearBtn();
    };

    // ── Clear all ──────────────────────────────────────────────────────────
    window.lpClearAll = function () {
        _cal.in.selected = null;
        _cal.out.selected = null;
        _updateDisplay('in');
        _updateDisplay('out');
        lpSelectProp('', 'All properties', document.querySelector('.lp-prop-option[data-id=""]'));
        document.getElementById('lpClearBtn').classList.remove('visible');
        document.getElementById('lpAvailResults').innerHTML = _promptHtml();
    };

    function _updateClearBtn() {
        const has = _cal.in.selected || _cal.out.selected || !!document.getElementById('lpPropertyId').value;
        document.getElementById('lpClearBtn').classList.toggle('visible', !!has);
    }

    // ── Close on outside click ─────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const inside = e.target.closest('.lp-cal-popup') ||
            e.target.closest('.lp-date-trigger') ||
            e.target.closest('.lp-prop-dropdown') ||
            e.target.closest('.lp-prop-trigger');
        if (!inside) _closeAll();
    });

    // ── Check availability ─────────────────────────────────────────────────
    window.lpCheckAvail = function () {
        const dateIn = _cal.in.selected;
        const dateOut = _cal.out.selected;
        const propertyId = document.getElementById('lpPropertyId').value;

        if (!dateIn) {
            lpOpenCal('in');
            return;
        }
        const btn = document.getElementById('lpCheckBtn');
        btn.classList.add('loading');
        btn.disabled = true;

        let url = `api/user/unit_availability.php?date=${encodeURIComponent(dateIn)}`;
        if (propertyId) url += `&property_id=${encodeURIComponent(propertyId)}`;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                btn.classList.remove('loading');
                btn.disabled = false;
                _renderResults(data.units || [], dateIn, dateOut, propertyId);
            })
            .catch(() => {
                btn.classList.remove('loading');
                btn.disabled = false;
                document.getElementById('lpAvailResults').innerHTML =
                    '<div class="lp-empty-state"><svg viewBox="0 0 24 24" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg><p>Could not load availability. Please try again.</p></div>';
            });
    };

    // ── Render results ─────────────────────────────────────────────────────
    function _renderResults(units, dateIn, dateOut, propertyId) {
        const panel = document.getElementById('lpAvailResults');
        let filtered = dateOut ?
            units.filter(u => !u.available_until || u.available_until > dateOut) :
            units;

        const propName = propertyId ?
            (document.querySelector(`.lp-prop-option[data-id="${propertyId}"]`)?.textContent?.trim()?.split('\n')[0]?.trim() || '') :
            '';
        const propTag = propName ? `<span class="lp-prop-tag"><svg viewBox="0 0 24 24" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>${_esc(propName)}</span>` : '';
        const rangeNote = dateOut ? `<span class="lp-range-note">${_fmtIso(dateIn)} → ${_fmtIso(dateOut)}</span>` : '';

        if (!units.length) {
            panel.innerHTML = `<div class="lp-results-hdr"><h3>Available on ${_fmtIso(dateIn)}</h3><span class="lp-avail-badge">0 units</span>${propTag}${rangeNote}</div><div class="lp-empty-state"><svg viewBox="0 0 24 24" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg><p>No units available on this date.</p></div>`;
            return;
        }
        if (!filtered.length && dateOut) {
            panel.innerHTML = `<div class="lp-results-hdr"><h3>Available on ${_fmtIso(dateIn)}</h3><span class="lp-avail-badge">0 for full stay</span>${propTag}${rangeNote}</div><div class="lp-empty-state"><svg viewBox="0 0 24 24" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg><p>No units free for the full stay. Try adjusting your dates.</p></div>`;
            return;
        }

        let cards = '';
        filtered.forEach(u => {
            const img = u.image_path ? u.image_path : '';
            const imgTag = img ? `<img src="${_esc(img)}" alt="${_esc(u.name)}" onerror="this.style.display='none'">` : '';
            const until = u.available_until ? `Until ${_fmtIso(u.available_until)}` : 'Indefinitely';
            cards += `<div class="lp-unit-card" onclick="openModal('signup')"><div class="lp-card-img">${imgTag}<span class="lp-card-type">${_esc((u.unit_type||'UNIT').toUpperCase())}</span><div class="lp-until-pill"><svg viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Available<span>${_esc(until)}</span></div></div><div class="lp-card-body"><div class="lp-card-name">${_esc(u.name)}</div><div class="lp-card-loc"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>${_esc(u.property_name)}${u.city?', '+_esc(u.city):''}</div><div class="lp-card-footer"><div class="lp-card-price">₱${_num(u.rent_amount)} <sub>/ night</sub></div><button class="btn-lp-view" onclick="event.stopPropagation();openModal('signup')">Book Now</button></div></div></div>`;
        });

        panel.innerHTML = `<div class="lp-results-hdr"><h3>Available on ${_fmtIso(dateIn)}</h3><span class="lp-avail-badge">${filtered.length} unit${filtered.length!==1?'s':''}</span>${propTag}${rangeNote}</div><div class="lp-units-grid">${cards}</div>`;
    }

    // ── Utility ────────────────────────────────────────────────────────────
    function _cap(s) {
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    function _iso(y, m, d) {
        return `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    }

    function _fmtIso(iso) {
        if (!iso) return '';
        const [y, m, d] = iso.split('-');
        return SHORT[parseInt(m, 10) - 1] + ' ' + parseInt(d, 10) + ', ' + y;
    }

    function _esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function _num(n) {
        return Number(n).toLocaleString('en-PH', {
            maximumFractionDigits: 0
        });
    }

    function _promptHtml() {
        return '<div class="lp-prompt-state"><svg viewBox="0 0 24 24" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><p>Select a check-in date to see available units.</p></div>';
    }

    // Init calendars
    _renderCal('in');
    _renderCal('out');
})();