const propTrendDatasets = window.__PS_OCCUPANCY__.propTrendDatasets;
const last6Labels = window.__PS_OCCUPANCY__.last6Labels;
const perPropLabels = window.__PS_OCCUPANCY__.perPropLabels;
const perPropRates = window.__PS_OCCUPANCY__.perPropRates;
const propColors = ['#2563c4', '#deaf37', '#2ECC71', '#93c5fd', '#E74C3C', '#8B5CF6'];

new Chart(document.getElementById('occTrendChart'), {
    type: 'line',
    data: { labels: last6Labels, datasets: propTrendDatasets.map((ds, i) => ({
        ...ds,
        borderWidth: 2.5,
        pointRadius: 5,
        pointHoverRadius: 7,
        pointBackgroundColor: '#fff',
        pointBorderWidth: 2,
        pointBorderColor: propColors[i % 6],
        fill: true,
        backgroundColor: propColors[i % 6] + '12',
    }))},
    options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: { size: 11 },
                    padding: 16
                }
            },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.dataset.label + ': ' + ctx.parsed.y + '%'
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 11 } }
            },
            y: {
                min: 0, max: 100,
                grid: { color: 'rgba(0,0,0,.04)' },
                ticks: { callback: v => v + '%', font: { size: 11 } }
            }
        }
    }
});

new Chart(document.getElementById('occBarChart'), {
    type: 'bar',
    data: {
        labels: perPropLabels,
        datasets: [{
            label: 'Occupancy %',
            data: perPropRates,
            backgroundColor: propColors.slice(0, perPropLabels.length).map(c => c + 'cc'),
            borderColor: propColors.slice(0, perPropLabels.length),
            borderWidth: 2,
            borderRadius: 10,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ' ' + ctx.parsed.y + '% occupied',
                    title: ctx => ctx[0].label
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { font: { size: 11, weight: '600' } }
            },
            y: {
                min: 0, max: 100,
                grid: { color: 'rgba(0,0,0,.04)' },
                ticks: { callback: v => v + '%', font: { size: 11 } }
            }
        }
    },
    plugins: [{
        id: 'barLabels',
        afterDatasetsDraw(chart) {
            const { ctx, data } = chart;
            chart.getDatasetMeta(0).data.forEach((bar, i) => {
                const val = data.datasets[0].data[i];
                if (val === 0) return;
                ctx.save();
                ctx.fillStyle = '#fff';
                ctx.font = 'bold 13px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(val + '%', bar.x, bar.y + 16);
                ctx.restore();
            });
        }
    }]
});