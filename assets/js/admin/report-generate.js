/**
 * assets/js/admin/report-generate.js
 * Shared "Generate Report" modal controller.
 * Each report page calls initReportGenerator({ type, endpoint }) once on load.
 * type: 'financial' | 'booking' | 'occupancy'
 */
(function () {
  function pad(n) { return String(n).padStart(2, '0'); }
  function toISO(d) { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }

  window.initReportGenerator = function (opts) {
    const { type, endpoint = '../../endpoints/generate_report.php' } = opts;

    const overlay = document.getElementById('repgenOverlay');
    const openBtn = document.getElementById('repgenOpenBtn');
    const closeBtn = document.getElementById('repgenCloseBtn');
    const cancelBtn = document.getElementById('repgenCancelBtn');
    const generateBtn = document.getElementById('repgenGenerateBtn');
    const fromInput = document.getElementById('repgenFrom');
    const toInput = document.getElementById('repgenTo');
    const errorBox = document.getElementById('repgenError');
    const presets = document.querySelectorAll('.repgen-preset');
    const formatCards = document.querySelectorAll('.repgen-format');

    if (!overlay || !openBtn) return; // page didn't include the modal markup

    let selectedFormat = 'pdf';

    function today() { return new Date(); }

    function applyPreset(key) {
      const now = today();
      let from, to = now;
      switch (key) {
        case '7': from = new Date(now); from.setDate(from.getDate() - 6); break;
        case '30': from = new Date(now); from.setDate(from.getDate() - 29); break;
        case 'month': from = new Date(now.getFullYear(), now.getMonth(), 1); break;
        case 'year': from = new Date(now.getFullYear(), 0, 1); break;
        default: from = new Date(now.getFullYear(), now.getMonth(), 1);
      }
      fromInput.value = toISO(from);
      toInput.value = toISO(to);
      presets.forEach(p => p.classList.toggle('active', p.dataset.preset === key));
    }

    function clearPresetActive() {
      presets.forEach(p => p.classList.remove('active'));
    }

    function openModal() {
      overlay.classList.add('open');
      if (!fromInput.value || !toInput.value) applyPreset('month');
      hideError();
    }
    function closeModal() { overlay.classList.remove('open'); }
    function showError(msg) { errorBox.textContent = msg; errorBox.classList.add('show'); }
    function hideError() { errorBox.classList.remove('show'); }

    openBtn.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });

    presets.forEach(p => p.addEventListener('click', () => applyPreset(p.dataset.preset)));
    [fromInput, toInput].forEach(inp => inp.addEventListener('change', clearPresetActive));

    formatCards.forEach(card => {
      card.addEventListener('click', () => {
        formatCards.forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        selectedFormat = card.dataset.format;
      });
    });

    generateBtn.addEventListener('click', async () => {
      hideError();
      const from = fromInput.value;
      const to = toInput.value;
      if (!from || !to) { showError('Please choose both a start and end date.'); return; }
      if (from > to) { showError('Start date must be before the end date.'); return; }

      const originalHtml = generateBtn.innerHTML;
      generateBtn.disabled = true;
      generateBtn.innerHTML = '<span class="repgen-spinner"></span>Generating…';

      try {
        const url = `${endpoint}?type=${encodeURIComponent(type)}&format=${encodeURIComponent(selectedFormat)}&from=${from}&to=${to}`;
        const res = await fetch(url);
        if (!res.ok) {
          const text = await res.text();
          throw new Error(text || 'Failed to generate the report.');
        }
        const blob = await res.blob();
        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^"]+)"?/);
        const filename = match ? match[1] : `${type}-report.${selectedFormat}`;

        const blobUrl = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(blobUrl);

        closeModal();
        if (window.showToast) window.showToast('Report generated successfully.', 'success');
      } catch (err) {
        showError(err.message || 'Something went wrong generating the report.');
      } finally {
        generateBtn.disabled = false;
        generateBtn.innerHTML = originalHtml;
      }
    });
  };
})();