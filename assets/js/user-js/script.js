// ── AMENITY ICONS ──────────────────────────────────────────
const amenityIcons = {
    'wifi': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0114.08 0"/><path d="M1.42 9a16 16 0 0121.16 0"/><path d="M8.53 16.11a6 6 0 016.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>',
    'wi-fi': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0114.08 0"/><path d="M1.42 9a16 16 0 0121.16 0"/><path d="M8.53 16.11a6 6 0 016.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>',
    'shower': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="8 6 10 4 8 2"/><polyline points="12 6 14 4 12 2"/><polyline points="16 6 18 4 16 2"/><path d="M4 20h16"/><path d="M6 20v-6a6 6 0 0112 0v6"/></svg>',
    'water': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6 8 4 13 4 16a8 8 0 0016 0c0-3-2-8-8-14z"/></svg>',
    'rooftop': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M9 22V12h6v10"/></svg>',
    'aircon': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="8" rx="2"/><path d="M7 15l5 5 5-5M12 11v9"/></svg>',
    'tv': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="13" rx="2"/><path d="M9 21l3-3 3 3"/></svg>',
    'fridge': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="4" y1="10" x2="20" y2="10"/></svg>',
    'parking': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 010 6H9"/></svg>',
    'pool': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-5 10-5 10 5 10 5-3 5-10 5-10-5-10-5z"/><line x1="12" y1="2" x2="12" y2="5"/></svg>',
    'gym': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v16M18 4v16M3 8h4M17 8h4M3 16h4M17 16h4"/></svg>',
    'balcony': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7"/><rect x="6" y="14" width="12" height="7"/><line x1="3" y1="21" x2="21" y2="21"/></svg>',
    'breakfast': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>',
    'security': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    'kitchen': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/></svg>',
    'jacuzzi': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16M4 12c2-2 4-2 6 0s4 2 6 0M4 16c2-2 4-2 6 0s4 2 6 0M12 4v4"/></svg>',
    'concierge': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
    'garden': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="3"/><path d="M12 8v13M9 10c-2 0-5 1-5 5h16c0-4-3-5-5-5"/></svg>',
    'laundry': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M6 6h.01M9 6h.01"/></svg>',
    'safe': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="12" cy="12" r="4"/><path d="M12 8v1M12 15v1M8 12h1M15 12h1"/></svg>',
    'spa': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 00-7.35 16.76A2 2 0 006 20h12a2 2 0 001.35-.24A10 10 0 0012 2z"/><path d="M8 12s2-4 4-4 4 4 4 4"/></svg>',
    'Free WiFi': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0114.08 0"/><path d="M1.42 9a16 16 0 0121.16 0"/><path d="M8.53 16.11a6 6 0 016.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>',
    'Air Conditioning': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 15.5L12 19l4-3.5"/><path d="M12 3v6M12 19v2M3 12h6M19 12h2M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/></svg>',
    'Balcony': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7"/><rect x="6" y="14" width="12" height="7"/><line x1="3" y1="21" x2="21" y2="21"/></svg>',
    'Breakfast Included': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>',
    'Hot Shower': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="8 6 10 4 8 2"/><polyline points="12 6 14 4 12 2"/><polyline points="16 6 18 4 16 2"/><path d="M4 20h16"/><path d="M6 20v-6a6 6 0 0112 0v6"/></svg>',
    'Smart TV': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="13" rx="2"/><path d="M9 21l3-3 3 3"/></svg>',
    'Mini Fridge': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="4" y1="10" x2="20" y2="10"/></svg>',
    'In-room Safe': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="12" cy="12" r="4"/><path d="M12 8v1M12 15v1M8 12h1M15 12h1"/></svg>',
    'Private Jacuzzi': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20h16M4 12c2-2 4-2 6 0s4 2 6 0M4 16c2-2 4-2 6 0s4 2 6 0M12 4v4"/></svg>',
    'Rooftop Pool': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-5 10-5 10 5 10 5-3 5-10 5-10-5-10-5z"/><line x1="12" y1="2" x2="12" y2="5"/></svg>',
    'Spa Discount': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 00-7.35 16.76A2 2 0 006 20h12a2 2 0 001.35-.24A10 10 0 0012 2z"/><path d="M8 12s2-4 4-4 4 4 4 4"/></svg>',
    'Pool Access': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-5 10-5 10 5 10 5-3 5-10 5-10-5-10-5z"/></svg>',
};

function normalizeAmenityKey(value) {
    return String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function getAmenityIcon(name, iconSlug) {
    const rawName = String(name || '');
    const byRaw = amenityIcons[rawName];
    if (byRaw) return byRaw;
    const bySlug = iconSlug ? amenityIcons[String(iconSlug).toLowerCase()] : null;
    if (bySlug) return bySlug;
    const lowerName = rawName.toLowerCase();
    if (amenityIcons[lowerName]) return amenityIcons[lowerName];
    const normalized = normalizeAmenityKey(rawName);
    const normalizedMap = {
        wifi: amenityIcons.wifi,
        water: amenityIcons.water,
        rooftop: amenityIcons.rooftop,
        airconditioning: amenityIcons.aircon,
        airconditioner: amenityIcons.aircon,
        tv: amenityIcons.tv,
        smarttv: amenityIcons.tv,
        minibar: amenityIcons.fridge,
        fridge: amenityIcons.fridge,
        parking: amenityIcons.parking,
        pool: amenityIcons.pool,
        gym: amenityIcons.gym,
        balcony: amenityIcons.balcony,
    };
    if (normalizedMap[normalized]) return normalizedMap[normalized];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/></svg>';
}

// ── SIDEBAR ────────────────────────────────────────────────
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

// ── HEADER SCROLL ──────────────────────────────────────────
const hdr = document.getElementById('hdr');
window.addEventListener('scroll', () => hdr.classList.toggle('scrolled', scrollY > 20));

// ── HAMBURGER ──────────────────────────────────────────────
const burger = document.getElementById('hamburger');
const mob = document.getElementById('mobileNav');
let mobOpen = false;
burger.addEventListener('click', () => {
    mobOpen = !mobOpen;
    mob.classList.toggle('open', mobOpen);
    const s = burger.querySelectorAll('span');
    if (mobOpen) {
        s[0].style.transform = 'translateY(6.5px) rotate(45deg)';
        s[1].style.opacity = '0';
        s[2].style.transform = 'translateY(-6.5px) rotate(-45deg)';
    } else resetB();
});
function resetB() { burger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; }); }
function closeMob() { mobOpen = false; mob.classList.remove('open'); resetB(); }

// ── MAP HELPERS ────────────────────────────────────────────
function _destroyPdMap() {
    if (window._pdMapInstance) {
        window._pdMapInstance.remove();
        window._pdMapInstance = null;
    }
    const el = document.getElementById('pdLeafletMap');
    if (el) el.innerHTML = '';
}

function _initPdMap(lat, lng) {
    const mapEl = document.getElementById('pdLeafletMap');
    const overlay = document.getElementById('pdMapLoadingOverlay');
    const mapPanel = document.getElementById('pdMap');

    if (!mapEl) return;

    if (!lat || !lng || lat === 0 || lng === 0) {
        if (mapPanel) mapPanel.style.display = 'none';
        if (overlay) overlay.style.display = 'none';
        return;
    }

    if (mapPanel) mapPanel.style.display = '';
    if (overlay) overlay.style.display = 'flex';

    // Wait for modal transition + Leaflet to be ready
    setTimeout(function () {
        if (typeof L === 'undefined') {
            if (overlay) overlay.style.display = 'none';
            return;
        }

        _destroyPdMap();

        const map = L.map(mapEl, { scrollWheelZoom: false, zoomControl: true });
        window._pdMapInstance = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        const pinIcon = L.divIcon({
            html: '<div style="background:#2563eb;width:28px;height:28px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35);"></div>',
            className: '',
            iconSize: [28, 28],
            iconAnchor: [14, 28]
        });

        L.marker([lat, lng], { icon: pinIcon }).addTo(map);
        map.setView([lat, lng], 15);

        // invalidateSize after tiles render
        setTimeout(() => {
            map.invalidateSize();
            if (overlay) overlay.style.display = 'none';
        }, 200);

    }, 350);
}

// ── ROOM MODAL ─────────────────────────────────────────────
function openRoomModal(room) {
    window._pdRoom = room;

    // ── Gallery ──────────────────────────────────────────────
    const track = document.getElementById('pdGalleryTrack');
    const fallback = document.getElementById('modalImgFallback');
    const prevBtn = document.getElementById('pdGalleryPrev');
    const nextBtn = document.getElementById('pdGalleryNext');
    const dotsWrap = document.getElementById('pdGalleryDots');

    const images = (Array.isArray(room.images) && room.images.length)
        ? room.images
        : (room.image ? [room.image] : []);

    window._pdGalleryIdx = 0;
    window._pdGalleryImages = images;

    if (track) {
        track.innerHTML = '';
        if (images.length) {
            if (fallback) fallback.style.display = 'none';
            images.forEach(src => {
                const img = document.createElement('img');
                img.src = src;
                img.alt = room.name || 'Room image';
                img.style.cssText = 'flex-shrink:0;width:100%;height:100%;object-fit:cover;display:block;';
                img.onerror = () => { img.style.display = 'none'; };
                track.appendChild(img);
            });
            track.style.transform = 'translateX(0)';
            if (prevBtn) { prevBtn.style.display = images.length > 1 ? 'flex' : 'none'; prevBtn.style.alignItems = 'center'; prevBtn.style.justifyContent = 'center'; }
            if (nextBtn) { nextBtn.style.display = images.length > 1 ? 'flex' : 'none'; nextBtn.style.alignItems = 'center'; nextBtn.style.justifyContent = 'center'; }
            if (dotsWrap) {
                dotsWrap.innerHTML = images.length > 1
                    ? images.map((_, i) => `<span style="width:7px;height:7px;border-radius:50%;background:${i === 0 ? '#fff' : 'rgba(255,255,255,.45)'};display:inline-block;transition:background .2s;"></span>`).join('')
                    : '';
            }
        } else {
            if (fallback) { fallback.className = `pd-hero-fallback ${room.grad || 'g1'}`; fallback.style.display = 'block'; }
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
            if (dotsWrap) dotsWrap.innerHTML = '';
        }
    }

    // Hero text
    document.getElementById('modalRoomName').textContent = room.name;
    document.getElementById('modalRoomLoc').textContent = room.location;

    const badgeEl = document.getElementById('pdHeroBadge');
    if (badgeEl) badgeEl.textContent = (room.view || 'Residential');

    // Stats bar
    document.getElementById('modalRoomPrice').textContent = room.price;
    const ratingEl = document.getElementById('modalRoomRating');
    const ratingNum = Number(room.rating);
    if (ratingEl) {
        ratingEl.textContent = Number.isFinite(ratingNum) && ratingNum > 0 ? ratingNum.toFixed(1) : '—';
    }

    // Description
    document.getElementById('modalRoomDesc').textContent = room.desc || 'A comfortable and well-appointed unit.';

    // Amenity chips
    const amenDiv = document.getElementById('modalAmenities');
    const amenList = Array.isArray(room.amenities) ? room.amenities : [];
    if (amenDiv) {
        amenDiv.innerHTML = amenList.length
            ? amenList.map(a => {
                const name = (a && typeof a === 'object') ? (a.name || '') : String(a);
                return `<span class="pd-amenity-chip"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>${escHtml(name)}</span>`;
            }).join('')
            : '<span style="font-size:.8rem;color:#8aa4c0;">No amenities listed.</span>';
    }

    // Availability note + date defaults
    const noteEl = document.getElementById('pdAvailNote');
    const today = new Date(); today.setDate(today.getDate() + 1);
    const dayAfter = new Date(); dayAfter.setDate(dayAfter.getDate() + 2);
    if (noteEl) noteEl.textContent = `Unit is available from ${today.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}. Security deposit is 50% of total stay.`;

    document.getElementById('modalCheckin').value = today.toISOString().split('T')[0];
    const coEl = document.getElementById('modalGuests');
    if (coEl) {
        coEl.value = dayAfter.toISOString().split('T')[0];
        coEl.min = dayAfter.toISOString().split('T')[0];
    }
    document.getElementById('modalCheckin').onchange = function () {
        const ci = new Date(this.value + 'T12:00');
        ci.setDate(ci.getDate() + 1);
        const minCo = ci.toISOString().split('T')[0];
        if (coEl) {
            coEl.min = minCo;
            if (coEl.value <= this.value) coEl.value = minCo;
        }
        updateModalTotal();
    };

    document.getElementById('roomModal').dataset.unitId = room.id;
    document.getElementById('roomModal').dataset.pricePerNight = String(parseRoomPrice(room.price));
    updateModalTotal();

    // ── Show modal first, then init map ──────────────────────
    document.getElementById('roomModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    // Reset map panel visibility before each open
    const mapPanel = document.getElementById('pdMap');
    if (mapPanel) mapPanel.style.display = '';

    _destroyPdMap();
    _initPdMap(
        parseFloat(room.latitude || 0),
        parseFloat(room.longitude || 0)
    );

    // ── Reviews ───────────────────────────────────────────────
    window._pdReviewUnitId = room.id;
    window._pdReviewPage = 1;
    pdLoadReviews(room.id, 1);
}

// Gallery navigation
function pdGalleryNav(dir) {
    const images = window._pdGalleryImages || [];
    if (images.length < 2) return;
    let idx = (window._pdGalleryIdx + dir + images.length) % images.length;
    window._pdGalleryIdx = idx;
    const track = document.getElementById('pdGalleryTrack');
    if (track) track.style.transform = `translateX(-${idx * 100}%)`;
    const dots = document.querySelectorAll('#pdGalleryDots span');
    dots.forEach((d, i) => d.style.background = i === idx ? '#fff' : 'rgba(255,255,255,.45)');
}

// Load reviews
function pdLoadReviews(unitId, page) {
    const container = document.getElementById('pdReviews');
    const pager = document.getElementById('pdReviewsPager');
    const countEl = document.getElementById('pdReviewCount');
    if (!container) return;

    container.innerHTML = '<div id="pdReviewsLoading" style="padding:20px 0;text-align:center;color:#8aa4c0;font-size:.83rem;">Loading reviews…</div>';
    if (pager) pager.style.display = 'none';

    fetch(`../../api/user/unit_reviews.php?unit_id=${unitId}&page=${page}&limit=5`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { container.innerHTML = '<p style="color:#8aa4c0;font-size:.83rem;">Could not load reviews.</p>'; return; }
            if (countEl) countEl.textContent = data.total > 0 ? `(${data.total})` : '';
            if (!data.reviews.length) {
                container.innerHTML = '<p style="color:#8aa4c0;font-size:.83rem;padding:10px 0;">No reviews yet for this unit.</p>';
                return;
            }
            container.innerHTML = data.reviews.map(r => {
                const stars = Array.from({ length: 5 }, (_, i) =>
                    `<span style="color:${i < r.rating ? '#e8c87a' : '#d0d8e4'};">★</span>`).join('');
                return `<div class="pd-review">
                    <div class="pd-review-stars">${stars}</div>
                    <p class="pd-review-text">${escHtml(r.comment || '')}</p>
                    <div class="pd-review-author">${escHtml(r.author)} · ${escHtml(r.date)}</div>
                </div>`;
            }).join('');
            if (data.total_pages > 1 && pager) {
                pager.style.display = 'flex';
                const prevBtn = document.getElementById('pdReviewsPrev');
                const nextBtn = document.getElementById('pdReviewsNext');
                const pageLabel = document.getElementById('pdReviewsPageLabel');
                if (pageLabel) pageLabel.textContent = `Page ${data.page} of ${data.total_pages}`;
                if (prevBtn) prevBtn.disabled = data.page <= 1;
                if (nextBtn) nextBtn.disabled = data.page >= data.total_pages;
                window._pdReviewPage = data.page;
                window._pdReviewTotalPages = data.total_pages;
            }
        })
        .catch(() => { container.innerHTML = '<p style="color:#8aa4c0;font-size:.83rem;">Failed to load reviews.</p>'; });
}

function pdReviewsNav(dir) {
    const page = (window._pdReviewPage || 1) + dir;
    const total = window._pdReviewTotalPages || 1;
    if (page < 1 || page > total) return;
    pdLoadReviews(window._pdReviewUnitId, page);
}

function parseRoomPrice(priceText) {
    const n = Number(String(priceText || '').replace(/[^\d.]/g, ''));
    return Number.isFinite(n) ? n : 0;
}

function escHtml(v) {
    return String(v ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function updateModalTotal() {
    const ci = document.getElementById('modalCheckin')?.value;
    const co = document.getElementById('modalGuests')?.value;
    const totalEl = document.getElementById('modalTotal');
    if (!ci || !co || !totalEl) return;
    const pricePerNight = Number(document.getElementById('roomModal')?.dataset.pricePerNight || 0);
    if (ci && co && co > ci && pricePerNight > 0) {
        const nights = Math.round((new Date(co) - new Date(ci)) / 86400000);
        const stayTotal = pricePerNight * nights;
        const deposit = stayTotal * 0.5;
        totalEl.innerHTML = `<strong>${nights} night${nights !== 1 ? 's' : ''}</strong> × ₱${pricePerNight.toLocaleString()} = ₱${stayTotal.toLocaleString()} &nbsp;·&nbsp; <strong style="color:#1a7a4a">Deposit: ₱${deposit.toLocaleString()}</strong>`;
    } else {
        totalEl.innerHTML = '';
    }
}

function closeRoomModal() {
    document.getElementById('roomModal').classList.remove('open');
    document.body.style.overflow = '';
    _destroyPdMap();
    const mapPanel = document.getElementById('pdMap');
    if (mapPanel) mapPanel.style.display = '';
    const overlay = document.getElementById('pdMapLoadingOverlay');
    if (overlay) overlay.style.display = 'flex';
}

document.getElementById('roomModalClose').addEventListener('click', closeRoomModal);
document.getElementById('roomModal').addEventListener('click', e => {
    if (e.target === document.getElementById('roomModal')) closeRoomModal();
});

// ── PAYMENT ────────────────────────────────────────────────
const paymentMethodMeta = {
    GCash: { title: 'Pay with GCash', desc: 'Scan this QR code in GCash, then tap Confirm Payment.' },
    Maya: { title: 'Pay with Maya', desc: 'Scan this QR code in Maya, then tap Confirm Payment.' },
    Bank: { title: 'Pay via Bank Transfer', desc: 'Use your banking app to scan this QR and proceed with transfer.' },
    Cash: { title: 'Pay with Cash', desc: 'Pay in cash upon check-in at the front desk. Please prepare the exact amount if possible.' },
};
let pendingBookingPayload = null;
let selectedPaymentMethod = 'GCash';

function buildPaymentQr(method, payload) {
    const room = payload?.roomName || 'Room';
    const amount = payload?.amount || '';
    const ref = `Unit ${payload?.unitId || ''}`;
    const qrText = `PropSight ${method.toUpperCase()} | ${room} | ${amount} | ${ref}`;
    return `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrText)}`;
}

function openPaymentModal(payload) {
    pendingBookingPayload = payload;
    selectedPaymentMethod = 'GCash';
    document.querySelectorAll('#pmMethods .pm-method').forEach(el => {
        const isActive = el.dataset.method === 'GCash';
        el.classList.toggle('active', isActive);
        const input = el.querySelector('input');
        if (input) input.checked = isActive;
    });
    updatePaymentPreview();
    document.getElementById('paymentModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closePaymentModal() {
    const modal = document.getElementById('paymentModal');
    if (modal) modal.classList.remove('open');  // ← add null check
    pendingBookingPayload = null;
    document.body.style.overflow = '';
}

function updatePaymentPreview() {
    if (!pendingBookingPayload) return;
    const meta = paymentMethodMeta[selectedPaymentMethod] || paymentMethodMeta.gcash;
    document.getElementById('pmMethodTitle').textContent = meta.title;
    document.getElementById('pmMethodDesc').textContent = meta.desc;
    const qrImg = document.getElementById('pmQrImage');
    if (selectedPaymentMethod === 'Cash') {
        qrImg.style.display = 'none';
        qrImg.removeAttribute('src');
    } else {
        qrImg.style.display = '';
        qrImg.src = buildPaymentQr(selectedPaymentMethod, pendingBookingPayload);
    }
}

document.getElementById('paymentModalClose')?.addEventListener('click', closePaymentModal);
document.getElementById('paymentModal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('paymentModal')) closePaymentModal();
});
document.querySelectorAll('#pmMethods .pm-method').forEach(el => {
    el.addEventListener('click', () => {
        selectedPaymentMethod = el.dataset.method;
        document.querySelectorAll('#pmMethods .pm-method').forEach(m => m.classList.remove('active'));
        el.classList.add('active');
        updatePaymentPreview();
    });
});

// ── CONFIRM BOOKING ────────────────────────────────────────
function confirmBooking() {
    if (window.hasActiveBooking) { showToast('You already have an active booking.'); return; }
    const checkin = document.getElementById('modalCheckin').value;
    const checkout = document.getElementById('modalGuests').value;
    closeRoomModal();
    if (window._pdRoom) {
        openBookingModal(window._pdRoom);
        if (checkin) document.getElementById('bm-checkin').value = checkin;
        if (checkout) document.getElementById('bm-lease').value = checkout;
    }
}

function submitBookingAfterPayment() {
    if (!pendingBookingPayload) return;
    const { checkin, checkout, guests, roomName, unitId } = pendingBookingPayload;
    showToast('Submitting your booking request…', 'info');
    fetch('../../api/user/book_unit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            unit_id: unitId, checkin, checkout, guests,
            payment_method: selectedPaymentMethod,
            csrf_token: (typeof window.psGetCsrfToken === 'function' ? window.psGetCsrfToken() : '')
        })
    })
        .then(r => r.json())
        .then(data => {
            closePaymentModal();
            if (data.success) {
                showToast(`Booking submitted! Ref #BK-${String(data.booking_id || '').padStart(4, '0')}`, 'success', 'Booking Submitted!', 6000);
                setTimeout(() => _onBookingSuccess(data), 800);
            } else {
                showToast(data.message || 'Something went wrong. Please try again.', 'error', 'Booking Failed');
            }
        })
        .catch(() => {
            closePaymentModal();
            showToast('Could not reach the server. Please try again.', 'error', 'Connection Error');
        });
}

// ── REAL-TIME DOM HELPERS ──────────────────────────────────
function _fmtDateDash(iso) {
    if (!iso) return '—';
    const d = new Date(iso + (iso.includes('-') && iso.length === 10 ? 'T00:00:00' : ''));
    if (isNaN(d)) return iso;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function _markDashboardUnitBooked(unitId) {
    if (!unitId) return;
    const roomCard = document.querySelector(`.room-card[data-unit-id="${unitId}"]`);
    if (!roomCard) return;
    roomCard.dataset.status = 'occupied';
    const availBadge = roomCard.querySelector('[data-avail-status]');
    if (availBadge) { availBadge.className = 'room-avail avail-no'; availBadge.textContent = 'BOOKED'; }
    const bookBtn = roomCard.querySelector('[data-book-btn]');
    if (bookBtn) {
        bookBtn.disabled = true;
        bookBtn.textContent = 'Unavailable';
        bookBtn.setAttribute('aria-disabled', 'true');
        bookBtn.onclick = function (ev) { if (ev) { ev.preventDefault(); ev.stopPropagation(); } return false; };
    }
}

function _onBookingSuccess(data) {
    if (!data) return;
    const id = String(data.booking_id || '');
    window.hasActiveBooking = true;
    _markDashboardUnitBooked(data.unit_id || (_bmRoom && _bmRoom.id));

    const banner = document.getElementById('rt-active-booking-wrap');
    if (banner) {
        banner.dataset.bookingId = id;
        banner.dataset.checkin = data.checkin || '';
        banner.dataset.checkout = data.checkout || '';
        banner.style.display = '';
        banner.style.opacity = '1';
        banner.style.maxHeight = '';
        const pill = document.getElementById('rt-active-booking-status');
        if (pill) { pill.textContent = 'Pending'; pill.className = 'bb-status st-pending'; }
        const bbDates = banner.querySelector('.bb-dates');
        if (bbDates) {
            bbDates.innerHTML = `Check-in: ${_fmtDateDash(data.checkin)}<span class="bb-date-sep"> &nbsp;·&nbsp; </span>Check-out: ${_fmtDateDash(data.checkout)}`;
        }
        if (data.unit_id) banner.dataset.unitId = String(data.unit_id);
        const unitEl = banner.querySelector('.bb-room, .bb-unit, .bb-title, [data-rt="unit_name"]');
        if (unitEl) unitEl.textContent = data.unit_name || '';
    }

    const histList = document.getElementById('bookingHistoryList');
    if (histList && id) {
        const nights = parseInt(data.nights) || 1;
        const initials = (data.unit_name || 'U').charAt(0).toUpperCase();
        const newItem = document.createElement('div');
        newItem.className = 'history-item';
        newItem.dataset.bookingId = id;
        newItem.innerHTML = `
          <div class="history-thumb">${initials}</div>
          <div class="history-body">
            <div class="history-top">
              <span class="history-name">${data.unit_name || '—'}</span>
              <span class="history-status st-pending" data-field="status" data-raw-status="pending">Pending</span>
            </div>
            <div class="history-meta">
              <span data-field="checkin">${_fmtDateDash(data.checkin)}</span>
              <span class="history-sep">→</span>
              <span data-field="checkout">${_fmtDateDash(data.checkout)}</span>
              · <span data-field="nights">${nights} night${nights !== 1 ? 's' : ''}</span>
            </div>
            <div class="history-price" data-field="price">${data.total_amount || '—'}</div>
          </div>`;
        histList.prepend(newItem);
        const emptyState = histList.querySelector('.history-empty, [data-empty]');
        if (emptyState) emptyState.style.display = 'none';
    }

    document.querySelectorAll('[data-rt-stat="upcoming"], [data-rt-user="upcoming"]').forEach(el => {
        el.textContent = (parseInt(el.textContent) || 0) + 1;
    });
    const roomModal = document.getElementById('roomModal');
    if (roomModal) roomModal.classList.remove('open');

    closeBookingModal();   // handles .active/.open cleanup + body overflow
    closePaymentModal();
}

function _onBookingCancelled(bookingId, overrideUnitId) {
    if (!bookingId) return;
    const id = String(bookingId);
    const banner = document.getElementById('rt-active-booking-wrap');
    const unitId = overrideUnitId || (banner ? banner.dataset.unitId : null);

    if (banner && String(banner.dataset.bookingId) === id) {
        banner.style.transition = 'opacity 0.5s, max-height 0.7s ease';
        banner.style.overflow = 'hidden';
        banner.style.opacity = '0';
        setTimeout(() => { banner.style.maxHeight = '0'; banner.style.marginTop = '0'; banner.style.marginBottom = '0'; }, 520);
        setTimeout(() => { banner.style.display = 'none'; }, 1300);
    }

    const histItem = document.querySelector(`.history-item[data-booking-id="${id}"]`);
    if (histItem) {
        const badge = histItem.querySelector('[data-field="status"]');
        if (badge) { badge.textContent = 'Cancelled'; badge.className = 'history-status st-cancelled'; badge.dataset.rawStatus = 'cancelled'; }
    }

    // ── Reset the room card ──────────────────────────────────
    if (unitId) {
        const roomCard = document.querySelector(`.room-card[data-unit-id="${unitId}"]`);
        if (roomCard) {
            roomCard.dataset.status = 'vacant';
            const availBadge = roomCard.querySelector('[data-avail-status]');
            if (availBadge) {
                availBadge.textContent = 'AVAILABLE';
                availBadge.classList.remove('avail-no');
                availBadge.classList.add('avail-yes');
            }
            const bookBtn = roomCard.querySelector('[data-book-btn]');
            if (bookBtn) {
                bookBtn.textContent = 'Book Now';
                bookBtn.disabled = false;
                bookBtn.removeAttribute('aria-disabled');
                bookBtn.onclick = function (ev) {
                    if (ev) ev.stopPropagation();
                    try { openBookingModal(JSON.parse(roomCard.dataset.roomPayload || '{}')); } catch (err) { }
                };
            }
        }
    }
    // ────────────────────────────────────────────────────────

    document.querySelectorAll('[data-rt-stat="upcoming"], [data-rt-user="upcoming"]').forEach(el => {
        el.textContent = Math.max(0, (parseInt(el.textContent) || 1) - 1);
    });
    document.querySelectorAll('[data-rt-stat="cancelled"], [data-rt-user="cancelled"]').forEach(el => {
        el.textContent = (parseInt(el.textContent) || 0) + 1;
    });

    window.hasActiveBooking = false;

    // Safe close — modal may not exist on all pages
    if (typeof closeManageModal === 'function') {
        try { closeManageModal(); } catch (e) { }
    }
}
window._onBookingCancelled = _onBookingCancelled;

// ── FILTERS ────────────────────────────────────────────────
let currentRoomCategory = 'all';
let currentRoomSearch = '';

function applyRoomFilters() {
    const cards = document.querySelectorAll('.room-card');
    cards.forEach(card => {
        const cats = (card.dataset.cat || '').toLowerCase();
        const name = (card.dataset.name || '').toLowerCase();
        const catMatch = currentRoomCategory === 'all' || cats.includes(currentRoomCategory);
        const textMatch = !currentRoomSearch || name.includes(currentRoomSearch);
        card.classList.toggle('room-filter-hidden', !(catMatch && textMatch));
    });
    carouselState.rooms.page = 0;
    renderPage('rooms');
}
function filterRooms(cat, btn) {
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    currentRoomCategory = (cat || 'all').toLowerCase();
    applyRoomFilters();
}
function searchRooms(val) {
    currentRoomSearch = (val || '').toLowerCase().trim();
    applyRoomFilters();
}

// ── TOAST ──────────────────────────────────────────────────
function showToast(msg) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.style.opacity = '1';
    t.style.transform = 'translateX(-50%) translateY(0)';
    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transform = 'translateX(-50%) translateY(80px)';
    }, 3500);
}

// ── SCROLL REVEAL ──────────────────────────────────────────
const revObs = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('visible'); revObs.unobserve(e.target); }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -32px 0px' });
document.querySelectorAll('.reveal').forEach(el => revObs.observe(el));

// ── KEYBOARD ──────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeRoomModal(); closePaymentModal(); closeSidebar(); closeManageModal(); }
});

// ── VERIFICATION BADGE ─────────────────────────────────────
const badgeText = document.getElementById('badgeText');
const badgeDot = document.getElementById('badgeDot');
const verifyBtn = document.getElementById('verifyBtn');
function updateBadge() {
    const status = badgeText ? badgeText.textContent.trim().toLowerCase() : '';
    if (status === 'verified' || status === 'verified guest') {
        if (badgeDot) badgeDot.style.background = 'green';
        if (verifyBtn) verifyBtn.style.display = 'none';
    } else {
        if (badgeDot) badgeDot.style.background = 'red';
        if (verifyBtn) verifyBtn.style.display = 'inline-block';
    }
}
updateBadge();

// ── MANAGE STAY MODAL ──────────────────────────────────────
let currentBookingId = null;

function openManageModal(booking) {
    currentBookingId = booking.booking_id;
    window.currentBookingId = currentBookingId;

    const heroImg = document.getElementById('manageHeroImg');
    if (heroImg) heroImg.style.backgroundImage = booking.image ? `url('${booking.image}')` : 'none';

    document.getElementById('manageBookingRef').textContent = '#BK-' + String(booking.booking_id).padStart(4, '0');
    document.getElementById('manageUnitName').textContent = booking.unit_name;
    document.getElementById('manageProperty').textContent = booking.property_name;

    const st = (booking.status || '').toLowerCase().replace(' ', '');
    const pill = document.getElementById('manageStatusPill');
    pill.className = 'mm-status mm-st-' + st;
    document.getElementById('manageStatusText').textContent = booking.status;

    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    let inDate = null, outDate = null;
    try {
        inDate = new Date(booking.checkin + 'T12:00:00');
        outDate = new Date(booking.checkout + 'T12:00:00');
    } catch (e) { }

    document.getElementById('manageCheckin').textContent = booking.checkin;
    document.getElementById('manageCheckout').textContent = booking.checkout;
    document.getElementById('manageCheckinDay').textContent = inDate ? days[inDate.getDay()] : '';
    document.getElementById('manageCheckoutDay').textContent = outDate ? days[outDate.getDay()] : '';
    document.getElementById('manageNightsNum').textContent = booking.nights;
    document.getElementById('manageGuests').textContent = (booking.guests || 2) + ' guest' + ((booking.guests || 2) > 1 ? 's' : '');
    document.getElementById('manageTotal').textContent = booking.total_amount;

    const progressWrap = document.getElementById('manageProgressWrap');
    if (inDate && outDate) {
        const now = new Date();
        const total = outDate - inDate;
        const elapsed = now - inDate;
        const pct = Math.max(0, Math.min(100, Math.round((elapsed / total) * 100)));
        document.getElementById('manageProgressFill').style.width = pct + '%';
        document.getElementById('manageProgressText').textContent = pct + '%';
        progressWrap.style.display = (pct > 0 && pct < 100) ? '' : 'none';
    } else {
        progressWrap.style.display = 'none';
    }

    document.getElementById('manageCancelBtn').style.display =
        (st === 'completed' || st === 'cancelled') ? 'none' : '';

    document.getElementById('manageModal').classList.add('open');
    document.body.style.overflow = 'hidden';

    // Location map
    const mapWrap = document.getElementById('manageMapWrap');
    const lat = parseFloat(booking.latitude || 0);
    const lng = parseFloat(booking.longitude || 0);

    if (lat !== 0 && lng !== 0) {
        mapWrap.style.display = '';
        document.getElementById('manageMapAddress').textContent = booking.address || booking.property_name;
        setTimeout(function () {
            const mapEl = document.getElementById('manageMap');
            if (window._manageMapInstance) { window._manageMapInstance.remove(); window._manageMapInstance = null; mapEl.innerHTML = ''; }
            const map = L.map(mapEl, { scrollWheelZoom: false, zoomControl: true });
            window._manageMapInstance = map;
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
            const pinIcon = L.divIcon({
                html: '<div style="background:#2563eb;width:28px;height:28px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35);"></div>',
                className: '', iconSize: [28, 28], iconAnchor: [14, 28]
            });
            L.marker([lat, lng], { icon: pinIcon }).addTo(map);
            map.setView([lat, lng], 15);
            map.invalidateSize();
        }, 320);
    } else {
        mapWrap.style.display = 'none';
    }
}

function closeManageModal() {
    document.getElementById('manageModal').classList.remove('open');
    document.body.style.overflow = '';
    currentBookingId = null;
    window.currentBookingId = null;
    if (window._manageMapInstance) {
        window._manageMapInstance.remove();
        window._manageMapInstance = null;
        document.getElementById('manageMap').innerHTML = '';
    }
}

document.getElementById('manageModal').addEventListener('click', e => {
    if (e.target === document.getElementById('manageModal')) closeManageModal();
});

function cancelBooking() {
    if (!currentBookingId) return;
    if (!confirm('Are you sure you want to cancel this reservation? This cannot be undone.')) return;
    showToast('Cancelling your reservation…', 'info');
    fetch('../../api/user/cancel_booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            booking_id: currentBookingId,
            csrf_token: (typeof window.psGetCsrfToken === 'function' ? window.psGetCsrfToken() : '')
        })
    })
        .then(r => r.json())
        .then(data => {
            const cancelledBookingId = currentBookingId;
            closeManageModal();
            if (data.success) {
                showToast(data.message, 'success', 'Cancelled');
                _onBookingCancelled(cancelledBookingId);
            } else {
                showToast(data.message || 'Could not cancel booking.', 'error', 'Failed');
            }
        })
        .catch(() => showToast('Server unreachable.', 'error'));
}

function goToSupportFromManage() {
    const id = Number(currentBookingId);
    closeManageModal();
    if (Number.isFinite(id) && id > 0) { window.location.href = `support.php?booking_id=${encodeURIComponent(String(id))}`; return; }
    window.location.href = 'support.php';
}

// ── CAROUSEL ───────────────────────────────────────────────
const carouselState = {
    rooms: { page: 0, perPage: 8 },
    history: { page: 0, perPage: 5 },
};

function getRoomsPerPage() {
    const grid = document.getElementById('roomsGrid');
    if (!grid) return 8;
    const style = window.getComputedStyle(grid);
    const cols = (style.gridTemplateColumns || '').split(' ').filter(Boolean).length || 4;
    return Math.max(1, cols * 2);
}

function initCarousel(type) {
    const gridId = type === 'rooms' ? 'roomsGrid' : 'historyList';
    const grid = document.getElementById(gridId);
    if (!grid) return;
    renderPage(type);
}

function renderPage(type) {
    const state = carouselState[type];
    const gridId = type === 'rooms' ? 'roomsGrid' : 'historyList';
    const dotsId = type === 'rooms' ? 'roomsDots' : 'historyDots';
    const prevId = type === 'rooms' ? 'roomsPrev' : 'historyPrev';
    const nextId = type === 'rooms' ? 'roomsNext' : 'historyNext';
    const grid = document.getElementById(gridId);
    if (!grid) return;
    if (type === 'rooms') state.perPage = getRoomsPerPage();

    const items = Array.from(grid.children);
    const pageItems = type === 'rooms'
        ? items.filter(el => !el.classList.contains('room-filter-hidden'))
        : items;
    const total = pageItems.length;
    const pages = Math.max(1, Math.ceil(total / state.perPage));
    if (state.page >= pages) state.page = pages - 1;
    const start = state.page * state.perPage;
    const end = start + state.perPage;

    items.forEach(el => {
        if (type === 'rooms' && el.classList.contains('room-filter-hidden')) { el.style.display = 'none'; return; }
        const i = pageItems.indexOf(el);
        el.style.display = (i >= start && i < end) ? '' : 'none';
    });

    const dotsWrap = document.getElementById(dotsId);
    if (dotsWrap) {
        dotsWrap.innerHTML = '';
        for (let i = 0; i < pages; i++) {
            const dot = document.createElement('button');
            dot.className = 'carousel-dot' + (i === state.page ? ' active' : '');
            dot.onclick = () => goToPage(type, i);
            dotsWrap.appendChild(dot);
        }
        dotsWrap.style.display = total > state.perPage ? '' : 'none';
    }

    const prevBtn = document.getElementById(prevId);
    const nextBtn = document.getElementById(nextId);
    if (prevBtn) { prevBtn.disabled = state.page === 0; prevBtn.style.display = total > state.perPage ? '' : 'none'; }
    if (nextBtn) { nextBtn.disabled = state.page >= pages - 1; nextBtn.style.display = total > state.perPage ? '' : 'none'; }
}

function scrollCarousel(type, dir) {
    const state = carouselState[type];
    const gridId = type === 'rooms' ? 'roomsGrid' : 'historyList';
    const grid = document.getElementById(gridId);
    if (!grid) return;
    if (type === 'rooms') state.perPage = getRoomsPerPage();
    const items = Array.from(grid.children);
    const pageItems = type === 'rooms' ? items.filter(el => !el.classList.contains('room-filter-hidden')) : items;
    const pages = Math.max(1, Math.ceil(pageItems.length / state.perPage));
    state.page = Math.max(0, Math.min(pages - 1, state.page + dir));
    renderPage(type);
}

function goToPage(type, page) {
    carouselState[type].page = page;
    renderPage(type);
}

document.addEventListener('DOMContentLoaded', () => {
    initCarousel('rooms');
    initCarousel('history');
    document.getElementById('modalCheckin')?.addEventListener('change', updateModalTotal);
    document.getElementById('modalGuests')?.addEventListener('change', updateModalTotal);
    window.addEventListener('resize', () => { carouselState.rooms.page = 0; renderPage('rooms'); });
});

function toggleSaveRoom(unitId, btn) {
    const isSaved = btn.classList.contains('saved');
    const fd = new FormData();
    fd.append('unit_id', unitId);
    fd.append('action', isSaved ? 'unsave' : 'save');
    window.psAppendCsrf(fd);
    fetch('../../api/user/save_toggle.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.classList.toggle('saved');
                showToast(isSaved ? 'Removed from saved.' : 'Room saved!');
                document.querySelectorAll('[data-rt-user="saved_count"]').forEach(el => {
                    let c = parseInt(el.textContent) || 0;
                    el.textContent = isSaved ? Math.max(0, c - 1) : c + 1;
                });
                document.querySelectorAll('[data-rt-user="saved_count_text"]').forEach(el => {
                    let c = parseInt(el.textContent) || 0;
                    c = isSaved ? Math.max(0, c - 1) : c + 1;
                    el.textContent = c + ' on wishlist';
                });
            } else {
                showToast(data.message || 'Could not save room.');
            }
        })
        .catch(() => showToast('Network error. Please try again.'));
}

// ── MULTI-STEP BOOKING MODAL ───────────────────────────────
let _bmCurrentStep = 1;
let _bmRoom = null;
let _bmCountdownTimer = null;

function openBookingModal(room) {
    if (window.hasActiveBooking) { showToast('You already have an active booking.'); return; }
    _bmRoom = room;

    document.getElementById('bmSbName').textContent = room.name || '—';
    document.getElementById('bmSbLoc').textContent = room.location || '—';
    document.getElementById('sb-rent').textContent = room.price + ' / night';
    const dep = (room.priceNum || 0) * 0.5;
    document.getElementById('sb-deposit').textContent = '₱' + dep.toLocaleString();
    document.getElementById('sb-total').textContent = '₱' + dep.toLocaleString();

    const img = document.getElementById('bmUnitImg');
    if (img && room.image) { img.src = room.image; img.style.display = 'block'; }

    const s = window._psSessionFields || {};
    document.getElementById('bm-fname').value = s.fname || '';
    document.getElementById('bm-lname').value = s.lname || '';
    document.getElementById('bm-email').value = s.email || '';
    document.getElementById('bm-phone').value = s.phone || '';

    const today = new Date().toISOString().split('T')[0];
    document.getElementById('bm-checkin').min = today;
    document.getElementById('bm-lease').min = today;

    bmGoToStep(1);
    const bmOverlay = document.getElementById('bmOverlay');
    bmOverlay.classList.add('active');
    // tiny rAF so the translateY(100%) start state renders before we add .open
    requestAnimationFrame(() => bmOverlay.classList.add('open'));

    // Apply popular badge to the most-used payment method
    document.querySelectorAll('#bmPayMethods .bm-pay-option .bm-pay-badge').forEach(b => b.remove());
    const popular = window.PS_POPULAR_PAYMENT || 'GCash';
    const popularEl = document.querySelector(`#bmPayMethods .bm-pay-option[data-method="${popular}"]`);
    if (popularEl && !popularEl.querySelector('.bm-pay-badge')) {
        const badge = document.createElement('span');
        badge.className = 'bm-pay-badge';
        badge.textContent = 'Popular';
        popularEl.appendChild(badge);
    }
}

function closeBookingModal() {
    const bmOverlay = document.getElementById('bmOverlay');
    const bmBox = document.getElementById('bmBox');

    // Trigger the exit animation
    bmOverlay.classList.remove('open');
    bmBox.style.transform = 'translateY(40px) scale(.97)';
    bmBox.style.opacity = '0';

    // Wait for it to finish, then fully hide
    setTimeout(() => {
        bmOverlay.classList.remove('active');
        bmBox.style.transform = '';
        bmBox.style.opacity = '';
    }, 380);

    document.body.style.overflow = '';
    if (_bmCountdownTimer) { clearInterval(_bmCountdownTimer); _bmCountdownTimer = null; }
}

function bmGoToStep(step) {
    _bmCurrentStep = step;
    document.querySelectorAll('.bm-panel').forEach((p, i) => { p.classList.toggle('active', i + 1 === step); });
    for (let i = 1; i <= 4; i++) {
        const circle = document.getElementById('bm-circle-' + i);
        const stepEl = document.getElementById('bm-step-' + i);
        if (!circle || !stepEl) continue;
        if (i < step) { circle.innerHTML = '✓'; stepEl.classList.add('done'); stepEl.classList.remove('active'); }
        else if (i === step) { circle.textContent = i; stepEl.classList.add('active'); stepEl.classList.remove('done'); }
        else { circle.textContent = i; stepEl.classList.remove('active', 'done'); }
    }
    document.getElementById('bmBack').style.display = (step > 1 && step < 4) ? '' : 'none';
    document.getElementById('bmBox').classList.toggle('bm-step-final', step === 4);
    document.getElementById('bmNext').style.display = step < 3 ? '' : 'none';
    document.getElementById('bmConfirmBtn').style.display = step === 3 ? '' : 'none';
    document.getElementById('bmDoneBtn').style.display = step === 4 ? '' : 'none';
    if (step === 2) bmPopulateReview();
    if (step === 3) bmSetupPayment();
}

function bmNextStep() { if (_bmCurrentStep === 1 && !bmValidateStep1()) return; bmGoToStep(_bmCurrentStep + 1); }
function bmPrevStep() { if (_bmCurrentStep > 1) bmGoToStep(_bmCurrentStep - 1); }

function bmValidateStep1() {
    const fname = document.getElementById('bm-fname').value.trim();
    const lname = document.getElementById('bm-lname').value.trim();
    const email = document.getElementById('bm-email').value.trim();
    const phone = document.getElementById('bm-phone').value.trim();
    const checkin = document.getElementById('bm-checkin').value;
    const lease = document.getElementById('bm-lease').value;
    if (!fname || !lname) { showToast('Please enter your full name.'); return false; }
    if (!email) { showToast('Please enter your email.'); return false; }
    if (!phone) { showToast('Please enter your contact number.'); return false; }
    if (!checkin) { showToast('Please select a check-in date.'); return false; }
    if (!lease) { showToast('Please select a check-out date.'); return false; }
    if (lease <= checkin) { showToast('Check-out must be after check-in.'); return false; }
    return true;
}

function bmPopulateReview() {
    const fname = document.getElementById('bm-fname').value.trim();
    const lname = document.getElementById('bm-lname').value.trim();
    const email = document.getElementById('bm-email').value.trim();
    const phone = document.getElementById('bm-phone').value.trim();
    const checkin = document.getElementById('bm-checkin').value;
    const lease = document.getElementById('bm-lease').value;
    const nights = Math.round((new Date(lease) - new Date(checkin)) / 86400000) || 0;
    const deposit = (_bmRoom?.priceNum || 0) * nights * 0.5;

    document.getElementById('rv-name').textContent = fname + ' ' + lname;
    document.getElementById('rv-email').textContent = email;
    document.getElementById('rv-phone').textContent = phone;
    document.getElementById('rv-unit').textContent = _bmRoom?.name || '—';
    document.getElementById('rv-movein').textContent = checkin;
    document.getElementById('rv-checkout').textContent = lease;
    document.getElementById('rv-nights').textContent = nights + ' night' + (nights !== 1 ? 's' : '');
    document.getElementById('rv-rent').textContent = _bmRoom?.price || '—';
    document.getElementById('rv-deposit').textContent = '₱' + deposit.toLocaleString();
    document.getElementById('rv-total').textContent = '₱' + deposit.toLocaleString();
    document.getElementById('sb-deposit').textContent = '₱' + deposit.toLocaleString();
    document.getElementById('sb-total').textContent = '₱' + deposit.toLocaleString();
}

function bmSetupPayment() {
    const checkin = document.getElementById('bm-checkin').value;
    const lease = document.getElementById('bm-lease').value;
    const nights = Math.round((new Date(lease) - new Date(checkin)) / 86400000) || 0;
    const deposit = (_bmRoom?.priceNum || 0) * nights * 0.5;
    const amountFmt = '₱' + deposit.toLocaleString();

    document.getElementById('bmQrAmount').textContent = amountFmt;
    document.getElementById('bmCashAmount').textContent = amountFmt;

    document.querySelectorAll('#bmPayMethods .bm-pay-option').forEach(el => {
        el.onclick = () => {
            document.querySelectorAll('#bmPayMethods .bm-pay-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            selectedPaymentMethod = el.dataset.method;
            const isCash = selectedPaymentMethod === 'Cash';
            document.getElementById('bmQrBox').style.display = isCash ? 'none' : '';
            document.getElementById('bmCashBox').style.display = isCash ? '' : 'none';
            const titles = { GCash: 'Pay via GCash', Maya: 'Pay via Maya', Bank: 'Pay via Bank Transfer' };
            document.getElementById('bmQrTitle').textContent = titles[selectedPaymentMethod] || '';
        };
    });

    if (_bmCountdownTimer) clearInterval(_bmCountdownTimer);
    let secs = 30 * 60;
    const tick = () => {
        const m = String(Math.floor(secs / 60)).padStart(2, '0');
        const s = String(secs % 60).padStart(2, '0');
        const el = document.getElementById('bmCountdown');
        if (el) el.textContent = m + ':' + s;
        if (secs <= 0) clearInterval(_bmCountdownTimer);
        secs--;
    };
    tick();
    _bmCountdownTimer = setInterval(tick, 1000);
}

function bmSubmitBooking() {
    const checkin = document.getElementById('bm-checkin').value;
    const lease = document.getElementById('bm-lease').value;
    const nights = Math.round((new Date(lease) - new Date(checkin)) / 86400000) || 0;
    const deposit = (_bmRoom?.priceNum || 0) * nights * 0.5;

    showToast('Submitting your booking…');

    const fd = new FormData();
    fd.append('unit_id', _bmRoom?.id || '');
    fd.append('checkin', checkin);
    fd.append('checkout', lease);
    fd.append('guests', 2);
    fd.append('first_name', document.getElementById('bm-fname').value.trim());
    fd.append('last_name', document.getElementById('bm-lname').value.trim());
    fd.append('email', document.getElementById('bm-email').value.trim());
    fd.append('phone', document.getElementById('bm-phone').value.trim());
    fd.append('payment_method', selectedPaymentMethod);
    window.psAppendCsrf(fd);

    fetch('../../api/user/book_unit.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window._lastBmBookingData = {
                    ...data,
                    unit_id: _bmRoom?.id || data.unit_id || '',
                    unit_name: _bmRoom?.name || data.unit_name || 'Unit',
                    property_name: _bmRoom?.location || data.property_name || '',
                    checkin, checkout: lease, nights,
                    total_amount: 'PHP ' + deposit.toLocaleString()
                };
                _markDashboardUnitBooked(window._lastBmBookingData.unit_id);
                const ref = '#BK-' + String(data.booking_id || '').padStart(4, '0');
                document.getElementById('bmConfirmRef').textContent = ref;
                document.getElementById('cf-unit').textContent = _bmRoom?.name || '—';
                document.getElementById('cf-movein').textContent = checkin;
                document.getElementById('cf-checkout').textContent = lease;
                document.getElementById('cf-method').textContent = selectedPaymentMethod;
                document.getElementById('cf-total').textContent = '₱' + deposit.toLocaleString();
                bmGoToStep(4);
                window.hasActiveBooking = true;
                if (_bmCountdownTimer) { clearInterval(_bmCountdownTimer); _bmCountdownTimer = null; }
            } else {
                showToast(data.message || 'Booking failed. Please try again.');
            }
        })
        .catch(() => showToast('Network error. Please try again.'));
}

function _onBookingDoneFromDashboard() {
    if (window._lastBmBookingData && typeof _onBookingSuccess === 'function') {
        _onBookingSuccess(window._lastBmBookingData);
        window._lastBmBookingData = null;
    }
    if (window.PSRealtime && typeof window.PSRealtime.poll === 'function') {
        window.PSRealtime.poll();
    }
}

document.getElementById('bmOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('bmOverlay')) closeBookingModal();
});