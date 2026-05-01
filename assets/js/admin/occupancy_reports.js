const propTrendDatasets = window.__PS_OCCUPANCY__.propTrendDatasets;
    const last6Labels = window.__PS_OCCUPANCY__.last6Labels;
    const perPropLabels = window.__PS_OCCUPANCY__.perPropLabels;
    const perPropRates = window.__PS_OCCUPANCY__.perPropRates;
    const propColors = ['#2563c4', '#deaf37', '#2ECC71', '#93c5fd', '#E74C3C', '#8B5CF6'];

    new Chart(document.getElementById('occTrendChart'), {
        type: 'line', data: { labels: last6Labels, datasets: propTrendDatasets },
        options: {
            responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'left', labels: { usePointStyle: true, font: { size: 11 } } } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: { min: 0, max: 100, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { callback: v => v + '%', font: { size: 11 } } }
            }
        }
    });

    new Chart(document.getElementById('occBarChart'), {
        type: 'bar',
        data: {
            labels: perPropLabels, datasets: [{
                label: 'Occupancy %', data: perPropRates,
                backgroundColor: propColors.slice(0, perPropLabels.length), borderRadius: 8, borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.parsed.y + '%' } } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: { min: 0, max: 100, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { callback: v => v + '%', font: { size: 11 } } }
            }
        }
    });

    function exportCSV() {
        const rows = [['Month', 'Occupied', 'Total Units', 'Rate (%)']];
    window.__PS_OCCUPANCY__.trendRows.forEach(r => rows.push(r));
        const csv = rows.map(r => r.join(',')).join('\n');
        const a = document.createElement('a');
        a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
        a.download = 'occupancy_' + window.__PS_OCCUPANCY__.selectedMonth + '.csv';
        a.click();
    }
