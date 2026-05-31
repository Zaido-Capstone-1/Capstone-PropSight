(function () {
    // Inject modal CSS once
    if (!document.getElementById('ps-modal-styles')) {
        const style = document.createElement('style');
        style.id = 'ps-modal-styles';
        style.textContent = `
      .ps-modal-backdrop {
        position: fixed; inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 1100;
        display: flex; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none;
        transition: opacity .2s;
      }
      .ps-modal-backdrop.open { opacity: 1; pointer-events: all; }
      .ps-modal {
        background: var(--white, #fff);
        border-radius: 14px;
        padding: 28px 30px 24px;
        width: min(540px, 94vw);
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,.18);
        transform: translateY(12px) scale(.97);
        transition: transform .2s;
        position: relative;
      }
      .ps-modal-backdrop.open .ps-modal { transform: none; }
      .ps-modal-title {
        font-size: 17px; font-weight: 700;
        color: var(--text-dark, #1a202c);
        margin-bottom: 20px;
      }
      .ps-modal-close {
        position: absolute; top: 16px; right: 18px;
        background: none; border: none; font-size: 20px;
        cursor: pointer; color: var(--text-soft, #888);
        line-height: 1; padding: 4px 6px; border-radius: 6px;
      }
      .ps-modal-close:hover { background: var(--gray-light, #f4f6f9); }
      .ps-modal .form-group { margin-bottom: 14px; }
      .ps-modal .form-group label {
        display: block; font-size: 12px; font-weight: 600;
        color: var(--text-soft, #888); margin-bottom: 5px;
        text-transform: uppercase; letter-spacing: .5px;
      }
      .ps-modal .form-group input,
      .ps-modal .form-group select,
      .ps-modal .form-group textarea {
        width: 100%; padding: 9px 12px;
        border: 1.5px solid var(--border, #e2e8f0);
        border-radius: var(--radius, 8px);
        font-size: 13.5px; color: var(--text-dark, #1a202c);
        background: var(--white, #fff);
        box-sizing: border-box;
        transition: border-color .15s;
      }
      .ps-modal .form-group input:focus,
      .ps-modal .form-group select:focus,
      .ps-modal .form-group textarea:focus {
        outline: none;
        border-color: var(--blue-400, #2563c4);
        box-shadow: 0 0 0 3px rgba(37,99,196,.12);
      }
      .ps-modal .form-group textarea { resize: vertical; min-height: 80px; }
      .ps-modal-footer {
        display: flex; gap: 10px; justify-content: flex-end;
        margin-top: 22px; padding-top: 16px;
        border-top: 1px solid var(--border, #e2e8f0);
      }
      .ps-modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
      .ps-modal-grid .full { grid-column: 1 / -1; }

      /* Toast */
      #ps-toast-container {
        position: fixed; top: 24px; left: 50%; 
        transform: translateX(-50%);
        display: flex; flex-direction: column; gap: 10px;
        z-index: 9999; pointer-events: none;
        align-items: center;
        }
      .ps-toast {
        border-radius: 10px;
        padding: 12px 18px; font-size: 13.5px;
        box-shadow: 0 6px 24px rgba(0,0,0,.18);
        opacity: 0; transform: translateY(10px);
        transition: opacity .25s, transform .25s;
        pointer-events: none; max-width: 320px;
      }
      .ps-toast.show { opacity: 1; transform: none; }
      .ps-toast.success { background: var(--success, #27ae60); }
      .ps-toast.error   { background: var(--danger,  #e74c3c); }
      .ps-toast.info    { background: var(--blue-400, #2563c4); }

      /* Confirm dialog */
      .ps-confirm-modal { max-width: 380px; }
      .ps-confirm-modal .ps-modal-title { font-size: 15px; }
      .ps-confirm-msg { font-size: 13.5px; color: var(--text-soft,#666); line-height: 1.55; }

      /* Detail view modal */
      .ps-detail-row { display:flex; gap:8px; padding:8px 0; border-bottom:1px solid var(--border,#eee); font-size:13.5px; }
      .ps-detail-row:last-child { border-bottom: none; }
      .ps-detail-label { color: var(--text-soft,#888); min-width: 130px; font-size: 12px; font-weight:600; text-transform:uppercase; letter-spacing:.4px; padding-top:1px; }
      .ps-detail-val { color: var(--text-dark,#1a202c); font-weight:500; flex:1; }

      /* Notification toggle active state */
      .ps-toggle { cursor:pointer; transition:background .2s; }
    `;
        document.head.appendChild(style);
    }

    // Toast container
    if (!document.getElementById('ps-toast-container')) {
        const tc = document.createElement('div');
        tc.id = 'ps-toast-container';
        document.body.appendChild(tc);
    }

    /* ── Public helpers ── */
    window.PS = window.PS || {};

    PS.toast = function (msg, type = 'success', duration = 3000) {
        const t = document.createElement('div');
        t.className = 'ps-toast ' + type;
        t.textContent = msg;
        document.getElementById('ps-toast-container').appendChild(t);
        requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
        setTimeout(() => {
            t.classList.remove('show');
            setTimeout(() => t.remove(), 300);
        }, duration);
    };

    PS.openModal = function (html, opts = {}) {
        const backdrop = document.createElement('div');
        backdrop.className = 'ps-modal-backdrop';
        backdrop.innerHTML = `<div class="ps-modal">${html}<button class="ps-modal-close" aria-label="Close">✕</button></div>`;
        document.body.appendChild(backdrop);
        requestAnimationFrame(() => requestAnimationFrame(() => backdrop.classList.add('open')));

        function close() {
            backdrop.classList.remove('open');
            setTimeout(() => backdrop.remove(), 220);
            if (opts.onClose) opts.onClose();
        }

        backdrop.querySelector('.ps-modal-close').addEventListener('click', close);
        backdrop.addEventListener('click', e => { if (e.target === backdrop) close(); });
        document.addEventListener('keydown', function esc(e) {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
        });

        // Wire cancel buttons
        backdrop.querySelectorAll('[data-ps-cancel]').forEach(b => b.addEventListener('click', close));

        if (opts.onMount) opts.onMount(backdrop);
        return { backdrop, close };
    };

    PS.confirm = function (message, onConfirm, opts = {}) {
        const { title = 'Are you sure?', confirmLabel = 'Confirm', confirmClass = 'btn btn-danger' } = opts;
        const { close } = PS.openModal(`
      <div class="ps-modal-title">${title}</div>
      <p class="ps-confirm-msg">${message}</p>
      <div class="ps-modal-footer">
        <button class="btn btn-secondary" data-ps-cancel>Cancel</button>
        <button class="${confirmClass}" id="ps-confirm-ok">${confirmLabel}</button>
      </div>
    `);
        document.getElementById('ps-confirm-ok').addEventListener('click', () => { close(); onConfirm(); });
    };

    /* ─── Generic detail-row helper ─── */
    PS.detailModal = function (title, rows) {
        const inner = rows.map(([label, val]) =>
            `<div class="ps-detail-row"><span class="ps-detail-label">${label}</span><span class="ps-detail-val">${val}</span></div>`
        ).join('');
        PS.openModal(`
      <div class="ps-modal-title">${title}</div>
      <div>${inner}</div>
      <div class="ps-modal-footer"><button class="btn btn-secondary" data-ps-cancel>Close</button></div>
    `);
    };

})();

/* ─────────────────────────────────────────────
   2.  RESERVATIONS PAGE
   ───────────────────────────────────────────── */
PS.initReservations = function () {
    // New Reservation button
    document.querySelectorAll('.page-header .btn-primary').forEach(btn => {
        if (btn.textContent.trim().includes('New Reservation')) {
            btn.addEventListener('click', () => {
                PS.openModal(`
          <div class="ps-modal-title">New Reservation</div>
          <div class="ps-modal-grid">
            <div class="form-group"><label>Guest Name</label><input type="text" placeholder="Full name" /></div>
            <div class="form-group"><label>Phone</label><input type="tel" placeholder="+63 9XX XXX XXXX" /></div>
            <div class="form-group"><label>Unit</label>
              <select><option>A-101 · Skyline</option><option>A-102 · Skyline</option><option>B-201 · Green Valley</option><option>B-202 · Green Valley</option><option>C-301 · Downtown</option><option>C-302 · Downtown</option></select>
            </div>
            <div class="form-group"><label>Status</label>
              <select><option>Pending</option><option>Confirmed</option></select>
            </div>
            <div class="form-group"><label>Check-in Date</label><input type="date" /></div>
            <div class="form-group"><label>Check-out Date</label><input type="date" /></div>
            <div class="form-group full"><label>Notes</label><textarea placeholder="Optional notes..."></textarea></div>
          </div>
          <div class="ps-modal-footer">
            <button class="btn btn-secondary" data-ps-cancel>Cancel</button>
            <button class="btn btn-primary" id="ps-save-reservation">Save Reservation</button>
          </div>
        `, {
                    onMount(bd) {
                        bd.querySelector('#ps-save-reservation').addEventListener('click', () => {
                            const name = bd.querySelector('input').value.trim();
                            if (!name) { PS.toast('Please enter a guest name.', 'error'); return; }
                            PS.toast('Reservation created successfully!', 'success');
                            bd.classList.remove('open');
                            setTimeout(() => bd.remove(), 220);
                        });
                    }
                });
            });
        }
    });

    // Status filter select
    const sel = document.querySelector('.card-header select');
    if (sel) {
        sel.addEventListener('change', function () {
            const val = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(tr => {
                const badge = tr.querySelector('.badge');
                if (!badge) return;
                tr.style.display = (val === 'all status' || badge.textContent.toLowerCase() === val) ? '' : 'none';
            });
        });
    }
};

/* ─────────────────────────────────────────────
   3.  GUESTS / CLIENTS PAGE
   ───────────────────────────────────────────── */
PS.initGuests = function () {
    // Add Guest button
    document.querySelectorAll('.page-header .btn-primary').forEach(btn => {
        if (btn.textContent.trim().includes('Add Guest')) {
            btn.addEventListener('click', () => {
                PS.openModal(`
          <div class="ps-modal-title">Add Guest / Client</div>
          <div class="ps-modal-grid">
            <div class="form-group"><label>First Name</label><input type="text" id="ps-g-fname" placeholder="First name" /></div>
            <div class="form-group"><label>Last Name</label><input type="text" placeholder="Last name" /></div>
            <div class="form-group"><label>Email</label><input type="email" placeholder="email@example.com" /></div>
            <div class="form-group"><label>Phone</label><input type="tel" placeholder="+63 9XX XXX XXXX" /></div>
            <div class="form-group"><label>Current Unit (optional)</label>
              <select><option value="">— None —</option><option>A-101</option><option>A-102</option><option>B-201</option><option>B-202</option><option>C-301</option><option>C-302</option></select>
            </div>
            <div class="form-group"><label>Status</label>
              <select><option>Guest</option><option>Active</option><option>Inactive</option></select>
            </div>
          </div>
          <div class="ps-modal-footer">
            <button class="btn btn-secondary" data-ps-cancel>Cancel</button>
            <button class="btn btn-primary" id="ps-save-guest">Add Guest</button>
          </div>
        `, {
                    onMount(bd) {
                        bd.querySelector('#ps-save-guest').addEventListener('click', () => {
                            const fname = bd.querySelector('#ps-g-fname').value.trim();
                            if (!fname) { PS.toast('Please enter a first name.', 'error'); return; }
                            PS.toast('Guest added successfully!', 'success');
                            bd.classList.remove('open'); setTimeout(() => bd.remove(), 220);
                        });
                    }
                });
            });
        }
    });

    // Search input
    const search = document.querySelector('.card-header input[type="text"]');
    if (search) {
        search.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
};

/* ─────────────────────────────────────────────
   4.  UNITS / ROOMS PAGE
   ───────────────────────────────────────────── */
PS.initUnits = function () {

};

/* ─────────────────────────────────────────────
   6.  PAYMENTS PAGE
   ───────────────────────────────────────────── */
PS.initPayments = function () {

    // Status filter + month filter
    document.querySelectorAll('.card-header select, .card-header input[type="month"]').forEach(el => {
        el.addEventListener('change', applyPaymentFilters);
    });

    function applyPaymentFilters() {
        const sels = [...document.querySelectorAll('.card-header select')];
        const statusSel = sels.find(s => s.querySelector('option[value=""], option:first-child')?.textContent.includes('Status'));
        const statusVal = statusSel ? statusSel.value.toLowerCase() : '';
        document.querySelectorAll('tbody tr').forEach(tr => {
            const badge = tr.querySelector('.badge');
            if (!badge) return;
            const match = (!statusVal || statusVal === 'all status' || badge.textContent.toLowerCase() === statusVal);
            tr.style.display = match ? '' : 'none';
        });
    }
};

/* ─────────────────────────────────────────────
   7.  INVOICES / BILLING PAGE
   ───────────────────────────────────────────── */
PS.initInvoices = function () {
    const sel = document.querySelector('.card-header select');
    if (sel) {
        sel.addEventListener('change', function () {
            const val = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(tr => {
                const badge = tr.querySelector('.badge');
                if (!badge) return;
                tr.style.display = (val === 'all status' || badge.textContent.toLowerCase() === val) ? '' : 'none';
            });
        });
    }

    // View / Send action buttons in table
    document.querySelectorAll('tbody tr').forEach((tr, i) => {
        const cells = [...tr.cells];
        const invNum = cells[0]?.textContent.trim();
        const tenant = cells[1]?.textContent.trim();
        const total = cells[6]?.textContent.trim();
        const status = cells[7]?.querySelector('.badge')?.textContent.trim();

        const viewBtn = tr.querySelector('.btn-secondary');
        if (viewBtn) {
            viewBtn.addEventListener('click', e => {
                e.preventDefault();
                PS.detailModal(`Invoice: ${invNum}`, [
                    ['Tenant', tenant],
                    ['Unit', cells[2]?.textContent.trim()],
                    ['Issued', cells[3]?.textContent.trim()],
                    ['Due', cells[4]?.textContent.trim()],
                    ['Items', cells[5]?.textContent.trim()],
                    ['Total', total],
                    ['Status', status],
                ]);
            });
        }

        const sendBtn = tr.querySelector('.btn-primary');
        if (sendBtn && sendBtn.textContent.trim() === 'Send') {
            sendBtn.addEventListener('click', e => {
                e.preventDefault();
                PS.confirm(`Send invoice ${invNum} to ${tenant}?`, () => {
                    PS.toast(`Invoice ${invNum} sent to ${tenant}!`, 'info');
                }, { title: 'Send Invoice', confirmLabel: 'Send', confirmClass: 'btn btn-primary' });
            });
        }
    });
};

/* ─────────────────────────────────────────────
   9.  AMENITIES PAGE
   ───────────────────────────────────────────── */
PS.initAmenities = function () {
    document.querySelectorAll('.page-header .btn-primary').forEach(btn => {
        if (btn.textContent.trim().includes('Add Amenity')) {
            btn.addEventListener('click', () => {
                PS.openModal(`
          <div class="ps-modal-title">Add Amenity</div>
          <div class="ps-modal-grid">
            <div class="form-group full"><label>Amenity Name</label><input type="text" id="ps-am-name" placeholder="e.g. Rooftop Deck" /></div>
            <div class="form-group"><label>Property</label>
              <select><option>Skyline Apartments</option><option>Green Valley Homes</option><option>Downtown Lofts</option><option>All Properties</option></select>
            </div>
            <div class="form-group"><label>Status</label>
              <select><option>✅ Available</option><option>⚠️ Under Repair</option><option>❌ Not Available</option></select>
            </div>
            <div class="form-group full"><label>Description (optional)</label><textarea placeholder="Brief description..."></textarea></div>
          </div>
          <div class="ps-modal-footer">
            <button class="btn btn-secondary" data-ps-cancel>Cancel</button>
            <button class="btn btn-primary" id="ps-save-amenity">Save Amenity</button>
          </div>
        `, {
                    onMount(bd) {
                        bd.querySelector('#ps-save-amenity').addEventListener('click', () => {
                            const name = bd.querySelector('#ps-am-name').value.trim();
                            if (!name) { PS.toast('Please enter an amenity name.', 'error'); return; }
                            PS.toast(`Amenity "${name}" added!`, 'success');
                            bd.classList.remove('open'); setTimeout(() => bd.remove(), 220);
                        });
                    }
                });
            });
        }
    });
};

/* ─────────────────────────────────────────────
   10.  MESSAGES PAGE
   ───────────────────────────────────────────── */
// PS.initMessages is handled entirely by assets/js/admin/messages.js.
// This stub is intentionally left empty to avoid duplicate listeners.
PS.initMessages = function () {};

/* ─────────────────────────────────────────────
   12.  SETTINGS PAGE
   ───────────────────────────────────────────── */
PS.initSettings = function () {
    // Profile form
    const forms = document.querySelectorAll('.card form');
    forms.forEach((form, i) => {
        form.addEventListener('submit', e => {
            e.preventDefault();
            if (i === 0) {
                // Profile
                PS.toast('Profile updated successfully!', 'success');
            } else {
                // Password
                const inputs = form.querySelectorAll('input[type="password"]');
                if (inputs.length >= 3) {
                    const newPw = inputs[1].value;
                    const confirmPw = inputs[2].value;
                    if (newPw.length < 6) { PS.toast('Password must be at least 6 characters.', 'error'); return; }
                    if (newPw !== confirmPw) { PS.toast('Passwords do not match.', 'error'); return; }
                }
                PS.toast('Password updated!', 'success');
                form.reset();
            }
        });
    });

    // Change Photo button
    document.querySelectorAll('.btn-secondary').forEach(btn => {
        if (btn.textContent.trim().includes('Change Photo')) {
            btn.addEventListener('click', () => {
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'image/*';
                input.addEventListener('change', () => {
                    if (input.files[0]) PS.toast('Profile photo updated!', 'success');
                });
                input.click();
            });
        }
    });

    // System preferences save (no form tag)
    const prefSelects = document.querySelectorAll('.cards-area .card:nth-of-type(3) select');
    prefSelects.forEach(sel => {
        sel.addEventListener('change', () => PS.toast('Preference saved.', 'info'));
    });

    // Notification toggles
    document.querySelectorAll('[style*="border-radius:20px"]').forEach(toggle => {
        toggle.classList.add('ps-toggle');
        let on = toggle.style.background.includes('blue') || toggle.style.background.includes('400');
        toggle.addEventListener('click', function () {
            on = !on;
            this.style.background = on ? 'var(--blue-400)' : 'var(--border)';
            const dot = this.querySelector('div');
            if (dot) dot.style.left = on ? '21px' : '3px';
            PS.toast(on ? 'Notification enabled.' : 'Notification disabled.', 'info');
        });
    });
};

/* ─────────────────────────────────────────────
   13.  BOOKING REPORTS / FINANCIAL REPORTS /
        OCCUPANCY REPORTS / ANALYTICS / TRANSACTIONS
        — Export & filter buttons
   ───────────────────────────────────────────── */
PS.initReports = function () {
    // Export buttons — only secondary buttons labelled "Export" or "Export PDF"
    document.querySelectorAll('.page-header .btn-secondary').forEach(btn => {
        if (btn.textContent.trim().includes('Export')) {
            btn.addEventListener('click', () => {
                PS.toast('Export started — file will download shortly.', 'info');
            });
        }
    });

    // Status / date filter selects inside card headers
    document.querySelectorAll('.card-header select').forEach(sel => {
        sel.addEventListener('change', function () {
            const val = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(tr => {
                const badge = tr.querySelector('.badge');
                if (!badge) return;
                tr.style.display = (val === 'all' || val.includes('all') || badge.textContent.toLowerCase().includes(val)) ? '' : 'none';
            });
        });
    });

    // Primary button on report pages — only fire if label is explicitly report-related
    document.querySelectorAll('.page-header .btn-primary').forEach(btn => {
        const label = btn.textContent.trim();
        if (label.includes('Generate') || label.includes('Run Report') || label.includes('Refresh')) {
            btn.addEventListener('click', () => PS.toast('Report generated!', 'success'));
        }
    });
};

/* ─────────────────────────────────────────────
   14.  CHECK-IN / CHECK-OUT PAGE
   ───────────────────────────────────────────── */
PS.initCheckinCheckout = function () {
    document.querySelectorAll('.btn-primary').forEach(btn => {
        if (btn.textContent.trim().includes('Check In') || btn.textContent.trim().includes('Check-in')) {
            btn.addEventListener('click', () => PS.toast('Check-in recorded!', 'success'));
        }
        if (btn.textContent.trim().includes('Check Out') || btn.textContent.trim().includes('Check-out')) {
            btn.addEventListener('click', () => PS.toast('Check-out recorded!', 'success'));
        }
    });
};

/* ─────────────────────────────────────────────
   15.  DASHBOARD — Task "See all" link
   ───────────────────────────────────────────── */
PS.initDashboard = function () {
    document.querySelectorAll('.see-all').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            PS.openModal(`
        <div class="ps-modal-title">All Tasks</div>
        <div>
          ${[
                    ['Fix HVAC Unit', 'Skyline Apartments', 'Urgent', 'danger'],
                    ['Monthly Landscaping', 'Green Valley Homes', 'Scheduled', 'blue'],
                    ['Quarterly Inspection', 'Downtown Lofts', 'Pending', 'pending'],
                    ['Lease Renewal – B-201', 'Green Valley Homes', 'Done', 'success'],
                    ['Paint Unit A-102', 'Skyline Apartments', 'Scheduled', 'blue'],
                    ['Replace Water Heater', 'Downtown Lofts', 'Pending', 'pending'],
                ].map(([task, prop, status, cls]) => `
            <div class="ps-detail-row">
              <span class="ps-detail-label">${task}</span>
              <span class="ps-detail-val" style="color:var(--text-soft);font-size:12px;">${prop}</span>
              <span class="badge badge-${cls}" style="margin-left:auto;">${status}</span>
            </div>`).join('')}
        </div>
        <div class="ps-modal-footer"><button class="btn btn-secondary" data-ps-cancel>Close</button></div>
      `);
        });
    });

    // Period select on revenue chart
    const periodSel = document.querySelector('.period-select');
    if (periodSel) {
        periodSel.addEventListener('change', () => PS.toast(`Showing data for ${periodSel.value}.`, 'info'));
    }
};

/* ─────────────────────────────────────────────
   16.  AUTO-DETECT & INITIALISE
   ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    const path = window.location.pathname.toLowerCase();
    const page = path.split('/').pop().replace('.php', '');

    const reportPages = ['booking_reports', 'financial_reports', 'occupancy_reports', 'analytics', 'transactions'];
    if (reportPages.includes(page)) PS.initReports();

    if (page === 'reservations') PS.initReservations();
    if (page === 'guests_clients') PS.initGuests();
    if (page === 'units_rooms') PS.initUnits();
    // if (page === 'expenses') PS.initExpenses();
    // if (page === 'payments') PS.initPayments();
    if (page === 'invoices_billing') PS.initInvoices();
    if (page === 'staff_roles') PS.initStaff();
    if (page === 'amenities') PS.initAmenities();
    if (page === 'messages') PS.initMessages();
    if (page === 'properties_list') PS.initProperties();
    if (page === 'settings') PS.initSettings();
    if (page === 'index') PS.initDashboard();
    if (page === 'checkin_checkout') PS.initCheckinCheckout();
});