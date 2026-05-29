const propertiesList = window.__UNITS_DATA__.propertiesList;

function badgeOf(s) {
  return { occupied: 'Occupied', vacant: 'Vacant', maintenance: 'Maintenance' }[s] || s;
}
function statusPillHtml(s) {
  return `<span class="status-pill ${s}">${badgeOf(s)}</span>`;
}
function animateStat(el, val) {
  if (!el) return;
  el.style.transition = 'opacity .2s';
  el.style.opacity = '0';
  setTimeout(() => { el.textContent = val; el.style.opacity = '1'; }, 200);
}

async function refreshStats() {
  try {
    const data = await fetch('../../api/admin/get_unit_stats.php').then(r => r.json());
    if (data.status !== 'success') return;
    animateStat(document.getElementById('stat-total'), data.stats.total);
    animateStat(document.getElementById('stat-occupied'), data.stats.occupied);
    animateStat(document.getElementById('stat-vacant'), data.stats.vacant);
    animateStat(document.getElementById('stat-maintenance'), data.stats.maintenance);
  } catch (e) { console.error(e); }
}

function applyFilters() {
  const search = document.getElementById('search-units').value.toLowerCase().trim();
  const status = document.getElementById('filter-status').value;
  const property = document.getElementById('filter-property').value;
  const cards = document.querySelectorAll('#units-grid .unit-listing-card');

  let visible = 0;
  cards.forEach(card => {
    const ok =
      (!search || (card.dataset.search || '').includes(search)) &&
      (!status || card.dataset.status === status) &&
      (!property || String(card.dataset.propertyId) === String(property));
    card.style.display = ok ? '' : 'none';
    if (ok) visible++;
  });

  const countEl = document.getElementById('units-count');
  if (countEl && cards.length) {
    countEl.textContent = `Showing ${visible} of ${cards.length} unit${cards.length !== 1 ? 's' : ''}`;
  }

  const grid = document.getElementById('units-grid');
  let fEmpty = document.getElementById('filter-empty-row');
  if (visible === 0 && cards.length > 0) {
    if (!fEmpty) {
      fEmpty = document.createElement('div');
      fEmpty.id = 'filter-empty-row';
      fEmpty.style.cssText = 'grid-column:1/-1;text-align:center;padding:64px;color:var(--text-soft);';
      fEmpty.innerHTML = `
        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
          style="width:38px;height:38px;margin:0 auto 12px;display:block;opacity:.25;">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <div style="font-size:15px;font-weight:600;margin-bottom:4px;">No results</div>
        <div style="font-size:13px;opacity:.7;">Try adjusting your filters.</div>`;
      grid.appendChild(fEmpty);
    }
  } else {
    fEmpty?.remove();
  }
}

document.getElementById('search-units').addEventListener('input', applyFilters);
applyFilters();

function toggleUrDrop(wrapId) {
  const wrap = document.getElementById(wrapId);
  const menu = wrap.querySelector('.ur-drop-menu');
  const isOpen = menu.style.display !== 'none';
  document.querySelectorAll('.ur-drop-wrap').forEach(w => {
    w.querySelector('.ur-drop-menu').style.display = 'none';
    w.classList.remove('open');
  });
  if (!isOpen) {
    menu.style.display = 'block';
    wrap.classList.add('open');
  }
}

function selectUrDrop(wrapId, labelId, inputId, btn) {
  document.getElementById(labelId).textContent = btn.textContent.trim();
  document.getElementById(inputId).value = btn.dataset.value;
  const wrap = document.getElementById(wrapId);
  wrap.querySelectorAll('.ur-drop-opt').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  wrap.querySelector('.ur-drop-menu').style.display = 'none';
  wrap.classList.remove('open');
  applyFilters();
}

document.addEventListener('click', function (e) {
  document.querySelectorAll('.ur-drop-wrap').forEach(wrap => {
    if (!wrap.contains(e.target)) {
      wrap.querySelector('.ur-drop-menu').style.display = 'none';
      wrap.classList.remove('open');
    }
  });
});

async function fetchPropertyAmenities(propertyId) {
  if (!propertyId) return [];
  try {
    const data = await fetch(
      `../../api/admin/get_property_amenities.php?property_id=${propertyId}`
    ).then(r => r.json());
    return data.status === 'success' ? data.amenities : [];
  } catch (e) {
    console.error('Amenity fetch error', e);
    return [];
  }
}

async function fetchUnitAmenities(unitId) {
  if (!unitId) return [];
  try {
    const data = await fetch(
      `../../api/admin/get_unit_amenities.php?unit_id=${unitId}`
    ).then(r => r.json());
    return data.status === 'success' ? data.amenities : [];
  } catch (e) {
    console.error('Unit amenity fetch error', e);
    return [];
  }
}

const ICON_MAP = {
  water:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6 9 4 13 4 16a8 8 0 0 0 16 0c0-3-2-7-8-14z"/></svg>',
  wifi:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor"/></svg>',
  parking:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>',
  rooftop:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>',
  gym:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v16M18 4v16M6 12h12M3 8h3M18 8h3M3 16h3M18 16h3"/></svg>',
  pool:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 20c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><path d="M2 16c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><circle cx="12" cy="7" r="3"/><path d="M12 10v4"/></svg>',
  elevator:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 9l3-3 3 3M9 15l3 3 3-3"/></svg>',
  security:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
  cctv:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
  garden:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22V12"/><path d="M5 12c0-4 3-7 7-7s7 3 7 7"/><path d="M5 17c0-3 3-5 7-5s7 2 7 5"/></svg>',
  laundry:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 6h.01M11 6h.01M14 6h.01"/></svg>',
  balcony:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 20h18M3 12h18M3 12V8l9-4 9 4v4M7 12v8M12 12v8M17 12v8"/></svg>',
  aircon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>',
  ac:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>',
  generator:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
  storage:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2M8 7V5a2 2 0 0 0-4 0v2M12 12v4M10 14h4"/></svg>',
  concierge:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20a6 6 0 0 0-12 0"/><circle cx="12" cy="10" r="4"/><path d="M2 20h20"/></svg>',
  kitchen:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v4M8 11v9M16 3v9M16 16v2"/><circle cx="16" cy="14" r="2"/><path d="M4 3h3M4 7h3"/></svg>',
  aircon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>',
};

function getIcon(iconStr) {
  if (!iconStr) return '';
  return ICON_MAP[iconStr.toLowerCase().trim()] || '';
}

function renderAmenityPicker(container, amenities) {
  if (!amenities.length) {
    container.innerHTML = '<div style="display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:8px;background:#f8fafc;border:1.5px dashed #cbd5e1;"><svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:16px;height:16px;color:#94a3b8;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span style="font-size:12px;color:#94a3b8;">No amenities available for this property.</span></div>';
    return;
  }
  let html = '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
  amenities.forEach(function (a) {
    const svg = getIcon(a.icon);
    html += '<label data-amenity-label style="display:inline-flex;align-items:center;gap:7px;padding:7px 13px 7px 10px;border-radius:99px;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;font-size:12.5px;font-weight:500;color:#475569;transition:border-color .15s,background .15s,color .15s,box-shadow .15s;user-select:none;white-space:nowrap;">'
      + '<input type="checkbox" name="amenity_ids[]" value="' + a.amenity_id + '" style="display:none;">'
      + '<span data-cb-dot style="width:15px;height:15px;border-radius:50%;border:1.5px solid #cbd5e1;background:#f1f5f9;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,border-color .15s;">'
      + '<svg fill="none" stroke="#fff" stroke-width="3" viewBox="0 0 12 12" style="width:8px;height:8px;opacity:0;transition:opacity .12s;"><polyline points="2 6 5 9 10 3"/></svg>'
      + '</span>'
      + (svg ? '<span style="display:inline-flex;align-items:center;width:14px;height:14px;flex-shrink:0;opacity:.75;">' + svg + '</span>' : '')
      + '<span>' + a.name + '</span>'
      + '</label>';
  });
  html += '</div>';
  container.innerHTML = html;

  container.querySelectorAll('label[data-amenity-label]').forEach(function (lbl) {
    const cb = lbl.querySelector('input[type="checkbox"]');
    const dot = lbl.querySelector('[data-cb-dot]');
    const chk = dot.querySelector('svg');
    lbl.addEventListener('click', function (e) {
      e.preventDefault();
      cb.checked = !cb.checked;
      if (cb.checked) {
        lbl.style.borderColor = 'var(--primary,#6366f1)';
        lbl.style.background = '#eef2ff';
        lbl.style.color = '#4338ca';
        lbl.style.boxShadow = '0 0 0 3px rgba(99,102,241,.12)';
        dot.style.background = 'var(--primary,#6366f1)';
        dot.style.borderColor = 'var(--primary,#6366f1)';
        chk.style.opacity = '1';
      } else {
        lbl.style.borderColor = '#e2e8f0';
        lbl.style.background = '#fff';
        lbl.style.color = '#475569';
        lbl.style.boxShadow = 'none';
        dot.style.background = '#f1f5f9';
        dot.style.borderColor = '#cbd5e1';
        chk.style.opacity = '0';
      }
    });
  });
}

function getCheckedAmenityIds(bd) {
  return [...bd.querySelectorAll('input[name="amenity_ids[]"]:checked')]
    .map(cb => parseInt(cb.value, 10))
    .filter(Boolean);
}

// ── Status config ───────────────────────────────────────────────────────────
const STATUS_CONFIG = {
  occupied:    { color: '#16a34a', bg: '#dcfce7', border: '#86efac', label: 'Occupied',    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:11px;height:11px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' },
  vacant:      { color: '#2563eb', bg: '#dbeafe', border: '#93c5fd', label: 'Vacant',      icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:11px;height:11px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>' },
  maintenance: { color: '#dc2626', bg: '#fee2e2', border: '#fca5a5', label: 'Maintenance', icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:11px;height:11px;"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>' },
};

// ═══════════════════════════════════════════════════════════════════════════
// VIEW MODAL – improved layout
// ═══════════════════════════════════════════════════════════════════════════
async function openViewModal(unit) {
  const status = (unit.status || 'vacant').toLowerCase();
  const images = unit.images || [];
  const sc = STATUS_CONFIG[status] || STATUS_CONFIG.vacant;
  const initials = unit.tenant_name
    ? unit.tenant_name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase()
    : null;

  const INP = 'width:100%;box-sizing:border-box;font-size:13px;padding:9px 12px;border:1.5px solid var(--border,#e2e8f0);border-radius:9px;font-family:inherit;color:var(--text,#1e293b);background:var(--input-bg,#fff);line-height:1.5;outline:none;transition:border-color .15s,box-shadow .15s;';
  const LBL = 'display:block;font-size:10px;font-weight:700;letter-spacing:.7px;text-transform:uppercase;color:var(--text-soft,#64748b);margin-bottom:5px;';

  // ── Gallery ──────────────────────────────────────────────────────────────
  const galleryHtml = images.length
    ? `<div id="vm-gallery" style="position:relative;height:280px;background:#0f172a;overflow:hidden;flex-shrink:0;">
        <img id="vm-main-img" src="${(window.APP_BASE||'')}/${images[0]}"
          style="width:100%;height:100%;object-fit:cover;display:block;transition:opacity .25s;">

        ${images.length > 1 ? `
          <!-- Prev/Next arrows -->
          <button id="vm-prev" onclick="vmGalleryNav(-1)" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:34px;height:34px;border-radius:50%;background:rgba(0,0,0,.45);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);transition:background .15s;">
            <svg fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 24 24" style="width:15px;height:15px;"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <button id="vm-next" onclick="vmGalleryNav(1)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);width:34px;height:34px;border-radius:50%;background:rgba(0,0,0,.45);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);transition:background .15s;">
            <svg fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 24 24" style="width:15px;height:15px;"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
          <!-- Dot indicators -->
          <div style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:5px;align-items:center;">
            ${images.map((_, i) => `<span class="vm-dot-ind" data-idx="${i}" style="display:block;height:6px;width:${i===0?'20px':'6px'};border-radius:99px;background:${i===0?'#fff':'rgba(255,255,255,.4)'};transition:all .2s;cursor:pointer;" onclick="vmGalleryGoTo(${i})"></span>`).join('')}
          </div>
          <!-- Photo count -->
          <span style="position:absolute;top:14px;left:14px;background:rgba(0,0,0,.5);color:#fff;font-size:11px;font-weight:600;padding:4px 10px;border-radius:99px;backdrop-filter:blur(4px);">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:11px;height:11px;vertical-align:middle;margin-right:3px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            ${images.length} photos
          </span>` : ''}

        <!-- Status badge -->
        <span style="position:absolute;top:14px;right:14px;display:inline-flex;align-items:center;gap:5px;background:${sc.color};color:#fff;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:5px 11px;border-radius:99px;box-shadow:0 2px 8px rgba(0,0,0,.2);">
          ${sc.icon} ${sc.label}
        </span>
      </div>`
    : `<div style="height:200px;background:linear-gradient(135deg,#f1f5f9 0%,#e2e8f0 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#94a3b8;flex-shrink:0;position:relative;">
        <svg fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="width:42px;height:42px;opacity:.3;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        <span style="font-size:12px;font-weight:500;">No photos for this unit</span>
        <span style="position:absolute;top:14px;right:14px;display:inline-flex;align-items:center;gap:5px;background:${sc.color};color:#fff;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:5px 11px;border-radius:99px;">
          ${sc.icon} ${sc.label}
        </span>
      </div>`;

  // Gallery JS state (window-scoped for inline onclick handlers)
  window._vmImages = images;
  window._vmIdx = 0;
  window.vmGalleryNav = function(dir) {
    const next = (window._vmIdx + dir + window._vmImages.length) % window._vmImages.length;
    vmGalleryGoTo(next);
  };
  window.vmGalleryGoTo = function(idx) {
    window._vmIdx = idx;
    const img = document.getElementById('vm-main-img');
    if (img) {
      img.style.opacity = '0';
      setTimeout(() => { img.src = (window.APP_BASE || '') + '/' + window._vmImages[idx]; img.style.opacity = '1'; }, 200);
    }
    document.querySelectorAll('.vm-dot-ind').forEach((d, i) => {
      d.style.width = i === idx ? '20px' : '6px';
      d.style.background = i === idx ? '#fff' : 'rgba(255,255,255,.4)';
    });
  };

  // ── Render amenities (view mode) ──────────────────────────────────────────
  function renderAmenitiesView(wrap, amenities) {
    if (!amenities.length) {
      wrap.innerHTML = `<div style="display:flex;align-items:center;gap:8px;padding:12px 14px;background:#f8fafc;border-radius:10px;border:1.5px dashed #e2e8f0;">
        <svg fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24" style="width:16px;height:16px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
        <span style="font-size:12.5px;color:#94a3b8;font-style:italic;">No amenities assigned to this unit.</span>
      </div>`;
      return;
    }
    wrap.innerHTML = `<div style="display:flex;flex-wrap:wrap;gap:7px;">` +
      amenities.map(a => {
        const svg = getIcon(a.icon);
        return `<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px 6px 9px;border-radius:99px;background:#f0fdf4;border:1.5px solid #bbf7d0;font-size:12px;font-weight:500;color:#15803d;white-space:nowrap;">
          ${svg ? `<span style="display:inline-flex;width:13px;height:13px;flex-shrink:0;">${svg}</span>` : ''}
          ${a.name}
        </span>`;
      }).join('') + `</div>`;
  }

  const modal = PS.openModal(`
    <style>
      @keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
      @keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
      #vm-edit-mode input:focus,#vm-edit-mode select:focus,#vm-edit-mode textarea:focus{
        border-color:var(--primary,#6366f1)!important;box-shadow:0 0 0 3px rgba(99,102,241,.1)!important;
      }
      .vm-section-title{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text-soft,#64748b);margin-bottom:10px;}
      .vm-divider{height:1px;background:var(--border,#e2e8f0);margin:16px 0;}
      .vm-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
      .vm-info-cell{background:var(--bg,#f8fafc);border-radius:10px;padding:11px 13px;}
      .vm-info-cell-label{font-size:10px;color:var(--text-soft);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;}
      .vm-info-cell-value{font-size:13px;font-weight:600;color:var(--text);}
    </style>

    <div style="padding:0;overflow:hidden;">
      ${galleryHtml}

      <!-- ══ VIEW MODE ════════════════════════════════════════════════════ -->
      <div id="vm-view-mode" style="padding:20px 22px 4px;">

        <!-- Header: title + price -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
          <div style="min-width:0;">
            <div style="font-size:19px;font-weight:800;color:var(--text);line-height:1.2;letter-spacing:-.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              ${[unit.property_name, unit.unit_number].filter(Boolean).join(' ')}${unit.unit_name ? ' — ' + unit.unit_name : ''}
            </div>
            <div style="display:flex;align-items:center;gap:8px;margin-top:6px;flex-wrap:wrap;">
              ${unit.floor ? `<span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--text-soft);">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:12px;height:12px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                Floor ${unit.floor}</span>` : ''}
              ${unit.unit_type ? `<span style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:var(--text-soft);">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:12px;height:12px;"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/></svg>
                ${unit.unit_type}</span>` : ''}
              <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;background:${sc.bg};border:1px solid ${sc.border};font-size:11px;font-weight:600;color:${sc.color};">
                ${sc.icon} ${sc.label}
              </span>
            </div>
          </div>
          <div style="text-align:right;flex-shrink:0;background:linear-gradient(135deg,var(--primary-soft,#eef2ff),#e0e7ff);padding:10px 14px;border-radius:12px;border:1px solid #c7d2fe;">
            <div style="font-size:22px;font-weight:800;color:var(--primary,#6366f1);letter-spacing:-.5px;line-height:1;">₱${Number(unit.rent_amount).toLocaleString('en-US',{minimumFractionDigits:0})}</div>
            <div style="font-size:11px;color:#818cf8;margin-top:2px;font-weight:500;">/ night</div>
          </div>
        </div>

        <div class="vm-divider"></div>

        <!-- Description -->
        ${unit.description ? `
          <div style="margin-bottom:14px;">
            <div class="vm-section-title">Description</div>
            <p style="font-size:13px;color:var(--text-soft);line-height:1.7;margin:0;background:var(--bg,#f8fafc);padding:12px 14px;border-radius:10px;">${unit.description}</p>
          </div>
          <div class="vm-divider"></div>` : ''}

        <!-- Unit details grid -->
        <div style="margin-bottom:14px;">
          <div class="vm-section-title">Unit Details</div>
          <div class="vm-info-grid">
            <div class="vm-info-cell">
              <div class="vm-info-cell-label">Property</div>
              <div class="vm-info-cell-value">${unit.property_name || '—'}</div>
            </div>
            <div class="vm-info-cell">
              <div class="vm-info-cell-label">Type</div>
              <div class="vm-info-cell-value">${unit.unit_type || '—'}</div>
            </div>
            <div class="vm-info-cell">
              <div class="vm-info-cell-label">Floor</div>
              <div class="vm-info-cell-value">${unit.floor ? 'Floor ' + unit.floor : '—'}</div>
            </div>
            <div class="vm-info-cell">
              <div class="vm-info-cell-label">Rent / Night</div>
              <div class="vm-info-cell-value" style="color:var(--primary,#6366f1);">₱${Number(unit.rent_amount).toLocaleString('en-US',{minimumFractionDigits:0})}</div>
            </div>
          </div>
        </div>

        <div class="vm-divider"></div>

        <!-- Amenities -->
        <div style="margin-bottom:14px;">
          <div class="vm-section-title">Amenities</div>
          <div id="vm-amenities-wrap">
            <div style="display:flex;gap:8px;padding:4px 0;">
              ${[90,110,80].map((w,i)=>`<div style="height:30px;width:${w}px;border-radius:99px;background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200% 100%;animation:shimmer 1.2s infinite ${i*.15}s;"></div>`).join('')}
            </div>
          </div>
        </div>

        <div class="vm-divider"></div>

        <!-- Tenant -->
        <div style="margin-bottom:6px;">
          <div class="vm-section-title">Tenant</div>
          ${(status === 'occupied' || unit.tenant_name) && unit.tenant_name
            ? `<div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--bg,#f8fafc);border-radius:12px;border:1px solid var(--border,#e2e8f0);">
                ${unit.tenant_photo
                  ? `<img src="${(window.APP_BASE||'')}/${unit.tenant_photo}" style="width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #e2e8f0;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                     <div style="display:none;width:42px;height:42px;border-radius:50%;background:#dbeafe;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#1d4ed8;flex-shrink:0;">${initials}</div>`
                  : `<div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#dbeafe,#bfdbfe);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#1d4ed8;flex-shrink:0;border:2px solid #93c5fd;">${initials}</div>`}
                <div style="min-width:0;">
                  <div style="font-size:14px;font-weight:700;color:var(--text);">${unit.tenant_name}</div>
                  ${unit.tenant_email ? `<div style="font-size:12px;color:var(--text-soft);margin-top:2px;">${unit.tenant_email}</div>` : ''}
                </div>
                <span style="margin-left:auto;display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:99px;background:#dcfce7;border:1px solid #86efac;font-size:11px;font-weight:600;color:#16a34a;">
                  <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:11px;height:11px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  Active
                </span>
              </div>`
            : `<div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#f8fafc;border-radius:12px;border:1.5px dashed #e2e8f0;">
                <div style="width:38px;height:38px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <svg fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24" style="width:18px;height:18px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <span style="font-size:13px;color:#94a3b8;font-style:italic;">No tenant assigned</span>
              </div>`}
        </div>

      </div>

      <!-- ══ EDIT MODE ════════════════════════════════════════════════════ -->
      <div id="vm-edit-mode" style="display:none;padding:20px 22px 4px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;">
          <div style="width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#eef2ff,#e0e7ff);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg fill="none" stroke="var(--primary,#6366f1)" stroke-width="2" viewBox="0 0 24 24" style="width:15px;height:15px;"><path d="M15.232 5.232a2 2 0 0 1 2.828 2.828L8.828 17.293a4 4 0 0 1-1.414.93L3 20l1.777-4.414a4 4 0 0 1 .93-1.414L15.232 5.232z"/></svg>
          </div>
          <div style="font-size:15px;font-weight:700;color:var(--text);letter-spacing:-.2px;">Edit Unit</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div>
            <label style="${LBL}">Unit Number</label>
            <input id="ve-unit-number" type="text" style="${INP}" placeholder="e.g. A-101" value="${unit.unit_number || ''}">
          </div>
          <div>
            <label style="${LBL}">Unit Name</label>
            <input id="ve-unit-name" type="text" style="${INP}" placeholder="e.g. Garden Suite" value="${unit.unit_name || ''}">
          </div>
          <div>
            <label style="${LBL}">Unit Type</label>
            <select id="ve-unit-type" style="${INP}">
              <option value="">Select Type</option>
              ${['Studio','1 Bedroom','2 Bedroom','3 Bedroom','Loft','Penthouse'].map(t=>`<option value="${t}"${unit.unit_type===t?' selected':''}>${t}</option>`).join('')}
            </select>
          </div>
          <div>
            <label style="${LBL}">Floor</label>
            <input id="ve-floor" type="number" style="${INP}" min="1" placeholder="1" value="${unit.floor || ''}">
          </div>
          <div>
            <label style="${LBL}">Rent Amount (₱)</label>
            <input id="ve-rent" type="number" style="${INP}" min="0" step="0.01" placeholder="0.00" value="${unit.rent_amount || ''}">
          </div>
          <div>
            <label style="${LBL}">Status</label>
            <select id="ve-status" style="${INP}">
              <option value="vacant"${status==='vacant'?' selected':''}>Vacant</option>
              <option value="occupied"${status==='occupied'?' selected':''}>Occupied</option>
              <option value="maintenance"${status==='maintenance'?' selected':''}>Maintenance</option>
            </select>
          </div>
          <div style="grid-column:1/-1;">
            <label style="${LBL}">Tenant Name</label>
            <input id="ve-tenant" type="text" style="${INP}" placeholder="Full name" value="${unit.tenant_name || ''}">
          </div>
          <div style="grid-column:1/-1;">
            <label style="${LBL}">Description</label>
            <textarea id="ve-description" rows="3" style="${INP}resize:vertical;" placeholder="Describe the unit — features, highlights, furnishing details…">${unit.description || ''}</textarea>
            <div style="text-align:right;font-size:11px;color:var(--text-soft);margin-top:3px;"><span id="ve-desc-count">${(unit.description||'').length}</span> / 500</div>
          </div>

          <!-- Amenities -->
          <div style="grid-column:1/-1;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
              <label style="${LBL}margin-bottom:0;">Amenities</label>
              <span id="ve-amenities-count" style="font-size:11px;color:var(--primary,#6366f1);font-weight:500;"></span>
            </div>
            <div id="ve-amenities-loading" style="display:flex;gap:8px;padding:4px 0;">
              ${[90,110,80].map((w,i)=>`<div style="height:32px;width:${w}px;border-radius:99px;background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200% 100%;animation:shimmer 1.2s infinite ${i*.2}s;"></div>`).join('')}
            </div>
            <div id="ve-amenities-wrap" style="display:none;"></div>
          </div>

          <!-- ── Photo management ── -->
          <div style="grid-column:1/-1;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
              <label style="${LBL}margin-bottom:0;">Photos</label>
              <span id="ve-photo-count" style="font-size:11px;color:var(--text-soft);"></span>
            </div>

            <!-- Existing photos -->
            ${images.length ? `
              <div style="margin-bottom:12px;">
                <div style="font-size:11px;color:var(--text-soft);margin-bottom:7px;font-weight:500;">Current photos — click × to remove</div>
                <div id="ve-existing-photos" style="display:flex;flex-wrap:wrap;gap:8px;">
                  ${images.map((src, i) => `
                    <div data-existing-photo style="position:relative;">
                      <img src="${(window.APP_BASE||'')}/${src}" style="width:78px;height:78px;object-fit:cover;border-radius:9px;border:2px solid var(--border,#e2e8f0);display:block;" onerror="this.style.opacity='.3';">
                      <button data-remove-existing="${src}" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#ef4444;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;box-shadow:0 1px 4px rgba(0,0,0,.25);" title="Remove this photo">
                        <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:9px;height:9px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                      </button>
                      ${i === 0 ? `<span style="position:absolute;bottom:4px;left:4px;background:rgba(0,0,0,.6);color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;">COVER</span>` : ''}
                    </div>`).join('')}
                </div>
                <input type="hidden" id="ve-removed-photos" value="">
              </div>` : ''}

            <!-- Upload new photos -->
            <div id="ve-dropzone" style="border:2px dashed #cbd5e1;border-radius:12px;padding:24px 20px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;background:#f8fafc;position:relative;">
              <input type="file" id="ve-images" accept="image/*" multiple style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:2;">
              <div id="ve-dropzone-inner" style="pointer-events:none;">
                <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#eef2ff,#e0e7ff);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
                  <svg fill="none" stroke="var(--primary,#6366f1)" stroke-width="1.75" viewBox="0 0 24 24" style="width:20px;height:20px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:3px;">Add new photos</div>
                <div style="font-size:11.5px;color:#94a3b8;">PNG, JPG, WEBP · Up to 10 total</div>
              </div>
            </div>
            <div id="ve-new-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>
          </div>

        </div>
      </div>
    </div>

    <div class="ps-modal-footer">
      <button class="btn btn-secondary" id="vm-close-btn">Close</button>
      <button class="btn btn-secondary" id="vm-edit-btn" style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;"><path d="M15.232 5.232a2 2 0 0 1 2.828 2.828L8.828 17.293a4 4 0 0 1-1.414.93L3 20l1.777-4.414a4 4 0 0 1 .93-1.414L15.232 5.232z"/></svg>
        Edit Unit
      </button>
    </div>
  `);

  // ── Load amenities (view mode) ────────────────────────────────────────────
  fetchUnitAmenities(unit.unit_id).then(function (amenities) {
    const wrap = document.getElementById('vm-amenities-wrap');
    if (wrap) renderAmenitiesView(wrap, amenities);
  });

  // ── Close button ──────────────────────────────────────────────────────────
  document.getElementById('vm-close-btn')?.addEventListener('click', () => modal.close());

  // ── Edit button ───────────────────────────────────────────────────────────
  document.getElementById('vm-edit-btn')?.addEventListener('click', async function () {
    const viewMode = document.getElementById('vm-view-mode');
    const editMode = document.getElementById('vm-edit-mode');
    const footer   = document.querySelector('.ps-modal-footer');

    viewMode.style.display = 'none';
    editMode.style.display = 'block';

    // Description counter
    const descTA    = document.getElementById('ve-description');
    const descCount = document.getElementById('ve-desc-count');
    descTA.addEventListener('input', function () {
      const len = this.value.length;
      if (len > 500) this.value = this.value.slice(0, 500);
      descCount.textContent = Math.min(len, 500);
      descCount.style.color = len >= 450 ? '#ef4444' : 'var(--text-soft)';
    });

    // Load amenity picker
    const aloading = document.getElementById('ve-amenities-loading');
    const awrap    = document.getElementById('ve-amenities-wrap');
    const acount   = document.getElementById('ve-amenities-count');

    const [allAmenities, currentAmenities] = await Promise.all([
      fetchPropertyAmenities(unit.property_id),
      fetchUnitAmenities(unit.unit_id),
    ]);
    const assignedIds = new Set(currentAmenities.map(a => a.amenity_id));
    aloading.style.display = 'none';
    awrap.style.display = '';
    renderAmenityPicker(awrap, allAmenities);

    awrap.querySelectorAll('input[name="amenity_ids[]"]').forEach(cb => {
      if (assignedIds.has(parseInt(cb.value))) {
        cb.checked = true;
        const lbl = cb.closest('label');
        lbl.style.borderColor    = 'var(--primary,#6366f1)';
        lbl.style.background     = '#eef2ff';
        lbl.style.color          = '#4338ca';
        lbl.style.boxShadow      = '0 0 0 3px rgba(99,102,241,.12)';
        lbl.querySelector('[data-cb-dot]').style.background  = 'var(--primary,#6366f1)';
        lbl.querySelector('[data-cb-dot]').style.borderColor = 'var(--primary,#6366f1)';
        lbl.querySelector('svg').style.opacity = '1';
      }
    });

    function updateAmenityCount() {
      const sel = awrap.querySelectorAll('input[type="checkbox"]:checked').length;
      acount.textContent = sel > 0 ? `${sel} of ${allAmenities.length} selected` : (allAmenities.length ? `${allAmenities.length} available` : '');
      acount.style.color = sel > 0 ? 'var(--primary,#6366f1)' : '#94a3b8';
    }
    updateAmenityCount();
    awrap.addEventListener('click', updateAmenityCount);

    // ── Photo management wiring ─────────────────────────────────────────────
    let newFiles = [];
    let removedPhotos = [];

    function updatePhotoCount() {
      const existingWrap = document.getElementById('ve-existing-photos');
      const remaining = existingWrap
        ? existingWrap.querySelectorAll('[data-existing-photo]:not([style*="display:none"])').length
        : images.length;
      const total = remaining + newFiles.length;
      const countEl = document.getElementById('ve-photo-count');
      if (countEl) {
        countEl.textContent = `${total} photo${total !== 1 ? 's' : ''}`;
        countEl.style.color = total >= 10 ? '#ef4444' : 'var(--text-soft)';
      }
    }

    // Wire remove buttons on existing photos
    document.querySelectorAll('[data-remove-existing]').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const src = this.dataset.removeExisting;
        removedPhotos.push(src);
        const input = document.getElementById('ve-removed-photos');
        if (input) input.value = removedPhotos.join(',');
        this.closest('[data-existing-photo]').style.display = 'none';
        updatePhotoCount();
      });
    });

    // Dropzone drag & drop
    const dropzone = document.getElementById('ve-dropzone');
    if (dropzone) {
      dropzone.addEventListener('dragover', e => {
        e.preventDefault();
        dropzone.style.borderColor = 'var(--primary,#6366f1)';
        dropzone.style.background = '#eef2ff';
      });
      dropzone.addEventListener('dragleave', e => {
        if (!dropzone.contains(e.relatedTarget)) {
          dropzone.style.borderColor = '#cbd5e1';
          dropzone.style.background = '#f8fafc';
        }
      });
      dropzone.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.style.borderColor = '#cbd5e1';
        dropzone.style.background = '#f8fafc';
        addNewFiles([...e.dataTransfer.files].filter(f => f.type.startsWith('image/')));
      });
    }

    document.getElementById('ve-images')?.addEventListener('change', function () {
      addNewFiles([...this.files]);
      this.value = '';
    });

    function addNewFiles(incoming) {
      const existingWrap = document.getElementById('ve-existing-photos');
      const remaining = existingWrap
        ? existingWrap.querySelectorAll('[data-existing-photo]:not([style*="display:none"])').length
        : 0;
      const preview = document.getElementById('ve-new-preview');
      incoming.forEach(file => {
        if (remaining + newFiles.length >= 10) return;
        newFiles.push(file);
        const url = URL.createObjectURL(file);
        const wrap = document.createElement('div');
        wrap.style.cssText = 'position:relative;';
        const img = Object.assign(document.createElement('img'), { src: url });
        img.style.cssText = 'width:78px;height:78px;object-fit:cover;border-radius:9px;border:2px solid #c7d2fe;display:block;';
        const rm = document.createElement('button');
        rm.innerHTML = `<svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:9px;height:9px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
        rm.style.cssText = 'position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#ef4444;color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;box-shadow:0 1px 4px rgba(0,0,0,.25);';
        rm.onclick = () => {
          const idx = newFiles.indexOf(file);
          if (idx > -1) newFiles.splice(idx, 1);
          wrap.remove();
          URL.revokeObjectURL(url);
          updatePhotoCount();
        };
        const newBadge = document.createElement('span');
        newBadge.textContent = 'NEW';
        newBadge.style.cssText = 'position:absolute;bottom:4px;left:4px;background:rgba(99,102,241,.85);color:#fff;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;';
        wrap.append(img, rm, newBadge);
        preview.appendChild(wrap);
        updatePhotoCount();
      });
    }

    updatePhotoCount();

    // ── Footer swap ───────────────────────────────────────────────────────────
    footer.innerHTML = `
      <button class="btn btn-secondary" id="ve-cancel-btn" style="display:inline-flex;align-items:center;gap:6px;">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;"><polyline points="15 18 9 12 15 6"/></svg>
        Cancel
      </button>
      <button class="btn btn-primary" id="ve-save-btn" style="min-width:140px;display:inline-flex;align-items:center;gap:6px;">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Changes
      </button>
    `;

    document.getElementById('ve-cancel-btn').addEventListener('click', () => {
      editMode.style.display = 'none';
      viewMode.style.display = 'block';
      footer.innerHTML = `
        <button class="btn btn-secondary" id="vm-close-btn">Close</button>
        <button class="btn btn-secondary" id="vm-edit-btn" style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;"><path d="M15.232 5.232a2 2 0 0 1 2.828 2.828L8.828 17.293a4 4 0 0 1-1.414.93L3 20l1.777-4.414a4 4 0 0 1 .93-1.414L15.232 5.232z"/></svg>
          Edit Unit
        </button>
      `;
      document.getElementById('vm-close-btn').addEventListener('click', () => modal.close());
      // Re-bind edit button in a simple way: just reload
      document.getElementById('vm-edit-btn').addEventListener('click', () => location.reload());
    });

    // ── Save ──────────────────────────────────────────────────────────────────
    async function doSave() {
      const saveBtn = document.getElementById('ve-save-btn');
      if (!saveBtn || saveBtn.disabled) return;
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;animation:spin 1s linear infinite;"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Saving…';

      try {
        const fd = new FormData();
        fd.append('csrf_token',       window.PS_CSRF_TOKEN || '');
        fd.append('unit_id',          unit.unit_id);
        fd.append('unit_number',      document.getElementById('ve-unit-number').value.trim());
        fd.append('unit_name',        document.getElementById('ve-unit-name').value.trim());
        fd.append('unit_type',        document.getElementById('ve-unit-type').value);
        fd.append('floor',            document.getElementById('ve-floor').value);
        fd.append('rent_amount',      document.getElementById('ve-rent').value);
        fd.append('status',           document.getElementById('ve-status').value);
        fd.append('tenant_name',      document.getElementById('ve-tenant').value.trim());
        fd.append('description',      document.getElementById('ve-description').value.trim());

        // Amenities
        getCheckedAmenityIds(awrap).forEach(id => fd.append('amenity_ids[]', id));

        // Removed photos (comma-separated paths)
        const removedInput = document.getElementById('ve-removed-photos');
        if (removedInput && removedInput.value) {
          removedInput.value.split(',').filter(Boolean).forEach(p => fd.append('remove_images[]', p));
        }

        // New photo files
        newFiles.forEach(f => fd.append('unit_images[]', f));

        const data = await fetch('../../api/admin/update_unit.php', { method: 'POST', body: fd }).then(r => r.json());

        if (data.status === 'success') {
          if (typeof showToast === 'function') showToast('Unit updated successfully.', 'success');
          else if (typeof PS !== 'undefined' && PS.toast) PS.toast('Unit updated successfully.', 'success');

          const updatedUnit = data.unit;
          const card = document.querySelector(`.view-unit-btn[data-unit*='"unit_id":${unit.unit_id}']`)?.closest('.unit-listing-card');
          if (card && updatedUnit) {
            const mergedUnit = Object.assign({}, unit, {
              unit_number: updatedUnit.unit_number  || unit.unit_number,
              unit_name:   updatedUnit.unit_name    || unit.unit_name,
              unit_type:   updatedUnit.unit_type    || unit.unit_type,
              floor:       updatedUnit.floor        ?? unit.floor,
              rent_amount: updatedUnit.rent_amount  ?? unit.rent_amount,
              status:      updatedUnit.status       || unit.status,
              tenant_name: updatedUnit.tenant_name  || '',
              description: updatedUnit.description  || '',
              images:      updatedUnit.images       || unit.images,
            });
            const viewBtn = card.querySelector('.view-unit-btn');
            if (viewBtn) viewBtn.dataset.unit = JSON.stringify(mergedUnit);
            const newStatus = (updatedUnit.status || '').toLowerCase();
            card.dataset.status = newStatus;
            const pill = card.querySelector('.status-pill');
            if (pill) { pill.className = `status-pill ${newStatus}`; pill.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1); }
            const titleEl = card.querySelector('.unit-title');
            if (titleEl) titleEl.textContent = updatedUnit.unit_number || unit.unit_number || '—';
            const priceEl = card.querySelector('.price-value');
            if (priceEl) priceEl.textContent = '₱' + Number(updatedUnit.rent_amount).toLocaleString('en-US', { minimumFractionDigits: 0 });
          }

          modal.close();
          await refreshStats();
          applyFilters();
        } else {
          if (typeof showToast === 'function') showToast('Error: ' + (data.message || 'Unknown error'), 'error');
          else alert('Error: ' + (data.message || 'Unknown error'));
          saveBtn.disabled = false;
          saveBtn.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Changes';
        }
      } catch (e) {
        console.error(e);
        if (typeof showToast === 'function') showToast('Network error. Please try again.', 'error');
        else alert('Network error. Please try again.');
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save Changes';
      }
    }

    document.getElementById('ve-save-btn').addEventListener('click', doSave);
  });
}

function attachViewHandler(btn) {
  btn.addEventListener('click', function () {
    try { openViewModal(JSON.parse(this.dataset.unit)); }
    catch (e) { console.error('View parse error', e); }
  });
}
document.querySelectorAll('.view-unit-btn').forEach(attachViewHandler);

// ═══════════════════════════════════════════════════════════════════════════
// ADD UNIT MODAL (unchanged from original, re-included for completeness)
// ═══════════════════════════════════════════════════════════════════════════
document.getElementById('open-add-unit-modal').addEventListener('click', () => {
  const opts = propertiesList.map(p =>
    `<option value="${p.property_id}">${p.property_name}</option>`
  ).join('');

  PS.openModal(`
    <div class="ps-modal-title">Add New Unit</div>
    <div class="ps-modal-grid">

      <div class="form-group">
        <label>Unit Number <span style="color:var(--danger)">*</span></label>
        <input type="text" id="m-unit-number" placeholder="e.g. A-101">
      </div>
      <div class="form-group">
        <label>Unit Name</label>
        <input type="text" id="m-unit-name" placeholder="e.g. Garden Suite">
      </div>
      <div class="form-group">
        <label>Property <span style="color:var(--danger)">*</span></label>
        <select id="m-property">
          <option value="">Select Property</option>
          ${opts}
        </select>
      </div>
      <div class="form-group">
        <label>Unit Type</label>
        <select id="m-unit-type">
          <option value="">Select Type</option>
          <option>Studio</option>
          <option>1 Bedroom</option>
          <option>2 Bedroom</option>
          <option>3 Bedroom</option>
          <option>Loft</option>
          <option>Penthouse</option>
        </select>
      </div>
      <div class="form-group">
        <label>Floor</label>
        <input type="number" id="m-floor" min="1" placeholder="1">
      </div>
      <div class="form-group">
        <label>Rent Amount (₱)</label>
        <input type="number" id="m-rent" min="0" step="0.01" placeholder="0.00">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select id="m-status">
          <option value="vacant">Vacant</option>
          <option value="occupied">Occupied</option>
          <option value="maintenance">Maintenance</option>
        </select>
      </div>
      <div class="form-group full">
        <label>Tenant Name</label>
        <input type="text" id="m-tenant" placeholder="Full name">
      </div>
      <div class="form-group full">
        <label>Description</label>
        <textarea id="m-description" rows="3" placeholder="Describe the unit…" style="width:100%;resize:vertical;font-size:13px;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;color:var(--text);background:var(--input-bg,#fff);line-height:1.5;"></textarea>
        <div style="text-align:right;font-size:11px;color:var(--text-soft);margin-top:4px;"><span id="m-desc-count">0</span> / 500</div>
      </div>

      <div class="form-group full" id="m-amenities-section" style="display:none;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
          <label style="margin:0;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--text-soft);">Amenities</label>
          <span id="m-amenities-count" style="font-size:11px;color:var(--primary,#6366f1);font-weight:500;display:none;"></span>
        </div>
        <div id="m-amenities-loading" style="display:none;padding:10px 0;">
          <div style="display:flex;gap:8px;">
            <div style="height:32px;width:90px;border-radius:99px;background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200% 100%;animation:shimmer 1.2s infinite;"></div>
            <div style="height:32px;width:110px;border-radius:99px;background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200% 100%;animation:shimmer 1.2s infinite .2s;"></div>
            <div style="height:32px;width:80px;border-radius:99px;background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200% 100%;animation:shimmer 1.2s infinite .4s;"></div>
          </div>
        </div>
        <div id="m-amenities-wrap"></div>
      </div>

      <div class="form-group full">
        <label>Unit Photos</label>
        <div id="m-dropzone" style="border:2px dashed #cbd5e1;border-radius:12px;padding:32px 20px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;background:#f8fafc;position:relative;">
          <input type="file" id="m-images" accept="image/*" multiple style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:2;">
          <div id="m-dropzone-inner" style="pointer-events:none;">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#eef2ff,#e0e7ff);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
              <svg fill="none" stroke="var(--primary,#6366f1)" stroke-width="1.75" viewBox="0 0 24 24" style="width:22px;height:22px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            </div>
            <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">Drop photos here or <span style="color:var(--primary,#6366f1);">browse files</span></div>
            <div style="font-size:11.5px;color:#94a3b8;">PNG, JPG, WEBP supported · Up to 10 photos</div>
          </div>
        </div>
        <div id="m-file-count" style="display:none;font-size:12px;color:var(--text-soft);margin-top:8px;text-align:right;"></div>
        <div id="m-preview" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>
      </div>

    </div>
    <div class="ps-modal-footer">
      <button class="btn btn-secondary" data-ps-cancel>Cancel</button>
      <button class="btn btn-primary" id="m-save">Save Unit</button>
    </div>
  `, {
    onMount(bd) {
      let files = [];

      const descArea = bd.querySelector('#m-description');
      const descCount = bd.querySelector('#m-desc-count');
      descArea.addEventListener('input', function () {
        const len = this.value.length;
        if (len > 500) this.value = this.value.slice(0, 500);
        descCount.textContent = Math.min(len, 500);
        descCount.style.color = len >= 450 ? '#ef4444' : 'var(--text-soft)';
      });

      bd.querySelector('#m-property').addEventListener('change', async function () {
        const propertyId = this.value;
        const section = bd.querySelector('#m-amenities-section');
        const loading = bd.querySelector('#m-amenities-loading');
        const wrap = bd.querySelector('#m-amenities-wrap');
        const countBadge = bd.querySelector('#m-amenities-count');
        wrap.innerHTML = '';
        countBadge.style.display = 'none';
        countBadge.textContent = '';
        if (!propertyId) { section.style.display = 'none'; return; }
        section.style.display = 'block';
        loading.style.display = 'block';
        wrap.style.display = 'none';
        const amenities = await fetchPropertyAmenities(propertyId);
        loading.style.display = 'none';
        wrap.style.display = '';
        renderAmenityPicker(wrap, amenities);
        if (amenities.length) {
          countBadge.textContent = amenities.length + ' available';
          countBadge.style.display = 'inline';
        }
        wrap.addEventListener('click', function () {
          const selected = wrap.querySelectorAll('input[type="checkbox"]:checked').length;
          if (selected > 0) {
            countBadge.textContent = selected + ' of ' + amenities.length + ' selected';
            countBadge.style.color = 'var(--primary,#6366f1)';
          } else {
            countBadge.textContent = amenities.length + ' available';
            countBadge.style.color = '#94a3b8';
          }
        });
      });

      const dropzone = bd.querySelector('#m-dropzone');
      const fileCountEl = bd.querySelector('#m-file-count');

      function updateFileCount() {
        if (files.length > 0) {
          fileCountEl.style.display = 'block';
          fileCountEl.textContent = `${files.length} / 10 photo${files.length !== 1 ? 's' : ''} selected`;
          fileCountEl.style.color = files.length >= 10 ? '#ef4444' : 'var(--text-soft)';
        } else {
          fileCountEl.style.display = 'none';
        }
      }

      function addFiles(newFiles) {
        const preview = bd.querySelector('#m-preview');
        newFiles.forEach(file => {
          if (files.length >= 10) return;
          files.push(file);
          const url = URL.createObjectURL(file);
          const wrap = document.createElement('div');
          wrap.style.cssText = 'position:relative;';
          const img = Object.assign(document.createElement('img'), { src: url });
          img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:9px;border:1px solid var(--border);display:block;transition:opacity .2s;';
          const rm = document.createElement('button');
          rm.innerHTML = `<svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:9px;height:9px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
          rm.style.cssText = 'position:absolute;top:-5px;right:-5px;width:20px;height:20px;border-radius:50%;background:#ef4444;color:#fff;border:none;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;line-height:1;box-shadow:0 1px 4px rgba(0,0,0,.2);';
          rm.onclick = () => {
            const idx = [...preview.children].indexOf(wrap);
            if (idx > -1) files.splice(idx, 1);
            wrap.remove();
            updateFileCount();
          };
          wrap.append(img, rm);
          preview.appendChild(wrap);
        });
        updateFileCount();
      }

      dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.style.borderColor = 'var(--primary,#6366f1)'; dropzone.style.background = '#eef2ff'; });
      dropzone.addEventListener('dragleave', e => { if (!dropzone.contains(e.relatedTarget)) { dropzone.style.borderColor = '#cbd5e1'; dropzone.style.background = '#f8fafc'; } });
      dropzone.addEventListener('drop', e => { e.preventDefault(); dropzone.style.borderColor = '#cbd5e1'; dropzone.style.background = '#f8fafc'; addFiles([...e.dataTransfer.files].filter(f => f.type.startsWith('image/'))); });
      bd.querySelector('#m-images').addEventListener('change', function () { addFiles([...this.files]); this.value = ''; });

      bd.querySelector('#m-save').addEventListener('click', async () => {
        const unitNumber = bd.querySelector('#m-unit-number').value.trim();
        const propertyId = bd.querySelector('#m-property').value;
        if (!propertyId) { PS.toast('Please select a property.', 'error'); return; }
        const btn = bd.querySelector('#m-save');
        btn.disabled = true;
        btn.textContent = 'Saving…';
        try {
          const fd = new FormData();
          fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
          fd.append('unit_number', unitNumber);
          fd.append('unit_name', bd.querySelector('#m-unit-name').value.trim());
          fd.append('property_id', propertyId);
          fd.append('unit_type', bd.querySelector('#m-unit-type').value);
          fd.append('floor', bd.querySelector('#m-floor').value);
          fd.append('rent_amount', bd.querySelector('#m-rent').value);
          fd.append('status', bd.querySelector('#m-status').value);
          fd.append('tenant_name', bd.querySelector('#m-tenant').value.trim());
          fd.append('description', bd.querySelector('#m-description').value.trim());
          getCheckedAmenityIds(bd).forEach(id => fd.append('amenity_ids[]', id));
          files.forEach(f => fd.append('unit_images[]', f));
          const data = await fetch('../../api/admin/add_unit.php', { method: 'POST', body: fd }).then(r => r.json());
          if (data.status === 'success') {
            PS.toast(data.message, 'success');
            bd.classList.remove('open');
            setTimeout(() => bd.remove(), 220);
            addUnitCard(data.unit);
            await refreshStats();
            applyFilters();
          } else {
            PS.toast(data.message, 'error');
            btn.disabled = false;
            btn.textContent = 'Save Unit';
          }
        } catch (err) {
          console.error(err);
          PS.toast('Server error. Please try again.', 'error');
          btn.disabled = false;
          btn.textContent = 'Save Unit';
        }
      });
    }
  });
});

function buildCardHTML(unit) {
  const status = (unit.status || '').toLowerCase();
  const images = unit.images || [];
  const thumb = images[0] || null;
  const unitJson = JSON.stringify(unit).replace(/"/g, '&quot;');
  const tags = [];
  if (unit.unit_type) tags.push(unit.unit_type);
  if (unit.floor) tags.push('Floor ' + unit.floor);
  if (status === 'vacant') tags.push('Available');
  if (status === 'maintenance') tags.push('Under Maintenance');

  const photoHtml = thumb
    ? `<img src="${(window.APP_BASE || '')}/${thumb}" alt="unit photo">
       <div class="overlay"></div>
       ${images.length > 1 ? `<span class="photo-count-pill">${images.length} photos</span>` : ''}
       ${statusPillHtml(status)}`
    : `<div class="no-photo">
         <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:36px;height:36px;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
         <span style="font-size:12px;">No photos added</span>
       </div>
       ${statusPillHtml(status)}`;

  const subParts = [unit.property_name, unit.unit_name].filter(Boolean);

  return `
    <div class="photo-wrap">${photoHtml}</div>
    <div class="body">
      <div>
        <div class="unit-title">${unit.unit_number || '—'}</div>
        <div class="unit-sub">${subParts.join(' · ')}</div>
      </div>
      <div class="meta-row">
        ${unit.unit_type ? `<span class="meta-item"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>${unit.unit_type}</span>` : ''}
        ${unit.floor ? `<span class="meta-item"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>Floor ${unit.floor}</span>` : ''}
        ${unit.tenant_name ? `<span class="meta-item"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>${unit.tenant_name}</span>` : ''}
      </div>
      <div class="tags">${tags.slice(0, 4).map(t => `<span class="tag">${t}</span>`).join('')}</div>
      <div class="footer">
        <div class="price">
          <span class="price-value">₱${Number(unit.rent_amount).toLocaleString('en-US', { minimumFractionDigits: 0 })}</span>
          <span class="price-label">/ month</span>
        </div>
        <div class="card-actions">
          <button class="btn-view view-unit-btn" data-unit="${unitJson}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>View
          </button>
          <button class="btn-del delete-unit-btn" data-id="${unit.unit_id}" data-name="${unit.unit_number}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg>Delete
          </button>
        </div>
      </div>
    </div>`;
}

function addUnitCard(unit) {
  document.getElementById('empty-state-row')?.remove();
  const status = (unit.status || '').toLowerCase();
  const search = [unit.unit_number, unit.unit_name, unit.property_name, unit.unit_type, unit.tenant_name].join(' ').toLowerCase();
  const card = document.createElement('div');
  card.className = 'unit-listing-card';
  card.dataset.propertyId = String(unit.property_id);
  card.dataset.status = status;
  card.dataset.search = search;
  card.style.opacity = '0';
  card.style.transform = 'scale(.97)';
  card.style.transition = 'box-shadow .25s,transform .2s,opacity .25s';
  card.innerHTML = buildCardHTML(unit);
  card.onmouseenter = () => { card.style.boxShadow = '0 8px 32px rgba(0,0,0,.11)'; card.style.transform = 'translateY(-3px)'; };
  card.onmouseleave = () => { card.style.boxShadow = ''; card.style.transform = ''; };
  document.getElementById('units-grid').insertBefore(card, document.getElementById('units-grid').firstChild);
  attachViewHandler(card.querySelector('.view-unit-btn'));
  attachDeleteHandler(card.querySelector('.delete-unit-btn'));
  requestAnimationFrame(() => { card.style.opacity = '1'; card.style.transform = 'scale(1)'; });
}

function attachDeleteHandler(btn) {
  btn.addEventListener('click', function () {
    const { id, name } = this.dataset;
    const card = this.closest('.unit-listing-card');
    PS.confirm(`Remove unit <strong>${name}</strong>? This cannot be undone.`, async () => {
      try {
        const fd = new FormData();
        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
        fd.append('unit_id', id);
        const data = await fetch('../../api/admin/delete_unit.php', { method: 'POST', body: fd }).then(r => r.json());
        if (data.status === 'success') {
          PS.toast(data.message, 'success');
          card.style.transition = 'opacity .3s,transform .3s';
          card.style.opacity = '0';
          card.style.transform = 'scale(.95)';
          setTimeout(async () => {
            card.remove();
            const grid = document.getElementById('units-grid');
            if (!grid.querySelector('.unit-listing-card')) {
              const e = document.createElement('div');
              e.id = 'empty-state-row';
              e.style.cssText = 'grid-column:1/-1;text-align:center;padding:72px 32px;color:var(--text-soft);';
              e.innerHTML = `<svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:44px;height:44px;margin:0 auto 14px;display:block;opacity:.25;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg><div style="font-size:15px;font-weight:600;margin-bottom:4px;">No units yet</div><div style="font-size:13px;opacity:.7;">Click "Add Unit" to get started.</div>`;
              grid.appendChild(e);
            }
            await refreshStats();
            applyFilters();
          }, 300);
        } else {
          PS.toast(data.message, 'error');
        }
      } catch (err) {
        console.error(err);
        PS.toast('Server error. Please try again.', 'error');
      }
    }, { title: 'Remove Unit', confirmLabel: 'Remove', confirmClass: 'btn btn-danger' });
  });
}
document.querySelectorAll('.delete-unit-btn').forEach(attachDeleteHandler);