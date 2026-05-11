const blue = '#2563c4', gold = '#deaf37', grn = '#2ECC71', red = '#E74C3C';
const sourceColors = ['#2563c4', '#2ECC71', '#deaf37', '#1a3d7c', '#93c5fd', '#E74C3C'];

new Chart(document.getElementById('revByPropChart'), {
  type: 'bar',
  data: {
    labels: window.__PS_ANALYTICS__.revByPropLabels,
    datasets: [{
      label: 'Revenue', data: window.__PS_ANALYTICS__.revByPropData,
      backgroundColor: [blue, grn, gold, '#dbeafe', '#1a3d7c', red],
      borderRadius: 8, borderSkipped: false
    }]
  },
  options: {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => '₱ ' + ctx.parsed.x.toLocaleString() } } },
    scales: {
      x: { grid: { display: false }, ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v), font: { size: 11 } } },
      y: { grid: { display: false }, ticks: { font: { size: 12, weight: '600' } } }
    }
  }
});

new Chart(document.getElementById('monthlyOccChart'), {
  type: 'line',
  data: {
    labels: window.__PS_ANALYTICS__.activeMonthLabels,
    datasets: [{
      label: 'Bookings', data: window.__PS_ANALYTICS__.activeMonthBookings,
      borderColor: blue, borderWidth: 2.5, backgroundColor: 'rgba(37,99,196,0.08)',
      fill: true, tension: .4, pointBackgroundColor: blue, pointRadius: 4, pointHoverRadius: 7
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11 } } },
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { stepSize: 1, font: { size: 11 } } }
    }
  }
});

const srcLabels = window.__PS_ANALYTICS__.srcLabels;
const srcData = window.__PS_ANALYTICS__.srcData;
const srcTotal = srcData.reduce((a, b) => a + b, 0) || 1;

new Chart(document.getElementById('sourceDonut'), {
  type: 'doughnut',
  data: { labels: srcLabels, datasets: [{ data: srcData, backgroundColor: sourceColors.slice(0, srcLabels.length), borderWidth: 0, hoverOffset: 0 }] },
  options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } }
});

const legend = document.getElementById('sourceLegend');
srcLabels.forEach((lbl, i) => {
  const pct = Math.round(srcData[i] / srcTotal * 100);
  legend.innerHTML += `
        <div class="legend-item">
            <div class="legend-dot" style="background:${sourceColors[i]};"></div>
            <span class="legend-label">${lbl}</span>
            <span class="legend-val">${pct}%</span>
        </div>`;
});

new Chart(document.getElementById('revTrendChart'), {
  type: 'line',
  data: {
    labels: window.__PS_ANALYTICS__.monthLabels,
    datasets: [{
      label: 'Revenue', data: window.__PS_ANALYTICS__.revActual,
      borderColor: grn, borderWidth: 2.5, backgroundColor: 'rgba(46,204,113,0.08)',
      fill: true, tension: .4, pointRadius: 4, spanGaps: true
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.parsed.y != null ? '₱ ' + ctx.parsed.y.toLocaleString() : '—' } } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11 } } },
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { callback: v => '₱' + (v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v), font: { size: 11 } } }
    }
  }
});
