function animateStat(el, newVal) {
    el.style.transition = 'opacity .2s';
    el.style.opacity = '0';
    setTimeout(() => {
        el.textContent = newVal;
        el.style.opacity = '1';
    }, 200);
}

async function refreshStats() {
    try {
        const res = await fetch('../../endpoints/admin/get_property_stats.php');
        const data = await res.json();
        if (data.status !== 'success') return;

        const s = data.stats;
        animateStat(document.getElementById('stat-total'), s.total);
        animateStat(document.getElementById('stat-occupied'), s.occupied);
        animateStat(document.getElementById('stat-vacant'), s.vacant);
        animateStat(document.getElementById('stat-maintenance'), s.maintenance);
        animateStat(document.getElementById('stat-new-month'), s.new_this_month);

        const pct = s.total > 0 ? Math.round((s.occupied / s.total) * 100) : 0;
        document.getElementById('stat-occ-pct').textContent = pct;
    } catch (e) {
        console.error('Stats refresh failed:', e);
    }
}

// ── View modal ────────────────────────────────────────────────────────────────

const overlay = document.getElementById('property-view-overlay');
const closebtns = [
    document.getElementById('pvm-close'),
    document.getElementById('pvm-close-btn'),
];

function statusBadgeHTML(status) {
    const map = {
        active: { bg: '#d1fae5', color: '#065f46', label: 'Active' },
        inactive: { bg: '#fee2e2', color: '#991b1b', label: 'Inactive' },
        maintenance: { bg: '#fef3c7', color: '#92400e', label: 'Maintenance' },
    };
    const s = map[status?.toLowerCase()] || { bg: '#f3f4f6', color: '#374151', label: status };
    return `<span style="font-size:12px;font-weight:500;padding:3px 10px;border-radius:99px;background:${s.bg};color:${s.color};">${s.label}</span>`;
}

function barColor(pct) {
    if (pct >= 80) return '#22c55e';
    if (pct >= 40) return '#f59e0b';
    return '#ef4444';
}

function openPropertyModal(btn) {
    const id = btn.dataset.id;
    const name = btn.dataset.name;
    const propId = btn.dataset.propid;
    const type = btn.dataset.type;
    const address = btn.dataset.address;
    const units = btn.dataset.units;
    const occupied = btn.dataset.occupied;
    const pct = parseInt(btn.dataset.pct, 10);
    const status = btn.dataset.status;

    document.getElementById('pvm-name').textContent = name;
    document.getElementById('pvm-meta').textContent = `ID #${propId} · ${type}`;
    document.getElementById('pvm-units').textContent = units;
    document.getElementById('pvm-occupied').textContent = occupied;
    document.getElementById('pvm-pct').textContent = `${pct}%`;
    document.getElementById('pvm-address').textContent = address;
    document.getElementById('pvm-type').textContent = type;
    document.getElementById('pvm-status').innerHTML = statusBadgeHTML(status);
    document.getElementById('pvm-bar-label').textContent = `${pct}%`;
    document.getElementById('pvm-bar').style.background = barColor(pct);

    overlay.style.display = 'flex';
    overlay.style.visibility = 'visible';
    overlay.style.opacity = '1';
    overlay.style.pointerEvents = 'auto';

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.getElementById('pvm-bar').style.width = `${pct}%`;
        });
    });
}

function closePropertyModal() {
    overlay.style.display = 'none';
    overlay.style.visibility = 'hidden';
    overlay.style.opacity = '0';
    overlay.style.pointerEvents = 'none';
    document.getElementById('pvm-bar').style.width = '0%';
}

document.querySelectorAll('.view-property-btn').forEach(btn => {
    btn.addEventListener('click', () => openPropertyModal(btn));
});

closebtns.forEach(b => b?.addEventListener('click', closePropertyModal));

overlay.addEventListener('click', e => {
    if (e.target === overlay) closePropertyModal();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && overlay.style.display === 'flex') closePropertyModal();
});

// ── Delete ────────────────────────────────────────────────────────────────────

document.querySelectorAll('.delete-property-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        const id = this.dataset.id;
        const name = this.dataset.name;
        const row = this.closest('tr');

        PS.confirm(
            `Remove property <strong>${name}</strong>? This action cannot be undone.`,
            async () => {
                try {
                    const formData = new FormData();
                    formData.append('csrf_token', window.PS_CSRF_TOKEN || '');
                    formData.append('property_id', id);

                    const response = await fetch('../../endpoints/admin/delete_property.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        PS.toast(data.message, 'success');

                        row.style.transition = 'opacity .4s';
                        row.style.opacity = '0';

                        setTimeout(async () => {
                            row.remove();

                            const tbody = document.querySelector('tbody');
                            const remaining = tbody.querySelectorAll('tr').length;
                            if (remaining === 0) {
                                const emptyRow = document.createElement('tr');
                                emptyRow.innerHTML = `
                                    <td colspan="7" style="text-align:center;padding:32px;color:var(--text-soft);">
                                        No properties found.
                                    </td>`;
                                tbody.appendChild(emptyRow);
                            }

                            await refreshStats();

                        }, 400);

                    } else {
                        PS.toast(data.message, 'error');
                    }

                } catch (error) {
                    console.error(error);
                    PS.toast('Server error. Please try again.', 'error');
                }
            },
            {
                title: 'Remove Property',
                confirmLabel: 'Remove',
                confirmClass: 'btn btn-danger'
            }
        );
    });
});