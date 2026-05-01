let currentBookPrice = 0;

function openModal(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }

function openBookModal(name, price) {
    currentBookPrice = price;
    document.getElementById('bookModalTitle').textContent = name;
    document.getElementById('bookModalPrice').textContent = '₱' + price.toLocaleString() + ' / night';
    document.getElementById('bookError').style.display = 'none';
    const today = new Date(); today.setDate(today.getDate() + 1);
    const out = new Date(today); out.setDate(out.getDate() + 3);
    document.getElementById('book_checkin').value = today.toISOString().split('T')[0];
    document.getElementById('book_checkout').value = out.toISOString().split('T')[0];
    updateBookTotal();
    openModal('bookModal');
}
function updateBookTotal() {
    const ci = document.getElementById('book_checkin').value;
    const co = document.getElementById('book_checkout').value;
    if (ci && co && new Date(co) > new Date(ci)) {
        const nights = Math.round((new Date(co) - new Date(ci)) / (1000 * 60 * 60 * 24));
        const total = nights * currentBookPrice;
        document.getElementById('bookTotal').innerHTML =
            `<strong>${nights} night${nights > 1 ? 's' : ''}</strong> × ₱${currentBookPrice.toLocaleString()} = <strong style="color:var(--blue-500);">₱${total.toLocaleString()}</strong>`;
    } else {
        document.getElementById('bookTotal').textContent = 'Select dates to see total.';
    }
}
document.getElementById('book_checkin').addEventListener('change', updateBookTotal);
document.getElementById('book_checkout').addEventListener('change', updateBookTotal);

function confirmBook() {
    const ci = document.getElementById('book_checkin').value;
    const co = document.getElementById('book_checkout').value;
    const errEl = document.getElementById('bookError');
    errEl.style.display = 'none';
    if (!ci || !co || new Date(co) <= new Date(ci)) { errEl.textContent = 'Please select valid dates.'; errEl.style.display = 'block'; return; }
    const btn = document.getElementById('bookConfirmBtn');
    btn.disabled = true; btn.textContent = 'Processing…';
    setTimeout(() => {
        closeModal('bookModal');
        btn.disabled = false; btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg> Confirm Booking';
        showToast('Booking confirmed! Confirmation sent to your email.');
    }, 800);
}

function unsaveRoom(btn) {
    const card = btn.closest('.saved-card');
    card.style.transition = 'opacity 0.3s, transform 0.3s';
    card.style.opacity = '0'; card.style.transform = 'scale(0.95)';
    setTimeout(() => { card.remove(); showToast('Room removed from saved list.'); }, 300);
}

document.getElementById('bookModal').addEventListener('click', e => { if (e.target.id === 'bookModal') closeModal('bookModal'); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal('bookModal'); closeSidebar(); } });