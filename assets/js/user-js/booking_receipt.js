async function downloadPDF() {
    const btn = document.getElementById('dlBtn');
    const status = document.getElementById('dlStatus');

    btn.disabled = true;
    btn.innerHTML = `
      <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;animation:spin .7s linear infinite">
        <circle cx="12" cy="12" r="10" stroke-opacity=".25"/>
        <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
      </svg>
      Generating…`;
    status.classList.add('visible');

    try {
      const card = document.getElementById('receiptCard');

      // Capture at 2× scale for crisp PDF
      const canvas = await html2canvas(card, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff',
        logging: false,
      });

      const { jsPDF } = window.jspdf;
      const imgData = canvas.toDataURL('image/png');

      // A4 dimensions in mm
      const pdfW = 210;
      const pdfH = (canvas.height * pdfW) / canvas.width;

      const pdf = new jsPDF({
        orientation: pdfH > pdfW ? 'portrait' : 'landscape',
        unit: 'mm',
        format: pdfH <= 297 ? 'a4' : [pdfW, pdfH],
      });

      pdf.addImage(imgData, 'PNG', 0, 0, pdfW, pdfH);
      pdf.save(FILENAME);

      // Reset button
      btn.disabled = false;
      btn.innerHTML = `
        <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Downloaded!`;
      status.classList.remove('visible');

      setTimeout(() => {
        btn.innerHTML = `
          <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Download PDF`;
      }, 3000);

    } catch (err) {
      console.error('PDF generation failed:', err);
      btn.disabled = false;
      btn.innerHTML = `
        <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5;">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Download PDF`;
      status.classList.remove('visible');
      alert('PDF generation failed. Try using Ctrl+P → Save as PDF instead.');
    }
  }

  // Auto-trigger on page load
  if (AUTO_DOWNLOAD) {
    window.addEventListener('load', () => {
      // Small delay so the page renders fully before capturing
      setTimeout(downloadPDF, 800);
    });
  }
