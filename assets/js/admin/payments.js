const _psChannel = new BroadcastChannel('propsight_data')

new Chart(document.getElementById('collectionChart'), {
    type: 'bar',
    data: {
        labels: window.__PS_PAYMENTS__.trendLabels,
        datasets: [{
            label: 'Collected',
            data: window.__PS_PAYMENTS__.trendCollected,
            backgroundColor: 'rgba(46,204,113,0.7)',
            borderRadius: 8,
            borderSkipped: false
        },
        {
            label: 'Pending + Overdue',
            data: window.__PS_PAYMENTS__.trendOutstanding,
            backgroundColor: 'rgba(231,76,60,0.4)',
            borderRadius: 8,
            borderSkipped: false
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
                position: 'left',
                labels: {
                    usePointStyle: true,
                    font: {
                        size: 11
                    }
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
                    callback: v => '₱' + v.toLocaleString(),
                    font: {
                        size: 11
                    }
                }
            }
        }
    }
});

function openModal(mode, data = null) {
    document.getElementById('modalTitle').textContent = mode === 'edit' ? 'Edit Payment' : 'Record Payment';
    document.getElementById('formAction').value = mode;
    document.getElementById('submitBtn').textContent = mode === 'edit' ? 'Save Changes' : 'Save Payment';

    const dropdown = document.getElementById('formBookingId');
    const editDisplay = document.getElementById('editTenantDisplay');

    if (mode === 'edit' && data) {
        // Hide dropdown, show tenant name display
        dropdown.style.display = 'none';
        dropdown.removeAttribute('required');
        editDisplay.style.display = 'flex';

        let hiddenBooking = document.getElementById('hiddenBookingId');
        if (!hiddenBooking) {
            hiddenBooking = document.createElement('input');
            hiddenBooking.type = 'hidden';
            hiddenBooking.name = 'booking_id';
            hiddenBooking.id = 'hiddenBookingId';
            document.getElementById('paymentForm').appendChild(hiddenBooking);
        }
        hiddenBooking.value = data.booking_id;

        // Populate name + photo
        const name = data.full_name || '—';
        const initial = name.charAt(0).toUpperCase();
        document.getElementById('editTenantName').textContent = name + (data.unit_number ? ' — ' + data.unit_number : '');
        document.getElementById('editTenantInitial').textContent = initial;

        const photoEl = document.getElementById('editTenantPhoto');
        if (data.profile_photo) {
            photoEl.src = '../../' + data.profile_photo;
            photoEl.style.display = 'block';
            document.getElementById('editTenantInitial').style.display = 'none';
            photoEl.onerror = function () {
                photoEl.style.display = 'none';
                document.getElementById('editTenantInitial').style.display = 'flex';
            };
        } else {
            photoEl.style.display = 'none';
            document.getElementById('editTenantInitial').style.display = 'flex';
        }

        document.getElementById('formPaymentId').value = data.payment_id;
        dropdown.value = data.booking_id; // still set value for form submit
        document.getElementById('formPaymentDate').value = data.payment_date;
        document.getElementById('formAmountPaid').value = data.amount_paid;
        document.getElementById('formPaymentMethod').value = data.payment_method ?? '';
        document.getElementById('formPaymentStatus').value = data.payment_status;
        document.getElementById('formNotes').value = data.notes ?? '';
    } else {
        // Show dropdown, hide tenant display
        dropdown.style.display = '';
        dropdown.setAttribute('required', 'required');
        editDisplay.style.display = 'none';

        document.getElementById('paymentForm').reset();
        document.getElementById('formPaymentDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('formPaymentStatus').value = 'paid';
    }
    document.getElementById('paymentModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('paymentModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.getElementById('paymentForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const form = this;
    const btn = document.getElementById('submitBtn');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerText = "Saving...";

    fetch(form.getAttribute('action'), {
        method: "POST",
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerText = "Save Payment";

            if (data.success) {
                _psChannel.postMessage({ type: 'payment_saved' });
                showToast(data.message || 'Saved successfully', 'success');
                closeModal();
                setTimeout(() => refreshPaymentsTable(), 600);
            } else {
                showToast(data.message || 'Operation failed', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerText = "Save Payment";

            showToast('Server error occurred', 'error');
        });
});

function confirmDelete(id) {
    document.getElementById('deletePaymentId').value = id;

    document.getElementById('deleteModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.querySelector('input[name="q"]').addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('filterForm').submit();
});

document.querySelectorAll('.flash').forEach(f => {
    setTimeout(() => {
        f.style.transition = 'opacity .4s';
        f.style.opacity = '0';
        setTimeout(() => f.remove(), 400);
    }, 4000);
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
    }
});

const observer = new MutationObserver(() => {
    const modal = document.getElementById('paymentModal');
    if (modal && modal.style.display === 'flex') {
        const first = modal.querySelector('select, input:not([type=hidden])');
        if (first) setTimeout(() => first.focus(), 100);
    }
});
observer.observe(document.getElementById('paymentModal'), {
    attributes: true,
    attributeFilter: ['style']
});

function deletePayment() {
    const id = document.getElementById('deletePaymentId').value;
    if (!id) return;

    fetch('../../api/payments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `form_action=delete&payment_id=${id}&csrf_token=${encodeURIComponent(window.PS_CSRF_TOKEN)}`
    })
        .then(res => res.json())
        .then(data => {

            if (data.success) {
                _psChannel.postMessage({ type: 'payment_deleted' });
                showToast(data.message || 'Deleted successfully', 'success');
                closeDeleteModal();
                setTimeout(() => refreshPaymentsTable(), 600);
            } else {
                showToast(data.message || 'Delete failed', 'error');
            }

        })
        .catch(() => {
            showToast('Server error', 'error');
        });
}

function showToast(message, type = 'success') {
    Toast.fire({
        icon: type,
        title: message
    });
}

function refreshPaymentsTable() {
    window.location.reload();
}

function fmtPeso(v) {
    return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderPaymentsTable(payments) {
    const tbody = document.querySelector('.table-wrap tbody');
    if (!tbody) return;

    if (!payments.length) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-soft);">No payment records found.</td></tr>';
        return;
    }

    const badgeMap = { paid: ['success', 'Paid'], pending: ['pending', 'Pending'], late: ['danger', 'Overdue'] };

    tbody.innerHTML = payments.map(p => {
        const [badgeCls, badgeLabel] = badgeMap[p.payment_status] || ['pending', p.payment_status];
        const displayName = p.full_name || p.tenant_name || '—';
        const initial = displayName.charAt(0).toUpperCase();
        const photoHtml = p.profile_photo
            ? `<img src="../../${p.profile_photo}" alt="${initial}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
               <div class="avatar" style="display:none;">${initial}</div>`
            : `<div class="avatar">${initial}</div>`;
        const payDate = p.payment_date ? new Date(p.payment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
        const payNum = '#PAY-' + String(p.payment_id).padStart(3, '0');
        const dataJson = JSON.stringify(p).replace(/"/g, '&quot;');
        return `<tr data-payment-id="${p.payment_id}">
          <td><strong>${payNum}</strong></td>
          <td><div style="display:flex;align-items:center;gap:8px;">${photoHtml}${displayName}</div></td>
          <td>${p.unit_number || '—'}</td>
          <td>${payDate}</td>
          <td style="font-weight:700;">${p.amount_paid ? fmtPeso(parseFloat(p.amount_paid)) : '—'}</td>
          <td>${p.payment_method || '—'}</td>
          <td><span class="badge badge-${badgeCls}">${badgeLabel}</span></td>
          <td style="color:var(--text-soft);font-size:12px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${(p.notes || '').replace(/"/g, '&quot;')}">${p.notes || '—'}</td>
          <td><div style="display:flex;gap:6px;justify-content:center;">
            <button class="btn-icon btn-edit" title="Edit" onclick="openModal('edit',${dataJson})">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="15" height="15"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn-icon btn-delete" title="Delete" onclick="confirmDelete(${p.payment_id})">
              <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="15" height="15"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div></td>
        </tr>`;
    }).join('');
}