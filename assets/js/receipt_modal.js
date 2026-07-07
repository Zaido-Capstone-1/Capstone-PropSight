/**
 * assets/js/receipt-modal.js
 * Shared receipt preview modal — used by pages/user/bookings.php and
 * pages/admin/reservations.php.
 *
 * Usage: openReceiptModal(bookingId)
 * Requires: assets/css/receipt-modal.css to be linked on the page.
 * Endpoint: endpoints/user/booking_receipt.php (role-aware: admins can view
 * any booking, users only their own — enforced server-side).
 */
(function () {
  let _html2canvasLoaded = false;
  let _overlay, _iframe, _loading, _status, _btnImage;
  let _currentBookingId = null;

  function _endpointBase() {
    // Both pages/user/*.php and pages/admin/*.php sit two levels below root.
    return '../../endpoints/user/booking_receipt.php';
  }

  function _loadHtml2Canvas() {
    if (_html2canvasLoaded) return Promise.resolve();
    return new Promise((resolve, reject) => {
      const s1 = document.createElement('script');
      s1.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
      s1.onload = () => { _html2canvasLoaded = true; resolve(); };
      s1.onerror = reject;
      document.head.appendChild(s1);
    });
  }

  function _ensureModal() {
    if (_overlay) return;

    _overlay = document.createElement('div');
    _overlay.className = 'rcpt-modal-overlay';
    _overlay.innerHTML = `
      <div class="rcpt-modal">
        <button type="button" class="rcpt-modal-close" id="rcptModalClose" aria-label="Close">
          <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="rcpt-modal-body" id="rcptModalBody">
          <div class="rcpt-modal-loading" id="rcptModalLoading">
            <span class="rcpt-spinner"></span> Loading receipt…
          </div>
          <iframe id="rcptModalFrame" title="Receipt preview" style="height:0;"></iframe>
        </div>
        <div class="rcpt-modal-footer">
          <span class="rcpt-modal-status" id="rcptModalStatus"><span class="rcpt-spinner"></span> Generating…</span>
          <button type="button" class="rcpt-btn rcpt-btn-secondary" id="rcptModalCancel">Close</button>
          <button type="button" class="rcpt-btn rcpt-btn-image" id="rcptModalImageBtn">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Download Image
          </button>
        </div>
      </div>`;
    document.body.appendChild(_overlay);

    _iframe = _overlay.querySelector('#rcptModalFrame');
    _loading = _overlay.querySelector('#rcptModalLoading');
    _status = _overlay.querySelector('#rcptModalStatus');
    _btnImage = _overlay.querySelector('#rcptModalImageBtn');

    _overlay.querySelector('#rcptModalClose').addEventListener('click', closeReceiptModal);
    _overlay.querySelector('#rcptModalCancel').addEventListener('click', closeReceiptModal);
    _overlay.addEventListener('click', (e) => { if (e.target === _overlay) closeReceiptModal(); });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && _overlay.classList.contains('open')) closeReceiptModal();
    });

    _btnImage.addEventListener('click', () => _exportReceipt());
  }

  function _setStatus(msg) {
    if (!msg) { _status.classList.remove('visible'); return; }
    _status.innerHTML = `<span class="rcpt-spinner"></span> ${msg}`;
    _status.classList.add('visible');
  }

  function _setButtonsDisabled(disabled) {
    _btnImage.disabled = disabled;
  }

  async function _exportReceipt() {
    const iCard = _iframe.contentDocument && _iframe.contentDocument.getElementById('receiptCard');
    if (!iCard) return;

    _setButtonsDisabled(true);
    _setStatus('Generating image…');

    try {
      await _loadHtml2Canvas();

      const canvas = await html2canvas(iCard, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
        logging: false,
        allowTaint: true,
      });

      const fileBase = 'Receipt-BK-' + String(_currentBookingId).padStart(6, '0');

      const url = canvas.toDataURL('image/png');
      const a = document.createElement('a');
      a.href = url;
      a.download = fileBase + '.png';
      document.body.appendChild(a);
      a.click();
      a.remove();

      _setStatus('');
    } catch (err) {
      console.error('Receipt export failed:', err);
      if (typeof showToast === 'function') {
        showToast('Could not generate the receipt image. Try again.', 'error');
      }
      _setStatus('');
    } finally {
      _setButtonsDisabled(false);
    }
  }

  function _resizeFrame() {
    try {
      const doc = _iframe.contentDocument;
      const h = Math.max(doc.body.scrollHeight, doc.documentElement.scrollHeight);
      _iframe.style.height = h + 'px';
    } catch (e) { /* ignore */ }
  }

  window.openReceiptModal = async function (bookingId) {
    _ensureModal();
    _currentBookingId = bookingId;
    _iframe.style.height = '0';
    _loading.style.display = 'flex';
    _setStatus('');
    _overlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    try {
      const res = await fetch(_endpointBase() + '?booking_id=' + encodeURIComponent(bookingId) + '&view=1', { credentials: 'same-origin' });
      if (!res.ok) throw new Error('Fetch failed ' + res.status);
      const html = await res.text();

      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const card = doc.getElementById('receiptCard');
      if (!card) throw new Error('receiptCard not found');

      const stylePromises = [];
      doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
        const href = link.getAttribute('href');
        if (!href) return;
        const base = new URL(_endpointBase(), window.location.href);
        const absHref = new URL(href, base).href;
        stylePromises.push(fetch(absHref).then(r => r.text()).catch(() => ''));
      });

      let inlineStyles = '';
      doc.querySelectorAll('style').forEach(s => { inlineStyles += s.textContent; });

      const sheetTexts = await Promise.all(stylePromises);
      const allCss = sheetTexts.join('\n') + '\n' + inlineStyles;

      _iframe.onload = () => {
        _loading.style.display = 'none';
        _resizeFrame();
      };
      _iframe.srcdoc = `<!DOCTYPE html><html><head><meta charset="utf-8">
        <style>${allCss}
        body{margin:0;padding:8px 16px 20px;background:transparent;}
        .action-bar{display:none !important;}</style>
        </head><body>${card.outerHTML}</body></html>`;
    } catch (err) {
      console.error('Receipt preview failed:', err);
      _loading.innerHTML = 'Could not load receipt. Please try again.';
      if (typeof showToast === 'function') showToast('Could not load the receipt.', 'error');
    }
  };

  window.closeReceiptModal = function () {
    if (!_overlay) return;
    _overlay.classList.remove('open');
    document.body.style.overflow = '';
    _iframe.srcdoc = '';
    _currentBookingId = null;
  };
})();