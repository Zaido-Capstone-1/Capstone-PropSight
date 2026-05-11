(() => {
  const D = window.__PS_BOOKING_REPORTS__;

  /* ── 1. Monthly Booking Volume ───────────────────────────── */
  const volCtx = document.getElementById('bookingVolChart');
  if (volCtx) {
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
  if (donutCtx) {
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
  if (mapEl && typeof L !== 'undefined' && D.demographics && D.demographics.length) {
    const bookingData = {};
    D.demographics.forEach(d => {
      bookingData[d.nationality] = { bookings: d.bookings, guests: d.guests, revenue: d.revenue };
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
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors', opacity: 0.4, noWrap: true
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