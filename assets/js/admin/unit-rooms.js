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
document.getElementById('filter-status').addEventListener('change', applyFilters);
document.getElementById('filter-property').addEventListener('change', applyFilters);
applyFilters();

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

/**
 * Render the amenity picker section inside the Add Unit modal.
 * @param {HTMLElement} container  – the #m-amenities-wrap div
 * @param {Array}       amenities  – array of {amenity_id, name, icon}
 */
function renderAmenityPicker(container, amenities) {
  if (!amenities.length) {
    container.innerHTML = '<div style="display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:8px;background:#f8fafc;border:1.5px dashed #cbd5e1;"><svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:16px;height:16px;color:#94a3b8;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span style="font-size:12px;color:#94a3b8;">No amenities available for this property.</span></div>';
    return;
  }

  const ICON_MAP = {
    water: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6 9 4 13 4 16a8 8 0 0 0 16 0c0-3-2-7-8-14z"/></svg>',
    wifi: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor"/></svg>',
    parking: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>',
    rooftop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>',
    gym: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v16M18 4v16M6 12h12M3 8h3M18 8h3M3 16h3M18 16h3"/></svg>',
    pool: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 20c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><path d="M2 16c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><circle cx="12" cy="7" r="3"/><path d="M12 10v4"/></svg>',
    elevator: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 9l3-3 3 3M9 15l3 3 3-3"/></svg>',
    security: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    cctv: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
    garden: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22V12"/><path d="M5 12c0-4 3-7 7-7s7 3 7 7"/><path d="M5 17c0-3 3-5 7-5s7 2 7 5"/></svg>',
    laundry: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 6h.01M11 6h.01M14 6h.01"/></svg>',
    balcony: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 20h18M3 12h18M3 12V8l9-4 9 4v4M7 12v8M12 12v8M17 12v8"/></svg>',
    aircon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>',
    ac: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>',
    generator: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
    storage: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2M8 7V5a2 2 0 0 0-4 0v2M12 12v4M10 14h4"/></svg>',
    concierge: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20a6 6 0 0 0-12 0"/><circle cx="12" cy="10" r="4"/><path d="M2 20h20"/></svg>',
    playground: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M5 8l14 8M5 16l14-8"/></svg>',
    basketball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93c4.08 4.08 4.08 10.74 0 14.82M19.07 4.93c-4.08 4.08-4.08 10.74 0 14.82M2 12h20"/></svg>',
    tennis: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6.3 6.3c3.9 3.9 3.9 9.5 0 13.4M17.7 6.3c-3.9 3.9-3.9 9.5 0 13.4"/></svg>',
    spa: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C9 7 4 9 4 14a8 8 0 0 0 16 0c0-5-5-7-8-12z"/><path d="M12 2c3 5 8 7 8 12"/></svg>',
    lounge: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 9V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v2"/><path d="M2 11v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v2H6v-2a2 2 0 0 0-4 0z"/><path d="M4 18v2M20 18v2"/></svg>',
    kitchen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v4M8 11v9M16 3v9M16 16v2"/><circle cx="16" cy="14" r="2"/><path d="M4 3h3M4 7h3"/></svg>',
    fireplace: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2c0 6-4 8-4 13a4 4 0 0 0 8 0c0-5-4-7-4-13z"/><path d="M12 10c0 3-2 4-2 7a2 2 0 0 0 4 0c0-3-2-4-2-7z"/></svg>',
    pet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7" cy="4" r="2"/><circle cx="17" cy="4" r="2"/><circle cx="4" cy="12" r="2"/><circle cx="20" cy="12" r="2"/><path d="M12 18c-3 0-6-2-6-5 0-2 2-3 3-5h6c1 2 3 3 3 5 0 3-3 5-6 5z"/></svg>',
    bicycle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="15" r="4"/><circle cx="6" cy="15" r="4"/><path d="M6 15l4-8h4l2 4H6M14 7h2"/></svg>',
    ev: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h11l5 5v5h-2"/><circle cx="7" cy="19" r="2"/><circle cx="17" cy="19" r="2"/><path d="M9 11V7M12 11V9"/></svg>',
    solar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>',
    trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>',
    cafe: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>',
    gameroom: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="6" y1="11" x2="10" y2="11"/><line x1="8" y1="9" x2="8" y2="13"/><line x1="15" y1="12" x2="15.01" y2="12"/><line x1="17" y1="10" x2="17.01" y2="10"/><rect x="2" y="6" width="20" height="12" rx="2"/></svg>',
    bbq: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11h16M12 11V4M6 11l-2 9M18 11l2 9M9 20h6"/><circle cx="12" cy="4" r="1"/></svg>',
    clubhouse: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    shower: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h16M4 12a8 8 0 0 1 16 0"/><line x1="8" y1="12" x2="7" y2="16"/><line x1="12" y1="12" x2="12" y2="17"/><line x1="16" y1="12" x2="17" y2="16"/><line x1="6" y1="12" x2="5" y2="15"/><line x1="18" y1="12" x2="19" y2="15"/></svg>',
  };

  function getIcon(iconStr) {
    if (!iconStr) return '';
    return ICON_MAP[iconStr.toLowerCase().trim()] || '';
  }

  let html = '<div style="display:flex;flex-wrap:wrap;gap:8px;">';
  amenities.forEach(function (a) {
    const emoji = getIcon(a.icon);
    html += '<label data-amenity-label style="display:inline-flex;align-items:center;gap:7px;padding:7px 13px 7px 10px;border-radius:99px;cursor:pointer;border:1.5px solid #e2e8f0;background:#fff;font-size:12.5px;font-weight:500;color:#475569;transition:border-color .15s,background .15s,color .15s,box-shadow .15s;user-select:none;white-space:nowrap;">'
      + '<input type="checkbox" name="amenity_ids[]" value="' + a.amenity_id + '" style="display:none;">'
      + '<span data-cb-dot style="width:15px;height:15px;border-radius:50%;border:1.5px solid #cbd5e1;background:#f1f5f9;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,border-color .15s;">'
      + '<svg fill="none" stroke="#fff" stroke-width="3" viewBox="0 0 12 12" style="width:8px;height:8px;opacity:0;transition:opacity .12s;"><polyline points="2 6 5 9 10 3"/></svg>'
      + '</span>'
      + (emoji ? '<span style="display:inline-flex;align-items:center;width:14px;height:14px;flex-shrink:0;opacity:.75;">' + emoji + '</span>' : '')
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


/**
 * Collect checked amenity IDs from the modal body.
 * @param {HTMLElement} bd – modal body element
 * @returns {number[]}
 */
function getCheckedAmenityIds(bd) {
  return [...bd.querySelectorAll('input[name="amenity_ids[]"]:checked')]
    .map(cb => parseInt(cb.value, 10))
    .filter(Boolean);
}

async function openViewModal(unit) {
  const status = (unit.status || '').toLowerCase()
  const images = unit.images || [];
  const initials = unit.tenant_name
    ? unit.tenant_name.trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase()
    : null;

  const accentColor = { occupied: '#22c55e', vacant: '#3b82f6', maintenance: '#ef4444' }[status] || '#94a3b8';

  const galleryHtml = images.length ? `
    <div style="position:relative;height:260px;background:#0f172a;overflow:hidden;border-radius:16px 16px 0 0;flex-shrink:0;">
      <img id="vm-main" src="${(window.APP_BASE || '')}/${images[0]}"
        style="width:100%;height:100%;object-fit:cover;display:block;transition:opacity .2s;">
      ${images.length > 1 ? `
        <div style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:6px;">
          ${images.map((src, i) => `
            <button onclick="(function(el,src){
              var m=document.getElementById('vm-main');
              m.style.opacity='0';
              setTimeout(function(){m.src=(window.APP_BASE||'')+'/'+src;m.style.opacity='1';},200);
              document.querySelectorAll('.vm-dot').forEach(function(d){d.style.background='rgba(255,255,255,.45)';d.style.width='6px';});
              el.style.background='#fff';el.style.width='18px';
            })(this,'${src}')"
            class="vm-dot" style="
              height:6px;width:${i === 0 ? '18px' : '6px'};border-radius:99px;
              background:${i === 0 ? '#fff' : 'rgba(255,255,255,.45)'};
              border:none;cursor:pointer;padding:0;transition:all .2s;flex-shrink:0;">
            </button>`).join('')}
        </div>` : ''}
      <span style="
        position:absolute;top:14px;right:14px;
        background:${accentColor};color:#fff;
        font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;
        padding:4px 10px;border-radius:99px;">
        ${status}
      </span>
    </div>` : `
    <div style="height:180px;background:#f1f5f9;border-radius:16px 16px 0 0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#94a3b8;flex-shrink:0;">
      <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:36px;height:36px;opacity:.4;">
        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
      </svg>
      <span style="font-size:12px;">No photos for this unit</span>
      <span style="
        position:absolute;top:14px;right:14px;
        background:${accentColor};color:#fff;
        font-size:11px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;
        padding:4px 10px;border-radius:99px;">
        ${status}
      </span>
    </div>`;

  const modal = PS.openModal(`
    <style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
    <div style="padding:0;overflow:hidden;">

      ${galleryHtml}

      <div style="padding:20px 22px 0;">

        <!-- Title + price row -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:4px;">
          <div>
            <div style="font-size:18px;font-weight:800;color:var(--text);line-height:1.25;letter-spacing:-.3px;">
              ${[unit.property_name, unit.unit_number].filter(Boolean).join(' ')}${unit.unit_name ? ' — ' + unit.unit_name : ''}
            </div>
            ${unit.floor ? `
            <div style="display:flex;align-items:center;gap:4px;margin-top:5px;font-size:12px;color:var(--text-soft);">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:12px;height:12px;">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
              </svg>
              Floor ${unit.floor}${unit.unit_type ? ' · ' + unit.unit_type : ''}
            </div>` : unit.unit_type ? `<div style="margin-top:5px;font-size:12px;color:var(--text-soft);">${unit.unit_type}</div>` : ''}
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:22px;font-weight:800;color:var(--primary,#6366f1);letter-spacing:-.5px;">
              ₱${Number(unit.rent_amount).toLocaleString('en-US', { minimumFractionDigits: 0 })}
            </div>
            <div style="font-size:11px;color:var(--text-soft);margin-top:1px;">/ month</div>
          </div>
        </div>

        <!-- Divider -->
        <div style="height:1px;background:var(--border);margin:14px 0;"></div>

        <!-- About section -->
        <div style="margin-bottom:14px;">
          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text-soft);margin-bottom:8px;">About This Unit</div>
          ${unit.description ? `
          <p style="font-size:13px;color:var(--text-soft);line-height:1.65;margin:0 0 12px;">${unit.description}</p>` : ''}
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            ${[
      ['Property', unit.property_name || '—'],
      ['Type', unit.unit_type || '—'],
      ['Floor', unit.floor ? 'Floor ' + unit.floor : '—'],
      ['Status', status.charAt(0).toUpperCase() + status.slice(1)],
    ].map(([l, v]) => `
              <div style="background:var(--bg,#f8fafc);border-radius:10px;padding:10px 12px;">
                <div style="font-size:10px;color:var(--text-soft);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">${l}</div>
                <div style="font-size:13px;font-weight:600;color:var(--text);">${v}</div>
              </div>`).join('')}
          </div>
        </div>

        <!-- Divider -->
        <div style="height:1px;background:var(--border);margin:0 0 14px;"></div>

        <!-- Amenities -->
        <div style="margin-bottom:14px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text-soft);">Amenities</div>
            <button id="vm-edit-amenities-btn" title="Edit Amenities" style="width:34px;height:34px;border:1px solid rgba(99,102,241,0.2);color:var(--primary,#6366f1);background:#f8fafc;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s ease;" onmouseover="this.style.background='rgba(99,102,241,0.12)';this.style.transform='scale(1.03)';" onmouseout="this.style.background='#f8fafc';this.style.transform='scale(1)';">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;">
                <path d="M15.232 5.232a2 2 0 0 1 2.828 2.828L8.828 17.293a4 4 0 0 1-1.414.93L3 20l1.777-4.414a4 4 0 0 1 .93-1.414L15.232 5.232z"/>
                <path d="M18 7l-4-4"/>
              </svg>
            </button>
          </div>
          <div id="vm-amenities-wrap" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
            <div style="grid-column:1/-1;font-size:12px;color:var(--text-soft);font-style:italic;">Loading…</div>
          </div>
        </div>

        <!-- Divider -->
        <div style="height:1px;background:var(--border);margin:0 0 14px;"></div>

        <div style="margin-bottom:6px;">
          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text-soft);margin-bottom:9px;">Tenant</div>
            ${(status === 'occupied' || unit.tenant_name) && unit.tenant_name
      ? `<div style="display:flex;align-items:center;gap:10px;">
              <div style="width:36px;height:36px;border-radius:50%;background:#dbeafe;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#1d4ed8;flex-shrink:0;">${initials}</div>
              <div>
                <div style="font-size:14px;font-weight:600;color:var(--text);">${unit.tenant_name}</div>
                ${unit.tenant_email ? `<div style="font-size:12px;color:var(--text-soft);">${unit.tenant_email}</div>` : ''}
              </div>
            </div>`
      : '<div style="font-size:12px;color:var(--text-soft);font-style:italic;">No tenant assigned</div>'
    }
        </div>

      </div>
    </div>
    <div class="ps-modal-footer">
      <button class="btn btn-secondary" data-ps-cancel>Close</button>
    </div>
  `);

  fetchUnitAmenities(unit.unit_id).then(function (amenities) {
    const wrap = document.getElementById('vm-amenities-wrap');
    if (!wrap) return;

    const ICON_MAP = { water: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6 9 4 13 4 16a8 8 0 0 0 16 0c0-3-2-7-8-14z"/></svg>', wifi: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor"/></svg>', parking: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>', rooftop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>', gym: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v16M18 4v16M6 12h12M3 8h3M18 8h3M3 16h3M18 16h3"/></svg>', pool: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 20c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><path d="M2 16c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><circle cx="12" cy="7" r="3"/><path d="M12 10v4"/></svg>', elevator: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 9l3-3 3 3M9 15l3 3 3-3"/></svg>', security: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>', cctv: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>', garden: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22V12"/><path d="M5 12c0-4 3-7 7-7s7 3 7 7"/><path d="M5 17c0-3 3-5 7-5s7 2 7 5"/></svg>', laundry: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 6h.01M11 6h.01M14 6h.01"/></svg>', balcony: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 20h18M3 12h18M3 12V8l9-4 9 4v4M7 12v8M12 12v8M17 12v8"/></svg>', aircon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>', ac: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>', generator: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>', storage: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2M8 7V5a2 2 0 0 0-4 0v2M12 12v4M10 14h4"/></svg>', concierge: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20a6 6 0 0 0-12 0"/><circle cx="12" cy="10" r="4"/><path d="M2 20h20"/></svg>', playground: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M5 8l14 8M5 16l14-8"/></svg>', basketball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93c4.08 4.08 4.08 10.74 0 14.82M19.07 4.93c-4.08 4.08-4.08 10.74 0 14.82M2 12h20"/></svg>', tennis: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6.3 6.3c3.9 3.9 3.9 9.5 0 13.4M17.7 6.3c-3.9 3.9-3.9 9.5 0 13.4"/></svg>', spa: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C9 7 4 9 4 14a8 8 0 0 0 16 0c0-5-5-7-8-12z"/></svg>', lounge: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 9V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v2"/><path d="M2 11v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v2H6v-2a2 2 0 0 0-4 0z"/><path d="M4 18v2M20 18v2"/></svg>', kitchen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v4M8 11v9M16 3v9M16 16v2"/><circle cx="16" cy="14" r="2"/><path d="M4 3h3M4 7h3"/></svg>', pet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7" cy="4" r="2"/><circle cx="17" cy="4" r="2"/><circle cx="4" cy="12" r="2"/><circle cx="20" cy="12" r="2"/><path d="M12 18c-3 0-6-2-6-5 0-2 2-3 3-5h6c1 2 3 3 3 5 0 3-3 5-6 5z"/></svg>', bicycle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="15" r="4"/><circle cx="6" cy="15" r="4"/><path d="M6 15l4-8h4l2 4H6M14 7h2"/></svg>', ev: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h11l5 5v5h-2"/><circle cx="7" cy="19" r="2"/><circle cx="17" cy="19" r="2"/><path d="M9 11V7M12 11V9"/></svg>', solar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>', trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg>', mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>' };

    if (!amenities.length) {
      wrap.innerHTML = '<div style="grid-column:1/-1;font-size:13px;color:var(--text-soft);font-style:italic;padding:4px 0;">No amenities assigned.</div>';
      return;
    }

    wrap.innerHTML = amenities.map(function (a) {
      var svg = ICON_MAP[(a.icon || '').toLowerCase().trim()] || '';
      var iconHtml = svg
        ? '<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:var(--primary-soft,#eef2ff);flex-shrink:0;">'
        + '<span style="display:inline-flex;width:14px;height:14px;color:var(--primary,#6366f1);">' + svg + '</span>'
        + '</span>'
        : '<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:var(--bg,#f1f5f9);flex-shrink:0;">'
        + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;color:#94a3b8;"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>'
        + '</span>';
      return '<div style="display:flex;align-items:center;gap:9px;padding:9px 11px;background:var(--bg,#f8fafc);border:1px solid var(--border);border-radius:10px;">'
        + iconHtml
        + '<span style="font-size:12.5px;font-weight:500;color:var(--text);">' + a.name + '</span>'
        + '</div>';
    }).join('');
  });

  // Add edit amenities functionality
  const editBtn = document.getElementById('vm-edit-amenities-btn');
  if (editBtn) {
    editBtn.addEventListener('click', async function () {
      const wrap = document.getElementById('vm-amenities-wrap');
      const footer = document.querySelector('.ps-modal-footer');
      if (!wrap || !footer) return;

      // Fetch all property amenities
      const allAmenities = await fetchPropertyAmenities(unit.property_id);
      const currentAmenities = await fetchUnitAmenities(unit.unit_id);
      const assignedIds = new Set(currentAmenities.map(a => a.amenity_id));

      // Render picker
      renderAmenityPicker(wrap, allAmenities);

      // Pre-check assigned
      wrap.querySelectorAll('input[name="amenity_ids[]"]').forEach(cb => {
        cb.checked = assignedIds.has(parseInt(cb.value));
        const lbl = cb.closest('label');
        if (cb.checked) {
          lbl.style.borderColor = 'var(--primary,#6366f1)';
          lbl.style.background = '#eef2ff';
          lbl.style.color = '#4338ca';
          lbl.style.boxShadow = '0 0 0 3px rgba(99,102,241,.12)';
          lbl.querySelector('[data-cb-dot]').style.background = 'var(--primary,#6366f1)';
          lbl.querySelector('[data-cb-dot]').style.borderColor = 'var(--primary,#6366f1)';
          lbl.querySelector('svg').style.opacity = '1';
        }
      });

      // Change footer to Save/Cancel
      footer.innerHTML = `
        <button class="btn btn-secondary" id="vm-cancel-edit" style="min-width:100px;">Cancel</button>
        <button class="btn btn-primary" id="vm-save-amenities" style="min-width:120px;">Save Changes</button>
      `;

      // Cancel
      document.getElementById('vm-cancel-edit').addEventListener('click', () => {
        // Re-render amenities in view mode
        fetchUnitAmenities(unit.unit_id).then(function (amenities) {
          const ICON_MAP = { water: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6 9 4 13 4 16a8 8 0 0 0 16 0c0-3-2-7-8-14z"/></svg>', wifi: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor"/></svg>', parking: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>', rooftop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>', gym: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v16M18 4v16M6 12h12M3 8h3M18 8h3M3 16h3M18 16h3"/></svg>', pool: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 20c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><path d="M2 16c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><circle cx="12" cy="7" r="3"/><path d="M12 10v4"/></svg>', elevator: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 9l3-3 3 3M9 15l3 3 3-3"/></svg>', security: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>', cctv: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>', garden: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22V12"/><path d="M5 12c0-4 3-7 7-7s7 3 7 7"/><path d="M5 17c0-3 3-5 7-5s7 2 7 5"/></svg>', laundry: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 6h.01M11 6h.01M14 6h.01"/></svg>', balcony: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 20h18M3 12h18M3 12V8l9-4 9 4v4M7 12v8M12 12v8M17 12v8"/></svg>', aircon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>', ac: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>', generator: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>', storage: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2M8 7V5a2 2 0 0 0-4 0v2M12 12v4M10 14h4"/></svg>', concierge: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20a6 6 0 0 0-12 0"/><circle cx="12" cy="10" r="4"/><path d="M2 20h20"/></svg>', playground: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M5 8l14 8M5 16l14-8"/></svg>', basketball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93c4.08 4.08 4.08 10.74 0 14.82M19.07 4.93c-4.08 4.08-4.08 10.74 0 14.82M2 12h20"/></svg>', tennis: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6.3 6.3c3.9 3.9 3.9 9.5 0 13.4M17.7 6.3c-3.9 3.9-3.9 9.5 0 13.4"/></svg>', spa: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C9 7 4 9 4 14a8 8 0 0 0 16 0c0-5-5-7-8-12z"/></svg>', lounge: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 9V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v2"/><path d="M2 11v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v2H6v-2a2 2 0 0 0-4 0z"/><path d="M4 18v2M20 18v2"/></svg>', kitchen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v4M8 11v9M16 3v9M16 16v2"/><circle cx="16" cy="14" r="2"/><path d="M4 3h3M4 7h3"/></svg>', fireplace: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2c0 6-4 8-4 13a4 4 0 0 0 8 0c0-5-4-7-4-13z"/><path d="M12 10c0 3-2 4-2 7a2 2 0 0 0 4 0c0-3-2-4-2-7z"/></svg>', pet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7" cy="4" r="2"/><circle cx="17" cy="4" r="2"/><circle cx="4" cy="12" r="2"/><circle cx="20" cy="12" r="2"/><path d="M12 18c-3 0-6-2-6-5 0-2 2-3 3-5h6c1 2 3 3 3 5 0 3-3 5-6 5z"/></svg>', bicycle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="15" r="4"/><circle cx="6" cy="15" r="4"/><path d="M6 15l4-8h4l2 4H6M14 7h2"/></svg>', ev: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h11l5 5v5h-2"/><circle cx="7" cy="19" r="2"/><circle cx="17" cy="19" r="2"/><path d="M9 11V7M12 11V9"/></svg>', solar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>', trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg>', mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>' };

          if (!amenities.length) {
            wrap.innerHTML = '<div style="grid-column:1/-1;font-size:13px;color:var(--text-soft);font-style:italic;padding:4px 0;">No amenities assigned.</div>';
            return;
          }

          wrap.innerHTML = amenities.map(function (a) {
            var svg = ICON_MAP[(a.icon || '').toLowerCase().trim()] || '';
            var iconHtml = svg
              ? '<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:var(--primary-soft,#eef2ff);flex-shrink:0;">'
              + '<span style="display:inline-flex;width:14px;height:14px;color:var(--primary,#6366f1);">' + svg + '</span>'
              + '</span>'
              : '<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:var(--bg,#f1f5f9);flex-shrink:0;">'
              + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;color:#94a3b8;"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>'
              + '</span>';
            return '<div style="display:flex;align-items:center;gap:9px;padding:9px 11px;background:var(--bg,#f8fafc);border:1px solid var(--border);border-radius:10px;">'
              + iconHtml
              + '<span style="font-size:12.5px;font-weight:500;color:var(--text);">' + a.name + '</span>'
              + '</div>';
          }).join('');
        });

        // Change footer back to Close
        const footer = document.querySelector('.ps-modal-footer');
        footer.innerHTML = '<button class="btn btn-secondary" data-ps-cancel>Close</button>';
        footer.querySelector('[data-ps-cancel]')?.addEventListener('click', () => modal.close());
      });

      // Save
      document.getElementById('vm-save-amenities').addEventListener('click', async () => {
        const checkedIds = getCheckedAmenityIds(wrap);
        const fd = new FormData();
        fd.append('csrf_token', window.PS_CSRF_TOKEN || '');
        fd.append('unit_id', unit.unit_id);
        checkedIds.forEach(id => fd.append('amenity_ids[]', id));

        const saveBtn = document.getElementById('vm-save-amenities');
        saveBtn.textContent = '';
        saveBtn.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px;animation:spin 1s linear infinite;"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
        saveBtn.disabled = true;

        try {
          const res = await fetch('../../api/admin/update_unit_amenities.php', { method: 'POST', body: fd });
          const data = await res.json();
          if (data.status === 'success') {
            // Show success alert
            if (typeof showToast === 'function') showToast('Amenities updated successfully.', 'success');
            else alert('Amenities updated successfully!');

            // Re-fetch and re-render amenities in view mode
            fetchUnitAmenities(unit.unit_id).then(function (amenities) {
              const ICON_MAP = { water: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6 9 4 13 4 16a8 8 0 0 0 16 0c0-3-2-7-8-14z"/></svg>', wifi: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor"/></svg>', parking: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>', rooftop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>', gym: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v16M18 4v16M6 12h12M3 8h3M18 8h3M3 16h3M18 16h3"/></svg>', pool: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 20c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><path d="M2 16c2 0 4-2 6-2s4 2 6 2 4-2 6-2"/><circle cx="12" cy="7" r="3"/><path d="M12 10v4"/></svg>', elevator: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 9l3-3 3 3M9 15l3 3 3-3"/></svg>', security: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>', cctv: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>', garden: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22V12"/><path d="M5 12c0-4 3-7 7-7s7 3 7 7"/><path d="M5 17c0-3 3-5 7-5s7 2 7 5"/></svg>', laundry: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 6h.01M11 6h.01M14 6h.01"/></svg>', balcony: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 20h18M3 12h18M3 12V8l9-4 9 4v4M7 12v8M12 12v8M17 12v8"/></svg>', aircon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>', ac: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="8" rx="2"/><path d="M7 18l-2 2M12 18v4M17 18l2 2M6 10h.01M10 10h.01"/></svg>', generator: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>', storage: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2M8 7V5a2 2 0 0 0-4 0v2M12 12v4M10 14h4"/></svg>', concierge: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20a6 6 0 0 0-12 0"/><circle cx="12" cy="10" r="4"/><path d="M2 20h20"/></svg>', playground: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M5 8l14 8M5 16l14-8"/></svg>', basketball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93c4.08 4.08 4.08 10.74 0 14.82M19.07 4.93c-4.08 4.08-4.08 10.74 0 14.82M2 12h20"/></svg>', tennis: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6.3 6.3c3.9 3.9 3.9 9.5 0 13.4M17.7 6.3c-3.9 3.9-3.9 9.5 0 13.4"/></svg>', spa: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C9 7 4 9 4 14a8 8 0 0 0 16 0c0-5-5-7-8-12z"/></svg>', lounge: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 9V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v2"/><path d="M2 11v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v2H6v-2a2 2 0 0 0-4 0z"/><path d="M4 18v2M20 18v2"/></svg>', kitchen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v4M8 11v9M16 3v9M16 16v2"/><circle cx="16" cy="14" r="2"/><path d="M4 3h3M4 7h3"/></svg>', fireplace: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2c0 6-4 8-4 13a4 4 0 0 0 8 0c0-5-4-7-4-13z"/><path d="M12 10c0 3-2 4-2 7a2 2 0 0 0 4 0c0-3-2-4-2-7z"/></svg>', pet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7" cy="4" r="2"/><circle cx="17" cy="4" r="2"/><circle cx="4" cy="12" r="2"/><circle cx="20" cy="12" r="2"/><path d="M12 18c-3 0-6-2-6-5 0-2 2-3 3-5h6c1 2 3 3 3 5 0 3-3 5-6 5z"/></svg>', bicycle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="15" r="4"/><circle cx="6" cy="15" r="4"/><path d="M6 15l4-8h4l2 4H6M14 7h2"/></svg>', ev: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17H3a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h11l5 5v5h-2"/><circle cx="7" cy="19" r="2"/><circle cx="17" cy="19" r="2"/><path d="M9 11V7M12 11V9"/></svg>', solar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>', trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/></svg>', mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>' };

              if (!amenities.length) {
                wrap.innerHTML = '<div style="grid-column:1/-1;font-size:13px;color:var(--text-soft);font-style:italic;padding:4px 0;">No amenities assigned.</div>';
                return;
              }

              wrap.innerHTML = amenities.map(function (a) {
                var svg = ICON_MAP[(a.icon || '').toLowerCase().trim()] || '';
                var iconHtml = svg
                  ? '<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:var(--primary-soft,#eef2ff);flex-shrink:0;">'
                  + '<span style="display:inline-flex;width:14px;height:14px;color:var(--primary,#6366f1);">' + svg + '</span>'
                  + '</span>'
                  : '<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:var(--bg,#f1f5f9);flex-shrink:0;">'
                  + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;color:#94a3b8;"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>'
                  + '</span>';
                return '<div style="display:flex;align-items:center;gap:9px;padding:9px 11px;background:var(--bg,#f8fafc);border:1px solid var(--border);border-radius:10px;">'
                  + iconHtml
                  + '<span style="font-size:12.5px;font-weight:500;color:var(--text);">' + a.name + '</span>'
                  + '</div>';
              }).join('');
            });

            // Change footer back to Close
            const footer = document.querySelector('.ps-modal-footer');
            footer.innerHTML = '<button class="btn btn-secondary" data-ps-cancel>Close</button>';
            footer.querySelector('[data-ps-cancel]')?.addEventListener('click', () => modal.close());

          } else {
            if (typeof showToast === 'function') showToast('Failed to update amenities: ' + (data.message || 'Unknown error'), 'error');
            else alert('Failed to update amenities: ' + (data.message || 'Unknown error'));
          }
        } catch (e) {
          console.error(e);
          if (typeof showToast === 'function') showToast('Network error occurred.', 'error');
          else alert('Network error occurred.');
        } finally {
          saveBtn.innerHTML = 'Save Changes';
          saveBtn.disabled = false;
        }
      });
    });
  }
}
function attachViewHandler(btn) {
  btn.addEventListener('click', function () {
    try { openViewModal(JSON.parse(this.dataset.unit)); }
    catch (e) { console.error('View parse error', e); }
  });
}
document.querySelectorAll('.view-unit-btn').forEach(attachViewHandler);

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
          <option value="">— Select Property —</option>
          ${opts}
        </select>
      </div>
      <div class="form-group">
        <label>Unit Type</label>
        <select id="m-unit-type">
          <option value="">— Select Type —</option>
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
        <textarea id="m-description" rows="3" placeholder="Describe the unit — features, highlights, furnishing details…" style="width:100%;resize:vertical;font-size:13px;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;color:var(--text);background:var(--input-bg,#fff);line-height:1.5;"></textarea>
        <div style="text-align:right;font-size:11px;color:var(--text-soft);margin-top:4px;">
          <span id="m-desc-count">0</span> / 500
        </div>
      </div>

      <!-- ── Amenities ── -->
      <div class="form-group full" id="m-amenities-section" style="display:none;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
          <label style="margin:0;font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--text-soft);">
            Amenities
          </label>
          <span id="m-amenities-count" style="font-size:11px;color:var(--primary,#6366f1);font-weight:500;display:none;"></span>
        </div>
        <div id="m-amenities-loading" style="display:none;padding:10px 0;">
          <div style="display:flex;gap:8px;">
            <div style="height:32px;width:90px;border-radius:99px;background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200% 100%;animation:shimmer 1.2s infinite;"></div>
            <div style="height:32px;width:110px;border-radius:99px;background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200% 100%;animation:shimmer 1.2s infinite .2s;"></div>
            <div style="height:32px;width:80px;border-radius:99px;background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 50%,#f1f5f9 75%);background-size:200% 100%;animation:shimmer 1.2s infinite .4s;"></div>
          </div>
          <style>@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}</style>
        </div>
        <div id="m-amenities-wrap"></div>
      </div>

      <!-- ── Unit Photos ── -->
      <div class="form-group full">
        <label>Unit Photos</label>
        <div id="m-dropzone" style="
          border: 2px dashed #cbd5e1;
          border-radius: 12px;
          padding: 32px 20px;
          text-align: center;
          cursor: pointer;
          transition: border-color .2s, background .2s;
          background: #f8fafc;
          position: relative;
        ">
          <input type="file" id="m-images" accept="image/*" multiple style="
            position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:2;
          ">
          <div id="m-dropzone-inner" style="pointer-events:none;">
            <div style="
              width:48px;height:48px;border-radius:12px;
              background:linear-gradient(135deg,#eef2ff 0%,#e0e7ff 100%);
              display:flex;align-items:center;justify-content:center;
              margin:0 auto 12px;
            ">
              <svg fill="none" stroke="var(--primary,#6366f1)" stroke-width="1.75" viewBox="0 0 24 24" style="width:22px;height:22px;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
              </svg>
            </div>
            <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">
              Drop photos here or <span style="color:var(--primary,#6366f1);">browse files</span>
            </div>
            <div style="font-size:11.5px;color:#94a3b8;">
              PNG, JPG, WEBP supported &nbsp;·&nbsp; Up to 10 photos
            </div>
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

        if (!propertyId) {
          section.style.display = 'none';
          return;
        }

        section.style.display = 'block';
        loading.style.display = 'block';
        wrap.style.display = 'none';

        const amenities = await fetchPropertyAmenities(propertyId);

        loading.style.display = 'none';
        wrap.style.display = '';
        renderAmenityPicker(wrap, amenities);

        // Show count badge once loaded
        if (amenities.length) {
          countBadge.textContent = amenities.length + ' available';
          countBadge.style.display = 'inline';
        }

        // Update selected count when any checkbox is toggled
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

      // ── Dropzone & Image Preview ───────────────────────────────────────────

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

      // Drag over
      dropzone.addEventListener('dragover', e => {
        e.preventDefault();
        dropzone.style.borderColor = 'var(--primary,#6366f1)';
        dropzone.style.background = '#eef2ff';
      });

      // Drag leave
      dropzone.addEventListener('dragleave', e => {
        if (!dropzone.contains(e.relatedTarget)) {
          dropzone.style.borderColor = '#cbd5e1';
          dropzone.style.background = '#f8fafc';
        }
      });

      // Drop
      dropzone.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.style.borderColor = '#cbd5e1';
        dropzone.style.background = '#f8fafc';
        const dropped = [...e.dataTransfer.files].filter(f => f.type.startsWith('image/'));
        addFiles(dropped);
      });

      // File input change
      bd.querySelector('#m-images').addEventListener('change', function () {
        addFiles([...this.files]);
        this.value = '';
      });

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

          const data = await fetch('../../api/admin/add_unit.php', {
            method: 'POST', body: fd
          }).then(r => r.json());

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
         <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="width:36px;height:36px;">
           <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
         </svg>
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
        ${unit.unit_type ? `<span class="meta-item">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;">
            <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path d="M9 21V12h6v9"/>
          </svg>${unit.unit_type}</span>` : ''}
        ${unit.floor ? `<span class="meta-item">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;">
            <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>
          </svg>Floor ${unit.floor}</span>` : ''}
        ${unit.tenant_name ? `<span class="meta-item">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>${unit.tenant_name}</span>` : ''}
      </div>
      <div class="tags">
        ${tags.slice(0, 4).map(t => `<span class="tag">${t}</span>`).join('')}
      </div>
      <div class="footer">
        <div class="price">
          <span class="price-value">₱${Number(unit.rent_amount).toLocaleString('en-US', { minimumFractionDigits: 0 })}</span>
          <span class="price-label">/ month</span>
        </div>
        <div class="card-actions">
          <button class="btn-view view-unit-btn" data-unit="${unitJson}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>View
          </button>
          <button class="btn-del delete-unit-btn" data-id="${unit.unit_id}" data-name="${unit.unit_number}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:13px;height:13px;">
              <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6M10 11v6M14 11v6M9 6V4h6v2"/>
            </svg>Delete
          </button>
        </div>
      </div>
    </div>`;
}

function addUnitCard(unit) {
  document.getElementById('empty-state-row')?.remove();
  const status = (unit.status || '').toLowerCase();
  const search = [unit.unit_number, unit.unit_name, unit.property_name, unit.unit_type, unit.tenant_name]
    .join(' ').toLowerCase();

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
        const data = await fetch('../../api/admin/delete_unit.php', {
          method: 'POST', body: fd
        }).then(r => r.json());

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
              e.innerHTML = `
                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"
                  style="width:44px;height:44px;margin:0 auto 14px;display:block;opacity:.25;">
                  <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>
                </svg>
                <div style="font-size:15px;font-weight:600;margin-bottom:4px;">No units yet</div>
                <div style="font-size:13px;opacity:.7;">Click "Add Unit" to get started.</div>`;
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