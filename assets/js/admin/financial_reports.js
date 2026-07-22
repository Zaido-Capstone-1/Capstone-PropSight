let chartData = window.__PS_FINANCIAL__.chartData;
let selectedYear = window.__PS_FINANCIAL__.selectedYear;

// Authoritative "has data" flags — computed server-side from real SUM
// query totals (see lib/admin-queries/financial_reports_queries.php),
// not from inspecting the chart arrays here. Updated on each AJAX year
// switch using the same total_income/total_expenses/etc. fields.
let hasFinancialActivity = !!window.__PS_FINANCIAL__.hasFinancialActivity;
let hasRevenueMix        = !!window.__PS_FINANCIAL__.hasRevenueMix;
let hasExpenseBreakdown  = !!window.__PS_FINANCIAL__.hasExpenseBreakdown;

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

/* ── Design tokens (matching PropSight navy/gold system) ── */
const C = {
  navy:    '#0f2447',
  navyMid: '#153060',
  blue:    '#2563c4',
  blueLt:  '#3b82f6',
  gold:    '#deaf37',
  goldDk:  '#cfab57',
  green:   '#2ECC71',
  red:     '#E74C3C',
  orange:  '#F39C12',
  purple:  '#8B5CF6',
  teal:    '#14B8A6',
  soft:    '#64748b',
  border:  '#dbeafe',
};

const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

let plChartInstance     = null;
let revMixChartInstance = null;
let expBreakChartInstance = null;
let autoRefreshInterval = null;

/* ── Helpers ── */
function formatCurrency(amount) {
  if (amount >= 1_000_000) return '₱ ' + (amount / 1_000_000).toFixed(2) + 'M';
  if (amount >= 1_000)     return '₱ ' + (amount / 1_000).toFixed(1) + 'K';
  return '₱ ' + Math.round(amount).toLocaleString();
}

function makeGradient(ctx, colorTop, colorBot) {
  const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
  g.addColorStop(0, colorTop);
  g.addColorStop(1, colorBot);
  return g;
}

const baseTooltip = {
  backgroundColor: '#0f2447',
  titleColor: '#deaf37',
  bodyColor: 'rgba(255,255,255,0.88)',
  borderColor: 'rgba(222,175,55,0.3)',
  borderWidth: 1,
  padding: 12,
  cornerRadius: 10,
  titleFont: { size: 12, weight: '700' },
  bodyFont: { size: 12 },
  displayColors: true,
  boxWidth: 10,
  boxHeight: 10,
  boxPadding: 4,
};

const baseScales = {
  x: {
    grid: { display: false },
    border: { display: false },
    ticks: { color: C.soft, font: { size: 11, family: "'DM Sans', sans-serif" } },
  },
  y: {
    grid: { color: 'rgba(203,213,225,0.35)', drawBorder: false },
    border: { display: false, dash: [4, 4] },
    ticks: { callback: v => '₱ ' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v), color: C.soft, font: { size: 11 } },
  },
};

/* ── Year change ── */
function handleYearChange(year) {
  selectedYear = parseInt(year);
  loadFinancialData(selectedYear);
}

function loadFinancialData(year) {
  fetch('../../endpoints/financial_report.php?year=' + year, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;

      // API returns flat keys; remap to chartData shape used by charts
      chartData = {
        revenue:     data.monthly_income    || [],
        expenses:    data.monthly_expenses  || [],
        refunds:     data.monthly_refunds   || [],
        maintenance: data.maintenance       || chartData.maintenance || [],
        utilities:   data.utilities         || chartData.utilities   || [],
        salaries:    data.salaries          || chartData.salaries    || [],
        admin:       data.admin             || chartData.admin       || [],
        revenue_mix: data.revenue_mix       || chartData.revenue_mix || {},
        pnl_summary: data.pnl_summary       || [],
      };

      updateStatistics({
        total_revenue:   data.total_income    || 0,
        total_expenses:  data.total_expenses  || 0,
        total_refunds:   data.total_refunds   || 0,
        net_profit:      data.net_profit      || 0,
        roi:             data.roi             || 0,
        revenue_growth:  data.revenue_growth  || 0,
        expense_growth:  data.expense_growth  || 0,
        profit_growth:   data.profit_growth   || 0,
      });

      // Recompute has-data flags from this year's authoritative totals
      // (same fields the stat cards use), not from the chart arrays.
      hasFinancialActivity = (data.total_income || 0) > 0 || (data.total_expenses || 0) > 0 || (data.total_refunds || 0) > 0;
      hasRevenueMix = Object.keys(data.revenue_mix || {}).length > 0;
      hasExpenseBreakdown = ['maintenance', 'utilities', 'salaries', 'admin']
        .reduce((sum, k) => sum + (data[k] || []).reduce((a, b) => a + Number(b || 0), 0), 0) > 0;

      updateCharts();
      updateTable(chartData.pnl_summary);
      updateLegend(chartData.revenue_mix);
    })
    .catch(e => console.error('Fetch error:', e));
}

/* ── Stat cards ── */
function updateStatistics(stats) {
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('totalRevenue',  formatCurrency(stats.total_revenue  || 0));
  set('totalExpenses', formatCurrency(stats.total_expenses || 0));
  set('netProfit',     formatCurrency(stats.net_profit     || 0));
  set('roi',           (stats.roi || 0) + '%');
  if (document.getElementById('totalRefunds'))
    set('totalRefunds', formatCurrency(stats.total_refunds || 0));
  const growthEl = document.getElementById('revenueGrowth');
  if (growthEl) growthEl.textContent = ((stats.revenue_growth || 0) >= 0 ? '↑ ' : '↓ ') + Math.abs(stats.revenue_growth || 0) + '%';
  const expGrEl = document.getElementById('expenseGrowth');
  if (expGrEl) expGrEl.textContent = ((stats.expense_growth || 0) >= 0 ? '↑ ' : '↓ ') + Math.abs(stats.expense_growth || 0) + '%';
  const prGrEl = document.getElementById('profitGrowth');
  if (prGrEl) prGrEl.textContent = ((stats.profit_growth || 0) >= 0 ? '↑ ' : '↓ ') + Math.abs(stats.profit_growth || 0) + '%';
}

/* ── Chart update dispatch ── */
function updateCharts() {
  const rev = chartData.revenue  || [];
  const exp = chartData.expenses || [];
  const ref = chartData.refunds  || [];
  const profit = rev.map((r, i) => r - (exp[i] || 0) - (ref[i] || 0));

  if (!hasFinancialActivity) {
    if (plChartInstance) { plChartInstance.destroy(); plChartInstance = null; }
    showEmptyState('plChart', 'No financial activity recorded yet');
  } else if (plChartInstance) {
    plChartInstance.data.labels = months.slice(0, rev.length);
    plChartInstance.data.datasets[0].data = rev;
    plChartInstance.data.datasets[1].data = exp;
    plChartInstance.data.datasets[2].data = ref;
    plChartInstance.data.datasets[3].data = profit;
    plChartInstance.update('active');
  } else {
    initPLChart(rev, exp, ref, profit);
  }

  if (!hasRevenueMix) {
    if (revMixChartInstance) { revMixChartInstance.destroy(); revMixChartInstance = null; }
    showEmptyState('revMixDonut', 'No revenue recorded yet');
  } else if (revMixChartInstance) {
    const labels = Object.keys(chartData.revenue_mix);
    const vals   = Object.values(chartData.revenue_mix);
    revMixChartInstance.data.labels = labels;
    revMixChartInstance.data.datasets[0].data = vals;
    revMixChartInstance.update('active');
  } else {
    initRevenueMixChart();
  }

  if (!hasExpenseBreakdown) {
    if (expBreakChartInstance) { expBreakChartInstance.destroy(); expBreakChartInstance = null; }
    showEmptyState('expBreakChart', 'No expenses recorded yet');
  } else if (expBreakChartInstance) {
    const len = chartData.maintenance.length;
    expBreakChartInstance.data.labels = months.slice(0, len);
    expBreakChartInstance.data.datasets[0].data = chartData.maintenance;
    expBreakChartInstance.data.datasets[1].data = chartData.utilities;
    expBreakChartInstance.data.datasets[2].data = chartData.salaries;
    expBreakChartInstance.data.datasets[3].data = chartData.admin;
    expBreakChartInstance.update('active');
  } else {
    initExpenseBreakChart();
  }
}

/* ── P&L table ── */
function updateTable(pnlSummary) {
  const tbody = document.getElementById('pnlTableBody');
  tbody.innerHTML = '';
  if (!pnlSummary || !pnlSummary.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-soft);">No data available for ${selectedYear}</td></tr>`;
    return;
  }
  pnlSummary.forEach(row => {
    const tr = document.createElement('tr');
    // Support both 7-col (with refunds) and 6-col (legacy) rows
    const hasRefunds = row.length >= 7;
    const refCell  = hasRefunds ? row[3] : '₱ 0';
    const netCell  = hasRefunds ? row[4] : row[3];
    const margin   = hasRefunds ? row[5] : row[4];
    const vsPrior  = hasRefunds ? row[6] : row[5];
    const trendColor = (vsPrior || '').includes('▲') ? 'var(--success)' : ((vsPrior || '').includes('▼') ? 'var(--danger)' : 'var(--text-soft)');
    tr.innerHTML = `
      <td style="font-weight:600;">${row[0]}</td>
      <td style="color:var(--success);font-weight:600;">${row[1]}</td>
      <td style="color:var(--danger);">${row[2]}</td>
      <td style="color:var(--danger);">${refCell}</td>
      <td style="font-weight:700;">${netCell}</td>
      <td>${margin}</td>
      <td style="color:${trendColor};font-weight:600;">${vsPrior}</td>`;
    tbody.appendChild(tr);
  });
}

/* ── Revenue mix legend ── */
function updateLegend(revenueMix) {
  const legend = document.getElementById('revenueMixLegend');
  legend.innerHTML = '';
  const colors = [C.blue, C.green, C.gold, C.purple, C.teal];
  Object.entries(revenueMix).forEach(([property, pct], i) => {
    const color = colors[i % colors.length];
    const item  = document.createElement('div');
    item.className = 'legend-item';
    item.innerHTML = `<div class="legend-dot" style="background:${color};"></div>
      <span class="legend-label">${property}</span>
      <span class="legend-val">${pct}%</span>`;
    legend.appendChild(item);
  });
}

/* ══════════════════════════════════════════
   CHART 1 — Monthly P&L  (bar + line combo)
══════════════════════════════════════════ */
function initPLChart(revenue, expenses, refunds, profit) {
  const ctx = document.getElementById('plChart');
  if (!ctx) return;
  const c2d = ctx.getContext('2d');

  const revGrad = makeGradient(c2d, 'rgba(37,99,196,0.92)', 'rgba(37,99,196,0.55)');
  const expGrad = makeGradient(c2d, 'rgba(231,76,60,0.88)', 'rgba(231,76,60,0.45)');
  const refGrad = makeGradient(c2d, 'rgba(222,175,55,0.88)', 'rgba(222,175,55,0.45)');
  const prfFill = makeGradient(c2d, 'rgba(46,204,113,0.18)', 'rgba(46,204,113,0.01)');

  plChartInstance = new Chart(c2d, {
    type: 'bar',
    data: {
      labels: months.slice(0, revenue.length),
      datasets: [
        {
          label: 'Revenue',
          data: revenue,
          backgroundColor: revGrad,
          borderRadius: { topLeft: 6, topRight: 6 },
          borderSkipped: 'bottom',
          borderWidth: 0,
          order: 2,
        },
        {
          label: 'Expenses',
          data: expenses,
          backgroundColor: expGrad,
          borderRadius: { topLeft: 6, topRight: 6 },
          borderSkipped: 'bottom',
          borderWidth: 0,
          order: 3,
        },
        {
          label: 'Refunds',
          data: refunds,
          backgroundColor: refGrad,
          borderRadius: { topLeft: 6, topRight: 6 },
          borderSkipped: 'bottom',
          borderWidth: 0,
          order: 4,
        },
        {
          label: 'Net Profit',
          data: profit,
          type: 'line',
          borderColor: C.green,
          borderWidth: 2.5,
          backgroundColor: prfFill,
          fill: true,
          tension: 0.45,
          pointRadius: 4,
          pointHoverRadius: 7,
          pointBackgroundColor: C.green,
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          spanGaps: true,
          order: 1,
          yAxisID: 'y',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      animation: { duration: 600, easing: 'easeInOutQuart' },
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            pointStyle: 'circle',
            font: { size: 11, family: "'DM Sans', sans-serif" },
            color: C.soft,
            padding: 18,
            generateLabels(chart) {
              return chart.data.datasets.map((ds, i) => ({
                text: ds.label,
                fillStyle: Array.isArray(ds.backgroundColor) ? ds.backgroundColor[0] : (ds.borderColor || ds.backgroundColor),
                strokeStyle: ds.borderColor || 'transparent',
                pointStyle: ds.type === 'line' ? 'line' : 'circle',
                hidden: !chart.isDatasetVisible(i),
                datasetIndex: i,
                fontColor: C.soft,
              }));
            },
          },
        },
        tooltip: {
          ...baseTooltip,
          callbacks: {
            title: items => months[parseInt(items[0].label === items[0].label ? months.indexOf(items[0].label) : 0)] || items[0].label,
            label: ctx => {
              const v = ctx.parsed.y;
              const sign = v < 0 ? '-' : '';
              return `  ${ctx.dataset.label}: ${sign}₱ ${Math.abs(v).toLocaleString()}`;
            },
          },
        },
      },
      scales: {
        x: { ...baseScales.x, barPercentage: 0.72, categoryPercentage: 0.75 },
        y: {
          ...baseScales.y,
          beginAtZero: true,
          grace: '8%',
        },
      },
    },
  });
}

/* ══════════════════════════════════════════
   CHART 2 — Revenue Mix Donut
══════════════════════════════════════════ */
function initRevenueMixChart() {
  const ctx = document.getElementById('revMixDonut');
  if (!ctx || !chartData.revenue_mix || !Object.keys(chartData.revenue_mix).length) return;

  const labels = Object.keys(chartData.revenue_mix);
  const vals   = Object.values(chartData.revenue_mix);
  const palette = [C.blue, C.green, C.gold, C.purple, C.teal, C.orange];

  revMixChartInstance = new Chart(ctx.getContext('2d'), {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data: vals,
        backgroundColor: palette.slice(0, labels.length),
        borderColor: '#fff',
        borderWidth: 3,
        hoverBorderWidth: 4,
        hoverOffset: 8,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      animation: { animateRotate: true, duration: 700, easing: 'easeInOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          ...baseTooltip,
          callbacks: { label: ctx => `  ${ctx.label}: ${ctx.parsed}%` },
        },
      },
    },
  });
}

/* ══════════════════════════════════════════
   CHART 3 — Expense Breakdown (stacked bar)
══════════════════════════════════════════ */
function initExpenseBreakChart() {
  const ctx = document.getElementById('expBreakChart');
  if (!ctx || !chartData.maintenance || !chartData.maintenance.length) return;

  const len = chartData.maintenance.length;

  expBreakChartInstance = new Chart(ctx.getContext('2d'), {
    type: 'bar',
    data: {
      labels: months.slice(0, len),
      datasets: [
        {
          label: 'Maintenance',
          data: chartData.maintenance,
          backgroundColor: 'rgba(231,76,60,0.82)',
          borderRadius: 0,
          stack: 's',
          borderSkipped: false,
        },
        {
          label: 'Utilities',
          data: chartData.utilities,
          backgroundColor: 'rgba(37,99,196,0.82)',
          borderRadius: 0,
          stack: 's',
          borderSkipped: false,
        },
        {
          label: 'Salaries',
          data: chartData.salaries,
          backgroundColor: 'rgba(46,204,113,0.82)',
          borderRadius: 0,
          stack: 's',
          borderSkipped: false,
        },
        {
          label: 'Admin',
          data: chartData.admin,
          backgroundColor: 'rgba(222,175,55,0.82)',
          /* top segment gets rounded top */
          borderRadius: { topLeft: 6, topRight: 6 },
          stack: 's',
          borderSkipped: false,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      animation: { duration: 600, easing: 'easeInOutQuart' },
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            pointStyle: 'circle',
            font: { size: 11, family: "'DM Sans', sans-serif" },
            color: C.soft,
            padding: 18,
          },
        },
        tooltip: {
          ...baseTooltip,
          callbacks: {
            label: ctx => `  ${ctx.dataset.label}: ₱ ${ctx.parsed.y.toLocaleString()}`,
            footer: items => {
              const total = items.reduce((s, i) => s + i.parsed.y, 0);
              return `  Total: ₱ ${total.toLocaleString()}`;
            },
          },
        },
      },
      scales: {
        x: { ...baseScales.x, stacked: true, barPercentage: 0.6, categoryPercentage: 0.75 },
        y: { ...baseScales.y, stacked: true, beginAtZero: true, grace: '6%' },
      },
    },
  });
}

/* ── Export ── */
/* ── Auto-refresh ── */
function startAutoRefresh() {
  autoRefreshInterval = setInterval(() => loadFinancialData(selectedYear), 30000);
}
function stopAutoRefresh() {
  if (autoRefreshInterval) clearInterval(autoRefreshInterval);
}

/* ── Boot ── */
document.addEventListener('DOMContentLoaded', () => {
  updateCharts();
  startAutoRefresh();
});

window.addEventListener('beforeunload', stopAutoRefresh);