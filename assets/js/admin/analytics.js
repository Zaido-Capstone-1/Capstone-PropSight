const blue = '#2563c4', gold = '#deaf37', grn = '#2ECC71', red = '#E74C3C';
const sourceColors = ['#2563c4', '#2ECC71', '#deaf37', '#1a3d7c', '#93c5fd', '#E74C3C'];
const d = window.__PS_ANALYTICS__;

// ── Empty-state helper ────────────────────────────────────────────────────
// Replaces a canvas's parent .chart-wrap with a centered "no data" message.
// Pass containerId when the chart has a sibling (like a legend list) so the
// whole row gets replaced and the message centers across the full card
// width, rather than just centering inside the small chart box.
function showEmptyState(canvasId, message = 'No data available yet', containerId = null) {
  const target = containerId
    ? document.getElementById(containerId)
    : (document.getElementById(canvasId)?.closest('.chart-wrap') || document.getElementById(canvasId)?.parentElement);
  if (!target) return;
  target.innerHTML = `
    <div style="width:100%;min-height:180px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#94a3b8;">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M3 3v18h18" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M7 15l4-4 3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span style="font-size:13px;font-weight:500;">${message}</span>
    </div>`;
}

// ── Has-data flags (computed server-side from real DB totals) ────────────
// See lib/admin-queries/analytics_queries.php — these come from actual
// SUM/COUNT query results, not from inspecting the chart arrays here.
// That matters: a property or month can legitimately have 0 revenue/bookings
// without the *whole dataset* being empty, so we don't want to infer
// "no data" from a zero sum on the client.
const hasRevByProp = !!d.hasRevenueData;
const hasSourceData = !!d.hasBookingData;
const hasMonthlyBookings = !!d.hasBookingData;
const hasRevTrend = !!d.hasRevenueData;

// ── Revenue by Property (% share) ────────────────────────────────────────
if (!hasRevByProp) {
  showEmptyState('revByPropChart', 'No revenue recorded yet');
} else {
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
}

// ── Monthly Bookings + Forecast ───────────────────────────────────────────
if (!hasMonthlyBookings) {
  showEmptyState('monthlyOccChart', 'No bookings recorded yet');
} else {
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
}

// ── Booking Status Donut (already %) ─────────────────────────────────────
if (!hasSourceData) {
  showEmptyState('sourceDonut', 'No bookings yet', 'sourceRow');
} else {
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
}

// ── Revenue Trend + Forecast ──────────────────────────────────────────────
if (!hasRevTrend) {
  showEmptyState('revTrendChart', 'No revenue recorded yet');
} else {
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
}