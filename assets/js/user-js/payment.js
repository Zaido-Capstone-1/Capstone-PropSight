function openModal(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
function openAddCard() { clearCardForm(); updatePreview(); openModal('addCardModal'); }
function closeAddCard() { closeModal('addCardModal'); }

// ── LIVE CARD PREVIEW ──────────────────────────────
function updatePreview() {
    const raw = (document.getElementById('cardNumber')?.value || '').replace(/\s/g, '');
    const holder = (document.getElementById('cardHolder')?.value || '').trim();
    const expiry = (document.getElementById('cardExpiry')?.value || '').trim();
    const groups = [];
    for (let i = 0; i < 4; i++) {
        const chunk = raw.slice(i * 4, i * 4 + 4);
        groups.push(chunk.padEnd(4, '•'));
    }
    document.getElementById('previewNumber').textContent = groups.join(' ');
    document.getElementById('previewHolder').textContent = holder.toUpperCase() || 'YOUR NAME';
    document.getElementById('previewExpiry').textContent = expiry || 'MM/YY';
    const type = raw.startsWith('4') ? 'VISA' : /^5[1-5]/.test(raw) ? 'MC' : /^3[47]/.test(raw) ? 'AMEX' : 'CARD';
    document.getElementById('previewType').textContent = type;
    const colors = {
        VISA: 'linear-gradient(135deg,#0f2447,#1e50a2)',
        MC: 'linear-gradient(135deg,#7f1d1d,#b91c1c)',
        AMEX: 'linear-gradient(135deg,#064e3b,#047857)',
        CARD: 'linear-gradient(135deg,var(--blue-800),var(--blue-500))'
    };
    document.getElementById('cardPreview').style.background = colors[type];
}

// ── AUTO-FORMAT ────────────────────────────────────
function formatCardNumber(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 16);
    input.value = v.replace(/(.{4})/g, '$1 ').trim();
}
function formatExpiry(input) {
    let v = input.value.replace(/\D/g, '').slice(0, 4);
    if (v.length >= 3) v = v.slice(0, 2) + ' / ' + v.slice(2);
    input.value = v;
}

// ── CLEAR FORM ─────────────────────────────────────
function clearCardForm() {
    ['cardNumber', 'cardExpiry', 'cardCvv'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const err = document.getElementById('cardError');
    if (err) err.style.display = 'none';
}

// ── VALIDATE & SAVE ────────────────────────────────
function saveCard() {
    const num = (document.getElementById('cardNumber').value || '').replace(/\s/g, '');
    const expiry = (document.getElementById('cardExpiry').value || '').trim();
    const cvv = (document.getElementById('cardCvv').value || '').trim();
    const holder = (document.getElementById('cardHolder').value || '').trim();
    const errEl = document.getElementById('cardError');
    errEl.style.display = 'none';

    if (num.length !== 16 || !/^\d+$/.test(num)) { showCardError('Please enter a valid 16-digit card number.'); return; }
    if (!/^\d{2}\s*\/\s*\d{2}$/.test(expiry)) { showCardError('Please enter expiry as MM / YY.'); return; }
    if (cvv.length < 3 || !/^\d+$/.test(cvv)) { showCardError('CVV must be 3–4 digits.'); return; }
    if (!holder) { showCardError('Cardholder name is required.'); return; }

    const [mm, yy] = expiry.replace(/\s/g, '').split('/');
    if (new Date(2000 + parseInt(yy), parseInt(mm) - 1, 1) < new Date()) { showCardError('This card has expired.'); return; }

    const provider = num.startsWith('4') ? 'Visa' : /^5[1-5]/.test(num) ? 'Mastercard' : /^3[47]/.test(num) ? 'Amex' : 'Card';
    const bg = { Visa: 'linear-gradient(135deg,#0f2447,#1e50a2)', Mastercard: 'linear-gradient(135deg,#7f1d1d,#b91c1c)', Amex: 'linear-gradient(135deg,#064e3b,#047857)', Card: 'linear-gradient(135deg,#153060,#1e50a2)' }[provider];

    const btn = document.getElementById('saveCardBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Saving…';

    const fd = new FormData();
    fd.append('action', 'add_card');
    fd.append('provider', provider);
    fd.append('card_number', num);
    fd.append('expiry_month', mm);
    fd.append('expiry_year', yy);
    fd.append('holder_name', holder);
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    fetch('../../api/user/payment_methods.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Save Card';

            if (!d.success) { showCardError(d.message || 'Could not save card.'); return; }

            const last4 = num.slice(-4);
            const expDisp = mm + '/' + yy;
            const dbId = d.id;

            const grid = document.querySelector('.cards-list');
            const newWrap = document.createElement('div');
            newWrap.className = 'card-item-wrap';
            newWrap.dataset.id = dbId;
            newWrap.innerHTML = `
                <div class="card-visual" style="background:${bg}">
                    <div><div class="cv-chip"></div><div class="cv-number">•••• •••• •••• ${last4}</div></div>
                    <div class="cv-footer">
                        <div><div class="cv-label">Card Holder</div><div class="cv-value">${holder.toUpperCase()}</div></div>
                        <div style="text-align:right;"><div class="cv-label">Expires</div><div class="cv-value">${expDisp}</div></div>
                        <div class="cv-type">${provider}</div>
                    </div>
                </div>
                <div class="card-actions">
                    <button class="btn-secondary" style="font-size:0.72rem;padding:7px 14px;"
                        onclick="handleSetDefault(${dbId}, 'card', this)">Set Default</button>
                    <button class="btn-danger" style="font-size:0.72rem;padding:7px 14px;"
                        onclick="handleRemoveCard(${dbId}, this)">Remove</button>
                </div>`;

            grid.appendChild(newWrap);
            closeAddCard();
            showToast(`${provider} ending in ${last4} added!`);
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Save Card';
            showCardError('Network error. Please try again.');
        });
}

function showCardError(msg) {
    const el = document.getElementById('cardError');
    el.textContent = msg;
    el.style.display = 'block';
}

// ── SET DEFAULT ────────────────────────────────────
function handleSetDefault(id, type, btn) {
    btn.disabled = true;
    btn.textContent = 'Setting…';

    const fd = new FormData();
    fd.append('action', 'set_default');
    fd.append('id', id);
    fd.append('type', type);
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    fetch('../../api/user/payment_methods.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.textContent = 'Set Default';
            if (!d.success) { showToast(d.message || 'Error', true); return; }

            document.querySelectorAll('.cv-default-badge').forEach(b => b.remove());
            document.querySelectorAll('.card-actions .btn-secondary').forEach(b => {
                if (b.textContent.trim() === 'Default') {
                    b.textContent = 'Set Default';
                    b.style.display = '';
                }
            });

            const wrap = btn.closest('.card-item-wrap');
            const badge = Object.assign(document.createElement('div'), { className: 'cv-default-badge', textContent: 'Default' });
            wrap.querySelector('.card-visual').appendChild(badge);
            btn.remove();
            showToast('Default payment method updated.');
        })
        .catch(() => { btn.disabled = false; btn.textContent = 'Set Default'; showToast('Network error.', true); });
}

// ── REMOVE CARD ────────────────────────────────────
function handleRemoveCard(id, btn) {
    const wrap = btn.closest('.card-item-wrap');
    if (wrap.querySelector('.cv-default-badge')) {
        showToast('Cannot remove your default card. Set another as default first.', true);
        return;
    }
    btn.disabled = true;
    btn.textContent = 'Removing…';

    const fd = new FormData();
    fd.append('action', 'remove');
    fd.append('id', id);
    fd.append('type', 'card');
    if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

    fetch('../../api/user/payment_methods.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { btn.disabled = false; btn.textContent = 'Remove'; showToast(d.message || 'Error', true); return; }
            wrap.style.transition = 'opacity 0.3s,transform 0.3s';
            wrap.style.opacity = '0';
            wrap.style.transform = 'scale(0.95)';
            setTimeout(() => { wrap.remove(); showToast('Card removed.'); }, 300);
        })
        .catch(() => { btn.disabled = false; btn.textContent = 'Remove'; showToast('Network error.', true); });
}

// ── WIRE UP PHP-RENDERED CARDS ─────────────────────
document.querySelectorAll('.card-actions .btn-secondary').forEach(btn => {
    if (btn.textContent.trim() !== 'Set Default') return;
    const wrap = btn.closest('.card-item-wrap');
    const id = wrap?.dataset?.id;
    if (!id) return;
    btn.addEventListener('click', function () { handleSetDefault(id, 'card', this); });
});

document.querySelectorAll('.card-actions .btn-danger').forEach(btn => {
    const wrap = btn.closest('.card-item-wrap');
    const id = wrap?.dataset?.id;
    if (!id) return;
    btn.addEventListener('click', function () { handleRemoveCard(id, this); });
});

// ── LINK E-WALLET ──────────────────────────────────
document.querySelectorAll('.ewallet-item .btn-secondary').forEach(btn => {
    btn.addEventListener('click', function () {
        const item = this.closest('.ewallet-item');
        const provider = item.querySelector('.ewallet-name').textContent;
        this.disabled = true;
        this.textContent = 'Linking…';

        const account = prompt(`Enter your ${provider} account number / mobile number:`);
        if (!account) { this.disabled = false; this.textContent = 'Link'; return; }

        const fd = new FormData();
        fd.append('action', 'add_ewallet');
        fd.append('provider', provider);
        fd.append('account_number', account);
        if (typeof window.psAppendCsrf === 'function') window.psAppendCsrf(fd);

        fetch('../../api/user/payment_methods.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (!d.success) { this.disabled = false; this.textContent = 'Link'; showToast(d.message || 'Error', true); return; }
                item.classList.add('linked');
                item.querySelector('.ewallet-num').textContent = account;
                this.replaceWith(Object.assign(document.createElement('span'), { className: 'badge badge-green', textContent: 'Linked' }));
                showToast(provider + ' linked successfully!');
            })
            .catch(() => { this.disabled = false; this.textContent = 'Link'; showToast('Network error.', true); });
    });
});

// ── INVOICE DOWNLOAD ───────────────────────────────
function downloadInvoice(paymentId, btn) {
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = '…';
    const a = document.createElement('a');
    a.href = '../../api/user/booking_receipt.php?booking_id=' + paymentId;
    a.target = '_blank';
    a.click();
    setTimeout(() => { btn.disabled = false; btn.textContent = orig; showToast('Invoice opened.'); }, 800);
}

// ── CUSTOM FILTER DROPDOWNS ────────────────────────
function toggleFdd(name) {
    const menu = document.getElementById('fdd' + name + 'Menu');
    const btn = document.getElementById('fdd' + name + 'Btn');
    const isOpen = menu.classList.contains('open');

    // Close all menus first
    document.querySelectorAll('.fdd-menu').forEach(m => m.classList.remove('open'));
    document.querySelectorAll('.fdd-trigger').forEach(b => b.setAttribute('aria-expanded', 'false'));

    if (!isOpen) {
        menu.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
    }
}

let activeType = 'all';
let activeStatus = 'all';

document.querySelectorAll('[data-filter-type]').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('[data-filter-type]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        activeType = this.dataset.filterType;
        filterUnified();
    });
});

document.querySelectorAll('[data-filter-status]').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('[data-filter-status]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        activeStatus = this.dataset.filterStatus;
        filterUnified();
    });
});

function filterUnified() {
    const type = document.getElementById('filterType')?.value || 'all';
    const status = document.getElementById('filterStatus')?.value || 'all';
    const q = (document.getElementById('billingSearch')?.value || '').toLowerCase().trim();

    const rows = Array.from(document.querySelectorAll('#billingTable tbody tr[data-type]'));

    // Determine which rows match the filter
    rows.forEach(row => {
        const typeMatch = type === 'all' || row.dataset.type === type;
        const statusMatch = status === 'all' || row.dataset.status === status;
        const searchMatch = !q || (row.dataset.search || '').includes(q);
        row._visible = typeMatch && statusMatch && searchMatch;
        row.style.display = 'none'; // hide all first
    });

    const matched = rows.filter(r => r._visible);
    renderTxPage(matched, 1);
}

const TX_PER_PAGE = 10;

function renderTxPage(matchedRows, page) {
    const total = matchedRows.length;
    const totalPages = Math.max(1, Math.ceil(total / TX_PER_PAGE));
    page = Math.min(Math.max(1, page), totalPages);

    const start = (page - 1) * TX_PER_PAGE;
    const end = start + TX_PER_PAGE;

    // All rows off, then show only this page's slice
    matchedRows.forEach((row, i) => {
        row.style.display = (i >= start && i < end) ? '' : 'none';
    });

    const emptyEl = document.getElementById('billingEmpty');
    if (emptyEl) emptyEl.style.display = total === 0 ? 'block' : 'none';

    // Render pagination bar
    // Inject pagination styles once
    if (!document.getElementById('txPgStyles')) {
        const s = document.createElement('style');
        s.id = 'txPgStyles';
        s.textContent = `
            #txPaginationBar { animation: txFadeIn .25s ease; }
            @keyframes txFadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
            .tx-pg-btn {
                min-width:32px; height:32px; padding:0 10px;
                border-radius:50%; font-size:.78rem; font-weight:600;
                cursor:pointer; border:1px solid #dbe8f6; background:#fff;
                color:var(--ink-soft); transition:background .18s, border-color .18s, color .18s, transform .15s, box-shadow .18s;
            }
            .tx-pg-btn:not(:disabled):hover {
                background: #f1f5f9;
                border-color: #cbd5e1;
            }
            .tx-pg-btn.active {
                background: #0f2744;
                color: #e8c86a;
                border-color: #0f2744;
                cursor: pointer;
            }
            .tx-pg-btn:disabled { opacity:.35; cursor:not-allowed; }
        `;
        document.head.appendChild(s);
    }

    let pgWrap = document.getElementById('txPaginationBar');
    if (!pgWrap) {
        pgWrap = document.createElement('div');
        pgWrap.id = 'txPaginationBar';
        pgWrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:6px;padding:20px 0 8px;';
        const table = document.getElementById('billingTable');
        if (table) table.parentNode.insertBefore(pgWrap, table.nextSibling);
    }
    pgWrap.innerHTML = '';

    if (totalPages <= 1) return;

    const btnRow = document.createElement('div');
    btnRow.style.cssText = 'display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:center;';

    function makeBtn(label, targetPage, isActive, isDisabled) {
        const b = document.createElement('button');
        b.innerHTML = label;
        b.className = 'tx-pg-btn' + (isActive ? ' active' : '');
        if (isDisabled) b.disabled = true;
        if (!isDisabled) b.addEventListener('click', () => renderTxPage(window._txMatchedRows, targetPage));
        return b;
    }

    btnRow.appendChild(makeBtn('&#8592;', page - 1, false, page === 1));
    for (let i = 1; i <= totalPages; i++) {
        btnRow.appendChild(makeBtn(i, i, i === page, false));
    }
    btnRow.appendChild(makeBtn('&#8594;', page + 1, false, page === totalPages));
    pgWrap.appendChild(btnRow);

    const info = document.createElement('div');
    info.style.cssText = 'font-size:.68rem;color:var(--ink-faint);letter-spacing:.03em;';
    info.textContent = `Showing ${Math.min(start + 1, total)}–${Math.min(end, total)} of ${total}`;
    pgWrap.appendChild(info);

    // Store current matched rows for page navigation
    window._txMatchedRows = matchedRows;
}

function pickFdd(name, val, label) {
    document.getElementById('filter' + name).value = val;
    document.getElementById('fdd' + name + 'Label').textContent = label;
    const btn = document.getElementById('fdd' + name + 'Btn');
    btn.classList.toggle('is-active', val !== 'all');
    document.querySelectorAll('#fdd' + name + 'Menu .fdd-option').forEach(opt => {
        opt.classList.toggle('selected', opt.dataset.val === val);
    });
    document.getElementById('fdd' + name + 'Menu').classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
    filterUnified();
}

document.addEventListener('click', e => {
    if (!e.target.closest('.fdd-wrap')) {
        document.querySelectorAll('.fdd-menu').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.fdd-trigger').forEach(b => b.setAttribute('aria-expanded', 'false'));
    }
});

document.addEventListener('DOMContentLoaded', () => filterUnified());

// ── MODAL & KEYBOARD ──────────────────────────────
document.getElementById('addCardModal')?.addEventListener('click', e => {
    if (e.target.id === 'addCardModal') closeAddCard();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeAddCard();
        if (typeof closeSidebar === 'function') closeSidebar();
    }
});