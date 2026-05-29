const blue = '#2563c4', gold = '#deaf37', grn = '#2ECC71', red = '#E74C3C';
const sourceColors = ['#2563c4', '#2ECC71', '#deaf37', '#1a3d7c', '#93c5fd', '#E74C3C'];
const d = window.__PS_ANALYTICS__;

// ── Revenue by Property (% share) ────────────────────────────────────────
new Chart(document.getElementById('revByPropChart'), {
  type: 'bar',
  data: {
    labels: d.revByPropLabels,
    datasets: [{
      label: 'Revenue Share', data: d.revByPropPct,
      backgroundColor: [blue, grn, gold, '#dbeafe', '#1a3d7c', red],
      borderRadius: 8, borderSkipped: false
    }]
  },
  options: {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ctx.parsed.x + '% of total revenue' } }
    },
    scales: {
      x: {
        max: 100,
        grid: { display: false },
        ticks: { callback: v => v + '%', font: { size: 11 } }
      },
      y: { grid: { display: false }, ticks: { font: { size: 12, weight: '600' } } }
    }
  }
});

// ── Monthly Bookings + Forecast ───────────────────────────────────────────
new Chart(document.getElementById('monthlyOccChart'), {
  type: 'line',
  data: {
    labels: d.activeMonthLabels,
    datasets: [
      {
        label: 'Actual Bookings',
        data: d.activeMonthBookings.map((v, i) => i < d.currentMonth ? v : null),
        borderColor: blue, borderWidth: 2.5, backgroundColor: 'rgba(37,99,196,0.08)',
        fill: true, tension: .4, pointBackgroundColor: blue, pointRadius: 4,
        pointHoverRadius: 7, spanGaps: false
      },
      {
        label: 'Forecast',
        data: d.forecastBookings,
        borderColor: gold, borderWidth: 2, borderDash: [6, 4],
        backgroundColor: 'rgba(222,175,55,0.06)',
        fill: false, tension: .4, pointRadius: 3, pointBackgroundColor: gold,
        spanGaps: true
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: true, labels: { boxWidth: 12, font: { size: 11 } } }
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11 } } },
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { stepSize: 1, font: { size: 11 } } }
    }
  }
});

// ── Booking Status Donut (already %) ─────────────────────────────────────
const srcTotal = d.srcData.reduce((a, b) => a + b, 0) || 1;

new Chart(document.getElementById('sourceDonut'), {
  type: 'doughnut',
  data: {
    labels: d.srcLabels,
    datasets: [{
      data: d.srcData,
      backgroundColor: sourceColors.slice(0, d.srcLabels.length),
      borderWidth: 0, hoverOffset: 0
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false, cutout: '68%',
    plugins: { legend: { display: false } }
  }
});

const legend = document.getElementById('sourceLegend');
d.srcLabels.forEach((lbl, i) => {
  const pct = Math.round(d.srcData[i] / srcTotal * 100);
  legend.innerHTML += `
    <div class="legend-item">
      <div class="legend-dot" style="background:${sourceColors[i]};"></div>
      <span class="legend-label">${lbl}</span>
      <span class="legend-val">${pct}%</span>
    </div>`;
});

// ── Revenue Trend + Forecast ──────────────────────────────────────────────
new Chart(document.getElementById('revTrendChart'), {
  type: 'line',
  data: {
    labels: d.monthLabels,
    datasets: [
      {
        label: 'Actual Revenue',
        data: d.revActual,
        borderColor: grn, borderWidth: 2.5, backgroundColor: 'rgba(46,204,113,0.08)',
        fill: true, tension: .4, pointRadius: 4, spanGaps: true
      },
      {
        label: 'Forecast',
        data: d.forecastRev,
        borderColor: gold, borderWidth: 2, borderDash: [6, 4],
        backgroundColor: 'rgba(222,175,55,0.06)',
        fill: false, tension: .4, pointRadius: 3, pointBackgroundColor: gold,
        spanGaps: true
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { display: true, labels: { boxWidth: 12, font: { size: 11 } } },
      tooltip: {
        callbacks: {
          label: ctx => ctx.parsed.y != null ? ctx.dataset.label + ': ₱' + ctx.parsed.y.toLocaleString() : '—'
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11 } } },
      y: {
        beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' },
        ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v), font: { size: 11 } }
      }
    }
  }
});