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
    a.href = '../../api/admin/invoice.php?payment_id=' + paymentId;
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
    const q = (document.getElementById('billingSearch')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#billingTable tbody tr[data-type]');
    let visible = 0;

    rows.forEach(row => {
        const typeMatch = activeType === 'all' || row.dataset.type === activeType;
        const statusMatch = activeStatus === 'all' || row.dataset.status === activeStatus;
        const searchMatch = !q || (row.dataset.search || '').includes(q);
        const show = typeMatch && statusMatch && searchMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const empty = document.getElementById('billingEmpty');
    if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
}
function pickFdd(name, val, label) {
    // Update hidden input
    document.getElementById('filter' + name).value = val;

    // Update label text
    document.getElementById('fdd' + name + 'Label').textContent = label;

    // Toggle active state on trigger
    const btn = document.getElementById('fdd' + name + 'Btn');
    btn.classList.toggle('is-active', val !== 'all');

    // Update selected option styling
    document.querySelectorAll('#fdd' + name + 'Menu .fdd-option').forEach(opt => {
        opt.classList.toggle('selected', opt.dataset.val === val);
    });

    // Close menu
    document.getElementById('fdd' + name + 'Menu').classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');

    filterUnified();
}

// Close dropdowns when clicking outside
document.addEventListener('click', e => {
    if (!e.target.closest('.fdd-wrap')) {
        document.querySelectorAll('.fdd-menu').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.fdd-trigger').forEach(b => b.setAttribute('aria-expanded', 'false'));
    }
});

// ── UNIFIED FILTER & SEARCH ────────────────────────
function filterUnified() {
    const type = document.getElementById('filterType')?.value || 'all';
    const status = document.getElementById('filterStatus')?.value || 'all';
    const q = (document.getElementById('billingSearch')?.value || '').toLowerCase().trim();

    const rows = document.querySelectorAll('#billingTable tbody tr[data-type]');
    let visible = 0;

    rows.forEach(row => {
        const typeMatch = type === 'all' || row.dataset.type === type;
        const statusMatch = status === 'all' || row.dataset.status === status;
        const searchMatch = !q || (row.dataset.search || '').includes(q);
        const show = typeMatch && statusMatch && searchMatch;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const emptyEl = document.getElementById('billingEmpty');
    if (emptyEl) emptyEl.style.display = (visible === 0 && rows.length > 0) ? 'block' : 'none';
}

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
