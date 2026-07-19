<?php
/**
 * includes/_report_generate_modal.php
 * Shared "Generate Report" modal markup for the admin report pages.
 * Include after setting: $repgen_title (string), and the page must load
 * assets/css/admin-css/report-generate.css + assets/js/admin/report-generate.js.
 */
$repgen_title = $repgen_title ?? 'Report';
?>
<div class="repgen-overlay" id="repgenOverlay">
  <div class="repgen-modal">
    <button class="repgen-close" id="repgenCloseBtn" type="button" aria-label="Close">&times;</button>
    <h3>Generate <?php echo htmlspecialchars($repgen_title); ?></h3>
    <p class="repgen-sub">Choose a date range and file format to generate a downloadable report.</p>

    <div class="repgen-presets">
      <button type="button" class="repgen-preset" data-preset="7">Last 7 days</button>
      <button type="button" class="repgen-preset" data-preset="30">Last 30 days</button>
      <button type="button" class="repgen-preset active" data-preset="month">This month</button>
      <button type="button" class="repgen-preset" data-preset="year">This year</button>
    </div>

    <div class="repgen-row">
      <div class="repgen-field">
        <label for="repgenFrom">From</label>
        <input type="date" id="repgenFrom">
      </div>
      <div class="repgen-field">
        <label for="repgenTo">To</label>
        <input type="date" id="repgenTo">
      </div>
    </div>

    <div class="repgen-field">
      <label>Format</label>
      <div class="repgen-formats">
        <div class="repgen-format active" data-format="pdf">
          <div class="repgen-format-icon">📄</div>
          <div class="repgen-format-label">PDF</div>
          <div class="repgen-format-desc">Summary report</div>
        </div>
        <div class="repgen-format" data-format="xlsx">
          <div class="repgen-format-icon">📊</div>
          <div class="repgen-format-label">Excel</div>
          <div class="repgen-format-desc">Full data</div>
        </div>
        <div class="repgen-format" data-format="csv">
          <div class="repgen-format-icon">📑</div>
          <div class="repgen-format-label">CSV</div>
          <div class="repgen-format-desc">Full data</div>
        </div>
      </div>
    </div>

    <div class="repgen-error" id="repgenError"></div>

    <div class="repgen-actions">
      <button type="button" class="btn btn-secondary" id="repgenCancelBtn">Cancel</button>
      <button type="button" class="btn btn-primary repgen-generate-btn" id="repgenGenerateBtn">Generate</button>
    </div>
  </div>
</div>