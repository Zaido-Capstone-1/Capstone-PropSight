/* ═══════════════════════════════════════════════════════════════════════════
   units.js  —  Browse Units Page
   Path: assets/js/user-js/units.js
   ═══════════════════════════════════════════════════════════════════════════ */

/* ── Config injected by PHP into window.UNITS_CONFIG before this script ──── *
 *  window.UNITS_CONFIG = { priceMin: <int>, priceMax: <int> };
 * ─────────────────────────────────────────────────────────────────────────── */

(function () {
    'use strict';

    /* ── Constants ────────────────────────────────────────────────────────── */
    const CFG       = window.UNITS_CONFIG || {};
    const PRICE_MIN = CFG.priceMin ?? 0;
    const PRICE_MAX = CFG.priceMax ?? 99999;

    /* ── State ────────────────────────────────────────────────────────────── */
    let _avail     = 'all';
    let _types     = new Set();
    let _floors    = new Set();
    let _seasons   = new Set();
    let _amenities = new Set();
    let _priceMin  = PRICE_MIN;
    let _priceMax  = PRICE_MAX;

    /* ── DOM refs ─────────────────────────────────────────────────────────── */
    const $ = id => document.getElementById(id);
    const $$ = sel => Array.from(document.querySelectorAll(sel));

    /* ════════════════════════════════════════════════════════════════════════
       AVAILABILITY
    ════════════════════════════════════════════════════════════════════════ */
    function setAvail(val, btn) {
        _avail = val;
        $$('.sb-avail-btn').forEach(b => b.classList.remove('active'));
        btn?.classList.add('active');
        applyFilters();
    }
    window.setAvail = setAvail;   // called from inline HTML onclick

    /* ════════════════════════════════════════════════════════════════════════
       UNIT TYPE
    ════════════════════════════════════════════════════════════════════════ */
    function clearTypeFilters() {
        _types.clear();
        $$('.type-cb').forEach(cb => (cb.checked = false));
        applyFilters();
    }
    window.clearTypeFilters = clearTypeFilters;

    /* ════════════════════════════════════════════════════════════════════════
       FLOOR
    ════════════════════════════════════════════════════════════════════════ */
    function toggleFloor(floor, btn) {
        if (_floors.has(floor)) { _floors.delete(floor); btn.classList.remove('active'); }
        else                    { _floors.add(floor);    btn.classList.add('active'); }
        applyFilters();
    }
    function clearFloorFilters() {
        _floors.clear();
        $$('.sb-floor-btn').forEach(b => b.classList.remove('active'));
        applyFilters();
    }
    window.toggleFloor      = toggleFloor;
    window.clearFloorFilters = clearFloorFilters;

    /* ════════════════════════════════════════════════════════════════════════
       SEASON
    ════════════════════════════════════════════════════════════════════════ */
    function toggleSeason(season, btn) {
        if (_seasons.has(season)) { _seasons.delete(season); btn.classList.remove('active'); }
        else                      { _seasons.add(season);    btn.classList.add('active'); }
        applyFilters();
    }
    window.toggleSeason = toggleSeason;

    /* ════════════════════════════════════════════════════════════════════════
       AMENITIES
    ════════════════════════════════════════════════════════════════════════ */
    function clearAmenityFilters() {
        _amenities.clear();
        $$('.amenity-cb').forEach(cb => (cb.checked = false));
        applyFilters();
    }
    window.clearAmenityFilters = clearAmenityFilters;

    /* ════════════════════════════════════════════════════════════════════════
       PRICE RANGE
    ════════════════════════════════════════════════════════════════════════ */
    function onPriceRange() {
        const minEl = $('priceRangeMin');
        const maxEl = $('priceRangeMax');
        if (!minEl || !maxEl) return;
        let lo = parseInt(minEl.value), hi = parseInt(maxEl.value);
        if (lo > hi) { [lo, hi] = [hi, lo]; minEl.value = lo; maxEl.value = hi; }
        _priceMin = lo;
        _priceMax = hi;
        _updatePriceDisplay();
        _updateRangeFill();
        applyFilters();
    }

    function resetPrice() {
        const minEl = $('priceRangeMin');
        const maxEl = $('priceRangeMax');
        if (minEl) minEl.value = PRICE_MIN;
        if (maxEl) maxEl.value = PRICE_MAX;
        _priceMin = PRICE_MIN;
        _priceMax = PRICE_MAX;
        _updatePriceDisplay();
        _updateRangeFill();
        applyFilters();
    }

    function _updatePriceDisplay() {
        const minEl = $('priceMinInput');
        const maxEl = $('priceMaxInput');
        if (minEl) minEl.value = _priceMin;
        if (maxEl) maxEl.value = _priceMax;
    }

    /* Typing in the min/max price fields — live preview of the slider only,
       no filtering yet (avoids re-filtering on every half-typed number). */
    function onPriceInputLive() {
        const minInput = $('priceMinInput');
        const maxInput = $('priceMaxInput');
        const minEl    = $('priceRangeMin');
        const maxEl    = $('priceRangeMax');
        if (!minInput || !maxInput || !minEl || !maxEl) return;

        let lo = parseInt(minInput.value, 10);
        let hi = parseInt(maxInput.value, 10);
        if (isNaN(lo)) lo = PRICE_MIN;
        if (isNaN(hi)) hi = PRICE_MAX;
        lo = Math.min(Math.max(lo, PRICE_MIN), PRICE_MAX);
        hi = Math.min(Math.max(hi, PRICE_MIN), PRICE_MAX);

        minEl.value = lo;
        maxEl.value = hi;
        _priceMin = lo;
        _priceMax = hi;
        _updateRangeFill();
    }

    /* Fires on blur / change (e.g. Enter, tab away) — clamps, fixes ordering,
       syncs everything, and applies the filter. */
    function onPriceInputCommit() {
        const minInput = $('priceMinInput');
        const maxInput = $('priceMaxInput');
        if (!minInput || !maxInput) return;

        let lo = parseInt(minInput.value, 10);
        let hi = parseInt(maxInput.value, 10);
        if (isNaN(lo)) lo = PRICE_MIN;
        if (isNaN(hi)) hi = PRICE_MAX;
        lo = Math.min(Math.max(lo, PRICE_MIN), PRICE_MAX);
        hi = Math.min(Math.max(hi, PRICE_MIN), PRICE_MAX);
        if (lo > hi) { [lo, hi] = [hi, lo]; }

        _priceMin = lo;
        _priceMax = hi;

        const minEl = $('priceRangeMin');
        const maxEl = $('priceRangeMax');
        if (minEl) minEl.value = lo;
        if (maxEl) maxEl.value = hi;

        _updatePriceDisplay();
        _updateRangeFill();
        applyFilters();
    }

    function _updateRangeFill() {
        const fill = $('rangeFill');
        if (!fill) return;
        const range = PRICE_MAX - PRICE_MIN || 1;
        const left  = ((_priceMin - PRICE_MIN) / range) * 100;
        const right = ((_priceMax - PRICE_MIN) / range) * 100;
        fill.style.left  = left + '%';
        fill.style.width = (right - left) + '%';
    }

    window.onPriceRange       = onPriceRange;
    window.resetPrice         = resetPrice;
    window.onPriceInputLive   = onPriceInputLive;
    window.onPriceInputCommit = onPriceInputCommit;

    /* ════════════════════════════════════════════════════════════════════════
       MASTER FILTER FUNCTION
    ════════════════════════════════════════════════════════════════════════ */
    function applyFilters() {
        /* Collect checked types */
        _types.clear();
        $$('.type-cb:checked').forEach(cb => _types.add(cb.value));
        /* Collect checked amenities */
        _amenities.clear();
        $$('.amenity-cb:checked').forEach(cb => _amenities.add(cb.value));

        const q     = ($('unitSearch')?.value || '').toLowerCase().trim();
        const cards = $$('#unitsGrid .room-card');
        let visible = 0;

        cards.forEach(card => {
            const status = card.dataset.status   || '';
            const name   = card.dataset.name     || '';
            const rent   = parseFloat(card.dataset.rent   || 0);
            const floor  = parseInt(card.dataset.floor    || 0);
            const season = card.dataset.season   || '';
            const type   = card.dataset.type     || '';
            const amList = card.dataset.amenities
                ? card.dataset.amenities.split('||').map(s => s.toLowerCase().trim())
                : [];

            let show = true;

            /* Availability */
            if      (_avail === 'vacant') show = status === 'vacant';
            else if (_avail === 'booked') show = status !== 'vacant' && status !== 'maintenance';

            /* Price */
            if (show) show = rent >= _priceMin && rent <= _priceMax;

            /* Type — any checked must match */
            if (show && _types.size > 0) show = _types.has(type);

            /* Floor — any checked must match */
            if (show && _floors.size > 0) show = _floors.has(floor);

            /* Season */
            if (show && _seasons.size > 0) show = _seasons.has(season);

            /* Amenities — ALL checked must be present */
            if (show && _amenities.size > 0) {
                for (const am of _amenities) {
                    if (!amList.includes(am.toLowerCase())) { show = false; break; }
                }
            }

            /* Search */
            if (show && q) show = name.includes(q);

            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const countEl = $('unitsCountNum');
        if (countEl) countEl.textContent = visible;

        const fb = $('unitsEmptyFallback');
        if (fb) fb.style.display = visible === 0 ? '' : 'none';

        _renderActiveTags();
        _updateClearBtn();
        _updateMobileBadge();
    }
    window.applyFilters = applyFilters;

    /* ════════════════════════════════════════════════════════════════════════
       ACTIVE TAG CHIPS
    ════════════════════════════════════════════════════════════════════════ */
    function _renderActiveTags() {
        // Active filter tag chips are hidden on this page.
        const wrap = $('activeTagsWrap');
        if (wrap) wrap.innerHTML = '';
    }

    function _updateClearBtn() {
        const hasAny = _avail !== 'all' || _types.size || _floors.size || _seasons.size
                       || _amenities.size || _priceMin > PRICE_MIN || _priceMax < PRICE_MAX;
        $('sbClearBtn')?.classList.toggle('visible', !!hasAny);
    }

    function _updateMobileBadge() {
        const n = (_avail !== 'all' ? 1 : 0)
                + _types.size + _floors.size + _seasons.size + _amenities.size
                + (_priceMin > PRICE_MIN || _priceMax < PRICE_MAX ? 1 : 0);
        const badge = $('mobileFilterCount');
        if (badge) {
            badge.textContent = n;
            badge.style.display = n > 0 ? 'inline-flex' : 'none';
        }
    }

    /* ════════════════════════════════════════════════════════════════════════
       CLEAR ALL
    ════════════════════════════════════════════════════════════════════════ */
    function clearAllFilters() {
        _avail = 'all';
        $$('.sb-avail-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.sb-avail-btn[data-avail="all"]')?.classList.add('active');

        clearTypeFilters();
        clearFloorFilters();

        _seasons.clear();
        $$('.sb-season-btn').forEach(b => b.classList.remove('active'));

        clearAmenityFilters();
        resetPrice();

        const si = $('unitSearch');
        if (si) si.value = '';

        applyFilters();
    }
    window.clearAllFilters = clearAllFilters;

    /* ════════════════════════════════════════════════════════════════════════
       SORT
    ════════════════════════════════════════════════════════════════════════ */
    function applySort(val) {
        const grid  = $('unitsGrid');
        if (!grid) return;
        const cards = $$('#unitsGrid .room-card');
        cards.sort((a, b) => {
            if (val === 'price-asc')   return parseFloat(a.dataset.rent)   - parseFloat(b.dataset.rent);
            if (val === 'price-desc')  return parseFloat(b.dataset.rent)   - parseFloat(a.dataset.rent);
            if (val === 'name-asc')    return (a.dataset.name || '').localeCompare(b.dataset.name || '');
            if (val === 'rating-desc') return parseFloat(b.dataset.rating  || 0) - parseFloat(a.dataset.rating || 0);
            return 0;
        });
        cards.forEach(c => grid.appendChild(c));
        const fb = $('unitsEmptyFallback');
        if (fb) grid.appendChild(fb);
    }
    window.applySort = applySort;

    /* ════════════════════════════════════════════════════════════════════════
       SORT DROPDOWN (custom-built, replaces native <select>)
    ════════════════════════════════════════════════════════════════════════ */
    function toggleSortDropdown() {
        const dd = $('sortDropdown');
        if (!dd) return;
        const willOpen = !dd.classList.contains('open');
        dd.classList.toggle('open', willOpen);
        $('sortTrigger')?.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    }
    window.toggleSortDropdown = toggleSortDropdown;

    function closeSortDropdown() {
        const dd = $('sortDropdown');
        if (!dd) return;
        dd.classList.remove('open');
        $('sortTrigger')?.setAttribute('aria-expanded', 'false');
    }

    function selectSortOption(el) {
        const dd = $('sortDropdown');
        if (!dd) return;

        $$('.u-sort-option').forEach(opt => {
            opt.classList.toggle('active', opt === el);
            opt.setAttribute('aria-selected', opt === el ? 'true' : 'false');
        });

        const label = $('sortTriggerLabel');
        if (label) label.textContent = el.textContent.trim();

        closeSortDropdown();
        applySort(el.dataset.value);
    }
    window.selectSortOption = selectSortOption;

    document.addEventListener('click', (e) => {
        const dd = $('sortDropdown');
        if (dd && dd.classList.contains('open') && !dd.contains(e.target)) {
            closeSortDropdown();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSortDropdown();
    });

    /* ════════════════════════════════════════════════════════════════════════
       VIEW TOGGLE
    ════════════════════════════════════════════════════════════════════════ */
    function setView(view) {
        $('viewGrid')?.classList.toggle('active', view === 'grid');
        $('viewList')?.classList.toggle('active', view === 'list');
        $('unitsGrid')?.classList.toggle('view-list', view === 'list');
    }
    window.setView = setView;

    /* ════════════════════════════════════════════════════════════════════════
       MOBILE SIDEBAR DRAWER
    ════════════════════════════════════════════════════════════════════════ */
    function openSidebar() {
        $('unitsSidebar')?.classList.add('open');
        $('sidebarBackdrop')?.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        $('unitsSidebar')?.classList.remove('open');
        $('sidebarBackdrop')?.classList.remove('open');
        document.body.style.overflow = '';
    }
    window.openSidebar  = openSidebar;
    window.closeSidebar = closeSidebar;

    /* ════════════════════════════════════════════════════════════════════════
       SAVE / HEART TOGGLE
    ════════════════════════════════════════════════════════════════════════ */
    function toggleSaveRoom(unitId, btn) {
        const isSaved = btn.classList.contains('saved');
        btn.classList.add('saving');
        const fd = new FormData();
        fd.append('unit_id', unitId);
        fd.append('action', isSaved ? 'unsave' : 'save');
        if (window.psAppendCsrf) window.psAppendCsrf(fd);
        fetch('../../endpoints/user/save_toggle.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                btn.classList.remove('saving');
                if (d.success) {
                    btn.classList.toggle('saved', !!d.saved);
                    btn.setAttribute('aria-label', d.saved ? 'Remove from saved' : 'Save room');
                    // Update all saved count badges
                    if (d.saved_count !== undefined) {
                        const count = parseInt(d.saved_count, 10);
                        document.querySelectorAll('[data-rt-user="saved_count"]').forEach(el => {
                            if (count > 0) { el.textContent = String(count); el.style.display = ''; }
                            else { el.textContent = ''; el.style.display = 'none'; }
                        });
                        document.querySelectorAll('[data-rt-user="saved_count_text"]').forEach(el => {
                            el.textContent = count + ' on wishlist';
                        });
                    }
                } else {
                    window.showToast?.(d.message || 'Could not update saved status.', 'error');
                }
            })
            .catch(() => {
                btn.classList.remove('saving');
                window.showToast?.('Network error. Please try again.', 'error');
            });
    }
    window.toggleSaveRoom = toggleSaveRoom;

    /* ════════════════════════════════════════════════════════════════════════
       HELPERS
    ════════════════════════════════════════════════════════════════════════ */
    function _esc(str) {
        return String(str ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ════════════════════════════════════════════════════════════════════════
       INIT
    ════════════════════════════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', () => {
        /* Price slider initial fill */
        _updateRangeFill();

        /* Mobile hamburger */
        document.getElementById('hamburger')?.addEventListener('click', function () {
            this.classList.toggle('open');
        });
    });

})();