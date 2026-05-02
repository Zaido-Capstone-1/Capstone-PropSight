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