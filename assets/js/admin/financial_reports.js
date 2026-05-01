let chartData = window.__PS_FINANCIAL__.chartData;
  let selectedYear = window.__PS_FINANCIAL__.selectedYear;

  const blue = '#2563c4', gold = '#deaf37', grn = '#2ECC71', red = '#E74C3C';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  let plChartInstance = null;
  let revMixChartInstance = null;
  let expBreakChartInstance = null;
  let autoRefreshInterval = null;

  function formatCurrency(amount) {
    if (amount >= 1000000) {
      return '₱ ' + (amount / 1000000).toFixed(2) + 'M';
    } else if (amount >= 1000) {
      return '₱ ' + (amount / 1000).toFixed(2) + 'K';
    }
    return '₱ ' + Math.round(amount).toLocaleString();
  }

  function handleYearChange(year) {
    selectedYear = parseInt(year);
    loadFinancialData(selectedYear);
  }

  function loadFinancialData(year) {
    fetch('../../api/admin/get_financial_data.php?year=' + year, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      }
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          chartData = data.financial_data;
          updateStatistics(data.stats);
          updateCharts();
          updateTable(data.financial_data.pnl_summary);
          updateLegend(data.financial_data.revenue_mix);
        } else {
          console.error('Error loading data:', data.message);
        }
      })
      .catch(error => console.error('Fetch error:', error));
  }

  function updateStatistics(stats) {
    document.getElementById('totalRevenue').textContent = formatCurrency(stats.total_revenue);
    document.getElementById('revenueGrowth').textContent = (stats.revenue_growth >= 0 ? '↑ ' : '↓ ') + Math.abs(stats.revenue_growth) + '%';

    document.getElementById('totalExpenses').textContent = formatCurrency(stats.total_expenses);
    document.getElementById('expenseGrowth').textContent = (stats.expense_growth >= 0 ? '↑ ' : '↓ ') + Math.abs(stats.expense_growth) + '%';

    document.getElementById('netProfit').textContent = formatCurrency(stats.net_profit);
    document.getElementById('profitGrowth').textContent = (stats.profit_growth >= 0 ? '↑ ' : '↓ ') + Math.abs(stats.profit_growth) + '%';

    document.getElementById('roi').textContent = stats.roi + '%';
  }

  function updateCharts() {
    const rev = chartData.revenue || [];
    const exp = chartData.expenses || [];
    const profit = rev.map((r, i) => r - (exp[i] || 0));

    if (rev.length === 0) {
      console.log('No financial data available');
      return;
    }

    if (plChartInstance) {
      plChartInstance.data.labels = months.slice(0, rev.length);
      plChartInstance.data.datasets[0].data = rev;
      plChartInstance.data.datasets[1].data = exp;
      plChartInstance.data.datasets[2].data = profit;
      plChartInstance.update();
    } else {
      initPLChart(rev, exp, profit);
    }

    if (Object.keys(chartData.revenue_mix).length > 0) {
      if (revMixChartInstance) {
        const labels = Object.keys(chartData.revenue_mix);
        const data = Object.values(chartData.revenue_mix);
        revMixChartInstance.data.labels = labels;
        revMixChartInstance.data.datasets[0].data = data;
        revMixChartInstance.update();
      } else {
        initRevenueMixChart();
      }
    }

    if (chartData.maintenance && chartData.maintenance.length > 0) {
      if (expBreakChartInstance) {
        expBreakChartInstance.data.labels = months.slice(0, chartData.maintenance.length);
        expBreakChartInstance.data.datasets[0].data = chartData.maintenance;
        expBreakChartInstance.data.datasets[1].data = chartData.utilities;
        expBreakChartInstance.data.datasets[2].data = chartData.salaries;
        expBreakChartInstance.data.datasets[3].data = chartData.admin;
        expBreakChartInstance.update();
      } else {
        initExpenseBreakChart();
      }
    }
  }

  function updateTable(pnlSummary) {
    const tbody = document.getElementById('pnlTableBody');
    tbody.innerHTML = '';

    if (!pnlSummary || pnlSummary.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-soft);">No data available for ' + selectedYear + '</td></tr>';
      return;
    }

    pnlSummary.forEach(row => {
      const tr = document.createElement('tr');
      const trendColor = row[5].includes('▲') ? 'var(--success)' : (row[5].includes('▼') ? 'var(--danger)' : 'var(--text-soft)');

      tr.innerHTML = `
        <td style="font-weight:600;">${row[0]}</td>
        <td style="color:var(--success);font-weight:600;">${row[1]}</td>
        <td style="color:var(--danger);">${row[2]}</td>
        <td style="font-weight:700;">${row[3]}</td>
        <td>${row[4]}</td>
        <td style="color:${trendColor};font-weight:600;">${row[5]}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  function updateLegend(revenueMix) {
    const legend = document.getElementById('revenueMixLegend');
    legend.innerHTML = '';

    const colors = ['#2563c4', '#2ECC71', '#deaf37'];
    let index = 0;

    Object.entries(revenueMix).forEach(([property, percentage]) => {
      const color = colors[index % colors.length];
      const item = document.createElement('div');
      item.className = 'legend-item';
      item.innerHTML = `
        <div class="legend-dot" style="background:${color};"></div>
        <span class="legend-label">${property}</span>
        <span class="legend-val">${percentage}%</span>
      `;
      legend.appendChild(item);
      index++;
    });
  }

  function initPLChart(revenue, expenses, profit) {
    const plCtx = document.getElementById('plChart');
    if (!plCtx) return;

    plChartInstance = new Chart(plCtx.getContext('2d'), {
      type: 'bar',
      data: {
        labels: months.slice(0, revenue.length),
        datasets: [
          {
            label: 'Revenue',
            data: revenue,
            backgroundColor: 'rgba(37,99,196,0.8)',
            borderRadius: 5,
            borderSkipped: false
          },
          {
            label: 'Expenses',
            data: expenses,
            backgroundColor: 'rgba(231,76,60,0.65)',
            borderRadius: 5,
            borderSkipped: false
          },
          {
            label: 'Profit',
            data: profit,
            type: 'line',
            borderColor: grn,
            borderWidth: 2.5,
            backgroundColor: 'rgba(46,204,113,0.08)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: grn,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'left',
            labels: { usePointStyle: true, font: { size: 11 } }
          },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ₱ ${ctx.parsed.y}K`
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { size: 11 } }
          },
          y: {
            grid: { color: 'rgba(0,0,0,.05)' },
            ticks: {
              callback: v => '₱' + v + 'K',
              font: { size: 11 }
            }
          },
          y1: {
            type: 'linear',
            display: false,
            position: 'left'
          }
        }
      }
    });
  }

  function initRevenueMixChart() {
    const revMixCtx = document.getElementById('revMixDonut');
    if (!revMixCtx || !chartData.revenue_mix || Object.keys(chartData.revenue_mix).length === 0) {
      return;
    }

    const revenueMixLabels = Object.keys(chartData.revenue_mix);
    const revenueMixData = Object.values(chartData.revenue_mix);

    revMixChartInstance = new Chart(revMixCtx.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: revenueMixLabels,
        datasets: [{
          data: revenueMixData,
          backgroundColor: [blue, grn, gold],
          borderWidth: 0,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
          legend: { display: false }
        }
      }
    });
  }

  function initExpenseBreakChart() {
    const expBreakCtx = document.getElementById('expBreakChart');
    if (!expBreakCtx || !chartData.maintenance || chartData.maintenance.length === 0) {
      return;
    }

    expBreakChartInstance = new Chart(expBreakCtx.getContext('2d'), {
      type: 'bar',
      data: {
        labels: months.slice(0, chartData.maintenance.length),
        datasets: [
          {
            label: 'Maintenance',
            data: chartData.maintenance,
            backgroundColor: 'rgba(231,76,60,0.75)',
            borderRadius: 4,
            stack: 's'
          },
          {
            label: 'Utilities',
            data: chartData.utilities,
            backgroundColor: 'rgba(37,99,196,0.7)',
            borderRadius: 4,
            stack: 's'
          },
          {
            label: 'Salaries',
            data: chartData.salaries,
            backgroundColor: 'rgba(46,204,113,0.7)',
            borderRadius: 4,
            stack: 's'
          },
          {
            label: 'Admin',
            data: chartData.admin,
            backgroundColor: 'rgba(222,175,55,0.7)',
            borderRadius: 4,
            stack: 's'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'left',
            labels: { usePointStyle: true, font: { size: 11 } }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            stacked: true,
            ticks: { font: { size: 11 } }
          },
          y: {
            stacked: true,
            grid: { color: 'rgba(0,0,0,.05)' },
            ticks: {
              callback: v => '₱' + v + 'K',
              font: { size: 11 }
            }
          }
        }
      }
    });
  }

  function exportPDF() {
    const element = document.querySelector('.page-inner');
    const opt = {
      margin: 10,
      filename: `Financial_Report_${selectedYear}.pdf`,
      image: { type: 'jpeg', quality: 0.98 },
      html2canvas: { scale: 2 },
      jsPDF: { orientation: 'portrait', unit: 'mm', format: 'a4' }
    };
    html2pdf().set(opt).from(element).save();
  }

  function startAutoRefresh() {
    autoRefreshInterval = setInterval(() => {
      loadFinancialData(selectedYear);
    }, 30000);
  }

    function stopAutoRefresh() {
      if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      initPLChart(chartData.revenue || [], chartData.expenses || [], (chartData.revenue || []).map((r, i) => r - ((chartData.expenses || [])[i] || 0)));
      initRevenueMixChart();
      initExpenseBreakChart();

      startAutoRefresh();
    });

    window.addEventListener('beforeunload', stopAutoRefresh);
