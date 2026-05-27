const blue = '#2563c4',
  gold = '#deaf37',
  grn = '#2ECC71',
  red = '#E74C3C';
const MONTH_LABELS_FULL = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const CURRENT_YEAR = window.__PS_DASHBOARD__.currentYear;
let selectedRevenueYear = CURRENT_YEAR;

window._psRevenueChart = new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: window.__PS_DASHBOARD__.chartLabels,
    datasets: [{
      label: 'Revenue',
      data: window.__PS_DASHBOARD__.revData,
      backgroundColor: 'rgba(37,99,196,0.15)',
      borderColor: '#2563c4',
      borderWidth: 2,
      borderRadius: 6
    },
    {
      label: 'Expenses',
      data: window.__PS_DASHBOARD__.expData,
      borderColor: '#deaf37',
      borderWidth: 2.5,
      type: 'line',
      tension: .4,
      fill: false,
      pointBackgroundColor: '#deaf37',
      pointRadius: 4,
      pointHoverRadius: 6
    }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: 'index',
      intersect: false
    },
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        callbacks: {
          label: ctx => `₱ ${(ctx.parsed.y * 1000).toLocaleString()}`
        }
      }
    },
    scales: {
      x: {
        grid: {
          display: false
        },
        ticks: {
          font: {
            size: 11
          }
        }
      },
      y: {
        grid: {
          color: 'rgba(0,0,0,.05)'
        },
        ticks: {
          callback: v => '₱' + v + 'K',
          font: {
            size: 11
          }
        }
      }
    }
  }
});

function loadRevenueYear(year) {
  const y = Number(year) || CURRENT_YEAR;
  selectedRevenueYear = y;
  const chart = window._psRevenueChart;
  if (!chart) return;

  fetch(`../../api/admin/get_financial_data.php?year=${encodeURIComponent(y)}`, {
    credentials: 'same-origin'
  })
    .then(r => r.json())
    .then(data => {
      if (!data || data.success !== true || !data.financial_data) return;
      const fd = data.financial_data;
      const rev = Array.isArray(fd.revenue) ? fd.revenue : [];
      const exp = Array.isArray(fd.expenses) ? fd.expenses : [];

      chart.data.labels = MONTH_LABELS_FULL;
      chart.data.datasets[0].data = MONTH_LABELS_FULL.map((_, i) => Number(rev[i] || 0));
      chart.data.datasets[1].data = MONTH_LABELS_FULL.map((_, i) => Number(exp[i] || 0));
      chart.update();
    })
    .catch(() => { });
}

document.getElementById('revenueYearSelect')?.addEventListener('change', function () {
  loadRevenueYear(this.value);
});

window._psOccupancyChart = new Chart(document.getElementById('occupancyDonut'), {
  type: 'doughnut',
  data: {
    labels: ['Occupied', 'Vacant', 'Maintenance'],
    datasets: [{
      data: [window.__PS_DASHBOARD__.occupiedUnits, window.__PS_DASHBOARD__.vacantUnits, window.__PS_DASHBOARD__.maintenanceUnits],
      backgroundColor: ['#2563c4', '#dbeafe', '#E74C3C'],
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '72%',
    hover: {
      mode: null
    },
    plugins: {
      legend: {
        display: false
      },
      tooltip: {
        enabled: true
      }
    }
  }
});

function escHtml(str) {
  return String(str || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function setGrowthText(selector, value) {
  document.querySelectorAll(selector).forEach(el => {
    const n = Number(value) || 0;
    el.classList.toggle('up', n >= 0);
    el.classList.toggle('down', n < 0);
    el.textContent = `${n >= 0 ? '↑' : '↓'} ${Math.abs(n)}%`;
  });
}

window.addEventListener('ps:dashboard_metrics', e => {
  const m = e.detail || {};
  if (typeof m.cancel_rate !== 'undefined') {
    document.querySelectorAll('[data-rt-kpi="cancel_rate"]').forEach(el => {
      el.textContent = `${Number(m.cancel_rate) || 0}%`;
    });
  }
  if (typeof m.cancelled_this_month !== 'undefined') {
    document.querySelectorAll('[data-rt-kpi="cancelled_this_month"]').forEach(el => {
      el.textContent = String(m.cancelled_this_month || 0);
    });
  }
  if (typeof m.last_year_revenue !== 'undefined') {
    document.querySelectorAll('[data-rt-kpi="last_year_revenue"]').forEach(el => {
      el.textContent = `₱ ${Math.round((Number(m.last_year_revenue) || 0) / 1000)}K`;
    });
  }
  if (typeof m.revenue_growth !== 'undefined') setGrowthText('[data-rt-kpi="revenue_growth"]', m.revenue_growth);
  if (typeof m.booking_growth !== 'undefined') setGrowthText('[data-rt-kpi="booking_growth"]', m.booking_growth);

  const totalUnits = Math.max(1, parseInt(m.total_units, 10) || 1);
  const occupied = parseInt(m.occupied_units, 10) || 0;
  const vacant = parseInt(m.vacant_units, 10) || 0;
  const maintenance = parseInt(m.maintenance_units, 10) || 0;
  const occupiedPct = Math.round((occupied / totalUnits) * 100);
  const vacantPct = Math.round((vacant / totalUnits) * 100);
  const maintenancePct = Math.round((maintenance / totalUnits) * 100);
  document.querySelectorAll('[data-rt-kpi="occupied_pct"]').forEach(el => el.textContent = `(${occupiedPct}%)`);
  document.querySelectorAll('[data-rt-kpi="vacant_pct"]').forEach(el => el.textContent = `(${vacantPct}%)`);
  document.querySelectorAll('[data-rt-kpi="maintenance_pct"]').forEach(el => el.textContent = `(${maintenancePct}%)`);
});

window.addEventListener('ps:financial_series', e => {
  if (Number(selectedRevenueYear) !== Number(CURRENT_YEAR)) return;
  const series = e.detail || {};
  const chart = window._psRevenueChart;
  if (!chart) return;
  if (Array.isArray(series.labels)) chart.data.labels = series.labels;
  if (Array.isArray(series.revenue_k)) chart.data.datasets[0].data = series.revenue_k;
  if (Array.isArray(series.expenses_k)) chart.data.datasets[1].data = series.expenses_k;
  chart.update('none');
});

window.addEventListener('ps:top_properties', e => {
  const properties = Array.isArray(e.detail) ? e.detail : [];
  const list = document.getElementById('rt-properties-list');
  if (!list) return;
  if (!properties.length) {
    list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-soft);font-size:13px;">No properties found.</div>';
    return;
  }

  list.innerHTML = properties.map(prop => {
    const totalUnits = Math.max(1, parseInt(prop.total_units, 10) || 0);
    const occupied = parseInt(prop.occupied, 10) || 0;
    const occPct = Math.round((occupied / totalUnits) * 100);
    const barColor = occPct >= 80 ? 'var(--success)' : (occPct >= 50 ? 'var(--gold)' : 'var(--blue-400)');
    return `
          <div class="prop-item">
            <div class="prop-thumb" style="background:var(--blue-50);color:var(--blue-400);display:flex;align-items:center;justify-content:center;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg></div>            <div class="prop-info">
              <div class="name">${escHtml(prop.property_name)}</div>
              <div class="addr">${escHtml(prop.address || '')}</div>
              <div class="prop-bar-wrap">
                <div class="prop-bar" style="width:${occPct}%;background:${barColor};"></div>
              </div>
            </div>
            <div class="prop-score">
              <div class="prop-score-main">${occPct}%</div>
              <div class="prop-score-sub">${occupied}/${totalUnits}</div>
            </div>
          </div>
        `;
  }).join('');
});

window.addEventListener('ps:task_summary', e => {
  const tasks = Array.isArray(e.detail) ? e.detail : [];
  const list = document.getElementById('rt-task-list');
  if (!list) return;
  if (!tasks.length) {
    list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-soft);font-size:13px;">No tasks found.</div>';
    return;
  }

  const statusMap = {
    open: {
      bg: 'var(--danger-light)',
      color: 'var(--danger)',
      label: 'Urgent',
      dot: 'var(--danger)'
    },
    in_progress: {
      bg: 'var(--blue-50)',
      color: 'var(--blue-500)',
      label: 'In Progress',
      dot: 'var(--blue-400)'
    },
    pending: {
      bg: 'var(--pending-light)',
      color: 'var(--accent-dk)',
      label: 'Pending',
      dot: 'var(--gold)'
    },
    completed: {
      bg: 'var(--success-light)',
      color: 'var(--success)',
      label: 'Done',
      dot: 'var(--success)'
    },
    closed: {
      bg: 'var(--success-light)',
      color: 'var(--success)',
      label: 'Closed',
      dot: 'var(--success)'
    }
  };
  const priorityMap = {
    high: {
      bg: 'var(--danger-light)',
      color: 'var(--danger)',
      label: 'High'
    },
    medium: {
      bg: 'var(--pending-light)',
      color: 'var(--accent-dk)',
      label: 'Medium'
    },
    low: {
      bg: 'var(--blue-50)',
      color: 'var(--blue-500)',
      label: 'Low'
    }
  };

  list.innerHTML = tasks.map(task => {
    const st = statusMap[task.status] || statusMap.pending;
    const pr = priorityMap[(task.priority || '').toLowerCase()] || priorityMap.medium;
    return `
          <div class="task-item">
            <div class="task-dot" style="background:${st.dot};"></div>
            <div class="task-info">
              <div class="tname">${escHtml(task.title)}</div>
              <div class="tmeta">
                <span class="tprop">${escHtml(task.property_name || '—')}</span>
                <span class="task-priority" style="background:${pr.bg};color:${pr.color};">${pr.label}</span>
              </div>
            </div>
            <div class="task-status" style="background:${st.bg};color:${st.color};">${st.label}</div>
          </div>
        `;
  }).join('');
});

// ── Live Recent Activity Feed Updates ──────────────────────────────────────
window.addEventListener('ps:recent_activity', e => {
  const activities = Array.isArray(e.detail) ? e.detail : [];
  const feed = document.getElementById('rt-activity-feed');
  if (!feed) return;

  if (!activities.length) {
    feed.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-soft);font-size:13px;">No recent activity.</div>';
    return;
  }

  function statusBadge(status) {
    const map = {
      pending: { text: 'Pending', bg: 'var(--pending-light)', color: 'var(--accent-dk)' },
      confirmed: { text: 'Confirmed', bg: 'var(--blue-50)', color: 'var(--blue-500)' },
      active: { text: 'Active', bg: 'var(--success-light)', color: 'var(--success)' },
      completed: { text: 'Completed', bg: 'var(--success-light)', color: 'var(--success)' },
      cancelled: { text: 'Cancelled', bg: 'var(--danger-light)', color: 'var(--danger)' }
    };
    const s = map[status] || map.pending;
    return `<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:${s.bg};color:${s.color};">${s.text}</span>`;
  }

  function relativeTime(ts) {
    if (!ts) return 'just now';
    const d = new Date(ts);
    if (Number.isNaN(d.getTime())) return 'just now';
    const sec = Math.floor((Date.now() - d.getTime()) / 1000);
    if (sec < 60) return 'just now';
    if (sec < 3600) return `${Math.floor(sec / 60)}m ago`;
    if (sec < 86400) return `${Math.floor(sec / 3600)}h ago`;
    return `${Math.floor(sec / 86400)}d ago`;
  }

  feed.innerHTML = activities.slice(0, 5).map(act => `
    <div class="activity-row" style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f1f5f9;">
      <div class="activity-icon" style="width:36px;height:36px;border-radius:50%;background:var(--blue-50);color:var(--blue-500);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:600;color:#0f172a;margin-bottom:2px;">${escHtml(act.user_name || 'Guest')}</div>
        <div style="font-size:12px;color:#64748b;">Booked ${escHtml(act.unit_name || 'Unit')} • ₱${Number(act.total_amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 0 })}</div>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        ${statusBadge(act.status)}
        <div style="font-size:11px;color:#94a3b8;margin-top:4px;">${relativeTime(act.created_at)}</div>
      </div>
    </div>
  `).join('');
});

(function loadInitialActivity() {
  const feed = document.getElementById('rt-activity-feed');
  if (!feed) return;
 
  // Show loading state
  feed.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-soft);font-size:13px;">Loading activity...</div>';
 
  // Fetch initial activity data from realtime API
  // Use timestamp of 1 hour ago to get recent items
  const oneHourAgo = new Date(Date.now() - 3600000).toISOString().slice(0, 19).replace('T', ' ');
  
  fetch('../../api/realtime.php?since=' + encodeURIComponent(oneHourAgo))
    .then(res => res.json())
    .then(data => {
      if (data.recent_activity && data.recent_activity.length) {
        // Emit the event to trigger the existing handler
        window.dispatchEvent(new CustomEvent('ps:recent_activity', {
          detail: data.recent_activity
        }));
      } else {
        feed.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-soft);font-size:13px;">No recent activity.</div>';
      }
    })
    .catch(err => {
      console.error('Failed to load initial activity:', err);
      feed.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-soft);font-size:13px;">Failed to load activity.</div>';
    });
})();