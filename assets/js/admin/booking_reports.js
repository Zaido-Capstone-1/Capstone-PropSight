(() => {
  const D = window.__PS_BOOKING_REPORTS__;

  // Replaces a canvas's parent .chart-wrap with a centered "no data" message.
  function showEmptyState(canvasId, message = 'No data available yet') {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const wrap = canvas.closest('.chart-wrap') || canvas.parentElement;
    wrap.innerHTML = `
      <div style="height:100%;min-height:120px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#94a3b8;">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M3 3v18h18" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M7 15l4-4 3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span style="font-size:13px;font-weight:500;">${message}</span>
      </div>`;
  }

  /* ── 1. Monthly Booking Volume ───────────────────────────── */
  const volCtx = document.getElementById('bookingVolChart');
  if (volCtx && !D.hasBookingData) {
    showEmptyState('bookingVolChart', 'No bookings recorded yet');
  } else if (volCtx) {
    new Chart(volCtx, {
      type: 'bar',
      data: {
        labels: D.mLabels,
        datasets: [
          {
            label: 'Confirmed',
            data: D.mConfirmed,
            backgroundColor: 'rgba(37,99,196,0.85)',
            borderRadius: 6,
            stack: 'a'
          },
          {
            label: 'Cancelled',
            data: D.mCancelled,
            backgroundColor: 'rgba(231,76,60,0.75)',
            borderRadius: 6,
            stack: 'a'
          },
          {
            label: 'Pending',
            data: D.mPending,
            backgroundColor: 'rgba(222,175,55,0.8)',
            borderRadius: 6,
            stack: 'a'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'bottom',
            labels: { usePointStyle: true, pointStyle: 'circle', font: { size: 11 }, padding: 16 }
          },
          tooltip: {
            callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y}` }
          }
        },
        scales: {
          x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 } } },
          y: { stacked: true, grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 11 }, stepSize: 1 } }
        }
      }
    });
  }

  /* ── 2. Booking Status Donut ─────────────────────────────── */
  const donutCtx = document.getElementById('statusDonut');
  if (donutCtx && !D.hasBookingData) {
    showEmptyState('statusDonut', 'No bookings yet');
  } else if (donutCtx) {
    new Chart(donutCtx, {
      type: 'doughnut',
      data: {
        labels: D.donutLabels,
        datasets: [{
          data: D.donutData,
          backgroundColor: D.donutColors,
          borderWidth: 3,
          borderColor: '#fff',
          hoverOffset: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` }
          }
        }
      }
    });
  }

  /* ── 3. Payment Methods ──────────────────────────────────── */
  const payCtx = document.getElementById('paymentChart');
  if (payCtx && D.payLabels) {
    const payColors = ['#2563c4', '#deaf37', '#2ECC71', '#93c5fd', '#E74C3C'];
    new Chart(payCtx, {
      type: 'bar',
      data: {
        labels: D.payLabels,
        datasets: [{
          label: 'Bookings',
          data: D.payData,
          backgroundColor: payColors.slice(0, D.payLabels.length).map(c => c + 'cc'),
          borderColor: payColors.slice(0, D.payLabels.length),
          borderWidth: 2,
          borderRadius: 8,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: { label: ctx => ` ${ctx.parsed.x} booking${ctx.parsed.x !== 1 ? 's' : ''}` }
          }
        },
        scales: {
          x: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 11 }, stepSize: 1 } },
          y: { grid: { display: false }, ticks: { font: { size: 12, weight: '600' } } }
        }
      },
      plugins: [{
        id: 'barLabels',
        afterDatasetsDraw(chart) {
          const { ctx, data } = chart;
          chart.getDatasetMeta(0).data.forEach((bar, i) => {
            const val = data.datasets[0].data[i];
            if (!val) return;
            ctx.save();
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 12px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(val, bar.x - 14, bar.y);
            ctx.restore();
          });
        }
      }]
    });
  }

  /* ── 4. Leaflet Map ──────────────────────────────────────── */
  const mapEl = document.getElementById('guestMap');
  if (mapEl && typeof L !== 'undefined') {

    // Normalize stored nationality values to match GeoJSON country names.
    // The GeoJSON uses non-standard names for several countries (e.g. "USA"
    // instead of "United States"), so we map common variants here.
    const NATIONALITY_MAP = {
      // United States variants
      'United States':                   'USA',
      'United States of America':        'USA',
      'American':                        'USA',
      'US':                              'USA',
      'U.S.':                            'USA',
      'U.S.A.':                          'USA',
      // Serbia
      'Serbia':                          'Republic of Serbia',
      // Congo variants
      'Congo':                           'Republic of the Congo',
      'DR Congo':                        'Democratic Republic of the Congo',
      'DRC':                             'Democratic Republic of the Congo',
      'Democratic Republic of Congo':    'Democratic Republic of the Congo',
      // Tanzania
      'Tanzania':                        'United Republic of Tanzania',
      // Ivory Coast
      'Cote d\'Ivoire':                  'Ivory Coast',
      "Côte d'Ivoire":                   'Ivory Coast',
      // Timor-Leste
      'Timor-Leste':                     'East Timor',
      // South Korea variants
      'Korea':                           'South Korea',
      'Republic of Korea':               'South Korea',
      // North Korea variants
      "Democratic People's Republic of Korea": 'North Korea',
      // Russia variants
      'Russian Federation':              'Russia',
      // Iran variants
      'Islamic Republic of Iran':        'Iran',
      // Syria variants
      'Syrian Arab Republic':            'Syria',
      // Vietnam variants
      'Viet Nam':                        'Vietnam',
      // Myanmar/Burma
      'Burma':                           'Myanmar',
      // Czechia
      'Czechia':                         'Czech Republic',
      // North Macedonia
      'North Macedonia':                 'Macedonia',
      // Eswatini
      'Eswatini':                        'Swaziland',
      // Bolivia
      'Plurinational State of Bolivia':  'Bolivia',
      // Venezuela
      'Bolivarian Republic of Venezuela': 'Venezuela',
    };

    const bookingData = {};
    D.demographics.forEach(d => {
      const geoName = NATIONALITY_MAP[d.nationality] || d.nationality;
      bookingData[geoName] = { bookings: d.bookings, guests: d.guests, revenue: d.revenue };
    });
    const maxBookings = Math.max(...D.demographics.map(d => d.bookings), 1);
    function getColor(b) {
      const r = b / maxBookings;
      if (r === 0) return '#e2e8f0';
      if (r < 0.2) return '#bfdbfe';
      if (r < 0.4) return '#93c5fd';
      if (r < 0.6) return '#60a5fa';
      if (r < 0.8) return '#3b82f6';
      return '#1d4ed8';
    }
    const map = L.map(mapEl, {
      zoomControl: false, scrollWheelZoom: false,
      minZoom: 2, maxZoom: 5,
      maxBounds: [[-90, -180], [90, 180]], maxBoundsViscosity: 1.0
    }).setView([20, 100], 2);
    L.control.zoom({ position: 'topright' }).addTo(map);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      attribution: '© OpenStreetMap contributors © CARTO', opacity: 0.4, noWrap: true,
      subdomains: 'abcd', maxZoom: 19
    }).addTo(map);
    fetch('../../assets/data/world.geojson')
      .then(r => r.json())
      .then(geo => {
        L.geoJSON(geo, {
          style: f => {
            const e = bookingData[f.properties.name || ''];
            return { fillColor: e ? getColor(e.bookings) : '#e2e8f0', weight: 0.5, color: '#94a3b8', fillOpacity: e ? 0.85 : 0.4 };
          },
          onEachFeature: (f, layer) => {
            const e = bookingData[f.properties.name || ''];
            if (e) {
              layer.bindTooltip(`<strong>${f.properties.name}</strong><br>Bookings: ${e.bookings}<br>Guests: ${e.guests}<br>Revenue: ₱${e.revenue.toLocaleString()}`, { sticky: true });
              layer.on('mouseover', ev => ev.target.setStyle({ weight: 2, color: '#1d4ed8' }));
              layer.on('mouseout', ev => ev.target.setStyle({ weight: 0.5, color: '#94a3b8' }));
            }
          }
        }).addTo(map);
      })
      .catch(() => {
        mapEl.innerHTML = '<div style="padding:40px;text-align:center;color:#64748b;font-size:13px;">Map unavailable</div>';
      });
  }

  /* ── 5. Export CSV ───────────────────────────────────────── */
  window.exportCSV = function () {
    const rows = [['Month', 'Confirmed', 'Cancelled', 'Pending']];
    D.mLabels.forEach((l, i) => rows.push([l, D.mConfirmed[i], D.mCancelled[i], D.mPending[i]]));
    const csv = rows.map(r => r.join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'booking_report.csv';
    a.click();
  };

})();