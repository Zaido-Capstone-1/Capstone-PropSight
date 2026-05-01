(() => {
  const D = window.__PS_BOOKING_REPORTS__;

  /* ── 1. Monthly Booking Volume (stacked bar) ─────────────── */
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
            backgroundColor: 'rgba(37,99,196,0.8)',
            borderRadius: 4,
            stack: 'a'
          },
          {
            label: 'Cancelled',
            data: D.mCancelled,
            backgroundColor: 'rgba(231,76,60,0.65)',
            borderRadius: 4,
            stack: 'a'
          },
          {
            label: 'Pending',
            data: D.mPending,
            backgroundColor: 'rgba(222,175,55,0.75)',
            borderRadius: 4,
            stack: 'a'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { usePointStyle: true, font: { size: 11 } } }
        },
        scales: {
          x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 } } },
          y: { stacked: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 11 }, stepSize: 1 } }
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
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed }
          }
        }
      }
    });
  }

  /* ── 3. Payment Methods (horizontal bar) ─────────────────── */
  const payCtx = document.getElementById('paymentChart');
  if (payCtx && D.payLabels) {
    new Chart(payCtx, {
      type: 'bar',
      data: {
        labels: D.payLabels,
        datasets: [{
          label: 'Bookings',
          data: D.payData,
          backgroundColor: ['#2563c4', '#deaf37', '#2ECC71', '#93c5fd', '#E74C3C'],
          borderRadius: 6,
          borderSkipped: false
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 11 }, stepSize: 1 } },
          y: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
      }
    });
  }

  /* ── 4. Leaflet World Map (Guest Demographics) ───────────── */
  const mapEl = document.getElementById('guestMap');
  if (mapEl && typeof L !== 'undefined' && D.demographics && D.demographics.length) {
    const bookingData = {};
    D.demographics.forEach(d => {
      bookingData[d.nationality] = { bookings: d.bookings, guests: d.guests, revenue: d.revenue };
    });

    const maxBookings = Math.max(...D.demographics.map(d => d.bookings), 1);

    function getColor(bookings) {
      const ratio = bookings / maxBookings;
      if (ratio === 0) return '#e2e8f0';
      if (ratio < 0.2) return '#bfdbfe';
      if (ratio < 0.4) return '#93c5fd';
      if (ratio < 0.6) return '#60a5fa';
      if (ratio < 0.8) return '#3b82f6';
      return '#1d4ed8';
    }

    const map = L.map(mapEl, {
      zoomControl: false,     // disable default position
      scrollWheelZoom: false,
      minZoom: 2,
      maxZoom: 5,
      maxBounds: [[-90, -180], [90, 180]],
      maxBoundsViscosity: 1.0
    }).setView([20, 100], 2);   // centered on Asia-Pacific

    // Add zoom control on the RIGHT to avoid sidebar overlap
    L.control.zoom({ position: 'topright' }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      opacity: 0.4,
      noWrap: true       // ← stops the map from repeating horizontally
    }).addTo(map);

    fetch('../../assets/data/world.geojson')
      .then(r => r.json())
      .then(geo => {
        L.geoJSON(geo, {
          style: feature => {
            const name = feature.properties.name || '';
            const entry = bookingData[name];
            return {
              fillColor: entry ? getColor(entry.bookings) : '#e2e8f0',
              weight: 0.5,
              color: '#94a3b8',
              fillOpacity: entry ? 0.85 : 0.4
            };
          },
          onEachFeature: (feature, layer) => {
            const name = feature.properties.name || '';
            const entry = bookingData[name];
            if (entry) {
              layer.bindTooltip(
                `<strong>${name}</strong><br>Bookings: ${entry.bookings}<br>Guests: ${entry.guests}<br>Revenue: ₱${entry.revenue.toLocaleString()}`,
                { sticky: true }
              );
              layer.on('mouseover', e => e.target.setStyle({ weight: 2, color: '#1d4ed8' }));
              layer.on('mouseout', e => e.target.setStyle({ weight: 0.5, color: '#94a3b8' }));
            }
          }
        }).addTo(map);
      })
      .catch(err => {
        console.error('Map load error:', err);
        mapEl.innerHTML = '<div style="padding:40px;text-align:center;color:#64748b;font-size:13px;">Map unavailable — place world.geojson in assets/data/</div>';
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